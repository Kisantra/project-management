<?php

namespace App\Filament\Pages\Calendar;

use App\Models\CalendarEvent;
use App\Models\Client;
use App\Models\Project;
use App\Models\User;
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
use Illuminate\Support\Collection;
use Livewire\Attributes\Url;
use Throwable;

/**
 * Team calendar for project management: appointments, data-request reminders
 * and other events, on a month grid or a week strip. Click a day to add, click
 * a chip to open it, drag a chip to another day to reschedule.
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

    /** Kind filter; empty = all kinds. */
    #[Url(as: 'jenis')]
    public array $kinds = [];

    /** Only events I created or am invited to. */
    #[Url(as: 'saya')]
    public bool $mine = false;

    /** Event open in the detail panel (also honoured from the URL so notifications can deep-link). */
    #[Url(as: 'event')]
    public ?int $selectedId = null;

    /** Event being edited in the form modal, null when creating. */
    public ?int $editingId = null;

    public ?array $data = [];

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

    public function toggleKind(string $kind): void
    {
        if (! array_key_exists($kind, CalendarEvent::KINDS)) {
            return;
        }

        $this->kinds = in_array($kind, $this->kinds, true)
            ? array_values(array_diff($this->kinds, [$kind]))
            : [...$this->kinds, $kind];
    }

    public function toggleMine(): void
    {
        $this->mine = ! $this->mine;
    }

    /* ------------------------------------------------------------------ */
    /* Form                                                                */
    /* ------------------------------------------------------------------ */

    public function form(Form $form): Form
    {
        return $form->statePath('data')->schema([
            Forms\Components\TextInput::make('title')->label('Judul')->required()->maxLength(150)
                ->placeholder('mis. Meeting laporan SPT Tahunan'),

            Forms\Components\Grid::make(2)->schema([
                Forms\Components\Select::make('kind')->label('Jenis')->required()->native(false)
                    ->options(collect(CalendarEvent::KINDS)->map(fn ($k) => $k['label'])->all()),
                Forms\Components\DatePicker::make('date')->label('Tanggal')->required()
                    ->native(false)->displayFormat('D, d M Y')->closeOnDateSelection(),
            ]),

            Forms\Components\Toggle::make('all_day')->label('Seharian')->live()->inline(),

            Forms\Components\Grid::make(2)->schema([
                Forms\Components\TimePicker::make('start_time')->label('Mulai')->seconds(false)->native(false)
                    ->minutesStep(5)->displayFormat('H:i')->required(fn (Forms\Get $get) => ! $get('all_day')),
                Forms\Components\TimePicker::make('end_time')->label('Selesai')->seconds(false)->native(false)
                    ->minutesStep(5)->displayFormat('H:i')->after('start_time')
                    ->validationMessages(['after' => 'Jam selesai harus setelah jam mulai.']),
            ])->hidden(fn (Forms\Get $get) => (bool) $get('all_day')),

            Forms\Components\TextInput::make('location')->label('Lokasi atau tautan meeting')->maxLength(255)
                ->placeholder('Kantor klien, ruang rapat, atau tautan Zoom/Meet'),

            Forms\Components\Grid::make(2)->schema([
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
            ]),

            Forms\Components\Select::make('participants')->label('Peserta')->multiple()->native(false)->searchable()->preload()
                ->options(fn () => $this->participantOptions())
                ->helperText('Peserta mendapat notifikasi undangan dan pengingat. Anda otomatis ikut sebagai peserta.'),

            Forms\Components\Select::make('reminders')->label('Pengingat')->multiple()->native(false)
                ->options(CalendarEvent::REMINDER_OPTIONS)
                ->helperText('Dikirim sebagai notifikasi dalam aplikasi ke semua peserta.'),

            Forms\Components\Textarea::make('description')->label('Catatan')->rows(3)->autosize()
                ->placeholder('Agenda, dokumen yang perlu dibawa, atau data yang diminta'),
        ]);
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

    /* ------------------------------------------------------------------ */
    /* Actions                                                             */
    /* ------------------------------------------------------------------ */

    public function openCreate(?string $date = null): void
    {
        $this->editingId = null;
        $this->form->fill($this->defaults($date && strtotime($date) ? $date : $this->cursor));
        $this->dispatch('open-modal', id: 'acara-form');
    }

    public function openEdit(int $id): void
    {
        $event = CalendarEvent::with(['participants', 'reminders'])->findOrFail($id);

        if (! $event->canBeManagedBy(auth()->user())) {
            Notification::make()->title('Hanya pembuat acara atau manajer yang bisa mengubahnya')->warning()->send();

            return;
        }

        $this->editingId = $event->id;
        $this->form->fill([
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
        ]);

        $this->dispatch('close-modal', id: 'acara-detail');
        $this->dispatch('open-modal', id: 'acara-form');
    }

    public function save(CalendarService $service): void
    {
        $data = $this->form->getState();

        try {
            if ($this->editingId) {
                $event = $service->update(CalendarEvent::findOrFail($this->editingId), $data, auth()->user());
                Notification::make()->title('Acara diperbarui')->success()->send();
            } else {
                $event = $service->create($data, auth()->user());
                Notification::make()->title('Acara dibuat')->body($event->dateLabel() . ' · ' . $event->timeLabel())->success()->send();
            }
        } catch (Throwable $e) {
            Notification::make()->title('Gagal menyimpan acara')->body($e->getMessage())->danger()->send();

            return;
        }

        $this->cursor = $event->starts_at->toDateString();
        $this->editingId = null;
        $this->dispatch('close-modal', id: 'acara-form');
    }

    public function open(int $id): void
    {
        $this->selectedId = $id;
        $this->dispatch('open-modal', id: 'acara-detail');
    }

    public function closeDetail(): void
    {
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

    public function delete(CalendarService $service): void
    {
        $event = $this->selectedId ? CalendarEvent::find($this->selectedId) : null;

        if (! $event || ! $event->canBeManagedBy(auth()->user())) {
            return;
        }

        $service->delete($event);
        $this->selectedId = null;
        $this->dispatch('close-modal', id: 'hapus-acara');
        $this->dispatch('close-modal', id: 'acara-detail');
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

    protected function getViewData(): array
    {
        $cursor = CarbonImmutable::parse($this->cursor);
        $today = CarbonImmutable::today();

        if ($this->mode === 'week') {
            $from = $cursor->startOfWeek(CarbonImmutable::MONDAY);
            $to = $from->addDays(6);
        } else {
            $from = $cursor->startOfMonth()->startOfWeek(CarbonImmutable::MONDAY);
            $to = $cursor->endOfMonth()->endOfWeek(CarbonImmutable::SUNDAY);
        }

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
            'weeks'         => array_chunk($days, 7),
            'rangeLabel'    => $this->mode === 'week'
                ? $from->locale('id')->translatedFormat('d M') . ' – ' . $to->locale('id')->translatedFormat('d M Y')
                : $cursor->locale('id')->translatedFormat('F Y'),
            'eventCount'    => $events->flatten(1)->count(),
            'selected'      => $selected,
            'canManage'     => $selected?->canBeManagedBy(auth()->user()) ?? false,
            'isParticipant' => $selected ? $selected->participants->contains('id', auth()->id()) : false,
            'kindsMeta'     => CalendarEvent::KINDS,
            'editing'       => $this->editingId !== null,
            'upcoming'      => $this->upcoming(),
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

        if ($this->kinds !== []) {
            $query->whereIn('kind', $this->kinds);
        }
        if ($this->mine) {
            $query->involving(auth()->user());
        }

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

    /** Next events for the phone-width agenda and the sidebar list. */
    protected function upcoming(): Collection
    {
        return CalendarEvent::query()
            ->with(['client:id,name'])
            ->where('status', CalendarEvent::STATUS_SCHEDULED)
            ->where(fn ($q) => $q->where('ends_at', '>=', now())->orWhere(fn ($w) => $w->whereNull('ends_at')->where('starts_at', '>=', now())))
            ->when($this->kinds !== [], fn ($q) => $q->whereIn('kind', $this->kinds))
            ->when($this->mine, fn ($q) => $q->involving(auth()->user()))
            ->orderBy('starts_at')
            ->limit(8)
            ->get();
    }
}
