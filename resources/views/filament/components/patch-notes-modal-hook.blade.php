{{-- Popup "Pembaruan Sistem", dipasang di akhir <body> setiap halaman admin. --}}
@if (auth()->check() && ! auth()->user()->hasAnyRole(['client']))
    @livewire('patch-notes-modal')
@endif
