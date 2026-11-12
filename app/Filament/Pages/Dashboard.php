<?php

namespace App\Filament\Pages;

use App\Models\User;
use App\Services\Dashboard\HomeDashboard;
use App\Support\Access\PathPermission;
use Filament\Pages\Dashboard as BaseDashboard;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Url;

/**
 * Main dashboard (docs/mockups/dashboard-redesign.html), in the order of three
 * questions: what needs doing today, how the office is doing, what changed.
 *
 * Scope "kantor" (everything) or "saya" (the user's own clients, projects and
 * tasks), kept in ?lingkup=. The header, brief and action cards render here;
 * every other block is a lazy App\Livewire\Dashboard\Home\Section keyed by
 * scope, so switching scope reloads them.
 */
class Dashboard extends BaseDashboard
{
    protected static ?string $navigationIcon = 'heroicon-o-computer-desktop';

    protected static string $view = 'filament.pages.dashboard.index';

    protected ?string $heading = '';

    /**
     * The tax and team blocks show every client's data. They keep the path
     * permissions of the pages they replaced (Dashboard Pajak, Project
     * Dashboard): grant a permission covering one of these paths on /peran-izin.
     */
    public const TAX_SUMMARY_PATH = 'dashboard-tax-report';

    public const PROJECT_SUMMARY_PATH = 'project-dashboard';

    #[Url(as: 'lingkup')]
    public string $scope = HomeDashboard::KANTOR;

    public static function canSeeTax(?User $user): bool
    {
        return PathPermission::allows($user, self::TAX_SUMMARY_PATH);
    }

    public static function canSeeTeam(?User $user): bool
    {
        return PathPermission::allows($user, self::PROJECT_SUMMARY_PATH);
    }

    public function mount(): void
    {
        $this->setScope($this->scope);
    }

    public function setScope(string $scope): void
    {
        $this->scope = in_array($scope, [HomeDashboard::KANTOR, HomeDashboard::SAYA], true) ? $scope : HomeDashboard::KANTOR;
    }

    public function getViewData(): array
    {
        $user = auth()->user();
        $canSeeTax = self::canSeeTax($user);
        $now = Carbon::now();

        return [
            'greeting' => match (true) {
                $now->hour >= 5 && $now->hour < 12 => 'Selamat pagi',
                $now->hour >= 12 && $now->hour < 15 => 'Selamat siang',
                $now->hour >= 15 && $now->hour < 18 => 'Selamat sore',
                default => 'Selamat malam',
            },
            'firstName' => explode(' ', trim($user->name ?? ''))[0] ?: 'Anda',
            'dateLabel' => self::dateLabel($now),
            'home' => HomeDashboard::for($user, $this->scope)->actions($canSeeTax),
            'canSeeTax' => $canSeeTax,
            'canSeeTeam' => $this->scope === HomeDashboard::SAYA || self::canSeeTeam($user),
        ];
    }

    protected static function dateLabel(Carbon $date): string
    {
        $days = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
        $months = [1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];

        return $days[$date->dayOfWeek] . ', ' . $date->day . ' ' . $months[$date->month] . ' ' . $date->year;
    }
}
