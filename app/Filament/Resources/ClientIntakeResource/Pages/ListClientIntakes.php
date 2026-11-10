<?php

namespace App\Filament\Resources\ClientIntakeResource\Pages;

use App\Filament\Resources\ClientIntakeResource;
use App\Models\ClientIntake;
use Filament\Resources\Components\Tab;
use Filament\Resources\Pages\ListRecords;

class ListClientIntakes extends ListRecords
{
    protected static string $resource = ClientIntakeResource::class;

    // Sama dengan tampilan bawaan ListRecords, ditambah keterangan tentang CM.
    protected static string $view = 'filament.resources.client-intake-resource.pages.list-client-intakes';

    public function getTabs(): array
    {
        $pending = ClientIntake::query()->pending()->count();

        return [
            'menunggu' => Tab::make('Menunggu dicek')
                ->badge($pending ?: null)
                ->badgeColor('warning')
                ->modifyQueryUsing(fn ($query) => $query->pending()),
            'selesai' => Tab::make('Selesai')
                ->modifyQueryUsing(fn ($query) => $query->completed()),
            'batal' => Tab::make('Batal di Client Management')
                ->modifyQueryUsing(fn ($query) => $query->withdrawn()),
        ];
    }

    public function getDefaultActiveTab(): string | int | null
    {
        return 'menunggu';
    }
}
