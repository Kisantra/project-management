<?php

namespace App\Livewire\TaxReport\Dashboard;

use App\Models\User;
use App\Services\TaxDeadlineService;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Session;
use Livewire\Component;

/**
 * Klien dengan peredaran bruto terbesar, beserta penanggung jawabnya.
 *
 * Menjawab dua pertanyaan sekaligus: klien mana yang paling besar (untuk
 * prioritas), dan siapa yang menangani laporan pajaknya.
 *
 * Bruto = SUM(dpp) Faktur Keluaran non-revisi. Rentangnya bisa di-switch:
 *  - 'year'  : akumulasi setahun (default). Peringkat lebih stabil dan
 *              sesuai makna PKP/PP 23.
 *  - 'month' : hanya masa terpilih, mengikuti filter periode dashboard.
 * Filter klien juga berlaku; filter jenis/status tidak berlaku pada bruto.
 */
class TopClientsRevenue extends Component
{
    use Concerns\ReadsDashboardFilters;

    /** Jumlah baris sebelum daftar perlu dibuka penuh. */
    public const PREVIEW_LIMIT = 5;

    public bool $showAll = false;

    /** Rentang peredaran bruto: 'year' (tahunan) atau 'month' (per masa). */
    #[Session(key: 'tp-topclients-scope')]
    public string $scope = 'year';

    public function mount(TaxDeadlineService $deadlines): void
    {
        $this->hydrateFiltersFromRequest($deadlines);
    }

    public function setScope(string $scope): void
    {
        $this->scope = in_array($scope, ['year', 'month'], true) ? $scope : 'year';
        $this->showAll = false;
    }

    protected function onFiltersUpdated(): void
    {
        $this->showAll = false;
    }

    public function toggleShowAll(): void
    {
        $this->showAll = ! $this->showAll;
    }

    public function render()
    {
        $period = $this->periodDate();
        $year = $period->year;
        // tax_reports.month disimpan sebagai nama bulan Inggris ("April").
        $monthName = $period->format('F');
        $byMonth = $this->scope === 'month';
        $limit = $this->showAll ? 20 : self::PREVIEW_LIMIT;

        // Kueri 1: peringkat klien menurut peredaran bruto. Rentangnya setahun,
        // atau dibatasi ke masa terpilih bila scope = 'month'.
        $ranked = DB::table('invoices as i')
            ->join('tax_reports as tr', 'i.tax_report_id', '=', 'tr.id')
            ->join('clients as c', 'tr.client_id', '=', 'c.id')
            ->where('tr.year', $year)
            ->when($byMonth, fn ($q) => $q->where('tr.month', $monthName))
            ->where('c.status', 'Active')
            ->where(fn ($q) => $q
                ->where('c.ppn_contract', true)
                ->orWhere('c.pph_contract', true)
                ->orWhere('c.bupot_contract', true)
                ->orWhere('c.pph_badan_contract', true))
            ->where('i.type', 'Faktur Keluaran')
            ->where('i.is_revision', false)
            ->when($this->clientId, fn ($q) => $q->where('c.id', $this->clientId))
            ->groupBy('c.id', 'c.name', 'c.logo')
            ->selectRaw('c.id, c.name, c.logo, SUM(i.dpp) as bruto, COUNT(*) as faktur')
            ->orderByDesc('bruto')
            ->limit($limit)
            ->get();

        $clientIds = $ranked->pluck('id')->all();

        // Kueri 2: penanggung jawab tiap klien, dalam satu kueri untuk seluruh
        // klien di daftar. Modus per klien diselesaikan di PHP, jadi tidak ada
        // subkueri per baris.
        $handlers = $this->resolveHandlers($clientIds, $year, $byMonth ? $monthName : null);

        $rows = $ranked->map(fn ($r) => [
            'id' => $r->id,
            'name' => $r->name,
            'logo' => $r->logo,
            'bruto' => (float) $r->bruto,
            'faktur' => (int) $r->faktur,
            'handler' => $handlers[$r->id] ?? null,
            'url' => route('filament.admin.resources.clients.view', ['record' => $r->id]),
        ]);

        return view('livewire.tax-report.dashboard.top-clients-revenue', [
            'rows' => $rows,
            'scope' => $this->scope,
            // Label rentang: "April 2026" (per masa) atau "2026" (tahunan).
            'periodLabel' => $byMonth
                ? app(TaxDeadlineService::class)->periodLabel($period)
                : (string) $year,
            'hasMore' => ! $this->showAll && $ranked->count() >= self::PREVIEW_LIMIT,
        ]);
    }

    /**
     * Penanggung jawab utama per klien: user yang paling banyak menginput
     * faktur klien itu pada rentang aktif (setahun, atau masa bila $monthName
     * diberikan). Faktur apa pun dihitung sebagai kerja, bukan hanya Faktur
     * Keluaran yang masuk hitungan bruto.
     *
     * @param  array<int>  $clientIds
     * @return array<int, array{name: string, avatar: ?string, others: int}>
     */
    protected function resolveHandlers(array $clientIds, int $year, ?string $monthName = null): array
    {
        if ($clientIds === []) {
            return [];
        }

        // (client_id, user_id, jumlah) untuk semua klien sekaligus.
        $counts = DB::table('invoices as i')
            ->join('tax_reports as tr', 'i.tax_report_id', '=', 'tr.id')
            ->whereIn('tr.client_id', $clientIds)
            ->where('tr.year', $year)
            ->when($monthName, fn ($q) => $q->where('tr.month', $monthName))
            ->whereNotNull('i.created_by')
            ->groupBy('tr.client_id', 'i.created_by')
            ->selectRaw('tr.client_id, i.created_by, COUNT(*) as n')
            ->get();

        $userNames = User::whereIn('id', $counts->pluck('created_by')->unique())
            ->get(['id', 'name', 'avatar_url'])
            ->keyBy('id');

        return $counts
            ->groupBy('client_id')
            ->map(function ($group) use ($userNames) {
                $sorted = $group->sortByDesc('n')->values();
                $topUser = $userNames->get($sorted->first()->created_by);

                return [
                    'name' => $topUser?->name ?? 'Pengguna dihapus',
                    'avatar' => $topUser?->avatar_url,
                    // Berapa user lain yang juga menyentuh klien ini.
                    'others' => $sorted->count() - 1,
                ];
            })
            ->all();
    }

    public function formatBruto(float $amount): string
    {
        return match (true) {
            $amount >= 1_000_000_000 => 'Rp ' . number_format($amount / 1_000_000_000, 1, ',', '.') . ' M',
            $amount >= 1_000_000 => 'Rp ' . number_format($amount / 1_000_000, 0, ',', '.') . ' Jt',
            default => 'Rp ' . number_format($amount, 0, ',', '.'),
        };
    }

    public function formatBrutoFull(float $amount): string
    {
        return 'Rp ' . number_format($amount, 0, ',', '.');
    }

    /** Inisial untuk fallback avatar. */
    public function initials(string $name): string
    {
        $parts = preg_split('/\s+/', trim($name));
        $first = mb_substr($parts[0] ?? '', 0, 1);
        $second = count($parts) > 1 ? mb_substr(end($parts), 0, 1) : '';

        return mb_strtoupper($first . $second);
    }
}
