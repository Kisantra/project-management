{{-- Team roster for the project detail "Tim & PIC" tab. Rendered inside the page's
     panel, so it carries no card chrome of its own. --}}
@php
    $canManage = ! auth()->user()->hasRole('staff');
@endphp
<div x-data="{ adding: false }">
    {{-- Section head --}}
    <div class="flex items-center justify-between gap-4 px-4 py-3 sm:px-5">
        <h3 class="text-sm font-semibold text-gray-900 dark:text-white">
            Anggota tim
            <span class="ml-1 font-normal tabular-nums text-gray-500 dark:text-gray-400">{{ $users->count() }}</span>
        </h3>
        @if ($canManage)
            <button type="button" x-on:click="adding = !adding" :aria-expanded="adding"
                    class="inline-flex items-center gap-1.5 rounded-md px-2.5 py-1.5 text-xs font-medium text-primary-600 transition-colors hover:bg-primary-50 dark:text-primary-400 dark:hover:bg-primary-400/10">
                <x-heroicon-m-plus class="h-4 w-4" />
                <span x-text="adding ? 'Tutup' : 'Tambah anggota'">Tambah anggota</span>
            </button>
        @endif
    </div>

    {{-- Add-member panel (managers only) --}}
    @if ($canManage)
        <div x-show="adding" x-cloak
             x-transition:enter="transition duration-150 ease-out motion-reduce:transition-none"
             x-transition:enter-start="opacity-0"
             x-transition:enter-end="opacity-100"
             class="border-y border-gray-100 bg-gray-50/70 px-4 py-4 dark:border-white/5 dark:bg-white/[.02] sm:px-5">
            <div class="relative max-w-md">
                <x-heroicon-m-magnifying-glass class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400" />
                <input type="search"
                       wire:model.live.debounce.300ms="search"
                       placeholder="Cari nama atau email…"
                       class="block w-full rounded-lg border-0 bg-white py-2 pl-9 pr-3 text-sm text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 placeholder:text-gray-500 focus:ring-2 focus:ring-inset focus:ring-primary-500 dark:bg-gray-900 dark:text-white dark:ring-white/10 dark:placeholder:text-gray-400">
            </div>

            <ul class="mt-3 max-h-72 divide-y divide-gray-100 overflow-y-auto rounded-lg bg-white ring-1 ring-gray-950/5 dark:divide-white/5 dark:bg-gray-900 dark:ring-white/10">
                @forelse ($availableUsers as $availableUser)
                    <li class="flex items-center gap-3 px-3 py-2" wire:key="avail-{{ $availableUser->id }}">
                        <img src="https://ui-avatars.com/api/?name={{ urlencode($availableUser->name) }}&color=7F9CF5&background=EBF4FF"
                             alt="" class="h-8 w-8 shrink-0 rounded-full object-cover">
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-sm font-medium text-gray-900 dark:text-white">{{ $availableUser->name }}</p>
                            <p class="truncate text-xs text-gray-500 dark:text-gray-400">{{ $availableUser->email }}</p>
                        </div>
                        <x-filament::button size="xs" color="gray" wire:click="addUserToProject({{ $availableUser->id }})" wire:loading.attr="disabled">
                            Tambah
                        </x-filament::button>
                    </li>
                @empty
                    <li class="px-3 py-6 text-center text-sm text-gray-500 dark:text-gray-400">
                        Tidak ada pengguna yang cocok.
                    </li>
                @endforelse
            </ul>
        </div>
    @endif

    {{-- Roster: dense rows, two columns on wide screens --}}
    @if ($users->isEmpty())
        <div class="px-4 py-10 text-center sm:px-5">
            <x-heroicon-o-user-group class="mx-auto h-7 w-7 text-gray-400" />
            <p class="mt-2 text-sm font-medium text-gray-900 dark:text-white">Belum ada anggota tim</p>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Tambahkan anggota agar mereka bisa mengerjakan tugas proyek ini.</p>
        </div>
    @else
        <ul class="grid border-t border-gray-100 dark:border-white/5 lg:grid-cols-2">
            @foreach ($users as $user)
                <li class="flex items-center gap-3 border-b border-gray-100 px-4 py-2.5 dark:border-white/5 sm:px-5 lg:odd:border-r"
                    wire:key="member-{{ $user['id'] }}">
                    <img src="{{ $user['avatar'] }}" alt="" class="h-9 w-9 shrink-0 rounded-full object-cover">

                    <div class="min-w-0 flex-1">
                        <p class="truncate text-sm font-medium text-gray-900 dark:text-white">{{ $user['name'] }}</p>
                        <p class="truncate text-xs text-gray-500 dark:text-gray-400">
                            {{ $user['email'] }}
                            <span class="mx-1 text-gray-300 dark:text-gray-600">&middot;</span>
                            <span class="tabular-nums">{{ $user['comments_count'] }} komentar</span>
                            <span class="mx-1 text-gray-300 dark:text-gray-600">&middot;</span>
                            <span class="tabular-nums">{{ $user['documents_count'] }} dokumen</span>
                            @if ($user['last_active'])
                                <span class="mx-1 text-gray-300 dark:text-gray-600">&middot;</span>
                                aktif {{ $user['last_active'] }}
                            @endif
                        </p>
                    </div>

                    @if ($canManage)
                        <x-filament::dropdown placement="bottom-end">
                            <x-slot name="trigger">
                                <button type="button" aria-label="Aksi untuk {{ $user['name'] }}"
                                        class="flex h-8 w-8 items-center justify-center rounded-md text-gray-400 transition-colors hover:bg-gray-100 hover:text-gray-700 dark:hover:bg-white/5 dark:hover:text-gray-200">
                                    <x-heroicon-m-ellipsis-horizontal class="h-5 w-5" />
                                </button>
                            </x-slot>
                            <x-filament::dropdown.list>
                                <x-filament::dropdown.list.item
                                    x-on:click="$dispatch('open-modal', { id: 'confirm-remove-{{ $user['id'] }}' })"
                                    icon="heroicon-m-trash" color="danger">
                                    Keluarkan dari tim
                                </x-filament::dropdown.list.item>
                            </x-filament::dropdown.list>
                        </x-filament::dropdown>

                        <div class="[&>.fi-modal]:block [&>.fi-modal]:h-0">
                            <x-filament::modal id="confirm-remove-{{ $user['id'] }}" width="md">
                                <div class="space-y-4">
                                    <h3 class="text-base font-semibold text-gray-900 dark:text-white">Keluarkan anggota tim</h3>
                                    <p class="text-sm text-gray-600 dark:text-gray-300">
                                        {{ $user['name'] }} akan dikeluarkan dari proyek ini. Tugas yang sudah dikerjakan tetap tersimpan.
                                    </p>
                                    <div class="flex justify-end gap-3">
                                        <x-filament::button color="gray" x-on:click="$dispatch('close-modal', { id: 'confirm-remove-{{ $user['id'] }}' })">
                                            Batal
                                        </x-filament::button>
                                        <x-filament::button color="danger" wire:click="removeMember({{ $user['id'] }})">
                                            Keluarkan
                                        </x-filament::button>
                                    </div>
                                </div>
                            </x-filament::modal>
                        </div>
                    @endif
                </li>
            @endforeach
        </ul>
    @endif
</div>
