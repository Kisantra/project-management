<?php

namespace App\Livewire\Dashboard\Home;

use App\Filament\Pages\Dashboard;
use App\Models\User;
use App\Services\Dashboard\HomeDashboard;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Lazy;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * One lazily loaded block of the main dashboard. The page decides which blocks
 * to place; access is checked again here, so a block never renders data the
 * user may not see.
 */
#[Lazy]
class Section extends Component
{
    /** block => [HomeDashboard method, who may see it: all | tax | team] */
    public const SECTIONS = [
        'compliance' => ['taxCompliance', 'tax'],
        'agenda' => ['agenda', 'all'],
        'flow' => ['projectFlow', 'all'],
        'status' => ['projectStatus', 'all'],
        'workload' => ['teamWorkload', 'team'],
        'ontime' => ['onTimeTrend', 'tax'],
        'ppn' => ['ppnPosition', 'tax'],
        'clients' => ['topClients', 'tax'],
        'activity' => ['activity', 'all'],
    ];

    #[Locked]
    public string $section = 'activity';

    #[Locked]
    public string $scope = HomeDashboard::KANTOR;

    public static function allowed(string $section, string $scope, ?User $user): bool
    {
        return match (self::SECTIONS[$section][1] ?? null) {
            'all' => true,
            'tax' => Dashboard::canSeeTax($user),
            // Everyone may see their own workload; the whole team needs the old Project Dashboard access.
            'team' => $scope === HomeDashboard::SAYA || Dashboard::canSeeTeam($user),
            default => false,
        };
    }

    public function placeholder(): View
    {
        return view('livewire.dashboard.home.placeholder', ['section' => $this->section]);
    }

    public function render(): View
    {
        $user = auth()->user();

        if (! self::allowed($this->section, $this->scope, $user)) {
            return view('livewire.dashboard.home.hidden');
        }

        $home = HomeDashboard::for($user, $this->scope);
        $method = self::SECTIONS[$this->section][0];

        return view('livewire.dashboard.home.' . $this->section, [
            'data' => $method === 'agenda' ? $home->agenda(Dashboard::canSeeTax($user)) : $home->{$method}(),
            'mine' => $home->isMine(),
        ]);
    }
}
