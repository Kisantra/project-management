{{--
    Status / category badge (DESIGN.md, Badges). Tones: blue, neutral, red, outline.
    Statuses get a dot, categories don't. Pick the tone with App\Support\Ui\Tone.

    <x-kis.badge :tone="\App\Support\Ui\Tone::forPaymentStatus($status)" dot>{{ $status }}</x-kis.badge>
--}}
@props([
    'tone' => 'neutral',
    'dot' => false,
    'size' => 'md',
])

<span {{ $attributes->class([
    'kis-badge',
    'kis-badge--' . \App\Support\Ui\Tone::normalize($tone),
    'kis-badge--dot' => $dot,
    'kis-badge--sm' => $size === 'sm',
]) }}>{{ $slot }}</span>
