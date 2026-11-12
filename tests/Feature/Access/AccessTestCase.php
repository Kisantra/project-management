<?php

namespace Tests\Feature\Access;

use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Base for access tests. Uses DatabaseTransactions like the other tests in
 * this repo, so run it against a migrated test database, not a working one.
 * The Spatie permission cache is flushed after each test so rolled-back roles
 * never linger in the shared cache.
 */
abstract class AccessTestCase extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        config(['broadcasting.default' => 'null']);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    protected function tearDown(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        parent::tearDown();
    }

    protected function makeUser(?string $role = null): User
    {
        $user = User::forceCreate([
            'name' => 'Uji Akses ' . uniqid(),
            'email' => 'uji-akses-' . uniqid() . '@example.test',
            'password' => bcrypt('rahasia'),
            'status' => 'active',
        ]);

        if ($role) {
            Role::findOrCreate($role, 'web');
            $user->assignRole($role);
        }

        return $user;
    }

    protected function makeRoleWith(string $name, array $permissions): Role
    {
        $role = Role::findOrCreate($name, 'web');

        foreach ($permissions as $permissionName => $paths) {
            $permission = Permission::findOrCreate($permissionName, 'web');
            $permission->update(['http_path' => $paths]);
            $role->givePermissionTo($permission);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return $role;
    }
}
