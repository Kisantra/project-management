{{--
    Dashboard card (DESIGN.md: radius 14, hairline, no shadow).

    <x-kis.card title="Arus proyek" subtitle="12 bulan terakhir">
        <x-slot name="aside">legend or range</x-slot>
        ...body...
        <x-slot name="footer">note + link</x-slot>
    </x-kis.card>
--}}
@props([
    'title',
    'subtitle' => null,
    // Extra classes for the body, e.g. "flex flex-col" so content can fill the card's height.
    'bodyClass' => null,
])

<section {{ $attributes->class(['kis-card']) }}>
    <header class="kis-card__head">
        <div class="min-w-0">
            <h2 class="kis-card__title">{{ $title }}</h2>
            @if (filled($subtitle))
                <p class="kis-card__sub">{{ $subtitle }}</p>
            @endif
        </div>
        @isset($aside)
            <div class="kis-card__aside">{{ $aside }}</div>
        @endisset
    </header>

    <div @class(['kis-card__body', $bodyClass])>{{ $slot }}</div>

    @isset($footer)
        <footer class="kis-card__foot">{{ $footer }}</footer>
    @endisset
</section>
