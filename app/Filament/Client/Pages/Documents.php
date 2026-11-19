<?php

namespace App\Filament\Client\Pages;

use App\Support\ClientPortal\PendingDocuments;

class Documents extends PortalPage
{
    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    protected static ?string $activeNavigationIcon = 'heroicon-s-document-text';

    protected static ?string $navigationLabel = 'Dokumen';

    protected static ?string $title = 'Dokumen';

    protected static ?string $slug = 'dokumen';

    protected static ?int $navigationSort = 3;

    protected static string $component = 'client.panel.document-tab';

    /** Documents the selected company still has to upload. */
    public static function getNavigationBadge(): ?string
    {
        $count = PendingDocuments::forCurrentClient();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'Dokumen yang perlu diunggah';
    }
}
