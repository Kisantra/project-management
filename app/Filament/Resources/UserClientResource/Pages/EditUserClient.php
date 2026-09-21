<?php

namespace App\Filament\Resources\UserClientResource\Pages;

use App\Filament\Resources\UserClientResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use App\Models\User;
use App\Models\UserClient;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Filament\Notifications\Notification;

class EditUserClient extends EditRecord
{
    protected static string $resource = UserClientResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make()
                ->label('Hapus')
                ->modalHeading('Hapus Karyawan')
                ->modalDescription('Apakah Anda yakin ingin menghapus karyawan ini?')
                ->successNotificationTitle('Karyawan Dihapus'),
        ];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        // Load user data
        $user = User::find($this->record->user_id);
        
        if ($user) {
            // Nested array, not dot keys: Filament fills `user.name` fields from $data['user']['name'].
            $data['user'] = [
                'name'          => $user->name,
                'email'         => $user->email,
                'department_id' => $user->department_id,
                'position'      => $user->position,
                'job_title'     => $user->job_title,
                'status'        => $user->status,
                'avatar_path'   => $user->avatar_path,
                // avatar_url mirrors the upload ('storage/...') when a file exists; only an external URL belongs in the field.
                'avatar_url'    => $user->avatar_path ? null : $user->avatar_url,
                // Only files on the public disk can be shown in the uploader; hand-placed
                // files under public/images are kept as-is unless replaced.
                'signature_path' => $user->signature_path && Storage::disk('public')->exists($user->signature_path)
                    ? $user->signature_path
                    : null,
            ];
        }

        // Get all client IDs for this user
        $data['client_ids'] = UserClient::where('user_id', $this->record->user_id)
            ->pluck('client_id')
            ->toArray();

        return $data;
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        // Update user details
        $updateData = [
            'name' => $data['user']['name'],
            'email' => $data['user']['email'],
            'department_id' => $data['user']['department_id'] ?? null,
            'position' => $data['user']['position'] ?? null,
            'job_title' => filled($data['user']['job_title'] ?? null) ? trim($data['user']['job_title']) : null,
            'status' => $data['user']['status'] ?? 'active',
        ];

        // Handle avatar_path (only delete the old file when a different one was uploaded)
        if (!empty($data['user']['avatar_path'])) {
            if ($data['user']['avatar_path'] !== $record->user->avatar_path) {
                $record->user->deleteOldAvatar();
            }
            $updateData['avatar_path'] = $data['user']['avatar_path'];
            $updateData['avatar_url'] = 'storage/' . $data['user']['avatar_path'];
        }

        // Handle avatar_url only if no file uploaded
        if (!empty($data['user']['avatar_url']) && empty($data['user']['avatar_path'])) {
            $updateData['avatar_url'] = $data['user']['avatar_url'];
            $updateData['avatar_path'] = null;
        }

        // Handle signature: a new upload replaces; a cleared uploader removes a disk-stored file.
        $newSignature = $data['user']['signature_path'] ?? null;
        if ($newSignature && $newSignature !== $record->user->signature_path) {
            $record->user->deleteOldSignature();
            $updateData['signature_path'] = $newSignature;
        } elseif (! $newSignature && $record->user->signature_path && Storage::disk('public')->exists($record->user->signature_path)) {
            $record->user->deleteOldSignature();
            $updateData['signature_path'] = null;
        }

        // Update password if provided
        if (!empty($data['user']['password'])) {
            $updateData['password'] = Hash::make($data['user']['password']);
        }

        $record->user->update($updateData);

        // Delete existing client relationships
        UserClient::where('user_id', $record->user_id)->delete();

        // Create new client relationships
        if (isset($data['client_ids']) && is_array($data['client_ids'])) {
            foreach ($data['client_ids'] as $clientId) {
                UserClient::create([
                    'user_id' => $record->user_id,
                    'client_id' => $clientId,
                ]);
            }
        }

        return $record;
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function getSavedNotification(): ?Notification
    {
        return Notification::make()
            ->success()
            ->title('Karyawan Diperbarui')
            ->body('Data karyawan berhasil diperbarui.');
    }
}