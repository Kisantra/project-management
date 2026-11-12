{{-- Clients with the highest gross sales this year, from faktur keluaran DPP. --}}
@php
    $max = max(1, collect($data['rows'])->max('bruto'));
    $peak = $max;
    [$divisor, $unit] = $peak >= 1e9 ? [1e9, 'miliar'] : [1e6, 'juta'];
@endphp

<x-kis.card title="Klien terbesar" :subtitle="'Peredaran bruto ' . $data['year'] . ' dari faktur keluaran'" class="h-full" body-class="flex flex-col gap-3">
    <x-slot name="aside">Rp {{ $unit }}</x-slot>

    @if (empty($data['rows']))
        <p class="py-10 text-center text-sm text-gray-500 dark:text-gray-400">Belum ada faktur keluaran tahun ini.</p>
    @else
        <ol class="grid gap-3">
            @foreach ($data['rows'] as $row)
                <li class="grid grid-cols-[22px_1fr_5.5rem] items-center gap-3 text-[13px]">
                    <span class="font-extrabold tabular-nums text-gray-400">{{ $loop->iteration }}</span>
                    <a href="{{ $row['url'] }}" class="group min-w-0">
                        <span class="block truncate font-bold text-gray-950 group-hover:text-primary-700 dark:text-white dark:group-hover:text-primary-300">{{ $row['name'] }}</span>
                        @if ($row['handler'])
                            <span class="block truncate text-[11.5px] font-semibold text-gray-500 dark:text-gray-400">{{ $row['handler'] }}</span>
                        @endif
                        <span class="mt-1.5 block h-1.5 rounded-full bg-gray-100 dark:bg-white/5"><i class="block h-full rounded-full" style="width: {{ $row['bruto'] / $max * 100 }}%; background: var(--kis-ramp-b3)"></i></span>
                    </a>
                    <span class="text-right font-extrabold tabular-nums text-gray-950 dark:text-white">{{ number_format($row['bruto'] / $divisor, 2, ',', '.') }}</span>
                </li>
            @endforeach
        </ol>
        @if (count($data['rows']) < 6)
            {{-- Fewer clients than places: say why instead of leaving the card blank. --}}
            <div class="flex min-h-[3.5rem] flex-1 items-center justify-center rounded-[11px] border border-dashed border-gray-200 px-4 text-center text-xs font-semibold text-gray-500 dark:border-white/10 dark:text-gray-400">
                Baru {{ count($data['rows']) }} klien yang punya faktur keluaran di {{ $data['year'] }}.
            </div>
        @endif
    @endif

    <x-slot name="footer">
        <span>Nama di bawah klien = yang paling banyak menginput fakturnya.</span>
        <a href="{{ route('filament.admin.resources.clients.index') }}" class="kis-card__link">Clients →</a>
    </x-slot>
</x-kis.card>
