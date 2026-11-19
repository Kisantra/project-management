<?php

namespace App\Filament\Client\Pages;

class TaxReports extends PortalPage
{
    protected static ?string $navigationIcon = 'heroicon-o-document-chart-bar';

    protected static ?string $activeNavigationIcon = 'heroicon-s-document-chart-bar';

    protected static ?string $navigationLabel = 'Laporan Pajak';

    protected static ?string $title = 'Laporan Pajak';

    protected static ?string $slug = 'laporan-pajak';

    protected static ?int $navigationSort = 4;

    protected static string $component = 'client.panel.tax-report-tab';
}
