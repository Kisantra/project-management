@php
    /** @var string $shape  one of: empty | dashed | half | clock | check | x */
    /** @var string $color */
    /** @var int|null $size */
    /** @var bool|null $inverse */
    /** @var string|null $tone  design-system tone; draws in currentColor so dark mode can adjust it */
    $size = $size ?? 18;
    $inverse = $inverse ?? false;
    $tone = $tone ?? null;
    if ($tone) {
        $color = 'currentColor';
    }

    // For solid shapes (check / x): swap fill and inner-stroke when inverse.
    // For outline shapes: just use white as the line color when inverse.
    $line = $inverse ? '#ffffff' : $color;       // outline / strokes
    $solidFill = $inverse ? '#ffffff' : $color;  // filled circle bg
    $innerInk = $inverse ? $color : '#ffffff';   // inner glyph (check / x)
@endphp
<svg width="{{ $size }}" height="{{ $size }}" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg" @if ($tone) data-kis-tone="{{ $tone }}" class="kis-tone-ink" @endif aria-hidden="true">
    @switch($shape)
        @case('empty')
            <circle cx="10" cy="10" r="7.5" stroke="{{ $line }}" stroke-width="2" fill="none"/>
            @break
        @case('dashed')
            <circle cx="10" cy="10" r="7.5" stroke="{{ $line }}" stroke-width="2" fill="none" stroke-dasharray="3 3"/>
            @break
        @case('half')
            <circle cx="10" cy="10" r="7.5" stroke="{{ $line }}" stroke-width="2" fill="none"/>
            <path d="M10 2.5 A7.5 7.5 0 0 1 10 17.5 Z" fill="{{ $line }}"/>
            @break
        @case('clock')
            <circle cx="10" cy="10" r="7.5" stroke="{{ $line }}" stroke-width="2" fill="none"/>
            <path d="M10 5.5 V10 L13 12" stroke="{{ $line }}" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
            @break
        @case('check')
            <circle cx="10" cy="10" r="8.5" fill="{{ $solidFill }}"/>
            <path d="M6 10.2 L9 13 L14 7.5" stroke="{{ $innerInk }}" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" fill="none"/>
            @break
        @case('x')
            <circle cx="10" cy="10" r="8.5" fill="{{ $solidFill }}"/>
            <path d="M7 7 L13 13 M13 7 L7 13" stroke="{{ $innerInk }}" stroke-width="2.4" stroke-linecap="round"/>
            @break
        @default
            <circle cx="10" cy="10" r="7.5" stroke="{{ $line }}" stroke-width="2" fill="none"/>
    @endswitch
</svg>
