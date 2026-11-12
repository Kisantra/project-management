<?php

namespace App\Livewire\TaxReport\Pph;

use App\Models\Employee;
use App\Models\IncomeTax;
use App\Models\TaxReport;
use App\Support\TaxPeriod;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * PPh workspace of the tax report page: PPh 21 slips, the calculation,
 * employees and the SPT. Figures come straight from income_taxes on every
 * render (the old 5-minute cache made "Refresh" do nothing).
 */
class TaxReportPph extends Component
{
    /** Sub-tabs, as they appear in ?tab= */
    public const TABS = ['daftar', 'perhitungan', 'karyawan', 'spt'];

    /** jenis_pajak value => label in the calculation */
    public const KINDS = [
        'Pasal 21' => 'PPh 21',
        'Pasal 23' => 'PPh 23',
        'Pasal 4(2)' => 'PPh 4(2)',
        'Pasal 4 ayat 2' => 'PPh 4(2)',
    ];

    #[Locked]
    public int $taxReportId;

    /** Sub-tab open on first render (Alpine takes over afterwards). */
    public string $initialTab = 'daftar';

    public function mount(int $taxReportId): void
    {
        $this->taxReportId = $taxReportId;

        $tab = (string) request()->query('tab', '');
        $this->initialTab = request()->query('jenis') === 'pph' && in_array($tab, self::TABS, true) ? $tab : 'daftar';
    }

    #[Computed]
    public function taxReport(): TaxReport
    {
        return TaxReport::with('client')->findOrFail($this->taxReportId);
    }

    /**
     * Rows per kind of PPh (count, DPP, PPh) and the total. Values that are not a
     * known kind are kept apart, so a broken import shows up instead of vanishing.
     */
    #[Computed]
    public function breakdown(): array
    {
        $groups = IncomeTax::where('tax_report_id', $this->taxReportId)
            ->selectRaw('jenis_pajak, COUNT(*) as n, COALESCE(SUM(dasar_pengenaan_pajak), 0) as dpp, COALESCE(SUM(pajak_penghasilan), 0) as pph')
            ->groupBy('jenis_pajak')
            ->get();

        $rows = [];
        foreach ($groups as $group) {
            $label = self::KINDS[$group->jenis_pajak] ?? null;
            $key = $label ?? 'unknown';
            $rows[$key] ??= ['label' => $label ?? 'Jenis tidak dikenali', 'known' => (bool) $label, 'count' => 0, 'dpp' => 0.0, 'pph' => 0.0];
            $rows[$key]['count'] += (int) $group->n;
            $rows[$key]['dpp'] += (float) $group->dpp;
            $rows[$key]['pph'] += (float) $group->pph;
        }

        // PPh 21 first, then the other known kinds, unknown values last.
        uksort($rows, fn ($a, $b) => [$a === 'unknown', $a !== 'PPh 21', $a] <=> [$b === 'unknown', $b !== 'PPh 21', $b]);

        return [
            'rows' => array_values($rows),
            'pph21' => $rows['PPh 21'] ?? ['count' => 0, 'dpp' => 0.0, 'pph' => 0.0],
            'total' => [
                'count' => array_sum(array_column($rows, 'count')),
                'dpp' => array_sum(array_column($rows, 'dpp')),
                'pph' => array_sum(array_column($rows, 'pph')),
            ],
        ];
    }

    #[Computed]
    public function activeEmployees(): int
    {
        return Employee::where('client_id', $this->taxReport->client_id)->where('status', 'active')->count();
    }

    #[Computed]
    public function sptUploaded(): bool
    {
        return (bool) $this->taxReport->taxCalculationSummaries()->where('tax_type', 'pph')->value('bukti_lapor');
    }

    public function periodLabel(): string
    {
        return TaxPeriod::label($this->taxReport->month, $this->taxReport->year ?? $this->taxReport->created_at->year);
    }

    /** Computed values are rebuilt on every request, so a re-render is all a refresh needs. */
    #[On('tax-report-changed')]
    #[On('spt-updated')]
    #[On('refreshPphCalculations')]
    #[On('pphDataUpdated')]
    public function refreshData(): void
    {
    }

    public function render(): View
    {
        return view('livewire.tax-report.pph.tax-report-pph');
    }
}
