@php
    use App\Livewire\Clients\ClientListClickup;
    $statuses = ClientListClickup::STATUSES;
    $types    = ClientListClickup::TYPES;
    $pkps     = ClientListClickup::PKP_STATUSES;
    $contracts = ClientListClickup::CONTRACTS;
    $activeCount = $this->activeFilterCount;
    $groupByOptions = [
        'type'     => 'Client Type',
        'status'   => 'Status',
        'pkp'      => 'PKP Status',
        'contract' => 'Jenis Kontrak',
        'pic'      => 'PIC',
        'group'    => 'Group',
        'none'     => 'None',
    ];
@endphp

<div class="cl-root">
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
            <button type="button" @click="open = !open" class="cl-filter-btn cl-filter-btn-icon" title="Group by: {{ $groupByOptions[$groupBy] ?? 'None' }}">
                <svg class="cl-filter-ico" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M3 6h18"/><path d="M7 12h10"/><path d="M11 18h2"/></svg>
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
                        <button type="button" wire:click="clearFilters" class="cl-filter-mega-clear">Clear all</button>
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
                                    <span class="cl-pill" style="color: {{ $meta['color'] }}; background: {{ $meta['bg'] }};">{{ $meta['label'] }}</span>
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
                                    <span class="cl-pill" style="color: {{ $meta['color'] }}; background: {{ $meta['bg'] }};">{{ $meta['label'] }}</span>
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
                                    <span class="cl-pill" style="color: {{ $meta['color'] }}; background: {{ $meta['bg'] }};">{{ $meta['label'] }}</span>
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
                                    <span class="cl-pill" style="color: #0e7490; background: #cffafe;">{{ $label }}</span>
                                </label>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Columns toggle (icon-only) --}}
        @php $toggleable = \App\Livewire\Clients\ClientListClickup::TOGGLEABLE_COLUMNS; @endphp
        <div class="cl-filter cl-dropdown" x-data="{ open: false }">
            <button type="button" @click="open = !open" class="cl-filter-btn cl-filter-btn-icon" title="Columns">
                <svg class="cl-filter-ico" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><rect x="3" y="3" width="7" height="18" rx="1"/><rect x="14" y="3" width="7" height="11" rx="1"/></svg>
            </button>
            <div x-show="open" @click.outside="open = false" x-cloak class="cl-filter-mega" style="width: 200px;">
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

        <div class="cl-toolbar-spacer"></div>
    </div>

    {{-- ============== ACTIVE CHIPS ============== --}}
    @if ($this->hasActiveFilters())
        <div class="cl-active-filters">
            <span class="cl-active-label">Active</span>

            @if ($search !== '')
                <button type="button" wire:click="$set('search', '')" class="cl-active-chip">
                    <span class="cl-chip-kind">Search</span><span class="cl-chip-sep">:</span>
                    <span class="cl-chip-value">"{{ $search }}"</span>
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>
                </button>
            @endif

            @foreach ($statusFilter as $key)
                @php $m = $statuses[$key] ?? null; @endphp
                @if ($m)
                    <button type="button" wire:click="removeStatus('{{ $key }}')" class="cl-active-chip">
                        <span class="cl-chip-kind">Status</span><span class="cl-chip-sep">:</span>
                        <span class="cl-chip-value cl-chip-pill" style="color: {{ $m['color'] }}; background: {{ $m['bg'] }};">{{ $m['label'] }}</span>
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>
                    </button>
                @endif
            @endforeach

            @foreach ($typeFilter as $key)
                @php $m = $types[$key] ?? null; @endphp
                @if ($m)
                    <button type="button" wire:click="removeType('{{ $key }}')" class="cl-active-chip">
                        <span class="cl-chip-kind">Type</span><span class="cl-chip-sep">:</span>
                        <span class="cl-chip-value cl-chip-pill" style="color: {{ $m['color'] }}; background: {{ $m['bg'] }};">{{ $m['label'] }}</span>
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>
                    </button>
                @endif
            @endforeach

            @foreach ($pkpFilter as $key)
                @php $m = $pkps[$key] ?? null; @endphp
                @if ($m)
                    <button type="button" wire:click="removePkp('{{ $key }}')" class="cl-active-chip">
                        <span class="cl-chip-kind">PKP</span><span class="cl-chip-sep">:</span>
                        <span class="cl-chip-value cl-chip-pill" style="color: {{ $m['color'] }}; background: {{ $m['bg'] }};">{{ $m['label'] }}</span>
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>
                    </button>
                @endif
            @endforeach

            @foreach ($contractFilter as $col)
                @php $label = $contracts[$col] ?? null; @endphp
                @if ($label)
                    <button type="button" wire:click="removeContract('{{ $col }}')" class="cl-active-chip">
                        <span class="cl-chip-kind">Kontrak</span><span class="cl-chip-sep">:</span>
                        <span class="cl-chip-value cl-chip-pill" style="color: #0e7490; background: #cffafe;">{{ $label }}</span>
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>
                    </button>
                @endif
            @endforeach

            <button wire:click="clearFilters" class="cl-clear-all">Clear all</button>
        </div>
    @endif

    {{-- ============== TABLE HEADER ============== --}}
    @php $gridStyle = 'grid-template-columns: ' . $this->gridTemplate . ';'; @endphp
    <div class="cl-table-head" style="{{ $gridStyle }}">
        <div class="cl-col-logo"></div>
        <div class="cl-col-name cl-sortable" wire:click="sortBy('name')">
            Client
            @if ($sortField === 'name') <span class="cl-sort-arrow">{{ $sortDirection === 'asc' ? '↑' : '↓' }}</span> @endif
        </div>
        <div class="cl-col-status cl-sortable" wire:click="sortBy('status')">
            Status
            @if ($sortField === 'status') <span class="cl-sort-arrow">{{ $sortDirection === 'asc' ? '↑' : '↓' }}</span> @endif
        </div>
        @if ($this->isColumnVisible('type'))
            <div class="cl-col-type">Type</div>
        @endif
        @if ($this->isColumnVisible('pkp'))
            <div class="cl-col-pkp">PKP</div>
        @endif
        @if ($this->isColumnVisible('group'))
            <div class="cl-col-group">Group</div>
        @endif
        <div class="cl-col-actions"></div>
    </div>

    {{-- ============== ROWS (grouped) ============== --}}
    @php $isAnyData = false; @endphp
    @foreach ($grouped as $gKey => $gData) @php $isAnyData = $isAnyData || $gData['total'] > 0; @endphp @endforeach

    @if (!$isAnyData)
        <div class="cl-empty">
            <div class="cl-empty-icon">🏢</div>
            <h3>No clients found</h3>
            <p>Try adjusting your filters or create a new client.</p>
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

            {{-- Card wraps the group header AND its rows — colored left edge
                 sits flush against the badge. --}}
            <div class="cl-group-card {{ $groupBy === 'none' ? 'cl-group-card-flat' : '' }}" style="--group-color: {{ $groupMeta['color'] ?? '#6366f1' }};">
            @if ($groupBy !== 'none')
                <div class="cl-group-row">
                    <span class="cl-group-badge" style="background: {{ $groupMeta['color'] }};">
                        <span class="cl-group-badge-icon">
                            @include('livewire.projects.partials.status-shape', ['shape' => $groupShape, 'color' => $groupMeta['color'], 'size' => 14, 'inverse' => true])
                        </span>
                        <span class="cl-group-badge-label">{{ $groupLabel }}</span>
                    </span>
                    <span class="cl-group-count-num">{{ $groupData['total'] }}</span>
                </div>
            @endif

            @foreach ($groupData['visible'] as $client)
                @php
                    $statusMeta = $statuses[$client->status] ?? ['label' => $client->status ?? '—', 'color' => '#64748b', 'bg' => '#f1f5f9'];
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

                <div class="cl-row" wire:key="client-{{ $client->id }}" style="{{ $gridStyle }}">
                    {{-- Logo (circle) --}}
                    <div class="cl-col-logo">
                        <span class="cl-logo" x-data="{ failed: false }">
                            @if ($logoSrc)
                                <img src="{{ $logoSrc }}" alt="{{ $client->name }}" x-show="!failed" x-on:error="failed = true" loading="lazy">
                                <span x-show="failed" x-cloak class="cl-logo-initials">{{ $logoInitials }}</span>
                            @else
                                <span class="cl-logo-initials">{{ $logoInitials }}</span>
                            @endif
                        </span>
                    </div>

                    {{-- Name + NPWP/subtype meta --}}
                    <div class="cl-col-name">
                        <a href="{{ $this->viewUrl($client) }}" class="cl-client-name">{{ $client->name }}</a>
                        <div class="cl-client-meta">
                            @if ($originTone)
                                <span class="cl-origin cl-origin-{{ $originTone }}" title="{{ $originTitle }}">{{ $intake->originLabel() }}</span>
                            @endif
                            @if ($client->NPWP)
                                <span class="cl-npwp">NPWP {{ $client->NPWP }}</span>
                            @endif
                            @if ($client->client_subtype)
                                @if ($client->NPWP) <span class="cl-meta-sep">·</span> @endif
                                <span class="cl-subtype">{{ $client->client_subtype }}</span>
                            @endif
                        </div>
                    </div>

                    {{-- Status pill --}}
                    <div class="cl-col-status">
                        <span class="cl-pill" style="color: {{ $statusMeta['color'] }}; background: {{ $statusMeta['bg'] }};">
                            <span class="cl-pill-dot" style="background: {{ $statusMeta['color'] }};"></span>
                            {{ $statusMeta['label'] }}
                        </span>
                    </div>

                    {{-- Client type pill --}}
                    @if ($this->isColumnVisible('type'))
                        <div class="cl-col-type">
                            @if ($typeMeta)
                                <span class="cl-pill cl-pill-soft" style="color: {{ $typeMeta['color'] }}; background: {{ $typeMeta['bg'] }};">
                                    <span class="cl-pill-dot cl-pill-dot-sm" style="background: {{ $typeMeta['color'] }};"></span>
                                    {{ $typeMeta['label'] }}
                                </span>
                            @else
                                <span class="cl-empty-cell">—</span>
                            @endif
                        </div>
                    @endif

                    {{-- PKP --}}
                    @if ($this->isColumnVisible('pkp'))
                        <div class="cl-col-pkp">
                            @if ($pkpMeta)
                                <span class="cl-pill cl-pill-soft" style="color: {{ $pkpMeta['color'] }}; background: {{ $pkpMeta['bg'] }};">
                                    <span class="cl-pill-dot cl-pill-dot-sm" style="background: {{ $pkpMeta['color'] }};"></span>
                                    {{ $pkpMeta['label'] }}
                                </span>
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
                                <a href="{{ $this->viewUrl($client) }}" class="cl-act-item">View</a>
                                <a href="{{ $this->editUrl($client) }}" class="cl-act-item">Edit</a>
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
                            Show less
                        @else
                            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><polyline points="6 9 12 15 18 9"/></svg>
                            Show {{ $groupData['hidden'] }} more
                        @endif
                    </button>
                </div>
            @endif
            </div> {{-- /cl-group-card --}}
        @endforeach
    @endif

    {{-- ============== TOTAL / CAP NOTICE ============== --}}
    @if ($isAnyData)
        <div class="cl-list-footer">
            <span class="cl-list-total">
                {{ $totalCount }} {{ $totalCount === 1 ? 'client' : 'clients' }}
                @if ($isCapped)
                    <span class="cl-list-cap">· showing first {{ \App\Livewire\Clients\ClientListClickup::HARD_CAP }}</span>
                @endif
            </span>
        </div>
    @endif

    {{-- ============== CREDENTIAL MODAL ============== --}}
    @if ($viewingCredentialsClientId)
        @php $credClient = $this->credentialClient; @endphp
        @if ($credClient)
            <div class="cl-cred-overlay" wire:keydown.escape.window="closeCredentials">
                <div class="cl-cred-backdrop" wire:click="closeCredentials"></div>
                <div class="cl-cred-modal" role="dialog" aria-modal="true">
                    <header class="cl-cred-head">
                        <div class="cl-cred-head-title">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="8" cy="15" r="4"/><line x1="10.85" y1="12.15" x2="19" y2="4"/><line x1="18" y1="5" x2="20" y2="7"/><line x1="15" y1="8" x2="17" y2="10"/></svg>
                            <span>Kredensial Aplikasi — {{ $credClient->name }}</span>
                        </div>
                        <button type="button" wire:click="closeCredentials" class="cl-cred-close" aria-label="Tutup">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>
                        </button>
                    </header>
                    <div class="cl-cred-body">
                        @include('filament.modals.clients.client-all-credentials', ['clientId' => $credClient->id])
                    </div>
                </div>
            </div>
        @endif
    @endif

    @once
        <style>
            [x-cloak] { display: none !important; }

            .cl-root {
                /* Light theme tokens */
                --cl-ink: #0f172a;
                --cl-muted: #64748b;
                --cl-subtle: #94a3b8;
                --cl-line: #eef0f3;
                --cl-line-strong: #d8dde3;
                --cl-bg: #ffffff;
                --cl-bg-soft: #f7f8fa;
                --cl-bg-hover: #f4f5f7;
                --cl-accent: #6366f1;
                --cl-accent-ink: #4f46e5;
                --cl-accent-soft: #eef2ff;
                /* Mode-sensitive surfaces — re-mapped in dark mode */
                --cl-cred-hover-bg: #e0e7ff;
                font-family: 'Plus Jakarta Sans', system-ui, sans-serif;
                color: var(--cl-ink);
                font-size: 13.5px;
                display: flex;
                flex-direction: column;
                gap: 14px;
            }

            /* Dark mode — Filament toggles `.dark` on <html>. Same near-black
               palette as the Project Dashboard so both surfaces match. */
            .dark .cl-root {
                --cl-ink: #f3f4f6;
                --cl-muted: #9ca3af;
                --cl-subtle: #6b7280;
                --cl-line: #2e2e2e;
                --cl-line-strong: #525252;
                --cl-bg: #171717;
                --cl-bg-soft: #0a0a0a;
                --cl-bg-hover: #262626;
                --cl-accent: #818cf8;
                --cl-accent-ink: #a5b4fc;
                --cl-accent-soft: #1e1b4b;
                --cl-cred-hover-bg: #1e1b4b;
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
                    0 0 0 3px rgba(99, 102, 241, .12);
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
                    inset 0 -1px 0 rgba(15, 23, 42, .06),
                    0 0 0 1px rgba(15, 23, 42, .06);
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
                box-shadow: inset 0 0 0 1px var(--cl-line-strong), 0 1px 2px rgba(15,23,42,.03);
                transition: box-shadow .14s, transform .08s;
            }
            .cl-filter-btn:hover { box-shadow: inset 0 0 0 1px var(--cl-muted), 0 2px 6px rgba(15,23,42,.07); }
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
                box-shadow: inset 0 0 0 1px var(--cl-accent), 0 1px 2px rgba(99,102,241,.08);
            }
            .cl-filter.is-active .cl-filter-ico, .cl-filter.is-active .cl-filter-label { color: var(--cl-accent-ink); }

            /* Mega panel */
            .cl-filter-mega {
                position: absolute; top: calc(100% + 8px); left: 0; z-index: 50;
                width: 320px; max-height: 480px;
                background: var(--cl-bg); border-radius: 14px;
                box-shadow: 0 0 0 1px rgba(15,23,42,.04), 0 16px 40px rgba(15,23,42,.14);
                display: flex; flex-direction: column; overflow: hidden;
            }
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

            /* Active chips */
            .cl-active-filters { display: flex; flex-wrap: wrap; align-items: center; gap: 8px; padding: 0 2px; }
            .cl-active-label {
                font-size: 11px; font-weight: 700; text-transform: uppercase;
                letter-spacing: .08em; color: var(--cl-subtle); margin-right: 2px;
            }
            .cl-active-chip {
                display: inline-flex; align-items: center; gap: 7px;
                padding: 5px 7px 5px 11px;
                background: var(--cl-bg); border: 0; border-radius: 99px;
                font: inherit; font-size: 12px; color: var(--cl-ink); cursor: pointer;
                box-shadow: inset 0 0 0 1px var(--cl-line-strong), 0 1px 2px rgba(15,23,42,.04);
                transition: box-shadow .12s, transform .08s;
            }
            .cl-active-chip:hover { box-shadow: inset 0 0 0 1px var(--cl-muted), 0 2px 6px rgba(15,23,42,.08); }
            .cl-active-chip svg {
                color: var(--cl-subtle); padding: 3px; margin-left: -2px;
                border-radius: 50%; box-sizing: content-box;
                transition: background .12s, color .12s;
            }
            .cl-active-chip:hover svg { background: var(--cl-bg-hover); color: var(--cl-ink); }
            .cl-chip-kind { color: var(--cl-muted); font-weight: 500; }
            .cl-chip-sep { color: var(--cl-subtle); }
            .cl-chip-value { font-weight: 600; }
            .cl-chip-pill {
                padding: 2px 8px; border-radius: 99px;
                font-size: 11px; font-weight: 600;
                display: inline-flex; align-items: center;
            }
            .cl-clear-all {
                font: inherit; font-size: 12px; font-weight: 600;
                color: var(--cl-muted); background: transparent; border: 0;
                cursor: pointer; padding: 6px 10px; margin-left: auto;
                border-radius: 6px; transition: color .12s, background .12s;
            }
            .cl-clear-all:hover { color: var(--cl-accent-ink); background: var(--cl-accent-soft); }

            /* Table head & rows — matches project list spacing.
               Grid columns are inlined per-render via $gridStyle (dynamic
               based on visible columns from the Columns toggle). */
            .cl-table-head, .cl-row {
                display: grid;
                gap: 10px;
                align-items: center;
            }
            .cl-table-head {
                padding: 4px 18px 10px;
                font-size: 11px; font-weight: 700;
                text-transform: uppercase; letter-spacing: .06em;
                color: var(--cl-subtle);
                border-bottom: 1px solid var(--cl-line-strong);
            }
            .cl-sortable { cursor: pointer; user-select: none; transition: color .12s; }
            .cl-sortable:hover { color: var(--cl-accent); }
            .cl-sort-arrow { color: var(--cl-accent); margin-left: 2px; }
            .cl-row {
                padding: 13px 16px 13px 22px;       /* extra left padding for the card's accent bar */
                background: transparent;
                border-bottom: 1px solid var(--cl-line);
                transition: background .12s;
            }
            .cl-row > *, .cl-table-head > * { min-width: 0; }
            .cl-row:hover { background: var(--cl-bg-hover); }

            /* Circle logo cell */
            .cl-col-logo { display: flex; align-items: center; justify-content: center; }
            .cl-logo {
                position: relative;
                width: 32px; height: 32px;
                border-radius: 50%;
                background: linear-gradient(135deg, var(--cl-accent), var(--cl-accent-ink));
                color: white;
                display: inline-flex;
                align-items: center;
                justify-content: center;
                font-size: 11px;
                font-weight: 700;
                letter-spacing: .02em;
                overflow: hidden;
                box-shadow:
                    inset 0 0 0 1px rgba(255, 255, 255, .15),
                    0 1px 2px rgba(15, 23, 42, .12),
                    0 0 0 2px var(--cl-bg);
                flex-shrink: 0;
            }
            .cl-logo img { width: 100%; height: 100%; object-fit: cover; display: block; }
            .cl-logo-initials { user-select: none; }

            /* Name col */
            .cl-client-name {
                font-weight: 600; font-size: 14px;
                color: var(--cl-ink); text-decoration: none;
                transition: color .12s;
            }
            .cl-client-name:hover { color: var(--cl-accent-ink); }
            .cl-client-meta {
                display: flex; align-items: center; gap: 7px;
                margin-top: 2px;
                font-size: 11px; color: var(--cl-muted);
            }
            .cl-npwp { font-family: 'SF Mono', Menlo, monospace; font-size: 10.5px; letter-spacing: .02em; }
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

            /* Pills — multi-layer recipe:
               - Top inset highlight for chip depth
               - Inset 1px tint border using currentColor at low opacity (same-color stroke)
               - Soft outer shadow for lift
               - Hairline ambient shadow for definition on any bg */
            .cl-pill {
                display: inline-flex;
                align-items: center;
                gap: 6px;
                padding: 4px 11px;
                border-radius: 99px;
                font-size: 11px;
                font-weight: 600;
                letter-spacing: .01em;
                white-space: nowrap;
                line-height: 1.4;
                box-shadow:
                    inset 0 1px 0 rgba(255, 255, 255, .55),
                    inset 0 0 0 1px color-mix(in srgb, currentColor 18%, transparent),
                    0 1px 2px rgba(15, 23, 42, .07);
            }
            /* "Soft" variant — quieter (used by Type / PKP columns):
               smaller, no top highlight, lighter inset border, no drop. */
            .cl-pill-soft {
                padding: 3px 10px;
                font-size: 10.5px;
                font-weight: 600;
                box-shadow:
                    inset 0 0 0 1px color-mix(in srgb, currentColor 14%, transparent),
                    0 1px 1px rgba(15, 23, 42, .04);
            }
            .cl-pill-dot {
                width: 7px; height: 7px;
                border-radius: 50%;
                flex-shrink: 0;
                box-shadow: 0 0 0 2px color-mix(in srgb, currentColor 12%, transparent);
            }
            .cl-pill-dot-sm {
                width: 5px; height: 5px;
                box-shadow: 0 0 0 1.5px color-mix(in srgb, currentColor 10%, transparent);
            }

            /* Dark-mode treatment for status / type / PKP pills.
               Pills carry inline `color` (dark hue) + `background` (light pastel)
               set by PHP, which looks like a sticker on a dark page. Override:
                 1. Lighten the text so it reads on the dark surface,
                 2. Derive a deep tinted bg from the (now lighter) text color,
                 3. Drop the white-gloss highlight, keep a subtle outline.
               Inline `style="..."` must be beaten with `!important`. */
            .dark .cl-pill,
            .dark .cl-pill-soft,
            .dark .cl-chip-pill {
                color: color-mix(in srgb, currentColor 55%, white) !important;
                background: color-mix(in srgb, currentColor 22%, var(--cl-bg)) !important;
                box-shadow: inset 0 0 0 1px color-mix(in srgb, currentColor 28%, transparent) !important;
            }
            /* Dot inline bg is the original dark hue — match it to the
               lightened pill text so it stays visible on the new bg. */
            .dark .cl-pill-dot,
            .dark .cl-pill-dot-sm {
                background: currentColor !important;
            }

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
            .cl-cred-pill {
                display: inline-flex;
                align-items: center;
                gap: 6px;
                height: 28px;
                padding: 0 11px 0 9px;
                background: var(--cl-accent-soft);
                border: 0;
                border-radius: 7px;
                font: inherit;
                font-size: 11.5px;
                font-weight: 600;
                color: var(--cl-accent-ink);
                cursor: pointer;
                white-space: nowrap;
                box-shadow: inset 0 0 0 1px rgba(99, 102, 241, .18);
                transition: background .12s, box-shadow .12s, transform .08s;
            }
            .cl-cred-pill:hover {
                background: var(--cl-cred-hover-bg);
                box-shadow: inset 0 0 0 1px rgba(99, 102, 241, .35), 0 1px 3px rgba(99, 102, 241, .15);
            }
            .cl-cred-pill:active { transform: translateY(1px); }
            .cl-cred-pill svg { flex-shrink: 0; }
            .cl-cred-pill-label { line-height: 1; }

            /* Origin badge for clients that came in through Client Management */
            .cl-client-meta { flex-wrap: wrap; row-gap: 3px; }
            .cl-origin {
                display: inline-flex; align-items: center;
                padding: 1px 7px; border-radius: 99px;
                font-size: 10.5px; font-weight: 600; line-height: 1.5;
                white-space: nowrap; cursor: help;
                color: var(--cl-origin-ink); background: var(--cl-origin-bg);
                box-shadow: inset 0 0 0 1px color-mix(in srgb, var(--cl-origin-ink) 16%, transparent);
            }
            .cl-origin-created   { --cl-origin-ink: #4338ca; --cl-origin-bg: #eef2ff; }
            .cl-origin-matched   { --cl-origin-ink: #0e7490; --cl-origin-bg: #ecfeff; }
            .cl-origin-withdrawn { --cl-origin-ink: #b91c1c; --cl-origin-bg: #fef2f2; }
            .dark .cl-origin-created   { --cl-origin-ink: #a5b4fc; --cl-origin-bg: #1e1b4b; }
            .dark .cl-origin-matched   { --cl-origin-ink: #67e8f9; --cl-origin-bg: #083344; }
            .dark .cl-origin-withdrawn { --cl-origin-ink: #fca5a5; --cl-origin-bg: #450a0a; }

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
                box-shadow: 0 0 0 1px rgba(15,23,42,.04), 0 10px 28px rgba(15,23,42,.12);
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
            .cl-group-card {
                position: relative;
                background: var(--cl-bg);
                border: 1px solid var(--cl-line);
                border-radius: 10px;
                margin-bottom: 20px;
                transition: border-color .15s;
            }
            .cl-group-card:hover {
                border-color: var(--cl-line-strong);
            }
            .cl-group-card::before {
                content: '';
                position: absolute;
                top: 0; left: 0; bottom: 0;
                width: 3px;
                background: var(--group-color, var(--cl-line-strong));
                border-top-left-radius: 9px;
                border-bottom-left-radius: 9px;
            }
            .cl-group-card-flat::before { background: var(--cl-line-strong); }

            /* Drop the bottom border on the last row of a card and round
               its corners to match the card's. */
            .cl-group-card > .cl-row:last-child {
                border-bottom: 0;
                border-bottom-left-radius: 9px;
                border-bottom-right-radius: 9px;
            }
            .cl-group-card > .cl-group-more {
                border-bottom-left-radius: 9px;
                border-bottom-right-radius: 9px;
            }

            /* Group header — flat, with the badge as the visual anchor.
               Extra left padding accommodates the card's accent stripe. */
            .cl-group-row {
                display: flex;
                align-items: center;
                gap: 12px;
                padding: 13px 16px 12px 22px;
                background: var(--cl-bg);
                border-bottom: 1px solid var(--cl-line);
                border-top-left-radius: 9px;
                border-top-right-radius: 9px;
            }
            .cl-group-badge {
                display: inline-flex;
                align-items: center;
                gap: 7px;
                padding: 6px 12px 6px 9px;
                border-radius: 6px;
                color: #ffffff;
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

            /* Show more — footer strip with soft background and top border */
            .cl-group-more {
                padding: 8px 18px;
                background: var(--cl-bg-soft);
                border-top: 1px solid var(--cl-line);
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
                padding: 18px 0 4px;
                display: flex;
                align-items: center;
                gap: 10px;
            }
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
                background: rgba(15,23,42,.45);
                animation: clFade .15s ease-out;
            }
            @keyframes clFade { from { opacity: 0; } to { opacity: 1; } }
            .cl-cred-modal {
                position: relative;
                width: 100%; max-width: 880px;
                max-height: calc(100vh - 48px);
                background: var(--cl-bg);
                border-radius: 14px;
                box-shadow: 0 24px 60px rgba(15,23,42,.3);
                overflow: hidden;
                display: flex; flex-direction: column;
                animation: clSlide .2s ease-out;
            }
            @keyframes clSlide { from { transform: translateY(6px); opacity: 0; } to { transform: translateY(0); opacity: 1; } }
            .cl-cred-head {
                display: flex; align-items: center; justify-content: space-between;
                padding: 16px 20px;
                border-bottom: 1px solid var(--cl-line);
            }
            .cl-cred-head-title {
                display: flex; align-items: center; gap: 10px;
                font-size: 14px; font-weight: 700; color: var(--cl-ink);
            }
            .cl-cred-head-title svg { color: var(--cl-accent); }
            .cl-cred-close {
                width: 28px; height: 28px;
                background: transparent; border: 0; border-radius: 6px;
                color: var(--cl-muted); cursor: pointer;
                display: flex; align-items: center; justify-content: center;
                transition: background .12s, color .12s;
            }
            .cl-cred-close:hover { background: var(--cl-bg-hover); color: var(--cl-ink); }
            .cl-cred-body {
                flex: 1; overflow-y: auto;
                padding: 20px;
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
                .cl-cred-head { padding: 14px 16px; }
                .cl-cred-body { padding: 14px; }
            }
        </style>
    @endonce
</div>
