<?php

namespace App\Filament\Pages\Administration;

use App\Models\User;
use App\Support\Access\PanelPaths;
use App\Support\Access\PathPermission;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Roles & permissions manager (replaces the old access plugin screens).
 * Permissions are Spatie permissions with URL patterns in `http_path`;
 * see PathPermission for how they are matched.
 */
class RolesPermissions extends Page
{
    protected static string $view = 'filament.pages.administration.roles-permissions';

    protected static ?string $slug = 'peran-izin';

    protected static ?string $navigationGroup = 'Administration';

    protected static ?string $navigationLabel = 'Peran & Izin';

    protected static ?string $title = 'Peran & Izin';

    protected static ?string $navigationIcon = 'heroicon-o-shield-check';

    protected static ?int $navigationSort = 2;

    /** Role names checked by name in the code base; they cannot be renamed or deleted. */
    public const SYSTEM_ROLES = ['super-admin', 'client', 'staff', 'direktur', 'director', 'project-manager', 'verificator', 'manager'];

    private const ACTION_LABELS = [
        '*' => 'Semua akses',
        'viewAny' => 'Lihat daftar',
        'view' => 'Lihat detail',
        'create' => 'Buat',
        'update' => 'Ubah',
        'delete' => 'Hapus',
    ];

    private const MODULE_LABELS = [
        'account' => 'Akun',
        'account-representative' => 'Account Representative',
        'applications' => 'Aplikasi',
        'client-communication' => 'Komunikasi Klien',
        'clients' => 'Klien',
        'daily-task' => 'Tugas Harian',
        // No page any more: this permission shows the tax summary on the main Dashboard.
        'dashboard-tax-report' => 'Ringkasan pajak di Dashboard',
        'dashboard-tugas-hariam' => 'Dashboard Tugas Harian',
        'employees' => 'Pengguna klien',
        'laporan-pajak' => 'Laporan Pajak (lama)',
        'pengguna' => 'Pengguna',
        'pic' => 'PIC',
        'project-steps' => 'Langkah Proyek',
        'projects' => 'Proyek',
        'required-documents' => 'Dokumen Wajib',
        'sop' => 'SOP',
        'sop-legal-documents' => 'Dokumen Legal SOP',
        'suggestion' => 'Pengembangan Sistem',
        'tasks' => 'Tugas',
        'tax-chat' => 'Tax Chat',
        'tax-report-dashboard' => 'Dashboard Laporan Pajak (lama)',
        'tax-reports' => 'Laporan Pajak',
        'tugas-harian-list' => 'Daftar Tugas Harian',
        'user-log' => 'Log Pengguna',
    ];

    #[Url]
    public string $tab = 'roles';

    #[Url(as: 'peran')]
    public ?int $roleId = null;

    /** Permission ids ticked for the selected role (saved explicitly). */
    public array $selected = [];

    public string $newRoleName = '';

    public string $renameValue = '';

    public ?int $editingPermissionId = null;

    public bool $permissionFormOpen = false;

    public string $permissionName = '';

    public string $permissionPaths = '';

    public string $testPath = '';

    public static function canAccess(): bool
    {
        return PathPermission::isSuperAdmin(auth()->user());
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess();
    }

    public function mount(): void
    {
        if (! in_array($this->tab, ['roles', 'permissions', 'test'], true)) {
            $this->tab = 'roles';
        }

        $this->selectRole($this->roleId ?? $this->roles->firstWhere('name', '!=', PathPermission::SUPER_ADMIN_ROLE)?->id);
    }

    #[Computed]
    public function roles(): Collection
    {
        return Role::query()
            ->withCount(['permissions', 'users'])
            ->get()
            ->sortBy(fn (Role $role) => [$role->name === PathPermission::SUPER_ADMIN_ROLE ? 0 : 1, $role->name])
            ->values();
    }

    #[Computed]
    public function role(): ?Role
    {
        return $this->roleId ? Role::query()->withCount(['permissions', 'users'])->find($this->roleId) : null;
    }

    #[Computed]
    public function permissions(): Collection
    {
        return Permission::query()->with('roles:id,name')->orderBy('name')->get();
    }

    /**
     * Permissions grouped by module, each with a readable action label.
     *
     * @return Collection<string, array{label: string, items: Collection}>
     */
    #[Computed]
    public function permissionGroups(): Collection
    {
        $order = array_keys(self::ACTION_LABELS);

        return $this->permissions
            ->map(fn (Permission $permission) => [
                'id' => $permission->id,
                'name' => $permission->name,
                'module' => self::moduleOf($permission->name),
                'action' => self::actionOf($permission->name),
                'canonical' => self::isCanonical($permission->name),
                'paths' => PathPermission::patterns($permission->http_path),
                'roles' => $permission->roles->pluck('name')->all(),
            ])
            ->groupBy('module')
            ->map(function (Collection $items, string $module) use ($order) {
                $items = $items->sortBy(fn (array $item) => [array_search($item['action']['key'], $order, true) === false ? 99 : array_search($item['action']['key'], $order, true), $item['name']])->values();

                // One canonical permission per standard action becomes a matrix
                // cell; anything else (sub-modules, login/logout) is an extra.
                $columns = [];
                foreach ($items as $item) {
                    if ($item['canonical'] && in_array($item['action']['key'], $order, true) && ! isset($columns[$item['action']['key']])) {
                        $columns[$item['action']['key']] = $item;
                    }
                }
                $columnIds = collect($columns)->pluck('id');

                return [
                    'key' => $module,
                    'label' => self::MODULE_LABELS[$module] ?? Str::of($module)->replace(['-', '_'], ' ')->ucfirst()->toString(),
                    'items' => $items,
                    'columns' => $columns,
                    'extra' => $items->reject(fn (array $item) => $columnIds->contains($item['id']))->values(),
                ];
            })
            ->sortBy('label')
            ->values();
    }

    #[Computed]
    public function members(): Collection
    {
        if (! $this->role) {
            return collect();
        }

        return User::role($this->role->name)->with('roles:id,name')->orderBy('name')->get(['id', 'name', 'email']);
    }

    /** Staff accounts that can be added to the selected role. */
    #[Computed]
    public function candidates(): array
    {
        $memberIds = $this->members->pluck('id');

        return User::query()
            ->with('roles:id,name')
            ->orderBy('name')
            ->get(['id', 'name', 'email'])
            ->reject(fn (User $user) => $memberIds->contains($user->id))
            ->map(fn (User $user) => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'roles' => $user->roles->pluck('name')->implode(', '),
            ])
            ->values()
            ->all();
    }

    #[Computed]
    public function pathPreview(): Collection
    {
        return PanelPaths::matching(PathPermission::patterns($this->permissionPaths));
    }

    /** Which roles can open the tested path, and through which permissions. */
    #[Computed]
    public function accessTest(): ?Collection
    {
        $path = trim((string) parse_url(trim($this->testPath), PHP_URL_PATH), '/');

        if (trim($this->testPath) === '') {
            return null;
        }

        return $this->roles->map(function (Role $role) use ($path) {
            $isSuper = $role->name === PathPermission::SUPER_ADMIN_ROLE;
            $matches = $isSuper ? collect() : PathPermission::matching($role->permissions, $path);

            return [
                'role' => $role->name,
                'allowed' => $isSuper || PathPermission::isPublic($path) || $matches->isNotEmpty(),
                'byPermission' => ! $isSuper && ! PathPermission::isPublic($path) && $matches->isNotEmpty(),
                'reason' => match (true) {
                    $isSuper => 'Super-admin lolos semua pemeriksaan',
                    PathPermission::isPublic($path) => 'Halaman umum (dashboard, login, error)',
                    $matches->isNotEmpty() => $matches->pluck('name')->implode(', '),
                    default => 'Tidak ada izin yang cocok',
                },
            ];
        });
    }

    public function isSystemRole(?Role $role): bool
    {
        return $role !== null && in_array($role->name, self::SYSTEM_ROLES, true);
    }

    public function setTab(string $tab): void
    {
        $this->tab = in_array($tab, ['roles', 'permissions', 'test'], true) ? $tab : 'roles';
    }

    public function selectRole(?int $roleId): void
    {
        $this->roleId = $roleId;
        unset($this->role, $this->members, $this->candidates);
        $this->renameValue = $this->role?->name ?? '';
        $this->selected = $this->role
            ? $this->role->permissions()->pluck('id')->map(fn ($id) => (string) $id)->all()
            : [];
    }

    public function discardPermissions(): void
    {
        $this->selectRole($this->roleId);
    }

    public function savePermissions(): void
    {
        $role = $this->role;

        if (! $role || $role->name === PathPermission::SUPER_ADMIN_ROLE) {
            return;
        }

        $before = $role->permissions()->pluck('name');
        $permissions = Permission::query()->whereIn('id', array_map('intval', $this->selected))->get();
        $role->syncPermissions($permissions);
        $this->flushPermissionCache();

        $added = $permissions->pluck('name')->diff($before)->values();
        $removed = $before->diff($permissions->pluck('name'))->values();

        $this->log($role, "Izin peran {$role->name} diubah: +{$added->count()} / -{$removed->count()}", [
            'added' => $added->all(),
            'removed' => $removed->all(),
        ]);

        $this->selectRole($role->id);
        unset($this->roles, $this->permissions, $this->permissionGroups);

        Notification::make()
            ->title("Izin peran {$role->name} disimpan")
            ->body("{$added->count()} ditambahkan, {$removed->count()} dicabut.")
            ->success()
            ->send();
    }

    public function createRole(): void
    {
        $this->newRoleName = Str::of($this->newRoleName)->trim()->lower()->replace(' ', '-')->toString();

        $this->validate([
            'newRoleName' => ['required', 'max:50', 'regex:/^[a-z0-9-]+$/', Rule::unique('roles', 'name')->where('guard_name', 'web')],
        ], [
            'newRoleName.required' => 'Nama peran wajib diisi.',
            'newRoleName.regex' => 'Pakai huruf kecil, angka, dan tanda hubung saja.',
            'newRoleName.unique' => 'Peran dengan nama ini sudah ada.',
        ]);

        $role = Role::create(['name' => $this->newRoleName, 'guard_name' => 'web']);
        $this->flushPermissionCache();
        $this->log($role, "Peran {$role->name} dibuat");

        $this->newRoleName = '';
        unset($this->roles);
        $this->selectRole($role->id);

        Notification::make()->title("Peran {$role->name} dibuat")->body('Centang izin yang dibutuhkan, lalu simpan.')->success()->send();
    }

    public function renameRole(): void
    {
        $role = $this->role;

        if (! $role || $this->isSystemRole($role)) {
            return;
        }

        $this->renameValue = Str::of($this->renameValue)->trim()->lower()->replace(' ', '-')->toString();

        $this->validate([
            'renameValue' => ['required', 'max:50', 'regex:/^[a-z0-9-]+$/', Rule::unique('roles', 'name')->where('guard_name', 'web')->ignore($role->id)],
        ], [
            'renameValue.required' => 'Nama peran wajib diisi.',
            'renameValue.regex' => 'Pakai huruf kecil, angka, dan tanda hubung saja.',
            'renameValue.unique' => 'Peran dengan nama ini sudah ada.',
        ]);

        $old = $role->name;
        $role->update(['name' => $this->renameValue]);
        $this->flushPermissionCache();
        $this->log($role, "Peran {$old} diganti nama menjadi {$role->name}");

        unset($this->roles, $this->role);
        Notification::make()->title('Nama peran diperbarui')->success()->send();
    }

    public function deleteRoleAction(): Action
    {
        return Action::make('deleteRole')
            ->label('Hapus peran')
            ->icon('heroicon-m-trash')
            ->outlined()
            ->size('sm')
            ->color('gray')
            ->requiresConfirmation()
            ->modalIcon('heroicon-o-trash')
            ->modalIconColor('danger')
            ->modalSubmitAction(fn ($action) => $action->color('danger'))
            ->modalHeading(fn () => 'Hapus peran ' . ($this->role?->name ?? '') . '?')
            ->modalDescription('Peran ini tidak punya anggota. Izin yang terpasang ikut dilepas; izinnya sendiri tidak terhapus.')
            ->modalSubmitActionLabel('Hapus peran')
            ->visible(fn () => $this->role && ! $this->isSystemRole($this->role) && $this->role->users_count === 0)
            ->action(function () {
                $role = $this->role;
                if (! $role || $this->isSystemRole($role) || $role->users()->exists()) {
                    return;
                }

                $name = $role->name;
                $this->log($role, "Peran {$name} dihapus");
                $role->delete();
                $this->flushPermissionCache();

                unset($this->roles);
                $this->selectRole($this->roles->firstWhere('name', '!=', PathPermission::SUPER_ADMIN_ROLE)?->id);
                Notification::make()->title("Peran {$name} dihapus")->success()->send();
            });
    }

    public function addMember(int $userId): void
    {
        $role = $this->role;
        $user = User::find($userId);

        if (! $role || ! $user) {
            return;
        }

        $user->assignRole($role);
        $this->flushPermissionCache();
        $this->log($role, "{$user->name} ditambahkan ke peran {$role->name}", ['user_id' => $user->id]);

        unset($this->members, $this->candidates, $this->roles, $this->role);
        Notification::make()->title("{$user->name} ditambahkan ke {$role->name}")->success()->send();
    }

    public function removeMember(int $userId): void
    {
        $role = $this->role;
        $user = User::find($userId);

        if (! $role || ! $user) {
            return;
        }

        if ($role->name === PathPermission::SUPER_ADMIN_ROLE) {
            if ($user->is(auth()->user())) {
                Notification::make()->title('Tidak bisa melepas peran super-admin dari akun sendiri')->danger()->send();

                return;
            }

            if ($this->members->count() <= 1) {
                Notification::make()->title('Harus ada minimal satu super-admin')->danger()->send();

                return;
            }
        }

        $user->removeRole($role);
        $this->flushPermissionCache();
        $this->log($role, "{$user->name} dikeluarkan dari peran {$role->name}", ['user_id' => $user->id]);

        unset($this->members, $this->candidates, $this->roles, $this->role);
        Notification::make()->title("{$user->name} dikeluarkan dari {$role->name}")->success()->send();
    }

    public function newPermission(): void
    {
        $this->resetErrorBag();
        $this->editingPermissionId = null;
        $this->permissionName = '';
        $this->permissionPaths = '';
        $this->permissionFormOpen = true;
    }

    public function editPermission(int $permissionId): void
    {
        $permission = Permission::find($permissionId);

        if (! $permission) {
            return;
        }

        $this->resetErrorBag();
        $this->editingPermissionId = $permission->id;
        $this->permissionName = $permission->name;
        $this->permissionPaths = implode("\n", PathPermission::patterns($permission->http_path));
        $this->permissionFormOpen = true;
    }

    public function closePermissionForm(): void
    {
        $this->permissionFormOpen = false;
        $this->editingPermissionId = null;
        $this->resetErrorBag();
    }

    public function savePermission(): void
    {
        $this->permissionName = trim($this->permissionName);
        $patterns = PathPermission::patterns($this->permissionPaths);

        $this->validate([
            'permissionName' => ['required', 'max:125', 'regex:/^[A-Za-z0-9._\-\/*]+$/', Rule::unique('permissions', 'name')->where('guard_name', 'web')->ignore($this->editingPermissionId)],
        ], [
            'permissionName.required' => 'Nama izin wajib diisi.',
            'permissionName.regex' => 'Nama izin tidak boleh berisi spasi.',
            'permissionName.unique' => 'Izin dengan nama ini sudah ada.',
        ]);

        foreach ($patterns as $pattern) {
            if (! preg_match('/^[A-Za-z0-9._\-\/*]+$/', $pattern)) {
                throw ValidationException::withMessages(['permissionPaths' => "Pola \"{$pattern}\" berisi karakter yang tidak didukung."]);
            }
        }

        $attributes = ['name' => $this->permissionName, 'guard_name' => 'web', 'http_path' => implode("\n", $patterns) ?: null];

        if ($this->editingPermissionId) {
            $permission = Permission::findOrFail($this->editingPermissionId);
            $permission->update($attributes);
            $this->log($permission, "Izin {$permission->name} diubah", ['http_path' => $patterns]);
            $title = "Izin {$permission->name} diperbarui";
        } else {
            $permission = Permission::create($attributes);
            $this->log($permission, "Izin {$permission->name} dibuat", ['http_path' => $patterns]);
            $title = "Izin {$permission->name} dibuat";
        }

        $this->flushPermissionCache();
        unset($this->permissions, $this->permissionGroups);
        $this->closePermissionForm();

        Notification::make()->title($title)->success()->send();
    }

    public function deletePermissionAction(): Action
    {
        return Action::make('deletePermission')
            ->label('Hapus')
            ->icon('heroicon-m-trash')
            ->iconButton()
            ->color('gray')
            ->requiresConfirmation()
            ->modalIcon('heroicon-o-trash')
            ->modalIconColor('danger')
            ->modalSubmitAction(fn ($action) => $action->color('danger'))
            ->modalHeading(fn (array $arguments) => 'Hapus izin ' . (Permission::find($arguments['permission'] ?? 0)?->name ?? '') . '?')
            ->modalDescription(function (array $arguments) {
                $roles = Permission::find($arguments['permission'] ?? 0)?->roles()->pluck('name') ?? collect();

                return $roles->isEmpty()
                    ? 'Izin ini tidak dipakai peran mana pun.'
                    : 'Izin ini dicabut dari peran: ' . $roles->implode(', ') . '. Pengguna di peran itu langsung kehilangan akses ke halaman yang cocok.';
            })
            ->modalSubmitActionLabel('Hapus izin')
            ->action(function (array $arguments) {
                $permission = Permission::find($arguments['permission'] ?? 0);
                if (! $permission) {
                    return;
                }

                $name = $permission->name;
                $this->log($permission, "Izin {$name} dihapus", ['roles' => $permission->roles()->pluck('name')->all()]);
                $permission->delete();
                $this->flushPermissionCache();

                if ($this->editingPermissionId === $permission->id) {
                    $this->closePermissionForm();
                }

                unset($this->permissions, $this->permissionGroups, $this->roles);
                $this->selectRole($this->roleId);
                Notification::make()->title("Izin {$name} dihapus")->success()->send();
            });
    }

    public static function moduleOf(string $name): string
    {
        if (in_array($name, ['login', 'logout'], true)) {
            return 'account';
        }

        $base = Str::contains($name, '.') ? Str::before($name, '.') : rtrim($name, '*');

        return Str::of($base)->before('/')->trim('-')->toString() ?: $name;
    }

    /** @return array{key: string, label: string} */
    public static function actionOf(string $name): array
    {
        if (in_array($name, ['login', 'logout'], true)) {
            return ['key' => $name, 'label' => $name === 'login' ? 'Masuk' : 'Keluar'];
        }

        if (! Str::contains($name, '.')) {
            return ['key' => '*', 'label' => self::ACTION_LABELS['*']];
        }

        $action = Str::afterLast($name, '.');
        $label = self::ACTION_LABELS[$action] ?? $action;

        // Sub-module names: "laporan-pajak/invoices.*", "daily-task.dashboard.*".
        $base = Str::beforeLast($name, '.');
        $sub = Str::contains($base, '/') ? Str::after($base, '/') : (Str::contains($base, '.') ? Str::after($base, '.') : null);

        if ($sub !== null) {
            $label .= ' · ' . str_replace(['-', '.'], ' ', $sub);
        }

        return ['key' => $action, 'label' => $label];
    }

    /** `module.action` or a bare wildcard name; not a sub-module permission. */
    public static function isCanonical(string $name): bool
    {
        if (in_array($name, ['login', 'logout'], true)) {
            return false;
        }

        if (! Str::contains($name, '.')) {
            return true;
        }

        return substr_count($name, '.') === 1 && ! Str::contains(Str::before($name, '.'), '/');
    }

    private function flushPermissionCache(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    private function log($subject, string $description, array $properties = []): void
    {
        activity('access')
            ->causedBy(auth()->user())
            ->performedOn($subject)
            ->withProperties($properties)
            ->log($description . ' oleh ' . (auth()->user()?->name ?? 'sistem'));
    }
}
