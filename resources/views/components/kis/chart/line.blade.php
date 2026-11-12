{{--
    Line chart that fills its container's height (use it as a flex-1 child).
    Lines and areas are SVG stretched to the plot (strokes stay 2px via
    non-scaling-stroke); labels, dots and hover columns are HTML placed in
    percent, so text never stretches. Null values break the line.

    <x-kis.chart.line
        :labels="['Des', 'Jan', ...]"
        :titles="['Desember 2025', ...]"            // tooltip titles
        :series="[['name' => 'Selesai', 'color' => 'rgb(var(--primary-600))', 'values' => [...], 'area' => true],
                  ['name' => 'Masuk', 'color' => 'rgb(var(--gray-500))', 'values' => [...], 'dashed' => true]]"
        :min="0" :max="null" unit="%" :target="90" :marks="[3]"
        label="Proyek masuk dan selesai per bulan" min-height="12rem"
    />
--}}
@props([
    'labels' => [],
    'titles' => [],
    'series' => [],
    'min' => 0,
    'max' => null,
    'unit' => '',
    'target' => null,
    'marks' => [],
    'label' => '',
    'minHeight' => '12rem',
])

@php
    $n = max(1, count($labels));
    $values = collect($series)->flatMap(fn ($s) => $s['values'])->filter(fn ($v) => $v !== null);
    if ($max === null) {
        // A "nice" top whose half is a whole number, so the middle gridline reads cleanly.
        $peak = max(1, (float) $values->max(), (float) ($target ?? 0));
        if ($peak < 10) {
            $max = (int) (ceil($peak / 2) * 2);
        } else {
            $step = (10 ** floor(log10($peak))) / 2;
            $max = (int) (ceil($peak / $step) * $step);
            if ($max % 2 !== 0) {
                $max += (int) $step;
            }
        }
    }
    $span = max(0.000001, $max - $min);
    $ticks = [$max, $min + $span / 2, $min];

    $px = fn (int $i) => round($n > 1 ? $i / ($n - 1) * 100 : 50, 3);
    $py = fn ($v) => round((1 - ($v - $min) / $span) * 100, 3);
    $fmt = fn ($v) => (fmod((float) $v, 1.0) === 0.0 ? number_format((float) $v, 0, ',', '.') : number_format((float) $v, 1, ',', '.')) . $unit;
    $colWidth = $n > 1 ? 100 / ($n - 1) : 100;
@endphp

<div {{ $attributes->class(['kis-lc']) }} style="min-height: {{ $minHeight }}" role="img" aria-label="{{ $label }}">
    <div class="kis-lc__plot">
        @foreach ($ticks as $tick)
            <div class="kis-lc__grid" style="top: {{ $py($tick) }}%"><span>{{ $fmt($tick) }}</span></div>
        @endforeach

        @if ($target !== null)
            <div class="kis-lc__target" style="top: {{ $py($target) }}%"><span>{{ $fmt($target) }}</span></div>
        @endif

        <svg class="kis-lc__svg" viewBox="0 0 100 100" preserveAspectRatio="none" aria-hidden="true">
            @foreach ($series as $s)
                @php
                    $runs = [];
                    $run = [];
                    foreach ($s['values'] as $i => $v) {
                        if ($v === null) {
                            if ($run) { $runs[] = $run; }
                            $run = [];
                            continue;
                        }
                        $run[] = [$px($i), $py($v)];
                    }
                    if ($run) { $runs[] = $run; }
                @endphp
                @foreach ($runs as $points)
                    @php $path = collect($points)->map(fn ($p) => $p[0] . ',' . $p[1])->implode(' '); @endphp
                    @if (! empty($s['area']) && count($points) > 1)
                        <polygon points="{{ $points[0][0] }},100 {{ $path }} {{ end($points)[0] }},100" style="fill: {{ $s['color'] }}" opacity=".08" />
                    @endif
                    <polyline points="{{ $path }}" fill="none" style="stroke: {{ $s['color'] }}" stroke-width="2" stroke-linejoin="round" stroke-linecap="round" vector-effect="non-scaling-stroke" @if (! empty($s['dashed'])) stroke-dasharray="5 4" @endif />
                @endforeach
            @endforeach
        </svg>

        @foreach ($series as $s)
            @foreach ($marks as $i)
                @if (($s['values'][$i] ?? null) !== null)
                    <i class="kis-lc__dot" style="left: {{ $px($i) }}%; top: {{ $py($s['values'][$i]) }}%; background: {{ $s['color'] }}"></i>
                @endif
            @endforeach
            @php $last = collect($s['values'])->keys()->reverse()->first(fn ($i) => $s['values'][$i] !== null); @endphp
            @if ($last !== null)
                <i class="kis-lc__dot" style="left: {{ $px($last) }}%; top: {{ $py($s['values'][$last]) }}%; background: {{ $s['color'] }}"></i>
                <span class="kis-lc__end" style="left: {{ $px($last) }}%; top: {{ $py($s['values'][$last]) }}%">{{ $fmt($s['values'][$last]) }}</span>
            @endif
        @endforeach

        @foreach ($labels as $i => $text)
            <span @class(['kis-lc__x', 'is-strong' => $loop->last]) style="left: {{ $px($i) }}%">{{ $text }}</span>
        @endforeach

        {{-- Hover layer: one column per point --}}
        @foreach ($labels as $i => $text)
            @php
                $tip = ['t' => $titles[$i] ?? $text, 'r' => collect($series)->map(fn ($s) => [$s['color'], $s['name'], $s['values'][$i] === null ? '–' : $fmt($s['values'][$i])])->all()];
            @endphp
            <div class="kis-lc__col" style="left: {{ $px($i) - $colWidth / 2 }}%; width: {{ $colWidth }}%" data-kis-tip="{{ json_encode($tip) }}">
                <i class="kis-lc__cross"></i>
                @foreach ($series as $s)
                    @if ($s['values'][$i] !== null)
                        <i class="kis-lc__dot kis-lc__hover-dot" style="left: 50%; top: {{ $py($s['values'][$i]) }}%; background: {{ $s['color'] }}"></i>
                    @endif
                @endforeach
            </div>
        @endforeach
    </div>
</div>
