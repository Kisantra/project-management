@php
    use App\Livewire\Clients\ClientListClickup;
    $statuses = ClientListClickup::STATUSES;
    $types    = ClientListClickup::TYPES;
    $pkps     = ClientListClickup::PKP_STATUSES;
    $contracts = ClientListClickup::CONTRACTS;
    $activeCount = $this->activeFilterCount;
    $groupByOptions = ClientListClickup::GROUP_BY;
    $summary = $this->summary;
    $tile = $this->activeTile();
    $pct = fn (int $n) => $summary['total'] > 0 ? round($n / $summary['total'] * 100, 1) : 0;
@endphp

<div class="cl-root">
    {{-- ============== SUMMARY (doubles as quick filters) ============== --}}
    @php
        $tiles = [
            'all' => ['label' => 'Semua klien aktif', 'value' => $summary['total'], 'sub' => $summary['badan'] . ' Badan · ' . $summary['pribadi'] . ' Pribadi',
                'bar' => [[$pct($summary['badan']), 'var(--kis-ramp-b3)'], [$pct($summary['pribadi']), 'rgb(var(--gray-400))']]],
            'pkp' => ['label' => 'PKP', 'value' => $summary['pkp'], 'sub' => 'PKP · ' . ($summary['total'] - $summary['pkp']) . ' Non-PKP',
                'bar' => [[$pct($summary['pkp']), 'var(--kis-ramp-b3)'], [100 - $pct($summary['pkp']), 'rgb(var(--gray-400))']]],
            'contract' => ['label' => 'Berkontrak pajak', 'value' => $summary['contracted'], 'sub' => 'dari ' . $summary['total'] . ' klien aktif', 'chips' => $summary['contracts']],
            'nopic' => ['label' => 'Tanpa PIC', 'value' => $summary['noPic'], 'sub' => 'klien aktif belum punya PIC', 'alert' => $summary['noPic'] > 0,
                'bar' => [[$pct($summary['noPic']), 'var(--kis-bar-red)'], [100 - $pct($summary['noPic']), 'var(--kis-ramp-b3)']]],
        ];
    @endphp
    <div class="grid grid-cols-1 gap-px overflow-hidden rounded-[14px] border border-gray-200 bg-gray-200 sm:grid-cols-2 xl:grid-cols-4 dark:border-white/10 dark:bg-white/10" role="group" aria-label="Ringkasan klien">
        @foreach ($tiles as $key => $t)
            <button type="button" wire:click="quickFilter('{{ $key }}')" aria-pressed="{{ $tile === $key ? 'true' : 'false' }}"
                    @class([
                        'px-4 py-3.5 text-left transition',
                        'bg-primary-50 shadow-[inset_0_-2px_0_rgb(var(--primary-600))] dark:bg-primary-500/10 dark:shadow-[inset_0_-2px_0_rgb(var(--primary-400))]' => $tile === $key,
                        'bg-white hover:bg-gray-50 dark:bg-gray-900 dark:hover:bg-white/[.03]' => $tile !== $key,
                    ])>
                <span class="block text-[12.5px] font-bold text-gray-700 dark:text-gray-300">{{ $t['label'] }}</span>
                <span class="mt-1.5 flex items-baseline gap-2">
                    <b @class(['text-2xl font-extrabold tracking-tight tabular-nums', 'text-danger-700 dark:text-danger-300' => $t['alert'] ?? false, 'text-gray-950 dark:text-white' => ! ($t['alert'] ?? false)])>{{ number_format($t['value'], 0, ',', '.') }}</b>
                    <span class="truncate text-[12.5px] font-semibold text-gray-500 dark:text-gray-400">{{ $t['sub'] }}</span>
                </span>
                @isset($t['chips'])
                    <span class="mt-2 flex flex-wrap gap-1">
                        @foreach ($t['chips'] as $label => $count)
                            <x-kis.badge tone="neutral" size="sm">{{ $label }} {{ $count }}</x-kis.badge>
                        @endforeach
                    </span>
                @else
                    <span class="mt-2.5 flex h-1.5 gap-0.5 overflow-hidden rounded-full bg-gray-100 dark:bg-white/5">
                        @foreach ($t['bar'] as [$w, $color])
                            @if ($w > 0)<i class="block h-full" style="width: {{ $w }}%; background: {{ $color }}"></i>@endif
                        @endforeach
                    </span>
                @endisset
            </button>
        @endforeach
    </div>

    {{-- ============== TOOLBAR ============== --}}
    <div class="cl-toolbar">
        <div class="cl-search" x-data="{ focus() { $refs.searchInput.focus(); } }"
             @keydown.window.meta.k.prevent="focus()"
             @keydown.window.ctrl.k.prevent="focus()">
            <svg class="cl-search-icon" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <circle cx="11" cy="11" r="8"/>
                <path d="m21 21-4.3-4.3"/>
            </svg>
            <input type="text"
                   x-ref="searchInput"
                   wire:model.live.debounce.300ms="search"
                   name="cl_search"
                   autocomplete="off"
                   data-1p-ignore
                   data-lpignore="true"
                   data-form-type="other"
                   placeholder="Cari nama, NPWP, atau email…">

            {{-- Loading spinner — visible only while Livewire is processing the search --}}
            <span wire:loading wire:target="search" class="cl-search-spinner" aria-hidden="true">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round">
                    <path d="M21 12a9 9 0 1 1-6.219-8.56"/>
                </svg>
            </span>

            {{-- Clear button — appears only when there's a query --}}
            @if ($search !== '')
                <button type="button"
                        wire:click="$set('search', '')"
                        wire:loading.remove
                        wire:target="search"
                        class="cl-search-clear"
                        aria-label="Clear search">
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round">
                        <path d="M18 6 6 18"/>
                        <path d="m6 6 12 12"/>
                    </svg>
                </button>
            @endif

            <kbd class="cl-search-kbd">⌘K</kbd>
        </div>

        {{-- Group by (icon-only) --}}
        <div class="cl-filter cl-dropdown {{ $groupBy !== 'none' ? 'is-active' : '' }}" x-data="{ open: false }">
            <button type="button" @click="open = !open" class="cl-filter-btn" aria-haspopup="listbox">
                <span class="cl-filter-label">Kelompokkan</span>
                <span class="cl-filter-value">{{ $groupByOptions[$groupBy] ?? 'Tipe' }}</span>
                <svg class="cl-filter-caret" width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><polyline points="6 9 12 15 18 9"/></svg>
            </button>
            <div x-show="open" @click.outside="open = false" x-cloak class="cl-filter-mega" style="width: 220px;">
                <div class="cl-filter-mega-body">
                    @foreach ($groupByOptions as $key => $label)
                        <button type="button"
                                wire:click="$set('groupBy', '{{ $key }}')"
                                @click="open = false"
                                class="cl-dropdown-row {{ $groupBy === $key ? 'is-active' : '' }}">
                            <span>{{ $label }}</span>
                            @if ($groupBy === $key)
                                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                            @endif
                        </button>
                    @endforeach
                </div>
            </div>
        </div>

        {{-- Filter button --}}
        <div class="cl-filter cl-dropdown {{ $activeCount > 0 ? 'is-active' : '' }}" x-data="{ open: false, sections: { status: true, type: true, pkp: false, contract: false } }">
            <button type="button" @click="open = !open" class="cl-filter-btn">
                <svg class="cl-filter-ico" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18l-7 9v6l-4-2v-4z"/></svg>
                <span class="cl-filter-label">Filter</span>
                @if ($activeCount > 0)
                    <span class="cl-filter-count">{{ $activeCount }}</span>
                @endif
                <svg class="cl-filter-caret" width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><polyline points="6 9 12 15 18 9"/></svg>
            </button>

            <div x-show="open" @click.outside="open = false" x-cloak class="cl-filter-mega">
                <div class="cl-filter-mega-head">
                    <span class="cl-filter-mega-title">Filters</span>
                    @if ($this->hasActiveFilters())
                        <button type="button" wire:click="clearFilters" class="cl-filter-mega-clear">Kembalikan bawaan</button>
                    @endif
                </div>

                <div class="cl-filter-mega-body">
                    {{-- Status --}}
                    <div class="cl-fs">
                        <button type="button" @click="sections.status = !sections.status" class="cl-fs-head">
                            <span class="cl-fs-name">Status</span>
                            @if (count($statusFilter)) <span class="cl-fs-count">{{ count($statusFilter) }}</span> @endif
                            <svg class="cl-fs-caret" :class="{ 'is-open': sections.status }" width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><polyline points="6 9 12 15 18 9"/></svg>
                        </button>
                        <div x-show="sections.status" class="cl-fs-body">
                            @foreach ($statuses as $key => $meta)
                                <label class="cl-dropdown-item">
                                    <input type="checkbox" wire:model.live="statusFilter" value="{{ $key }}">
                                    <x-kis.badge :tone="$meta['tone']" size="sm">{{ $meta['label'] }}</x-kis.badge>
                                </label>
                            @endforeach
                        </div>
                    </div>

                    {{-- Client Type --}}
                    <div class="cl-fs">
                        <button type="button" @click="sections.type = !sections.type" class="cl-fs-head">
                            <span class="cl-fs-name">Client Type</span>
                            @if (count($typeFilter)) <span class="cl-fs-count">{{ count($typeFilter) }}</span> @endif
                            <svg class="cl-fs-caret" :class="{ 'is-open': sections.type }" width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><polyline points="6 9 12 15 18 9"/></svg>
                        </button>
                        <div x-show="sections.type" class="cl-fs-body">
                            @foreach ($types as $key => $meta)
                                <label class="cl-dropdown-item">
                                    <input type="checkbox" wire:model.live="typeFilter" value="{{ $key }}">
                                    <x-kis.badge :tone="$meta['tone']" size="sm">{{ $meta['label'] }}</x-kis.badge>
                                </label>
                            @endforeach
                        </div>
                    </div>

                    {{-- PKP --}}
                    <div class="cl-fs">
                        <button type="button" @click="sections.pkp = !sections.pkp" class="cl-fs-head">
                            <span class="cl-fs-name">PKP Status</span>
                            @if (count($pkpFilter)) <span class="cl-fs-count">{{ count($pkpFilter) }}</span> @endif
                            <svg class="cl-fs-caret" :class="{ 'is-open': sections.pkp }" width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><polyline points="6 9 12 15 18 9"/></svg>
                        </button>
                        <div x-show="sections.pkp" class="cl-fs-body">
                            @foreach ($pkps as $key => $meta)
                                <label class="cl-dropdown-item">
                                    <input type="checkbox" wire:model.live="pkpFilter" value="{{ $key }}">
                                    <x-kis.badge :tone="$meta['tone']" size="sm">{{ $meta['label'] }}</x-kis.badge>
                                </label>
                            @endforeach
                        </div>
                    </div>

                    {{-- Jenis Kontrak --}}
                    <div class="cl-fs">
                        <button type="button" @click="sections.contract = !sections.contract" class="cl-fs-head">
                            <span class="cl-fs-name">Jenis Kontrak</span>
                            @if (count($contractFilter)) <span class="cl-fs-count">{{ count($contractFilter) }}</span> @endif
                            <svg class="cl-fs-caret" :class="{ 'is-open': sections.contract }" width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><polyline points="6 9 12 15 18 9"/></svg>
                        </button>
                        <div x-show="sections.contract" class="cl-fs-body">
                            @foreach ($contracts as $col => $label)
                                <label class="cl-dropdown-item">
                                    <input type="checkbox" wire:model.live="contractFilter" value="{{ $col }}">
                                    <span class="cl-filter-option-label">{{ $label }}</span>
                                </label>
                            @endforeach
                        </div>
                    </div>

                    {{-- PIC --}}
                    <div class="cl-fs">
                        <label class="cl-dropdown-item">
                            <input type="checkbox" wire:model.live="noPic">
                            <span class="cl-filter-option-label">Hanya klien tanpa PIC</span>
                        </label>
                    </div>
                </div>
            </div>
        </div>

        <div class="cl-toolbar-spacer"></div>

        {{-- Sort --}}
        <div class="cl-filter cl-dropdown" x-data="{ open: false }">
            <button type="button" @click="open = !open" class="cl-filter-btn" aria-haspopup="listbox">
                <span class="cl-filter-label">Urut</span>
                <span class="cl-filter-value">{{ ClientListClickup::SORTS[$this->currentSort()] }}</span>
                <svg class="cl-filter-caret" width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><polyline points="6 9 12 15 18 9"/></svg>
            </button>
            <div x-show="open" @click.outside="open = false" x-cloak class="cl-filter-mega cl-filter-mega-end" style="width: 220px;">
                <div class="cl-filter-mega-body">
                    @foreach (ClientListClickup::SORTS as $key => $label)
                        <button type="button" wire:click="setSort('{{ $key }}')" @click="open = false" class="cl-dropdown-row {{ $this->currentSort() === $key ? 'is-active' : '' }}">
                            <span>{{ $label }}</span>
                            @if ($this->currentSort() === $key)
                                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                            @endif
                        </button>
                    @endforeach
                </div>
            </div>
        </div>

        {{-- Columns toggle (icon-only) --}}
        @php $toggleable = \App\Livewire\Clients\ClientListClickup::TOGGLEABLE_COLUMNS; @endphp
        <div class="cl-filter cl-dropdown" x-data="{ open: false }">
            <button type="button" @click="open = !open" class="cl-filter-btn" aria-haspopup="dialog">
                <span class="cl-filter-label">Kolom</span>
                <svg class="cl-filter-caret" width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><polyline points="6 9 12 15 18 9"/></svg>
            </button>
            <div x-show="open" @click.outside="open = false" x-cloak class="cl-filter-mega cl-filter-mega-end" style="width: 200px;">
                <div class="cl-filter-mega-body">
                    @foreach ($toggleable as $key => $label)
                        <label class="cl-dropdown-item">
                            <input type="checkbox" wire:click="toggleColumn('{{ $key }}')" {{ $this->isColumnVisible($key) ? 'checked' : '' }}>
                            <span>{{ $label }}</span>
                        </label>
                    @endforeach
                </div>
            </div>
        </div>
    </div>

    {{-- ============== ACTIVE CHIPS ============== --}}
    @if ($this->hasActiveFilters())
        <x-kis.active-filters>
            @if ($search !== '')
                <x-kis.filter-chip wire:click="$set('search', '')">Cari: “{{ $search }}”</x-kis.filter-chip>
            @endif

            @foreach ($statusFilter as $key)
                @if (isset($statuses[$key]))
                    <x-kis.filter-chip wire:click="removeStatus('{{ $key }}')">Status: {{ $statuses[$key]['label'] }}</x-kis.filter-chip>
                @endif
            @endforeach

            @foreach ($typeFilter as $key)
                @if (isset($types[$key]))
                    <x-kis.filter-chip wire:click="removeType('{{ $key }}')">Tipe: {{ $types[$key]['label'] }}</x-kis.filter-chip>
                @endif
            @endforeach

            @foreach ($pkpFilter as $key)
                @if (isset($pkps[$key]))
                    <x-kis.filter-chip wire:click="removePkp('{{ $key }}')">PKP: {{ $pkps[$key]['label'] }}</x-kis.filter-chip>
                @endif
            @endforeach

            @if (count(array_intersect($contractFilter, array_keys($contracts))) === count($contracts))
                {{-- All four contract types = "has any tax contract" (the summary tile): one chip. --}}
                <x-kis.filter-chip wire:click="$set('contractFilter', [])">Berkontrak pajak</x-kis.filter-chip>
            @else
                @foreach ($contractFilter as $col)
                    @if (isset($contracts[$col]))
                        <x-kis.filter-chip wire:click="removeContract('{{ $col }}')">Kontrak: {{ $contracts[$col] }}</x-kis.filter-chip>
                    @endif
                @endforeach
            @endif

            @if ($noPic)
                <x-kis.filter-chip wire:click="removeNoPic">Tanpa PIC</x-kis.filter-chip>
            @endif

            {{-- clearFilters() resets to the defaults (Active clients only), so say that. --}}
            @unless ($search === '' && $statusFilter === ['Active'] && ! $typeFilter && ! $pkpFilter && ! $contractFilter && ! $noPic)
                <x-slot name="reset" wire:click="clearFilters">Kembalikan bawaan</x-slot>
            @endunless
        </x-kis.active-filters>
    @endif

    {{-- ============== TABLE (one frame: header, groups, rows, total) ============== --}}
    <div class="cl-table">

    {{-- ============== TABLE HEADER ============== --}}
    @php $gridStyle = 'grid-template-columns: ' . $this->gridTemplate . ';'; @endphp
    <div class="cl-table-head" style="{{ $gridStyle }}">
        <div class="cl-col-logo"></div>
        <div class="cl-col-name cl-sortable" wire:click="sortBy('name')">
            Client
            @if ($sortField === 'name') <span class="cl-sort-arrow">{{ $sortDirection === 'asc' ? '↑' : '↓' }}</span> @endif
        </div>
        @if ($this->isColumnVisible('contract'))
            <div class="cl-col-contract">Kontrak</div>
        @endif
        @if ($this->isColumnVisible('pic'))
            <div class="cl-col-pic">PIC</div>
        @endif
        <div class="cl-col-status cl-sortable" wire:click="sortBy('status')">
            Status
            @if ($sortField === 'status') <span class="cl-sort-arrow">{{ $sortDirection === 'asc' ? '↑' : '↓' }}</span> @endif
        </div>
        @if ($this->isColumnVisible('type'))
            <div class="cl-col-type">Tipe</div>
        @endif
        @if ($this->isColumnVisible('pkp'))
            <div class="cl-col-pkp">PKP</div>
        @endif
        @if ($this->isColumnVisible('group'))
            <div class="cl-col-group">Grup</div>
        @endif
        <div class="cl-col-actions"></div>
    </div>

    {{-- ============== ROWS (grouped) ============== --}}
    @php $isAnyData = false; @endphp
    @foreach ($grouped as $gKey => $gData) @php $isAnyData = $isAnyData || $gData['total'] > 0; @endphp @endforeach

    @if (!$isAnyData)
        <div class="cl-empty">
            <div class="cl-empty-icon">🏢</div>
            <h3>Tidak ada klien yang cocok</h3>
            <p>Ubah pencarian atau filter, atau tambah klien baru.</p>
        </div>
    @else
        @foreach ($grouped as $groupKey => $groupData)
            @php
                $groupMeta = $this->getGroupColor((string) $groupKey);
                $groupLabel = $this->getGroupLabel((string) $groupKey);
                $groupShape = match (true) {
                    $groupBy === 'status' && $groupKey === 'Active'   => 'check',
                    $groupBy === 'status' && $groupKey === 'Inactive' => 'x',
                    $groupBy === 'pkp'    && $groupKey === 'PKP'      => 'check',
                    default => 'empty',
                };
            @endphp

            {{-- A group is a header row plus its rows inside the one table, not a separate card. --}}
            <div class="cl-group" wire:key="group-{{ md5((string) $groupKey) }}">
            @if ($groupBy !== 'none')
                <div class="cl-group-row">
                    <span class="cl-group-badge" data-kis-tone="{{ $groupMeta['tone'] ?? 'neutral' }}">
                        <span class="cl-group-badge-icon">
                            @include('livewire.projects.partials.status-shape', ['shape' => $groupShape, 'color' => $groupMeta['color'], 'tone' => $groupMeta['tone'] ?? 'neutral', 'size' => 14])
                        </span>
                        <span class="cl-group-badge-label">{{ $groupLabel }}</span>
                    </span>
                    <span class="cl-group-count-num">{{ $groupData['total'] }} klien</span>
                    <span class="cl-group-mini">{{ $groupData['pkp'] }} PKP · {{ $groupData['contracted'] }} berkontrak</span>
                </div>
            @endif

            @foreach ($groupData['visible'] as $client)
                @php
                    $statusMeta = $statuses[$client->status] ?? ['label' => $client->status ?? '—', 'tone' => 'neutral'];
                    $typeMeta   = $types[$client->client_type] ?? null;
                    $pkpMeta    = $pkps[$client->pkp_status] ?? null;

                    // Deal dari Client Management yang diproses menjadi klien ini (bila ada)
                    $intake = $client->latestIntake;
                    $originTone = match (true) {
                        $intake === null => null,
                        $intake->isWithdrawn() => 'withdrawn',
                        $intake->matched => 'matched',
                        default => 'created',
                    };
                    $originTitle = match ($originTone) {
                        'withdrawn' => 'Deal untuk klien ini dibatalkan di Client Management (aplikasi tim marketing) setelah diproses.',
                        'matched' => 'Klien yang sudah ada, menerima deal layanan baru dari Client Management (aplikasi tim marketing).',
                        'created' => 'Klien ini dibuat dari deal di Client Management (aplikasi tim marketing).',
                        default => null,
                    };

                    // Logo resolution — http(s) URL or public-disk relative path
                    $logoSrc = null;
                    if ($client->logo) {
                        $logoSrc = \Illuminate\Support\Str::startsWith($client->logo, ['http://', 'https://'])
                            ? $client->logo
                            : (\Illuminate\Support\Facades\Storage::disk('public')->exists($client->logo)
                                ? \Illuminate\Support\Facades\Storage::disk('public')->url($client->logo)
                                : asset($client->logo));
                    }

                    // Initials fallback — 2 letters from first + last word of name
                    $words = preg_split('/\s+/', trim($client->name ?? ''));
                    $logoInitials = count($words) >= 2
                        ? strtoupper(mb_substr($words[0], 0, 1) . mb_substr($words[count($words) - 1], 0, 1))
                        : strtoupper(mb_substr($client->name ?? '?', 0, 2));
                @endphp

                <div class="cl-row" wire:key="client-{{ $client->id }}" style="{{ $gridStyle }}"
                     x-data x-on:click="if (! $event.target.closest('a, button, input, label, .cl-act-wrap')) window.location = @js($this->viewUrl($client))">
                    {{-- Logo (circle) --}}
                    <div class="cl-col-logo">
                        {{-- Initials always underneath; a logo that fails to load removes itself (works before Alpine starts). --}}
                        <span class="cl-logo">
                            <span class="cl-logo-initials">{{ $logoInitials }}</span>
                            @if ($logoSrc)
                                <img src="{{ $logoSrc }}" alt="" loading="lazy" onerror="this.remove()">
                            @endif
                        </span>
                    </div>

                    {{-- Name + NPWP/subtype meta --}}
                    <div class="cl-col-name">
                        <a href="{{ $this->viewUrl($client) }}" class="cl-client-name" title="{{ $client->name }}">{{ $client->name }}</a>
                        <div class="cl-client-meta">
                            @if ($originTone)
                                <span class="cl-origin cl-origin-{{ $originTone }}" title="{{ $originTitle }}">{{ $intake->originLabel() }}</span>
                            @endif
                            @if ($client->NPWP)
                                <span class="cl-npwp">NPWP {{ ClientListClickup::formatNpwp($client->NPWP) }}</span>
                            @endif
                            @if ($client->client_subtype)
                                @if ($client->NPWP) <span class="cl-meta-sep">·</span> @endif
                                <span class="cl-subtype">{{ $client->client_subtype }}</span>
                            @endif
                        </div>
                    </div>

                    {{-- Tax contracts: two badges, the rest as +N (full list in the title) --}}
                    @if ($this->isColumnVisible('contract'))
                        @php $clientContracts = ClientListClickup::contractLabels($client); @endphp
                        <div class="cl-col-contract">
                            @if ($clientContracts)
                                <div class="flex min-w-0 gap-1 overflow-hidden" title="{{ implode(', ', $clientContracts) }}">
                                    @foreach (array_slice($clientContracts, 0, 2) as $label)
                                        <x-kis.badge tone="blue" size="sm">{{ $label }}</x-kis.badge>
                                    @endforeach
                                    @if (count($clientContracts) > 2)
                                        <x-kis.badge tone="neutral" size="sm">+{{ count($clientContracts) - 2 }}</x-kis.badge>
                                    @endif
                                </div>
                            @else
                                <span class="cl-empty-cell text-xs">Tanpa kontrak</span>
                            @endif
                        </div>
                    @endif

                    {{-- PIC --}}
                    @if ($this->isColumnVisible('pic'))
                        <div class="cl-col-pic min-w-0">
                            @if ($client->pic)
                                <span class="flex min-w-0 items-center gap-2 text-[13px] font-semibold text-gray-700 dark:text-gray-300">
                                    <span class="grid h-6 w-6 shrink-0 place-items-center rounded-full bg-gray-100 text-[10px] font-extrabold text-gray-600 dark:bg-white/5 dark:text-gray-300">{{ \App\Services\Dashboard\HomeDashboard::initials($client->pic->name) }}</span>
                                    <span class="truncate" title="{{ $client->pic->name }}">{{ $client->pic->name }}</span>
                                </span>
                            @else
                                <span class="text-[13px] font-semibold italic text-danger-700 dark:text-danger-300">Belum ada PIC</span>
                            @endif
                        </div>
                    @endif

                    {{-- Status pill --}}
                    <div class="cl-col-status">
                        <x-kis.badge :tone="$statusMeta['tone']" dot>{{ $statusMeta['label'] }}</x-kis.badge>
                    </div>

                    {{-- Client type pill --}}
                    @if ($this->isColumnVisible('type'))
                        <div class="cl-col-type">
                            @if ($typeMeta)
                                <x-kis.badge :tone="$typeMeta['tone']">{{ $typeMeta['label'] }}</x-kis.badge>
                            @else
                                <span class="cl-empty-cell">—</span>
                            @endif
                        </div>
                    @endif

                    {{-- PKP --}}
                    @if ($this->isColumnVisible('pkp'))
                        <div class="cl-col-pkp">
                            @if ($pkpMeta)
                                <x-kis.badge :tone="$pkpMeta['tone']">{{ $pkpMeta['label'] }}</x-kis.badge>
                            @else
                                <span class="cl-empty-cell">—</span>
                            @endif
                        </div>
                    @endif

                    {{-- Group --}}
                    @if ($this->isColumnVisible('group'))
                        <div class="cl-col-group">
                            @if ($client->group)
                                <span class="cl-group-name">{{ $client->group->name }}</span>
                            @else
                                <span class="cl-empty-cell">—</span>
                            @endif
                        </div>
                    @endif

                    {{-- Actions --}}
                    <div class="cl-col-actions">
                        {{-- Prominent credential button — labeled, accent-tinted --}}
                        <button type="button"
                                wire:click="openCredentials({{ $client->id }})"
                                class="cl-cred-pill"
                                title="Lihat Kredensial Aplikasi">
                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <circle cx="8" cy="15" r="4"/>
                                <line x1="10.85" y1="12.15" x2="19" y2="4"/>
                                <line x1="18" y1="5" x2="20" y2="7"/>
                                <line x1="15" y1="8" x2="17" y2="10"/>
                            </svg>
                            <span class="cl-cred-pill-label">Kredensial</span>
                        </button>

                        <div x-data="{ open: false }" class="cl-act-wrap">
                            <button type="button" @click="open = !open" class="cl-act-btn" aria-label="Actions">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor"><circle cx="12" cy="5" r="1.5"/><circle cx="12" cy="12" r="1.5"/><circle cx="12" cy="19" r="1.5"/></svg>
                            </button>
                            <div x-show="open" @click.outside="open = false" x-cloak class="cl-act-menu">
                                <a href="{{ $this->viewUrl($client) }}" class="cl-act-item">Lihat</a>
                                <a href="{{ $this->editUrl($client) }}" class="cl-act-item">Ubah</a>
                            </div>
                        </div>
                    </div>
                </div>
            @endforeach

            {{-- "Show all" expand button per group --}}
            @if ($groupData['hasMore'])
                <div class="cl-group-more">
                    <button wire:click="toggleGroupExpand('{{ addslashes((string) $groupKey) }}')" class="cl-show-more-btn">
                        @if ($groupData['expanded'])
                            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><polyline points="18 15 12 9 6 15"/></svg>
                            Tampilkan lebih sedikit
                        @else
                            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><polyline points="6 9 12 15 18 9"/></svg>
                            Tampilkan {{ $groupData['hidden'] }} lainnya
                        @endif
                    </button>
                </div>
            @endif
            </div> {{-- /cl-group --}}
        @endforeach
    @endif

    {{-- ============== TOTAL / CAP NOTICE ============== --}}
    @if ($isAnyData)
        <div class="cl-list-footer">
            <span class="cl-list-total">
                <b>{{ $totalCount }}</b> klien
                @if ($isCapped)
                    <span class="cl-list-cap">· menampilkan {{ \App\Livewire\Clients\ClientListClickup::HARD_CAP }} pertama</span>
                @endif
            </span>
            <span class="cl-list-hint">Klik baris untuk membuka detail klien</span>
        </div>
    @endif
    </div> {{-- /cl-table --}}

    {{-- ============== CREDENTIAL MODAL ============== --}}
    {{-- The credential manager draws its own header and footer; closing goes through it,
         so Escape or a backdrop click can ask before throwing away unsaved edits. --}}
    @if ($viewingCredentialsClientId && $this->credentialClient)
        <div class="cl-cred-overlay" x-data x-on:kis-cred-close="$wire.closeCredentials()">
            <div class="cl-cred-backdrop" x-on:click="$dispatch('kis-cred-dismiss')"></div>
            <div class="cl-cred-modal" role="dialog" aria-modal="true" aria-labelledby="kis-cred-title-{{ $viewingCredentialsClientId }}" x-trap.noscroll="true">
                @livewire('client.management.credential-manager', ['clientId' => $viewingCredentialsClientId, 'closable' => true], key('cred-' . $viewingCredentialsClientId))
            </div>
        </div>
    @endif

    @once
        <style>
            [x-cloak] { display: none !important; }

            .cl-root {
                /* Tokens map onto the panel palette (AdminPanelProvider::palette(), DESIGN.md). */
                --cl-ink: rgb(var(--gray-950));
                --cl-muted: rgb(var(--gray-500));
                --cl-subtle: rgb(var(--gray-400));
                --cl-line: rgb(var(--gray-200));
                --cl-line-strong: rgb(var(--gray-300));
                --cl-bg: #ffffff;
                --cl-bg-soft: rgb(var(--gray-50));
                --cl-bg-hover: rgb(var(--gray-100));
                --cl-accent: rgb(var(--primary-600));
                --cl-accent-ink: rgb(var(--primary-700));
                --cl-accent-soft: rgb(var(--primary-50));
                font-family: inherit;
                color: var(--cl-ink);
                font-size: 13.5px;
                display: flex;
                flex-direction: column;
                gap: 14px;
            }

            /* Dark: the panel's own tinted gray, so the list matches the rest of the app. */
            .dark .cl-root {
                --cl-ink: #ffffff;
                --cl-muted: rgb(var(--gray-400));
                --cl-subtle: rgb(var(--gray-500));
                --cl-line: rgba(255, 255, 255, .08);
                --cl-line-strong: rgba(255, 255, 255, .14);
                --cl-bg: rgb(var(--gray-900));
                --cl-bg-soft: rgba(255, 255, 255, .03);
                --cl-bg-hover: rgba(255, 255, 255, .05);
                --cl-accent: rgb(var(--primary-400));
                --cl-accent-ink: rgb(var(--primary-300));
                --cl-accent-soft: rgba(var(--primary-500), .14);
            }

            /* Toolbar */
            .cl-toolbar { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
            .cl-search {
                display: flex;
                align-items: center;
                gap: 10px;
                flex: 1 1 360px;
                max-width: 480px;
                height: 38px;
                padding: 0 8px 0 14px;
                background: var(--cl-bg-soft);
                border-radius: 10px;
                color: var(--cl-muted);
                box-shadow: inset 0 0 0 1px transparent;
                transition: background .15s, color .15s, box-shadow .15s;
            }
            .cl-search:hover { background: var(--cl-bg-hover); }
            .cl-search:focus-within {
                background: var(--cl-bg);
                color: var(--cl-ink);
                box-shadow:
                    inset 0 0 0 1px var(--cl-accent),
                    0 0 0 3px rgba(var(--primary-500), .12);
            }
            .cl-search:focus-within .cl-search-icon { color: var(--cl-accent-ink); }

            .cl-search-icon { flex-shrink: 0; transition: color .15s; }

            .cl-search input {
                background: transparent;
                border: 0; outline: 0;
                flex: 1;
                min-width: 0;
                font: inherit;
                font-size: 13px;
                color: var(--cl-ink);
            }
            .cl-search input::placeholder { color: var(--cl-subtle); }

            /* Loading spinner — sits in the same right slot as the clear button */
            .cl-search-spinner {
                display: inline-flex;
                align-items: center;
                justify-content: center;
                width: 22px; height: 22px;
                color: var(--cl-accent);
                flex-shrink: 0;
            }
            .cl-search-spinner svg {
                animation: cl-spin .8s linear infinite;
            }
            @keyframes cl-spin {
                to { transform: rotate(360deg); }
            }

            /* Clear (×) button — appears only when there's a query */
            .cl-search-clear {
                display: inline-flex;
                align-items: center;
                justify-content: center;
                width: 22px; height: 22px;
                background: transparent;
                border: 0;
                border-radius: 50%;
                color: var(--cl-muted);
                cursor: pointer;
                flex-shrink: 0;
                transition: background .12s, color .12s, transform .08s;
            }
            .cl-search-clear:hover {
                background: var(--cl-line);
                color: var(--cl-ink);
            }
            .cl-search-clear:active { transform: scale(.92); }

            /* ⌘K keyboard hint */
            .cl-search-kbd {
                font-family: 'SF Mono', Menlo, ui-monospace, monospace;
                font-size: 10.5px;
                font-weight: 600;
                color: var(--cl-muted);
                background: var(--cl-bg);
                padding: 3px 7px;
                border-radius: 6px;
                box-shadow:
                    inset 0 -1px 0 rgba(13, 20, 23, .06),
                    0 0 0 1px rgba(13, 20, 23, .06);
                letter-spacing: .02em;
                flex-shrink: 0;
            }
            .cl-search:focus-within .cl-search-kbd { opacity: .5; }

            /* Filter button */
            .cl-filter { position: relative; }
            .cl-filter-btn {
                display: inline-flex; align-items: center; gap: 7px;
                height: 38px; padding: 0 14px;
                background: var(--cl-bg);
                border: 0; border-radius: 10px;
                font: inherit; font-size: 13px; font-weight: 500;
                color: var(--cl-ink);
                cursor: pointer;
                box-shadow: inset 0 0 0 1px var(--cl-line-strong), 0 1px 2px rgba(13, 20, 23,.03);
                transition: box-shadow .14s, transform .08s;
            }
            .cl-filter-btn:hover { box-shadow: inset 0 0 0 1px var(--cl-muted), 0 2px 6px rgba(13, 20, 23,.07); }
            .cl-filter-btn:active { transform: translateY(1px); }

            /* Icon-only variant — square button, just the icon */
            .cl-filter-btn-icon {
                width: 38px;
                padding: 0;
                justify-content: center;
            }
            .cl-filter-ico { color: var(--cl-muted); flex-shrink: 0; }
            .cl-filter-label { color: var(--cl-muted); font-weight: 500; }
            .cl-filter-count {
                display: inline-flex; align-items: center; justify-content: center;
                min-width: 18px; height: 18px; padding: 0 6px;
                background: var(--cl-accent); color: white;
                font-size: 11px; font-weight: 700;
                border-radius: 99px; line-height: 1;
            }
            .cl-filter-caret { color: var(--cl-subtle); margin-left: 2px; }
            .cl-filter.is-active .cl-filter-btn {
                background: var(--cl-accent-soft);
                box-shadow: inset 0 0 0 1px var(--cl-accent), 0 1px 2px rgba(var(--primary-500), .08);
            }
            .cl-filter.is-active .cl-filter-ico, .cl-filter.is-active .cl-filter-label { color: var(--cl-accent-ink); }

            /* Mega panel */
            .cl-filter-mega {
                position: absolute; top: calc(100% + 8px); left: 0; z-index: 50;
                width: 320px; max-height: 480px;
                background: var(--cl-bg); border-radius: 14px;
                box-shadow: 0 0 0 1px rgba(13, 20, 23,.04), 0 16px 40px rgba(13, 20, 23,.14);
                display: flex; flex-direction: column; overflow: hidden;
            }
            .cl-filter-mega-end { left: auto; right: 0; }
            .cl-filter-mega-head {
                display: flex; align-items: center; justify-content: space-between;
                padding: 12px 16px;
                border-bottom: 1px solid var(--cl-line);
            }
            .cl-filter-mega-title { font-size: 13px; font-weight: 700; color: var(--cl-ink); }
            .cl-filter-mega-clear {
                font: inherit; font-size: 12px; font-weight: 600;
                color: var(--cl-accent-ink); background: transparent; border: 0;
                cursor: pointer; padding: 4px 8px; border-radius: 6px;
                transition: background .12s;
            }
            .cl-filter-mega-clear:hover { background: var(--cl-accent-soft); }
            .cl-filter-mega-body { flex: 1; overflow-y: auto; padding: 4px; }

            /* Section */
            .cl-fs + .cl-fs { border-top: 1px solid var(--cl-line); }
            .cl-fs-head {
                display: flex; align-items: center; gap: 8px;
                width: 100%; padding: 10px 12px;
                background: transparent; border: 0;
                font: inherit; font-size: 12.5px; font-weight: 600;
                color: var(--cl-ink); cursor: pointer; text-align: left;
                border-radius: 8px;
                transition: background .1s;
            }
            .cl-fs-head:hover { background: var(--cl-bg-hover); }
            .cl-fs-name { flex: 1; }
            .cl-fs-count {
                background: var(--cl-accent-soft); color: var(--cl-accent-ink);
                font-size: 10.5px; font-weight: 700;
                padding: 1px 7px; border-radius: 99px; line-height: 1.4;
            }
            .cl-fs-caret { color: var(--cl-subtle); transition: transform .18s; }
            .cl-fs-caret.is-open { transform: rotate(180deg); }
            .cl-fs-body { padding: 2px 6px 8px; }

            /* Checkbox */
            .cl-dropdown-item {
                display: flex; align-items: center; gap: 10px;
                padding: 8px 10px;
                border-radius: 8px;
                cursor: pointer;
                font-size: 12.5px;
                color: var(--cl-ink);
                transition: background .1s;
            }
            .cl-dropdown-item:hover { background: var(--cl-bg-hover); }
            .cl-dropdown-item input[type="checkbox"] {
                appearance: none; -webkit-appearance: none;
                width: 16px; height: 16px;
                border: 1.5px solid var(--cl-line-strong);
                border-radius: 5px;
                background: var(--cl-bg);
                cursor: pointer; position: relative; flex-shrink: 0;
                transition: border-color .12s, background .12s;
            }
            .cl-dropdown-item input[type="checkbox"]:hover { border-color: var(--cl-accent); }
            .cl-dropdown-item input[type="checkbox"]:checked {
                background: var(--cl-accent); border-color: var(--cl-accent);
            }
            .cl-dropdown-item input[type="checkbox"]:checked::after {
                content: ''; position: absolute; inset: 0;
                background-image: url("data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='white' stroke-width='3' stroke-linecap='round' stroke-linejoin='round'><polyline points='20 6 9 17 4 12'/></svg>");
                background-size: 11px; background-position: center; background-repeat: no-repeat;
            }

            .cl-toolbar-spacer { flex: 1; }
            .cl-toolbar-total { font-size: 12.5px; color: var(--cl-muted); font-weight: 500; }

            /* Active filters use the kis active-filters component (styles in theme.css). */

            /* Table head & rows — matches project list spacing.
               Grid columns are inlined per-render via $gridStyle (dynamic
               based on visible columns from the Columns toggle). */
            .cl-table-head, .cl-row {
                display: grid;
                gap: 10px;
                align-items: center;
            }
            /* One frame for the whole list. overflow: clip (not hidden) rounds the corners
               without becoming a scroll container, so the header can stay sticky. */
            .cl-table {
                background: var(--cl-bg);
                border: 1px solid var(--cl-line);
                border-radius: 14px;
                overflow: clip;
            }
            .cl-table-head {
                position: sticky;
                top: 4rem;                          /* below Filament's sticky topbar */
                z-index: 6;
                padding: 10px 20px;
                font-size: 11px; font-weight: 800;
                text-transform: uppercase; letter-spacing: .07em;
                color: var(--cl-muted);
                background: var(--cl-bg-soft);
                border-bottom: 1px solid var(--cl-line);
            }
            .dark .cl-table-head { background: rgb(var(--gray-900)); box-shadow: inset 0 -1px 0 var(--cl-line); }
            .cl-sortable { cursor: pointer; user-select: none; transition: color .12s; }
            .cl-sortable:hover { color: var(--cl-accent); }
            .cl-sort-arrow { color: var(--cl-accent); margin-left: 2px; }
            .cl-row {
                padding: 12px 20px;
                background: transparent;
                border-bottom: 1px solid var(--cl-line);
                transition: background .12s;
                cursor: pointer;
            }
            .cl-row > *, .cl-table-head > * { min-width: 0; }
            .cl-row:hover { background: var(--cl-bg-hover); }
            .cl-row:hover .cl-client-name { color: var(--cl-accent-ink); }

            /* Circle logo cell */
            .cl-col-logo { display: flex; align-items: center; justify-content: center; }
            /* Soft tile in the Kisantra style (initials), white with a hairline when there is a logo. */
            .cl-logo {
                position: relative;
                width: 36px; height: 36px;
                border-radius: 10px;
                background: rgb(var(--primary-100));
                color: rgb(var(--primary-700));
                display: inline-flex;
                align-items: center;
                justify-content: center;
                font-size: 11px;
                font-weight: 700;
                letter-spacing: .02em;
                overflow: hidden;
                flex-shrink: 0;
            }
            .cl-logo:has(img) {
                box-shadow: inset 0 0 0 1px var(--cl-line);
            }
            .dark .cl-logo {
                background: rgba(var(--primary-500), .16);
                color: rgb(var(--primary-300));
            }
            .cl-logo img { position: absolute; inset: 0; width: 100%; height: 100%; object-fit: cover; display: block; background: white; }
            .cl-logo-initials { user-select: none; }

            /* Name col */
            /* One line each, so every row has the same height; the full text is in the title. */
            .cl-client-name {
                display: block;
                overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
                font-weight: 700; font-size: 14px;
                color: var(--cl-ink); text-decoration: none;
                transition: color .12s;
            }
            .cl-client-name:hover { color: var(--cl-accent-ink); }
            .cl-row > .cl-col-name { min-width: 0; }
            .cl-client-meta {
                display: flex; align-items: center; gap: 7px;
                margin-top: 2px;
                font-size: 11px; color: var(--cl-muted);
            }
            .cl-npwp { font-size: 11px; font-variant-numeric: tabular-nums; letter-spacing: .01em; }
            .cl-meta-sep { color: var(--cl-subtle); }
            .cl-subtype { color: var(--cl-muted); }

            /* PIC */
            .cl-pic-cell { display: flex; align-items: center; gap: 8px; min-width: 0; }
            .cl-pic-cell > * { flex-shrink: 0; }
            .cl-pic-cell .cu-avatar,
            .cl-pic-cell .cu-avatar-inner {
                width: 24px; height: 24px;
                border-radius: 50%;
                background: var(--cl-accent);
                color: white;
                display: flex; align-items: center; justify-content: center;
                font-size: 10px; font-weight: 700;
                border: 2px solid var(--cl-bg);
                overflow: hidden;
                position: relative;
            }
            .cl-pic-cell .cu-avatar img { width: 100%; height: 100%; object-fit: cover; }
            .cl-pic-name {
                font-size: 12.5px; font-weight: 500; color: var(--cl-ink);
                overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
                flex: 1; min-width: 0;
            }

            /* Filter panel: contract option label (other options are kis badges). */
            .cl-filter-option-label { font-weight: 600; }

            /* Group */
            .cl-group-name { font-size: 12px; color: var(--cl-muted); font-weight: 500; }
            .cl-empty-cell { color: var(--cl-subtle); font-size: 13px; }

            /* Actions */
            .cl-col-actions {
                display: flex;
                gap: 6px;
                justify-content: flex-end;
                align-items: center;
            }
            .cl-act-wrap { position: relative; display: flex; gap: 4px; }

            /* Prominent credential pill — accent-tinted background, key icon + label */
            /* Outline button (DESIGN.md "btn o sm"): visible, but quieter than the primary action. */
            .cl-cred-pill {
                display: inline-flex;
                align-items: center;
                gap: 6px;
                height: 28px;
                padding: 0 11px 0 9px;
                background: var(--cl-bg);
                border: 0;
                border-radius: 9px;
                font: inherit;
                font-size: 12px;
                font-weight: 700;
                color: var(--cl-accent-ink);
                cursor: pointer;
                white-space: nowrap;
                box-shadow: inset 0 0 0 1px color-mix(in srgb, var(--cl-accent-ink) 38%, transparent);
                transition: background .12s, box-shadow .12s;
            }
            .cl-cred-pill:hover {
                background: var(--cl-accent-soft);
                box-shadow: inset 0 0 0 1px var(--cl-accent);
            }
            .cl-cred-pill:focus-visible { outline: 2px solid var(--cl-accent); outline-offset: 2px; }
            .cl-cred-pill:active { transform: translateY(1px); }
            .cl-cred-pill svg { flex-shrink: 0; }
            .cl-cred-pill-label { line-height: 1; }

            /* Origin badge for clients that came in through Client Management */
            .cl-client-meta { flex-wrap: nowrap; overflow: hidden; white-space: nowrap; }
            .cl-client-meta > * { flex-shrink: 0; }
            .cl-client-meta > .cl-subtype { flex-shrink: 1; overflow: hidden; text-overflow: ellipsis; }
            .cl-origin {
                display: inline-flex; align-items: center;
                padding: 1px 7px; border-radius: 99px;
                font-size: 10.5px; font-weight: 600; line-height: 1.5;
                white-space: nowrap; cursor: help;
                color: var(--cl-origin-ink); background: var(--cl-origin-bg);
                box-shadow: inset 0 0 0 1px color-mix(in srgb, var(--cl-origin-ink) 16%, transparent);
            }
            .cl-origin-created   { --cl-origin-ink: #10657f; --cl-origin-bg: #eef9fd; }
            .cl-origin-matched   { --cl-origin-ink: #10657f; --cl-origin-bg: #eef9fd; }
            .cl-origin-withdrawn { --cl-origin-ink: #af3628; --cl-origin-bg: #fff4f2; }
            .dark .cl-origin-created   { --cl-origin-ink: #89d2ea; --cl-origin-bg: #042632; }
            .dark .cl-origin-matched   { --cl-origin-ink: #89d2ea; --cl-origin-bg: #042632; }
            .dark .cl-origin-withdrawn { --cl-origin-ink: #fed0c7; --cl-origin-bg: #40140e; }

            /* Three-dot actions button — neutral */
            .cl-act-btn {
                width: 28px; height: 28px;
                background: transparent; border: 0; border-radius: 6px;
                color: var(--cl-subtle); cursor: pointer;
                display: flex; align-items: center; justify-content: center;
                transition: background .12s, color .12s;
            }
            .cl-act-btn:hover { background: var(--cl-line); color: var(--cl-ink); }
            .cl-act-menu {
                position: absolute; top: calc(100% + 4px); right: 0; z-index: 20;
                background: var(--cl-bg); border-radius: 10px; padding: 4px;
                min-width: 140px;
                box-shadow: 0 0 0 1px rgba(13, 20, 23,.04), 0 10px 28px rgba(13, 20, 23,.12);
            }
            .cl-act-item {
                display: block; width: 100%;
                padding: 7px 12px;
                background: transparent; border: 0;
                border-radius: 6px;
                font: inherit; font-size: 12.5px;
                color: var(--cl-ink); text-decoration: none; text-align: left;
                cursor: pointer;
                transition: background .1s;
            }
            .cl-act-item:hover { background: var(--cl-bg-hover); }

            /* Empty state */
            .cl-empty { padding: 64px 20px; text-align: center; }
            .cl-empty-icon { font-size: 36px; margin-bottom: 12px; opacity: .5; }
            .cl-empty h3 { font-size: 15px; font-weight: 600; margin: 0 0 4px; color: var(--cl-ink); }
            .cl-empty p { font-size: 13px; color: var(--cl-muted); margin: 0; }

            /* Group-by trigger styling — extra labels inside the cl-filter-btn */
            .cl-filter-sep { color: var(--cl-subtle); margin: 0 -2px; }
            .cl-filter-value { color: var(--cl-ink); font-weight: 600; }

            /* Group-by dropdown row (single-select) */
            .cl-dropdown-row {
                display: flex; align-items: center; justify-content: space-between;
                width: 100%;
                padding: 8px 10px;
                background: transparent; border: 0; border-radius: 8px;
                font: inherit; font-size: 13px;
                color: var(--cl-ink); cursor: pointer; text-align: left;
                transition: background .1s;
            }
            .cl-dropdown-row:hover { background: var(--cl-bg-hover); }
            .cl-dropdown-row.is-active { color: var(--cl-accent-ink); font-weight: 600; }
            .cl-dropdown-row.is-active svg { color: var(--cl-accent); }

            /* Group card — wraps the group's rows in a contained surface
               with a colored left edge that reflects the group's badge color.
               Matches the project list's .cu-group-card recipe.
               NOTE: no overflow:hidden — would clip the credential modal
               and the actions dropdown that pop out of the card. */
            /* Groups live inside the one table: no card, no accent edge. */
            .cl-group { display: block; }

            /* Group header: a quiet section row inside the table. */
            .cl-group-row {
                display: flex;
                align-items: center;
                gap: 12px;
                padding: 9px 20px;
                background: var(--cl-bg-soft);
                border-bottom: 1px solid var(--cl-line);
            }
            /* Soft tone badge (data-kis-tone): the group label, not a solid block. */
            .cl-group-badge {
                display: inline-flex;
                align-items: center;
                gap: 7px;
                padding: 6px 12px 6px 9px;
                border-radius: 7px;
                background: var(--s-bg);
                color: var(--s-color);
                box-shadow: inset 0 0 0 1px color-mix(in srgb, var(--s-color) 22%, transparent);
                font-size: 11.5px;
                font-weight: 700;
                text-transform: uppercase;
                letter-spacing: .06em;
                line-height: 1;
            }
            .cl-group-badge-icon {
                display: inline-flex;
                align-items: center;
                justify-content: center;
                flex-shrink: 0;
            }
            .cl-group-badge-label { line-height: 1; }
            .cl-group-count-num {
                font-size: 13px;
                font-weight: 600;
                color: var(--cl-muted);
            }
            .cl-group-mini {
                margin-left: auto;
                font-size: 12px;
                font-weight: 600;
                color: var(--cl-muted);
            }

            /* Show more — footer strip with soft background and top border */
            .cl-group-more {
                padding: 6px 12px;
                background: var(--cl-bg);
                border-bottom: 1px solid var(--cl-line);
                display: flex;
                justify-content: flex-start;
            }
            .cl-show-more-btn {
                display: inline-flex;
                align-items: center;
                gap: 6px;
                padding: 7px 14px;
                background: transparent;
                border: 0;
                border-radius: 8px;
                font: inherit;
                font-size: 12.5px;
                font-weight: 600;
                color: var(--cl-accent-ink);
                cursor: pointer;
                transition: background .12s;
            }
            .cl-show-more-btn:hover { background: var(--cl-accent-soft); }
            .cl-show-more-btn svg { color: var(--cl-accent); }

            /* List footer / total */
            .cl-list-footer {
                padding: 11px 20px;
                display: flex;
                align-items: center;
                justify-content: space-between;
                gap: 10px;
                font-size: 12.5px;
                color: var(--cl-muted);
                background: var(--cl-bg-soft);
            }
            .cl-list-footer b { color: var(--cl-ink); font-variant-numeric: tabular-nums; }
            .cl-list-hint { font-size: 12px; color: var(--cl-subtle); }
            .cl-list-total {
                font-size: 12px;
                font-weight: 500;
                color: var(--cl-muted);
            }
            .cl-list-cap {
                color: var(--cl-subtle);
                font-weight: 500;
            }

            /* Pagination wrapper */
            .cl-pagination { padding: 18px 0 4px; }

            /* Credential modal */
            .cl-cred-overlay {
                position: fixed; inset: 0; z-index: 70;
                display: flex; align-items: center; justify-content: center;
                padding: 24px;
            }
            .cl-cred-backdrop {
                position: absolute; inset: 0;
                background: rgba(13, 20, 23, .45);
                animation: clFade .15s ease-out;
            }
            @keyframes clFade { from { opacity: 0; } to { opacity: 1; } }
            .cl-cred-modal {
                position: relative;
                width: 100%; max-width: 920px;
                max-height: calc(100vh - 48px);
                max-height: calc(100dvh - 48px);
                display: flex; flex-direction: column;
                overflow: hidden;
                border-radius: 16px;
                background: var(--cl-bg);
                box-shadow: 0 24px 48px -16px rgba(13, 20, 23, .32), 0 2px 6px rgba(13, 20, 23, .08);
                animation: clSlide .2s cubic-bezier(.22, 1, .36, 1);
            }
            .dark .cl-cred-modal { box-shadow: 0 0 0 1px var(--cl-line), 0 24px 48px -16px rgba(0, 0, 0, .6); }
            @keyframes clSlide { from { transform: translateY(6px); opacity: 0; } to { transform: translateY(0); opacity: 1; } }
            @media (prefers-reduced-motion: reduce) {
                .cl-cred-backdrop, .cl-cred-modal { animation: none; }
            }

            /* ============== RESPONSIVE ============== */
            /* Tablet & down (the grid needs ~1026px to fit all columns):
               drop the secondary columns and keep a compact table —
               logo · name · status · actions. */
            @media (max-width: 1024px) {
                .cl-table-head,
                .cl-row {
                    grid-template-columns: 36px minmax(120px, 1fr) auto auto !important;
                    gap: 10px;
                }
                .cl-col-type,
                .cl-col-pkp,
                .cl-col-group { display: none !important; }

                .cl-row { padding: 12px 12px 12px 20px; }
                .cl-table-head { padding: 4px 14px 10px; }

                /* Icon-only credential button to save horizontal room. */
                .cl-cred-pill-label { display: none; }
                .cl-cred-pill { padding: 0; width: 28px; justify-content: center; }
            }

            /* Phones: stack each row into a card. Header hidden; cells reflow —
               line 1: logo · name · actions, then the pills wrap onto line 2+. */
            @media (max-width: 640px) {
                .cl-table-head { display: none !important; }

                .cl-row {
                    display: flex !important;
                    flex-wrap: wrap;
                    align-items: center;
                    grid-template-columns: none !important;
                    column-gap: 8px; row-gap: 8px;
                    padding: 12px 14px 12px 18px;
                }
                .cl-col-logo    { order: 1; }
                .cl-col-name    { order: 2; flex: 1 1 auto; min-width: 0; overflow: hidden; }
                .cl-client-name { display: block; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
                .cl-col-actions { order: 3; flex: 0 0 auto; }

                /* Bring the pills back (override the tablet hide) and let them wrap below. */
                .cl-col-status,
                .cl-col-type,
                .cl-col-pkp,
                .cl-col-group {
                    display: flex !important;
                    align-items: center;
                    order: 4;
                    flex: 0 0 auto;
                }

                /* Restore the labelled credential pill — there's room in the wrap. */
                .cl-cred-pill-label { display: inline; }
                .cl-cred-pill { padding: 0 11px 0 9px; width: auto; }

                /* Toolbar: full-width search, the icon buttons wrap beneath it. */
                .cl-search { flex: 1 1 100%; max-width: none; }
                .cl-toolbar-spacer { display: none; }

                /* Keep filter dropdowns within the viewport. */
                .cl-filter-mega { width: min(320px, calc(100vw - 28px)) !important; }

                /* Credential modal — tighter padding on small screens. */
                .cl-cred-overlay { padding: 12px; }
                .cl-cred-modal { max-height: calc(100dvh - 24px); }
            }
        </style>
    @endonce
</div>
