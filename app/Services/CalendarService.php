<?php

namespace App\Services;

use App\Models\CalendarEvent;
use App\Models\CalendarEventReminder;
use App\Models\User;
use Carbon\Carbon;
use Filament\Notifications\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Everything that changes a calendar event lives here: creating, editing,
 * moving between days, status changes, and the reminders that go with them.
 */
class CalendarService
{
    /**
     * @param  array{title:string, kind:string, date:string, all_day:bool, start_time?:?string, end_time?:?string,
     *               location?:?string, description?:?string, client_id?:?int, project_id?:?int,
     *               participants?:int[], reminders?:int[]} $data
     */
    public function create(array $data, User $by): CalendarEvent
    {
        return DB::transaction(function () use ($data, $by) {
            [$startsAt, $endsAt] = $this->times($data);

            $event = CalendarEvent::create([
                'title'       => trim($data['title']),
                'kind'        => $data['kind'],
                'starts_at'   => $startsAt,
                'ends_at'     => $endsAt,
                'all_day'     => (bool) ($data['all_day'] ?? false),
                'location'    => filled($data['location'] ?? null) ? trim($data['location']) : null,
                'description' => filled($data['description'] ?? null) ? trim($data['description']) : null,
                'client_id'   => $data['client_id'] ?? null,
                'project_id'  => $data['project_id'] ?? null,
                'status'      => CalendarEvent::STATUS_SCHEDULED,
                'created_by'  => $by->id,
            ]);

            $participants = collect($data['participants'] ?? [])->map(fn ($id) => (int) $id)->push($by->id)->unique()->values();
            $event->participants()->sync($participants);
            $this->syncReminders($event, $data['reminders'] ?? []);

            $event->logActivity('calendar_event_created', "Acara \"{$event->title}\" ({$event->kindLabel()}) dibuat untuk " . $event->starts_at->translatedFormat('d M Y'));
            $this->notifyInvited($event, $participants->reject(fn ($id) => $id === $by->id));

            return $event;
        });
    }

    public function update(CalendarEvent $event, array $data, User $by): CalendarEvent
    {
        return DB::transaction(function () use ($event, $data, $by) {
            [$startsAt, $endsAt] = $this->times($data);
            $before = $event->participants()->pluck('users.id');

            $event->update([
                'title'       => trim($data['title']),
                'kind'        => $data['kind'],
                'starts_at'   => $startsAt,
                'ends_at'     => $endsAt,
                'all_day'     => (bool) ($data['all_day'] ?? false),
                'location'    => filled($data['location'] ?? null) ? trim($data['location']) : null,
                'description' => filled($data['description'] ?? null) ? trim($data['description']) : null,
                'client_id'   => $data['client_id'] ?? null,
                'project_id'  => $data['project_id'] ?? null,
            ]);

            $participants = collect($data['participants'] ?? [])->map(fn ($id) => (int) $id)->push((int) $event->created_by)->unique()->values();
            $event->participants()->sync($participants);
            $this->syncReminders($event, $data['reminders'] ?? []);

            $event->logActivity('calendar_event_updated', "Acara \"{$event->title}\" diperbarui");
            $this->notifyInvited($event, $participants->diff($before)->reject(fn ($id) => $id === $by->id));

            return $event->refresh();
        });
    }

    /** Drag-and-drop onto another day: keep the clock time, shift the date. */
    public function move(CalendarEvent $event, string $date): CalendarEvent
    {
        $target = Carbon::parse($date);
        $delta = $event->starts_at->copy()->startOfDay()->diffInDays($target->startOfDay(), false);

        if ($delta === 0) {
            return $event;
        }

        $event->starts_at = $event->starts_at->copy()->addDays($delta);
        $event->ends_at = $event->ends_at?->copy()->addDays($delta);
        $event->save();

        // Reminders follow the new time; ones already sent stay sent.
        foreach ($event->reminders as $reminder) {
            $reminder->update(['remind_at' => $event->starts_at->copy()->subMinutes($reminder->minutes_before)]);
        }

        $event->logActivity('calendar_event_moved', "Acara \"{$event->title}\" dipindah ke " . $event->starts_at->translatedFormat('d M Y'));

        return $event;
    }

    public function setStatus(CalendarEvent $event, string $status): CalendarEvent
    {
        if (! array_key_exists($status, CalendarEvent::STATUSES)) {
            throw new \InvalidArgumentException('Status tidak dikenal.');
        }

        $event->update(['status' => $status]);
        $event->logActivity('calendar_event_status', "Acara \"{$event->title}\" ditandai " . strtolower($event->statusLabel()));

        return $event;
    }

    public function delete(CalendarEvent $event): void
    {
        $event->logActivity('calendar_event_deleted', "Acara \"{$event->title}\" pada " . $event->starts_at->translatedFormat('d M Y') . ' dihapus');
        $event->delete();
    }

    /**
     * Send every reminder that is due and not yet sent. Called by the
     * scheduler each minute. Returns how many reminders went out.
     */
    public function sendDueReminders(): int
    {
        $due = CalendarEventReminder::query()
            ->with(['event.participants', 'event.client'])
            ->whereNull('sent_at')
            ->where('remind_at', '<=', now())
            ->whereHas('event', fn ($q) => $q->where('status', CalendarEvent::STATUS_SCHEDULED))
            ->get();

        foreach ($due as $reminder) {
            $event = $reminder->event;

            foreach ($event->participants as $user) {
                Notification::make()
                    ->title($event->kindLabel() . ': ' . $event->title)
                    ->body($this->when($event) . ($event->client ? ' · ' . $event->client->name : '') . ($event->location ? ' · ' . $event->location : ''))
                    ->icon('heroicon-o-calendar-days')
                    ->actions([Action::make('buka')->label('Buka kalender')->url($this->url($event))])
                    ->sendToDatabase($user);
            }

            $reminder->update(['sent_at' => now()]);
        }

        return $due->count();
    }

    /* ------------------------------------------------------------------ */
    /* Internals                                                           */
    /* ------------------------------------------------------------------ */

    /** @return array{0: Carbon, 1: ?Carbon} */
    protected function times(array $data): array
    {
        $date = Carbon::parse($data['date']);

        if (! empty($data['all_day'])) {
            return [$date->copy()->startOfDay(), $date->copy()->endOfDay()];
        }

        // Filament's TimePicker may hand back "H:i" or a full "Y-m-d H:i:s"; keep only the clock.
        $clock = fn ($t) => Carbon::parse($t)->format('H:i');
        $start = $date->copy()->setTimeFromTimeString($clock($data['start_time'] ?: '09:00'));
        $end = filled($data['end_time'] ?? null) ? $date->copy()->setTimeFromTimeString($clock($data['end_time'])) : null;

        if ($end && $end->lte($start)) {
            throw new \InvalidArgumentException('Jam selesai harus setelah jam mulai.');
        }

        return [$start, $end];
    }

    /** Replace reminder rows; keep sent ones for offsets that still exist. */
    protected function syncReminders(CalendarEvent $event, array $minutes): void
    {
        $minutes = collect($minutes)->map(fn ($m) => (int) $m)->unique()->values();
        $existing = $event->reminders()->get()->keyBy('minutes_before');

        $event->reminders()->whereNotIn('minutes_before', $minutes)->delete();

        foreach ($minutes as $m) {
            $remindAt = $event->starts_at->copy()->subMinutes($m);
            $row = $existing->get($m);

            if ($row) {
                // Re-arm if the time moved into the future again.
                $row->update(['remind_at' => $remindAt, 'sent_at' => $remindAt->isFuture() ? null : $row->sent_at]);
            } else {
                $event->reminders()->create(['minutes_before' => $m, 'remind_at' => $remindAt]);
            }
        }
    }

    protected function notifyInvited(CalendarEvent $event, Collection $userIds): void
    {
        if ($userIds->isEmpty()) {
            return;
        }

        $event->loadMissing('client');

        foreach (User::query()->whereIn('id', $userIds)->get() as $user) {
            Notification::make()
                ->title('Anda diundang: ' . $event->title)
                ->body($event->kindLabel() . ' · ' . $this->when($event) . ($event->client ? ' · ' . $event->client->name : ''))
                ->icon('heroicon-o-calendar-days')
                ->actions([Action::make('buka')->label('Buka kalender')->url($this->url($event))])
                ->sendToDatabase($user);
        }
    }

    protected function when(CalendarEvent $event): string
    {
        return $event->starts_at->locale('id')->translatedFormat('D, d M Y') . ' ' . $event->timeLabel();
    }

    protected function url(CalendarEvent $event): string
    {
        return \App\Filament\Pages\Calendar\Index::getUrl() . '?cursor=' . $event->starts_at->toDateString() . '&event=' . $event->id;
    }
}
