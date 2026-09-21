{{-- Activity timeline for the project detail "Aktivitas" tab.
     One vertical rail; day markers and entries sit on it. Colour lives only in
     the node: green = selesai/disetujui, amber = menunggu/berjalan, red = ditolak/
     dihapus, blue = file baru, gray = dibuat/diubah. Titles stay ink. --}}
@php
    $node = [
        'success' => 'bg-success-100 text-success-700 dark:bg-success-400/15 dark:text-success-300',
        'warning' => 'bg-warning-100 text-warning-700 dark:bg-warning-400/15 dark:text-warning-300',
        'danger'  => 'bg-danger-100 text-danger-700 dark:bg-danger-400/15 dark:text-danger-300',
        'info'    => 'bg-info-100 text-info-700 dark:bg-info-400/15 dark:text-info-300',
        'gray'    => 'bg-gray-100 text-gray-500 dark:bg-white/10 dark:text-gray-400',
    ];
    $iconName = [
        'plus' => 'heroicon-m-plus', 'check' => 'heroicon-m-check', 'play' => 'heroicon-m-play',
        'arrow-uturn' => 'heroicon-m-arrow-uturn-left', 'trash' => 'heroicon-m-trash', 'eye' => 'heroicon-m-eye',
        'upload' => 'heroicon-m-arrow-up-tray', 'x' => 'heroicon-m-x-mark', 'pencil' => 'heroicon-m-pencil',
        'flag' => 'heroicon-m-flag', 'user' => 'heroicon-m-user', 'calendar' => 'heroicon-m-calendar-days',
        'no-symbol' => 'heroicon-m-no-symbol',
    ];
    $hasMore = $total > $limit;
    $burstTitle = fn (array $e) => count($e['items']) . ' ' . Str::lower(Str::before($e['title'], ' ')) . ' ' . Str::after($e['title'], ' ');
@endphp
<div>
    {{-- Filter chips --}}
    <div class="flex flex-wrap items-center justify-between gap-3 px-4 py-3 sm:px-5">
        <div class="flex flex-wrap items-center gap-1.5" role="group" aria-label="Filter aktivitas">
            @foreach (\App\Livewire\Projects\Components\ProjectActivityFeed::FILTERS as $key => $label)
                @php $on = $filter === $key; $n = $counts[$key] ?? 0; @endphp
                <button type="button"
                        wire:click="setFilter('{{ $key }}')"
                        wire:loading.attr="disabled"
                        aria-pressed="{{ $on ? 'true' : 'false' }}"
                        @class([
                            'inline-flex h-7 items-center gap-1.5 rounded-full px-2.5 text-xs font-medium ring-1 ring-inset transition-colors duration-150',
                            'bg-primary-50 text-primary-700 ring-primary-600/30 dark:bg-primary-400/10 dark:text-primary-300 dark:ring-primary-400/30' => $on,
                            'bg-white text-gray-600 ring-gray-200 hover:text-gray-900 dark:bg-white/5 dark:text-gray-400 dark:ring-white/10 dark:hover:text-white' => ! $on,
                        ])>
                    {{ $label }}
                    <span class="tabular-nums {{ $on ? 'text-primary-600/80 dark:text-primary-300/80' : 'text-gray-400 dark:text-gray-500' }}">{{ $n }}</span>
                </button>
            @endforeach
        </div>
        <a href="{{ \App\Filament\Resources\ProjectResource::getUrl('activity', ['record' => $project]) }}"
           class="inline-flex items-center gap-1 text-xs font-medium text-gray-500 hover:text-primary-600 dark:text-gray-400 dark:hover:text-primary-400">
            Log lengkap &amp; laporan PDF
            <x-heroicon-m-arrow-top-right-on-square class="h-3.5 w-3.5" />
        </a>
    </div>

    <div wire:loading.class="opacity-50" wire:target="setFilter, loadMore" class="border-t border-gray-100 transition-opacity duration-150 dark:border-white/5">
        @if (empty($feed))
            <div class="px-4 py-12 text-center sm:px-5">
                <x-heroicon-o-clock class="mx-auto h-7 w-7 text-gray-400" />
                <p class="mt-2 text-sm font-medium text-gray-900 dark:text-white">Belum ada aktivitas</p>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    @if ($filter === 'semua')
                        Perubahan pada tahapan, tugas, dan dokumen proyek ini akan tercatat di sini.
                    @else
                        Tidak ada aktivitas untuk kategori ini.
                    @endif
                </p>
            </div>
        @else
            <div class="px-4 py-5 sm:px-6">
                {{-- The rail: a 1px line the nodes are centred on --}}
                <ol class="relative ml-3 border-l border-gray-200 dark:border-white/10">
                    @foreach ($feed as $group)
                        {{-- Day marker on the rail --}}
                        <li class="relative pl-7 {{ $loop->first ? '' : 'pt-4' }} pb-3" wire:key="day-{{ $group['date'] }}">
                            <span class="absolute -left-[5px] h-[9px] w-[9px] rounded-full bg-gray-300 ring-4 ring-white dark:bg-gray-600 dark:ring-gray-900 {{ $loop->first ? 'top-[3.5px]' : 'top-[19.5px]' }}"></span>
                            <h3 class="text-xs font-semibold text-gray-700 dark:text-gray-200">
                                {{ $group['label'] }}
                                <span class="ml-1 font-normal tabular-nums text-gray-400 dark:text-gray-500">{{ count($group['entries']) }}</span>
                            </h3>
                        </li>

                        @foreach ($group['entries'] as $entry)
                            @php $isBurst = count($entry['items']) > 1; @endphp
                            <li class="relative pl-7 pb-4 last:pb-0" wire:key="act-{{ $entry['id'] }}" x-data="{ open: false }">
                                {{-- Node --}}
                                <span class="absolute -left-[13px] top-0 flex h-[26px] w-[26px] items-center justify-center rounded-full ring-4 ring-white dark:ring-gray-900 {{ $node[$entry['tone']] ?? $node['gray'] }}">
                                    @svg($iconName[$entry['icon']] ?? 'heroicon-m-pencil', 'h-3.5 w-3.5')
                                </span>

                                <div class="min-w-0 pt-0.5">
                                    <p class="text-sm leading-6 text-gray-900 dark:text-gray-100">
                                        <span class="font-semibold">{{ $isBurst ? $burstTitle($entry) : $entry['title'] }}</span>
                                        @if (! $isBurst && $entry['subject'])
                                            <span class="text-gray-400 dark:text-gray-500">&middot;</span>
                                            <span>{{ $entry['subject'] }}</span>
                                        @endif
                                        @if (! $isBurst && $entry['detail'])
                                            <span class="text-gray-500 dark:text-gray-400">{{ $entry['detail'] }}</span>
                                        @endif
                                    </p>
                                    <p class="text-xs text-gray-500 dark:text-gray-400">
                                        <time class="tabular-nums" datetime="{{ $entry['at']->toIso8601String() }}">{{ $entry['at']->format('H:i') }}</time>
                                        <span class="mx-1 text-gray-300 dark:text-gray-600">&middot;</span>
                                        {{ $entry['actor'] }}
                                        @if ($isBurst)
                                            <span class="mx-1 text-gray-300 dark:text-gray-600">&middot;</span>
                                            <button type="button" x-on:click="open = !open" :aria-expanded="open"
                                                    class="font-medium text-primary-600 hover:underline dark:text-primary-400">
                                                <span x-text="open ? 'Sembunyikan' : 'Lihat {{ count($entry['items']) }} item'">Lihat {{ count($entry['items']) }} item</span>
                                            </button>
                                        @endif
                                    </p>

                                    @if ($isBurst)
                                        <ul x-show="open" x-cloak
                                            x-transition:enter="transition duration-150 ease-out motion-reduce:transition-none"
                                            x-transition:enter-start="opacity-0"
                                            x-transition:enter-end="opacity-100"
                                            class="mt-2 max-w-2xl divide-y divide-gray-100 rounded-lg bg-gray-50 text-sm ring-1 ring-gray-950/5 dark:divide-white/5 dark:bg-white/[.03] dark:ring-white/10">
                                            @foreach ($entry['items'] as $item)
                                                <li class="flex items-baseline gap-2 px-3 py-1.5">
                                                    <span class="w-10 shrink-0 text-xs tabular-nums text-gray-400 dark:text-gray-500">{{ $item['at']->format('H:i') }}</span>
                                                    <span class="min-w-0 flex-1 truncate text-gray-800 dark:text-gray-200" title="{{ $item['subject'] }}">{{ $item['subject'] }}</span>
                                                    @if ($item['detail'])
                                                        <span class="hidden shrink-0 truncate text-xs text-gray-500 dark:text-gray-400 sm:inline">{{ $item['detail'] }}</span>
                                                    @endif
                                                </li>
                                            @endforeach
                                        </ul>
                                    @endif
                                </div>
                            </li>
                        @endforeach
                    @endforeach
                </ol>
            </div>

            @if ($hasMore)
                <div class="border-t border-gray-100 px-4 py-3 text-center dark:border-white/5 sm:px-5">
                    <x-filament::button size="sm" color="gray" wire:click="loadMore" wire:loading.attr="disabled" wire:target="loadMore">
                        Muat lebih banyak
                    </x-filament::button>
                    <p class="mt-1.5 text-xs text-gray-400 dark:text-gray-500">Menampilkan {{ min($limit, $total) }} dari {{ $total }} catatan</p>
                </div>
            @endif
        @endif
    </div>
</div>
