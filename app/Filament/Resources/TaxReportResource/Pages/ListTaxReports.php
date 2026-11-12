<?php

namespace App\Filament\Resources\TaxReportResource\Pages;

use App\Filament\Resources\TaxReportResource;
use App\Models\TaxReport;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Computed;

class ListTaxReports extends ListRecords
{
    protected static string $resource = TaxReportResource::class;

    protected static string $view = 'filament.pages.tax-report.list-tax-reports';

    /** Months as stored in tax_reports.month, in calendar order. */
    public const MONTHS = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];

    public const MONTHS_ID = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];

    /** Tax types shown as status columns: summary tax_type => client contract flag. */
    public const TAX_TYPES = [
        'ppn' => ['label' => 'PPN', 'contract' => 'ppn_contract', 'action' => 'update_ppn_status'],
        'pph' => ['label' => 'PPh', 'contract' => 'pph_contract', 'action' => 'update_pph_status'],
        'bupot' => ['label' => 'Bupot', 'contract' => 'bupot_contract', 'action' => 'update_bupot_status'],
        'pph_badan' => ['label' => 'PPh Badan', 'contract' => 'pph_badan_contract', 'action' => 'update_pph_badan_status'],
    ];

    /**
     * Period filter from <x-kis.month-picker>: one year and any number of
     * months (1-12). No months means the whole year.
     */
    public array $period = ['year' => null, 'months' => []];

    public function mount(): void
    {
        parent::mount();

        $this->period = ['year' => now()->year, 'months' => []];
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('Buat Laporan Pajak')
                ->icon('heroicon-o-plus'),
        ];
    }

    public function getSubheading(): ?string
    {
        $year = $this->periodYear();
        $count = collect($this->monthCounts[$year] ?? [])->sum();

        return "{$count} laporan di {$year}";
    }

    public function periodYear(): int
    {
        return (int) ($this->period['year'] ?? now()->year);
    }

    /** @return int[] sanitised month numbers */
    public function periodMonths(): array
    {
        return collect($this->period['months'] ?? [])
            ->map(fn ($month) => (int) $month)
            ->filter(fn (int $month) => $month >= 1 && $month <= 12)
            ->unique()
            ->sort()
            ->values()
            ->all();
    }

    /**
     * Report counts per year and month for the month picker, from one grouped
     * query: [year => [month => count]]. The current year is always present.
     */
    #[Computed]
    public function monthCounts(): array
    {
        $counts = [now()->year => array_fill_keys(range(1, 12), 0)];

        TaxReport::query()
            ->selectRaw('year, month, count(*) as total')
            ->whereNotNull('year')
            ->groupBy('year', 'month')
            ->get()
            ->each(function ($row) use (&$counts) {
                $month = array_search($row->month, self::MONTHS, true);
                if ($month === false) {
                    return;
                }
                $counts[(int) $row->year] ??= array_fill_keys(range(1, 12), 0);
                $counts[(int) $row->year][$month + 1] = (int) $row->total;
            });

        krsort($counts);

        return $counts;
    }

    /**
     * Per-month totals for the group headers, over every filtered record (not
     * just the current page): ["2026-10" => ['total' => n, 'pending' => n]].
     */
    #[Computed]
    public function monthSummary(): array
    {
        $ids = $this->getFilteredTableQuery()->reorder()->select('tax_reports.id');

        return TaxReport::query()
            ->whereIn('id', $ids)
            ->with([
                'client:id,ppn_contract,pph_contract,bupot_contract,pph_badan_contract',
                'taxCalculationSummaries:id,tax_report_id,tax_type,report_status',
            ])
            ->get(['id', 'year', 'month', 'client_id'])
            ->groupBy(fn (TaxReport $report) => self::periodKey($report))
            ->map(fn ($reports) => [
                'total' => $reports->count(),
                'pending' => $reports->filter(fn (TaxReport $report) => collect(self::slots($report))->contains('state', 'pending'))->count(),
            ])
            ->all();
    }

    public static function periodKey(TaxReport $report): string
    {
        $month = array_search($report->month, self::MONTHS, true);

        return sprintf('%04d-%02d', (int) $report->year, $month === false ? 0 : $month + 1);
    }

    public static function periodLabel(TaxReport $report): string
    {
        $month = array_search($report->month, self::MONTHS, true);

        return ($month === false ? $report->month : self::MONTHS_ID[$month]) . ' ' . $report->year;
    }

    public static function isCurrentPeriod(TaxReport $report): bool
    {
        return (int) $report->year === now()->year && $report->month === now()->format('F');
    }

    /**
     * Status per tax type for one report:
     *  - none:    the client has no contract for this tax
     *  - done:    reported (with date and whether it is paid)
     *  - pending: contracted but not reported yet
     *
     * @return array<string, array{label: string, state: string, date: ?string, paid: bool, title: string}>
     */
    public static function slots(TaxReport $report): array
    {
        $summaries = $report->taxCalculationSummaries->keyBy('tax_type');

        return collect(self::TAX_TYPES)->map(function (array $type, string $key) use ($report, $summaries) {
            $summary = $summaries->get($key);
            $hasContract = (bool) ($report->client?->{$type['contract']} ?? false);

            if (! $hasContract) {
                return ['label' => $type['label'], 'state' => 'none', 'date' => null, 'paid' => false, 'title' => "{$type['label']}: tidak ada kontrak"];
            }

            if ($summary?->report_status !== 'Sudah Lapor') {
                return ['label' => $type['label'], 'state' => 'pending', 'date' => null, 'paid' => false, 'title' => "{$type['label']} belum dilaporkan"];
            }

            $date = $summary->reported_at ? Carbon::parse($summary->reported_at)->locale('id') : null;
            $paid = ($summary->bayar_status ?? null) === 'Sudah Bayar';

            return [
                'label' => $type['label'],
                'state' => 'done',
                'date' => $date?->translatedFormat('j M'),
                'paid' => $paid,
                'title' => "{$type['label']} sudah dilaporkan" . ($date ? ' pada ' . $date->translatedFormat('j F Y') : '') . ($paid ? ' · sudah bayar' : ''),
            ];
        })->all();
    }

    protected function getTableQuery(): Builder
    {
        $query = parent::getTableQuery();

        $query->where('year', $this->periodYear());

        if ($months = $this->periodMonths()) {
            $query->whereIn('month', array_map(fn (int $month) => self::MONTHS[$month - 1], $months));
        }

        return $query;
    }

    public function updatedPeriod(): void
    {
        $this->period = ['year' => $this->periodYear(), 'months' => $this->periodMonths()];
        unset($this->monthSummary);
        $this->resetTable();
    }

    public function clearPeriod(): void
    {
        $this->period = ['year' => $this->periodYear(), 'months' => []];
        $this->updatedPeriod();
    }

    public function resetAllFilters(): void
    {
        $this->clearPeriod();
        $this->resetTableFiltersForm();
    }

    /** Label for the period chip, e.g. "Sep – Nov 2026" or "Jan, Mar 2026". */
    public function periodSummary(): ?string
    {
        $months = $this->periodMonths();
        $year = $this->periodYear();
        $short = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];

        if ($months === []) {
            return null;
        }

        if (count($months) === 12) {
            return "Setahun {$year}";
        }

        if (count($months) === 1) {
            return self::MONTHS_ID[$months[0] - 1] . " {$year}";
        }

        $contiguous = $months === range($months[0], end($months));

        if ($contiguous) {
            return $short[$months[0] - 1] . ' – ' . $short[end($months) - 1] . " {$year}";
        }

        return count($months) <= 3
            ? implode(', ', array_map(fn ($month) => $short[$month - 1], $months)) . " {$year}"
            : count($months) . " bulan · {$year}";
    }
}
