<x-filament-panels::page>
    @php
        /** Chip + dot classes per kind tone. Spelled out so Tailwind sees them. */
        $chip = [
            'cyan'   => 'bg-primary-50 text-primary-800 ring-primary-600/20 hover:bg-primary-100 dark:bg-primary-500/15 dark:text-primary-200 dark:ring-primary-400/30',
            'amber'  => 'bg-amber-50 text-amber-800 ring-amber-600/20 hover:bg-amber-100 dark:bg-amber-500/15 dark:text-amber-200 dark:ring-amber-400/30',
            'violet' => 'bg-violet-50 text-violet-800 ring-violet-600/20 hover:bg-violet-100 dark:bg-violet-500/15 dark:text-violet-200 dark:ring-violet-400/30',
            'slate'  => 'bg-slate-100 text-slate-700 ring-slate-500/20 hover:bg-slate-200 dark:bg-white/10 dark:text-gray-200 dark:ring-white/20',
        ];
        $dot = ['cyan' => 'bg-primary-500', 'amber' => 'bg-amber-500', 'violet' => 'bg-violet-500', 'slate' => 'bg-slate-500'];
        $badge = [
            'cyan'   => 'bg-primary-50 text-primary-700 dark:bg-primary-500/15 dark:text-primary-300',
            'amber'  => 'bg-amber-50 text-amber-700 dark:bg-amber-500/15 dark:text-amber-300',
            'violet' => 'bg-violet-50 text-violet-700 dark:bg-violet-500/15 dark:text-violet-300',
            'slate'  => 'bg-slate-100 text-slate-700 dark:bg-white/10 dark:text-gray-200',
        ];
        $shown = 3;
    @endphp

    <div x-data="{
            dragging: null,
            over: null,
            start(e, id) { this.dragging = id; e.dataTransfer.effectAllowed = 'move'; e.dataTransfer.setData('text/plain', String(id)); },
            end() { this.dragging = null; this.over = null; },
            drop(date) { if (this.dragging) { $wire.move(this.dragging, date); } this.end(); },
         }"
         class="space-y-4">

        {{-- ================= TOOLBAR ================= --}}
        <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
            <div class="flex items-center gap-2">
                <div class="inline-flex overflow-hidden rounded-lg bg-white shadow-sm ring-1 ring-gray-950/10 dark:bg-gray-900 dark:ring-white/10">
                    <button type="button" wire:click="previous" class="px-2.5 py-1.5 text-gray-500 transition-colors hover:bg-gray-50 hover:text-gray-900 dark:hover:bg-white/5 dark:hover:text-white" aria-label="Sebelumnya">
                        <x-filament::icon icon="heroicon-m-chevron-left" class="h-4 w-4" />
                    </button>
                    <button type="button" wire:click="today" class="border-x border-gray-950/10 px-3 py-1.5 text-xs font-medium text-gray-700 transition-colors hover:bg-gray-50 dark:border-white/10 dark:text-gray-200 dark:hover:bg-white/5">Hari ini</button>
                    <button type="button" wire:click="next" class="px-2.5 py-1.5 text-gray-500 transition-colors hover:bg-gray-50 hover:text-gray-900 dark:hover:bg-white/5 dark:hover:text-white" aria-label="Berikutnya">
                        <x-filament::icon icon="heroicon-m-chevron-right" class="h-4 w-4" />
                    </button>
                </div>
                <h2 class="text-lg font-semibold tracking-tight text-gray-950 dark:text-white" wire:loading.class="opacity-60" wire:target="previous,next,today,setMode">{{ $rangeLabel }}</h2>
                <span class="text-xs text-gray-500">{{ $eventCount }} acara</span>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                {{-- kind filter pills --}}
                <div class="flex flex-wrap items-center gap-1.5" role="group" aria-label="Jenis acara">
                    @foreach ($kindsMeta as $key => $meta)
                        @php $on = in_array($key, $kinds, true); @endphp
                        <button type="button" wire:click="toggleKind('{{ $key }}')" aria-pressed="{{ $on ? 'true' : 'false' }}"
                                class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-medium ring-1 ring-inset transition-colors {{ $on ? 'bg-gray-900 text-white ring-gray-900 dark:bg-white dark:text-gray-900 dark:ring-white' : 'bg-white text-gray-600 ring-gray-950/10 hover:bg-gray-50 dark:bg-gray-900 dark:text-gray-300 dark:ring-white/10 dark:hover:bg-white/5' }}">
                            <span class="h-1.5 w-1.5 rounded-full {{ $dot[$meta['tone']] }}"></span>{{ $meta['label'] }}
                        </button>
                    @endforeach
                    <button type="button" wire:click="toggleMine" aria-pressed="{{ $mine ? 'true' : 'false' }}"
                            class="rounded-full px-2.5 py-1 text-xs font-medium ring-1 ring-inset transition-colors {{ $mine ? 'bg-gray-900 text-white ring-gray-900 dark:bg-white dark:text-gray-900 dark:ring-white' : 'bg-white text-gray-600 ring-gray-950/10 hover:bg-gray-50 dark:bg-gray-900 dark:text-gray-300 dark:ring-white/10 dark:hover:bg-white/5' }}">
                        Acara saya
                    </button>
                </div>

                <div class="inline-flex rounded-lg bg-gray-100 p-0.5 text-xs font-medium dark:bg-gray-800" role="tablist" aria-label="Tampilan">
                    @foreach (['month' => 'Bulan', 'week' => 'Minggu'] as $key => $label)
                        <button type="button" wire:click="setMode('{{ $key }}')" role="tab" aria-selected="{{ $mode === $key ? 'true' : 'false' }}"
                                class="rounded-md px-3 py-1 transition-colors {{ $mode === $key ? 'bg-white text-gray-900 shadow-sm dark:bg-gray-700 dark:text-white' : 'text-gray-500 hover:text-gray-900 dark:text-gray-400 dark:hover:text-gray-200' }}">{{ $label }}</button>
                    @endforeach
                </div>

                <x-filament::button size="sm" icon="heroicon-m-plus" wire:click="openCreate">Acara baru</x-filament::button>
            </div>
        </div>

        {{-- ================= GRID (tablet and up) ================= --}}
        <section class="hidden overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 md:block dark:bg-gray-900 dark:ring-white/10"
                 aria-label="Kalender {{ $rangeLabel }}" wire:loading.class="opacity-60" wire:target="previous,next,today,setMode,toggleKind,toggleMine,move">
            <div class="grid grid-cols-7 border-b border-gray-100 text-[11px] font-semibold uppercase tracking-wide text-gray-500 dark:border-white/5 dark:text-gray-400">
                @foreach (['Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab', 'Min'] as $name)
                    <div class="px-2 py-2 {{ $loop->index >= 5 ? 'text-gray-400' : '' }}">{{ $name }}</div>
                @endforeach
            </div>

            <div class="grid grid-cols-7" role="list">
                @foreach ($days as $cell)
                    @php
                        $list = $cell['events'];
                        $visible = $mode === 'week' ? $list : $list->take($shown);
                        $rest = $list->count() - $visible->count();
                        $dayOff = $cell['weekend'];
                    @endphp
                    <div role="listitem"
                         @dragover.prevent="over = '{{ $cell['date'] }}'" @dragleave="over === '{{ $cell['date'] }}' && (over = null)"
                         @drop.prevent="drop('{{ $cell['date'] }}')"
                         :class="over === '{{ $cell['date'] }}' ? 'bg-primary-50/70 ring-2 ring-inset ring-primary-400 dark:bg-primary-500/10' : ''"
                         class="group/day flex {{ $mode === 'week' ? 'min-h-[22rem]' : 'min-h-[7.5rem]' }} min-w-0 flex-col gap-1 border-b border-r border-gray-100 p-1.5 transition-colors [&:nth-child(7n)]:border-r-0 dark:border-white/5
                                {{ ! $cell['inMonth'] ? 'bg-gray-50/60 dark:bg-white/[.02]' : ($dayOff ? 'bg-danger-50/40 dark:bg-danger-500/[.06]' : '') }}">
                        <div class="flex items-center justify-between">
                            <span class="grid h-6 w-6 place-items-center rounded-full text-xs font-semibold tabular-nums
                                {{ $cell['isToday'] ? 'bg-primary-600 text-white' : (! $cell['inMonth'] ? 'text-gray-300 dark:text-gray-600' : ($cell['isPast'] ? 'text-gray-400' : 'text-gray-700 dark:text-gray-200')) }}">
                                {{ $cell['day'] }}@if ($cell['isToday'])<span class="sr-only"> (hari ini)</span>@endif
                            </span>
                            {{-- quiet until the cell is pointed at; always reachable by keyboard --}}
                            <button type="button" wire:click="openCreate('{{ $cell['date'] }}')"
                                    class="grid h-6 w-6 place-items-center rounded-md text-gray-400 opacity-0 transition-opacity hover:bg-primary-50 hover:text-primary-700 focus-visible:opacity-100 group-hover/day:opacity-100 dark:hover:bg-primary-500/15 dark:hover:text-primary-300"
                                    aria-label="Tambah acara pada {{ $cell['label'] }}">
                                <x-filament::icon icon="heroicon-m-plus" class="h-3.5 w-3.5" />
                            </button>
                        </div>

                        {{-- tax deadlines: a fact of the calendar, never an alarm --}}
                        @foreach ($cell['keyDates'] as $kd)
                            <p class="truncate rounded px-1.5 py-0.5 text-[11px] font-semibold leading-4 {{ $kd['kind'] === 'payment' ? 'bg-gray-100 text-gray-600 dark:bg-white/10 dark:text-gray-300' : 'bg-info-50 text-info-700 dark:bg-info-500/15 dark:text-info-300' }}" title="{{ $kd['title'] }}">
                                Tenggat {{ $kd['label'] }}
                            </p>
                        @endforeach

                        @foreach ($visible as $ev)
                            <button type="button" wire:click="open({{ $ev->id }})" wire:key="chip-{{ $cell['date'] }}-{{ $ev->id }}"
                                    draggable="true" @dragstart="start($event, {{ $ev->id }})" @dragend="end()"
                                    :class="dragging === {{ $ev->id }} ? 'opacity-40' : ''"
                                    class="flex w-full items-center gap-1.5 truncate rounded-md px-1.5 py-1 text-left text-[11px] font-medium leading-4 ring-1 ring-inset transition-colors {{ $chip[$ev->tone()] }} {{ $ev->status === \App\Models\CalendarEvent::STATUS_DONE ? 'line-through opacity-60' : ($cell['isPast'] && $ev->isPast() ? 'opacity-70' : '') }}"
                                    title="{{ $ev->title }} · {{ $ev->timeLabel() }}{{ $ev->client ? ' · ' . $ev->client->name : '' }}">
                                @unless ($ev->all_day)
                                    <span class="shrink-0 tabular-nums opacity-80">{{ $ev->starts_at->format('H.i') }}</span>
                                @endunless
                                <span class="truncate">{{ $ev->title }}</span>
                            </button>
                        @endforeach

                        @if ($rest > 0)
                            <button type="button" wire:click="showDay('{{ $cell['date'] }}')" class="rounded px-1.5 py-0.5 text-left text-[11px] font-semibold text-gray-500 hover:bg-gray-100 hover:text-primary-700 dark:hover:bg-white/5">+{{ $rest }} lagi</button>
                        @endif
                    </div>
                @endforeach
            </div>
        </section>

        {{-- ================= AGENDA (phone) ================= --}}
        <section class="space-y-3 md:hidden" aria-label="Agenda">
            @php $withEvents = collect($days)->filter(fn ($c) => $c['events']->isNotEmpty() || $c['keyDates'] !== []); @endphp
            @forelse ($withEvents as $cell)
                <div class="rounded-xl bg-white p-3 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                    <div class="flex items-center justify-between">
                        <span class="text-sm font-semibold {{ $cell['isToday'] ? 'text-primary-700 dark:text-primary-300' : 'text-gray-900 dark:text-gray-100' }}">{{ $cell['label'] }}</span>
                        <button type="button" wire:click="openCreate('{{ $cell['date'] }}')" class="text-xs font-medium text-primary-600">+ Tambah</button>
                    </div>
                    <div class="mt-2 space-y-1.5">
                        @foreach ($cell['keyDates'] as $kd)
                            <p class="rounded px-2 py-1 text-xs font-semibold bg-info-50 text-info-700 dark:bg-info-500/15 dark:text-info-300">Tenggat {{ $kd['label'] }}</p>
                        @endforeach
                        @foreach ($cell['events'] as $ev)
                            <button type="button" wire:click="open({{ $ev->id }})" class="flex w-full items-center gap-2 rounded-md px-2 py-1.5 text-left text-xs ring-1 ring-inset {{ $chip[$ev->tone()] }}">
                                <span class="w-12 shrink-0 tabular-nums opacity-80">{{ $ev->all_day ? 'Seharian' : $ev->starts_at->format('H.i') }}</span>
                                <span class="truncate font-medium">{{ $ev->title }}</span>
                            </button>
                        @endforeach
                    </div>
                </div>
            @empty
                <div class="rounded-xl bg-white p-6 text-center text-sm text-gray-500 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">Tidak ada acara pada rentang ini.</div>
            @endforelse
        </section>

        {{-- ================= UPCOMING + LEGEND ================= --}}
        <div class="grid gap-4 lg:grid-cols-3">
            <div class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-950/5 lg:col-span-2 dark:bg-gray-900 dark:ring-white/10">
                <h3 class="text-sm font-semibold text-gray-900 dark:text-gray-100">Agenda mendatang</h3>
                @if ($upcoming->isEmpty())
                    <p class="mt-2 text-sm text-gray-500">Belum ada acara terjadwal. Klik tanggal di kalender atau tombol "Acara baru".</p>
                @else
                    <ol class="mt-2 divide-y divide-gray-100 dark:divide-white/5">
                        @foreach ($upcoming as $ev)
                            <li>
                                <button type="button" wire:click="open({{ $ev->id }})" class="flex w-full items-center gap-3 py-2 text-left hover:bg-gray-50 dark:hover:bg-white/5">
                                    <span class="w-24 shrink-0 text-xs text-gray-500">{{ $ev->starts_at->locale('id')->translatedFormat('D, d M') }}<span class="block tabular-nums">{{ $ev->timeLabel() }}</span></span>
                                    <span class="h-2 w-2 shrink-0 rounded-full {{ $dot[$ev->tone()] }}"></span>
                                    <span class="min-w-0 flex-1">
                                        <span class="block truncate text-sm font-medium text-gray-900 dark:text-gray-100">{{ $ev->title }}</span>
                                        <span class="block truncate text-xs text-gray-500">{{ $ev->kindLabel() }}{{ $ev->client ? ' · ' . $ev->client->name : '' }}{{ $ev->location ? ' · ' . $ev->location : '' }}</span>
                                    </span>
                                    <span class="text-xs text-gray-400">{{ $ev->starts_at->locale('id')->diffForHumans(['parts' => 1, 'short' => true]) }}</span>
                                </button>
                            </li>
                        @endforeach
                    </ol>
                @endif
            </div>
            <div class="rounded-xl bg-white p-4 text-xs text-gray-500 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:text-gray-400 dark:ring-white/10">
                <h3 class="text-sm font-semibold text-gray-900 dark:text-gray-100">Cara pakai</h3>
                <ul class="mt-2 space-y-1.5">
                    <li>Klik tanggal untuk menambah acara di hari itu.</li>
                    <li>Seret chip acara ke hari lain untuk menjadwal ulang.</li>
                    <li>Peserta menerima undangan dan pengingat lewat lonceng notifikasi.</li>
                    <li>Label <span class="rounded bg-info-50 px-1 font-semibold text-info-700 dark:bg-info-500/15 dark:text-info-300">Tenggat</span> adalah batas lapor pajak dari modul Laporan Pajak.</li>
                </ul>
            </div>
        </div>
    </div>

    {{-- ================= FORM MODAL ================= --}}
    <x-filament::modal id="acara-form" width="2xl" :close-by-clicking-away="false">
        <x-slot name="heading">{{ $editing ? 'Ubah acara' : 'Acara baru' }}</x-slot>
        <x-slot name="description">Isi detailnya; peserta langsung mendapat notifikasi undangan.</x-slot>
        <form wire:submit="save" class="space-y-4">
            {{ $this->form }}
            <div class="flex justify-end gap-2 pt-2">
                <x-filament::button color="gray" type="button" x-on:click="$dispatch('close-modal', { id: 'acara-form' })">Batal</x-filament::button>
                <x-filament::button type="submit" wire:loading.attr="disabled" wire:target="save">{{ $editing ? 'Simpan perubahan' : 'Simpan acara' }}</x-filament::button>
            </div>
        </form>
    </x-filament::modal>

    {{-- ================= DETAIL PANEL ================= --}}
    <x-filament::modal id="acara-detail" slide-over width="md" x-on:close-modal.window="$event.detail.id === 'acara-detail' && $wire.closeDetail()">
        @if ($selected)
            <x-slot name="heading">
                <span class="inline-flex items-center gap-2">
                    <span class="rounded-full px-2 py-0.5 text-[11px] font-semibold {{ $badge[$selected->tone()] }}">{{ $selected->kindLabel() }}</span>
                    @if ($selected->status !== \App\Models\CalendarEvent::STATUS_SCHEDULED)
                        <span class="rounded-full bg-gray-100 px-2 py-0.5 text-[11px] font-semibold text-gray-600 dark:bg-white/10 dark:text-gray-300">{{ $selected->statusLabel() }}</span>
                    @endif
                </span>
            </x-slot>

            <div class="space-y-4">
                <div>
                    <h3 class="text-lg font-semibold leading-snug text-gray-950 dark:text-white {{ $selected->status === \App\Models\CalendarEvent::STATUS_DONE ? 'line-through' : '' }}">{{ $selected->title }}</h3>
                    <p class="mt-1 text-sm text-gray-600 dark:text-gray-300">{{ $selected->dateLabel() }} &middot; {{ $selected->timeLabel() }}</p>
                </div>

                <dl class="divide-y divide-gray-100 rounded-lg bg-gray-50 text-sm ring-1 ring-gray-950/5 dark:divide-white/5 dark:bg-white/5 dark:ring-white/10">
                    @if ($selected->location)
                        <div class="flex gap-3 px-3 py-2"><dt class="w-24 shrink-0 text-gray-500">Lokasi</dt><dd class="min-w-0 break-words text-gray-900 dark:text-white">
                            @if (\Illuminate\Support\Str::startsWith($selected->location, ['http://', 'https://']))
                                <a href="{{ $selected->location }}" target="_blank" rel="noopener" class="text-primary-600 underline dark:text-primary-400">{{ $selected->location }}</a>
                            @else {{ $selected->location }} @endif
                        </dd></div>
                    @endif
                    @if ($selected->client)
                        <div class="flex gap-3 px-3 py-2"><dt class="w-24 shrink-0 text-gray-500">Klien</dt><dd class="min-w-0 truncate"><a href="{{ \App\Filament\Resources\ClientResource::getUrl('view', ['record' => $selected->client_id]) }}" class="text-primary-600 hover:underline dark:text-primary-400">{{ $selected->client->name }}</a></dd></div>
                    @endif
                    @if ($selected->project)
                        <div class="flex gap-3 px-3 py-2"><dt class="w-24 shrink-0 text-gray-500">Proyek</dt><dd class="min-w-0 truncate"><a href="{{ \App\Filament\Resources\ProjectResource::getUrl('view', ['record' => $selected->project_id]) }}" class="text-primary-600 hover:underline dark:text-primary-400">{{ $selected->project->name }}</a></dd></div>
                    @endif
                    <div class="flex gap-3 px-3 py-2"><dt class="w-24 shrink-0 text-gray-500">Peserta</dt><dd class="flex flex-wrap gap-1">
                        @foreach ($selected->participants as $u)
                            <span class="rounded-full bg-white px-2 py-0.5 text-xs text-gray-700 ring-1 ring-gray-950/10 dark:bg-gray-800 dark:text-gray-200 dark:ring-white/10">{{ $u->name }}</span>
                        @endforeach
                    </dd></div>
                    <div class="flex gap-3 px-3 py-2"><dt class="w-24 shrink-0 text-gray-500">Pengingat</dt><dd class="text-gray-900 dark:text-white">
                        @forelse ($selected->reminders as $rm)
                            <span class="block text-xs">{{ $rm->label() }} <span class="text-gray-400">({{ $rm->remind_at->translatedFormat('d M H:i') }}{{ $rm->sent_at ? ', terkirim' : '' }})</span></span>
                        @empty <span class="text-xs text-gray-400">tidak ada</span> @endforelse
                    </dd></div>
                    <div class="flex gap-3 px-3 py-2"><dt class="w-24 shrink-0 text-gray-500">Dibuat</dt><dd class="text-xs text-gray-600 dark:text-gray-300">{{ $selected->creator?->name }} &middot; {{ $selected->created_at->translatedFormat('d M Y') }}</dd></div>
                </dl>

                @if ($selected->description)
                    <div class="whitespace-pre-line text-sm text-gray-700 dark:text-gray-200">{{ $selected->description }}</div>
                @endif
            </div>

            <x-slot name="footerActions">
                @if ($selected->isScheduled() && ($canManage || $isParticipant))
                    <x-filament::button size="sm" color="success" icon="heroicon-m-check" wire:click="setStatus({{ $selected->id }}, 'done')">Selesai</x-filament::button>
                @endif
                @if ($canManage)
                    @if ($selected->isScheduled())
                        <x-filament::button size="sm" color="gray" wire:click="setStatus({{ $selected->id }}, 'canceled')">Batalkan</x-filament::button>
                    @else
                        <x-filament::button size="sm" color="gray" wire:click="setStatus({{ $selected->id }}, 'scheduled')">Jadwalkan lagi</x-filament::button>
                    @endif
                    <x-filament::button size="sm" color="gray" icon="heroicon-m-pencil" wire:click="openEdit({{ $selected->id }})">Ubah</x-filament::button>
                    <x-filament::button size="sm" color="danger" outlined icon="heroicon-m-trash" x-on:click="$dispatch('open-modal', { id: 'hapus-acara' })">Hapus</x-filament::button>
                @endif
            </x-slot>
        @else
            <p class="text-sm text-gray-500">Acara tidak ditemukan.</p>
        @endif
    </x-filament::modal>

    {{-- ================= DELETE CONFIRM ================= --}}
    <x-filament::modal id="hapus-acara" width="sm" icon="heroicon-o-trash" icon-color="danger">
        <x-slot name="heading">Hapus acara</x-slot>
        <x-slot name="description">{{ $selected?->title }} &middot; {{ $selected?->dateLabel() }}</x-slot>
        <p class="text-sm text-gray-600 dark:text-gray-300">Acara dan pengingatnya dihapus permanen. Jejaknya tetap tercatat di log aktivitas.</p>
        <x-slot name="footerActions">
            <x-filament::button color="gray" x-on:click="$dispatch('close-modal', { id: 'hapus-acara' })">Kembali</x-filament::button>
            <x-filament::button color="danger" wire:click="delete" wire:loading.attr="disabled" wire:target="delete">Hapus acara</x-filament::button>
        </x-slot>
    </x-filament::modal>
</x-filament-panels::page>
