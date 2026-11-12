{{--
    One active filter. Click handlers (wire:click, x-on:click) go to the remove
    button; everything else stays on the chip.
--}}
@props([
    'removable' => true,
    'removeLabel' => null,
])

@php
    $removeAttributes = $attributes->filter(fn ($value, $key) => str_starts_with($key, 'wire:click') || str_starts_with($key, 'x-on:click') || str_starts_with($key, '@click'));
    $chipAttributes = $attributes->filter(fn ($value, $key) => ! $removeAttributes->has($key));
@endphp

<span {{ $chipAttributes->class(['kis-filter-chip']) }}>
    <span class="kis-filter-chip__text">{{ $slot }}</span>
    @if ($removable)
        <button type="button" {{ $removeAttributes->class(['kis-filter-chip__remove']) }} aria-label="{{ $removeLabel ?? 'Hapus filter ' . trim(strip_tags($slot)) }}">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M6 18 18 6M6 6l12 12" /></svg>
        </button>
    @endif
</span>
