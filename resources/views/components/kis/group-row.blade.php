{{--
    Group header row for custom tables (DESIGN.md, Data table).

    variant="group"   : wash row, e.g. "Oktober 2026 · 2 laporan", right side in the `aside` slot.
    variant="section" : uppercase label above groups, e.g. "NOT STARTED".

    <x-kis.group-row :colspan="8" label="Oktober 2026" :count="2" count-label="laporan">
        <x-kis.badge tone="blue" size="sm">Bulan ini</x-kis.badge>
        <x-slot name="aside"><span class="kis-group-row__alert">1 belum lapor</span></x-slot>
    </x-kis.group-row>
--}}
@props([
    'colspan' => 1,
    'label' => '',
    'count' => null,
    'countLabel' => null,
    'variant' => 'group',
    'aside' => null,
])

<tr {{ $attributes->class(['kis-group-row', 'kis-group-row--section' => $variant === 'section']) }}>
    <td colspan="{{ $colspan }}">
        <div class="kis-group-row__inner">
            <span class="kis-group-row__label">{{ $label }}</span>
            @if (filled($count))
                <span class="kis-group-row__count">{{ $count }}{{ $countLabel ? ' ' . $countLabel : '' }}</span>
            @endif
            {{ $slot }}
            @if ($aside)
                <span class="kis-group-row__aside">{{ $aside }}</span>
            @endif
        </div>
    </td>
</tr>
