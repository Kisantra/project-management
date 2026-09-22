<?php

namespace App\Livewire\Dashboard\Widget;

use App\Filament\Pages\Letters\Index as LettersIndex;
use App\Filament\Resources\ProjectResource;
use App\Filament\Resources\RequiredDocumentResource;
use App\Models\Letter;
use App\Models\Project;
use App\Models\RequiredDocument;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Component;
use Spatie\Activitylog\Models\Activity;

/**
 * Four headline cards at the top of the dashboard. Each card is one number the
 * reader can act on, a pill that says whether it needs attention, a one-line
 * context, and a link to the filtered list behind it.
 */
class ProjectStatsOverview extends Component
{
    protected const OPEN_STATUSES_EXCLUDED = ['completed', 'canceled'];

    public function render(): View
    {
        return view('livewire.dashboard.widget.project-stats-overview', [
            'cards' => $this->cards(),
        ]);
    }

    /** @return array<int, array<string, mixed>> */
    protected function cards(): array
    {
        $user = auth()->user();

        $cards = [
            $this->activeProjectsCard(),
            $this->completedProjectsCard(),
            $this->pendingDocumentsCard(),
        ];

        $cards[] = $user->can('surat.*') ? $this->lettersCard() : $this->urgentProjectsCard();

        return $cards;
    }

    /* ------------------------------------------------------------------ */
    /* Cards                                                               */
    /* ------------------------------------------------------------------ */

    protected function activeProjectsCard(): array
    {
        $open = $this->projects()->whereNotIn('status', self::OPEN_STATUSES_EXCLUDED);
        $active = (clone $open)->count();
        $overdue = (clone $open)->whereDate('due_date', '<', today())->count();
        $dueSoon = (clone $open)->whereBetween('due_date', [today(), today()->addDays(7)])->count();

        return [
            'label' => 'Proyek Aktif',
            'icon'  => 'heroicon-o-folder-open',
            'value' => $active,
            'pill'  => $overdue > 0
                ? ['tone' => 'danger', 'icon' => 'heroicon-m-exclamation-triangle', 'text' => $this->n($overdue) . ' lewat tenggat']
                : ['tone' => 'success', 'icon' => 'heroicon-m-check', 'text' => 'sesuai jadwal'],
            'note'  => $dueSoon > 0
                ? $this->n($dueSoon) . ' jatuh tempo dalam 7 hari'
                : 'tidak ada tenggat dalam 7 hari',
            'href'  => ProjectResource::getUrl('index') . ($overdue > 0 ? '?due=overdue' : ''),
        ];
    }

    protected function completedProjectsCard(): array
    {
        $thisMonth = $this->completedBetween(now()->startOfMonth(), now());
        $lastMonth = $this->completedBetween(now()->subMonthNoOverflow()->startOfMonth(), now()->subMonthNoOverflow()->endOfMonth());
        $allTime = $this->projects()->where('status', 'completed')->count();

        return [
            'label' => 'Selesai Bulan Ini',
            'icon'  => 'heroicon-o-check-circle',
            'value' => $thisMonth,
            'pill'  => $this->deltaPill($thisMonth, $lastMonth),
            'note'  => $this->n($lastMonth) . ' bulan lalu · ' . $this->n($allTime) . ' total',
            'href'  => ProjectResource::getUrl('index') . '?status[0]=completed',
        ];
    }

    protected function pendingDocumentsCard(): array
    {
        $pending = $this->documents()->whereIn('status', ['pending_review', 'uploaded']);
        $count = (clone $pending)->count();
        $newThisWeek = (clone $pending)->where('updated_at', '>=', now()->subDays(7))->count();
        $oldest = (clone $pending)->min('updated_at');
        $waitingDays = $oldest ? (int) now()->diffInDays($oldest) : 0;

        return [
            'label' => 'Dokumen Menunggu Review',
            'icon'  => 'heroicon-o-document-magnifying-glass',
            'value' => $count,
            'pill'  => $newThisWeek > 0
                ? ['tone' => 'warning', 'icon' => 'heroicon-m-arrow-down-tray', 'text' => $this->n($newThisWeek) . ' masuk minggu ini']
                : ['tone' => 'gray', 'icon' => null, 'text' => 'tidak ada yang baru'],
            'note'  => $count > 0
                ? 'terlama menunggu ' . $this->n($waitingDays) . ' hari'
                : 'semua dokumen sudah ditinjau',
            'href'  => RequiredDocumentResource::getUrl('index'),
        ];
    }

    protected function lettersCard(): array
    {
        $user = auth()->user();

        $awaiting = Letter::query()
            ->with('signatures')
            ->where('status', Letter::STATUS_SUBMITTED)
            ->get();
        $mine = $awaiting->filter(fn (Letter $l) => $l->canBeSignedBy($user))->count();
        $drafts = Letter::query()->where('status', Letter::STATUS_DRAFT)->where('created_by', $user->id)->count();

        return [
            'label' => 'Surat Menunggu Tanda Tangan',
            'icon'  => 'heroicon-o-pencil-square',
            'value' => $awaiting->count(),
            'pill'  => $mine > 0
                ? ['tone' => 'warning', 'icon' => 'heroicon-m-hand-raised', 'text' => $this->n($mine) . ' giliran Anda']
                : ['tone' => 'gray', 'icon' => null, 'text' => 'bukan giliran Anda'],
            'note'  => $drafts > 0
                ? $this->n($drafts) . ' draft Anda belum diajukan'
                : 'tidak ada draft tertunda',
            'href'  => LettersIndex::getUrl() . '?tab=surat',
        ];
    }

    protected function urgentProjectsCard(): array
    {
        $open = $this->projects()->whereNotIn('status', self::OPEN_STATUSES_EXCLUDED);
        $urgent = (clone $open)->where('priority', 'urgent');
        $count = (clone $urgent)->count();
        $overdue = (clone $urgent)->whereDate('due_date', '<', today())->count();
        $active = (clone $open)->count();

        return [
            'label' => 'Proyek Mendesak',
            'icon'  => 'heroicon-o-bolt',
            'value' => $count,
            'pill'  => $overdue > 0
                ? ['tone' => 'danger', 'icon' => 'heroicon-m-exclamation-triangle', 'text' => $this->n($overdue) . ' lewat tenggat']
                : ['tone' => 'success', 'icon' => 'heroicon-m-check', 'text' => 'sesuai jadwal'],
            'note'  => 'dari ' . $this->n($active) . ' proyek aktif',
            'href'  => ProjectResource::getUrl('index') . '?priority[0]=urgent',
        ];
    }

    /* ------------------------------------------------------------------ */
    /* Helpers                                                             */
    /* ------------------------------------------------------------------ */

    /** Projects the current user is allowed to see (super-admin: all; others: their clients). */
    protected function projects(): Builder
    {
        $query = Project::query();

        if (! auth()->user()->hasRole('super-admin')) {
            $query->whereIn('client_id', fn ($q) => $q->select('client_id')->from('user_clients')->where('user_id', auth()->id()));
        }

        return $query;
    }

    protected function documents(): Builder
    {
        $projects = $this->projects();

        return RequiredDocument::query()
            ->whereHas('projectStep.project', fn ($q) => $q->whereIn('id', $projects->select('id')));
    }

    /**
     * Projects whose status flipped to "completed" in the window, read from the
     * Spatie activity log because the projects table has no completed_at.
     */
    protected function completedBetween($from, $to): int
    {
        return Activity::query()
            ->where('subject_type', Project::class)
            ->whereIn('subject_id', $this->projects()->select('id'))
            ->where('properties->attributes->status', 'completed')
            ->whereBetween('created_at', [$from, $to])
            ->distinct('subject_id')
            ->count('subject_id');
    }

    /** "+25%" style pill against last period; falls back to plain counts when there is no base. */
    protected function deltaPill(int $current, int $previous): array
    {
        if ($previous === 0 && $current === 0) {
            return ['tone' => 'gray', 'icon' => null, 'text' => 'sama seperti bulan lalu'];
        }

        if ($previous === 0) {
            return ['tone' => 'success', 'icon' => 'heroicon-m-arrow-trending-up', 'text' => '+' . $this->n($current) . ' proyek'];
        }

        $pct = (int) round(($current - $previous) / $previous * 100);

        if ($pct === 0) {
            return ['tone' => 'gray', 'icon' => null, 'text' => 'sama seperti bulan lalu'];
        }

        return $pct > 0
            ? ['tone' => 'success', 'icon' => 'heroicon-m-arrow-trending-up', 'text' => '+' . $pct . '%']
            : ['tone' => 'danger', 'icon' => 'heroicon-m-arrow-trending-down', 'text' => $pct . '%'];
    }

    protected function n(int|float $value): string
    {
        return number_format($value, 0, ',', '.');
    }
}
