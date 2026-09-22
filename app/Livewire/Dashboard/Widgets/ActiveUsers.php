<?php

namespace App\Livewire\Dashboard\Widgets;

use App\Models\User;
use App\Models\UserActivity;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Livewire\Attributes\Lazy;
use Livewire\Component;

/**
 * Leaderboard of the most active users, counted from the custom activity log
 * (user_activities) over a selectable window. Rank 1 is emphasised the most and
 * the emphasis fades down the list.
 */
#[Lazy]
class ActiveUsers extends Component
{
    public const RANGES = [
        '30'  => '30 hari',
        '90'  => '90 hari',
        '365' => '1 tahun',
    ];

    public const LIMIT = 6;

    public string $range = '30';

    public function placeholder(): View
    {
        return view('livewire.dashboard.widgets.chart-placeholder', ['height' => 'h-[22rem]']);
    }

    public function setRange(string $range): void
    {
        if (array_key_exists($range, self::RANGES)) {
            $this->range = $range;
        }
    }

    public function render(): View
    {
        $rows = $this->rows();

        return view('livewire.dashboard.widgets.active-users', [
            'rows'   => $rows,
            'max'    => max($rows->max('total') ?? 0, 1),
            'total'  => $this->totalInRange(),
            'ranges' => self::RANGES,
        ]);
    }

    protected function totalInRange(): int
    {
        return UserActivity::query()->where('created_at', '>=', now()->subDays((int) $this->range))->count();
    }

    protected function rows(): Collection
    {
        $counts = UserActivity::query()
            ->where('created_at', '>=', now()->subDays((int) $this->range))
            ->selectRaw('user_id, COUNT(*) AS total, MAX(created_at) AS last_at')
            ->groupBy('user_id')
            ->orderByDesc('total')
            ->limit(self::LIMIT)
            ->get();

        $users = User::query()->whereIn('id', $counts->pluck('user_id'))->get(['id', 'name', 'avatar_url', 'position', 'job_title', 'department_id'])->keyBy('id');
        $grand = max($this->totalInRange(), 1);

        return $counts->values()->map(function ($row, $i) use ($users, $grand) {
            $user = $users->get($row->user_id);

            return [
                'rank'     => $i + 1,
                'name'     => $user?->name ?? 'Pengguna terhapus',
                'title'    => $user?->signatureTitle(),
                'initials' => $this->initials($user?->name ?? '?'),
                'avatar'   => $user?->avatar_url ? (str_starts_with($user->avatar_url, 'http') ? $user->avatar_url : asset($user->avatar_url)) : null,
                'total'    => (int) $row->total,
                'share'    => (int) round($row->total / $grand * 100),
                'last'     => \Carbon\Carbon::parse($row->last_at)->locale('id')->diffForHumans(),
            ];
        });
    }

    protected function initials(string $name): string
    {
        $words = preg_split('/\s+/', trim($name)) ?: [];

        return strtoupper(count($words) >= 2
            ? mb_substr($words[0], 0, 1) . mb_substr(end($words), 0, 1)
            : mb_substr($name, 0, 2));
    }
}
