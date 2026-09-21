<?php

namespace App\Filament\Resources\RequestedDocumentTypeResource\Pages;

use App\Filament\Resources\RequestedDocumentTypeResource;
use Filament\Actions;
use Filament\Resources\Pages\ManageRecords;

class ManageRequestedDocumentTypes extends ManageRecords
{
    protected static string $resource = RequestedDocumentTypeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()->label('Jenis dokumen baru'),
        ];
    }
}
