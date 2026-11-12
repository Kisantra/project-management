{{--
    Next 14 days. The calendar strip takes whatever height the card has left
    (it sits beside the taller compliance card), so busy days stand out and the
    card never shows a blank area; the nearest items are listed below it.
--}}
@php
    $dots = $data['colors'];
@endphp

<x-kis.card title="14 hari ke depan" :subtitle="$mine ? 'Tenggat dan jadwal Anda' : 'Tenggat pajak, proyek, dan jadwal'" class="h-full" body-class="flex flex-col gap-4">
    <div class="grid min-h-[8.5rem] flex-1 grid-cols-7 grid-rows-2 gap-1.5" role="list" aria-label="Kalender 14 hari ke depan">
        @foreach ($data['days'] as $day)
            <div role="listitem"
                 @class([
                     'flex min-w-0 flex-col justify-between rounded-[9px] px-2 py-1.5',
                     'bg-primary-50 ring-1 ring-inset ring-primary-600/30 dark:bg-primary-500/10 dark:ring-primary-400/30' => $day['today'],
                     'bg-gray-50 dark:bg-white/[.03]' => ! $day['today'] && ! $day['weekend'],
                     'bg-gray-50/50 dark:bg-white/[.015]' => ! $day['today'] && $day['weekend'],
                     'cursor-default' => $day['tip'],
                 ])
                 @if ($day['tip']) data-kis-tip="{{ json_encode($day['tip']) }}" @endif
                 aria-label="{{ $day['weekday'] }} {{ $day['day'] }}: {{ $day['count'] }} agenda">
                <div>
                    <div @class(['text-[10px] font-bold uppercase tracking-wide', 'text-primary-700 dark:text-primary-300' => $day['today'], 'text-gray-400' => ! $day['today']])>{{ $day['weekday'] }}</div>
                    <div @class(['text-[15px] font-extrabold leading-tight tabular-nums', 'text-gray-950 dark:text-white' => ! $day['weekend'] || $day['count'], 'text-gray-400' => $day['weekend'] && ! $day['count']])>{{ $day['day'] }}</div>
                </div>
                @if ($day['count'] > 0)
                    <div class="flex items-center gap-1">
                        @foreach ($day['tones'] as $tone)
                            <span class="h-2 w-2 rounded-full" style="background: {{ $dots[$tone] }}"></span>
                        @endforeach
                        @if ($day['count'] > 1)
                            <span class="text-[10.5px] font-bold tabular-nums text-gray-500 dark:text-gray-400">{{ $day['count'] }}</span>
                        @endif
                    </div>
                @endif
            </div>
        @endforeach
    </div>

    @if ($data['total'] === 0)
        <p class="text-sm text-gray-500 dark:text-gray-400">Tidak ada tenggat atau jadwal dalam 14 hari ke depan.</p>
    @else
        <ul>
            @foreach ($data['list'] as $item)
                <li class="grid grid-cols-[4.25rem_1fr_auto] items-start gap-2.5 border-t border-gray-100 py-2 first:border-0 first:pt-0 dark:border-white/5">
                    <span @class(['pt-px text-xs font-bold', 'text-primary-700 dark:text-primary-300' => $item['when'] === 'Hari ini', 'text-gray-500 dark:text-gray-400' => $item['when'] !== 'Hari ini'])>{{ $item['when'] }}</span>
                    <div class="flex min-w-0 gap-2">
                        <span class="mt-1.5 h-2 w-2 shrink-0 rounded-full" style="background: {{ $dots[$item['tone']] }}"></span>
                        <div class="min-w-0">
                            <div class="truncate text-[13px] font-bold text-gray-950 dark:text-white" title="{{ $item['title'] }}">{{ $item['title'] }}</div>
                            <div class="truncate text-xs font-semibold text-gray-500 dark:text-gray-400">{{ $item['sub'] }}</div>
                        </div>
                    </div>
                    <x-kis.badge tone="neutral" size="sm">{{ $item['kind'] }}</x-kis.badge>
                </li>
            @endforeach
        </ul>
    @endif

    <x-slot name="footer">
        <span>{{ $data['more'] > 0 ? '+' . $data['more'] . ' agenda lainnya di kalender' : 'Arahkan kursor ke tanggal' }}</span>
        <a href="{{ route('filament.admin.pages.kalender') }}" class="kis-card__link">Buka Kalender →</a>
    </x-slot>
</x-kis.card>
