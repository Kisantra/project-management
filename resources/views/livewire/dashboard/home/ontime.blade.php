{{-- Share of obligations reported on or before the deadline, last twelve closed periods. --}}
@php
    $values = array_column($data['points'], 'value');
    $marks = array_keys(array_filter($values, fn ($v) => $v !== null && $v < $data['target'] - 15));
@endphp

<x-kis.card title="Lapor tepat waktu" subtitle="Kewajiban dilapor sebelum tenggat, 12 masa terakhir yang sudah tutup" class="h-full" body-class="flex flex-col">
    <x-slot name="aside">Target {{ $data['target'] }}%</x-slot>

    @if (collect($values)->filter(fn ($v) => $v !== null)->isEmpty())
        <p class="py-10 text-center text-sm text-gray-500 dark:text-gray-400">Belum ada laporan dengan tanggal lapor tercatat.</p>
    @else
        <x-kis.chart.line
            :labels="array_column($data['points'], 'label')"
            :titles="array_column($data['points'], 'title')"
            :series="[['name' => 'Tepat waktu', 'color' => 'rgb(var(--primary-600))', 'values' => $values, 'area' => true]]"
            :min="0" :max="100" unit="%" :target="$data['target']" :marks="$marks"
            label="Persentase lapor tepat waktu per masa" min-height="10rem"
        />
    @endif

    <x-slot name="footer">
        <span>
            @if ($data['lowest'])
                Terendah: <b class="text-gray-700 dark:text-gray-300">{{ $data['lowest']['title'] }} ({{ $data['lowest']['value'] }}%)</b>
            @else
                Laporan tanpa tanggal lapor tidak dihitung.
            @endif
        </span>
    </x-slot>
</x-kis.card>
