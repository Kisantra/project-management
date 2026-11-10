<?php

namespace App\Services\ClientManagement;

use App\Filament\Resources\ClientResource;
use App\Filament\Resources\ProjectResource;
use App\Jobs\SendHandoffToClientManagement;
use App\Models\Client;
use App\Models\ClientIntake;
use App\Models\Project;
use App\Models\User;
use App\Services\Clients\DefaultClientTeam;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Arr;

/**
 * Alur klien masuk dari Client Management (CM) ke PM. API hanya menyimpan
 * data; klien dan proyek dibuat saat staf memproses di halaman Klien Masuk.
 */
class ClientIntakeService
{
    /** Bentuk badan dari CM -> client_subtype PM. UD tidak punya padanan. */
    public const ENTITY_SUBTYPES = [
        'PT' => 'Perseroan Terbatas (PT)',
        'CV' => 'Perseroan Komanditer (CV)',
        'Koperasi' => 'Koperasi',
    ];

    /** Layanan CM -> toggle kontrak PM (hanya saran). */
    public const SERVICE_CONTRACTS = [
        '/\bPPN\b/i' => 'ppn_contract',
        '/PPh\s*21|payroll/i' => 'pph_contract',
        '/PPh\s*Badan/i' => 'pph_badan_contract',
        '/unifikasi|bukti\s*potong|bupot/i' => 'bupot_contract',
    ];

    public function __construct(protected IntakeNotifier $notifier)
    {
    }

    /**
     * Terima lead yang Deal di CM. Idempoten per cm_lead_id: kiriman ulang
     * tidak membuat baris baru.
     */
    public function receive(array $data): ClientIntake
    {
        $existing = ClientIntake::query()->where('cm_lead_id', $data['cm_lead_id'])->first();

        if ($existing) {
            return $this->resend($existing, $data);
        }

        try {
            $intake = ClientIntake::create(['cm_lead_id' => $data['cm_lead_id']] + $this->snapshot($data));
        } catch (UniqueConstraintViolationException $e) {
            // Dua kiriman paralel untuk lead yang sama: yang kalah memakai hasil yang menang.
            $existing = ClientIntake::query()->where('cm_lead_id', $data['cm_lead_id'])->first();

            if (! $existing) {
                throw $e;
            }

            return $this->resend($existing, $data);
        }

        $this->notifier->received($intake);

        return $intake;
    }

    /** Lead mundur dari Deal atau ditutup di CM. Klien/proyek tidak pernah dihapus. */
    public function withdraw(ClientIntake $intake, array $data): void
    {
        $changes = [
            'withdrawn_reason' => $data['reason'],
            'withdrawn_detail' => $data['detail'] ?? null,
            'withdrawn_note' => $data['note'] ?? null,
        ];

        // Kiriman ulang yang sama tidak memicu notifikasi kedua; perubahan
        // (mis. "Mundur ke Proposal" lalu "Ditolak") tetap diberitahukan.
        if ($intake->isWithdrawn()
            && $intake->withdrawn_reason === $changes['withdrawn_reason']
            && $intake->withdrawn_detail === $changes['withdrawn_detail']) {
            return;
        }

        $intake->update($changes + ['withdrawn_at' => $intake->withdrawn_at ?? now()]);

        $this->notifier->withdrawn($intake);
    }

    /**
     * Buat klien baru atau perbarui klien yang ditautkan dengan isian staf.
     * Dipanggil di dalam transaksi pemrosesan.
     */
    public function saveClient(?Client $linked, array $attributes, array $contact): Client
    {
        if ($linked) {
            $linked->update($attributes);
            $client = $linked;
        } else {
            // Event model tetap jalan: staf sedang login, jadi log aktivitas
            // "klien baru" dan tugas hariannya sama seperti membuat klien manual.
            $client = Client::create($attributes);
            DefaultClientTeam::assign($client);
        }

        $this->savePrimaryContact($client, $contact);

        return $client;
    }

    /** Perbarui kontak utama yang ada, atau buat bila belum ada. Nama kosong = lewati. */
    public function savePrimaryContact(Client $client, array $contact): void
    {
        $values = array_map(
            fn ($value) => is_string($value) ? (trim($value) ?: null) : $value,
            Arr::only($contact, ['name', 'position', 'email', 'phone', 'mobile']),
        );

        if (blank($values['name'] ?? null)) {
            return;
        }

        $existing = $client->contacts()->where('type', 'primary')->orderByDesc('is_active')->orderBy('id')->first();

        $existing
            ? $existing->update($values)
            : $client->contacts()->create($values + ['type' => 'primary', 'is_active' => true]);
    }

    /** Tandai selesai. Dipanggil di dalam transaksi pemrosesan. */
    public function complete(ClientIntake $intake, Client $client, Project $project, bool $linked, User $by): void
    {
        $intake->update([
            'client_id' => $client->id,
            'project_id' => $project->id,
            'matched' => $linked,
            'completed_at' => now(),
            'completed_by' => $by->id,
            'handoff_error' => null,
        ]);

        $client->logActivity(
            'client_intake_processed',
            ($linked ? "Deal baru dari Client Management ditautkan ke klien '{$client->name}'" : "Klien '{$client->name}' dibuat dari Client Management")
                . ", proyek '{$project->name}'"
        );
    }

    /** Kabari CM (lewat antrean) bahwa klien dan proyek sudah dibuat di PM. */
    public function dispatchHandoff(ClientIntake $intake): void
    {
        $intake->loadMissing('completedBy', 'project');

        SendHandoffToClientManagement::dispatch($intake->id, [
            'status' => 'completed',
            'pm_client_id' => $intake->client_id,
            'completed_by' => $intake->completedBy?->name,
            'completed_at' => $intake->completed_at?->toIso8601String(),
            'client_url' => ClientResource::getUrl('view', ['record' => $intake->client_id], panel: 'admin'),
            'matched' => $intake->matched,
            'project_name' => $intake->project?->name,
            'project_url' => $intake->project_id
                ? ProjectResource::getUrl('view', ['record' => $intake->project_id], panel: 'admin')
                : null,
        ]);
    }

    /**
     * Cocokkan klien yang sudah ada: NPWP (angka saja) lebih dulu, lalu email.
     *
     * @return array{0: ?Client, 1: ?string} klien dan dasar pencocokan (npwp|email)
     */
    public function findMatchingClient(?string $npwp, ?string $email): array
    {
        $digits = self::normalizeNpwp($npwp);

        if ($digits !== '') {
            // Format NPWP di PM tidak seragam (titik, strip, spasi), jadi
            // perbandingan dilakukan di PHP. Jumlah klien ratusan, aman.
            $id = Client::query()
                ->whereNotNull('NPWP')
                ->where('NPWP', '<>', '')
                ->pluck('NPWP', 'id')
                ->search(fn ($value) => self::normalizeNpwp($value) === $digits);

            if ($id !== false) {
                return [Client::find($id), 'npwp'];
            }
        }

        $email = mb_strtolower(trim((string) $email));

        if ($email !== '' && $client = Client::query()->whereRaw('LOWER(email) = ?', [$email])->first()) {
            return [$client, 'email'];
        }

        return [null, null];
    }

    /**
     * Angka saja. NPWP 15 digit lama disetarakan dengan format 16 digit
     * (awalan 0, PMK 112/2022) supaya "01.234.567.8-901.000" cocok dengan
     * "0012345678901000".
     */
    public static function normalizeNpwp(?string $npwp): string
    {
        $digits = preg_replace('/\D+/', '', (string) $npwp);

        return strlen($digits) === 15 ? '0' . $digits : $digits;
    }

    /**
     * CM hanya punya satu kolom "Telepon / WhatsApp". Nomor seluler Indonesia
     * (08…, +628…, 628…) masuk ke kolom HP/WhatsApp PM, selain itu ke Telepon.
     *
     * @return array{phone: ?string, mobile: ?string}
     */
    public static function splitPhone(?string $number): array
    {
        $number = trim((string) $number);

        if ($number === '') {
            return ['phone' => null, 'mobile' => null];
        }

        $compact = preg_replace('/[\s\-\.\(\)]/', '', $number);

        return preg_match('/^(\+?62|0)8\d{7,12}$/', $compact)
            ? ['phone' => null, 'mobile' => $number]
            : ['phone' => $number, 'mobile' => null];
    }

    /** @return list<string> kolom kontrak yang disarankan dari nama layanan CM */
    public static function suggestedContracts(?string $service): array
    {
        if (blank($service)) {
            return [];
        }

        return array_values(array_filter(
            self::SERVICE_CONTRACTS,
            fn (string $field, string $pattern) => (bool) preg_match($pattern, $service),
            ARRAY_FILTER_USE_BOTH,
        ));
    }

    public static function subtypeForEntity(?string $entity): ?string
    {
        return self::ENTITY_SUBTYPES[$entity] ?? null;
    }

    protected function resend(ClientIntake $intake, array $data): ClientIntake
    {
        // Sudah diproses: data PM tidak lagi mengikuti CM.
        if ($intake->isCompleted() && ! $intake->isWithdrawn()) {
            return $intake;
        }

        $reactivated = $intake->isWithdrawn();

        $changes = [
            'withdrawn_at' => null,
            'withdrawn_reason' => null,
            'withdrawn_detail' => null,
            'withdrawn_note' => null,
        ];

        // Yang belum diproses ikut data terbaru dari CM (lead bisa diedit
        // sebelum dikirim ulang). Yang sudah diproses hanya dibuka kembali.
        if (! $intake->isCompleted()) {
            $changes += $this->snapshot($data);
        }

        $intake->update($changes);

        if ($reactivated) {
            $this->notifier->reactivated($intake);
        }

        return $intake;
    }

    protected function snapshot(array $data): array
    {
        [$suggested, $by] = $this->findMatchingClient($data['npwp'] ?? null, $data['email'] ?? null);

        return [
            'name' => trim($data['name']),
            'entity' => $data['entity'] ?? null,
            'service' => $data['service'] ?? null,
            'deal_value' => $data['value'] ?? null,
            'marketing_owner' => $data['marketing_owner'] ?? null,
            'deal_at' => $data['deal_at'] ?? null,
            'lead_url' => $data['lead_url'] ?? null,
            'payload' => $data,
            'suggested_client_id' => $suggested?->id,
            'suggested_by' => $by,
        ];
    }
}
