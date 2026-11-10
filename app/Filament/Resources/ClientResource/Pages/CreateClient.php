<?php

namespace App\Filament\Resources\ClientResource\Pages;

use App\Filament\Resources\ClientResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;
use Filament\Notifications\Notification;
use App\Services\Clients\DefaultClientTeam;

class CreateClient extends CreateRecord
{
    protected static string $resource = ClientResource::class;



    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function getCreatedNotification(): ?Notification
    {
        return Notification::make()
            ->success()
            ->title('Client berhasil ditambahkan')
            ->body("Client {$this->record->name} telah berhasil dibuat.");
    }
   

    public function getTitle(): string
    {
        return 'Tambah Client Baru';
    }

    protected function afterCreate(): void
    {
        // Same assignment the Client Management intake uses for new clients
        $assignedCount = DefaultClientTeam::assign($this->record);

        // Show a notification with the assignment details
        Notification::make()
            ->title('Client successfully created')
            ->body("All directors, project managers, and staff have been automatically assigned to this client. ({$assignedCount} users in total)")
            ->success()
            ->send();
    }
}