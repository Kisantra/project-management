<?php

namespace App\Filament\Client\Pages;

use Filament\Pages\Page;
use Filament\Support\Enums\MaxWidth;
use Illuminate\Contracts\Support\Htmlable;

/**
 * One section of the client portal (/klien). Each section is its own page, so
 * it has its own URL and only its Livewire component is mounted. The component
 * draws its own header, so Filament's page heading is switched off.
 */
abstract class PortalPage extends Page
{
    protected static string $view = 'filament.client.pages.portal';

    /** Livewire component rendered in the page body, e.g. "client.panel.overview-tab". */
    protected static string $component;

    public function getHeading(): string|Htmlable
    {
        return '';
    }

    public function getMaxContentWidth(): MaxWidth
    {
        return MaxWidth::Full;
    }

    protected function getViewData(): array
    {
        return ['portalComponent' => static::$component];
    }
}
