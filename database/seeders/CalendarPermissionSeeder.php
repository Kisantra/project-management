<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/** kalender.* permission, granted to every internal (non-client) role. */
class CalendarPermissionSeeder extends Seeder
{
    public function run(): void
    {
        $permission = Permission::firstOrCreate(['name' => 'kalender.*', 'guard_name' => 'web']);

        Role::query()
            ->where('name', '<>', 'client')
            ->get()
            ->each(fn (Role $role) => $role->givePermissionTo($permission));
    }
}
