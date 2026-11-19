<?php

namespace App\Filament\Client\Pages;

class Profile extends PortalPage
{
    protected static ?string $navigationIcon = 'heroicon-o-user-circle';

    protected static ?string $activeNavigationIcon = 'heroicon-s-user-circle';

    protected static ?string $navigationLabel = 'Profil';

    protected static ?string $title = 'Profil';

    protected static ?string $slug = 'profil';

    protected static ?int $navigationSort = 5;

    protected static string $component = 'client.panel.profile-tab';
}
