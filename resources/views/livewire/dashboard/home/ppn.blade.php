{{-- PPN underpaid (to pay) vs overpaid per period, diverging around zero. --}}
@php
    $peak = max(array_merge(array_column($data['points'], 'up'), array_column($data['points'], 'down'), [0]));
    [$divisor, $unit] = $peak >= 1e9 ? [1e9, 'miliar'] : [1e6, 'juta'];
    $latest = $data['latest'];
@endphp

<x-kis.card title="Posisi PPN klien" :subtitle="'Total kurang bayar dan lebih bayar per masa, Rp ' . $unit" class="h-full" body-class="flex flex-col">
    <x-slot name="aside">
        <div class="kis-legend">
            <span><i style="background: rgb(var(--danger-500))"></i>Kurang bayar</span>
            <span><i style="background: var(--kis-ramp-b3)"></i>Lebih bayar</span>
        </div>
    </x-slot>

    @if ($peak <= 0)
        <p class="py-10 text-center text-sm text-gray-500 dark:text-gray-400">Belum ada PPN kurang atau lebih bayar dalam 12 masa terakhir.</p>
    @else
        <x-kis.chart.diverging
            :labels="array_column($data['points'], 'label')"
            :titles="array_column($data['points'], 'title')"
            :up="array_column($data['points'], 'up')"
            :down="array_column($data['points'], 'down')"
            up-label="Kurang bayar" down-label="Lebih bayar"
            :divisor="$divisor" :unit="$unit"
            label="Kurang dan lebih bayar PPN per masa" min-height="10rem"
        />
    @endif

    <x-slot name="footer">
        <span>
            {{ $latest['title'] }}:
            @if ($latest['up'] > 0)
                <b class="text-danger-700 dark:text-danger-300">Rp {{ number_format($latest['up'] / $divisor, 1, ',', '.') }} {{ $unit }}</b> kurang bayar dari {{ $latest['clients'] }} klien.
            @else
                tidak ada kurang bayar.
            @endif
        </span>
        <a href="{{ route('filament.admin.resources.tax-reports.index') }}" class="kis-card__link">Laporan Pajak →</a>
    </x-slot>
</x-kis.card>
