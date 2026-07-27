{{--
    Dashboard Laporan Pajak.

    Urutan section mengikuti prioritas di PRODUCT.md: triase lebih dulu, lalu
    kelengkapan, baru nilai. Kontrol waktunya hanya satu, yaitu periode
    pelaporan, dan dipegang oleh Filters. Section lain hanya mendengarkan event
    'taxFiltersUpdated'.

    Kelas .tp-dash membawa token dashboard ini (lihat resources/css/filament/admin/theme.css)
    dan sengaja discope di sini supaya tidak bocor ke halaman admin lain.
--}}
<x-filament-panels::page>
    <div class="tp-dash space-y-4">

        @livewire(\App\Livewire\TaxReport\Dashboard\Filters::class)

        {{-- Apa yang genting: garis waktu tenggat bulan berjalan. --}}
        @livewire(\App\Livewire\TaxReport\Dashboard\DeadlineSpine::class)

        {{-- Apa yang harus dikerjakan: klien yang masih menyisakan kewajiban. --}}
        @livewire(\App\Livewire\TaxReport\Dashboard\TriageList::class)

        {{-- Konteks. Diberi jarak lebih lebar supaya terbaca sebagai lapisan
             berbeda, bukan lanjutan daftar triase. --}}
        <div class="grid grid-cols-1 gap-4 pt-2 xl:grid-cols-2">
            @livewire(\App\Livewire\TaxReport\Dashboard\PeriodProgress::class)
            @livewire(\App\Livewire\TaxReport\Dashboard\BalanceTrend::class)
        </div>

        {{-- Prioritas: klien terbesar menurut peredaran bruto pada masa terpilih,
             dan siapa yang menanganinya. Mengikuti filter periode seperti section
             lain di atas. --}}
        @livewire(\App\Livewire\TaxReport\Dashboard\TopClientsRevenue::class)
    </div>
</x-filament-panels::page>
