<?php

namespace App\Livewire\Client\Panel;

use App\Livewire\Client\Panel\Concerns\UsesCurrentClient;
use App\Models\Client;
use App\Models\TaxCalculationSummary;
use App\Models\TaxReport;
use App\Services\TaxReport\ReportOverview;
use App\Support\TaxPeriod;
use App\Support\Ui\Tone;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Laporan Pajak in the client portal (docs/mockups/client-portal-redesign.html):
 * the list of reported SPT, and the detail of one tax period opened from it.
 *
 * The open period and tax type live in the URL (?masa=2026-01&jenis=ppn), so
 * a reload or a shared link lands on the same detail. Both are read from the
 * browser, so they only ever select among the selected company's own reports.
 */
class TaxReportTab extends Component
{
    use UsesCurrentClient;

    /** Labels of the SPT list, by summary tax_type. */
    public const SPT_LABELS = [
        'ppn' => 'SPT Masa PPN',
        'pph' => 'SPT Masa PPh 21',
        'bupot' => 'SPT Masa PPh Unifikasi',
        'pph_badan' => 'SPT PPh Badan',
    ];

    /** What a tax-type card counts, by tax type. */
    private const COUNTS = [
        'ppn' => ['invoices_count', 'Faktur'],
        'pph' => ['income_taxs_count', 'Transaksi'],
        'bupot' => ['bupots_count', 'Bupot'],
    ];

    /** Open period as "YYYY-MM"; empty shows the SPT list. */
    #[Url(as: 'masa', except: '', history: true)]
    public string $period = '';

    /** Open tax type in the detail (ppn, pph, bupot, pph_badan). */
    #[Url(as: 'jenis', except: 'ppn')]
    public string $type = 'ppn';

    // SPT list: paging, filters, sort and grouping
    public int $sptPerPage = 10;
    public int $sptPage = 1;
    public string $sptSearch = '';
    public array $sptJenis = [];
    public string $sptYear = '';
    public string $sptSort = 'masa';      // masa | jenis | nominal | status
    public string $sptSortDir = 'desc';   // asc | desc
    public string $sptGroupBy = 'jenis';  // jenis | tahun | status | none

    #[Computed]
    public function client(): ?Client
    {
        return $this->selectedClientId
            ? Client::query()
                ->select(['id', 'name', 'ppn_contract', 'pph_contract', 'bupot_contract', 'pph_badan_contract'])
                ->find($this->selectedClientId)
            : null;
    }

    /** Every report of the company with its summaries and data counts, oldest period first (one query). */
    #[Computed]
    public function reports(): Collection
    {
        if (! $this->selectedClientId) {
            return collect();
        }

        return TaxReport::query()
            ->where('client_id', $this->selectedClientId)
            ->with(['taxCalculationSummaries:id,tax_report_id,tax_type,report_status,no_activity,reported_at,bukti_lapor,nomor_bukti_lapor'])
            ->withCount(['invoices', 'incomeTaxs', 'bupots'])
            ->get(['id', 'client_id', 'month', 'year', 'created_at'])
            ->each(function (TaxReport $report) {
                $report->setAttribute('period_year', (int) ($report->year ?: $report->created_at?->year));
                $report->setAttribute('period_month', TaxPeriod::index($report->month));
            })
            ->sortBy(fn (TaxReport $report) => $report->period_year * 100 + $report->period_month)
            ->values();
    }

    /** The open report, or null when the URL holds no (valid) period of this company. */
    #[Computed]
    public function report(): ?TaxReport
    {
        if (! preg_match('/^(\d{4})-(\d{2})$/', $this->period, $match)) {
            return null;
        }

        return $this->reports
            ->first(fn (TaxReport $report) => $report->period_year === (int) $match[1] && $report->period_month === (int) $match[2]);
    }

    /** @return list<string> tax types the company has contracted */
    #[Computed]
    public function contracted(): array
    {
        $client = $this->client;

        if (! $client) {
            return [];
        }

        return array_values(array_filter(
            array_keys(ReportOverview::TYPES),
            fn (string $type) => (bool) $client->{ReportOverview::TYPES[$type][1]},
        ));
    }

    private function isLocked(string $type): bool
    {
        return in_array($type, ReportOverview::LOCKABLE, true) && ! in_array($type, $this->contracted, true);
    }

    /** reported | no_activity | pending for a contracted type, not_contracted otherwise. */
    private function statusFor(TaxReport $report, string $type): string
    {
        if (! in_array($type, $this->contracted, true)) {
            return ReportOverview::NOT_CONTRACTED;
        }

        return ReportOverview::statusOf($report->taxCalculationSummaries->firstWhere('tax_type', $type));
    }

    /** Header, period navigation and tax-type cards of the open period. */
    #[Computed]
    public function detail(): ?array
    {
        $report = $this->report;

        if (! $report) {
            return null;
        }

        $contracted = $this->contracted;
        $statuses = array_map(fn (string $type) => $this->statusFor($report, $type), $contracted);
        $done = count(array_filter($statuses, fn (string $status) => $status !== ReportOverview::PENDING));
        $total = count($contracted);

        $progress = match (true) {
            $total === 0 => ['label' => 'Belum ada kontrak pajak', 'tone' => Tone::NEUTRAL],
            $done === $total => ['label' => 'Semua sudah lapor', 'tone' => Tone::BLUE],
            $done === 0 => ['label' => 'Belum lapor', 'tone' => Tone::RED],
            default => ['label' => "{$done}/{$total} sudah lapor", 'tone' => Tone::RED],
        };

        $cards = [];
        foreach (ReportOverview::TYPES as $type => [$label]) {
            $status = $this->statusFor($report, $type);
            $summary = $report->taxCalculationSummaries->firstWhere('tax_type', $type);
            [$countColumn, $unit] = self::COUNTS[$type] ?? [null, null];

            $cards[$type] = [
                'type' => $type,
                'label' => $label,
                'locked' => $this->isLocked($type),
                'count' => $countColumn ? (int) $report->{$countColumn} : null,
                'unit' => $unit,
                'status' => $status,
                'statusLabel' => ReportOverview::statusLabel($status),
                'tone' => ReportOverview::statusTone($status),
                'reportedOn' => TaxPeriod::date($summary?->reported_at),
            ];
        }

        return [
            'label' => TaxPeriod::label($report->period_month, $report->period_year),
            'year' => $report->period_year,
            'month' => $report->period_month,
            'progress' => $progress,
            'counts' => [
                'ppn' => (int) $report->invoices_count,
                'pph' => (int) $report->income_taxs_count,
                'bupot' => (int) $report->bupots_count,
            ],
            'years' => $this->reports->groupBy('period_year')->sortKeysDesc()
                ->map(fn (Collection $reports, int $year) => ['year' => $year, 'count' => $reports->count()])
                ->values()->all(),
            'months' => $this->monthCells($report),
            'cards' => $cards,
        ];
    }

    /** Twelve cells of the open year: done / pending / nocontract / nodata, plus a hover summary. */
    private function monthCells(TaxReport $open): array
    {
        $byMonth = $this->reports->where('period_year', $open->period_year)->keyBy('period_month');
        $contracted = $this->contracted;
        $cells = [];

        foreach (TaxPeriod::SHORT as $number => $short) {
            $report = $byMonth->get($number);
            $cell = [
                'number' => $number,
                'short' => $short,
                'name' => TaxPeriod::NAMES[$number],
                'current' => $number === $open->period_month,
                'state' => 'nodata',
                'activity' => false,
                'tip' => null,
            ];

            if ($report) {
                $statuses = array_map(fn (string $type) => $this->statusFor($report, $type), $contracted);
                $cell['state'] = $contracted === [] ? 'nocontract' : (in_array(ReportOverview::PENDING, $statuses, true) ? 'pending' : 'done');
                $cell['activity'] = $report->invoices_count + $report->income_taxs_count + $report->bupots_count > 0;

                $rows = [];
                foreach ($contracted as $i => $type) {
                    $rows[] = [
                        $statuses[$i] === ReportOverview::PENDING ? 'rgb(var(--danger-500))' : ($statuses[$i] === ReportOverview::REPORTED ? 'rgb(var(--primary-500))' : 'rgb(var(--gray-400))'),
                        ReportOverview::TYPES[$type][0],
                        ReportOverview::statusLabel($statuses[$i]),
                    ];
                }
                $rows[] = ['', 'Faktur', (string) $report->invoices_count];
                $rows[] = ['', 'PPh', (string) $report->income_taxs_count];
                $rows[] = ['', 'Bupot', (string) $report->bupots_count];
                $cell['tip'] = ['t' => TaxPeriod::label($number, $open->period_year), 'r' => $rows];
            }

            $cells[] = $cell;
        }

        return $cells;
    }

    /** PPh Badan has no data screen of its own: its SPT row of the open period. */
    #[Computed]
    public function pphBadan(): ?array
    {
        $summary = $this->report?->taxCalculationSummaries->firstWhere('tax_type', 'pph_badan');

        if (! $summary) {
            return null;
        }

        $status = ReportOverview::statusOf($summary);

        return [
            'statusLabel' => ReportOverview::statusLabel($status),
            'tone' => ReportOverview::statusTone($status),
            'reportedOn' => TaxPeriod::date($summary->reported_at),
            'nomor' => $summary->nomor_bukti_lapor,
            'fileUrl' => $summary->bukti_lapor ? Storage::disk('public')->url($summary->bukti_lapor) : null,
        ];
    }

    // ---------------------------------------------------------------- navigation

    /** Open the detail of a period, on the tax type of the clicked SPT. */
    public function openSptDetail($month, $year, $type = 'ppn'): void
    {
        $number = TaxPeriod::index($month);

        if ($number === 0 || ! ctype_digit((string) $year)) {
            return;
        }

        $this->openPeriod((int) $year, $number);
        $this->type = array_key_exists($type, ReportOverview::TYPES) ? $type : 'ppn';
        $this->keepOpenType();
        $this->dispatch('spt-detail-opened');
    }

    public function backToSptList(): void
    {
        $this->openPeriod(null, null);
        $this->type = 'ppn';
        $this->dispatch('spt-detail-opened');
    }

    /** A year opens its latest reported period. */
    public function selectYear($year): void
    {
        $report = $this->reports->where('period_year', (int) $year)->last();

        if ($report) {
            $this->openPeriod($report->period_year, $report->period_month);
            $this->keepOpenType();
        }
    }

    public function selectMonth($month): void
    {
        $year = $this->report?->period_year;
        $number = (int) $month;

        if ($year && $this->reports->first(fn (TaxReport $r) => $r->period_year === $year && $r->period_month === $number)) {
            $this->openPeriod($year, $number);
            $this->keepOpenType();
        }
    }

    /** Set the open period and drop the computed values that depend on it. */
    private function openPeriod(?int $year, ?int $month): void
    {
        $this->period = $year && $month ? sprintf('%04d-%02d', $year, $month) : '';

        unset($this->report, $this->detail, $this->pphBadan);
    }

    public function setType(string $type): void
    {
        if (array_key_exists($type, ReportOverview::TYPES) && ! $this->isLocked($type)) {
            $this->type = $type;
        }
    }

    /** A type from the URL or a clicked SPT may be locked or unknown: fall back to PPN. */
    private function keepOpenType(): void
    {
        if (! array_key_exists($this->type, ReportOverview::TYPES) || $this->isLocked($this->type)) {
            $this->type = 'ppn';
        }
    }

    // ---------------------------------------------------------------- SPT list

    /** Reported SPT of the company across all periods, newest first. */
    #[Computed]
    public function reportedSpts(): Collection
    {
        if (! $this->selectedClientId) {
            return collect();
        }

        return TaxCalculationSummary::query()
            ->whereHas('taxReport', fn ($q) => $q->where('client_id', $this->selectedClientId))
            ->where(fn ($q) => $q->where('report_status', 'Sudah Lapor')->orWhereNotNull('bukti_lapor'))
            // A period marked "no activity" is stored as Sudah Lapor so the
            // calculations keep working, but no SPT was ever filed; listing it
            // would send the client looking for a document that doesn't exist.
            ->where('no_activity', false)
            ->with(['taxReport:id,month,year,client_id,created_at'])
            ->get()
            ->map(function (TaxCalculationSummary $s) {
                $report = $s->taxReport;

                if (! $report) {
                    return null;
                }

                $year = (int) ($report->year ?: $report->created_at?->year);
                $month = TaxPeriod::index($report->month);

                return [
                    'id' => $s->id,
                    'type' => $s->tax_type,
                    'jenis' => self::SPT_LABELS[$s->tax_type] ?? strtoupper((string) $s->tax_type),
                    'masa' => TaxPeriod::label($month, $year),
                    'month' => $month,
                    'year' => $year,
                    'nominal' => abs((float) ($s->saldo_final ?? $s->selisih ?? 0)),
                    'paid' => $s->bayar_status === 'Sudah Bayar',
                    'fileUrl' => $s->bukti_lapor ? Storage::disk('public')->url($s->bukti_lapor) : null,
                    'nomor' => $s->nomor_bukti_lapor,
                    'sort' => $year * 100 + $month,
                ];
            })
            ->filter()
            ->sortByDesc('sort')
            ->values();
    }

    public function updatedSptPerPage(): void
    {
        $this->sptPerPage = in_array($this->sptPerPage, [5, 10, 25, 50], true) ? $this->sptPerPage : 10;
        $this->sptPage = 1;
    }

    public function updatedSptSearch(): void
    {
        $this->sptPage = 1;
    }

    public function updatedSptJenis(): void
    {
        $this->sptPage = 1;
    }

    public function updatedSptYear(): void
    {
        $this->sptPage = 1;
    }

    public function sortBy($column): void
    {
        if (! in_array($column, ['masa', 'jenis', 'nominal', 'status'], true)) {
            return;
        }

        if ($this->sptSort === $column) {
            $this->sptSortDir = $this->sptSortDir === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sptSort = $column;
            $this->sptSortDir = $column === 'jenis' ? 'asc' : 'desc';
        }

        $this->sptPage = 1;
    }

    public function resetSptFilters(): void
    {
        $this->sptSearch = '';
        $this->sptJenis = [];
        $this->sptYear = '';
        $this->sptPage = 1;
    }

    public function setSptYear($year): void
    {
        $this->sptYear = (string) $year;
        $this->sptPage = 1;
    }

    public function setSptGroup($group): void
    {
        $this->sptGroupBy = in_array($group, ['jenis', 'tahun', 'status', 'none'], true) ? $group : 'jenis';
        $this->sptPage = 1;
    }

    /** Jenis filter options: only the types that have reported SPT. */
    #[Computed]
    public function sptJenisOptions(): Collection
    {
        return collect(self::SPT_LABELS)->only($this->reportedSpts->pluck('type')->unique()->all());
    }

    #[Computed]
    public function sptYears(): Collection
    {
        return $this->reportedSpts->pluck('year')->filter()->unique()->sortDesc()->values();
    }

    /** SPT after search, filters and sort (before paging); the group is the primary sort. */
    #[Computed]
    public function filteredSpts(): Collection
    {
        $rows = $this->reportedSpts;
        $search = trim(mb_strtolower($this->sptSearch));

        if ($search !== '') {
            $rows = $rows->filter(fn ($r) => str_contains(mb_strtolower($r['jenis'] . ' ' . $r['masa'] . ' ' . ($r['nomor'] ?? '')), $search));
        }

        if ($this->sptJenis !== []) {
            $rows = $rows->filter(fn ($r) => in_array($r['type'], $this->sptJenis, true));
        }

        if ($this->sptYear !== '') {
            $rows = $rows->filter(fn ($r) => (string) $r['year'] === $this->sptYear);
        }

        $rows = $rows->sortBy(fn ($r) => match ($this->sptSort) {
            'jenis' => $r['jenis'],
            'nominal' => $r['nominal'],
            'status' => $r['paid'] ? 1 : 0,
            default => $r['sort'],
        }, SORT_REGULAR, $this->sptSortDir === 'desc');

        if ($this->sptGroupBy !== 'none') {
            // sortBy is stable, so the sort above holds inside each group.
            $rows = $rows->sortBy(fn ($r) => match ($this->sptGroupBy) {
                'tahun' => -$r['year'],
                'status' => $r['paid'] ? 0 : 1,
                default => $r['jenis'],
            });
        }

        return $rows->values();
    }

    public function gotoSptPage($page): void
    {
        $this->sptPage = max(1, (int) $page);
    }

    public function render()
    {
        // A period in the URL that this company doesn't have falls back to the list.
        if ($this->period !== '' && ! $this->report) {
            $this->period = '';
        }

        if (! $this->report) {
            $this->type = 'ppn';
        }

        if ($this->report) {
            $this->keepOpenType();
        }

        return view('livewire.client.panel.tax-report-tab');
    }
}
