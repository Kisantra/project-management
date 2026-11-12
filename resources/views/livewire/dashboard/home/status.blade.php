{{-- Projects per status in workflow order (ordinal ramp, Tone::projectStatusChart), plus two facts.
     The rows spread to the card's height (it sits beside the compliance card), so no blank area is left. --}}
<x-kis.card title="Proyek per status" :subtitle="number_format($data['total'], 0, ',', '.') . ' proyek ' . ($mine ? 'Anda' : 'klien aktif')" class="h-full" body-class="flex flex-col">
    @if ($data['total'] > 0)
        <div class="flex h-3.5 gap-0.5 overflow-hidden rounded-full" role="img" aria-label="Proyek per status">
            @foreach ($data['rows'] as $row)
                @if ($row['count'] > 0)
                    <a href="{{ $row['url'] }}" class="block h-full hover:opacity-80" style="width: {{ $row['percent'] }}%; background: {{ $row['color'] }}"
                       data-kis-tip="{{ json_encode(['t' => $row['label'], 'r' => [[$row['color'], 'Proyek', $row['count'] . ' (' . $row['percent'] . '%)']]]) }}"></a>
                @endif
            @endforeach
        </div>
    @endif

    <ul class="mt-3.5 flex flex-1 flex-col justify-between gap-0.5">
        @foreach ($data['rows'] as $row)
            <li>
                <a href="{{ $row['url'] }}" @class(['grid grid-cols-[12px_1fr_auto_46px] items-center gap-2.5 rounded-[7px] py-1.5 text-[13px] font-semibold hover:bg-gray-50 dark:hover:bg-white/[.03]', 'opacity-50' => $row['count'] === 0])>
                    <i class="h-2.5 w-2.5 rounded-[3px]" style="background: {{ $row['color'] }}"></i>
                    <span class="truncate text-gray-700 dark:text-gray-300">{{ $row['label'] }}</span>
                    <b class="tabular-nums text-gray-950 dark:text-white">{{ $row['count'] }}</b>
                    <small class="text-right tabular-nums text-gray-500">{{ round($row['percent']) }}%</small>
                </a>
            </li>
        @endforeach
    </ul>

    <div class="mt-3.5 grid grid-cols-2 gap-2.5">
        @foreach ($data['facts'] as $fact)
            <div class="rounded-[11px] bg-gray-50 px-3 py-2.5 dark:bg-white/[.03]">
                <b class="block text-xl font-extrabold tracking-tight tabular-nums text-gray-950 dark:text-white">{{ $fact['value'] }}</b>
                <span class="text-xs font-semibold text-gray-500 dark:text-gray-400">{{ $fact['label'] }}</span>
            </div>
        @endforeach
    </div>
</x-kis.card>
