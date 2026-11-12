{{-- Filing state of the period billed this month: headline rate, one 100% bar per obligation, this month's deadlines. --}}
@php
    $tones = ['done' => 'var(--kis-ramp-b3)', 'pending' => 'var(--kis-bar-track)', 'late' => 'var(--kis-bar-red)'];
    $spine = $data['spine'];
    $pos = fn (int $day) => round(($day - 1) / max(1, $spine['days'] - 1) * 100, 2);
@endphp

<x-kis.card title="Kepatuhan pajak" :subtitle="'Masa ' . $data['period'] . ' · ditagih ' . $data['anchor']" class="h-full">
    <x-slot name="aside">
        <div class="kis-legend">
            <span><i style="background: {{ $tones['done'] }}"></i>Sudah</span>
            <span><i style="background: {{ $tones['pending'] }}"></i>Belum</span>
            <span><i style="background: {{ $tones['late'] }}"></i>Lewat tenggat</span>
        </div>
    </x-slot>

    @if ($data['total'] === 0)
        <p class="py-10 text-center text-sm text-gray-500 dark:text-gray-400">Belum ada kewajiban pajak untuk masa {{ $data['period'] }}.</p>
    @else
        <div class="flex flex-wrap items-end gap-x-5 gap-y-2">
            <div class="text-[2.5rem] font-extrabold leading-none tracking-tight tabular-nums text-gray-950 dark:text-white">{{ $data['pct'] }}<span class="text-lg text-gray-400">%</span></div>
            <div class="pb-1 text-[13px] font-semibold text-gray-700 dark:text-gray-300">
                <b class="tabular-nums text-gray-950 dark:text-white">{{ $data['done'] }} dari {{ $data['total'] }}</b> kewajiban lapor masa ini sudah selesai
            </div>
            @if ($data['previous']['pct'] !== null)
                <span class="mb-1 inline-flex h-[22px] items-center rounded-[7px] bg-gray-100 px-2 text-xs font-bold text-gray-600 dark:bg-white/5 dark:text-gray-300">Masa {{ $data['previous']['label'] }}: {{ $data['previous']['pct'] }}%</span>
            @endif
        </div>
    @endif

    <div class="mt-5 grid gap-3">
        @foreach ($data['types'] as $type)
            <div class="grid grid-cols-[8rem_1fr_6rem] items-center gap-3.5 text-[13px]">
                <div class="font-bold text-gray-950 dark:text-white">
                    {{ $type['label'] }}
                    <small class="block text-[11.5px] font-semibold text-gray-500 dark:text-gray-400">{{ $type['sub'] }}</small>
                </div>
                @if ($type['total'] === 0)
                    <div class="text-xs font-semibold text-gray-400">{{ $type['key'] === 'payment' ? 'Tidak ada kurang bayar' : 'Tidak ada kewajiban' }}</div>
                    <div></div>
                @else
                    <div class="kis-bar100">
                        @foreach (['done' => 'Sudah', 'pending' => 'Belum', 'late' => 'Lewat tenggat'] as $part => $partLabel)
                            @if ($type[$part] > 0)
                                <i style="width: {{ $type[$part] / $type['total'] * 100 }}%; background: {{ $tones[$part] }}"
                                   data-kis-tip="{{ json_encode(['t' => $type['label'], 'r' => [[$tones[$part], $partLabel, $type[$part] . ' klien']]]) }}"></i>
                            @endif
                        @endforeach
                    </div>
                    <div class="text-right font-extrabold tabular-nums text-gray-950 dark:text-white">
                        {{ (int) round($type['done'] / $type['total'] * 100) }}%
                        @if ($type['late'] > 0)
                            <small class="block text-[11.5px] font-bold text-danger-700 dark:text-danger-300">{{ $type['late'] }} lewat tenggat</small>
                        @else
                            <small class="block text-[11.5px] font-semibold text-gray-500 dark:text-gray-400">{{ $type['done'] }} / {{ $type['total'] }}</small>
                        @endif
                    </div>
                @endif
            </div>
        @endforeach
    </div>

    {{-- This month's deadlines --}}
    <div class="mt-5 border-t border-dashed border-gray-200 pt-4 dark:border-white/10">
        <div class="mb-9 flex justify-between text-xs font-bold text-gray-500 dark:text-gray-400">
            <span>Tenggat {{ $spine['month'] }}</span>
            <span>Hari ini tanggal {{ $spine['today'] }}</span>
        </div>
        <div class="relative mx-2 mb-9 h-1 rounded-full bg-gray-200 dark:bg-white/10">
            <div class="absolute inset-y-0 left-0 rounded-full bg-primary-600 dark:bg-primary-400" style="width: {{ $pos($spine['today']) }}%"></div>
            <div class="absolute -top-2 h-5 w-0.5 bg-primary-600 dark:bg-primary-400" style="left: {{ $pos($spine['today']) }}%" title="Hari ini"></div>
            @foreach ($spine['marks'] as $mark)
                @php $edge = $pos($mark['day']) > 85 ? 'right' : ($mark['day'] < $spine['today'] ? 'right' : 'center'); @endphp
                <div class="absolute top-1/2 -translate-y-1/2" style="left: {{ $pos($mark['day']) }}%">
                    <span @class([
                        'absolute -left-[7px] -top-[7px] block h-3.5 w-3.5 rounded-full border-[2.5px] bg-white dark:bg-gray-900',
                        'border-danger-600 dark:border-danger-400' => $mark['late'],
                        'border-gray-500' => ! $mark['late'],
                    ])></span>
                    <span @class([
                        'absolute bottom-3 whitespace-nowrap text-[11px] font-extrabold',
                        'right-1' => $edge === 'right',
                        '-translate-x-1/2' => $edge === 'center',
                        'text-danger-700 dark:text-danger-300' => $mark['late'],
                        'text-gray-950 dark:text-white' => ! $mark['late'],
                    ])>{{ $mark['day'] }} · {{ $mark['label'] }}</span>
                    <span @class([
                        'absolute top-3 whitespace-nowrap text-[11px] font-semibold',
                        'right-1' => $edge === 'right',
                        '-translate-x-1/2' => $edge === 'center',
                        'text-danger-700 dark:text-danger-300' => $mark['late'],
                        'text-gray-500 dark:text-gray-400' => ! $mark['late'],
                    ])>{{ $mark['sub'] }}</span>
                </div>
            @endforeach
        </div>
    </div>

    <x-slot name="footer">
        <span>Satu kewajiban = satu klien aktif per masa per jenis pajak.</span>
        <a href="{{ $data['listUrl'] }}" class="kis-card__link">Buka Laporan Pajak →</a>
    </x-slot>
</x-kis.card>
