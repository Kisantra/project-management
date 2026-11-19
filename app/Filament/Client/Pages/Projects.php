<?php

namespace App\Filament\Client\Pages;

class Projects extends PortalPage
{
    protected static ?string $navigationIcon = 'heroicon-o-folder';

    protected static ?string $activeNavigationIcon = 'heroicon-s-folder';

    protected static ?string $navigationLabel = 'Proyek';

    protected static ?string $title = 'Proyek';

    protected static ?string $slug = 'proyek';

    protected static ?int $navigationSort = 2;

    protected static string $component = 'client.panel.proyek-tab';
}
