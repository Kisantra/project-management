<?php

namespace Tests\Feature\Access;

use App\Filament\Pages\Administration\RolesPermissions;
use App\Models\User;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RolesPermissionsPageTest extends AccessTestCase
{
    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = $this->makeUser('super-admin');
        $this->actingAs($this->admin);
    }

    public function test_only_super_admin_can_open_the_page(): void
    {
        $this->assertTrue(RolesPermissions::canAccess());

        $this->actingAs($this->makeUser('staff'));
        $this->assertFalse(RolesPermissions::canAccess());
    }

    public function test_create_role_normalises_the_name_and_rejects_duplicates(): void
    {
        Livewire::test(RolesPermissions::class)
            ->set('newRoleName', 'Admin Pajak Uji')
            ->call('createRole')
            ->assertHasNoErrors();

        $role = Role::findByName('admin-pajak-uji', 'web');
        $this->assertNotNull($role);

        Livewire::test(RolesPermissions::class)
            ->set('newRoleName', 'admin-pajak-uji')
            ->call('createRole')
            ->assertHasErrors(['newRoleName' => 'unique']);
    }

    public function test_saving_the_matrix_syncs_role_permissions(): void
    {
        $role = $this->makeRoleWith('uji-matriks', ['uji.a.*' => 'uji-a*']);
        $b = Permission::findOrCreate('uji.b.*', 'web');

        Livewire::test(RolesPermissions::class)
            ->call('selectRole', $role->id)
            ->set('selected', [(string) $b->id])
            ->call('savePermissions');

        $this->assertSame(['uji.b.*'], $role->fresh()->permissions->pluck('name')->all());
    }

    public function test_super_admin_role_permissions_are_never_written(): void
    {
        $super = Role::findByName('super-admin', 'web');
        $before = $super->permissions()->count();
        $permission = Permission::findOrCreate('uji.super.*', 'web');

        Livewire::test(RolesPermissions::class)
            ->call('selectRole', $super->id)
            ->set('selected', [(string) $permission->id])
            ->call('savePermissions');

        $this->assertSame($before, $super->permissions()->count());
    }

    public function test_members_can_be_added_and_removed(): void
    {
        $role = $this->makeRoleWith('uji-anggota', []);
        $user = $this->makeUser('staff');

        Livewire::test(RolesPermissions::class)
            ->call('selectRole', $role->id)
            ->call('addMember', $user->id);

        $this->assertTrue($user->fresh()->hasRole('uji-anggota'));
        $this->assertTrue($user->fresh()->hasRole('staff'), 'other roles are kept');

        Livewire::test(RolesPermissions::class)
            ->call('selectRole', $role->id)
            ->call('removeMember', $user->id);

        $this->assertFalse($user->fresh()->hasRole('uji-anggota'));
    }

    public function test_cannot_remove_own_super_admin_role(): void
    {
        $super = Role::findByName('super-admin', 'web');

        Livewire::test(RolesPermissions::class)
            ->call('selectRole', $super->id)
            ->call('removeMember', $this->admin->id);

        $this->assertTrue($this->admin->fresh()->hasRole('super-admin'));
    }

    public function test_system_roles_cannot_be_renamed(): void
    {
        $staff = Role::findOrCreate('staff', 'web');

        Livewire::test(RolesPermissions::class)
            ->call('selectRole', $staff->id)
            ->set('renameValue', 'staf-baru')
            ->call('renameRole');

        $this->assertSame('staff', $staff->fresh()->name);
    }

    public function test_delete_button_only_shows_for_empty_custom_roles(): void
    {
        $custom = $this->makeRoleWith('uji-kosong', []);

        Livewire::test(RolesPermissions::class)
            ->call('selectRole', Role::findOrCreate('direktur', 'web')->id)
            ->assertDontSee('Hapus peran')
            ->call('selectRole', $custom->id)
            ->assertSee('Hapus peran');
    }

    public function test_permission_is_created_with_cleaned_path_patterns(): void
    {
        Livewire::test(RolesPermissions::class)
            ->call('newPermission')
            ->set('permissionName', 'uji.invoices.*')
            ->set('permissionPaths', "/uji-invoices*/\n\nuji-invoices/create")
            ->call('savePermission')
            ->assertHasNoErrors();

        $permission = Permission::findByName('uji.invoices.*', 'web');
        $this->assertSame("uji-invoices*\nuji-invoices/create", $permission->http_path);
    }

    public function test_invalid_path_characters_are_rejected(): void
    {
        Livewire::test(RolesPermissions::class)
            ->call('newPermission')
            ->set('permissionName', 'uji.rusak')
            ->set('permissionPaths', 'klien baru')
            ->call('savePermission')
            ->assertHasErrors('permissionPaths');
    }

    public function test_access_test_explains_which_permission_matches(): void
    {
        $this->makeRoleWith('uji-penguji', ['uji.reports.*' => 'uji-reports*']);

        $result = Livewire::test(RolesPermissions::class)
            ->set('tab', 'test')
            ->set('testPath', '/uji-reports/12/edit')
            ->instance()
            ->accessTest
            ->firstWhere('role', 'uji-penguji');

        $this->assertTrue($result['allowed']);
        $this->assertSame('uji.reports.*', $result['reason']);
    }
}
