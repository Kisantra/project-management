<?php

namespace App\Filament\Pages\Calendar;

use App\Models\CalendarEvent;
use App\Models\Client;
use App\Models\Project;
use App\Models\User;
use App\Models\UserActivity;
use App\Services\CalendarService;
use App\Services\TaxDeadlineService;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Livewire\Attributes\Url;
use Throwable;

/**
 * Team calendar for project management, laid out like the content calendar
 * in client-management: a month grid (or week strip), a new-event dialog over
 * the grid, and a record panel that floats in from the right and turns into
 * the edit form in place.
 */
class Index extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-calendar-days';
    protected static ?string $navigationGroup = 'Project Management';
    protected static ?string $navigationLabel = 'Kalender';
    protected static ?int $navigationSort = 3;
    protected static ?string $slug = 'kalender';
    protected static ?string $title = 'Kalender';
    protected static string $view = 'filament.pages.calendar.index';

    /** Any date inside the month/week being shown. */
    #[Url(as: 'cursor')]
    public string $cursor = '';

    #[Url(as: 'view')]
    public string $mode = 'month';

    /** Free-text search over title, client and location. */
    #[Url(as: 'q')]
    public string $q = '';

    /** Status segment: all | scheduled | done | late. */
    #[Url(as: 'status')]
    public string $status = 'all';

    /** Kind dropdown; '' = all kinds. */
    #[Url(as: 'jenis')]
    public string $kind = '';

    /** Participant dropdown; null = everyone. */
    #[Url(as: 'peserta')]
    public ?int $participant = null;

    /** Only events I created or am invited to. */
    #[Url(as: 'saya')]
    public bool $mine = false;

    /** Event in the record panel (also honoured from the URL so notifications can deep-link). */
    #[Url(as: 'event')]
    public ?int $selectedId = null;

    /** Overlay state, entangled with Alpine so transitions run client-side. */
    public bool $panelOpen = false;
    public bool $dialogOpen = false;
    public bool $deleteOpen = false;

    /** 'view' shows the record; 'edit' turns the same panel into the form. */
    public string $panelMode = 'view';

    /** New-event form state and in-panel edit form state. */
    public ?array $data = [];
    public ?array $editData = [];

    public static function shouldRegisterNavigation(): bool
    {
        return auth()->user()->can('kalender.*');
    }

    public static function canAccess(): bool
    {
        return auth()->user()->can('kalender.*');
    }

    public function mount(): void
    {
        if ($this->cursor === '' || ! strtotime($this->cursor)) {
            $this->cursor = today()->toDateString();
        }
        if (! in_array($this->mode, ['month', 'week'], true)) {
            $this->mode = 'month';
        }

        $this->form->fill($this->defaults(today()->toDateString()));
        $this->editForm->fill($this->defaults(today()->toDateString()));

        // Deep link from a notification or the dashboard: open the record straight away,
        // in edit mode when asked for (?edit=1) and allowed.
        if ($this->selectedId && CalendarEvent::whereKey($this->selectedId)->exists()) {
            $this->panelOpen = true;
            if (request()->boolean('edit')) {
                $this->startEdit();
            }
        } else {
            $this->selectedId = null;
        }
    }

    /** The page draws its own header (title, counts, month stepper, add button). */
    public function getHeader(): ?View
    {
        return view('filament.pages.calendar.header', $this->headerData());
    }

    /* ------------------------------------------------------------------ */
    /* Navigation                                                          */
    /* ------------------------------------------------------------------ */

    public function previous(): void
    {
        $this->cursor = $this->mode === 'week'
            ? Carbon::parse($this->cursor)->subWeek()->toDateString()
            : Carbon::parse($this->cursor)->startOfMonth()->subMonth()->toDateString();
    }

    public function next(): void
    {
        $this->cursor = $this->mode === 'week'
            ? Carbon::parse($this->cursor)->addWeek()->toDateString()
            : Carbon::parse($this->cursor)->startOfMonth()->addMonth()->toDateString();
    }

    public function today(): void
    {
        $this->cursor = today()->toDateString();
    }

    public function setMode(string $mode): void
    {
        if (in_array($mode, ['month', 'week'], true)) {
            $this->mode = $mode;
        }
    }

    /** Jump to a day in week view (used by "+N lagi" on a crowded cell). */
    public function showDay(string $date): void
    {
        $this->cursor = Carbon::parse($date)->toDateString();
        $this->mode = 'week';
    }

    public function setStatusFilter(string $status): void
    {
        if (in_array($status, ['all', 'scheduled', 'done', 'late'], true)) {
            $this->status = $status;
        }
    }

    public function setKindFilter(string $kind): void
    {
        $this->kind = array_key_exists($kind, CalendarEvent::KINDS) ? $kind : '';
    }

    public function setParticipant(?int $userId): void
    {
        $this->participant = $userId ?: null;
    }

    public function toggleMine(): void
    {
        $this->mine = ! $this->mine;
    }

    public function clearFilters(): void
    {
        $this->q = '';
        $this->status = 'all';
        $this->kind = '';
        $this->participant = null;
        $this->mine = false;
    }

    protected function hasFilters(): bool
    {
        return $this->q !== '' || $this->status !== 'all' || $this->kind !== '' || $this->participant !== null || $this->mine;
    }

    /** Inline change of the kind from the record panel's pill. */
    public function setKind(int $id, string $kind): void
    {
        $event = CalendarEvent::findOrFail($id);

        if (! $event->canBeManagedBy(auth()->user()) || ! array_key_exists($kind, CalendarEvent::KINDS)) {
            return;
        }

        $event->update(['kind' => $kind]);
        $event->logActivity('calendar_event_updated', 'Jenis acara "' . $event->title . '" diubah menjadi ' . $event->kindLabel());
    }

    /* ------------------------------------------------------------------ */
    /* Forms                                                               */
    /* ------------------------------------------------------------------ */

    protected function getForms(): array
    {
        return ['form', 'editForm'];
    }

    public function form(Form $form): Form
    {
        return $form->statePath('data')->columns(2)->schema($this->schema('data'));
    }

    public function editForm(Form $form): Form
    {
        return $form->statePath('editData')->columns(2)->schema($this->schema('editData'));
    }

    /** One schema for both forms; $path only feeds the quick time-slot pills. */
    protected function schema(string $path): array
    {
        return [
            Forms\Components\TextInput::make('title')->label('Judul')->required()->maxLength(150)
                ->placeholder('mis. Meeting laporan SPT Tahunan')->columnSpanFull(),

            Forms\Components\Select::make('kind')->label('Jenis')->required()->native(false)
                ->options(collect(CalendarEvent::KINDS)->map(fn ($k) => $k['label'])->all()),
            Forms\Components\DatePicker::make('date')->label('Tanggal')->required()
                ->native(false)->displayFormat('D, d M Y')->closeOnDateSelection(),

            Forms\Components\Toggle::make('all_day')->label('Seharian')->live()->inline()->columnSpanFull(),

            Forms\Components\TimePicker::make('start_time')->label('Mulai')->seconds(false)->native(false)
                ->minutesStep(5)->displayFormat('H:i')->required(fn (Forms\Get $get) => ! $get('all_day'))
                ->hidden(fn (Forms\Get $get) => (bool) $get('all_day')),
            Forms\Components\TimePicker::make('end_time')->label('Selesai')->seconds(false)->native(false)
                ->minutesStep(5)->displayFormat('H:i')->after('start_time')
                ->validationMessages(['after' => 'Jam selesai harus setelah jam mulai.'])
                ->hidden(fn (Forms\Get $get) => (bool) $get('all_day')),

            // Quick picks: one click sets the start hour; the pickers stay for anything else.
            Forms\Components\View::make('filament.pages.calendar.partials.time-slots')
                ->viewData(['path' => $path])
                ->hidden(fn (Forms\Get $get) => (bool) $get('all_day'))
                ->columnSpanFull(),

            Forms\Components\TextInput::make('location')->label('Lokasi atau tautan meeting')->maxLength(255)
                ->placeholder('Kantor klien, ruang rapat, atau tautan Zoom/Meet')->columnSpanFull(),

            Forms\Components\Select::make('client_id')->label('Klien')->native(false)->searchable()->preload()
                ->options(fn () => Client::query()->where('status', 'Active')->orderBy('name')->pluck('name', 'id'))
                ->placeholder('Tidak terkait')->live()
                ->afterStateUpdated(fn (Forms\Set $set) => $set('project_id', null)),
            Forms\Components\Select::make('project_id')->label('Proyek')->native(false)->searchable()
                ->options(fn (Forms\Get $get) => Project::query()
                    ->when($get('client_id'), fn ($q, $id) => $q->where('client_id', $id))
                    ->whereNotIn('status', ['completed', 'canceled'])
                    ->orderBy('name')->limit(200)->pluck('name', 'id'))
                ->placeholder('Tidak terkait')
                ->helperText('Memilih klien mempersempit daftar proyek.'),

            Forms\Components\Select::make('participants')->label('Peserta')->multiple()->native(false)->searchable()->preload()
                ->options(fn () => $this->participantOptions())
                ->helperText('Peserta mendapat undangan dan pengingat. Anda otomatis ikut.'),

            Forms\Components\Select::make('reminders')->label('Pengingat')->multiple()->native(false)
                ->options(CalendarEvent::REMINDER_OPTIONS)
                ->helperText('Notifikasi dalam aplikasi ke semua peserta.'),

            Forms\Components\Textarea::make('description')->label('Catatan')->rows(3)->autosize()
                ->placeholder('Agenda, dokumen yang perlu dibawa, atau data yang diminta')->columnSpanFull(),
        ];
    }

    protected function participantOptions(): array
    {
        return User::query()
            ->where(fn ($q) => $q->where('status', 'active')->orWhereNull('status'))
            ->whereDoesntHave('roles', fn ($r) => $r->where('name', 'client'))
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }

    protected function defaults(string $date): array
    {
        return [
            'title'        => '',
            'kind'         => CalendarEvent::KIND_APPOINTMENT,
            'date'         => $date,
            'all_day'      => false,
            'start_time'   => '09:00',
            'end_time'     => '10:00',
            'location'     => '',
            'client_id'    => null,
            'project_id'   => null,
            'participants' => [],
            'reminders'    => [1440, 60],
            'description'  => '',
        ];
    }

    protected function fillFromEvent(CalendarEvent $event): array
    {
        return [
            'title'        => $event->title,
            'kind'         => $event->kind,
            'date'         => $event->starts_at->toDateString(),
            'all_day'      => $event->all_day,
            'start_time'   => $event->all_day ? '09:00' : $event->starts_at->format('H:i'),
            'end_time'     => $event->all_day || ! $event->ends_at ? null : $event->ends_at->format('H:i'),
            'location'     => $event->location,
            'client_id'    => $event->client_id,
            'project_id'   => $event->project_id,
            'participants' => $event->participants->pluck('id')->reject(fn ($id) => (int) $id === (int) $event->created_by)->values()->all(),
            'reminders'    => $event->reminders->pluck('minutes_before')->all(),
            'description'  => $event->description,
        ];
    }

    /* ------------------------------------------------------------------ */
    /* Create (dialog)                                                     */
    /* ------------------------------------------------------------------ */

    public function openCreate(?string $date = null): void
    {
        $this->form->fill($this->defaults($date && strtotime($date) ? $date : $this->defaultDay()));
        $this->dialogOpen = true;
    }

    /** New events start today when today is in view, else on the first visible day of the month. */
    protected function defaultDay(): string
    {
        $cursor = Carbon::parse($this->cursor);

        return today()->isSameMonth($cursor) ? today()->toDateString() : $cursor->startOfMonth()->toDateString();
    }

    public function save(CalendarService $service): void
    {
        $data = $this->form->getState();

        try {
            $event = $service->create($data, auth()->user());
        } catch (Throwable $e) {
            Notification::make()->title('Gagal menyimpan acara')->body($e->getMessage())->danger()->send();

            return;
        }

        $this->cursor = $event->starts_at->toDateString();
        $this->dialogOpen = false;
        Notification::make()->title('Acara dibuat')->body($event->dateLabel() . ' · ' . $event->timeLabel())->success()->send();
    }

    /* ------------------------------------------------------------------ */
    /* Record panel                                                        */
    /* ------------------------------------------------------------------ */

    public function open(int $id): void
    {
        $this->selectedId = $id;
        $this->panelMode = 'view';
        $this->panelOpen = true;
    }

    public function closePanel(): void
    {
        $this->panelOpen = false;
        $this->panelMode = 'view';
        $this->selectedId = null;
    }

    /** The record's own card becomes the form, in place. */
    public function startEdit(): void
    {
        $event = $this->selectedId ? CalendarEvent::with(['participants', 'reminders'])->find($this->selectedId) : null;

        if (! $event || ! $event->canBeManagedBy(auth()->user())) {
            Notification::make()->title('Hanya pembuat acara atau manajer yang bisa mengubahnya')->warning()->send();

            return;
        }

        $this->editForm->fill($this->fillFromEvent($event));
        $this->panelMode = 'edit';
    }

    public function cancelEdit(): void
    {
        $this->panelMode = 'view';
    }

    public function update(CalendarService $service): void
    {
        $event = $this->selectedId ? CalendarEvent::find($this->selectedId) : null;

        if (! $event || ! $event->canBeManagedBy(auth()->user())) {
            return;
        }

        $data = $this->editForm->getState();

        try {
            $event = $service->update($event, $data, auth()->user());
        } catch (Throwable $e) {
            Notification::make()->title('Gagal menyimpan perubahan')->body($e->getMessage())->danger()->send();

            return;
        }

        $this->cursor = $event->starts_at->toDateString();
        $this->panelMode = 'view';
        Notification::make()->title('Acara diperbarui')->success()->send();
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

    public function askDelete(): void
    {
        $this->deleteOpen = true;
    }

    public function delete(CalendarService $service): void
    {
        $event = $this->selectedId ? CalendarEvent::find($this->selectedId) : null;

        if (! $event || ! $event->canBeManagedBy(auth()->user())) {
            return;
        }

        $service->delete($event);
        $this->deleteOpen = false;
        $this->closePanel();
        Notification::make()->title('Acara dihapus')->success()->send();
    }

    /** Drag-and-drop target. */
    public function move(int $id, string $date, CalendarService $service): void
    {
        $event = CalendarEvent::find($id);

        if (! $event || ! strtotime($date)) {
            return;
        }
        if (! $event->canBeManagedBy(auth()->user())) {
            Notification::make()->title('Hanya pembuat acara atau manajer yang bisa memindahkannya')->warning()->send();

            return;
        }

        $service->move($event, $date);
        Notification::make()->title('Acara dipindah ke ' . $event->starts_at->translatedFormat('d M Y'))->success()->send();
    }

    /* ------------------------------------------------------------------ */
    /* View data                                                           */
    /* ------------------------------------------------------------------ */

    /** @return array{from: CarbonImmutable, to: CarbonImmutable, cursor: CarbonImmutable} */
    protected function window(): array
    {
        $cursor = CarbonImmutable::parse($this->cursor);

        if ($this->mode === 'week') {
            $from = $cursor->startOfWeek(CarbonImmutable::MONDAY);

            return ['from' => $from, 'to' => $from->addDays(6), 'cursor' => $cursor];
        }

        return [
            'from'   => $cursor->startOfMonth()->startOfWeek(CarbonImmutable::MONDAY),
            'to'     => $cursor->endOfMonth()->endOfWeek(CarbonImmutable::SUNDAY),
            'cursor' => $cursor,
        ];
    }

    protected function headerData(): array
    {
        ['from' => $from, 'to' => $to, 'cursor' => $cursor] = $this->window();

        // Counts for the status segments respect every filter except the status segment itself.
        $inRange = $this->applyFilters(
            CalendarEvent::query()->between($from->toMutable(), $to->toMutable())->where('status', '<>', CalendarEvent::STATUS_CANCELED),
            withStatus: false,
        )->get(['id', 'status', 'starts_at', 'ends_at']);

        $late = $inRange->filter(fn ($e) => $e->status === CalendarEvent::STATUS_SCHEDULED && ($e->ends_at ?? $e->starts_at)->isPast())->count();
        $done = $inRange->where('status', CalendarEvent::STATUS_DONE)->count();

        return [
            'counts'     => ['all' => $inRange->count(), 'scheduled' => $inRange->count() - $done - $late, 'done' => $done, 'late' => $late],
            'rangeLabel' => $this->mode === 'week'
                ? $from->locale('id')->translatedFormat('d M') . ' – ' . $to->locale('id')->translatedFormat('d M Y')
                : $cursor->locale('id')->translatedFormat('F Y'),
            'isCurrent'  => $this->mode === 'week' ? $cursor->isSameWeek(today()) : $cursor->isSameMonth(today()),
            'total'      => $inRange->count(),
            'done'       => $done,
            'late'       => $late,
            'hasFilters' => $this->hasFilters(),
        ];
    }

    /** Search, kind, participant, "mine" and (optionally) the status segment. */
    protected function applyFilters($query, bool $withStatus = true)
    {
        $query->when($this->q !== '', function ($q) {
            $term = '%' . $this->q . '%';
            $q->where(fn ($w) => $w->where('title', 'like', $term)
                ->orWhere('location', 'like', $term)
                ->orWhereHas('client', fn ($c) => $c->where('name', 'like', $term)));
        })
            ->when($this->kind !== '', fn ($q) => $q->where('kind', $this->kind))
            ->when($this->participant, fn ($q) => $q->whereHas('participants', fn ($p) => $p->where('users.id', $this->participant)))
            ->when($this->mine, fn ($q) => $q->involving(auth()->user()));

        if ($withStatus) {
            $now = now();
            match ($this->status) {
                'done'      => $query->where('status', CalendarEvent::STATUS_DONE),
                'late'      => $query->where('status', CalendarEvent::STATUS_SCHEDULED)->whereRaw('COALESCE(ends_at, starts_at) < ?', [$now]),
                'scheduled' => $query->where('status', CalendarEvent::STATUS_SCHEDULED)->whereRaw('COALESCE(ends_at, starts_at) >= ?', [$now]),
                default     => null,
            };
        }

        return $query;
    }

    protected function getViewData(): array
    {
        ['from' => $from, 'to' => $to, 'cursor' => $cursor] = $this->window();
        $today = CarbonImmutable::today();

        $events = $this->eventsBetween($from, $to);
        $keyDates = $this->keyDatesBetween($from, $to);

        $days = [];
        for ($d = $from; $d->lte($to); $d = $d->addDay()) {
            $key = $d->toDateString();
            $days[] = [
                'date'     => $key,
                'day'      => $d->day,
                'label'    => $d->locale('id')->translatedFormat('D, d M'),
                'inMonth'  => $this->mode === 'week' || $d->isSameMonth($cursor),
                'isToday'  => $d->isSameDay($today),
                'isPast'   => $d->lt($today),
                'weekend'  => $d->isWeekend(),
                'events'   => $events->get($key, collect()),
                'keyDates' => $keyDates[$key] ?? [],
            ];
        }

        $selected = $this->selectedId
            ? CalendarEvent::with(['client:id,name', 'project:id,name', 'creator:id,name', 'participants:id,name,avatar_url', 'reminders'])->find($this->selectedId)
            : null;

        return [
            'days'          => $days,
            'monthLabel'    => $cursor->locale('id')->translatedFormat('F Y'),
            'eventCount'    => $events->flatten(1)->unique('id')->count(),
            'selected'      => $selected,
            'canManage'     => $selected?->canBeManagedBy(auth()->user()) ?? false,
            'isParticipant' => $selected ? $selected->participants->contains('id', auth()->id()) : false,
            'history'       => $selected ? $this->history($selected) : collect(),
            'kindsMeta'     => CalendarEvent::KINDS,
            'hasFilters'    => $this->hasFilters(),
            'counts'        => $this->headerData()['counts'],
            'participants'  => $this->participantOptions(),
        ];
    }

    /** Events keyed by date (multi-day events appear on each day they span). */
    protected function eventsBetween(CarbonImmutable $from, CarbonImmutable $to): Collection
    {
        $query = CalendarEvent::query()
            ->with(['client:id,name', 'participants:id,name'])
            ->between($from->toMutable(), $to->toMutable())
            ->where('status', '<>', CalendarEvent::STATUS_CANCELED)
            ->orderBy('all_day', 'desc')->orderBy('starts_at');

        $this->applyFilters($query);

        $byDate = [];
        foreach ($query->get() as $event) {
            $start = $event->starts_at->copy()->startOfDay()->max($from->toMutable());
            $end = ($event->ends_at ?? $event->starts_at)->copy()->startOfDay()->min($to->toMutable());
            for ($d = $start->copy(); $d->lte($end); $d->addDay()) {
                $byDate[$d->toDateString()][] = $event;
            }
        }

        return collect($byDate)->map(fn ($list) => collect($list));
    }

    /** Tax filing deadlines from the deadline service, keyed by date. */
    protected function keyDatesBetween(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $service = app(TaxDeadlineService::class);
        $out = [];

        for ($m = $from->startOfMonth(); $m->lte($to); $m = $m->addMonth()) {
            foreach ($service->deadlinesFor($m->toMutable()) as $deadline) {
                $date = $deadline['date']->toDateString();
                if ($date < $from->toDateString() || $date > $to->toDateString()) {
                    continue;
                }
                $out[$date][] = [
                    'label' => $deadline['short'],
                    'title' => $deadline['label'] . ' · masa ' . $deadline['period_label'],
                    'kind'  => $deadline['key'],
                ];
            }
        }

        return $out;
    }

    /** The event's own history from the activity log, newest first. */
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
