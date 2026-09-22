<?php

namespace App\Livewire\Dashboard\Widget;

use App\Filament\Pages\Letters\Index as LettersIndex;
use App\Filament\Resources\ProjectResource;
use App\Filament\Resources\RequiredDocumentResource;
use App\Models\Letter;
use App\Models\Project;
use App\Models\SubmittedDocument;
use App\Models\UserActivity;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Component;
use Spatie\Activitylog\Models\Activity;

/**
 * KPI row at the top of the dashboard: four stat tiles, each a 30-day flow
 * (how much happened) with a delta against the previous 30 days and a daily
 * sparkline. The whole tile links to the list behind it.
 */
class ProjectStatsOverview extends Component
{
    public const WINDOW_DAYS = 30;

    public function render(): View
    {
        return view('livewire.dashboard.widget.project-stats-overview', [
            'tiles' => $this->tiles(),
        ]);
    }

    /** @return array<int, array<string, mixed>> */
    protected function tiles(): array
    {
        $tiles = [
            $this->newProjectsTile(),
            $this->completedProjectsTile(),
            $this->documentsTile(),
        ];

        $tiles[] = auth()->user()->can('surat.*') ? $this->lettersTile() : $this->activityTile();

        return $tiles;
    }

    /* ------------------------------------------------------------------ */
    /* Tiles                                                               */
    /* ------------------------------------------------------------------ */

    protected function newProjectsTile(): array
    {
        return $this->tile(
            label: 'Proyek baru',
            unit: 'proyek',
            query: $this->projects(),
            column: 'projects.created_at',
            href: ProjectResource::getUrl('index'),
        );
    }

    protected function completedProjectsTile(): array
    {
        // The projects table has no completed_at; the status flip lives in the activity log.
        $query = Activity::query()
            ->where('subject_type', Project::class)
            ->whereIn('subject_id', $this->projects()->select('projects.id'))
            ->where('properties->attributes->status', 'completed');

        return $this->tile(
            label: 'Proyek selesai',
            unit: 'proyek',
            query: $query,
            column: 'activity_log.created_at',
            href: ProjectResource::getUrl('index') . '?status[0]=completed',
        );
    }

    protected function documentsTile(): array
    {
        $query = SubmittedDocument::query()
            ->whereHas('requiredDocument.projectStep.project', fn ($q) => $q->whereIn('id', $this->projects()->select('projects.id')));

        return $this->tile(
            label: 'Dokumen masuk',
            unit: 'dokumen',
            query: $query,
            column: 'submitted_documents.created_at',
            href: RequiredDocumentResource::getUrl('index'),
        );
    }

    protected function lettersTile(): array
    {
        return $this->tile(
            label: 'Surat diajukan',
            unit: 'surat',
            query: Letter::query()->whereNotNull('submitted_at'),
            column: 'letters.submitted_at',
            href: LettersIndex::getUrl() . '?tab=surat',
        );
    }

    protected function activityTile(): array
    {
        $query = UserActivity::query();

        if (! auth()->user()->hasRole('super-admin')) {
            $query->whereIn('client_id', fn ($q) => $q->select('client_id')->from('user_clients')->where('user_id', auth()->id()));
        }

        return $this->tile(
            label: 'Aktivitas tim',
            unit: 'aktivitas',
            query: $query,
            column: 'user_activities.created_at',
            href: ProjectResource::getUrl('index'),
        );
    }

    /* ------------------------------------------------------------------ */
    /* Building blocks                                                     */
    /* ------------------------------------------------------------------ */

    /**
     * One stat tile: count in the last 30 days, the count in the 30 days before
     * that, the signed delta, and one value per day for the sparkline.
     */
    protected function tile(string $label, string $unit, Builder $query, string $column, string $href): array
    {
        $today = CarbonImmutable::today();
        $windowStart = $today->subDays(self::WINDOW_DAYS - 1);
        $previousStart = $windowStart->subDays(self::WINDOW_DAYS);

        $perDay = (clone $query)
            ->whereBetween($column, [$windowStart->startOfDay(), $today->endOfDay()])
            ->selectRaw("DATE({$column}) AS d, COUNT(*) AS c")
            ->groupBy('d')
            ->pluck('c', 'd');

        $series = [];
        for ($day = $windowStart; $day->lte($today); $day = $day->addDay()) {
            $series[] = (int) ($perDay[$day->toDateString()] ?? 0);
        }

        $current = array_sum($series);
        $previous = (clone $query)
            ->whereBetween($column, [$previousStart->startOfDay(), $windowStart->subDay()->endOfDay()])
            ->count();

        return [
            'label'    => $label,
            'unit'     => $unit,
            'value'    => $current,
            'previous' => $previous,
            'delta'    => $this->delta($current, $previous),
            'series'   => $series,
            'days'     => $this->dayLabels($windowStart, $today),
            'href'     => $href,
        ];
    }

    /** Projects the current user may see (super-admin: all; others: their clients). */
    protected function projects(): Builder
    {
        $query = Project::query();

        if (! auth()->user()->hasRole('super-admin')) {
            $query->whereIn('client_id', fn ($q) => $q->select('client_id')->from('user_clients')->where('user_id', auth()->id()));
        }

        return $query;
    }

    /**
     * Direction and label for the delta pill.
     * tone: up | down | flat. text: "+28%", "-12%", "+6" (no base to compare), "0%".
     */
    protected function delta(int $current, int $previous): array
    {
        if ($previous === 0) {
            return $current > 0
                ? ['tone' => 'up', 'text' => '+' . number_format($current, 0, ',', '.')]
                : ['tone' => 'flat', 'text' => '0%'];
        }

        $pct = (int) round(($current - $previous) / $previous * 100);

        return [
            'tone' => $pct > 0 ? 'up' : ($pct < 0 ? 'down' : 'flat'),
            'text' => ($pct > 0 ? '+' : '') . $pct . '%',
        ];
    }

    /** @return string[] one short label per day, e.g. "21 Sep" */
    protected function dayLabels(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $labels = [];
        for ($day = $from; $day->lte($to); $day = $day->addDay()) {
            $labels[] = $day->locale('id')->translatedFormat('j M');
        }

        return $labels;
    }

    /**
     * SVG path data for a smooth sparkline. Returns the stroke path and the
     * closed area path in a viewBox of $width x $height with $pad top/bottom.
     *
     * @param  int[]  $values
     * @return array{line: string, area: string, last: array{x: float, y: float}}
     */
    public static function sparkline(array $values, int $width = 120, int $height = 40, float $pad = 3): array
    {
        $n = count($values);
        if ($n < 2) {
            $values = array_pad($values, 2, $values[0] ?? 0);
            $n = 2;
        }

        $max = max(max($values), 1);
        $stepX = $width / ($n - 1);
        $usable = $height - 2 * $pad;

        $points = [];
        foreach ($values as $i => $v) {
            $points[] = [
                round($i * $stepX, 2),
                round($height - $pad - ($v / $max) * $usable, 2),
            ];
        }

        // Catmull-Rom -> cubic Bézier, so the line bends softly instead of zig-zagging.
        $d = 'M' . $points[0][0] . ',' . $points[0][1];
        for ($i = 0; $i < $n - 1; $i++) {
            $p0 = $points[max($i - 1, 0)];
            $p1 = $points[$i];
            $p2 = $points[$i + 1];
            $p3 = $points[min($i + 2, $n - 1)];

            $c1x = round($p1[0] + ($p2[0] - $p0[0]) / 6, 2);
            $c1y = round($p1[1] + ($p2[1] - $p0[1]) / 6, 2);
            $c2x = round($p2[0] - ($p3[0] - $p1[0]) / 6, 2);
            $c2y = round($p2[1] - ($p3[1] - $p1[1]) / 6, 2);

            $d .= " C{$c1x},{$c1y} {$c2x},{$c2y} {$p2[0]},{$p2[1]}";
        }

        $last = end($points);
        $area = $d . " L{$last[0]},{$height} L0,{$height} Z";

        return ['line' => $d, 'area' => $area, 'last' => ['x' => $last[0], 'y' => $last[1]]];
    }
}
