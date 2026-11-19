{{-- Empty state inside a card: icon tile, title, one line, optional action slot via $action (html). --}}
<div class="kpt-empty">
    <span class="kpt-empty__icon"><x-filament::icon :icon="$icon" class="h-5 w-5" /></span>
    <p class="kpt-empty__title">{{ $title }}</p>
    @if (filled($text ?? null))
        <p class="kpt-empty__text">{{ $text }}</p>
    @endif
    {{ $action ?? '' }}
</div>
