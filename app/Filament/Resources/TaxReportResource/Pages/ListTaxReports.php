<?php

namespace App\Filament\Resources\TaxReportResource\Pages;

use App\Filament\Resources\TaxReportResource;
use App\Models\TaxReport;
use App\Services\TaxDeadlineService;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;

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

    /**
     * The period mirrored in the query string: ?tahun=2026&bulan=9,10, with
     * bulan=semua for the whole year. Without parameters the page opens on
     * defaultPeriod(), so the sidebar link always lands there.
     */
    #[Url(as: 'tahun')]
    public ?int $tahun = null;

    #[Url(as: 'bulan')]
    public ?string $bulan = null;

    public function mount(): void
    {
        parent::mount();

        $this->period = $this->tahun
            ? ['year' => $this->tahun, 'months' => self::parseMonths($this->bulan)]
            : self::defaultPeriod();

        $this->normalizePeriod();
    }

    /**
     * The period being reported this month, i.e. last month (October while it
     * is November), as on the dashboard's tax deadline card.
     */
    public static function defaultPeriod(): array
    {
        $period = app(TaxDeadlineService::class)->periodFor(now());

        return ['year' => $period->year, 'months' => [$period->month]];
    }

    public function isDefaultPeriod(): bool
    {
        $default = self::defaultPeriod();

        return $this->periodYear() === $default['year'] && $this->periodMonths() === $default['months'];
    }

    /** @return int[] month numbers from the ?bulan= value; "semua" or empty means the whole year */
    public static function parseMonths(?string $value): array
    {
        if (blank($value) || $value === 'semua') {
            return [];
        }

        return array_map('intval', explode(',', $value));
    }

    /**
     * Filament's deferred table fires wire:init a second time after the first
     * response, which re-rendered (and re-queried) the whole list. Once the
     * table is loaded there is nothing to render.
     */
    public function loadTable(): void
    {
        if ($this->isTableLoaded) {
            $this->skipRender();

            return;
        }

        parent::loadTable();
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
        $months = $this->periodMonths();
        $counts = $this->monthCounts[$year] ?? [];
        $count = $months === []
            ? array_sum($counts)
            : collect($months)->sum(fn (int $month) => $counts[$month] ?? 0);
        $label = ($months === [] || count($months) === 12) ? $year : $this->periodSummary();

        return "{$count} laporan di {$label}";
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
     * Only the months on the current page get a header, so only those are
     * loaded, not every report of a whole-year selection.
     */
    #[Computed]
    public function monthSummary(): array
    {
        $records = $this->getTableRecords();

        $periods = collect($records instanceof Collection ? $records : $records->items())
            ->map(fn (TaxReport $report) => [(int) $report->year, $report->month])
            ->unique()
            ->values();

        if ($periods->isEmpty()) {
            return [];
        }

        $ids = $this->getFilteredTableQuery()
            ->reorder()
            ->where(function (Builder $query) use ($periods) {
                foreach ($periods as [$year, $month]) {
                    $query->orWhere(fn (Builder $query) => $query->where('tax_reports.year', $year)->where('tax_reports.month', $month));
                }
            })
            ->select('tax_reports.id');

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
        $this->normalizePeriod();
        unset($this->monthSummary);
        // Not resetTable(): that also resets the filter form, so picking another
        // month used to drop filters such as "PPN belum lapor".
        $this->resetPage();
        $this->flushCachedTableRecords();
    }

    /** Sanitises the period and mirrors it into ?tahun= / ?bulan=. */
    protected function normalizePeriod(): void
    {
        $this->period = ['year' => $this->periodYear(), 'months' => $this->periodMonths()];
        $this->tahun = $this->period['year'];
        $this->bulan = $this->period['months'] === [] ? 'semua' : implode(',', $this->period['months']);
    }

    /** Removes the month restriction: the whole selected year. */
    public function clearPeriod(): void
    {
        $this->period = ['year' => $this->periodYear(), 'months' => []];
        $this->updatedPeriod();
    }

    public function resetAllFilters(): void
    {
        $this->period = self::defaultPeriod();
        $this->updatedPeriod();
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
