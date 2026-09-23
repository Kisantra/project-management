{{-- Next events on the team calendar, grouped by day. A click floats the same record
     panel the calendar page uses in from the right. --}}
@php
    $dot = ['cyan' => 'bg-primary-500', 'amber' => 'bg-amber-500', 'violet' => 'bg-violet-500', 'slate' => 'bg-slate-500'];
    $today = today();
@endphp

<div class="flex h-full flex-col rounded-2xl border border-gray-200/80 bg-white shadow-sm dark:border-gray-800/80 dark:bg-gray-950">
    <div class="flex items-start justify-between gap-3 px-6 pt-6 sm:px-7">
        <div>
            <h2 class="text-base font-semibold tracking-tight text-gray-900 dark:text-gray-100">Agenda mendatang</h2>
            <p class="mt-0.5 text-xs text-gray-500">{{ $total }} acara terjadwal ke depan</p>
        </div>
        <div class="inline-flex rounded-lg bg-gray-100 p-0.5 text-[11px] font-medium dark:bg-gray-800" role="tablist" aria-label="Cakupan">
            <button type="button" wire:click="{{ $mine ? 'toggleMine' : '' }}" role="tab" aria-selected="{{ $mine ? 'false' : 'true' }}"
                    class="rounded-md px-2.5 py-1 transition-colors {{ ! $mine ? 'bg-white text-gray-900 shadow-sm dark:bg-gray-700 dark:text-white' : 'text-gray-500 hover:text-gray-900 dark:text-gray-400' }}">Tim</button>
            <button type="button" wire:click="{{ $mine ? '' : 'toggleMine' }}" role="tab" aria-selected="{{ $mine ? 'true' : 'false' }}"
                    class="rounded-md px-2.5 py-1 transition-colors {{ $mine ? 'bg-white text-gray-900 shadow-sm dark:bg-gray-700 dark:text-white' : 'text-gray-500 hover:text-gray-900 dark:text-gray-400' }}">Saya</button>
        </div>
    </div>

    <div class="mt-4 flex-1 px-3 pb-2 sm:px-5" wire:loading.class="opacity-60" wire:target="toggleMine">
        @if ($days->isEmpty())
            <div class="flex flex-col items-center gap-3 px-6 py-10 text-center">
                <span class="grid h-11 w-11 place-items-center rounded-lg bg-primary-50 text-primary-700 dark:bg-primary-500/15 dark:text-primary-300">
                    <x-filament::icon icon="heroicon-o-calendar-days" class="h-5 w-5" />
                </span>
                <p class="text-sm font-bold text-gray-900 dark:text-gray-100">Belum ada acara ke depan</p>
                <p class="max-w-[30ch] text-xs leading-relaxed text-gray-500 dark:text-gray-400">Janji temu dan pengingat yang dijadwalkan di kalender akan tampil di sini.</p>
                <a href="{{ $calendarUrl }}" class="mt-1 rounded-md bg-primary-50 px-3 py-2 text-[0.8438rem] font-bold text-primary-700 transition-colors hover:bg-primary-100 dark:bg-primary-500/15 dark:text-primary-300">Buka kalender</a>
            </div>
        @else
            @foreach ($days as $date => $events)
                @php $d = \Carbon\Carbon::parse($date); $isToday = $d->isSameDay($today); @endphp
                <div class="px-2 pt-3 first:pt-0">
                    <p class="flex items-baseline gap-2 text-[11px] font-bold uppercase tracking-wide {{ $isToday ? 'text-primary-700 dark:text-primary-300' : 'text-gray-500 dark:text-gray-400' }}">
                        {{ $isToday ? 'Hari ini' : ($d->isTomorrow() ? 'Besok' : $d->locale('id')->translatedFormat('l')) }}
                        <span class="font-medium normal-case tracking-normal text-gray-400">{{ $d->locale('id')->translatedFormat('d M') }}</span>
                    </p>
                </div>
                <ul class="divide-y divide-gray-100 dark:divide-white/5">
                    @foreach ($events as $ev)
                        <li>
                            <button type="button" wire:click="open({{ $ev->id }})" class="flex w-full items-center gap-3 rounded-md px-2 py-2.5 text-left transition-colors hover:bg-gray-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500 dark:hover:bg-white/5">
                                <span class="w-14 shrink-0 text-xs tabular-nums text-gray-500 dark:text-gray-400">{{ $ev->all_day ? 'Seharian' : $ev->starts_at->format('H.i') }}</span>
                                <span class="h-2 w-2 shrink-0 rounded-full {{ $dot[$ev->tone()] }}"></span>
                                <span class="min-w-0 flex-1">
                                    <span class="block truncate text-[0.8438rem] font-bold text-gray-900 dark:text-gray-100">{{ $ev->title }}</span>
                                    <span class="block truncate text-xs text-gray-500 dark:text-gray-400">{{ $ev->kindLabel() }}{{ $ev->client ? ' · ' . $ev->client->name : '' }}{{ $ev->location ? ' · ' . $ev->location : '' }}</span>
                                </span>
                                <x-filament::icon icon="heroicon-m-chevron-right" class="h-4 w-4 shrink-0 text-gray-400" />
                            </button>
                        </li>
                    @endforeach
                </ul>
            @endforeach
        @endif
    </div>

    <div class="mt-2 flex items-center justify-between border-t border-gray-100 px-6 py-3 text-[11px] sm:px-7 dark:border-gray-800">
        <span class="text-gray-400">Klik acara untuk melihat rinciannya.</span>
        <a href="{{ $calendarUrl }}" class="inline-flex items-center gap-1 font-medium text-gray-500 transition-colors hover:text-primary-600 dark:text-gray-400 dark:hover:text-primary-400">
            Buka kalender
            <x-filament::icon icon="heroicon-m-chevron-right" class="h-3 w-3" />
        </a>
    </div>

    {{-- record panel: identical to the calendar's --}}
    <div x-data="{ open: @entangle('panelOpen').live }" x-show="open" x-cloak class="fixed inset-0 z-40" role="dialog" aria-modal="true"
         x-on:keydown.escape.window="open && $wire.closePanel()">
        <div x-show="open" x-transition.opacity.duration.200ms class="absolute inset-0 bg-gray-950/30" x-on:click="$wire.closePanel()"></div>
        <div x-show="open"
             x-transition:enter="transition duration-300 ease-out" x-transition:enter-start="translate-x-8 opacity-0" x-transition:enter-end="translate-x-0 opacity-100"
             x-transition:leave="transition duration-200 ease-in" x-transition:leave-start="translate-x-0 opacity-100" x-transition:leave-end="translate-x-8 opacity-0"
             class="absolute inset-y-3 right-3 flex w-[calc(100%-1.5rem)] flex-col overflow-hidden rounded-xl bg-white shadow-2xl ring-1 ring-gray-950/10 sm:inset-y-4 sm:right-4 sm:w-[36rem] dark:bg-gray-900 dark:ring-white/10">
            @if ($selected)
                @include('filament.pages.calendar.partials.record', ['selected' => $selected, 'canManage' => $canManage, 'isParticipant' => $isParticipant, 'history' => $history, 'context' => 'dashboard'])
            @else
                <div class="flex flex-1 items-center justify-center p-8 text-sm text-gray-500">Acara tidak ditemukan.</div>
            @endif
        </div>
    </div>
</div>
