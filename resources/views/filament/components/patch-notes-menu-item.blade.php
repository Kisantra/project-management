{{-- Item menu user untuk membuka ulang popup "Pembaruan Sistem". --}}
@if (auth()->check() && ! auth()->user()->hasAnyRole(['client']))
    <x-filament::dropdown.list>
        <x-filament::dropdown.list.item
            icon="heroicon-m-sparkles"
            x-on:click="close(); Livewire.dispatch('open-patch-notes')"
        >
            Apa yang baru
        </x-filament::dropdown.list.item>
    </x-filament::dropdown.list>
@endif
