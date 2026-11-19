{{--
    Start of the topbar. Phones: app tile + compact company picker (the bottom
    bar replaces the drawer). Desktop: "Portal Klien › section" crumb.
--}}
@php
    use App\Filament\Client\Pages;

    $current = collect([Pages\DashboardClient::class, Pages\Projects::class, Pages\Documents::class, Pages\TaxReports::class, Pages\Profile::class])
        ->first(fn ($page) => request()->routeIs($page::getRouteName()));
@endphp

<div class="kp-topstart">
    <a href="{{ Pages\DashboardClient::getUrl() }}" class="kp-topstart__tile lg:hidden" aria-label="Ringkasan">
        <svg viewBox="0 0 160 160" aria-hidden="true"><rect width="160" height="160" rx="40" fill="#1790b5" /><path fill="#fff" d="M40 40.1A6.5 6.5 0 0 1 46.5 33.6L62.4 33.6A6.5 6.5 0 0 1 68.9 40.1L68.9 60.6A23.4 23.4 0 0 0 92.3 84L116 84A4 4 0 0 1 120 88L120 122.04A4.4 4.4 0 0 1 115.6 126.44L82.4 126.44A4.4 4.4 0 0 1 78 122.04L78 97A9.1 12.3 0 0 0 68.9 84.7L68.9 101.7A25.5 25.5 0 0 1 49.58 126.44L42.5 126.44A2.5 2.5 0 0 1 40 123.94Z" /><path fill="#10657f" d="M81.33 37.1A3.5 3.5 0 0 1 84.83 33.6L114.12 33.6Q118.12 33.6 118.06 37.6A37.5 37.5 0 0 1 86.33 72.17Q81.33 72.55 81.33 67.55Z" /></svg>
    </a>
    <div class="min-w-0 flex-1 lg:hidden">
        @livewire('client.panel.client-switcher', ['variant' => 'topbar'], key('client-switcher-topbar'))
    </div>
    @if ($current)
        <p class="kp-topstart__crumb hidden lg:flex">
            <span>Portal Klien</span>
            <x-heroicon-m-chevron-right class="h-4 w-4" />
            <b>{{ $current::getNavigationLabel() }}</b>
        </p>
    @endif
</div>
