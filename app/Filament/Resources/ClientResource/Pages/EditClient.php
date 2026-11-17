<?php

namespace App\Filament\Resources\ClientResource\Pages;

use App\Filament\Resources\ClientResource;
use App\Models\Client;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditClient extends EditRecord
{
    protected static string $resource = ClientResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make()
                ->before(function (Actions\DeleteAction $action, Client $record): void {
                    $projects = $record->projects()->count();

                    if ($projects === 0) {
                        return;
                    }

                    // projects.client_id has no ON DELETE rule, so deleting would fail
                    // with a foreign key error (Watchpost #16). Say why instead.
                    Notification::make()
                        ->danger()
                        ->title('Klien tidak bisa dihapus')
                        ->body("{$record->name} masih punya {$projects} proyek. Pindahkan atau hapus proyeknya dulu, atau nonaktifkan klien ini.")
                        ->send();

                    $action->cancel();
                }),
        ];
    }
}
