{{--
    Client groups (docs/mockups/client-groups-redesign.html): search + status rail,
    then one card per group and a "Buat grup baru" card. CSS: .kis-groups / .kis-gcard
    in resources/css/filament/admin/theme.css.
--}}
@php
    use App\Filament\Resources\ClientGroupResource;
    use App\Livewire\Clients\GroupList;
    use App\Support\Ui\ClientAvatar;

    $counts = $this->statusCounts;
@endphp

<div class="kis-groups">
    {{-- Toolbar --}}
    <div class="kis-groups__tool">
        <label class="kis-groups__search">
            <x-heroicon-m-magnifying-glass class="h-4 w-4 shrink-0" />
            <input type="search" wire:model.live.debounce.300ms="search" placeholder="Cari grup, kontak, atau nama klien" aria-label="Cari grup">
        </label>
        <div class="kis-rail__wrap kis-groups__rail" role="group" aria-label="Status grup">
            @foreach (GroupList::STATUSES as $value => $label)
                <button type="button" wire:click="setStatus('{{ $value }}')" @class(['kis-rail__chip', 'is-on' => $statusFilter === $value]) aria-pressed="{{ $statusFilter === $value ? 'true' : 'false' }}">
                    {{ $label }} <em>{{ $counts[$value] }}</em>
                </button>
            @endforeach
        </div>
    </div>

    <div class="kis-groups__grid" wire:loading.class="opacity-60" wire:target="search, setStatus">
        @foreach ($groups as $group)
            @php
                $members = $group->clients;
                $total = $members->count();
                $active = $members->where('status', 'Active')->count();
                $logo = ClientAvatar::url($group->logo);
                $contact = collect([$group->contact_name ? 'Kontak ' . $group->contact_name : null, $group->contact_email ?: $group->contact_phone])->filter()->implode(' · ');
            @endphp
            <article class="kis-gcard" wire:key="group-{{ $group->id }}">
                <header class="kis-gcard__head">
                    <span class="kis-gtile" aria-hidden="true">
                        <span>{{ ClientAvatar::initials($group->name) }}</span>
                        @if ($logo)<img src="{{ $logo }}" alt="" onerror="this.remove()">@endif
                    </span>
                    <div class="kis-gcard__id">
                        <div class="kis-gcard__name">
                            <a href="{{ $this->viewUrl($group) }}">{{ $group->name }}</a>
                            <x-kis.badge :tone="$group->status === 'active' ? 'blue' : 'neutral'" dot size="sm">{{ $group->status === 'active' ? 'Aktif' : 'Nonaktif' }}</x-kis.badge>
                        </div>
                        <p class="kis-gcard__meta">{{ $contact ?: 'Tanpa kontak' }}</p>
                    </div>
                    <a href="{{ $this->editUrl($group) }}" class="kis-gcard__icon" title="Ubah grup" aria-label="Ubah grup {{ $group->name }}">
                        <x-heroicon-m-pencil-square class="h-4 w-4" />
                    </a>
                </header>

                <div class="kis-gcard__label">Perusahaan · {{ $total }}</div>
                @if ($total === 0)
                    <p class="kis-gcard__empty">Belum ada perusahaan di grup ini.</p>
                @else
                    <ul class="kis-gcard__list">
                        @foreach ($members->take(GroupList::MEMBER_PREVIEW) as $member)
                            <li wire:key="group-{{ $group->id }}-member-{{ $member->id }}">
                                <a href="{{ $this->clientUrl($member) }}" class="kis-gcard__member">
                                    <span class="kis-gtile is-sm" aria-hidden="true">{{ ClientAvatar::initials($member->name) }}</span>
                                    <span class="kis-gcard__who">
                                        <b>{{ $member->name }}</b>
                                        @if ($member->client_type)
                                            <small>{{ $member->client_type }}{{ $member->client_subtype ? ' · ' . $member->client_subtype : '' }}</small>
                                        @endif
                                    </span>
                                    <span @class(['kis-gcard__st', 'is-on' => $member->status === 'Active'])>{{ $member->status === 'Active' ? 'Aktif' : 'Nonaktif' }}</span>
                                </a>
                            </li>
                        @endforeach
                    </ul>
                    @if ($total > GroupList::MEMBER_PREVIEW)
                        <a href="{{ $this->viewUrl($group) }}" class="kis-gcard__more">+{{ $total - GroupList::MEMBER_PREVIEW }} perusahaan lainnya →</a>
                    @endif
                @endif

                <footer class="kis-gcard__foot">
                    <div class="kis-gcard__meter">
                        <span>{{ $active }} dari {{ $total }} aktif</span>
                        <i aria-hidden="true"><b style="width: {{ $total ? round($active / $total * 100) : 0 }}%"></b></i>
                    </div>
                    <button type="button" wire:click="openAddClient({{ $group->id }})" class="kis-btn kis-btn--sm kis-btn--outline">
                        <x-heroicon-m-plus class="h-4 w-4 text-gray-500 dark:text-gray-400" /> Tambah client
                    </button>
                    <a href="{{ $this->viewUrl($group) }}" class="kis-btn kis-btn--sm kis-btn--outline">Buka →</a>
                </footer>
            </article>
        @endforeach

        @if ($hasFilters && $groups->isEmpty())
            <div class="kis-gnew is-static">
                <b>Tidak ada grup yang cocok</b>
                <span>Coba kata kunci lain atau ganti filter status.</span>
                <button type="button" wire:click="clearFilters" class="kis-btn kis-btn--sm kis-btn--outline">Reset filter</button>
            </div>
        @elseif (! $hasFilters)
            <a href="{{ ClientGroupResource::getUrl('create') }}" class="kis-gnew">
                <span class="kis-gtile is-muted" aria-hidden="true"><x-heroicon-m-plus class="h-5 w-5" /></span>
                <b>Buat grup baru</b>
                <span>Kelompokkan klien yang satu grup usaha.<br><strong>{{ number_format($this->ungroupedCount, 0, ',', '.') }} klien</strong> belum punya grup.</span>
                <span class="kis-btn kis-btn--sm kis-btn--primary">Tambah grup</span>
            </a>
        @endif
    </div>

    {{-- Modal: add clients to a group --}}
    <x-filament::modal id="add-client-to-group" width="lg" icon="heroicon-o-user-plus" footer-actions-alignment="end">
        <x-slot name="heading">Tambah client ke {{ $this->addGroup?->name ?? 'grup' }}</x-slot>
        <x-slot name="description">Pilih client yang belum tergabung dalam grup mana pun.</x-slot>

        <div class="space-y-3">
            <label class="kis-groups__search is-wide">
                <x-heroicon-m-magnifying-glass class="h-4 w-4 shrink-0" />
                <input type="search" wire:model.live.debounce.300ms="clientSearch" placeholder="Cari nama atau NPWP" aria-label="Cari client">
            </label>

            <div class="kis-groups__pick">
                @forelse ($this->availableClients as $ac)
                    <label wire:key="pick-{{ $ac->id }}">
                        <input type="checkbox" value="{{ $ac->id }}" wire:model.live="newClientIds"
                               class="h-4 w-4 rounded border-gray-300 text-primary-600 focus:ring-primary-500 dark:border-white/20 dark:bg-white/5 dark:checked:bg-primary-500">
                        <span class="kis-gtile is-sm" aria-hidden="true">{{ ClientAvatar::initials($ac->name) }}</span>
                        <span class="kis-gcard__who">
                            <b>{{ $ac->name }}</b>
                            <small>{{ collect([$ac->client_type, $ac->NPWP])->filter()->implode(' · ') ?: 'Tanpa NPWP' }}</small>
                        </span>
                    </label>
                @empty
                    <p class="kis-groups__pick-empty">{{ $clientSearch !== '' ? 'Tidak ada client yang cocok.' : 'Semua client sudah tergabung dalam grup.' }}</p>
                @endforelse
            </div>
        </div>

        <x-slot name="footerActions">
            <x-filament::button color="gray" x-on:click="$dispatch('close-modal', { id: 'add-client-to-group' })">Batal</x-filament::button>
            <x-filament::button wire:click="saveAddClient" wire:loading.attr="disabled" :disabled="empty($newClientIds)">
                Tambahkan{{ count($newClientIds) ? ' (' . count($newClientIds) . ')' : '' }}
            </x-filament::button>
        </x-slot>
    </x-filament::modal>
</div>
