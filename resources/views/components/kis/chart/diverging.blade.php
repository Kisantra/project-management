{{--
    Diverging bars around a zero baseline that fill their container's height
    (use as a flex-1 child): one value up, one down, per label. Used for
    kurang bayar (up, red) vs lebih bayar (down, blue). HTML bars placed in
    percent; values are divided by $divisor (1e6 = Rp juta) for axis and tooltip.
--}}
@props([
    'labels' => [],
    'titles' => [],
    'up' => [],
    'down' => [],
    'upLabel' => '',
    'downLabel' => '',
    'upColor' => 'rgb(var(--danger-500))',
    'downColor' => 'var(--kis-ramp-b3)',
    'divisor' => 1,
    'unit' => '',
    'label' => '',
    'minHeight' => '12rem',
])

@php
    $n = max(1, count($labels));
    $nice = function ($v) {
        $v = max(1, $v);
        $m = 10 ** floor(log10($v));
        return ceil($v / ($m / 2)) * ($m / 2);
    };
    $upMax = $nice(max(array_map(fn ($v) => $v / $divisor, $up ?: [0])));
    $downMax = $nice(max(array_map(fn ($v) => $v / $divisor, $down ?: [0])));
    // Keep the down side at least a third of the height so small overpayments stay visible.
    $downMax = max($downMax, $upMax / 3);
    $total = $upMax + $downMax;
    $py = fn ($v) => round(($upMax - $v) / $total * 100, 3);
    $fmt = fn ($v) => number_format($v, $v < 10 && fmod($v, 1.0) !== 0.0 ? 1 : 0, ',', '.');
@endphp

<div {{ $attributes->class(['kis-lc']) }} style="min-height: {{ $minHeight }}" role="img" aria-label="{{ $label }}">
    <div class="kis-lc__plot kis-lc__plot--bars">
        @foreach ([$upMax, $upMax / 2, 0, -$downMax] as $tick)
            <div @class(['kis-lc__grid', 'is-base' => $tick == 0]) style="top: {{ $py($tick) }}%"><span>{{ $tick > 0 ? '+' : ($tick < 0 ? '−' : '') }}{{ $fmt(abs($tick)) }}</span></div>
        @endforeach

        @foreach ($labels as $i => $text)
            @php
                $u = ($up[$i] ?? 0) / $divisor;
                $d = ($down[$i] ?? 0) / $divisor;
                $tip = ['t' => $titles[$i] ?? $text, 'r' => [[$upColor, $upLabel, 'Rp ' . $fmt($u) . ' ' . $unit], [$downColor, $downLabel, 'Rp ' . $fmt($d) . ' ' . $unit]]];
            @endphp
            <div class="kis-lc__band" style="left: {{ $i / $n * 100 }}%; width: {{ 100 / $n }}%" data-kis-tip="{{ json_encode($tip) }}">
                @if ($u > 0)
                    <i class="kis-lc__bar is-up" style="bottom: calc({{ 100 - $py(0) }}% + 1px); height: {{ $u / $total * 100 }}%; background: {{ $upColor }}"></i>
                @endif
                @if ($d > 0)
                    <i class="kis-lc__bar is-down" style="top: calc({{ $py(0) }}% + 1px); height: {{ $d / $total * 100 }}%; background: {{ $downColor }}"></i>
                @endif
                <span @class(['kis-lc__x', 'is-strong' => $loop->last]) style="left: 50%">{{ $text }}</span>
            </div>
        @endforeach
    </div>
</div>
