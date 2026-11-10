<?php

namespace Tests\Feature\ClientIntake;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Dasar uji serah terima Client Management. Memakai DatabaseTransactions seperti
 * tes lain di repo ini, jadi butuh database yang sudah dimigrasi (termasuk
 * client_intakes). Jalankan terhadap database uji, bukan database kerja:
 *
 *   DB_DATABASE=project_management_test php artisan migrate --force
 *   DB_DATABASE=project_management_test ./vendor/bin/phpunit tests/Feature/ClientIntake
 */
abstract class ClientIntakeTestCase extends TestCase
{
    use DatabaseTransactions;

    protected const TOKEN = 'rahasia-uji-cm';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.client_management.url' => 'https://cm.test',
            'services.client_management.token' => self::TOKEN,
            'services.client_management.timeout' => 5,
            // Tanpa Reverb di lingkungan uji
            'broadcasting.default' => 'null',
        ]);

        foreach (['super-admin', 'direktur', 'project-manager', 'staff', 'verificator', 'client'] as $role) {
            app(PermissionRegistrar::class)->getRoleClass()::findOrCreate($role, 'web');
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    protected function makeUser(string $role, array $attributes = []): User
    {
        $user = User::forceCreate($attributes + [
            'name' => 'Uji ' . ucfirst($role),
            'email' => 'uji-intake-' . $role . '-' . uniqid() . '@example.test',
            'password' => bcrypt('rahasia'),
            'status' => 'active',
        ]);

        $user->assignRole($role);

        return $user;
    }

    protected function intakePayload(array $overrides = []): array
    {
        return array_replace_recursive([
            'cm_lead_id' => random_int(100000, 999999),
            'name' => 'PT Uji Sinar Rejeki ' . uniqid(),
            'entity' => 'PT',
            'client_type' => 'Badan',
            'npwp' => '9' . random_int(100000000000000, 999999999999999),
            'email' => 'finance-' . uniqid() . '@uji-sinar.test',
            'address' => 'Jl. Panglima Polim 18, Jakarta Selatan',
            'contact' => [
                'name' => 'Budi Hartono',
                'position' => 'Direktur Keuangan',
                'phone' => '0812-3456-7810',
                'email' => null,
            ],
            'service' => 'PPh Badan',
            'value' => 12500000,
            'marketing_owner' => 'Dimas',
            'deal_at' => '2026-11-10',
            'lead_url' => 'https://cm.test/leads/12',
        ], $overrides);
    }

    /** Klien yang sudah ada, dibuat tanpa event (Client::created butuh user login). */
    protected function existingClient(array $attributes): \App\Models\Client
    {
        return \App\Models\Client::withoutEvents(fn () => \App\Models\Client::forceCreate($attributes + ['client_type' => 'Badan']));
    }

    protected function grantClientAccess(User $user): User
    {
        return $user->givePermissionTo(app(PermissionRegistrar::class)->getPermissionClass()::findOrCreate('clients.*', 'web'));
    }

    protected function postIntake(array $payload, ?string $token = self::TOKEN)
    {
        return $this->postJson('/api/v1/intake/clients', $payload, $token ? ['Authorization' => 'Bearer ' . $token] : []);
    }
}
