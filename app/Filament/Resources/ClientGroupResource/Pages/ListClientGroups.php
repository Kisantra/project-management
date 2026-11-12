<?php

namespace App\Filament\Resources\ClientGroupResource\Pages;

use App\Filament\Resources\ClientGroupResource;
use App\Filament\Resources\ClientResource;
use App\Models\Client;
use App\Models\ClientGroup;
use Filament\Actions;
use Filament\Resources\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\HtmlString;

/** Client groups list; the cards and filters live in the clients.group-list Livewire component. */
class ListClientGroups extends Page
{
    protected static string $resource = ClientGroupResource::class;

    protected static string $view = 'filament.resources.client-group-resource.pages.list-groups-panels';

    public function getTitle(): string
    {
        return 'Grup Client';
    }

    /** The page title already says it; Filament's default would read "Grup Client › Grup Client". */
    public function getBreadcrumbs(): array
    {
        return [];
    }

    /** "1 grup aktif · 3 klien tergabung · 166 klien belum punya grup →" */
    public function getSubheading(): string|Htmlable|null
    {
        $active = ClientGroup::where('status', 'active')->count();
        $grouped = Client::whereNotNull('group_id')->count();
        $ungrouped = Client::whereNull('group_id')->count();
        $n = fn (int $value) => number_format($value, 0, ',', '.');

        return new HtmlString(
            '<span class="kis-lead"><b>' . $n($active) . '</b> grup aktif · <b>' . $n($grouped) . '</b> klien tergabung'
            . ($ungrouped > 0
                ? ' · <a href="' . e(ClientResource::getUrl('index') . '?group=group') . '">' . $n($ungrouped) . ' klien belum punya grup →</a>'
                : '')
            . '</span>'
        );
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->url(fn () => ClientGroupResource::getUrl('create'))
                ->label('Tambah grup')
                ->icon('heroicon-m-plus'),
        ];
    }
}
