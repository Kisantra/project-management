{{--
    Bottom navigation on phones (below lg): the same five sections as the
    sidebar, one tap away. The sidebar drawer still opens from the topbar.
--}}
@php
    use App\Filament\Client\Pages;
    use App\Support\ClientPortal\PendingDocuments;

    $items = [
        [Pages\DashboardClient::class, 'Ringkasan', 'heroicon-o-home', 'heroicon-s-home', null],
        [Pages\Projects::class, 'Proyek', 'heroicon-o-folder', 'heroicon-s-folder', null],
        [Pages\Documents::class, 'Dokumen', 'heroicon-o-document-text', 'heroicon-s-document-text', PendingDocuments::forCurrentClient()],
        [Pages\TaxReports::class, 'Pajak', 'heroicon-o-document-chart-bar', 'heroicon-s-document-chart-bar', null],
        [Pages\Profile::class, 'Profil', 'heroicon-o-user-circle', 'heroicon-s-user-circle', null],
    ];
@endphp

<nav class="kp-tabbar lg:hidden" aria-label="Portal">
    @foreach ($items as [$page, $label, $icon, $activeIcon, $count])
        @php $active = request()->routeIs($page::getRouteName()); @endphp
        <a href="{{ $page::getUrl() }}" @class(['kp-tabbar__item', 'is-on' => $active]) @if ($active) aria-current="page" @endif>
            <span class="kp-tabbar__icon">
                <x-filament::icon :icon="$active ? $activeIcon : $icon" class="h-6 w-6" />
                @if ($count)
                    <span class="kp-tabbar__count">{{ $count }}</span>
                @endif
            </span>
            <span>{{ $label }}</span>
        </a>
    @endforeach
</nav>
