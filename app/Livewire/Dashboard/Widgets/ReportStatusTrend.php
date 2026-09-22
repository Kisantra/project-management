<?php

namespace App\Livewire\Dashboard\Widgets;

use App\Services\TaxDeadlineService;
use Carbon\Carbon;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Lazy;
use Livewire\Component;

/**
 * Reported vs not-yet-reported tax obligations per masa pajak for the last
 * twelve periods, per tax type or all together. Counts come from
 * tax_calculation_summaries (one row per client, period and tax type), the
 * same source as the tax dashboard, so the numbers agree.
 */
#[Lazy]
class ReportStatusTrend extends Component
{
    public const MONTHS = 12;

    /** key => [label, deadline day in the following month, null = all types] */
    public const TYPES = [
        'all'   => ['label' => 'Semua',         'day' => 20],
        'ppn'   => ['label' => 'PPN',           'day' => 20],
        'pph'   => ['label' => 'PPh 21',        'day' => 10],
        'bupot' => ['label' => 'PPh Unifikasi', 'day' => 10],
    ];

    public string $type = 'all';

    public function placeholder(): View
    {
        return view('livewire.dashboard.widgets.chart-placeholder', ['height' => 'h-[24rem]']);
    }

    public function setType(string $type): void
    {
        if (array_key_exists($type, self::TYPES)) {
            $this->type = $type;
        }
    }

    public function render(TaxDeadlineService $service): View
    {
        return view('livewire.dashboard.widgets.report-status-trend', $this->chart($service));
    }

    /** @return array<string, mixed> */
    protected function chart(TaxDeadlineService $service): array
    {
        $today = Carbon::today();
        // Latest period whose deadline month has started: the previous month.
        $latest = $service->periodFor($today);
        $day = self::TYPES[$this->type]['day'];

        $periods = [];
        for ($i = self::MONTHS - 1; $i >= 0; $i--) {
            $period = $latest->copy()->subMonths($i);
            $row = $this->countsFor($service, $period);
            $total = (int) $row->total;
            $done = (int) $row->done;
            $deadline = $service->deadlineDate($service->anchorFor($period), $day);
            $pending = max($total - $done, 0);

            $periods[] = [
                'key'      => $period->format('Y-m'),
                'label'    => $service->monthShort($period),
                'title'    => $service->periodLabel($period),
                'total'    => $total,
                'done'     => $done,
                'pending'  => $pending,
                'pct'      => $total > 0 ? (int) round($done / $total * 100) : null,
                'late'     => $pending > 0 && $deadline->lt($today),
                'deadline' => $deadline->format('d') . ' ' . $service->monthName($deadline) . ' ' . $deadline->year,
            ];
        }

        $totals = array_column($periods, 'total');
        $peak = max(max($totals), 1);
        $step = $peak > 200 ? 100 : ($peak > 100 ? 50 : ($peak > 40 ? 20 : ($peak > 16 ? 10 : 5)));
        $ceiling = max($step, (int) ceil($peak / $step) * $step);

        $allTotal = array_sum($totals);
        $allDone = array_sum(array_column($periods, 'done'));

        return [
            'periods'   => $periods,
            'ceiling'   => $ceiling,
            'gridlines' => range($ceiling, 0, -$step),
            'types'     => self::TYPES,
            'sumTotal'  => $allTotal,
            'sumDone'   => $allDone,
            'sumLate'   => array_sum(array_map(fn ($p) => $p['late'] ? $p['pending'] : 0, $periods)),
            'sumPct'    => $allTotal > 0 ? (int) round($allDone / $allTotal * 100) : null,
            'latestIndex' => count($periods) - 1,
        ];
    }

    /** One aggregate row (total, done) for a period, scoped to the user's clients. */
    protected function countsFor(TaxDeadlineService $service, Carbon $period): object
    {
        $query = $service->periodQuery($period);

        if (! auth()->user()->hasRole('super-admin')) {
            $query->whereIn('clients.id', fn ($s) => $s->select('client_id')->from('user_clients')->where('user_id', auth()->id()));
        }

        // PPh Unifikasi periods flagged "no activity" carry no filing duty, so they are left out,
        // exactly as the tax dashboard does.
        $query->where(function ($q) {
            $q->where('s.tax_type', '<>', 'bupot')->orWhere('s.no_activity', 0);
        });

        if ($this->type !== 'all') {
            $query->where('s.tax_type', $this->type);
        } else {
            $query->whereIn('s.tax_type', ['ppn', 'pph', 'bupot']);
        }

        return $query->selectRaw("COUNT(*) AS total, SUM(s.report_status = 'Sudah Lapor') AS done")->first();
    }
}
