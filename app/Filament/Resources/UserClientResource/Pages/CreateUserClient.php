<?php

namespace App\Filament\Resources\UserClientResource\Pages;

use App\Filament\Resources\UserClientResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use App\Models\User;
use App\Models\UserClient;
use Illuminate\Support\Facades\Hash;
use Filament\Notifications\Notification;

class CreateUserClient extends CreateRecord
{
    protected static string $resource = UserClientResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        // Create the user first
        $user = User::create([
            'name' => $data['user']['name'],
            'email' => $data['user']['email'],
            'password' => Hash::make($data['user']['password']),
            'department_id' => $data['user']['department_id'] ?? null,
            'position' => $data['user']['position'] ?? null,
            'job_title' => filled($data['user']['job_title'] ?? null) ? trim($data['user']['job_title']) : null,
            'status' => $data['user']['status'] ?? 'active',
            'avatar_path' => $data['user']['avatar_path'] ?? null,
            'avatar_url' => ! empty($data['user']['avatar_path']) ? 'storage/' . $data['user']['avatar_path'] : ($data['user']['avatar_url'] ?? null),
            'signature_path' => $data['user']['signature_path'] ?? null,
        ]);

        $firstUserClient = null;

        // Create multiple UserClient records
        foreach ($data['client_ids'] as $clientId) {
            $userClient = UserClient::create([
                'user_id' => $user->id,
                'client_id' => $clientId,
            ]);

            if (!$firstUserClient) {
                $firstUserClient = $userClient;
            }
        }

        return $firstUserClient;
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }


}
