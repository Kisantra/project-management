<?php

namespace App\Livewire\Dashboard\Widgets;

use App\Filament\Pages\Calendar\Index as CalendarPage;
use App\Models\CalendarEvent;
use App\Models\UserActivity;
use App\Services\CalendarService;
use Filament\Notifications\Notification;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Livewire\Attributes\Lazy;
use Livewire\Component;
use Throwable;

/**
 * The next events on the team calendar, on the dashboard. Clicking one opens
 * the same floating record panel the calendar page uses.
 */
#[Lazy]
class UpcomingAgenda extends Component
{
    public const LIMIT = 8;

    /** false = whole team, true = only events I created or am invited to. */
    public bool $mine = false;

    public ?int $selectedId = null;
    public bool $panelOpen = false;

    public function placeholder(): View
    {
        return view('livewire.dashboard.widgets.chart-placeholder', ['height' => 'h-72']);
    }

    public function toggleMine(): void
    {
        $this->mine = ! $this->mine;
    }

    public function open(int $id): void
    {
        $this->selectedId = $id;
        $this->panelOpen = true;
    }

    public function closePanel(): void
    {
        $this->panelOpen = false;
        $this->selectedId = null;
    }

    public function setStatus(int $id, string $status, CalendarService $service): void
    {
        $event = CalendarEvent::findOrFail($id);

        if (! $event->canBeManagedBy(auth()->user()) && ! $event->participants()->where('users.id', auth()->id())->exists()) {
            return;
        }

        try {
            $service->setStatus($event, $status);
            Notification::make()->title('Acara ditandai ' . strtolower($event->statusLabel()))->success()->send();
        } catch (Throwable $e) {
            Notification::make()->title('Gagal mengubah status')->body($e->getMessage())->danger()->send();
        }
    }

    public function setKind(int $id, string $kind): void
    {
        $event = CalendarEvent::findOrFail($id);

        if (! $event->canBeManagedBy(auth()->user()) || ! array_key_exists($kind, CalendarEvent::KINDS)) {
            return;
        }

        $event->update(['kind' => $kind]);
        $event->logActivity('calendar_event_updated', 'Jenis acara "' . $event->title . '" diubah menjadi ' . $event->kindLabel());
    }

    public function render(): View
    {
        $selected = $this->selectedId
            ? CalendarEvent::with(['client:id,name', 'project:id,name', 'creator:id,name', 'participants:id,name,avatar_url', 'reminders'])->find($this->selectedId)
            : null;

        return view('livewire.dashboard.widgets.upcoming-agenda', [
            'days'          => $this->upcoming()->groupBy(fn ($e) => $e->starts_at->toDateString()),
            'total'         => $this->upcoming()->count(),
            'calendarUrl'   => CalendarPage::getUrl(),
            'selected'      => $selected,
            'canManage'     => $selected?->canBeManagedBy(auth()->user()) ?? false,
            'isParticipant' => $selected ? $selected->participants->contains('id', auth()->id()) : false,
            'history'       => $selected ? $this->history($selected) : collect(),
        ]);
    }

    protected function upcoming(): Collection
    {
        return CalendarEvent::query()
            ->with(['client:id,name'])
            ->where('status', CalendarEvent::STATUS_SCHEDULED)
            ->where(fn ($q) => $q->where('ends_at', '>=', now())->orWhere(fn ($w) => $w->whereNull('ends_at')->where('starts_at', '>=', now())))
            ->when($this->mine, fn ($q) => $q->involving(auth()->user()))
            ->orderBy('starts_at')
            ->limit(self::LIMIT)
            ->get();
    }

    protected function history(CalendarEvent $event): Collection
    {
        return UserActivity::query()
            ->with('user:id,name')
            ->where('actionable_type', CalendarEvent::class)
            ->where('actionable_id', $event->id)
            ->latest('id')
            ->limit(30)
            ->get();
    }
}
