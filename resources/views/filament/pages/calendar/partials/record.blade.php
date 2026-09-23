{{-- One event's record, as the floating panel shows it. Shared by the calendar page and the
     dashboard agenda so both open the exact same card.
     Expects: $selected, $canManage, $isParticipant, $history, $context ('calendar' | 'dashboard').
     Host component actions: closePanel, setStatus(id, status), setKind(id, kind);
     on the calendar also startEdit and askDelete. Facts that need the full form (schedule,
     client, project, participants) are pills that lead to it. --}}
@php
    $E = \App\Models\CalendarEvent::class;
    $tones = [
        'cyan'   => ['dot' => 'bg-primary-500', 'pill' => 'bg-primary-50 text-primary-800 ring-primary-600/20 dark:bg-primary-500/15 dark:text-primary-200 dark:ring-primary-400/30'],
        'amber'  => ['dot' => 'bg-amber-500',   'pill' => 'bg-amber-50 text-amber-800 ring-amber-600/20 dark:bg-amber-500/15 dark:text-amber-200 dark:ring-amber-400/30'],
        'violet' => ['dot' => 'bg-violet-500',  'pill' => 'bg-violet-50 text-violet-800 ring-violet-600/20 dark:bg-violet-500/15 dark:text-violet-200 dark:ring-violet-400/30'],
        'slate'  => ['dot' => 'bg-slate-500',   'pill' => 'bg-slate-100 text-slate-700 ring-slate-500/20 dark:bg-white/10 dark:text-gray-200 dark:ring-white/20'],
    ];
    $statusPill = [
        $E::STATUS_SCHEDULED => 'bg-white text-gray-800 ring-gray-950/15 dark:bg-gray-800 dark:text-gray-100 dark:ring-white/15',
        $E::STATUS_DONE      => 'bg-success-50 text-success-700 ring-success-600/25 dark:bg-success-500/15 dark:text-success-300 dark:ring-success-400/30',
        $E::STATUS_CANCELED  => 'bg-gray-100 text-gray-500 ring-gray-950/10 dark:bg-white/10 dark:text-gray-400 dark:ring-white/10',
    ];
    $sTone = $tones[$selected->tone()];
    $late = $selected->isScheduled() && $selected->isPast();
    $calendarUrl = \App\Filament\Pages\Calendar\Index::getUrl() . '?cursor=' . $selected->starts_at->toDateString() . '&event=' . $selected->id;
    $editUrl = $calendarUrl . '&edit=1';
    $iconBtn = 'grid h-9 w-9 place-items-center rounded-md ring-1 ring-gray-950/10 transition-colors hover:bg-gray-100 dark:ring-white/10 dark:hover:bg-white/5';
    /* A pill that opens a menu (status, kind) or leads to the form (everything else). */
    $pill = 'inline-flex max-w-full items-center gap-1.5 rounded-full py-1 pl-3 pr-2 text-[0.8438rem] font-semibold ring-1 ring-inset transition-[background-color,box-shadow]';
    $pillPlain = 'bg-white text-gray-800 ring-gray-950/15 hover:bg-gray-50 dark:bg-gray-800 dark:text-gray-100 dark:ring-white/15 dark:hover:bg-gray-700';
    $pillEmpty = 'bg-white text-gray-500 ring-gray-950/10 hover:bg-gray-50 dark:bg-gray-800 dark:text-gray-400 dark:ring-white/10';
    $menu = 'absolute left-0 z-30 mt-1 min-w-[11rem] overflow-hidden rounded-lg bg-white p-1 text-[0.8438rem] shadow-lg ring-1 ring-gray-950/10 dark:bg-gray-900 dark:ring-white/10';
    $item = 'flex w-full items-center gap-2 rounded-md px-2.5 py-1.5 text-left transition-colors hover:bg-gray-100 dark:hover:bg-white/5';
    // A fact that needs the form: on the calendar it flips the panel, on the dashboard it goes there.
    $toForm = $context === 'calendar' ? 'wire:click="startEdit"' : 'onclick="window.location=\'' . $editUrl . '\'"';
    $chevron = '<svg class="h-3.5 w-3.5 shrink-0 opacity-60" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M5.22 8.22a.75.75 0 0 1 1.06 0L10 11.94l3.72-3.72a.75.75 0 1 1 1.06 1.06l-4.25 4.25a.75.75 0 0 1-1.06 0L5.22 9.28a.75.75 0 0 1 0-1.06Z" clip-rule="evenodd"/></svg>';
@endphp

{{-- toolbar: the way out on the left, what can be done on the right --}}
<div class="flex items-center gap-2 border-b border-gray-100 px-3 py-2.5 sm:px-4 dark:border-white/5">
    <button type="button" wire:click="closePanel" class="grid h-9 w-9 place-items-center rounded-md text-gray-600 transition-colors hover:bg-gray-100 hover:text-gray-900 dark:text-gray-300 dark:hover:bg-white/5" aria-label="Tutup">
        <x-filament::icon icon="heroicon-m-x-mark" class="h-4 w-4" />
    </button>
    <div class="ml-auto flex items-center gap-1.5">
        @if ($context === 'dashboard')
            <a href="{{ $calendarUrl }}" class="inline-flex h-9 items-center gap-1.5 rounded-md px-2.5 text-xs font-semibold text-gray-700 ring-1 ring-gray-950/10 transition-colors hover:bg-gray-100 dark:text-gray-200 dark:ring-white/10 dark:hover:bg-white/5">
                <x-filament::icon icon="heroicon-m-calendar-days" class="h-4 w-4" />Buka di kalender
            </a>
        @endif
        @if ($canManage)
            @if ($context === 'calendar')
                <button type="button" wire:click="startEdit" title="Ubah acara" class="{{ $iconBtn }}"><x-filament::icon icon="heroicon-m-pencil" class="h-4 w-4 text-gray-700 dark:text-gray-200" /><span class="sr-only">Ubah acara</span></button>
                <button type="button" wire:click="askDelete" title="Hapus acara" class="{{ $iconBtn }}"><x-filament::icon icon="heroicon-m-trash" class="h-4 w-4 text-gray-700 dark:text-gray-200" /><span class="sr-only">Hapus acara</span></button>
            @else
                <a href="{{ $editUrl }}" title="Ubah acara" class="{{ $iconBtn }}"><x-filament::icon icon="heroicon-m-pencil" class="h-4 w-4 text-gray-700 dark:text-gray-200" /><span class="sr-only">Ubah acara</span></a>
            @endif
        @endif
    </div>
</div>

<div class="min-h-0 flex-1 overflow-y-auto">
    <div class="px-5 pt-6 sm:px-7">
        <h2 class="break-words text-[1.625rem] font-bold leading-tight tracking-tight text-gray-950 dark:text-white {{ $selected->status === $E::STATUS_DONE ? 'line-through decoration-2 decoration-gray-300' : '' }}">{{ $selected->title }}</h2>

        {{-- the record, one fact per line; the facts are the controls --}}
        <dl class="mt-6 grid grid-cols-[7.5rem_minmax(0,1fr)] items-center gap-x-4 gap-y-3.5 text-[0.8438rem] sm:grid-cols-[9rem_minmax(0,1fr)]">
            {{-- Status: a pill with its own menu --}}
            <dt class="flex items-center gap-2 leading-snug text-gray-500 dark:text-gray-400"><x-filament::icon icon="heroicon-o-arrow-path" class="h-4 w-4 shrink-0" />Status</dt>
            <dd class="flex min-w-0 flex-wrap items-center gap-x-2 gap-y-1.5">
                <div x-data="{ open: false }" class="relative">
                    <button type="button" @click="open = !open" :aria-expanded="open" @disabled(! $canManage && ! $isParticipant)
                            class="{{ $pill }} {{ $statusPill[$selected->status] }} disabled:cursor-default">
                        {{ $selected->statusLabel() }}{!! $chevron !!}
                    </button>
                    <div x-show="open" x-cloak @click.outside="open = false" x-transition.origin.top.left class="{{ $menu }}">
                        @foreach ($E::STATUSES as $key => $label)
                            <button type="button" @click="open = false; $wire.setStatus({{ $selected->id }}, '{{ $key }}')" @disabled(! $canManage && ! ($isParticipant && $key === $E::STATUS_DONE))
                                    class="{{ $item }} disabled:opacity-40 {{ $selected->status === $key ? 'font-semibold text-primary-700 dark:text-primary-300' : '' }}">
                                <span class="h-2 w-2 rounded-full {{ $key === $E::STATUS_DONE ? 'bg-success-500' : ($key === $E::STATUS_CANCELED ? 'bg-gray-400' : 'bg-primary-500') }}"></span>{{ $label }}
                            </button>
                        @endforeach
                    </div>
                </div>
                @if ($late)
                    <span class="text-xs font-semibold text-danger-600 dark:text-danger-400">terlewat {{ ($selected->ends_at ?? $selected->starts_at)->locale('id')->diffForHumans(['parts' => 1]) }}</span>
                @elseif ($selected->isScheduled())
                    <span class="text-xs text-gray-500 tabular-nums">{{ $selected->starts_at->locale('id')->diffForHumans(['parts' => 1]) }}</span>
                @endif
            </dd>

            {{-- Schedule: a pill that opens the form --}}
            <dt class="flex items-center gap-2 leading-snug text-gray-500 dark:text-gray-400"><x-filament::icon icon="heroicon-o-calendar-days" class="h-4 w-4 shrink-0" />Jadwal</dt>
            <dd class="min-w-0">
                <button type="button" {!! $canManage ? $toForm : 'disabled' !!} class="{{ $pill }} {{ $pillPlain }} disabled:cursor-default">
                    {{ $selected->dateLabel() }} <span class="font-normal text-gray-500">· {{ $selected->timeLabel() }}</span>@if ($canManage){!! $chevron !!}@endif
                </button>
            </dd>

            {{-- Kind: a pill with its own menu --}}
            <dt class="flex items-center gap-2 leading-snug text-gray-500 dark:text-gray-400"><x-filament::icon icon="heroicon-o-tag" class="h-4 w-4 shrink-0" />Jenis</dt>
            <dd class="min-w-0">
                <div x-data="{ open: false }" class="relative">
                    <button type="button" @click="open = !open" :aria-expanded="open" @disabled(! $canManage) class="{{ $pill }} {{ $sTone['pill'] }} disabled:cursor-default">
                        <span class="h-2 w-2 rounded-full {{ $sTone['dot'] }}"></span>{{ $selected->kindLabel() }}@if ($canManage){!! $chevron !!}@endif
                    </button>
                    <div x-show="open" x-cloak @click.outside="open = false" x-transition.origin.top.left class="{{ $menu }}">
                        @foreach ($E::KINDS as $key => $meta)
                            <button type="button" @click="open = false; $wire.setKind({{ $selected->id }}, '{{ $key }}')" class="{{ $item }} {{ $selected->kind === $key ? 'font-semibold text-primary-700 dark:text-primary-300' : '' }}">
                                <span class="h-2 w-2 rounded-full {{ $tones[$meta['tone']]['dot'] }}"></span>{{ $meta['label'] }}
                            </button>
                        @endforeach
                    </div>
                </div>
            </dd>

            <dt class="flex items-center gap-2 leading-snug text-gray-500 dark:text-gray-400"><x-filament::icon icon="heroicon-o-map-pin" class="h-4 w-4 shrink-0" />Lokasi</dt>
            <dd class="min-w-0">
                @if ($selected->location && \Illuminate\Support\Str::startsWith($selected->location, ['http://', 'https://']))
                    <a href="{{ $selected->location }}" target="_blank" rel="noopener" class="{{ $pill }} {{ $pillPlain }} text-primary-700 dark:text-primary-300"><span class="truncate">{{ preg_replace('#^https?://#', '', $selected->location) }}</span><x-filament::icon icon="heroicon-m-arrow-top-right-on-square" class="h-3.5 w-3.5 shrink-0" /></a>
                @else
                    <button type="button" {!! $canManage ? $toForm : 'disabled' !!} class="{{ $pill }} {{ $selected->location ? $pillPlain : $pillEmpty }} disabled:cursor-default">
                        <span class="truncate">{{ $selected->location ?: 'Belum ditentukan' }}</span>@if ($canManage){!! $chevron !!}@endif
                    </button>
                @endif
            </dd>

            <dt class="flex items-center gap-2 leading-snug text-gray-500 dark:text-gray-400"><x-filament::icon icon="heroicon-o-building-office-2" class="h-4 w-4 shrink-0" />Klien</dt>
            <dd class="flex min-w-0 flex-wrap items-center gap-1.5">
                @if ($selected->client)
                    <a href="{{ \App\Filament\Resources\ClientResource::getUrl('view', ['record' => $selected->client_id]) }}" class="{{ $pill }} {{ $pillPlain }} text-primary-700 dark:text-primary-300"><span class="truncate">{{ $selected->client->name }}</span><x-filament::icon icon="heroicon-m-chevron-right" class="h-3.5 w-3.5 shrink-0" /></a>
                @else
                    <button type="button" {!! $canManage ? $toForm : 'disabled' !!} class="{{ $pill }} {{ $pillEmpty }} disabled:cursor-default">Tidak terkait @if ($canManage){!! $chevron !!}@endif</button>
                @endif
            </dd>

            <dt class="flex items-center gap-2 leading-snug text-gray-500 dark:text-gray-400"><x-filament::icon icon="heroicon-o-folder-open" class="h-4 w-4 shrink-0" />Proyek</dt>
            <dd class="min-w-0">
                @if ($selected->project)
                    <a href="{{ \App\Filament\Resources\ProjectResource::getUrl('view', ['record' => $selected->project_id]) }}" class="{{ $pill }} {{ $pillPlain }} text-primary-700 dark:text-primary-300"><span class="truncate">{{ $selected->project->name }}</span><x-filament::icon icon="heroicon-m-chevron-right" class="h-3.5 w-3.5 shrink-0" /></a>
                @else
                    <button type="button" {!! $canManage ? $toForm : 'disabled' !!} class="{{ $pill }} {{ $pillEmpty }} disabled:cursor-default">Tidak terkait @if ($canManage){!! $chevron !!}@endif</button>
                @endif
            </dd>

            <dt class="flex items-start gap-2 self-start pt-1.5 leading-snug text-gray-500 dark:text-gray-400"><x-filament::icon icon="heroicon-o-users" class="h-4 w-4 shrink-0" />Peserta</dt>
            <dd class="flex min-w-0 flex-wrap items-center gap-1.5">
                @foreach ($selected->participants as $u)
                    <span class="inline-flex items-center gap-1.5 rounded-full bg-gray-100 py-0.5 pl-0.5 pr-2.5 text-xs font-semibold text-gray-700 dark:bg-white/10 dark:text-gray-200">
                        @if ($u->avatar_url)
                            <img src="{{ \Illuminate\Support\Str::startsWith($u->avatar_url, 'http') ? $u->avatar_url : asset($u->avatar_url) }}" alt="" class="h-5 w-5 rounded-full object-cover">
                        @else
                            <span class="grid h-5 w-5 place-items-center rounded-full bg-white text-[9px] font-bold text-gray-600 dark:bg-gray-800 dark:text-gray-300">{{ strtoupper(mb_substr($u->name, 0, 1)) }}</span>
                        @endif
                        {{ $u->name }}
                    </span>
                @endforeach
                @if ($canManage)
                    <button type="button" {!! $toForm !!} class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs font-semibold text-primary-700 ring-1 ring-inset ring-primary-600/25 transition-colors hover:bg-primary-50 dark:text-primary-300 dark:ring-primary-400/30 dark:hover:bg-primary-500/10">+ Tambah</button>
                @endif
            </dd>

            <dt class="flex items-start gap-2 self-start pt-0.5 leading-snug text-gray-500 dark:text-gray-400"><x-filament::icon icon="heroicon-o-bell-alert" class="h-4 w-4 shrink-0" />Pengingat</dt>
            <dd class="min-w-0 font-semibold text-gray-900 dark:text-gray-100">
                @forelse ($selected->reminders as $rm)
                    <span class="block">{{ $rm->label() }} <span class="text-xs font-normal text-gray-400 tabular-nums">{{ $rm->remind_at->translatedFormat('d M H.i') }}{{ $rm->sent_at ? ' · terkirim' : '' }}</span></span>
                @empty <span class="font-normal text-gray-400">Tidak ada</span> @endforelse
            </dd>

            <dt class="flex items-center gap-2 leading-snug text-gray-500 dark:text-gray-400"><x-filament::icon icon="heroicon-o-user" class="h-4 w-4 shrink-0" />Dibuat oleh</dt>
            <dd class="min-w-0 font-semibold text-gray-900 dark:text-gray-100">{{ $selected->creator?->name }} <span class="text-xs font-normal text-gray-400">· {{ $selected->created_at->translatedFormat('d M Y') }}</span></dd>
        </dl>

        {{-- the notes, in a bordered box like the reference's text copy --}}
        <div class="mt-6 rounded-lg border border-gray-200 px-4 py-3.5 dark:border-white/10">
            <h3 class="text-[0.8438rem] font-bold text-gray-900 dark:text-gray-100">Catatan</h3>
            @if ($selected->description)
                <p class="mt-1.5 whitespace-pre-line text-[0.8438rem] leading-relaxed text-gray-700 dark:text-gray-300">{{ $selected->description }}</p>
            @else
                <p class="mt-1.5 text-[0.8438rem] leading-relaxed text-gray-500">Belum ada catatan.
                    @if ($canManage)<button type="button" {!! $toForm !!} class="font-bold text-primary-700 underline underline-offset-4 dark:text-primary-300">Tulis sekarang</button>@endif
                </p>
            @endif
        </div>
    </div>

    {{-- history under the record --}}
    <div class="mt-6 border-b border-gray-100 px-5 sm:px-7 dark:border-white/5">
        <span class="-mb-px inline-flex items-center gap-1.5 border-b-2 border-primary-600 py-2.5 text-[0.8438rem] font-bold text-primary-700 dark:text-primary-300">Riwayat
            <span class="rounded-full bg-primary-50 px-1.5 py-px text-[11px] font-extrabold text-primary-700 tabular-nums dark:bg-primary-500/15 dark:text-primary-300">{{ $history->count() }}</span>
        </span>
    </div>
    <div class="px-5 py-5 sm:px-7">
        @if ($history->isEmpty())
            <p class="rounded-lg border border-dashed border-gray-200 bg-gray-50/60 px-3 py-6 text-center text-xs text-gray-500 dark:border-white/10 dark:bg-white/5">Belum ada riwayat yang tercatat.</p>
        @else
            <ol class="flex flex-col">
                @foreach ($history as $row)
                    <li class="flex gap-3.5">
                        <span class="flex flex-col items-center" aria-hidden="true">
                            <span class="mt-1 h-3 w-3 shrink-0 rounded-full ring-4 ring-white dark:ring-gray-900 {{ $loop->first ? 'bg-primary-500' : 'bg-gray-300 dark:bg-gray-600' }}"></span>
                            @unless ($loop->last)<span class="w-px flex-1 bg-gray-200 dark:bg-white/10"></span>@endunless
                        </span>
                        <span class="min-w-0 flex-1 {{ $loop->last ? '' : 'pb-4' }}">
                            <span class="flex flex-wrap items-baseline gap-x-2 gap-y-0.5">
                                <span class="text-[0.8438rem] text-gray-800 dark:text-gray-200">{{ preg_replace('/ oleh .+$/u', '', $row->description) }}</span>
                                @if ($loop->first)<span class="rounded-full bg-primary-50 px-2 py-0.5 text-[11px] font-extrabold text-primary-700 dark:bg-primary-500/15 dark:text-primary-300">Terbaru</span>@endif
                            </span>
                            <span class="mt-0.5 block text-xs text-gray-500 tabular-nums">{{ $row->user?->name ?? 'Sistem' }} · {{ $row->created_at->translatedFormat('d M Y H.i') }}</span>
                        </span>
                    </li>
                @endforeach
            </ol>
        @endif
    </div>
</div>
