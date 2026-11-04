<?php

namespace App\Filament\Resources\ClientResource\Pages;

use App\Filament\Resources\ClientResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;
use Nben\FilamentRecordNav\Concerns\WithRecordNavigation;
use Nben\FilamentRecordNav\Actions\NextRecordAction;
use Nben\FilamentRecordNav\Actions\PreviousRecordAction;

class ViewClient extends ViewRecord
{
    protected static string $resource = ClientResource::class;

    protected static string $view = 'filament.resources.client-resource.pages.view-clients';

    public string $activeTab = 'identitas';

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
            PreviousRecordAction::make(),
            NextRecordAction::make(),
        ];
    }

    protected function getAllRelationManagers(): array
    {
        return [];
    }

    public function getTabs(): array
    {
        return [
            'identitas' => 'Identitas',
            'relasi' => 'Relasi',
            'projek' => 'Projek',
            'kontrak' => 'Kontrak',
            'dokumen' => 'Dokumen',
            'komunikasi' => 'Komunikasi',
            'compliance' => 'Compliance',
            'karyawan' => 'Karyawan',
            'tim' => 'Tim',
        ];
    }
}
