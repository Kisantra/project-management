<?php

namespace App\Support\Ui;

use App\Models\ProjectStatus;

/**
 * The three status voices from DESIGN.md (The Three Voices Rule): blue for
 * normal progress, red for a problem, neutral for everything else, plus an
 * outline-blue for "waiting" states. Every badge in the panel should get its
 * tone from here so the mapping lives in one place.
 */
final class Tone
{
    public const BLUE = 'blue';
    public const NEUTRAL = 'neutral';
    public const RED = 'red';
    public const OUTLINE = 'outline';

    public const ALL = [self::BLUE, self::NEUTRAL, self::RED, self::OUTLINE];

    /** Chart colours for project statuses outside the blue workflow ramp (see projectStatusChart). */
    public const CHART_NOT_STARTED = 'var(--kis-ramp-n1)';

    public const CHART_CLOSED = 'var(--kis-ramp-n2)';

    /** Choices shown where admins pick a tone (project statuses). */
    public const LABELS = [
        self::BLUE => ['label' => 'Biru', 'hint' => 'Berjalan atau selesai'],
        self::OUTLINE => ['label' => 'Garis biru', 'hint' => 'Menunggu pihak lain'],
        self::NEUTRAL => ['label' => 'Netral', 'hint' => 'Belum mulai atau ditutup'],
        self::RED => ['label' => 'Merah', 'hint' => 'Bermasalah atau terlambat'],
    ];

    /**
     * Hex values for markup that needs a literal colour (SVG attributes,
     * inline custom properties): text/icon colour and fill, light theme.
     * Matches the primary, gray and danger ramps in AdminPanelProvider::palette().
     */
    private const SWATCHES = [
        self::BLUE => ['color' => '#10657f', 'bg' => '#dff4fe'],
        self::OUTLINE => ['color' => '#10657f', 'bg' => 'transparent'],
        self::NEUTRAL => ['color' => '#485156', 'bg' => '#ebf2f4'],
        self::RED => ['color' => '#af3628', 'bg' => '#fff4f2'],
    ];

    public static function normalize(?string $tone): string
    {
        return in_array($tone, self::ALL, true) ? $tone : self::NEUTRAL;
    }

    /** @return array{color: string, bg: string} */
    public static function swatch(?string $tone): array
    {
        return self::SWATCHES[self::normalize($tone)];
    }

    /**
     * Project statuses carry a tone picked in the status manager. Rows saved
     * before that column existed fall back to category and shape.
     */
    public static function forProjectStatus(ProjectStatus|string|null $status): string
    {
        if (! $status instanceof ProjectStatus) {
            $status = filled($status) ? ProjectStatus::query()->where('key', $status)->first() : null;
        }

        if ($status === null) {
            return self::NEUTRAL;
        }

        if (in_array($status->tone, self::ALL, true)) {
            return $status->tone;
        }

        return self::defaultForProjectStatus($status->category, $status->shape);
    }

    /** Suggested tone for a category and shape (new statuses, old rows). */
    public static function defaultForProjectStatus(?string $category, ?string $shape): string
    {
        return match ($category) {
            ProjectStatus::CATEGORY_ACTIVE => in_array($shape, ['clock', 'dashed'], true) ? self::OUTLINE : self::BLUE,
            ProjectStatus::CATEGORY_DONE => self::BLUE,
            default => self::NEUTRAL,
        };
    }

    /**
     * Chart colour per project status. Statuses are a workflow, so they read as
     * an ordinal ramp (one hue, light to dark) rather than separate hues: the
     * ones underway or done step through the blue ramp in workflow order, not
     * started and closed stay gray. Values are CSS custom properties defined in
     * theme.css, which flips the ramp for dark mode. More than six statuses
     * underway or done share steps.
     *
     * @param  iterable<ProjectStatus>  $statuses  in ProjectStatus::ordered() order
     * @return array<string, string>  status key => 'var(--kis-ramp-…)'
     */
    public static function projectStatusChart(iterable $statuses): array
    {
        $statuses = collect($statuses);
        $workflow = $statuses
            ->filter(fn (ProjectStatus $status) => in_array($status->category, [ProjectStatus::CATEGORY_ACTIVE, ProjectStatus::CATEGORY_DONE], true))
            ->values();
        $last = $workflow->count() - 1;

        $colors = $statuses->mapWithKeys(fn (ProjectStatus $status) => [
            $status->key => $status->category === ProjectStatus::CATEGORY_CLOSED ? self::CHART_CLOSED : self::CHART_NOT_STARTED,
        ])->all();

        foreach ($workflow as $index => $status) {
            $step = $last <= 0 ? 4 : 1 + (int) round($index * 5 / $last);
            $colors[$status->key] = "var(--kis-ramp-b{$step})";
        }

        return $colors;
    }

    /** Status Bayar on tax reports (status_final of the PPN summary). */
    public static function forPaymentStatus(?string $status): string
    {
        return match ($status) {
            'Kurang Bayar' => self::RED,
            'Lebih Bayar' => self::BLUE,
            default => self::NEUTRAL,
        };
    }

    /** report_status of a tax calculation summary. */
    public static function forReportStatus(?string $status): string
    {
        return match ($status) {
            'Sudah Lapor' => self::BLUE,
            'Belum Lapor' => self::RED,
            default => self::NEUTRAL,
        };
    }

    public static function forClientStatus(?string $status): string
    {
        return $status === 'Active' ? self::BLUE : self::NEUTRAL;
    }

    /** Bridge for native Filament badges (TextColumn::badge()->color(...)). */
    public static function filamentColor(string $tone): string
    {
        return match ($tone) {
            self::BLUE, self::OUTLINE => 'primary',
            self::RED => 'danger',
            default => 'gray',
        };
    }
}
