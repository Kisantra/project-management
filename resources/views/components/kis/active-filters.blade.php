{{--
    "Filter aktif" row: every active filter as a removable chip, defaults included
    (DESIGN.md, Chips). Put <x-kis.filter-chip> elements in the slot.

    <x-kis.active-filters>
        <x-kis.filter-chip wire:click="clearPeriod">Periode: Sep – Nov 2026</x-kis.filter-chip>
        <x-slot name="reset" wire:click="resetFilters">Reset semua</x-slot>
    </x-kis.active-filters>
--}}
@props([
    'label' => 'Filter aktif',
    'reset' => null,
])

<div {{ $attributes->class(['kis-active-filters']) }}>
    <span class="kis-active-filters__label">{{ $label }}</span>
    {{ $slot }}
    @if ($reset)
        <button type="button" {{ $reset->attributes->class(['kis-active-filters__reset']) }}>{{ $reset }}</button>
    @endif
</div>
