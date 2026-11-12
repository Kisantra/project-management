{{-- Client status in the group panel: Aktif blue, Nonaktif neutral (DESIGN.md three tones). --}}
@props(['status' => null])

@if ($status === 'Active')
    <x-kis.badge tone="blue" dot size="sm" class="shrink-0">Aktif</x-kis.badge>
@elseif ($status === 'Inactive')
    <x-kis.badge tone="neutral" dot size="sm" class="shrink-0">Nonaktif</x-kis.badge>
@endif
