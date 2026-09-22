<?php

namespace App\Livewire\Dashboard\Widgets;

use App\Models\Project;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Lazy;
use Livewire\Component;
use Spatie\Activitylog\Models\Activity;

/**
 * Projects completed per month over the last twelve months. The projects
 * table has no completed_at, so the month comes from the activity-log entry
 * that flipped the status to "completed" (one count per project).
 */
#[Lazy]
class CompletedChart extends Component
{
    public const MONTHS = 12;

    public function placeholder(): View
    {
        return view('livewire.dashboard.widgets.chart-placeholder', ['height' => 'h-[22rem]']);
    }

    public function render(): View
    {
        return view('livewire.dashboard.widgets.completed-chart', $this->chart());
    }

    /** @return array<string, mixed> */
    protected function chart(): array
    {
        $today = CarbonImmutable::today();
        $first = $today->startOfMonth()->subMonths(self::MONTHS - 1);

        $rows = Activity::query()
            ->where('subject_type', Project::class)
            ->whereIn('subject_id', $this->projects()->select('projects.id'))
            ->where('properties->attributes->status', 'completed')
            ->where('created_at', '>=', $first)
            ->selectRaw("DATE_FORMAT(created_at, '%Y-%m') AS ym, COUNT(DISTINCT subject_id) AS total")
            ->groupBy('ym')
            ->pluck('total', 'ym');

        $months = [];
        for ($m = $first; $m->lte($today); $m = $m->addMonth()) {
            $months[] = [
                'key'   => $m->format('Y-m'),
                'label' => $m->locale('id')->translatedFormat('M'),
                'title' => $m->locale('id')->translatedFormat('F Y'),
                'total' => (int) ($rows[$m->format('Y-m')] ?? 0),
            ];
        }

        $totals = array_column($months, 'total');
        $peak = max(max($totals), 1);
        $step = $peak > 100 ? 50 : ($peak > 40 ? 20 : ($peak > 16 ? 10 : ($peak > 8 ? 5 : 2)));
        $ceiling = max($step, (int) ceil($peak / $step) * $step);
        $sum = array_sum($totals);
        $average = (int) round($sum / count($months));

        $best = $sum > 0 ? array_search(max($totals), $totals, true) : null;

        return [
            'months'    => $months,
            'ceiling'   => $ceiling,
            'gridlines' => range($ceiling, 0, -$step),
            'average'   => $average,
            'sum'       => $sum,
            'bestIndex' => $best,
            'allTime'   => $this->projects()->where('status', 'completed')->count(),
        ];
    }

    /** Projects the current user may see (same scoping as the old command center). */
    protected function projects(): Builder
    {
        $user = auth()->user();
        $query = Project::query();

        if (! $user->hasRole('super-admin') && ! $user->hasRole('direktur')) {
            $clientIds = $user->userClients()->pluck('client_id')->all();

            if ($clientIds !== []) {
                $query->whereIn('client_id', $clientIds)
                    ->where(fn ($q) => $q->where('pic_id', $user->id)
                        ->orWhereHas('userProject', fn ($p) => $p->where('user_id', $user->id)));
            }
        }

        return $query;
    }
}
