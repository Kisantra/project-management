{{-- Projects started vs completed per month: a backlog grows while "masuk" stays above "selesai". --}}
@php
    $in = ['name' => 'Masuk', 'color' => 'rgb(var(--gray-500))', 'values' => array_column($data['months'], 'in'), 'dashed' => true];
    $out = ['name' => 'Selesai', 'color' => 'rgb(var(--primary-600))', 'values' => array_column($data['months'], 'out'), 'area' => true];
@endphp

<x-kis.card title="Arus proyek" subtitle="Proyek masuk dan selesai per bulan, 12 bulan terakhir" class="h-full" body-class="flex flex-col gap-2">
    <x-slot name="aside">
        <div class="kis-legend">
            <span><i class="is-dash"></i>Masuk</span>
            <span><i class="is-line" style="background: rgb(var(--primary-600))"></i>Selesai</span>
        </div>
    </x-slot>

    <div class="flex flex-wrap gap-x-7 gap-y-1 text-[13px] font-semibold text-gray-700 dark:text-gray-300">
        <span><b class="text-xl font-extrabold tabular-nums text-gray-950 dark:text-white">{{ $data['in'] }}</b> masuk</span>
        <span><b class="text-xl font-extrabold tabular-nums text-gray-950 dark:text-white">{{ $data['out'] }}</b> selesai</span>
        <span>
            <b @class(['text-xl font-extrabold tabular-nums', 'text-danger-700 dark:text-danger-300' => $data['net'] > 0, 'text-gray-950 dark:text-white' => $data['net'] <= 0])>{{ $data['net'] > 0 ? '+' : '' }}{{ $data['net'] }}</b>
            {{ $data['net'] > 0 ? 'menumpuk' : 'antrean berkurang' }}
        </span>
    </div>

    <x-kis.chart.line
        :labels="array_column($data['months'], 'label')"
        :titles="array_column($data['months'], 'title')"
        :series="[$in, $out]"
        label="Proyek masuk dan selesai per bulan"
    />

    <x-slot name="footer">
        <span>Garis masuk di atas garis selesai berarti antrean bertambah. Selesai dihitung dari log perubahan status.</span>
        <a href="{{ $data['url'] }}" class="kis-card__link">Buka Projects →</a>
    </x-slot>
</x-kis.card>
