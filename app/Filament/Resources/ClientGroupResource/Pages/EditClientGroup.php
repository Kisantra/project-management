<?php

namespace App\Filament\Resources\ClientGroupResource\Pages;

use App\Filament\Resources\ClientGroupResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditClientGroup extends EditRecord
{
    protected static string $resource = ClientGroupResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make()
                ->label('Lihat grup')
                ->color('gray'),
            Actions\DeleteAction::make()
                ->label('Hapus grup')
                ->modalHeading('Hapus grup?')
                ->modalDescription(fn () => ($n = $this->record->clients()->count()) > 0
                    ? "{$n} client di grup ini tidak akan punya grup lagi. Data client tidak dihapus."
                    : 'Grup ini belum punya client.'),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->getRecord()]);
    }
}
