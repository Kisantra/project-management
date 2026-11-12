@props([
    'group'        => null,   // App\Models\ClientGroup
    'currentId'    => null,   // sorot anggota ini sebagai "Klien ini" (null = tidak ada, semua dapat diklik)
    'emptyEditUrl' => null,   // CTA empty-state saat klien belum punya grup (konteks klien saja)
    'manageUrl'    => null,   // tombol "Kelola" di header roster (konteks daftar grup)
    'showAddClient' => false, // tombol "Tambah Client" (butuh host Livewire dgn method openAddClient)
])

@php
    $logo = function (?string $path) {
        if (! $path) {
            return null;
        }
        if (str_starts_with($path, 'http')) {
            return $path;
        }
        return \Illuminate\Support\Facades\Storage::disk('public')->url($path);
    };
    $initial = fn ($n) => mb_strtoupper(mb_substr(trim((string) $n), 0, 1)) ?: '?';

    $members     = $group ? $group->clients->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)->values() : collect();
    $total       = $members->count();
    $activeCount = $members->where('status', 'Active')->count();
    $activePct   = $total > 0 ? round($activeCount / $total * 100) : 0;
    $groupLogo   = $group ? $logo($group->logo) : null;
    $showNotes   = $currentId === null && $group && filled($group->notes);
    $hasContact  = $group && ($group->contact_name || $group->contact_phone || $group->contact_email || $group->address || $showNotes);
@endphp

<section class="overflow-hidden rounded-[14px] border border-gray-200 bg-white dark:border-white/10 dark:bg-gray-900">
    @if($group)
        <div class="grid lg:grid-cols-3">
            {{-- ── Kolom kiri: identitas grup ── --}}
            <div class="border-b border-gray-100 p-6 dark:border-gray-800 lg:col-span-1 lg:border-b-0 sm:p-7">
                @if($groupLogo)
                    <img src="{{ $groupLogo }}" alt="Logo {{ $group->name }}"
                         class="h-16 w-16 rounded-2xl object-cover ring-1 ring-gray-200 dark:ring-gray-700">
                @else
                    <div class="flex h-16 w-16 items-center justify-center rounded-2xl bg-primary-100 text-xl font-extrabold tracking-tight text-primary-700 dark:bg-primary-500/15 dark:text-primary-300">
                        {{ $initial($group->name) }}
                    </div>
                @endif

                <p class="mt-4 text-[11px] font-semibold uppercase tracking-wider text-gray-400 dark:text-gray-500">Grup Usaha</p>
                <div class="mt-1 flex flex-wrap items-center gap-2">
                    <h3 class="text-lg font-semibold tracking-tight text-gray-900 dark:text-white">{{ $group->name }}</h3>
                    @if($group->status === 'active')
                        <x-kis.badge tone="blue" dot size="sm">Aktif</x-kis.badge>
                    @elseif($group->status)
                        <x-kis.badge tone="neutral" dot size="sm">Nonaktif</x-kis.badge>
                    @endif
                </div>

                {{-- Meter keanggotaan --}}
                <div class="mt-5">
                    <div class="flex items-baseline justify-between text-xs">
                        <span class="font-medium text-gray-700 dark:text-gray-200">{{ $total }} perusahaan</span>
                        <span class="text-gray-400 dark:text-gray-500">{{ $activeCount }} aktif</span>
                    </div>
                    <div class="mt-2 h-1.5 overflow-hidden rounded-full bg-gray-100 dark:bg-gray-800">
                        <div class="h-full rounded-full bg-primary-500" style="width: {{ $activePct }}%"></div>
                    </div>
                </div>

                {{-- Informasi grup — accordion (tertutup default biar ringkas) --}}
                @if($hasContact)
                    <div x-data="{ open: false }" class="mt-6 border-t border-gray-100 pt-4 dark:border-gray-800">
                        <button type="button" @click="open = !open"
                                class="flex w-full items-center justify-between gap-2 text-left focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500 focus-visible:ring-offset-2 dark:focus-visible:ring-offset-gray-900">
                            <span class="text-[11px] font-semibold uppercase tracking-wider text-gray-400 dark:text-gray-500">Informasi Grup</span>
                            <svg class="h-4 w-4 shrink-0 text-gray-400 transition-transform duration-200 dark:text-gray-500" :class="open && 'rotate-180'" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/>
                            </svg>
                        </button>
                        <dl x-show="open" x-collapse x-cloak class="mt-4 space-y-3 text-xs">
                            @if($group->contact_name)
                                <div>
                                    <dt class="font-medium text-gray-400 dark:text-gray-500">Kontak</dt>
                                    <dd class="mt-0.5 text-gray-700 dark:text-gray-200">{{ $group->contact_name }}</dd>
                                </div>
                            @endif
                            @if($group->contact_phone)
                                <div>
                                    <dt class="font-medium text-gray-400 dark:text-gray-500">Telepon</dt>
                                    <dd class="mt-0.5"><a href="tel:{{ $group->contact_phone }}" class="text-gray-700 transition-colors hover:text-primary-600 dark:text-gray-200 dark:hover:text-primary-400">{{ $group->contact_phone }}</a></dd>
                                </div>
                            @endif
                            @if($group->contact_email)
                                <div>
                                    <dt class="font-medium text-gray-400 dark:text-gray-500">Email</dt>
                                    <dd class="mt-0.5"><a href="mailto:{{ $group->contact_email }}" class="break-all text-gray-700 transition-colors hover:text-primary-600 dark:text-gray-200 dark:hover:text-primary-400">{{ $group->contact_email }}</a></dd>
                                </div>
                            @endif
                            @if($group->address)
                                <div>
                                    <dt class="font-medium text-gray-400 dark:text-gray-500">Alamat</dt>
                                    <dd class="mt-0.5 leading-relaxed text-gray-600 dark:text-gray-300">{{ $group->address }}</dd>
                                </div>
                            @endif
                            @if($showNotes)
                                <div>
                                    <dt class="font-medium text-gray-400 dark:text-gray-500">Catatan Internal</dt>
                                    <dd class="mt-0.5 leading-relaxed text-gray-600 dark:text-gray-300">{{ $group->notes }}</dd>
                                </div>
                            @endif
                        </dl>
                    </div>
                @endif
            </div>

            {{-- ── Kolom kanan: roster perusahaan ── --}}
            <div class="p-6 dark:border-gray-800 lg:col-span-2 lg:border-l lg:border-gray-100 dark:lg:border-gray-800 sm:p-7">
                <div class="mb-3 flex items-center justify-between gap-3">
                    <p class="text-[11px] font-semibold uppercase tracking-wider text-gray-400 dark:text-gray-500">Perusahaan dalam grup</p>
                    <div class="flex items-center gap-2">
                        <span class="mr-1 text-xs tabular-nums text-gray-400 dark:text-gray-500">{{ $total }}</span>
                        @if($showAddClient)
                            <button type="button" wire:click="openAddClient({{ $group->id }})"
                                    class="inline-flex items-center gap-1.5 rounded-lg bg-primary-600 px-2.5 py-1 text-xs font-bold text-white shadow-sm transition hover:bg-primary-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-600 focus-visible:ring-offset-2 dark:focus-visible:ring-offset-gray-900">
                                <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 5v14m7-7H5"/>
                                </svg>
                                Tambah Client
                            </button>
                        @endif
                        @if($manageUrl)
                            <a href="{{ $manageUrl }}"
                               class="inline-flex items-center gap-1.5 rounded-lg border border-gray-200 bg-white px-2.5 py-1 text-xs font-medium text-gray-700 shadow-sm transition hover:border-gray-300 hover:bg-gray-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 dark:hover:bg-gray-800">
                                <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/>
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M18.5 2.5a2.121 2.121 0 113 3L12 15l-4 1 1-4 9.5-9.5z"/>
                                </svg>
                                Kelola
                            </a>
                        @endif
                    </div>
                </div>

                <ul class="-mx-1">
                    @foreach($members as $member)
                        @php
                            $isSelf = $currentId !== null && $member->id === $currentId;
                            $mLogo  = $logo($member->logo);
                            $typeLine = $member->client_type
                                ? $member->client_type . ($member->client_subtype ? ' · ' . $member->client_subtype : '')
                                : null;
                        @endphp
                        <li>
                            @if($isSelf)
                                <div class="flex items-center gap-3 rounded-xl bg-primary-50/70 px-3 py-2.5 ring-1 ring-inset ring-primary-100 dark:bg-primary-500/10 dark:ring-primary-500/20">
                                    @if($mLogo)
                                        <img src="{{ $mLogo }}" alt="{{ $member->name }}" class="h-10 w-10 shrink-0 rounded-lg object-cover ring-1 ring-primary-200 dark:ring-primary-500/30">
                                    @else
                                        <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-primary-100 text-sm font-semibold text-primary-700 dark:bg-primary-500/20 dark:text-primary-300">{{ $initial($member->name) }}</span>
                                    @endif
                                    <div class="min-w-0 flex-1">
                                        <div class="flex items-center gap-2">
                                            <span class="truncate text-sm font-semibold text-gray-900 dark:text-white">{{ $member->name }}</span>
                                            <span class="shrink-0 rounded-full bg-primary-100 px-1.5 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-primary-700 dark:bg-primary-500/20 dark:text-primary-300">Klien ini</span>
                                        </div>
                                        @if($typeLine)<div class="truncate text-xs text-gray-500 dark:text-gray-400">{{ $typeLine }}</div>@endif
                                    </div>
                                    <x-relasi.status-pill :status="$member->status" />
                                </div>
                            @else
                                <a href="{{ \App\Filament\Resources\ClientResource::getUrl('view', ['record' => $member]) }}"
                                   class="group flex items-center gap-3 rounded-xl px-3 py-2.5 transition-colors duration-150 hover:bg-gray-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500 dark:hover:bg-gray-800/60">
                                    @if($mLogo)
                                        <img src="{{ $mLogo }}" alt="{{ $member->name }}" class="h-10 w-10 shrink-0 rounded-lg object-cover ring-1 ring-gray-200 dark:ring-gray-700">
                                    @else
                                        <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-gray-100 text-sm font-semibold text-gray-500 dark:bg-gray-800 dark:text-gray-400">{{ $initial($member->name) }}</span>
                                    @endif
                                    <div class="min-w-0 flex-1">
                                        <div class="truncate text-sm font-medium text-gray-900 transition-colors group-hover:text-primary-600 dark:text-gray-100 dark:group-hover:text-primary-400">{{ $member->name }}</div>
                                        @if($typeLine)<div class="truncate text-xs text-gray-500 dark:text-gray-400">{{ $typeLine }}</div>@endif
                                    </div>
                                    <x-relasi.status-pill :status="$member->status" />
                                    <svg class="h-4 w-4 shrink-0 text-gray-300 transition-all duration-150 group-hover:translate-x-0.5 group-hover:text-gray-400 dark:text-gray-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 18l6-6-6-6"/>
                                    </svg>
                                </a>
                            @endif
                        </li>
                    @endforeach
                </ul>

                @if($currentId !== null && $total <= 1)
                    <p class="mt-2 px-3 text-xs text-gray-400 dark:text-gray-500">Belum ada perusahaan lain di grup ini.</p>
                @elseif($currentId === null && $total === 0)
                    <p class="mt-2 px-3 text-xs text-gray-400 dark:text-gray-500">Belum ada perusahaan dalam grup ini.</p>
                @endif
            </div>
        </div>
    @else
        {{-- ── Empty: klien belum tergabung grup (konteks klien) ── --}}
        <div class="flex flex-col items-center justify-center px-6 py-14 text-center">
            <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-gray-100 text-gray-400 dark:bg-gray-800 dark:text-gray-500">
                <svg class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="1.6" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 21h18M5 21V7l7-4 7 4v14M9 9h.01M9 13h.01M9 17h.01M15 9h.01M15 13h.01M15 17h.01"/>
                </svg>
            </div>
            <p class="mt-4 text-sm font-semibold text-gray-900 dark:text-white">Belum tergabung dalam grup</p>
            <p class="mt-1 max-w-xs text-xs leading-relaxed text-gray-500 dark:text-gray-400">
                Klien ini belum terhubung ke grup usaha. Hubungkan untuk melihat perusahaan lain dalam grup yang sama.
            </p>
            @if($emptyEditUrl)
                <a href="{{ $emptyEditUrl }}"
                   class="mt-5 inline-flex items-center gap-1.5 rounded-lg bg-primary-600 px-3.5 py-2 text-xs font-bold text-white transition-colors hover:bg-primary-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-600 focus-visible:ring-offset-2 dark:focus-visible:ring-offset-gray-900">
                    <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 5v14m7-7H5"/>
                    </svg>
                    Hubungkan ke grup
                </a>
            @endif
        </div>
    @endif
</section>
