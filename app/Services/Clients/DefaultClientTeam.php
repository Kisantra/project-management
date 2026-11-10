<?php

namespace App\Services\Clients;

use App\Models\Client;
use App\Models\User;
use App\Models\UserClient;

/**
 * Tim internal yang otomatis ditugaskan ke setiap klien baru (tabel
 * user_clients). Dasbor, daftar anggota proyek, dan notifikasi staf
 * membaca penugasan ini, jadi klien yang tidak ditugaskan tidak terlihat
 * oleh staf non-admin.
 */
class DefaultClientTeam
{
    public const ROLES = ['direktur', 'staff', 'project-manager', 'verificator'];

    /** @return int jumlah pengguna yang ditugaskan */
    public static function assign(Client $client): int
    {
        // whereHas, bukan User::role(): scope Spatie melempar exception bila
        // salah satu role belum dibuat (mis. di database baru).
        $userIds = User::query()
            ->whereHas('roles', fn ($q) => $q->whereIn('name', self::ROLES))
            ->pluck('id');

        foreach ($userIds as $userId) {
            UserClient::firstOrCreate([
                'user_id' => $userId,
                'client_id' => $client->id,
            ]);
        }

        return $userIds->count();
    }
}
