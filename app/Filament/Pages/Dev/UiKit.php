<?php

namespace App\Filament\Pages\Dev;

use Filament\Pages\Page;

/**
 * Living gallery of the <x-kis.*> components (DESIGN.md), used to check them
 * against real Livewire state while the redesign is in progress. Local only
 * and never in the navigation.
 */
class UiKit extends Page
{
    protected static string $view = 'filament.pages.dev.ui-kit';

    protected static ?string $slug = 'dev/ui-kit';

    protected static ?string $title = 'UI kit';

    protected static bool $shouldRegisterNavigation = false;

    public ?string $sop = null;

    public array $period = ['year' => 2026, 'months' => [9, 10, 11]];

    public static function canAccess(): bool
    {
        return app()->isLocal() && auth()->check();
    }

    public function clearPeriod(): void
    {
        $this->period = ['year' => $this->period['year'] ?? now()->year, 'months' => []];
    }

    public function resetFilters(): void
    {
        $this->sop = null;
        $this->clearPeriod();
    }

    /** Sample SOP list in a fixed order, as admins will set it. */
    public function getSopOptions(): array
    {
        return collect([
            ['Lapor SPT Tahunan Badan', 35], ['Lapor SPT Tahunan Nihil', 33], ['Laporan Keuangan', 25],
            ['Permohonan PKP Badan', 11], ['SP2DK 1', 8], ['Buat NPWP Badan', 5], ['Permohonan Non Efektif Badan', 5],
            ['Lapor SPT Tahunan OP', 3], ['Laporan Keuangan 2', 3], ['LAPOR TAHUNAN', 3], ['LAPOR PPH 21', 2],
            ['AUDIT', 2], ['Pemeriksaan Badan', 2], ['Pembuatan Nomor Induk Berusaha', 1], ['PERUBAHAN ALAMAT BADAN', 1],
        ])->map(fn (array $row, int $index) => ['value' => (string) ($index + 1), 'label' => $row[0], 'count' => $row[1]])->all();
    }

    /** Sample report counts per month: [year => [month => count]]. */
    public function getMonthCounts(): array
    {
        return [
            2025 => array_combine(range(1, 12), array_fill(0, 12, 2)),
            2026 => array_combine(range(1, 12), [2, 1, 1, 2, 2, 1, 2, 2, 2, 2, 2, 0]),
        ];
    }
}
