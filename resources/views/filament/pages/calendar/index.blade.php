<x-filament-panels::page>
    @php
        $E = \App\Models\CalendarEvent::class;
        /* Kind tones, spelled out so Tailwind sees every class.
           filled  = settled (done)      outlined = owed (scheduled, dashed card)
           dot     = the kind's mark     soft/ink = badges in the panel */
        $tone = [
            'cyan'   => ['filled' => 'border-transparent bg-primary-100 text-primary-900 dark:bg-primary-500/25 dark:text-primary-100', 'dot' => 'bg-primary-500', 'soft' => 'bg-primary-50 text-primary-700 dark:bg-primary-500/15 dark:text-primary-300'],
            'amber'  => ['filled' => 'border-transparent bg-amber-100 text-amber-900 dark:bg-amber-500/25 dark:text-amber-100',        'dot' => 'bg-amber-500',   'soft' => 'bg-amber-50 text-amber-700 dark:bg-amber-500/15 dark:text-amber-300'],
            'violet' => ['filled' => 'border-transparent bg-violet-100 text-violet-900 dark:bg-violet-500/25 dark:text-violet-100',     'dot' => 'bg-violet-500',  'soft' => 'bg-violet-50 text-violet-700 dark:bg-violet-500/15 dark:text-violet-300'],
            'slate'  => ['filled' => 'border-transparent bg-slate-200 text-slate-800 dark:bg-white/15 dark:text-gray-100',              'dot' => 'bg-slate-500',   'soft' => 'bg-slate-100 text-slate-700 dark:bg-white/10 dark:text-gray-200'],
        ];
        $shown = 3;
        $chipClass = function ($ev, $tone) {
            $t = $tone[$ev->tone()];
            $late = $ev->isScheduled() && $ev->isPast();
            if ($late) return 'border-danger-200 bg-danger-50 text-danger-700 hover:border-danger-400 dark:border-danger-500/40 dark:bg-danger-500/10 dark:text-danger-300';
            if ($ev->status === \App\Models\CalendarEvent::STATUS_DONE) return $t['filled'] . ' opacity-70 line-through decoration-1 hover:opacity-100';
            return $t['filled'] . ' hover:shadow-sm';
        };
    @endphp

    <div x-data="{
            dragging: null, over: null,
            start(e, id) { this.dragging = id; e.dataTransfer.effectAllowed = 'move'; e.dataTransfer.setData('text/plain', String(id)); },
            end() { this.dragging = null; this.over = null; },
            drop(date) { if (this.dragging) { $wire.move(this.dragging, date); } this.end(); },
         }"
         class="space-y-4">

        {{-- ================= FILTER ROW ================= --}}
        @php
            $segments = ['all' => 'Semua', 'scheduled' => 'Terjadwal', 'done' => 'Selesai', 'late' => 'Terlewat'];
            $ddBtn = 'inline-flex h-10 items-center gap-2 rounded-lg bg-white px-3 text-[0.8438rem] font-semibold text-gray-700 shadow-sm ring-1 ring-gray-950/10 transition-colors hover:bg-gray-50 dark:bg-gray-900 dark:text-gray-200 dark:ring-white/10 dark:hover:bg-white/5';
            $ddMenu = 'absolute left-0 z-30 mt-1 min-w-[12rem] overflow-hidden rounded-lg bg-white p-1 text-[0.8438rem] shadow-lg ring-1 ring-gray-950/10 dark:bg-gray-900 dark:ring-white/10';
            $ddItem = 'flex w-full items-center gap-2 rounded-md px-2.5 py-1.5 text-left transition-colors hover:bg-gray-100 dark:hover:bg-white/5';
        @endphp
        <div class="flex flex-wrap items-center gap-2.5">
            {{-- search --}}
            <label class="relative block w-full sm:w-52">
                <x-filament::icon icon="heroicon-m-magnifying-glass" class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400" />
                <input type="search" wire:model.live.debounce.300ms="q" placeholder="Cari acara…"
                       class="h-10 w-full rounded-lg border-0 bg-white pl-9 pr-3 text-[0.8438rem] text-gray-900 shadow-sm ring-1 ring-gray-950/10 placeholder:text-gray-400 focus:ring-2 focus:ring-primary-500 dark:bg-gray-900 dark:text-gray-100 dark:ring-white/10">
            </label>

            {{-- status segments double as the month's read: how much is still owed --}}
            <div class="flex max-w-full overflow-x-auto rounded-lg bg-white p-1 shadow-sm ring-1 ring-gray-950/10 dark:bg-gray-900 dark:ring-white/10" role="tablist" aria-label="Status">
                @foreach ($segments as $key => $label)
                    @php $on = $status === $key; @endphp
                    <button type="button" wire:click="setStatusFilter('{{ $key }}')" role="tab" aria-selected="{{ $on ? 'true' : 'false' }}"
                            class="inline-flex shrink-0 items-center gap-1.5 rounded-md px-3 py-1.5 text-[0.8438rem] font-semibold transition-colors {{ $on ? 'bg-primary-600 text-white' : 'text-gray-600 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-white/5' }}">
                        {{ $label }}
                        <span class="rounded-full px-1.5 py-px text-[11px] font-bold tabular-nums {{ $on ? 'bg-white/20 text-white' : ($key === 'late' && $counts[$key] > 0 ? 'bg-danger-50 text-danger-700 dark:bg-danger-500/15 dark:text-danger-300' : 'bg-gray-100 text-gray-500 dark:bg-white/10 dark:text-gray-400') }}">{{ $counts[$key] }}</span>
                    </button>
                @endforeach
            </div>

            {{-- kind dropdown --}}
            <div x-data="{ open: false }" class="relative">
                <button type="button" @click="open = !open" :aria-expanded="open" class="{{ $ddBtn }}">
                    @if ($kind !== '')<span class="h-2 w-2 rounded-full {{ $tone[$kindsMeta[$kind]['tone']]['dot'] }}"></span>@endif
                    {{ $kind !== '' ? $kindsMeta[$kind]['label'] : 'Semua jenis' }}
                    <x-filament::icon icon="heroicon-m-chevron-down" class="h-4 w-4 text-gray-400" />
                </button>
                <div x-show="open" x-cloak @click.outside="open = false" x-transition.origin.top.left class="{{ $ddMenu }}">
                    <button type="button" @click="open = false; $wire.setKindFilter('')" class="{{ $ddItem }} {{ $kind === '' ? 'font-semibold text-primary-700 dark:text-primary-300' : '' }}">Semua jenis</button>
                    @foreach ($kindsMeta as $key => $meta)
                        <button type="button" @click="open = false; $wire.setKindFilter('{{ $key }}')" class="{{ $ddItem }} {{ $kind === $key ? 'font-semibold text-primary-700 dark:text-primary-300' : '' }}">
                            <span class="h-2 w-2 rounded-full {{ $tone[$meta['tone']]['dot'] }}"></span>{{ $meta['label'] }}
                        </button>
                    @endforeach
                </div>
            </div>

            {{-- participant dropdown --}}
            <div x-data="{ open: false }" class="relative">
                <button type="button" @click="open = !open" :aria-expanded="open" class="{{ $ddBtn }}">
                    {{ $participant ? ($participants[$participant] ?? 'Peserta') : 'Semua peserta' }}
                    <x-filament::icon icon="heroicon-m-chevron-down" class="h-4 w-4 text-gray-400" />
                </button>
                <div x-show="open" x-cloak @click.outside="open = false" x-transition.origin.top.left class="{{ $ddMenu }} max-h-72 overflow-y-auto">
                    <button type="button" @click="open = false; $wire.setParticipant(null)" class="{{ $ddItem }} {{ ! $participant ? 'font-semibold text-primary-700 dark:text-primary-300' : '' }}">Semua peserta</button>
                    @foreach ($participants as $id => $name)
                        <button type="button" @click="open = false; $wire.setParticipant({{ $id }})" class="{{ $ddItem }} {{ $participant === $id ? 'font-semibold text-primary-700 dark:text-primary-300' : '' }}">{{ $name }}</button>
                    @endforeach
                </div>
            </div>

            <button type="button" wire:click="toggleMine" aria-pressed="{{ $mine ? 'true' : 'false' }}"
                    class="h-10 rounded-lg px-3 text-[0.8438rem] font-semibold shadow-sm ring-1 transition-colors {{ $mine ? 'bg-gray-900 text-white ring-gray-900 dark:bg-white dark:text-gray-900 dark:ring-white' : 'bg-white text-gray-700 ring-gray-950/10 hover:bg-gray-50 dark:bg-gray-900 dark:text-gray-200 dark:ring-white/10 dark:hover:bg-white/5' }}">
                Acara saya
            </button>
            @if ($hasFilters)
                <button type="button" wire:click="clearFilters" class="text-[0.8438rem] font-semibold text-primary-700 underline decoration-transparent underline-offset-4 transition-colors hover:decoration-current dark:text-primary-300">Bersihkan</button>
            @endif
        </div>

        {{-- one line that explains the colours, so the grid needs no legend box --}}
        <p class="text-xs leading-relaxed text-gray-500 dark:text-gray-400">
            <span class="font-semibold text-danger-600 dark:text-danger-400">Merah</span> = lewat waktunya dan belum ditandai selesai.
            Chip berwarna sesuai jenisnya; yang dicoret sudah selesai. Label <span class="rounded bg-info-50 px-1 font-semibold text-info-700 dark:bg-info-500/15 dark:text-info-300">Tenggat</span> adalah batas lapor pajak.
            Klik tanggal untuk menambah acara di hari itu, seret chip untuk memindahkannya.
        </p>

        {{-- ================= GRID (tablet and up) ================= --}}
        <section class="hidden overflow-hidden rounded-xl border border-gray-200 bg-white md:block dark:border-white/10 dark:bg-gray-900"
                 aria-label="Kalender {{ $monthLabel }}" wire:loading.class="opacity-60" wire:target="previous,next,today,setMode,setStatusFilter,setKindFilter,setParticipant,toggleMine,clearFilters,q,move">
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
                    @endphp
                    <div role="listitem"
                         @dragover.prevent="over = '{{ $cell['date'] }}'" @dragleave="over === '{{ $cell['date'] }}' && (over = null)"
                         @drop.prevent="drop('{{ $cell['date'] }}')"
                         :class="over === '{{ $cell['date'] }}' ? 'bg-primary-50/70 ring-2 ring-inset ring-primary-400 dark:bg-primary-500/10' : ''"
                         class="group/day flex {{ $mode === 'week' ? 'min-h-[22rem]' : 'min-h-[7.75rem]' }} min-w-0 flex-col gap-1.5 border-b border-r border-gray-100 p-2 transition-colors [&:nth-child(7n)]:border-r-0 dark:border-white/5
                                {{ ! $cell['inMonth'] ? 'bg-gray-50/60 dark:bg-white/[.02]' : ($cell['weekend'] ? 'bg-danger-50/30 dark:bg-danger-500/[.05]' : '') }}">
                        <div class="flex items-center justify-between">
                            <span class="grid h-6 w-6 place-items-center rounded-full text-xs font-bold tabular-nums
                                {{ $cell['isToday'] ? 'bg-primary-600 text-white' : (! $cell['inMonth'] ? 'text-gray-300 dark:text-gray-600' : ($cell['isPast'] ? 'text-gray-400' : 'text-gray-700 dark:text-gray-200')) }}">
                                {{ $cell['day'] }}@if ($cell['isToday'])<span class="sr-only"> (hari ini)</span>@endif
                            </span>
                            <button type="button" wire:click="openCreate('{{ $cell['date'] }}')"
                                    class="grid h-6 w-6 place-items-center rounded-md text-gray-400 opacity-0 transition-opacity hover:bg-primary-50 hover:text-primary-700 focus-visible:opacity-100 group-hover/day:opacity-100 dark:hover:bg-primary-500/15 dark:hover:text-primary-300"
                                    aria-label="Tambah acara pada {{ $cell['label'] }}">
                                <x-filament::icon icon="heroicon-m-plus" class="h-3.5 w-3.5" />
                            </button>
                        </div>

                        @foreach ($cell['keyDates'] as $kd)
                            <p class="truncate rounded px-1.5 py-1 text-[11px] font-semibold leading-none {{ $kd['kind'] === 'payment' ? 'bg-gray-100 text-gray-600 dark:bg-white/10 dark:text-gray-300' : 'bg-info-50 text-info-700 dark:bg-info-500/15 dark:text-info-300' }}" title="{{ $kd['title'] }}">
                                Tenggat {{ $kd['label'] }}
                            </p>
                        @endforeach

                        @foreach ($visible as $ev)
                            @php $late = $ev->isScheduled() && $ev->isPast(); @endphp
                            <button type="button" wire:click="open({{ $ev->id }})" wire:key="chip-{{ $cell['date'] }}-{{ $ev->id }}"
                                    draggable="true" @dragstart="start($event, {{ $ev->id }})" @dragend="end()"
                                    :class="dragging === {{ $ev->id }} ? 'opacity-40' : ''"
                                    class="flex min-w-0 items-center gap-1.5 rounded-md border px-2 py-1.5 text-left text-[11px] font-semibold leading-none transition-[background-color,border-color,box-shadow] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50 {{ $chipClass($ev, $tone) }}"
                                    title="{{ $ev->title }} · {{ $ev->kindLabel() }} · {{ $ev->timeLabel() }}{{ $ev->client ? ' · ' . $ev->client->name : '' }}{{ $late ? ' · terlewat' : '' }}">
                                @unless ($ev->all_day)
                                    <span class="shrink-0 tabular-nums opacity-70">{{ $ev->starts_at->format('H.i') }}</span>
                                @endunless
                                <span class="truncate">{{ $ev->title }}</span>
                            </button>
                        @endforeach

                        @if ($rest > 0)
                            <button type="button" wire:click="showDay('{{ $cell['date'] }}')" class="rounded px-1.5 py-1 text-left text-[11px] font-bold text-gray-500 transition-colors hover:bg-gray-100 hover:text-primary-700 dark:hover:bg-white/5">+{{ $rest }} lagi</button>
                        @endif
                    </div>
                @endforeach
            </div>

            @if ($eventCount === 0)
                <div class="flex flex-col items-center gap-3 border-t border-gray-100 px-6 py-10 text-center dark:border-white/5">
                    <span class="grid h-11 w-11 place-items-center rounded-lg bg-primary-50 text-primary-700 dark:bg-primary-500/15 dark:text-primary-300">
                        <x-filament::icon :icon="$hasFilters ? 'heroicon-o-magnifying-glass' : 'heroicon-o-calendar-days'" class="h-5 w-5" />
                    </span>
                    <p class="text-sm font-bold text-gray-900 dark:text-gray-100">{{ $hasFilters ? 'Tidak ada acara yang cocok' : 'Belum ada acara di ' . $monthLabel }}</p>
                    <p class="max-w-[38ch] text-xs leading-relaxed text-gray-500 dark:text-gray-400">
                        {{ $hasFilters ? 'Longgarkan salah satu filter, atau bersihkan semuanya untuk melihat seluruh bulan.' : 'Jadwalkan janji temu, permintaan data, atau pengingat. Acara yang tersimpan langsung muncul pada tanggalnya.' }}
                    </p>
                    @if ($hasFilters)
                        <button type="button" wire:click="clearFilters" class="mt-1 rounded-md bg-primary-50 px-3 py-2 text-[0.8438rem] font-bold text-primary-700 transition-colors hover:bg-primary-100 dark:bg-primary-500/15 dark:text-primary-300">Bersihkan filter</button>
                    @else
                        <x-filament::button size="sm" icon="heroicon-m-plus" wire:click="openCreate" class="mt-1">Tambah Acara</x-filament::button>
                    @endif
                </div>
            @endif
        </section>

        {{-- ================= AGENDA (phone) ================= --}}
        <section class="space-y-3 md:hidden" aria-label="Agenda">
            @php $withEvents = collect($days)->filter(fn ($c) => $c['events']->isNotEmpty() || $c['keyDates'] !== []); @endphp
            @forelse ($withEvents as $cell)
                <div class="rounded-xl bg-white p-3 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                    <div class="flex items-center justify-between">
                        <span class="text-sm font-bold {{ $cell['isToday'] ? 'text-primary-700 dark:text-primary-300' : 'text-gray-900 dark:text-gray-100' }}">{{ $cell['label'] }}</span>
                        <button type="button" wire:click="openCreate('{{ $cell['date'] }}')" class="text-xs font-semibold text-primary-700 dark:text-primary-300">+ Tambah</button>
                    </div>
                    <div class="mt-2 space-y-1.5">
                        @foreach ($cell['keyDates'] as $kd)
                            <p class="rounded px-2 py-1 text-xs font-semibold bg-info-50 text-info-700 dark:bg-info-500/15 dark:text-info-300">Tenggat {{ $kd['label'] }}</p>
                        @endforeach
                        @foreach ($cell['events'] as $ev)
                            <button type="button" wire:click="open({{ $ev->id }})" class="flex w-full items-center gap-2 rounded-md border px-2 py-2 text-left text-xs font-bold {{ $chipClass($ev, $tone) }}">
                                <span class="w-14 shrink-0 tabular-nums opacity-70">{{ $ev->all_day ? 'Seharian' : $ev->starts_at->format('H.i') }}</span>
                                <span class="truncate">{{ $ev->title }}</span>
                            </button>
                        @endforeach
                    </div>
                </div>
            @empty
                <div class="rounded-xl bg-white p-6 text-center text-sm text-gray-500 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">Tidak ada acara pada rentang ini.</div>
            @endforelse
        </section>

    </div>

    {{-- ================= NEW EVENT DIALOG (over the grid; the month stays visible) ================= --}}
    <div x-data="{ open: @entangle('dialogOpen').live }" x-show="open" x-cloak
         class="fixed inset-0 z-40 flex items-end justify-center p-0 sm:items-center sm:p-4"
         role="dialog" aria-modal="true" aria-labelledby="acara-baru-judul"
         x-on:keydown.escape.window="open && (open = false)">
        <div x-show="open" x-transition.opacity.duration.200ms class="absolute inset-0 bg-gray-950/40 backdrop-blur-[1px]" x-on:click="open = false"></div>
        <div x-show="open"
             x-transition:enter="transition duration-200 ease-out" x-transition:enter-start="translate-y-4 opacity-0 sm:translate-y-0 sm:scale-[.98]" x-transition:enter-end="translate-y-0 opacity-100 sm:scale-100"
             x-transition:leave="transition duration-150 ease-in" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
             class="relative flex max-h-[calc(100dvh-1rem)] w-full flex-col overflow-hidden rounded-t-2xl bg-white shadow-2xl ring-1 ring-gray-950/10 sm:max-h-[calc(100dvh-2rem)] sm:max-w-2xl sm:rounded-xl dark:bg-gray-900 dark:ring-white/10">
            <div class="border-b border-gray-100 px-5 py-4 sm:px-6 dark:border-white/5">
                <h2 id="acara-baru-judul" class="text-base font-bold tracking-tight text-gray-950 dark:text-white">Tambah Acara</h2>
                <p class="mt-0.5 text-xs leading-relaxed text-gray-500 dark:text-gray-400">Judul, jenis, dan tanggal yang wajib. Peserta dan pengingat bisa menyusul.</p>
            </div>
            <form wire:submit="save" class="flex min-h-0 flex-1 flex-col">
                <div class="min-h-0 flex-1 overflow-y-auto px-5 py-4 sm:px-6">
                    {{ $this->form }}
                </div>
                <div class="flex items-center gap-3 border-t border-gray-100 px-5 py-3.5 sm:justify-between sm:px-6 dark:border-white/5">
                    {{-- consequence beside the decision: the chip the calendar will draw --}}
                    @php $draftKind = $data['kind'] ?? 'appointment'; $draftTone = $tone[$kindsMeta[$draftKind]['tone'] ?? 'cyan']; @endphp
                    <p class="hidden max-w-[16rem] min-w-0 items-center gap-1.5 rounded-md border border-dashed border-gray-300 px-1.5 py-1 text-[11px] font-bold text-gray-900 sm:flex dark:border-white/20 dark:text-gray-100 {{ blank($data['title'] ?? null) ? 'opacity-60' : '' }}" aria-hidden="true">
                        <span class="truncate">{{ $data['title'] ?: 'Judul acara' }}</span>
                        <span class="ml-auto h-2 w-2 shrink-0 rounded-full {{ $draftTone['dot'] }}"></span>
                    </p>
                    <div class="flex flex-1 gap-2 sm:flex-none">
                        <x-filament::button color="gray" type="button" class="flex-1 sm:flex-none" x-on:click="open = false">Batal</x-filament::button>
                        <x-filament::button type="submit" class="flex-1 sm:flex-none" wire:loading.attr="disabled" wire:target="save">Simpan acara</x-filament::button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    {{-- ================= RECORD PANEL (floats in from the right; edit happens in place) ================= --}}
    <div x-data="{ open: @entangle('panelOpen').live }" x-show="open" x-cloak class="fixed inset-0 z-40" role="dialog" aria-modal="true"
         x-on:keydown.escape.window="if (open) { $wire.panelMode === 'edit' ? $wire.cancelEdit() : $wire.closePanel() }">
        <div x-show="open" x-transition.opacity.duration.200ms class="absolute inset-0 bg-gray-950/30" x-on:click="$wire.panelMode === 'edit' ? $wire.cancelEdit() : $wire.closePanel()"></div>
        <div x-show="open"
             x-transition:enter="transition duration-300 ease-out" x-transition:enter-start="translate-x-8 opacity-0" x-transition:enter-end="translate-x-0 opacity-100"
             x-transition:leave="transition duration-200 ease-in" x-transition:leave-start="translate-x-0 opacity-100" x-transition:leave-end="translate-x-8 opacity-0"
             class="absolute inset-y-3 right-3 flex w-[calc(100%-1.5rem)] flex-col overflow-hidden rounded-xl bg-white shadow-2xl ring-1 ring-gray-950/10 sm:inset-y-4 sm:right-4 sm:w-[36rem] dark:bg-gray-900 dark:ring-white/10">
            @if ($selected)
                @if ($panelMode === 'edit')
                    {{-- the same card, turned into the form --}}
                    <div class="flex items-center gap-2 border-b border-gray-100 px-3 py-2.5 sm:px-4 dark:border-white/5">
                        <button type="button" wire:click="cancelEdit" class="grid h-9 w-9 place-items-center rounded-md text-gray-600 transition-colors hover:bg-gray-100 hover:text-gray-900 dark:text-gray-300 dark:hover:bg-white/5" aria-label="Kembali ke rincian">
                            <x-filament::icon icon="heroicon-m-arrow-left" class="h-4 w-4" />
                        </button>
                        <p class="text-[0.8438rem] font-bold text-gray-900 dark:text-gray-100">Ubah acara</p>
                    </div>
                    <form wire:submit="update" class="flex min-h-0 flex-1 flex-col">
                        <div class="min-h-0 flex-1 overflow-y-auto px-5 py-4 sm:px-6">
                            {{ $this->editForm }}
                        </div>
                        <div class="flex items-center justify-end gap-2 border-t border-gray-100 px-4 py-3 sm:px-5 dark:border-white/5">
                            <x-filament::button color="gray" type="button" wire:click="cancelEdit">Batal</x-filament::button>
                            <x-filament::button type="submit" wire:loading.attr="disabled" wire:target="update">Simpan perubahan</x-filament::button>
                        </div>
                    </form>
                @else
                    @include('filament.pages.calendar.partials.record', ['selected' => $selected, 'canManage' => $canManage, 'isParticipant' => $isParticipant, 'history' => $history, 'context' => 'calendar'])
                @endif
            @else
                <div class="flex flex-1 items-center justify-center p-8 text-sm text-gray-500">Acara tidak ditemukan.</div>
            @endif
        </div>
    </div>

    {{-- ================= DELETE DIALOG ================= --}}
    <div x-data="{ open: @entangle('deleteOpen').live }" x-show="open" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4" role="alertdialog" aria-modal="true" aria-labelledby="hapus-acara-judul"
         x-on:keydown.escape.window="open && (open = false)">
        <div x-show="open" x-transition.opacity.duration.150ms class="absolute inset-0 bg-gray-950/40" x-on:click="open = false"></div>
        <div x-show="open" x-transition.duration.150ms class="relative w-full max-w-md rounded-xl bg-white p-5 shadow-2xl ring-1 ring-gray-950/10 dark:bg-gray-900 dark:ring-white/10">
            <h2 id="hapus-acara-judul" class="text-base font-bold tracking-tight text-gray-950 dark:text-white">Hapus acara</h2>
            <p class="mt-1 text-xs leading-relaxed text-gray-500 dark:text-gray-400">
                {{ $selected?->title }} akan hilang dari kalender beserta pengingatnya.
                @if ($selected && $selected->participants->count() > 1) {{ $selected->participants->count() - 1 }} peserta lain tidak diberi tahu; sampaikan sendiri bila perlu. @endif
                Jejaknya tetap tercatat di log aktivitas.
            </p>
            <div class="mt-5 flex justify-end gap-2">
                <x-filament::button color="gray" type="button" x-on:click="open = false">Batal</x-filament::button>
                {{-- not red: red is reserved for lateness. Deleting is a decision, so it reads in ink. --}}
                <button type="button" wire:click="delete" wire:loading.attr="disabled" wire:target="delete"
                        class="inline-flex items-center gap-1.5 rounded-lg bg-gray-950 px-3 py-2 text-sm font-semibold text-white transition-colors hover:bg-gray-800 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-gray-950/40 disabled:opacity-60 dark:bg-white dark:text-gray-900 dark:hover:bg-gray-200">
                    <x-filament::icon icon="heroicon-m-trash" class="h-4 w-4" />Hapus acara
                </button>
            </div>
        </div>
    </div>
</x-filament-panels::page>
