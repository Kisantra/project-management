<?php

namespace App\Livewire\Clients;

use App\Services\ClientManagement\ClientManagementClient;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Throwable;

/**
 * Daftar lampiran lead (kontrak, proposal) yang disimpan di Client Management,
 * dengan pratinjau inline. Daftar diambil ulang setiap kali pratinjau dibuka
 * atau "Muat ulang" ditekan, karena preview_url dari CM hanya berlaku 5 menit.
 * Dipakai di halaman Cek & proses (ClientIntakeResource\Pages\ProsesClientIntake).
 */
class IntakeContractPanel extends Component
{
    #[Locked]
    public ?int $cmLeadId = null;

    public array $documents = [];

    /** not-configured | unreachable | not-found | null */
    public ?string $error = null;

    #[Locked]
    public ?int $previewId = null;

    /** `record` dikirim otomatis oleh komponen form Livewire Filament; tidak dipakai. */
    public function mount(?int $cmLeadId = null, mixed $record = null): void
    {
        abort_unless(auth()->user()?->can('clients.*'), 403);

        $this->cmLeadId = $cmLeadId;
        $this->fetch();

        // Buka kontrak pertama, seperti yang paling sering dicari staf.
        $first = collect($this->documents)->firstWhere('kind', 'kontrak') ?? ($this->documents[0] ?? null);
        $this->previewId = $first && $first['preview_url'] ? $first['id'] : null;
    }

    public function preview(int $documentId): void
    {
        $this->fetch();

        $this->previewId = collect($this->documents)->contains('id', $documentId) ? $documentId : null;
    }

    public function reload(): void
    {
        $this->fetch();

        if (! collect($this->documents)->contains('id', $this->previewId)) {
            $this->previewId = null;
        }
    }

    public function closePreview(): void
    {
        $this->previewId = null;
    }

    public function getPreviewProperty(): ?array
    {
        return $this->previewId === null ? null : collect($this->documents)->firstWhere('id', $this->previewId);
    }

    public function placeholder(): string
    {
        return <<<'HTML'
            <div class="flex items-center gap-2 py-6 text-sm text-gray-500 dark:text-gray-400">
                <svg class="h-4 w-4 animate-spin" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                    <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3" class="opacity-25"></circle>
                    <path d="M22 12a10 10 0 0 0-10-10" stroke="currentColor" stroke-width="3" stroke-linecap="round"></path>
                </svg>
                Mengambil lampiran dari Client Management…
            </div>
            HTML;
    }

    protected function fetch(): void
    {
        abort_unless(auth()->user()?->can('clients.*'), 403);

        $this->documents = [];
        $this->error = null;

        $cm = app(ClientManagementClient::class);

        if ($this->cmLeadId === null || ! $cm->isConfigured()) {
            $this->error = 'not-configured';

            return;
        }

        try {
            $this->documents = $cm->documents($this->cmLeadId);
        } catch (RequestException $e) {
            $this->error = $e->response->status() === 404 ? 'not-found' : 'unreachable';
            Log::warning('Client Management documents request failed', ['cm_lead_id' => $this->cmLeadId, 'status' => $e->response->status()]);
        } catch (Throwable $e) {
            $this->error = 'unreachable';
            Log::warning('Client Management documents request failed', ['cm_lead_id' => $this->cmLeadId, 'error' => $e->getMessage()]);
        }
    }

    public static function formatSize(?int $bytes): ?string
    {
        if (! $bytes) {
            return null;
        }

        return $bytes >= 1048576
            ? number_format($bytes / 1048576, 1, ',', '.') . ' MB'
            : max(1, (int) round($bytes / 1024)) . ' KB';
    }

    public static function formatDate(?string $value): ?string
    {
        if (blank($value)) {
            return null;
        }

        try {
            return Carbon::parse($value)->timezone(config('app.timezone'))->locale('id')->translatedFormat('j M Y');
        } catch (Throwable) {
            return null;
        }
    }

    public function render()
    {
        return view('livewire.clients.intake-contract-panel', [
            'preview' => $this->preview,
        ]);
    }
}
