<?php

namespace App\Filament\Client\Pages;

/** Ringkasan: the portal's home. The class keeps its old name; login redirects point here. */
class DashboardClient extends PortalPage
{
    protected static ?string $navigationIcon = 'heroicon-o-home';

    protected static ?string $activeNavigationIcon = 'heroicon-s-home';

    protected static ?string $navigationLabel = 'Ringkasan';

    protected static ?string $title = 'Ringkasan';

    protected static ?string $slug = 'ringkasan';

    protected static ?int $navigationSort = 1;

    protected static string $component = 'client.panel.overview-tab';
}
