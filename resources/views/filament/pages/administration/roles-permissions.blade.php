@php
    use App\Support\Access\PathPermission;

    $role = $this->role;
    $isSuper = $role?->name === PathPermission::SUPER_ADMIN_ROLE;
    $isSystem = $this->isSystemRole($role);
    $totalPermissions = $this->permissions->count();
    $card = 'rounded-[14px] border border-gray-200 bg-white dark:border-white/10 dark:bg-gray-900';
    $input = 'block w-full rounded-[11px] border-0 bg-white px-3 py-2 text-sm text-gray-950 shadow-none ring-1 ring-inset ring-gray-300 placeholder:text-gray-400 focus:ring-2 focus:ring-primary-600 dark:bg-white/5 dark:text-white dark:ring-white/10 dark:placeholder:text-gray-500';
    $tabs = [
        'roles' => ['Peran', $this->roles->count()],
        'permissions' => ['Izin', $totalPermissions],
        'test' => ['Uji akses', null],
    ];
@endphp

<x-filament-panels::page>
    <div class="space-y-5">
        <div class="flex flex-wrap items-center gap-3">
            <p class="text-sm font-medium text-gray-500 dark:text-gray-400">
                Peran menentukan halaman yang boleh dibuka staf. Setiap izin berisi pola alamat halaman; pengguna bisa membuka halaman bila salah satu izin perannya cocok.
            </p>
        </div>

        <div class="inline-flex gap-0.5 rounded-[14px] border border-gray-200 bg-gray-100 p-1 dark:border-white/10 dark:bg-white/5" role="tablist" aria-label="Bagian">
            @foreach ($tabs as $key => [$label, $count])
                <button
                    type="button"
                    role="tab"
                    aria-selected="{{ $tab === $key ? 'true' : 'false' }}"
                    wire:click="setTab('{{ $key }}')"
                    @class([
                        'inline-flex h-8 items-center gap-2 rounded-[9px] px-3 text-[13px] font-bold transition focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary-600',
                        'bg-white text-primary-700 shadow-sm ring-1 ring-primary-700/20 dark:bg-gray-800 dark:text-primary-300' => $tab === $key,
                        'text-gray-600 hover:bg-white/70 hover:text-gray-950 dark:text-gray-300 dark:hover:bg-white/5 dark:hover:text-white' => $tab !== $key,
                    ])
                >
                    {{ $label }}
                    @if (! is_null($count))
                        <span class="text-[11px] font-extrabold tabular-nums {{ $tab === $key ? 'text-primary-700 dark:text-primary-300' : 'text-gray-500 dark:text-gray-400' }}">{{ $count }}</span>
                    @endif
                </button>
            @endforeach
        </div>

        {{-- ============================== PERAN ============================== --}}
        @if ($tab === 'roles')
            <div
                class="grid items-start gap-5 lg:grid-cols-[17.5rem_minmax(0,1fr)]"
                wire:key="roles-{{ $roleId }}-{{ md5(json_encode($selected)) }}"
                x-data="{
                    original: @js(array_values($selected)),
                    q: '',
                    get dirty() {
                        const now = new Set(($wire.selected ?? []).map(String))
                        const was = new Set(this.original.map(String))
                        let n = 0
                        now.forEach((id) => { if (! was.has(id)) n++ })
                        was.forEach((id) => { if (! now.has(id)) n++ })
                        return n
                    },
                    matches(text) {
                        return text.toLowerCase().includes(this.q.trim().toLowerCase())
                    },
                    isOn(id) {
                        return ($wire.selected ?? []).map(String).includes(String(id))
                    },
                    toggleGroup(ids) {
                        const set = new Set(($wire.selected ?? []).map(String))
                        const all = ids.every((id) => set.has(String(id)))
                        ids.forEach((id) => all ? set.delete(String(id)) : set.add(String(id)))
                        $wire.$set('selected', [...set], false)
                    },
                    countOn(ids) {
                        const set = new Set(($wire.selected ?? []).map(String))
                        return ids.filter((id) => set.has(String(id))).length
                    },
                    go(id) {
                        if (this.dirty > 0 && ! confirm('Perubahan izin belum disimpan. Buang perubahan dan pindah peran?')) return
                        $wire.selectRole(id)
                    },
                }"
            >
                {{-- Daftar peran --}}
                <div class="{{ $card }} overflow-hidden lg:sticky lg:top-20">
                    <div class="border-b border-gray-200 px-4 py-3 dark:border-white/10">
                        <h2 class="text-[15px] font-extrabold tracking-tight text-gray-950 dark:text-white">Peran</h2>
                        <p class="text-xs font-semibold text-gray-500 dark:text-gray-400">{{ $this->roles->count() }} peran · {{ $this->roles->sum('users_count') }} penugasan</p>
                    </div>

                    <ul class="divide-y divide-gray-100 dark:divide-white/5" role="listbox" aria-label="Pilih peran">
                        @foreach ($this->roles as $item)
                            @php $active = $item->id === $roleId; @endphp
                            <li>
                                <button
                                    type="button"
                                    role="option"
                                    aria-selected="{{ $active ? 'true' : 'false' }}"
                                    x-on:click="go({{ $item->id }})"
                                    @class([
                                        'flex w-full items-center gap-3 px-4 py-3 text-left transition focus-visible:outline focus-visible:outline-2 focus-visible:-outline-offset-2 focus-visible:outline-primary-600',
                                        'bg-primary-50 dark:bg-primary-500/10' => $active,
                                        'hover:bg-gray-50 dark:hover:bg-white/5' => ! $active,
                                    ])
                                >
                                    <span @class([
                                        'grid h-8 w-8 shrink-0 place-items-center rounded-[9px] text-[11px] font-extrabold uppercase',
                                        'bg-gray-950 text-white dark:bg-white dark:text-gray-950' => $item->name === PathPermission::SUPER_ADMIN_ROLE,
                                        'bg-primary-600 text-white' => $active && $item->name !== PathPermission::SUPER_ADMIN_ROLE,
                                        'bg-gray-100 text-gray-600 dark:bg-white/10 dark:text-gray-300' => ! $active && $item->name !== PathPermission::SUPER_ADMIN_ROLE,
                                    ])>{{ \Illuminate\Support\Str::of($item->name)->explode('-')->map(fn ($w) => mb_substr($w, 0, 1))->take(2)->implode('') }}</span>
                                    <span class="min-w-0 flex-1">
                                        <span @class(['block truncate text-[13.5px] font-bold', 'text-primary-700 dark:text-primary-300' => $active, 'text-gray-950 dark:text-white' => ! $active])>{{ $item->name }}</span>
                                        <span class="block text-xs font-semibold text-gray-500 dark:text-gray-400">
                                            {{ $item->users_count }} anggota ·
                                            {{ $item->name === PathPermission::SUPER_ADMIN_ROLE ? 'semua akses' : $item->permissions_count . ' izin' }}
                                        </span>
                                    </span>
                                    @if (in_array($item->name, \App\Filament\Pages\Administration\RolesPermissions::SYSTEM_ROLES, true))
                                        <x-kis.badge tone="neutral" size="sm" title="Dipakai di kode; tidak bisa diganti nama atau dihapus">Sistem</x-kis.badge>
                                    @endif
                                </button>
                            </li>
                        @endforeach
                    </ul>

                    <form wire:submit="createRole" class="border-t border-gray-200 p-3 dark:border-white/10">
                        <label for="new-role" class="mb-1.5 block text-xs font-bold text-gray-600 dark:text-gray-300">Peran baru</label>
                        <div class="flex gap-2">
                            <input id="new-role" type="text" wire:model="newRoleName" placeholder="mis. admin-pajak" class="{{ $input }}" autocomplete="off">
                            <x-filament::button type="submit" size="sm" icon="heroicon-m-plus" wire:target="createRole">Tambah</x-filament::button>
                        </div>
                        @error('newRoleName') <p class="mt-1.5 text-xs font-semibold text-danger-600 dark:text-danger-400">{{ $message }}</p> @enderror
                    </form>
                </div>

                {{-- Detail peran --}}
                <div class="min-w-0 space-y-5">
                    @if (! $role)
                        <div class="{{ $card }} px-6 py-12 text-center">
                            <p class="text-sm font-bold text-gray-950 dark:text-white">Pilih peran di sebelah kiri</p>
                            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">atau buat peran baru untuk mulai mengatur izinnya.</p>
                        </div>
                    @else
                        <div class="{{ $card }} px-5 py-4">
                            <div class="flex flex-wrap items-start gap-4">
                                <div class="min-w-0 flex-1">
                                    @if ($isSystem)
                                        <h2 class="text-xl font-extrabold tracking-tight text-gray-950 dark:text-white">{{ $role->name }}</h2>
                                    @else
                                        <form wire:submit="renameRole" class="flex max-w-md items-center gap-2">
                                            <label for="rename-role" class="sr-only">Nama peran</label>
                                            <input id="rename-role" type="text" wire:model="renameValue" class="{{ $input }} text-base font-extrabold">
                                            <x-filament::button type="submit" size="sm" color="gray" wire:target="renameRole">Simpan nama</x-filament::button>
                                        </form>
                                        @error('renameValue') <p class="mt-1.5 text-xs font-semibold text-danger-600 dark:text-danger-400">{{ $message }}</p> @enderror
                                    @endif
                                    <div class="mt-2 flex flex-wrap items-center gap-2">
                                        <x-kis.badge tone="neutral">{{ $role->users_count }} anggota</x-kis.badge>
                                        <x-kis.badge tone="blue">{{ $isSuper ? 'Semua akses' : $role->permissions_count . ' dari ' . $totalPermissions . ' izin' }}</x-kis.badge>
                                        @if ($isSystem)
                                            <x-kis.badge tone="outline">Peran sistem</x-kis.badge>
                                        @endif
                                    </div>
                                </div>
                                @if ($this->deleteRoleAction->isVisible())
                                    <div class="flex items-center gap-2">
                                        {{ $this->deleteRoleAction }}
                                    </div>
                                @endif
                            </div>
                            @if ($isSystem)
                                <p class="mt-3 text-xs font-medium text-gray-500 dark:text-gray-400">Nama peran ini dipakai langsung di kode, jadi tidak bisa diganti nama atau dihapus. Izin dan anggotanya tetap bisa diatur.</p>
                            @elseif ($role->users_count > 0)
                                <p class="mt-3 text-xs font-medium text-gray-500 dark:text-gray-400">Keluarkan semua anggota dulu untuk bisa menghapus peran ini.</p>
                            @endif
                        </div>

                        @if ($isSuper)
                            <div class="flex items-start gap-3 rounded-[14px] border border-primary-700/20 bg-primary-50 px-5 py-4 dark:border-primary-300/20 dark:bg-primary-500/10">
                                <x-filament::icon icon="heroicon-o-shield-check" class="mt-0.5 h-5 w-5 shrink-0 text-primary-700 dark:text-primary-300" />
                                <div>
                                    <p class="text-sm font-bold text-primary-800 dark:text-primary-200">Super-admin selalu bisa membuka semua halaman</p>
                                    <p class="mt-0.5 text-sm text-primary-800/80 dark:text-primary-200/80">Peran ini tidak memakai daftar izin. Batasi jumlah anggotanya seminimal mungkin.</p>
                                </div>
                            </div>
                        @else
                            {{-- Matriks izin --}}
                            <div class="{{ $card }}">
                                <div class="sticky top-16 z-10 flex flex-wrap items-center gap-3 rounded-t-[14px] border-b border-gray-200 bg-white/95 px-5 py-3 backdrop-blur dark:border-white/10 dark:bg-gray-900/95">
                                    <div class="min-w-0">
                                        <h3 class="text-[15px] font-extrabold tracking-tight text-gray-950 dark:text-white">Izin</h3>
                                        <p class="text-xs font-semibold text-gray-500 dark:text-gray-400"><span class="tabular-nums" x-text="($wire.selected ?? []).length"></span> dari {{ $totalPermissions }} izin dipilih</p>
                                    </div>
                                    <label class="relative ms-auto block w-60 max-w-full">
                                        <span class="sr-only">Cari izin</span>
                                        <x-filament::icon icon="heroicon-m-magnifying-glass" class="pointer-events-none absolute start-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400" />
                                        <input type="search" x-model="q" placeholder="Cari izin atau path…" class="{{ $input }} ps-9">
                                    </label>
                                    <div class="flex items-center gap-2" x-show="dirty > 0" x-cloak>
                                        <span class="text-xs font-bold text-primary-700 dark:text-primary-300"><span x-text="dirty"></span> perubahan belum disimpan</span>
                                        <x-filament::button size="sm" color="gray" wire:click="discardPermissions">Batalkan</x-filament::button>
                                        <x-filament::button size="sm" icon="heroicon-m-check" wire:click="savePermissions" wire:target="savePermissions">Simpan izin</x-filament::button>
                                    </div>
                                </div>

                                @php
                                    $columns = ['*' => 'Semua', 'viewAny' => 'Lihat daftar', 'view' => 'Lihat detail', 'create' => 'Buat', 'update' => 'Ubah', 'delete' => 'Hapus'];
                                    $cell = 'inline-grid h-8 w-8 cursor-pointer place-items-center rounded-[9px] transition hover:bg-gray-100 dark:hover:bg-white/5';
                                    $box = 'h-4 w-4 cursor-pointer rounded-[5px] border-gray-300 text-primary-600 focus:ring-primary-600 dark:border-white/20 dark:bg-white/5';
                                    $allText = [];
                                @endphp
                                <div class="overflow-x-auto">
                                    <table class="w-full min-w-[46rem] text-sm">
                                        <thead>
                                            <tr class="border-b border-gray-200 dark:border-white/10">
                                                <th scope="col" class="w-[15rem] px-5 py-2.5 text-left text-[11px] font-extrabold uppercase tracking-[0.08em] text-gray-500 dark:text-gray-400">Modul</th>
                                                @foreach ($columns as $label)
                                                    <th scope="col" class="w-[4.75rem] px-1 py-2.5 text-center text-[11px] font-extrabold uppercase tracking-[0.06em] text-gray-500 dark:text-gray-400">{{ $label }}</th>
                                                @endforeach
                                                <th scope="col" class="px-4 py-2.5 text-left text-[11px] font-extrabold uppercase tracking-[0.08em] text-gray-500 dark:text-gray-400">Lainnya</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach ($this->permissionGroups as $group)
                                                @php
                                                    $ids = $group['items']->pluck('id')->map(fn ($id) => (string) $id)->values()->all();
                                                    $text = $group['label'] . ' ' . $group['items']->map(fn ($i) => $i['name'] . ' ' . $i['action']['label'] . ' ' . implode(' ', $i['paths']))->implode(' ');
                                                    $allText[] = $text;
                                                @endphp
                                                <tr class="border-b border-gray-100 last:border-0 hover:bg-gray-50/70 dark:border-white/5 dark:hover:bg-white/[.02]" x-show="matches(@js($text))" wire:key="row-{{ $group['key'] }}">
                                                    <th scope="row" class="px-5 py-1.5 text-left">
                                                        <button
                                                            type="button"
                                                            x-on:click="toggleGroup(@js($ids))"
                                                            title="Pilih atau lepas semua izin {{ $group['label'] }}"
                                                            class="group -mx-1.5 flex items-center gap-2 rounded-[7px] px-1.5 py-1 text-left focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary-600"
                                                        >
                                                            <span class="whitespace-nowrap text-[13px] font-bold text-gray-950 group-hover:text-primary-700 dark:text-white dark:group-hover:text-primary-300">{{ $group['label'] }}</span>
                                                            <span class="text-[11px] font-extrabold tabular-nums" x-bind:class="countOn(@js($ids)) === {{ count($ids) }} ? 'text-primary-700 dark:text-primary-300' : 'text-gray-400 dark:text-gray-500'"><span x-text="countOn(@js($ids))"></span>/{{ count($ids) }}</span>
                                                        </button>
                                                    </th>
                                                    @foreach ($columns as $key => $label)
                                                        @php $item = $group['columns'][$key] ?? null; @endphp
                                                        <td class="px-1 py-1.5 text-center">
                                                            @if ($item)
                                                                <label class="{{ $cell }}" title="{{ $item['name'] }}{{ $item['paths'] ? ' · /' . implode(', /', $item['paths']) : ' · tanpa pola path' }}">
                                                                    <input type="checkbox" wire:model="selected" value="{{ $item['id'] }}" class="{{ $box }}">
                                                                    <span class="sr-only">{{ $group['label'] }}: {{ $label }}</span>
                                                                </label>
                                                            @else
                                                                <span class="text-gray-300 dark:text-gray-600" aria-hidden="true">–</span>
                                                            @endif
                                                        </td>
                                                    @endforeach
                                                    <td class="px-4 py-1.5">
                                                        <div class="flex flex-wrap gap-1.5">
                                                            @foreach ($group['extra'] as $item)
                                                                <label
                                                                    class="inline-flex cursor-pointer items-center gap-1.5 whitespace-nowrap rounded-[9px] px-2 py-1 text-xs font-bold ring-1 ring-inset transition"
                                                                    x-bind:class="isOn('{{ $item['id'] }}') ? 'bg-primary-50 text-primary-700 ring-primary-700/30 dark:bg-primary-500/10 dark:text-primary-300 dark:ring-primary-300/30' : 'text-gray-600 ring-gray-200 hover:bg-gray-50 dark:text-gray-300 dark:ring-white/10 dark:hover:bg-white/5'"
                                                                    title="{{ $item['name'] }}{{ $item['paths'] ? ' · /' . implode(', /', $item['paths']) : '' }}"
                                                                >
                                                                    <input type="checkbox" wire:model="selected" value="{{ $item['id'] }}" class="h-3.5 w-3.5 rounded-[4px] border-gray-300 text-primary-600 focus:ring-primary-600 dark:border-white/20 dark:bg-white/5">
                                                                    {{ $item['action']['key'] === '*' && str_contains($item['action']['label'], ' · ') ? \Illuminate\Support\Str::ucfirst(\Illuminate\Support\Str::after($item['action']['label'], ' · ')) : $item['action']['label'] }}
                                                                </label>
                                                            @endforeach
                                                        </div>
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                    <p class="px-5 py-10 text-center text-sm font-semibold text-gray-500 dark:text-gray-400" x-show="@js($allText).every((text) => ! matches(text))" x-cloak>
                                        Tidak ada modul yang cocok dengan pencarian.
                                    </p>
                                </div>
                                <p class="border-t border-gray-200 px-5 py-2.5 text-xs font-medium text-gray-500 dark:border-white/10 dark:text-gray-400">Arahkan kursor ke kotak centang untuk melihat nama izin dan pola path-nya. Klik nama modul untuk memilih atau melepas semua izinnya.</p>
                            </div>
                        @endif

                        {{-- Anggota --}}
                        <div class="{{ $card }}" x-data="{ open: false, q: '' }" x-on:keydown.escape.window="open = false">
                            <div class="flex flex-wrap items-center gap-3 border-b border-gray-200 px-5 py-3 dark:border-white/10">
                                <div>
                                    <h3 class="text-[15px] font-extrabold tracking-tight text-gray-950 dark:text-white">Anggota</h3>
                                    <p class="text-xs font-semibold text-gray-500 dark:text-gray-400">{{ $this->members->count() }} pengguna memegang peran ini</p>
                                </div>
                                <div class="relative ms-auto" x-on:click.outside="open = false">
                                    <x-filament::button size="sm" color="gray" icon="heroicon-m-user-plus" x-on:click="open = ! open; $nextTick(() => $refs.memberSearch?.focus())">Tambah anggota</x-filament::button>
                                    <div x-show="open" x-cloak x-transition.opacity.duration.150ms class="kis-pop kis-pop--end w-80">
                                        <label class="kis-pop__search">
                                            <x-filament::icon icon="heroicon-m-magnifying-glass" class="h-4 w-4" />
                                            <input type="text" x-ref="memberSearch" x-model="q" placeholder="Cari nama atau email…" autocomplete="off">
                                        </label>
                                        <ul class="max-h-72 overflow-y-auto px-1.5 pb-1.5">
                                            @forelse ($this->candidates as $candidate)
                                                <li x-show="@js(mb_strtolower($candidate['name'] . ' ' . $candidate['email'])).includes(q.trim().toLowerCase())">
                                                    <button type="button" wire:click="addMember({{ $candidate['id'] }})" x-on:click="open = false" class="flex w-full items-center gap-3 rounded-[9px] px-2.5 py-2 text-left hover:bg-gray-100 focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary-600 dark:hover:bg-white/5">
                                                        <span class="grid h-7 w-7 shrink-0 place-items-center rounded-full bg-gray-100 text-[10px] font-extrabold text-gray-600 dark:bg-white/10 dark:text-gray-300">{{ \Illuminate\Support\Str::of($candidate['name'])->explode(' ')->map(fn ($w) => mb_substr($w, 0, 1))->take(2)->implode('') }}</span>
                                                        <span class="min-w-0 flex-1">
                                                            <span class="block truncate text-[13px] font-bold text-gray-950 dark:text-white">{{ $candidate['name'] }}</span>
                                                            <span class="block truncate text-[11.5px] font-semibold text-gray-500 dark:text-gray-400">{{ $candidate['roles'] ?: 'Belum punya peran' }}</span>
                                                        </span>
                                                    </button>
                                                </li>
                                            @empty
                                                <li class="px-3 py-6 text-center text-sm font-semibold text-gray-500">Semua pengguna sudah memegang peran ini.</li>
                                            @endforelse
                                        </ul>
                                    </div>
                                </div>
                            </div>

                            @if ($this->members->isEmpty())
                                <p class="px-5 py-8 text-center text-sm font-semibold text-gray-500 dark:text-gray-400">Belum ada anggota. Tambahkan pengguna yang perlu memegang peran ini.</p>
                            @else
                                <ul class="divide-y divide-gray-100 dark:divide-white/5">
                                    @foreach ($this->members as $member)
                                        @php $others = $member->roles->pluck('name')->reject(fn ($n) => $n === $role->name); @endphp
                                        <li class="flex items-center gap-3 px-5 py-2.5" wire:key="member-{{ $member->id }}">
                                            <span class="grid h-8 w-8 shrink-0 place-items-center rounded-full bg-gray-100 text-[11px] font-extrabold text-gray-600 dark:bg-white/10 dark:text-gray-300">{{ \Illuminate\Support\Str::of($member->name)->explode(' ')->map(fn ($w) => mb_substr($w, 0, 1))->take(2)->implode('') }}</span>
                                            <span class="min-w-0 flex-1">
                                                <span class="block truncate text-[13.5px] font-bold text-gray-950 dark:text-white">{{ $member->name }}</span>
                                                <span class="block truncate text-xs font-semibold text-gray-500 dark:text-gray-400">{{ $member->email }}</span>
                                            </span>
                                            @foreach ($others as $other)
                                                <x-kis.badge tone="neutral" size="sm">{{ $other }}</x-kis.badge>
                                            @endforeach
                                            <button
                                                type="button"
                                                wire:click="removeMember({{ $member->id }})"
                                                wire:confirm="Keluarkan {{ $member->name }} dari peran {{ $role->name }}?"
                                                class="rounded-[9px] px-2.5 py-1.5 text-xs font-bold text-gray-600 hover:bg-danger-50 hover:text-danger-700 focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary-600 dark:text-gray-300 dark:hover:bg-danger-500/10 dark:hover:text-danger-300"
                                            >Keluarkan</button>
                                        </li>
                                    @endforeach
                                </ul>
                            @endif
                        </div>
                    @endif
                </div>
            </div>
        @endif

        {{-- ============================== IZIN ============================== --}}
        @if ($tab === 'permissions')
            <div class="grid items-start gap-5 {{ $permissionFormOpen ? 'xl:grid-cols-[minmax(0,1fr)_24rem]' : '' }}" x-data="{ q: '' }">
                <div class="{{ $card }} min-w-0 overflow-hidden">
                    <div class="flex flex-wrap items-center gap-3 border-b border-gray-200 px-5 py-3 dark:border-white/10">
                        <div>
                            <h2 class="text-[15px] font-extrabold tracking-tight text-gray-950 dark:text-white">Semua izin</h2>
                            <p class="text-xs font-semibold text-gray-500 dark:text-gray-400">{{ $totalPermissions }} izin dalam {{ $this->permissionGroups->count() }} modul</p>
                        </div>
                        <label class="relative ms-auto block w-64 max-w-full">
                            <span class="sr-only">Cari izin</span>
                            <x-filament::icon icon="heroicon-m-magnifying-glass" class="pointer-events-none absolute start-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400" />
                            <input type="search" x-model="q" placeholder="Cari nama, path, atau peran…" class="{{ $input }} ps-9">
                        </label>
                        <x-filament::button size="sm" icon="heroicon-m-plus" wire:click="newPermission">Izin baru</x-filament::button>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="border-b border-gray-200 text-left dark:border-white/10">
                                    <th class="px-5 py-2.5 text-[11px] font-extrabold uppercase tracking-[0.08em] text-gray-500 dark:text-gray-400">Izin</th>
                                    <th class="px-3 py-2.5 text-[11px] font-extrabold uppercase tracking-[0.08em] text-gray-500 dark:text-gray-400">Pola path</th>
                                    <th class="px-3 py-2.5 text-[11px] font-extrabold uppercase tracking-[0.08em] text-gray-500 dark:text-gray-400">Dipakai peran</th>
                                    <th class="px-5 py-2.5"><span class="sr-only">Aksi</span></th>
                                </tr>
                            </thead>
                            @foreach ($this->permissionGroups as $group)
                                @php $groupText = $group['items']->map(fn ($i) => $i['name'] . ' ' . implode(' ', $i['paths']) . ' ' . implode(' ', $i['roles']) . ' ' . $group['label'])->values()->all(); @endphp
                                <tbody x-show="@js($groupText).some((t) => t.toLowerCase().includes(q.trim().toLowerCase()))">
                                    <x-kis.group-row :colspan="4" :label="$group['label']" :count="$group['items']->count()" count-label="izin" />
                                    @foreach ($group['items'] as $item)
                                        <tr
                                            wire:key="perm-row-{{ $item['id'] }}"
                                            x-show="@js(mb_strtolower($item['name'] . ' ' . implode(' ', $item['paths']) . ' ' . implode(' ', $item['roles']) . ' ' . $group['label'])).includes(q.trim().toLowerCase())"
                                            @class([
                                                'border-b border-gray-100 align-top last:border-0 dark:border-white/5',
                                                'bg-primary-50 dark:bg-primary-500/10' => $editingPermissionId === $item['id'],
                                            ])
                                        >
                                            <td class="px-5 py-2.5">
                                                <span class="block font-mono text-[12.5px] font-bold text-gray-950 dark:text-white">{{ $item['name'] }}</span>
                                                <span class="text-xs font-semibold text-gray-500 dark:text-gray-400">{{ $item['action']['label'] }}</span>
                                            </td>
                                            <td class="px-3 py-2.5">
                                                @forelse ($item['paths'] as $pattern)
                                                    <code class="mb-0.5 me-1 inline-block rounded-[5px] bg-gray-100 px-1.5 py-0.5 font-mono text-[11.5px] text-gray-700 dark:bg-white/5 dark:text-gray-300">/{{ $pattern }}</code>
                                                @empty
                                                    <x-kis.badge tone="red" size="sm">Tanpa pola</x-kis.badge>
                                                @endforelse
                                            </td>
                                            <td class="px-3 py-2.5">
                                                <div class="flex flex-wrap gap-1">
                                                    @forelse ($item['roles'] as $roleName)
                                                        <x-kis.badge tone="neutral" size="sm">{{ $roleName }}</x-kis.badge>
                                                    @empty
                                                        <span class="text-xs font-semibold text-gray-400 dark:text-gray-500">Belum dipakai</span>
                                                    @endforelse
                                                </div>
                                            </td>
                                            <td class="whitespace-nowrap px-5 py-2">
                                                <div class="flex items-center justify-end gap-0.5">
                                                    <x-filament::icon-button icon="heroicon-m-pencil-square" color="gray" label="Ubah {{ $item['name'] }}" wire:click="editPermission({{ $item['id'] }})" />
                                                    {{ ($this->deletePermissionAction)(['permission' => $item['id']]) }}
                                                </div>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            @endforeach
                        </table>
                    </div>
                </div>

                @if ($permissionFormOpen)
                    <form wire:submit="savePermission" class="{{ $card }} xl:sticky xl:top-20">
                        <div class="flex items-center gap-3 border-b border-gray-200 px-5 py-3 dark:border-white/10">
                            <h3 class="text-[15px] font-extrabold tracking-tight text-gray-950 dark:text-white">{{ $editingPermissionId ? 'Ubah izin' : 'Izin baru' }}</h3>
                            <x-filament::icon-button class="ms-auto" icon="heroicon-m-x-mark" color="gray" label="Tutup" wire:click="closePermissionForm" />
                        </div>
                        <div class="space-y-4 px-5 py-4">
                            <div>
                                <label for="perm-name" class="mb-1.5 block text-xs font-bold text-gray-700 dark:text-gray-300">Nama izin</label>
                                <input id="perm-name" type="text" wire:model="permissionName" placeholder="mis. invoices.*" class="{{ $input }} font-mono" autocomplete="off">
                                @error('permissionName') <p class="mt-1.5 text-xs font-semibold text-danger-600 dark:text-danger-400">{{ $message }}</p> @enderror
                                <p class="mt-1.5 text-xs text-gray-500 dark:text-gray-400">Pola <code>modul.aksi</code>, mis. <code>clients.viewAny</code>. Nama ini juga yang dicek oleh <code>can()</code> di kode.</p>
                            </div>
                            <div>
                                <label for="perm-paths" class="mb-1.5 block text-xs font-bold text-gray-700 dark:text-gray-300">Pola path</label>
                                <textarea id="perm-paths" rows="3" wire:model.live.debounce.300ms="permissionPaths" placeholder="clients*" class="{{ $input }} font-mono"></textarea>
                                @error('permissionPaths') <p class="mt-1.5 text-xs font-semibold text-danger-600 dark:text-danger-400">{{ $message }}</p> @enderror
                                <ul class="mt-2 space-y-1 text-xs text-gray-500 dark:text-gray-400">
                                    <li><code class="font-mono text-gray-700 dark:text-gray-300">clients</code> hanya halaman daftar</li>
                                    <li><code class="font-mono text-gray-700 dark:text-gray-300">clients*</code> semua halaman di bawah clients</li>
                                    <li><code class="font-mono text-gray-700 dark:text-gray-300">clients/*</code> daftar dan detail satu data, tanpa ubah dan buat baru</li>
                                    <li><code class="font-mono text-gray-700 dark:text-gray-300">clients/*/edit</code> halaman ubah</li>
                                    <li>Satu pola per baris.</li>
                                </ul>
                            </div>
                            <div>
                                <p class="mb-1.5 text-xs font-bold text-gray-700 dark:text-gray-300">Halaman yang cocok <span class="font-semibold text-gray-500">({{ $this->pathPreview->count() }})</span></p>
                                @if ($this->pathPreview->isEmpty())
                                    <p class="rounded-[11px] bg-gray-100 px-3 py-2.5 text-xs font-semibold text-gray-500 dark:bg-white/5 dark:text-gray-400">{{ trim($permissionPaths) === '' ? 'Isi pola path untuk melihat halaman yang terbuka.' : 'Tidak ada halaman panel yang cocok dengan pola ini.' }}</p>
                                @else
                                    <ul class="max-h-48 space-y-0.5 overflow-y-auto rounded-[11px] bg-gray-100 px-3 py-2 dark:bg-white/5">
                                        @foreach ($this->pathPreview as $route)
                                            <li class="truncate font-mono text-[11.5px] text-gray-700 dark:text-gray-300">{{ $route['label'] }}</li>
                                        @endforeach
                                    </ul>
                                @endif
                            </div>
                        </div>
                        <div class="flex justify-end gap-2 border-t border-gray-200 px-5 py-3 dark:border-white/10">
                            <x-filament::button color="gray" wire:click="closePermissionForm">Batal</x-filament::button>
                            <x-filament::button type="submit" wire:target="savePermission">{{ $editingPermissionId ? 'Simpan perubahan' : 'Buat izin' }}</x-filament::button>
                        </div>
                    </form>
                @endif
            </div>
        @endif

        {{-- ============================== UJI AKSES ============================== --}}
        @if ($tab === 'test')
            <div class="{{ $card }} max-w-4xl">
                <div class="border-b border-gray-200 px-5 py-4 dark:border-white/10">
                    <h2 class="text-[15px] font-extrabold tracking-tight text-gray-950 dark:text-white">Uji akses</h2>
                    <p class="text-xs font-semibold text-gray-500 dark:text-gray-400">Tempel alamat halaman untuk melihat peran mana yang boleh membukanya.</p>
                    <label class="relative mt-3 block">
                        <span class="sr-only">Alamat halaman</span>
                        <span class="pointer-events-none absolute start-3 top-1/2 -translate-y-1/2 font-mono text-sm text-gray-400">/</span>
                        <input type="text" wire:model.live.debounce.300ms="testPath" placeholder="tax-reports/12/edit" class="{{ $input }} ps-6 font-mono" autocomplete="off" autofocus>
                    </label>
                    <div class="mt-2 flex flex-wrap items-center gap-1.5 text-xs font-semibold text-gray-500 dark:text-gray-400">
                        Contoh:
                        @foreach (['projects', 'clients/create', 'tax-reports/12/edit', 'user-logs', 'peran-izin'] as $example)
                            <button type="button" wire:click="$set('testPath', '{{ $example }}')" class="rounded-[7px] bg-gray-100 px-2 py-0.5 font-mono text-[11.5px] text-gray-700 hover:bg-gray-200 dark:bg-white/5 dark:text-gray-300 dark:hover:bg-white/10">/{{ $example }}</button>
                        @endforeach
                    </div>
                </div>
                @if ($this->accessTest === null)
                    <p class="px-5 py-10 text-center text-sm font-semibold text-gray-500 dark:text-gray-400">Hasil akan muncul di sini.</p>
                @else
                    <ul class="divide-y divide-gray-100 dark:divide-white/5">
                        @foreach ($this->accessTest as $result)
                            <li class="flex items-center gap-3 px-5 py-3">
                                <span class="w-36 shrink-0 truncate text-[13.5px] font-bold text-gray-950 dark:text-white">{{ $result['role'] }}</span>
                                <x-kis.badge :tone="$result['allowed'] ? 'blue' : 'red'" dot>{{ $result['allowed'] ? 'Boleh' : 'Ditolak' }}</x-kis.badge>
                                <span @class(['min-w-0 truncate text-xs text-gray-500 dark:text-gray-400', 'font-mono' => $result['byPermission'], 'font-semibold' => ! $result['byPermission']]) title="{{ $result['reason'] }}">{{ $result['reason'] }}</span>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
        @endif
    </div>
</x-filament-panels::page>
