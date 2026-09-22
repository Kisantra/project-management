@php
    use App\Livewire\Projects\ProjectListClickup;
    // $statuses is passed in via render() — pulled from the project_statuses table.
    $priorities = ProjectListClickup::PRIORITIES;
    $stepStatuses = ProjectListClickup::STEP_STATUSES;
@endphp

<div class="cu-root" wire:poll.visible.60s>
    {{-- ============== TOOLBAR ============== --}}
    @php
        $groupByOptions = ProjectListClickup::GROUP_BY_OPTIONS;
        $groupByKeys = array_keys($groupByOptions);
        $groupByIndex = array_search($groupBy, $groupByKeys, true);
        $groupByIndex = $groupByIndex === false ? 0 : $groupByIndex;
        $groupByPrev = $groupByOptions[$groupByKeys[($groupByIndex - 1 + count($groupByKeys)) % count($groupByKeys)]];
        $groupByNext = $groupByOptions[$groupByKeys[($groupByIndex + 1) % count($groupByKeys)]];
    @endphp

    <div class="cu-toolbar">
        {{-- Search (command-bar style) --}}
        <div class="cu-search">
            <svg class="cu-search-icon" xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <circle cx="11" cy="11" r="8"/>
                <path d="m21 21-4.3-4.3"/>
            </svg>
            <input type="text" wire:model.live.debounce.300ms="search" placeholder="Search projects, clients…">
            <kbd class="cu-search-kbd">⌘K</kbd>
        </div>

        {{-- Group by — stepper: arrows cycle the option, the label opens the full list --}}
        <div class="cu-filter cu-dropdown cu-groupby {{ $groupBy === 'none' ? 'is-none' : '' }}"
             x-data="{ open: false }"
             @keydown.left.prevent="$wire.cycleGroupBy(-1)"
             @keydown.right.prevent="$wire.cycleGroupBy(1)">
            <div class="cu-groupby-ctl"
                 role="group"
                 aria-label="Group by"
                 wire:loading.class="is-busy"
                 wire:target="cycleGroupBy, groupBy">
                <button type="button"
                        wire:click="cycleGroupBy(-1)"
                        wire:loading.attr="disabled"
                        wire:target="cycleGroupBy, groupBy"
                        class="cu-groupby-arrow"
                        title="{{ $groupByPrev }}"
                        aria-label="Group by sebelumnya: {{ $groupByPrev }}">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"/></svg>
                </button>

                <button type="button"
                        @click="open = !open"
                        class="cu-groupby-current"
                        title="Pilih group by"
                        aria-haspopup="listbox"
                        :aria-expanded="open">
                    <span class="cu-groupby-cap">Group by</span>
                    <span class="cu-groupby-value" wire:key="groupby-value-{{ $groupBy }}">
                        {{ $groupByOptions[$groupBy] ?? 'None' }}
                        <svg class="cu-groupby-caret" width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"/></svg>
                    </span>
                    <span class="cu-groupby-spin" aria-hidden="true"></span>
                </button>

                <button type="button"
                        wire:click="cycleGroupBy(1)"
                        wire:loading.attr="disabled"
                        wire:target="cycleGroupBy, groupBy"
                        class="cu-groupby-arrow"
                        title="{{ $groupByNext }}"
                        aria-label="Group by berikutnya: {{ $groupByNext }}">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"/></svg>
                </button>
            </div>
            <div x-show="open" @click.outside="open = false" x-cloak class="cu-dropdown-panel">
                @foreach ($groupByOptions as $key => $label)
                    <button type="button"
                            wire:click="$set('groupBy', '{{ $key }}')"
                            @click="open = false"
                            class="cu-dropdown-row {{ $groupBy === $key ? 'is-active' : '' }}">
                        <span>{{ $label }}</span>
                        @if ($groupBy === $key)
                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                        @endif
                    </button>
                @endforeach
            </div>
        </div>

        {{-- ============== UNIFIED FILTER PANEL ============== --}}
        @php
            $types = \App\Livewire\Projects\ProjectListClickup::TYPES;
            $duePresets = \App\Livewire\Projects\ProjectListClickup::DUE_DATE_PRESETS;
            $activeCount = $this->activeFilterCount;
            $picOptions = $this->picOptions;
            $clientOptions = $this->clientOptions;
            $assigneeOptions = $this->assigneeOptions;
        @endphp

        <div class="cu-filter cu-dropdown {{ $activeCount > 0 ? 'is-active' : '' }}"
             x-data="{ open: false, loaded: @js($filterOptionsLoaded), sections: { status: true, priority: true, type: false, due: false, dept: false, pic: false, client: false, assignee: false } }">
            <button type="button"
                    @click="open = !open; if (open && !loaded) { $wire.loadFilterOptions(); loaded = true; }"
                    class="cu-filter-btn">
                <svg class="cu-filter-ico" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18l-7 9v6l-4-2v-4z"/></svg>
                <span class="cu-filter-label">Filter</span>
                @if ($activeCount > 0)
                    <span class="cu-filter-count">{{ $activeCount }}</span>
                @endif
                <svg class="cu-filter-caret" width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><polyline points="6 9 12 15 18 9"/></svg>
            </button>

            <div x-show="open" @click.outside="open = false" x-cloak class="cu-filter-mega">
                <div class="cu-filter-mega-head">
                    <span class="cu-filter-mega-title">Filters</span>
                    @if ($this->hasActiveFilters())
                        <button type="button" wire:click="clearFilters" class="cu-filter-mega-clear">Clear all</button>
                    @endif
                </div>

                <div class="cu-filter-mega-body">
                    {{-- Status --}}
                    <div class="cu-fs">
                        <button type="button" @click="sections.status = !sections.status" class="cu-fs-head">
                            <span class="cu-fs-name">Status</span>
                            @if (count($statusFilter))
                                <span class="cu-fs-count">{{ count($statusFilter) }}</span>
                            @endif
                            <svg class="cu-fs-caret" :class="{ 'is-open': sections.status }" width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><polyline points="6 9 12 15 18 9"/></svg>
                        </button>
                        <div x-show="sections.status" class="cu-fs-body">
                            @foreach ($statuses as $key => $meta)
                                <label class="cu-dropdown-item">
                                    <input type="checkbox" wire:model.live="statusFilter" value="{{ $key }}">
                                    <span class="cu-pill" style="color: {{ $meta['color'] }}; background: {{ $meta['bg'] }}">
                                        <span class="cu-pill-dot" style="background: {{ $meta['color'] }}"></span>
                                        {{ $meta['label'] }}
                                    </span>
                                </label>
                            @endforeach
                        </div>
                    </div>

                    {{-- Priority --}}
                    <div class="cu-fs">
                        <button type="button" @click="sections.priority = !sections.priority" class="cu-fs-head">
                            <span class="cu-fs-name">Priority</span>
                            @if (count($priorityFilter))
                                <span class="cu-fs-count">{{ count($priorityFilter) }}</span>
                            @endif
                            <svg class="cu-fs-caret" :class="{ 'is-open': sections.priority }" width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><polyline points="6 9 12 15 18 9"/></svg>
                        </button>
                        <div x-show="sections.priority" class="cu-fs-body">
                            @foreach ($priorities as $key => $meta)
                                <label class="cu-dropdown-item">
                                    <input type="checkbox" wire:model.live="priorityFilter" value="{{ $key }}">
                                    <span class="cu-pill" style="color: {{ $meta['color'] }}; background: {{ $meta['bg'] }}">{{ $meta['label'] }}</span>
                                </label>
                            @endforeach
                        </div>
                    </div>

                    {{-- Type --}}
                    <div class="cu-fs">
                        <button type="button" @click="sections.type = !sections.type" class="cu-fs-head">
                            <span class="cu-fs-name">Type</span>
                            @if ($typeFilter !== 'all')
                                <span class="cu-fs-count">1</span>
                            @endif
                            <svg class="cu-fs-caret" :class="{ 'is-open': sections.type }" width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><polyline points="6 9 12 15 18 9"/></svg>
                        </button>
                        <div x-show="sections.type" class="cu-fs-body">
                            @foreach ($types as $key => $label)
                                <label class="cu-dropdown-item">
                                    <input type="radio" name="typeFilter" wire:model.live="typeFilter" value="{{ $key }}" class="cu-radio">
                                    <span class="cu-fs-option">{{ $label }}</span>
                                </label>
                            @endforeach
                        </div>
                    </div>

                    {{-- Due Date --}}
                    <div class="cu-fs">
                        <button type="button" @click="sections.due = !sections.due" class="cu-fs-head">
                            <span class="cu-fs-name">Due Date</span>
                            @if ($dueDateFilter !== 'any')
                                <span class="cu-fs-count">1</span>
                            @endif
                            <svg class="cu-fs-caret" :class="{ 'is-open': sections.due }" width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><polyline points="6 9 12 15 18 9"/></svg>
                        </button>
                        <div x-show="sections.due" class="cu-fs-body">
                            @foreach ($duePresets as $key => $label)
                                <label class="cu-dropdown-item">
                                    <input type="radio" name="dueDateFilter" wire:model.live="dueDateFilter" value="{{ $key }}" class="cu-radio">
                                    <span class="cu-fs-option">{{ $label }}</span>
                                </label>
                            @endforeach
                        </div>
                    </div>

                    {{-- Departemen (single choice; the sidebar sub-menu sets the same filter via ?dept=) --}}
                    @if ($this->departmentOptions->isNotEmpty())
                        <div class="cu-fs">
                            <button type="button" @click="sections.dept = !sections.dept" class="cu-fs-head">
                                <span class="cu-fs-name">Departemen</span>
                                @if ($departmentFilter !== '')
                                    <span class="cu-fs-count">1</span>
                                @endif
                                <svg class="cu-fs-caret" :class="{ 'is-open': sections.dept }" width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><polyline points="6 9 12 15 18 9"/></svg>
                            </button>
                            <div x-show="sections.dept" class="cu-fs-body cu-fs-body-scroll">
                                <label class="cu-dropdown-item">
                                    <input type="radio" name="departmentFilter" wire:model.live="departmentFilter" value="" class="cu-radio">
                                    <span class="cu-fs-option">Semua departemen</span>
                                </label>
                                @foreach ($this->departmentOptions as $dept)
                                    <label class="cu-dropdown-item">
                                        <input type="radio" name="departmentFilter" wire:model.live="departmentFilter" value="{{ $dept->id }}" class="cu-radio">
                                        <span class="cu-fs-option">{{ $dept->name }}</span>
                                    </label>
                                @endforeach
                                <label class="cu-dropdown-item">
                                    <input type="radio" name="departmentFilter" wire:model.live="departmentFilter" value="none" class="cu-radio">
                                    <span class="cu-fs-option">Tanpa departemen</span>
                                </label>
                            </div>
                        </div>
                    @endif

                    {{-- PIC --}}
                    @if ($picOptions->isNotEmpty())
                        <div class="cu-fs">
                            <button type="button" @click="sections.pic = !sections.pic" class="cu-fs-head">
                                <span class="cu-fs-name">PIC</span>
                                @if (count($picFilter))
                                    <span class="cu-fs-count">{{ count($picFilter) }}</span>
                                @endif
                                <svg class="cu-fs-caret" :class="{ 'is-open': sections.pic }" width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><polyline points="6 9 12 15 18 9"/></svg>
                            </button>
                            <div x-show="sections.pic" class="cu-fs-body cu-fs-body-scroll">
                                @foreach ($picOptions as $pic)
                                    <label class="cu-dropdown-item">
                                        <input type="checkbox" wire:model.live="picFilter" value="{{ $pic->id }}">
                                        <span class="cu-fs-option">{{ $pic->name }}</span>
                                    </label>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    {{-- Client --}}
                    @if ($clientOptions->isNotEmpty())
                        <div class="cu-fs">
                            <button type="button" @click="sections.client = !sections.client" class="cu-fs-head">
                                <span class="cu-fs-name">Client</span>
                                @if (count($clientFilter))
                                    <span class="cu-fs-count">{{ count($clientFilter) }}</span>
                                @endif
                                <svg class="cu-fs-caret" :class="{ 'is-open': sections.client }" width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><polyline points="6 9 12 15 18 9"/></svg>
                            </button>
                            <div x-show="sections.client" class="cu-fs-body cu-fs-body-scroll">
                                @foreach ($clientOptions as $client)
                                    <label class="cu-dropdown-item">
                                        <input type="checkbox" wire:model.live="clientFilter" value="{{ $client->id }}">
                                        <span class="cu-fs-option">{{ $client->name }}</span>
                                    </label>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    {{-- Assignee --}}
                    @if ($assigneeOptions->isNotEmpty())
                        <div class="cu-fs">
                            <button type="button" @click="sections.assignee = !sections.assignee" class="cu-fs-head">
                                <span class="cu-fs-name">Assignee</span>
                                @if (count($assigneeFilter))
                                    <span class="cu-fs-count">{{ count($assigneeFilter) }}</span>
                                @endif
                                <svg class="cu-fs-caret" :class="{ 'is-open': sections.assignee }" width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><polyline points="6 9 12 15 18 9"/></svg>
                            </button>
                            <div x-show="sections.assignee" class="cu-fs-body cu-fs-body-scroll">
                                @foreach ($assigneeOptions as $user)
                                    <label class="cu-dropdown-item">
                                        <input type="checkbox" wire:model.live="assigneeFilter" value="{{ $user->id }}">
                                        <span class="cu-fs-option">{{ $user->name }}</span>
                                    </label>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    {{-- Active clients only --}}
                    <div class="cu-fs">
                        <label class="cu-fs-head cu-fs-toggle-row">
                            <span class="cu-fs-name">Klien Aktif Saja</span>
                            <input type="checkbox" wire:model.live="activeClientsOnly" class="cu-fs-toggle">
                        </label>
                    </div>
                </div>
            </div>
        </div>

        {{-- Columns toggle --}}
        @php $toggleable = \App\Livewire\Projects\ProjectListClickup::TOGGLEABLE_COLUMNS; @endphp
        <div class="cu-filter cu-dropdown" x-data="{ open: false }">
            <button type="button" @click="open = !open" class="cu-filter-btn cu-filter-btn-icon" title="Columns">
                <svg class="cu-filter-ico" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><rect x="3" y="3" width="7" height="18" rx="1"/><rect x="14" y="3" width="7" height="11" rx="1"/></svg>
            </button>
            <div x-show="open" @click.outside="open = false" x-cloak class="cu-dropdown-panel">
                @foreach ($toggleable as $key => $label)
                    <label class="cu-dropdown-item">
                        <input type="checkbox" wire:click="toggleColumn('{{ $key }}')" {{ $this->isColumnVisible($key) ? 'checked' : '' }}>
                        <span class="cu-fs-option">{{ $label }}</span>
                    </label>
                @endforeach
            </div>
        </div>

        <div class="cu-toolbar-spacer"></div>

        {{-- View toggle --}}
        <div class="cu-view-toggle" role="tablist" aria-label="Tampilan">
            <button type="button"
                    wire:click="switchView('list')"
                    class="{{ $viewMode === 'list' ? 'active' : '' }}"
                    role="tab"
                    aria-selected="{{ $viewMode === 'list' ? 'true' : 'false' }}"
                    title="Tampilan list">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="8" x2="21" y1="6" y2="6"/><line x1="8" x2="21" y1="12" y2="12"/><line x1="8" x2="21" y1="18" y2="18"/><line x1="3" x2="3.01" y1="6" y2="6"/><line x1="3" x2="3.01" y1="12" y2="12"/><line x1="3" x2="3.01" y1="18" y2="18"/></svg>
                List
            </button>
            <button type="button"
                    wire:click="switchView('board')"
                    class="{{ $viewMode === 'board' ? 'active' : '' }}"
                    role="tab"
                    aria-selected="{{ $viewMode === 'board' ? 'true' : 'false' }}"
                    title="Tampilan papan">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect width="7" height="18" x="3" y="3" rx="1"/><rect width="7" height="11" x="14" y="3" rx="1"/></svg>
                Papan
            </button>
        </div>
    </div>

    {{-- ============== SOP QUICK-FILTER PILLS ============== --}}
    @php
        $sopFacets = $this->sopFacets;
        $sopSelected = $this->sopSelection(); // normalised; never read $sopFilter directly
        $sopVisibleLimit = 6;
        $sopPrimary = array_slice($sopFacets['sops'], 0, $sopVisibleLimit);
        $sopOverflow = array_slice($sopFacets['sops'], $sopVisibleLimit);
        // If the selected SOP is hidden in the overflow, open the row expanded.
        $sopSelectedHidden = collect($sopOverflow)->contains(fn ($r) => in_array($r['id'], $sopSelected, true))
            || in_array('none', $sopSelected, true);
        $sopIsOn = fn (string $id): bool => in_array($id, $sopSelected, true);
        $sopAllOn = empty($sopSelected);
        // Pick a small icon from keywords in the SOP name; falls back to a document.
        $sopIcon = function (string $name): string {
            $n = strtolower($name);
            return match (true) {
                str_contains($n, 'sp2dk')                                              => 'mail',
                str_contains($n, 'pemeriksaan') || str_contains($n, 'audit')           => 'search',
                str_contains($n, 'keberatan') || str_contains($n, 'banding')           => 'scale',
                str_contains($n, 'keuangan')                                           => 'book',
                str_contains($n, 'spt') || str_contains($n, 'lapor')                   => 'clipboard',
                str_contains($n, 'npwp') || str_contains($n, 'pkp') || str_contains($n, 'nib')
                    || str_contains($n, 'nomor induk') || str_contains($n, 'permohonan')
                    || str_contains($n, 'perubahan')                                   => 'id',
                default                                                                => 'file',
            };
        };
    @endphp
    <div class="cu-sop-bar"
         role="group"
         aria-label="Filter SOP"
         x-data="{ more: @js($sopSelectedHidden) }"
         wire:loading.class="is-busy"
         wire:target="toggleSopFilter, sopFilter">
        <span class="cu-sop-bar-label">SOP</span>

        <div class="cu-sop-pills">
            <button type="button"
                    wire:click="toggleSopFilter('')"
                    class="cu-sop-pill {{ $sopAllOn ? 'is-on' : '' }}"
                    aria-pressed="{{ $sopAllOn ? 'true' : 'false' }}">
                @include('livewire.projects.partials.sop-icon', ['icon' => 'layers'])
                <span class="cu-sop-pill-label">Semua</span>
                <span class="cu-sop-pill-count">{{ $sopFacets['total'] }}</span>
            </button>

            @foreach ($sopPrimary as $sop)
                <button type="button"
                        wire:click="toggleSopFilter('{{ $sop['id'] }}')"
                        wire:key="sop-pill-{{ $sop['id'] }}"
                        class="cu-sop-pill {{ $sopIsOn($sop['id']) ? 'is-on' : '' }}"
                        aria-pressed="{{ $sopIsOn($sop['id']) ? 'true' : 'false' }}"
                        title="{{ $sop['name'] }}">
                    @include('livewire.projects.partials.sop-icon', ['icon' => $sopIcon($sop['name'])])
                    <span class="cu-sop-pill-label">{{ $sop['name'] }}</span>
                    <span class="cu-sop-pill-count">{{ $sop['count'] }}</span>
                </button>
            @endforeach

            @foreach ($sopOverflow as $sop)
                <button type="button"
                        x-show="more"
                        x-cloak
                        x-transition.opacity.duration.120ms
                        wire:click="toggleSopFilter('{{ $sop['id'] }}')"
                        wire:key="sop-pill-{{ $sop['id'] }}"
                        class="cu-sop-pill {{ $sopIsOn($sop['id']) ? 'is-on' : '' }}"
                        aria-pressed="{{ $sopIsOn($sop['id']) ? 'true' : 'false' }}"
                        title="{{ $sop['name'] }}">
                    @include('livewire.projects.partials.sop-icon', ['icon' => $sopIcon($sop['name'])])
                    <span class="cu-sop-pill-label">{{ $sop['name'] }}</span>
                    <span class="cu-sop-pill-count">{{ $sop['count'] }}</span>
                </button>
            @endforeach

            @if ($sopFacets['none'] > 0 || $sopIsOn('none'))
                <button type="button"
                        x-show="more || @js($sopIsOn('none'))"
                        x-cloak
                        x-transition.opacity.duration.120ms
                        wire:click="toggleSopFilter('none')"
                        class="cu-sop-pill cu-sop-pill-ghost {{ $sopIsOn('none') ? 'is-on' : '' }}"
                        aria-pressed="{{ $sopIsOn('none') ? 'true' : 'false' }}">
                    @include('livewire.projects.partials.sop-icon', ['icon' => 'slash'])
                    <span class="cu-sop-pill-label">Tanpa SOP</span>
                    <span class="cu-sop-pill-count">{{ $sopFacets['none'] }}</span>
                </button>
            @endif

            @if (count($sopOverflow) > 0 || $sopFacets['none'] > 0)
                <button type="button"
                        @click="more = !more"
                        class="cu-sop-more"
                        :aria-expanded="more">
                    <span x-show="!more">{{ count($sopOverflow) + ($sopFacets['none'] > 0 ? 1 : 0) }} lainnya</span>
                    <span x-show="more" x-cloak>Ringkas</span>
                    <svg class="cu-sop-more-caret" :class="{ 'is-open': more }" width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"/></svg>
                </button>
            @endif
        </div>
    </div>


    {{-- Compute "is there any data?" up-front so it's available in both views and the summary --}}
    @php
        $isAnyData = false;
        foreach ($grouped as $groupData) {
            if ($groupData['total'] > 0) { $isAnyData = true; break; }
        }
    @endphp

    @if ($viewMode === 'list')
    {{-- ============== TABLE HEADER ============== --}}
    @php $gridStyle = 'grid-template-columns: ' . $this->gridTemplate . ';'; @endphp
    <div class="cu-scroll-wrap">
    <div class="cu-table-head" style="{{ $gridStyle }}">
        <div class="cu-col-status"></div>
        <div class="cu-col-name cu-sortable" wire:click="sortBy('name')">
            Name
            @if ($sortField === 'name') <span class="cu-sort-arrow">{{ $sortDirection === 'asc' ? '↑' : '↓' }}</span> @endif
        </div>
        @if ($this->isColumnVisible('status'))
            <div class="cu-col-status-badge cu-sortable" wire:click="sortBy('status')">
                Status
                @if ($sortField === 'status') <span class="cu-sort-arrow">{{ $sortDirection === 'asc' ? '↑' : '↓' }}</span> @endif
            </div>
        @endif
        @if ($this->isColumnVisible('client'))
            <div class="cu-col-client cu-sortable" wire:click="sortBy('client')">
                Client
                @if ($sortField === 'client') <span class="cu-sort-arrow">{{ $sortDirection === 'asc' ? '↑' : '↓' }}</span> @endif
            </div>
        @endif
        @if ($this->isColumnVisible('type'))
            <div class="cu-col-type cu-sortable" wire:click="sortBy('type')">
                Type
                @if ($sortField === 'type') <span class="cu-sort-arrow">{{ $sortDirection === 'asc' ? '↑' : '↓' }}</span> @endif
            </div>
        @endif
        <div class="cu-col-priority cu-sortable" wire:click="sortBy('priority')">
            Priority
            @if ($sortField === 'priority') <span class="cu-sort-arrow">{{ $sortDirection === 'asc' ? '↑' : '↓' }}</span> @endif
        </div>
        @if ($this->isColumnVisible('assignees'))
            <div class="cu-col-assignees cu-sortable" wire:click="sortBy('assignees')">
                Assignees
                @if ($sortField === 'assignees') <span class="cu-sort-arrow">{{ $sortDirection === 'asc' ? '↑' : '↓' }}</span> @endif
            </div>
        @endif
        <div class="cu-col-department cu-sortable" wire:click="sortBy('department')">
            Departemen
            @if ($sortField === 'department') <span class="cu-sort-arrow">{{ $sortDirection === 'asc' ? '↑' : '↓' }}</span> @endif
        </div>
        @if ($this->isColumnVisible('progress'))
            <div class="cu-col-progress cu-sortable" wire:click="sortBy('progress')">
                Progress
                @if ($sortField === 'progress') <span class="cu-sort-arrow">{{ $sortDirection === 'asc' ? '↑' : '↓' }}</span> @endif
            </div>
        @endif
        <div class="cu-col-actions"></div>
    </div>

    {{-- ============== ROWS (grouped) ============== --}}
    @if (!$isAnyData)
        <div class="cu-empty">
            <div class="cu-empty-icon">📋</div>
            <h3>No projects found</h3>
            <p>Try adjusting your filters or create a new project.</p>
        </div>
    @else
        @php
            $prevCategory = null;
            $categoryDisplayLabels = [
                'not_started' => 'Not Started',
                'active'      => 'Active',
                'done'        => 'Done',
                'closed'      => 'Closed',
            ];
            $categoryDisplayColors = [
                'not_started' => '#64748b',
                'active'      => '#2563eb',
                'done'        => '#16a34a',
                'closed'      => '#dc2626',
            ];
        @endphp
        @foreach ($grouped as $groupKey => $groupData)
            @php
                $groupMeta = $this->getGroupColor((string) $groupKey);
                $groupLabel = $this->getGroupLabel((string) $groupKey);
                $isGroupExpanded = $groupData['expanded'];
                $currentCategory = ($groupBy === 'status') ? ($statuses[$groupKey]['category'] ?? null) : null;
                $showCategoryHeader = $currentCategory && $currentCategory !== $prevCategory;
            @endphp

            {{-- Parent category section header (only when grouping by status) --}}
            @if ($showCategoryHeader)
                <div class="cu-cat-header">
                    <span class="cu-cat-dot" style="background: {{ $categoryDisplayColors[$currentCategory] ?? '#64748b' }};"></span>
                    <span class="cu-cat-label">{{ $categoryDisplayLabels[$currentCategory] ?? ucfirst($currentCategory) }}</span>
                    <span class="cu-cat-rule"></span>
                </div>
                @php $prevCategory = $currentCategory; @endphp
            @endif

            <div class="cu-group-card {{ $groupBy === 'none' ? 'cu-group-card-flat' : '' }}" style="--group-color: {{ $groupMeta['color'] ?? '#6366f1' }};">
            @if ($groupBy !== 'none')
                <div class="cu-group-row">
                    <span class="cu-group-badge" style="background: {{ $groupMeta['color'] }};">
                        @if ($groupBy === 'status' && isset($groupMeta['shape']))
                            <span class="cu-group-badge-icon">
                                @include('livewire.projects.partials.status-shape', ['shape' => $groupMeta['shape'], 'color' => $groupMeta['color'], 'size' => 14, 'inverse' => true])
                            </span>
                        @else
                            <span class="cu-group-badge-dot"></span>
                        @endif
                        <span class="cu-group-badge-label">{{ $groupLabel }}</span>
                    </span>
                    <span class="cu-group-count-num">{{ $groupData['total'] }}</span>
                </div>
            @endif

            @foreach ($groupData['visible'] as $project)
                @php
                    $statusMeta = $statuses[$project->status] ?? ['label' => ucfirst($project->status), 'color' => '#64748b', 'bg' => '#f1f5f9', 'shape' => 'empty'];
                    $priorityMeta = $priorities[$project->priority] ?? ['label' => ucfirst($project->priority), 'color' => '#94a3b8', 'bg' => '#f1f5f9'];
                    $hasSteps = $project->steps_count > 0;
                    $progress = $project->steps_count > 0
                        ? round(($project->steps_completed_count / $project->steps_count) * 100)
                        : 0;

                    $dueClass = '';
                    $dueLabel = $project->due_date?->format('M j');
                    if ($project->due_date) {
                        $days = now()->startOfDay()->diffInDays($project->due_date->startOfDay(), false);
                        if ($days < 0) {
                            $dueClass = 'overdue';
                        } elseif ($days <= 3) {
                            $dueClass = 'due-soon';
                        }
                    }
                @endphp

                @php $projectUrl = $this->viewUrl($project); @endphp
                {{-- Whole row is the link. Clicks that land on an inner control
                     (status picker, priority, assignees, action menu) are left
                     alone; everything else navigates. Ctrl/Cmd-click opens a tab. --}}
                <div class="cu-row cu-row-link"
                     wire:key="proj-{{ $project->id }}"
                     style="{{ $gridStyle }}"
                     x-data
                     data-href="{{ $projectUrl }}"
                     role="link"
                     tabindex="0"
                     @click="
                        if ($event.target.closest('a, button, input, select, textarea, label, .cu-dropdown-panel')) return;
                        ($event.ctrlKey || $event.metaKey)
                            ? window.open($el.dataset.href, '_blank')
                            : window.location.assign($el.dataset.href);
                     "
                     @keydown.enter.self.prevent="window.location.assign($el.dataset.href)">
                    {{-- Status as clickable icon — opens hierarchical picker --}}
                    <div class="cu-col-status">
                        <div x-data="{ open: false }" class="cu-pill-wrap">
                            <button type="button" @click="open = !open"
                                    class="cu-status-icon"
                                    title="{{ $statusMeta['label'] }}"
                                    style="--s-color: {{ $statusMeta['color'] }}; --s-bg: {{ $statusMeta['bg'] }};">
                                @include('livewire.projects.partials.status-shape', ['shape' => $statusMeta['shape'] ?? 'empty', 'color' => $statusMeta['color']])
                            </button>
                            @include('livewire.projects.partials.status-picker', ['project' => $project, 'statuses' => $statuses])
                        </div>
                    </div>

                    <div class="cu-col-name">
                        <a href="{{ $projectUrl }}" class="cu-project-name">{{ $project->name }}</a>

                        {{-- Indikator inline: icon + angka. Alpine tooltip terjamin tampil saat hover. --}}
                        @if (($project->notes_count ?? 0) > 0 || ($project->uploaded_documents_count ?? 0) > 0)
                            <span class="cu-row-indicators">
                                @if (($project->uploaded_documents_count ?? 0) > 0)
                                    {{-- Status 'uploaded' = file baru, butuh review staff --}}
                                    <span class="cu-indicator-wrap"
                                          x-data="{ open: false }"
                                          @mouseenter="open = true"
                                          @mouseleave="open = false">
                                        <span class="cu-indicator cu-indicator-review">
                                            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                                                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                                                <circle cx="12" cy="12" r="3"/>
                                            </svg>
                                            <span>{{ $project->uploaded_documents_count }}</span>
                                        </span>
                                        <span x-show="open" x-cloak class="cu-indicator-tip">
                                            {{ $project->uploaded_documents_count }} dokumen menunggu review
                                        </span>
                                    </span>
                                @endif

                                @if (($project->notes_count ?? 0) > 0)
                                    <span class="cu-indicator-wrap"
                                          x-data="{ open: false }"
                                          @mouseenter="open = true"
                                          @mouseleave="open = false">
                                        <span class="cu-indicator cu-indicator-note">
                                            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                                                <path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"/>
                                            </svg>
                                            <span>{{ $project->notes_count }}</span>
                                        </span>
                                        <span x-show="open" x-cloak class="cu-indicator-tip">
                                            {{ $project->notes_count }} catatan
                                        </span>
                                    </span>
                                @endif
                            </span>
                        @endif
                    </div>

                    @if ($this->isColumnVisible('status'))
                        <div class="cu-col-status-badge">
                            <div x-data="{ open: false }" class="cu-pill-wrap">
                                <button type="button"
                                        @click="open = !open"
                                        class="cu-status-badge"
                                        title="Change status"
                                        style="--s-color: {{ $statusMeta['color'] }}; --s-bg: {{ $statusMeta['bg'] }};">
                                    <span class="cu-status-badge-icon">
                                        @include('livewire.projects.partials.status-shape', ['shape' => $statusMeta['shape'] ?? 'empty', 'color' => $statusMeta['color'], 'size' => 12])
                                    </span>
                                    <span class="cu-status-badge-label">{{ $statusMeta['label'] }}</span>
                                </button>
                                @include('livewire.projects.partials.status-picker', ['project' => $project, 'statuses' => $statuses])
                            </div>
                        </div>
                    @endif

                    @if ($this->isColumnVisible('client'))
                        <div class="cu-col-client">
                            @if ($project->client)
                                <span class="cu-client-name">{{ $project->client->name }}</span>
                            @else
                                <span class="cu-empty-cell">—</span>
                            @endif
                        </div>
                    @endif

                    @if ($this->isColumnVisible('type'))
                        <div class="cu-col-type">
                            @if ($project->type)
                                <span class="cu-type-tag cu-type-{{ $project->type }}">{{ $project->type === 'single' ? 'On Spot' : ucfirst($project->type) }}</span>
                            @else
                                <span class="cu-empty-cell">—</span>
                            @endif
                        </div>
                    @endif

                    {{-- Priority as flag + text --}}
                    <div class="cu-col-priority">
                        <div x-data="{ open: false }" class="cu-pill-wrap">
                            <button type="button" @click="open = !open"
                                    class="cu-flag-btn {{ $project->priority === 'low' || !$project->priority ? 'cu-flag-empty' : '' }}"
                                    title="Priority: {{ $priorityMeta['label'] }}"
                                    style="--p-color: {{ $priorityMeta['color'] }};">
                                @if ($project->priority === 'urgent' || $project->priority === 'normal')
                                    <svg width="13" height="13" viewBox="0 0 24 24" fill="currentColor" class="cu-flag-ico"><path d="M5 3v18h2v-7h11l-1.5-4 1.5-4H7V3z"/></svg>
                                @else
                                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="cu-flag-ico"><path d="M5 3v18"/><path d="M5 3h13l-2 4 2 4H5"/></svg>
                                @endif
                                <span class="cu-flag-label">{{ $priorityMeta['label'] }}</span>
                            </button>
                            <div x-show="open" @click.outside="open = false" x-cloak class="cu-dropdown-panel cu-pill-menu">
                                @foreach ($priorities as $key => $meta)
                                    <button type="button"
                                            wire:click="updatePriority({{ $project->id }}, '{{ $key }}')"
                                            @click="open = false"
                                            class="cu-pill-menu-item">
                                        <span class="cu-pill" style="color: {{ $meta['color'] }}; background: {{ $meta['bg'] }}">{{ $meta['label'] }}</span>
                                    </button>
                                @endforeach
                            </div>
                        </div>
                    </div>

                    {{-- Assignees — clickable to manage --}}
                    @if ($this->isColumnVisible('assignees'))
                    @php
                        $assignedIds = $project->teamMembers->pluck('id')->all();
                    @endphp
                    <div class="cu-col-assignees">
                        <div x-data="{ open: false }" class="cu-pill-wrap">
                            <button type="button" @click="open = !open" class="cu-avatar-trigger" title="Manage assignees">
                                <div class="cu-avatar-stack">
                                    @if ($project->pic)
                                        <div class="cu-avatar cu-avatar-pic" title="PIC: {{ $project->pic->name }}">
                                            @include('livewire.projects.partials.avatar', ['user' => $project->pic])
                                            <span class="cu-pic-badge">P</span>
                                        </div>
                                    @endif
                                    @foreach ($project->teamMembers->take(2) as $member)
                                        <div class="cu-avatar" title="{{ $member->name }}">
                                            @include('livewire.projects.partials.avatar', ['user' => $member])
                                        </div>
                                    @endforeach
                                    @if ($project->teamMembers->count() > 2)
                                        <div class="cu-avatar cu-avatar-more">+{{ $project->teamMembers->count() - 2 }}</div>
                                    @endif
                                    @if (!$project->pic && $project->teamMembers->isEmpty())
                                        <span class="cu-avatar cu-avatar-add" title="Add assignee">
                                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><path d="M12 5v14"/><path d="M5 12h14"/></svg>
                                        </span>
                                    @endif
                                </div>
                            </button>
                            <div x-show="open" @click.outside="open = false" x-cloak class="cu-dropdown-panel cu-assignee-panel">
                                <div class="cu-assignee-head">
                                    <span class="cu-assignee-head-label">Assignees</span>
                                    <span class="cu-assignee-head-count">{{ count($assignedIds) }} assigned</span>
                                </div>
                                <div class="cu-assignee-list">
                                    @foreach ($teamPool as $candidate)
                                        @php $isAssigned = in_array($candidate->id, $assignedIds, true); @endphp
                                        <button type="button"
                                                wire:click="{{ $isAssigned ? 'removeProjectMember' : 'addProjectMember' }}({{ $project->id }}, {{ $candidate->id }})"
                                                class="cu-assignee-row {{ $isAssigned ? 'is-assigned' : '' }}">
                                            <div class="cu-avatar cu-avatar-sm">
                                                @include('livewire.projects.partials.avatar', ['user' => $candidate])
                                            </div>
                                            <span class="cu-assignee-name">{{ $candidate->name }}</span>
                                            @if ($isAssigned)
                                                <svg class="cu-assignee-check" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                                            @endif
                                        </button>
                                    @endforeach
                                    @if ($teamPool->isEmpty())
                                        <div class="cu-assignee-empty">No users available.</div>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>
                    @endif

                    <div class="cu-col-department">
                        @if ($project->department)
                            <span class="cu-dept-pill" title="{{ $project->department->name }}">
                                <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 21h18"/><path d="M5 21V7l8-4v18"/><path d="M19 21V11l-6-4"/></svg>
                                <span class="cu-dept-pill-label">{{ $project->department->name }}</span>
                            </span>
                        @else
                            <span class="cu-empty-cell">—</span>
                        @endif
                    </div>

                    @if ($this->isColumnVisible('progress'))
                    <div class="cu-col-progress">
                        @if ($hasSteps)
                            <div class="cu-progress" title="{{ $project->steps_completed_count }}/{{ $project->steps_count }} steps">
                                <div class="cu-progress-bar">
                                    <div class="cu-progress-fill"
                                         style="width: {{ $progress }}%; background: {{ $progress === 100 ? '#22c55e' : '#0891b2' }};"></div>
                                </div>
                                <span class="cu-progress-label">{{ $progress }}%</span>
                            </div>
                        @else
                            <span class="cu-due-empty">—</span>
                        @endif
                    </div>
                    @endif

                    <div class="cu-col-actions">
                        <div x-data="{ open: false }" class="cu-pill-wrap">
                            <button type="button" @click="open = !open" class="cu-action-btn" aria-label="Actions">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor"><circle cx="12" cy="5" r="1.5"/><circle cx="12" cy="12" r="1.5"/><circle cx="12" cy="19" r="1.5"/></svg>
                            </button>
                            <div x-show="open" @click.outside="open = false" x-cloak class="cu-dropdown-panel cu-action-menu">
                                <a href="{{ $this->viewUrl($project) }}" class="cu-action-item">View</a>
                                <a href="{{ $this->editUrl($project) }}" class="cu-action-item">Edit</a>
                                <button
                                    type="button"
                                    wire:click="mountAction('deleteProject', { project: {{ $project->id }} })"
                                    @click="open = false"
                                    class="cu-action-item cu-action-item-danger">
                                    Hapus
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

            @endforeach

            {{-- "Show all" expand button per group --}}
            @if ($groupData['hasMore'])
                <div class="cu-group-more">
                    <button wire:click="toggleGroupExpand('{{ addslashes((string) $groupKey) }}')" class="cu-show-more-btn">
                        @if ($isGroupExpanded)
                            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><polyline points="18 15 12 9 6 15"/></svg>
                            Show less
                        @else
                            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><polyline points="6 9 12 15 18 9"/></svg>
                            Show {{ $groupData['hidden'] }} more
                        @endif
                    </button>
                </div>
            @endif
            </div> {{-- /cu-group-card --}}
        @endforeach
    @endif
    </div> {{-- /cu-scroll-wrap --}}
    @else
    {{-- ============== KANBAN / BOARD VIEW ============== --}}
    @php
        $kanbanCols = $this->kanbanColumns;
        // Which Livewire method handles a drop, given the current group?
        // 'client' is intentionally read-only (changing client is a heavy operation, not a drag-drop).
        $kbDropMethod = match ($groupBy) {
            'priority'   => 'updatePriority',
            'pic'        => 'updateProjectPic',
            'department' => 'updateProjectDepartment',
            // 'sop' read-only: mengganti SOP = operasi destruktif (regenerate steps/tasks),
            // bukan operasi drag-drop. Sama seperti 'client'.
            'none', 'client', 'sop' => null,
            default      => 'updateStatus',  // status (default)
        };
        $kbDragField = match ($groupBy) {
            'priority'   => 'priority',
            'pic'        => 'pic_id',
            'client'     => 'client_id',
            'department' => 'department_id',
            'sop'        => 'sop_id',
            'none'       => null,
            default      => 'status',
        };
    @endphp
    <div class="cu-kanban"
         x-data="{ draggedId: null, draggedFrom: null, hoverCol: null }">
        @foreach ($kanbanCols as $colId => $col)
            <section class="cu-kb-col"
                     :class="hoverCol === @js($colId) && draggedFrom !== @js($col['key']) ? 'is-drop-target' : ''"
                     @if ($kbDropMethod)
                        @dragover.prevent="hoverCol = @js($colId)"
                        @dragleave="hoverCol === @js($colId) && (hoverCol = null)"
                        @drop.prevent="
                            if (draggedId && draggedFrom !== @js($col['key'])) {
                                $wire.{{ $kbDropMethod }}(draggedId, @js($col['key']));
                            }
                            draggedId = null;
                            draggedFrom = null;
                            hoverCol = null;
                        "
                     @endif
                     >
                <header class="cu-kb-col-head" style="--col-color: {{ $col['color'] ?? 'var(--cu-line-strong)' }};">
                    <span class="cu-kb-col-marker">
                        @if ($groupBy === 'status' && isset($col['shape']))
                            @include('livewire.projects.partials.status-shape', ['shape' => $col['shape'], 'color' => $col['color'], 'size' => 12])
                        @else
                            <span class="cu-kb-col-dot" style="background: {{ $col['color'] ?? '#94a3b8' }};"></span>
                        @endif
                    </span>
                    <span class="cu-kb-col-name" title="{{ $col['label'] }}">{{ $col['label'] }}</span>
                    <span class="cu-kb-col-count">{{ $col['count'] }}</span>
                </header>

                <div class="cu-kb-col-body">
                    @forelse ($col['projects'] as $project)
                        @php
                            $projPrio = $priorities[$project->priority] ?? ['label' => ucfirst($project->priority), 'color' => '#94a3b8', 'bg' => '#f1f5f9'];
                            $projDueLabel = $project->due_date?->translatedFormat('j M');
                            $projDueClass = '';
                            if ($project->due_date) {
                                $days = now()->startOfDay()->diffInDays($project->due_date->startOfDay(), false);
                                if ($days < 0) $projDueClass = 'is-overdue';
                                elseif ($days <= 3) $projDueClass = 'is-due-soon';
                            }
                            // What value should `draggedFrom` carry, for same-column detection?
                            $cardCurrentValue = match ($groupBy) {
                                'priority' => $project->priority,
                                'pic'      => $project->pic_id,
                                'client'   => $project->client_id,
                                'none'     => null,
                                default    => $project->status,
                            };
                            $projStatusMeta = $statuses[$project->status] ?? null;
                        @endphp
                        <article class="cu-kb-card"
                                 wire:key="kb-{{ $project->id }}-{{ $colId }}"
                                 @if ($kbDropMethod)
                                    draggable="true"
                                    @dragstart="
                                        draggedId = {{ $project->id }};
                                        draggedFrom = @js($cardCurrentValue);
                                        $event.dataTransfer.effectAllowed = 'move';
                                    "
                                    @dragend="draggedId = null; draggedFrom = null; hoverCol = null;"
                                    :class="draggedId === {{ $project->id }} ? 'is-dragging' : ''"
                                 @endif
                                 data-href="{{ $this->viewUrl($project) }}"
                                 @click="
                                    if ($event.target.closest('a, button')) return;
                                    ($event.ctrlKey || $event.metaKey)
                                        ? window.open($el.dataset.href, '_blank')
                                        : window.location.assign($el.dataset.href);
                                 "
                                 @keydown.enter.self.prevent="window.location.assign($el.dataset.href)"
                                 role="link"
                                 tabindex="0">
                            <div class="cu-kb-card-top">
                                {{-- When grouped by something other than status, show a small status dot so context isn't lost --}}
                                @if ($groupBy !== 'status' && $projStatusMeta)
                                    <span class="cu-kb-card-stat" title="{{ $projStatusMeta['label'] }}">
                                        @include('livewire.projects.partials.status-shape', ['shape' => $projStatusMeta['shape'] ?? 'empty', 'color' => $projStatusMeta['color'], 'size' => 11])
                                    </span>
                                @endif
                                <h4 class="cu-kb-card-title">{{ $project->name }}</h4>
                                @if ($groupBy !== 'priority' && $project->priority && $project->priority !== 'normal')
                                    <span class="cu-kb-card-prio" style="--p-color: {{ $projPrio['color'] }};" title="{{ $projPrio['label'] }}">
                                        <svg width="9" height="9" viewBox="0 0 24 24" fill="currentColor"><path d="M4 21V4c0-.6.4-1 1-1h12l-2 4 2 4H5"/></svg>
                                    </span>
                                @endif
                            </div>

                            @if ($project->client || $project->department)
                                <div class="cu-kb-card-meta">
                                    {{-- Hide client when grouping BY client (redundant), same for department --}}
                                    @if ($project->client && $groupBy !== 'client')
                                        <span class="cu-kb-card-client" title="{{ $project->client->name }}">{{ $project->client->name }}</span>
                                    @endif
                                    @if ($project->client && $project->department && $groupBy !== 'client')
                                        <span class="cu-kb-card-sep">·</span>
                                    @endif
                                    @if ($project->department)
                                        <span class="cu-kb-card-dept" title="{{ $project->department->name }}">
                                            <svg width="9" height="9" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M3 21h18"/><path d="M5 21V7l8-4v18"/><path d="M19 21V11l-6-4"/></svg>
                                            {{ $project->department->name }}
                                        </span>
                                    @endif
                                </div>
                            @endif

                            <div class="cu-kb-card-foot">
                                <div class="cu-kb-card-people">
                                    @if ($project->pic && $groupBy !== 'pic')
                                        <span class="cu-avatar cu-avatar-pic cu-kb-card-avatar" title="PIC: {{ $project->pic->name }}">
                                            @include('livewire.projects.partials.avatar', ['user' => $project->pic])
                                        </span>
                                    @endif
                                    @foreach ($project->teamMembers->take(2) as $member)
                                        <span class="cu-avatar cu-kb-card-avatar" title="{{ $member->name }}">
                                            @include('livewire.projects.partials.avatar', ['user' => $member])
                                        </span>
                                    @endforeach
                                    @if ($project->teamMembers->count() > 2)
                                        <span class="cu-avatar cu-avatar-more cu-kb-card-avatar">+{{ $project->teamMembers->count() - 2 }}</span>
                                    @endif
                                </div>
                                @if ($projDueLabel)
                                    <span class="cu-kb-card-due {{ $projDueClass }}">
                                        <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" x2="16" y1="2" y2="6"/><line x1="8" x2="8" y1="2" y2="6"/><line x1="3" x2="21" y1="10" y2="10"/></svg>
                                        {{ $projDueLabel }}
                                    </span>
                                @endif
                            </div>
                        </article>
                    @empty
                        <div class="cu-kb-empty"
                             :class="hoverCol === @js($colId) && draggedFrom !== @js($col['key']) ? 'is-target' : ''">
                            Tidak ada proyek
                        </div>
                    @endforelse
                </div>
            </section>
        @endforeach
    </div>
    @endif

    {{-- Total summary --}}
    @if ($isAnyData)
        <div class="cu-list-footer">
            <span class="cu-list-total">
                {{ $totalCount }} {{ $totalCount === 1 ? 'project' : 'projects' }}
                @if ($isCapped)
                    <span class="cu-list-cap">· showing first {{ \App\Livewire\Projects\ProjectListClickup::HARD_CAP }}</span>
                @endif
            </span>
        </div>
    @endif

    {{-- ============== STATUS MANAGER MODAL ============== --}}
    @if ($showStatusManager)
        @php
            $smStatuses   = $this->statusesForManager;
            $smGrouped    = $smStatuses->groupBy('category');
            $smShapes     = \App\Models\ProjectStatus::SHAPES;
            $smCatMeta    = [
                'not_started' => ['label' => 'Belum Dimulai', 'bg' => '#f1f5f9', 'color' => '#475569'],
                'active'      => ['label' => 'Aktif',         'bg' => '#eff6ff', 'color' => '#1d4ed8'],
                'done'        => ['label' => 'Selesai',       'bg' => '#f0fdf4', 'color' => '#15803d'],
                'closed'      => ['label' => 'Ditutup',       'bg' => '#fef2f2', 'color' => '#b91c1c'],
            ];
        @endphp
        <div class="cu-sm-overlay" wire:keydown.escape.window="closeStatusManager">
            <div class="cu-sm-backdrop" wire:click="closeStatusManager"></div>

            <div class="cu-sm-modal" role="dialog" aria-modal="true">

                {{-- Modal header --}}
                <div class="cu-sm-modal-head">
                    <div>
                        <div class="cu-sm-modal-title">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9"/><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"/></svg>
                            Status Proyek
                        </div>
                        <p class="cu-sm-modal-sub">Kelola daftar status yang tersedia untuk semua proyek. Status sistem tidak dapat diedit atau dihapus.</p>
                    </div>
                    <button type="button" wire:click="closeStatusManager" class="cu-sm-close" aria-label="Tutup">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>
                    </button>
                </div>

                {{-- Modal body --}}
                <div class="cu-sm-modal-body">

                    {{-- Status table --}}
                    <div class="cu-sm-table">
                        <div class="cu-sm-thead">
                            <div class="cu-sm-th" style="flex:1">Status</div>
                            <div class="cu-sm-th" style="flex:0 0 140px; text-align:right">Aksi</div>
                        </div>

                        @foreach ($smCatMeta as $catKey => $catInfo)
                            @php $catStatuses = $smGrouped->get($catKey, collect()); @endphp
                            @if ($catStatuses->isNotEmpty())
                                <div class="cu-sm-cat-sep">
                                    <span class="cu-sm-cat-sep-badge" style="background:{{ $catInfo['bg'] }}; color:{{ $catInfo['color'] }};">
                                        {{ strtoupper($catInfo['label']) }}
                                    </span>
                                    <span class="cu-sm-cat-sep-rule"></span>
                                </div>

                                @foreach ($catStatuses as $smStatus)
                                    @php $isEditing = $editingStatusId === $smStatus->id; @endphp

                                    <div class="cu-sm-tr {{ $isEditing ? 'cu-sm-tr-editing' : '' }}" wire:key="sm-{{ $smStatus->id }}">

                                        {{-- Status cell --}}
                                        <div class="cu-sm-td" style="flex:1; overflow:visible;">
                                            @if ($isEditing)
                                                <div class="cu-sm-inline-edit">
                                                    <input type="color" wire:model="editColor" class="cu-sm-inline-color" title="Warna">
                                                    <div class="cu-sm-inline-shapes">
                                                        @foreach ($smShapes as $shapeKey)
                                                            <label class="cu-sm-shape-opt-sm {{ $editShape === $shapeKey ? 'is-selected' : '' }}" title="{{ ucfirst($shapeKey) }}">
                                                                <input type="radio" wire:model.live="editShape" value="{{ $shapeKey }}" class="cu-sm-shape-radio">
                                                                @include('livewire.projects.partials.status-shape', ['shape' => $shapeKey, 'color' => $editColor, 'size' => 13])
                                                            </label>
                                                        @endforeach
                                                    </div>
                                                    <div class="cu-sm-inline-input-wrap">
                                                        <input type="text" wire:model="editLabel" class="cu-sm-inline-input" placeholder="Nama status">
                                                        @error('editLabel') <span class="cu-sm-error" style="font-size:11px;">{{ $message }}</span> @enderror
                                                    </div>
                                                </div>
                                            @else
                                                <div class="cu-sm-td-status">
                                                    <span class="cu-sm-td-icon">
                                                        @include('livewire.projects.partials.status-shape', ['shape' => $smStatus->shape, 'color' => $smStatus->color, 'size' => 16])
                                                    </span>
                                                    <span class="cu-sm-td-label">{{ $smStatus->label }}</span>
                                                    @if ($smStatus->is_system)
                                                        <svg class="cu-sm-system-lock" width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" title="Status sistem"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                                                    @endif
                                                </div>
                                            @endif
                                        </div>

                                        {{-- Action cell --}}
                                        <div class="cu-sm-td cu-sm-td-right" style="flex:0 0 140px; gap:6px; flex-shrink:0; overflow:visible;">
                                            @if ($isEditing)
                                                <button type="button" wire:click="saveStatusEdit" class="cu-sm-save-btn">
                                                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><polyline points="20 6 9 17 4 12"/></svg>
                                                    Simpan
                                                </button>
                                                <button type="button" wire:click="cancelEdit" class="cu-sm-cancel-btn">Batal</button>
                                            @elseif ($smStatus->is_system)
                                                <span class="cu-sm-action-locked" title="Status sistem tidak dapat diubah">
                                                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                                                    Terkunci
                                                </span>
                                            @else
                                                <button type="button"
                                                        wire:click="startEditStatus({{ $smStatus->id }})"
                                                        class="cu-sm-edit-btn">
                                                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><path d="M12 20h9"/><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"/></svg>
                                                    Edit
                                                </button>
                                                @if ($smStatus->projects_count === 0)
                                                    <button type="button"
                                                            wire:click="deleteStatus({{ $smStatus->id }})"
                                                            wire:confirm="Hapus status '{{ $smStatus->label }}'? Tindakan ini tidak dapat dibatalkan."
                                                            class="cu-sm-delete-btn">
                                                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><path d="M3 6h18"/><path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"/><path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"/></svg>
                                                        Hapus
                                                    </button>
                                                @else
                                                    <span class="cu-sm-action-in-use" title="Status sedang digunakan">Digunakan</span>
                                                @endif
                                            @endif
                                        </div>

                                    </div>
                                @endforeach
                            @endif
                        @endforeach
                    </div>{{-- /cu-sm-table --}}

                    {{-- Add new status --}}
                    <div class="cu-sm-add-section">
                        <div class="cu-sm-add-head">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><path d="M12 5v14"/><path d="M5 12h14"/></svg>
                            Tambah Status Baru
                        </div>
                        <div class="cu-sm-add-grid">
                            <div class="cu-sm-field">
                                <label class="cu-sm-label">Label</label>
                                <input type="text" wire:model="newStatusLabel" class="cu-sm-input" placeholder="cth. Menunggu Persetujuan">
                                @error('newStatusLabel') <span class="cu-sm-error">{{ $message }}</span> @enderror
                            </div>
                            <div class="cu-sm-field">
                                <label class="cu-sm-label">Kategori</label>
                                <select wire:model="newStatusCategory" class="cu-sm-select">
                                    @foreach ($smCatMeta as $key => $info)
                                        <option value="{{ $key }}">{{ $info['label'] }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="cu-sm-field">
                                <label class="cu-sm-label">Warna</label>
                                <div class="cu-sm-color-wrap">
                                    <input type="color" wire:model="newStatusColor" class="cu-sm-color-input">
                                    <span class="cu-sm-color-value">{{ $newStatusColor }}</span>
                                </div>
                            </div>
                            <div class="cu-sm-field">
                                <label class="cu-sm-label">Bentuk</label>
                                <div class="cu-sm-shapes">
                                    @foreach ($smShapes as $shapeKey)
                                        <label class="cu-sm-shape-opt {{ $newStatusShape === $shapeKey ? 'is-selected' : '' }}" title="{{ ucfirst($shapeKey) }}">
                                            <input type="radio" wire:model.live="newStatusShape" value="{{ $shapeKey }}" class="cu-sm-shape-radio">
                                            @include('livewire.projects.partials.status-shape', ['shape' => $shapeKey, 'color' => $newStatusColor, 'size' => 16])
                                        </label>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                        <div class="cu-sm-add-footer">
                            <button type="button" wire:click="createStatus" class="cu-sm-create-btn">
                                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><path d="M12 5v14"/><path d="M5 12h14"/></svg>
                                Buat Status
                            </button>
                        </div>
                    </div>

                </div>{{-- /cu-sm-modal-body --}}
            </div>{{-- /cu-sm-modal --}}
        </div>{{-- /cu-sm-overlay --}}
    @endif

    {{-- ============== PROJECT VIEW MODAL ============== --}}
    {{-- Always rendered + Alpine controls visibility via x-show.
         This lets closeProjectView() use skipRender() — closing the modal
         doesn't trigger a full component re-render anymore. --}}
    <div x-show="$wire.viewingProjectId !== null" x-cloak>
        @if (! $this->viewingProject)
            {{-- Skeleton fallback — only renders if viewingProject is null
                 (e.g. project not found or transitional state). Open+load is a
                 single round-trip now, so this rarely shows in practice. --}}
            <div class="cu-pv-overlay" wire:keydown.escape.window="closeProjectView">
                <div class="cu-pv-backdrop" wire:click="closeProjectView"></div>
                <div class="cu-pv-modal" role="dialog" aria-modal="true" aria-label="Memuat proyek">
                    {{-- Header skeleton --}}
                    <header class="cu-pv-head">
                        <div class="cu-pv-head-id">
                            <div class="cu-skel cu-skel-badge"></div>
                            <div class="cu-skel cu-skel-title"></div>
                        </div>
                        <button type="button" wire:click="closeProjectView" class="cu-pv-icon-btn" aria-label="Tutup">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>
                        </button>
                    </header>

                    {{-- Body skeleton --}}
                    <div class="cu-pv-body">
                        <section class="cu-pv-info" aria-hidden="true">
                            {{-- dl-style placeholders --}}
                            <div class="cu-skel-dl">
                                @for ($i = 0; $i < 6; $i++)
                                    <div class="cu-skel-dl-row">
                                        <div class="cu-skel cu-skel-label"></div>
                                        <div class="cu-skel cu-skel-value"></div>
                                    </div>
                                @endfor
                            </div>

                            {{-- Progress placeholder --}}
                            <div class="cu-skel-progress">
                                <div class="cu-skel cu-skel-bar"></div>
                            </div>

                            {{-- Steps placeholder --}}
                            <div class="cu-skel-steps">
                                <div class="cu-skel cu-skel-section-title"></div>
                                @for ($i = 0; $i < 3; $i++)
                                    <div class="cu-skel-step">
                                        <div class="cu-skel cu-skel-step-icon"></div>
                                        <div class="cu-skel cu-skel-step-text"></div>
                                    </div>
                                @endfor
                            </div>
                        </section>

                        <aside class="cu-pv-info" aria-hidden="true">
                            <div class="cu-skel cu-skel-section-title"></div>
                            @for ($i = 0; $i < 3; $i++)
                                <div class="cu-skel-note">
                                    <div class="cu-skel cu-skel-note-meta"></div>
                                    <div class="cu-skel cu-skel-note-line"></div>
                                    <div class="cu-skel cu-skel-note-line short"></div>
                                </div>
                            @endfor
                        </aside>
                    </div>
                </div>
            </div>
        @else
        @php
            $p = $this->viewingProject;
            $pStatusMeta = $statuses[$p->status] ?? ['label' => ucfirst($p->status), 'color' => '#64748b', 'bg' => '#f1f5f9', 'shape' => 'empty'];
            $pPriorityMeta = $priorities[$p->priority] ?? ['label' => ucfirst($p->priority), 'color' => '#94a3b8', 'bg' => '#f1f5f9'];
            $pProgress = $p->steps_count > 0
                ? round(($p->steps_completed_count / $p->steps_count) * 100)
                : 0;
            $pDueClass = '';
            $pDueLabel = $p->due_date?->translatedFormat('j M Y');
            if ($p->due_date) {
                $days = now()->startOfDay()->diffInDays($p->due_date->startOfDay(), false);
                if ($days < 0) $pDueClass = 'is-overdue';
                elseif ($days <= 3) $pDueClass = 'is-due-soon';
            }
            $pTypeLabel = \App\Livewire\Projects\ProjectListClickup::TYPES[$p->type] ?? ucfirst($p->type);
            $noteTypes = [
                'general'   => ['label' => 'Umum',     'color' => 'var(--cu-muted)'],
                'important' => ['label' => 'Penting',  'color' => '#b45309'],
                'blocker'   => ['label' => 'Blokir',   'color' => '#b91c1c'],
            ];
        @endphp
        <div class="cu-pv-overlay" wire:keydown.escape.window="closeProjectView">
            <div class="cu-pv-backdrop" wire:click="closeProjectView"></div>

            <div class="cu-pv-modal" role="dialog" aria-modal="true" aria-labelledby="cu-pv-title">

                {{-- ============ HEADER ============ --}}
                <header class="cu-pv-head">
                    <div class="cu-pv-head-id">
                        <span class="cu-status-badge" style="--s-color: {{ $pStatusMeta['color'] }}; --s-bg: {{ $pStatusMeta['bg'] }};">
                            <span class="cu-status-badge-icon">
                                @include('livewire.projects.partials.status-shape', ['shape' => $pStatusMeta['shape'] ?? 'empty', 'color' => $pStatusMeta['color'], 'size' => 12])
                            </span>
                            <span class="cu-status-badge-label">{{ $pStatusMeta['label'] }}</span>
                        </span>
                        <h2 id="cu-pv-title" class="cu-pv-title">{{ $p->name }}</h2>
                    </div>
                    <button type="button" wire:click="closeProjectView" class="cu-pv-icon-btn" aria-label="Tutup">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>
                    </button>
                </header>

                {{-- ============ BODY (2 columns) ============ --}}
                <div class="cu-pv-body">

                    {{-- LEFT: project info -------------------------------------- --}}
                    <section class="cu-pv-info" aria-label="Detail proyek">

                        <dl class="cu-pv-dl">
                            <dt>Klien</dt>
                            <dd>
                                @if ($p->client)
                                    {{ $p->client->name }}
                                @else
                                    <span class="cu-pv-empty">Tidak ada</span>
                                @endif
                            </dd>

                            <dt>Departemen</dt>
                            <dd>
                                @php $deptOptions = $this->departmentOptions; @endphp
                                <div class="cu-pv-edit" x-data="{ open: false }">
                                    <button type="button"
                                            @click="open = !open"
                                            class="cu-pv-edit-trigger {{ $p->department ? '' : 'is-empty' }}"
                                            :class="{ 'is-open': open }"
                                            :aria-expanded="open"
                                            aria-haspopup="listbox"
                                            title="Klik untuk mengubah departemen">
                                        <span class="cu-pv-edit-value">
                                            @if ($p->department)
                                                <svg class="cu-pv-edit-ico" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 21h18"/><path d="M5 21V7l8-4v18"/><path d="M19 21V11l-6-4"/></svg>
                                                {{ $p->department->name }}
                                            @else
                                                <svg class="cu-pv-edit-ico" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 21h18"/><path d="M5 21V7l8-4v18"/><path d="M19 21V11l-6-4"/></svg>
                                                <span class="cu-pv-edit-placeholder">Pilih departemen</span>
                                            @endif
                                        </span>
                                        <svg class="cu-pv-edit-caret" width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"/></svg>
                                    </button>
                                    <div x-show="open"
                                         x-cloak
                                         @click.outside="open = false"
                                         @keydown.escape.window="open = false"
                                         x-transition:enter="cu-pv-edit-enter"
                                         x-transition:enter-start="cu-pv-edit-enter-start"
                                         x-transition:enter-end="cu-pv-edit-enter-end"
                                         class="cu-pv-edit-panel"
                                         role="listbox">
                                        <div class="cu-pv-edit-panel-head">Pilih Departemen</div>
                                        <div class="cu-pv-edit-panel-list">
                                            @forelse ($deptOptions as $dept)
                                                @php $isCurrent = (int) $p->department_id === (int) $dept->id; @endphp
                                                <button type="button"
                                                        wire:click="updateProjectDepartment({{ $p->id }}, {{ $dept->id }})"
                                                        @click="open = false"
                                                        role="option"
                                                        aria-selected="{{ $isCurrent ? 'true' : 'false' }}"
                                                        class="cu-pv-edit-opt {{ $isCurrent ? 'is-current' : '' }}">
                                                    <svg class="cu-pv-edit-opt-ico" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 21h18"/><path d="M5 21V7l8-4v18"/><path d="M19 21V11l-6-4"/></svg>
                                                    <span class="cu-pv-edit-opt-label">{{ $dept->name }}</span>
                                                    @if ($isCurrent)
                                                        <svg class="cu-pv-edit-opt-check" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                                                    @endif
                                                </button>
                                            @empty
                                                <div class="cu-pv-edit-empty">Belum ada departemen terdaftar.</div>
                                            @endforelse
                                        </div>
                                        @if ($p->department_id)
                                            <div class="cu-pv-edit-panel-foot">
                                                <button type="button"
                                                        wire:click="updateProjectDepartment({{ $p->id }}, null)"
                                                        @click="open = false"
                                                        class="cu-pv-edit-clear">
                                                    <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>
                                                    Lepaskan departemen
                                                </button>
                                            </div>
                                        @endif
                                    </div>
                                </div>
                            </dd>

                            <dt>Prioritas</dt>
                            <dd>
                                <span class="cu-pv-prio" style="color: {{ $pPriorityMeta['color'] }};">
                                    <span class="cu-pv-prio-dot" style="background: {{ $pPriorityMeta['color'] }};"></span>
                                    {{ $pPriorityMeta['label'] }}
                                </span>
                            </dd>

                            <dt>Tenggat</dt>
                            <dd>
                                @if ($pDueLabel)
                                    <span class="cu-pv-due {{ $pDueClass }}">{{ $pDueLabel }}</span>
                                @else
                                    <span class="cu-pv-empty">Tidak ada</span>
                                @endif
                            </dd>

                            <dt>Tipe</dt>
                            <dd>{{ $pTypeLabel }}</dd>

                            <dt>PIC</dt>
                            <dd>
                                @if ($p->pic)
                                    <span class="cu-pv-person">
                                        <span class="cu-avatar cu-avatar-pic">
                                            @include('livewire.projects.partials.avatar', ['user' => $p->pic])
                                        </span>
                                        {{ $p->pic->name }}
                                    </span>
                                @else
                                    <span class="cu-pv-empty">Belum ditetapkan</span>
                                @endif
                            </dd>

                            <dt>Tim</dt>
                            <dd>
                                @if ($p->teamMembers->isNotEmpty())
                                    <span class="cu-pv-team">
                                        @foreach ($p->teamMembers as $member)
                                            <span class="cu-avatar" title="{{ $member->name }}">
                                                @include('livewire.projects.partials.avatar', ['user' => $member])
                                            </span>
                                        @endforeach
                                    </span>
                                @else
                                    <span class="cu-pv-empty">Belum ada</span>
                                @endif
                            </dd>

                            @if ($p->steps_count > 0)
                                <dt>Progress</dt>
                                <dd>
                                    <div class="cu-pv-progress">
                                        <div class="cu-pv-progress-bar" role="progressbar" aria-valuenow="{{ $pProgress }}" aria-valuemin="0" aria-valuemax="100">
                                            <div class="cu-pv-progress-fill" style="width: {{ $pProgress }}%"></div>
                                        </div>
                                        <span class="cu-pv-progress-text">
                                            <strong>{{ $pProgress }}%</strong>
                                            <span class="cu-pv-progress-sub">{{ $p->steps_completed_count }} / {{ $p->steps_count }} langkah</span>
                                        </span>
                                    </div>
                                </dd>
                            @endif
                        </dl>

                        @if ($p->description)
                            <div class="cu-pv-block">
                                <h3 class="cu-pv-h3">Deskripsi</h3>
                                <div class="cu-pv-prose">{!! $p->description !!}</div>
                            </div>
                        @endif

                        @if ($p->steps->isNotEmpty())
                            <div class="cu-pv-block">
                                <h3 class="cu-pv-h3">Langkah <span class="cu-pv-h3-count">{{ $p->steps->count() }}</span></h3>
                                <ol class="cu-pv-steps">
                                    @foreach ($p->steps as $step)
                                        @php
                                            $stepMeta = $stepStatuses[$step->status] ?? ['label' => ucfirst($step->status), 'color' => '#64748b'];
                                            $stepDone = $step->status === 'completed';
                                            $hasDocs = $step->requiredDocuments && $step->requiredDocuments->isNotEmpty();
                                        @endphp
                                        <li class="cu-pv-step {{ $stepDone ? 'is-done' : '' }}">
                                            <div class="cu-pv-step-row">
                                                <span class="cu-pv-step-marker {{ $stepDone ? 'is-done' : '' }}" style="--m-color: {{ $stepMeta['color'] }};">
                                                    @if ($stepDone)
                                                        <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                                                    @endif
                                                </span>
                                                <span class="cu-pv-step-text">{{ $step->name }}</span>
                                                @if ($hasDocs)
                                                    <span class="cu-pv-step-doc-count" title="{{ $step->requiredDocuments->count() }} dokumen">
                                                        <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6"/></svg>
                                                        {{ $step->requiredDocuments->count() }}
                                                    </span>
                                                @endif
                                                <span class="cu-pv-step-state" style="color: {{ $stepMeta['color'] }};">{{ $stepMeta['label'] }}</span>
                                            </div>
                                            @if ($hasDocs)
                                                <div class="cu-pv-step-docs">
                                                    @foreach ($step->requiredDocuments as $reqDoc)
                                                        @php
                                                            $submissions = $reqDoc->submittedDocuments;
                                                            $hasSubs = $submissions && $submissions->isNotEmpty();
                                                            $reqDocStatus = $reqDoc->status ?? null;
                                                            $approvedNoDoc = $reqDocStatus === 'approved_without_document';
                                                            $reqDocStatusMeta = match ($reqDocStatus) {
                                                                'approved'                  => ['label' => 'Disetujui',                 'tone' => 'approved'],
                                                                'approved_without_document' => ['label' => 'Disetujui tanpa dokumen',  'tone' => 'approved'],
                                                                'pending_review'            => ['label' => 'Menunggu peninjauan',      'tone' => 'pending'],
                                                                'uploaded'                  => ['label' => 'Diunggah',                 'tone' => 'pending'],
                                                                'rejected'                  => ['label' => 'Ditolak',                  'tone' => 'rejected'],
                                                                'draft'                     => ['label' => 'Draft',                    'tone' => 'draft'],
                                                                default => null,
                                                            };
                                                        @endphp
                                                        <div class="cu-pv-doc">
                                                            <div class="cu-pv-doc-head">
                                                                <span class="cu-pv-doc-name">
                                                                    {{ $reqDoc->name }}
                                                                    @if ($reqDoc->is_required)
                                                                        <span class="cu-pv-doc-req" title="Wajib">*</span>
                                                                    @endif
                                                                </span>
                                                                @if ($reqDocStatusMeta)
                                                                    <span class="cu-pv-doc-status-tag is-{{ $reqDocStatusMeta['tone'] }}">
                                                                        @if ($reqDocStatusMeta['tone'] === 'approved')
                                                                            <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                                                                        @elseif ($reqDocStatusMeta['tone'] === 'rejected')
                                                                            <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>
                                                                        @elseif ($reqDocStatusMeta['tone'] === 'pending')
                                                                            <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"><circle cx="12" cy="12" r="9"/><polyline points="12 7 12 12 15 14"/></svg>
                                                                        @endif
                                                                        {{ $reqDocStatusMeta['label'] }}
                                                                    </span>
                                                                @endif
                                                            </div>

                                                            {{-- Show a flat note if approved-without-document AND no file submissions exist --}}
                                                            @if ($approvedNoDoc && ! $hasSubs)
                                                                <div class="cu-pv-doc-no-file-note">
                                                                    <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><polyline points="20 6 9 17 4 12"/></svg>
                                                                    Dokumen ini dianggap selesai tanpa unggahan file.
                                                                </div>
                                                            @elseif (! $hasSubs)
                                                                {{-- Empty state untuk required doc tanpa file: dashed placeholder --}}
                                                                <div class="cu-pv-doc-empty-state">
                                                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                                        <path d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                                                    </svg>
                                                                    <span>Belum ada file diunggah</span>
                                                                </div>
                                                            @endif
                                                            @if ($hasSubs)
                                                                <ul class="cu-pv-doc-files">
                                                                    @foreach ($submissions as $sub)
                                                                        @php
                                                                            $ext = strtoupper(pathinfo($sub->file_path, PATHINFO_EXTENSION) ?: 'FILE');
                                                                            $subStatusMeta = match ($sub->status) {
                                                                                'approved'       => ['label' => 'Disetujui',       'tone' => 'approved'],
                                                                                'rejected'       => ['label' => 'Ditolak',         'tone' => 'rejected'],
                                                                                'pending_review' => ['label' => 'Menunggu Review', 'tone' => 'pending'],
                                                                                'uploaded'       => ['label' => 'Diunggah',        'tone' => 'uploaded'],
                                                                                default          => ['label' => ucfirst((string) $sub->status), 'tone' => 'neutral'],
                                                                            };
                                                                        @endphp
                                                                        <li>
                                                                            <button type="button"
                                                                                    wire:click="openSubmittedDocumentPreview({{ $sub->id }})"
                                                                                    class="cu-pv-doc-file"
                                                                                    title="Klik untuk pratinjau">
                                                                                <span class="cu-pv-doc-file-ext">{{ $ext }}</span>
                                                                                <span class="cu-pv-doc-file-main">
                                                                                    <span class="cu-pv-doc-file-name" title="{{ basename($sub->file_path) }}">{{ basename($sub->file_path) }}</span>
                                                                                    <span class="cu-pv-doc-file-sub">
                                                                                        @if ($sub->user)<span>{{ $sub->user->name }}</span><span class="cu-pv-doc-sep">·</span>@endif
                                                                                        <span>{{ $sub->created_at?->diffForHumans() }}</span>
                                                                                    </span>
                                                                                </span>
                                                                                <span class="cu-pv-doc-file-status is-{{ $subStatusMeta['tone'] }}">
                                                                                    @if ($subStatusMeta['tone'] === 'approved')
                                                                                        <svg width="9" height="9" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                                                                                    @elseif ($subStatusMeta['tone'] === 'rejected')
                                                                                        <svg width="9" height="9" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>
                                                                                    @elseif ($subStatusMeta['tone'] === 'pending')
                                                                                        <svg width="9" height="9" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"><circle cx="12" cy="12" r="9"/><polyline points="12 7 12 12 15 14"/></svg>
                                                                                    @elseif ($subStatusMeta['tone'] === 'uploaded')
                                                                                        <svg width="9" height="9" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M12 19V5"/><polyline points="5 12 12 5 19 12"/></svg>
                                                                                    @endif
                                                                                    {{ $subStatusMeta['label'] }}
                                                                                </span>
                                                                                <span class="cu-pv-doc-dl" aria-hidden="true">
                                                                                    {{-- Preview icon — eye, replaces direct download --}}
                                                                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                                                                                        <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                                                                                        <circle cx="12" cy="12" r="3"/>
                                                                                    </svg>
                                                                                </span>
                                                                            </button>
                                                                        </li>
                                                                    @endforeach
                                                                </ul>
                                                            @endif
                                                        </div>
                                                    @endforeach
                                                </div>
                                            @endif
                                        </li>
                                    @endforeach
                                </ol>
                            </div>
                        @endif
                    </section>

                    {{-- RIGHT: notes column ------------------------------------- --}}
                    <aside class="cu-pv-notes-col" aria-label="Catatan">
                        <div class="cu-pv-notes-head">
                            <h3 class="cu-pv-h3">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6M9 13h6M9 17h4"/></svg>
                                Catatan
                                <span class="cu-pv-h3-count">{{ $p->notes->count() }}</span>
                            </h3>
                        </div>

                        {{-- Compose --}}
                        <form wire:submit.prevent="addNote" class="cu-pv-compose">
                            <div class="cu-pv-composer" x-data="{ focused: false }" :class="{ 'is-focused': focused }">
                                <textarea
                                    wire:model.live.debounce.300ms="newNoteContent"
                                    @focus="focused = true"
                                    @blur="focused = false"
                                    @keydown.meta.enter.prevent="$el.form.requestSubmit()"
                                    @keydown.ctrl.enter.prevent="$el.form.requestSubmit()"
                                    class="cu-pv-textarea"
                                    placeholder="Tulis catatan untuk proyek ini…"
                                    rows="3"
                                    maxlength="2000"></textarea>

                                <div class="cu-pv-composer-bar">
                                    <div class="cu-pv-type-pick" role="radiogroup" aria-label="Tipe catatan">
                                        @foreach ($noteTypes as $tk => $tm)
                                            <label class="cu-pv-type-opt {{ $newNoteType === $tk ? 'is-selected' : '' }}"
                                                   style="--t-color: {{ $tm['color'] }};"
                                                   title="{{ $tm['label'] }}">
                                                <input type="radio" wire:model.live="newNoteType" value="{{ $tk }}" class="cu-pv-type-radio">
                                                <span class="cu-pv-type-dot"></span>
                                                <span class="cu-pv-type-label">{{ $tm['label'] }}</span>
                                            </label>
                                        @endforeach
                                    </div>

                                    <div class="cu-pv-composer-end">
                                        @if (strlen($newNoteContent) > 0)
                                            <span class="cu-pv-char-count {{ strlen($newNoteContent) >= 1950 ? 'is-warn' : '' }}"
                                                  title="Sisa karakter">
                                                {{ 2000 - strlen($newNoteContent) }}
                                            </span>
                                        @endif
                                        <kbd class="cu-pv-hotkey" aria-hidden="true">⌘↵</kbd>
                                        <button type="submit"
                                                class="cu-pv-send"
                                                aria-label="Kirim catatan"
                                                wire:loading.attr="disabled"
                                                wire:target="addNote"
                                                {{ trim($newNoteContent) === '' ? 'disabled' : '' }}>
                                            <span wire:loading.remove wire:target="addNote">
                                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 2 11 13"/><path d="m22 2-7 20-4-9-9-4 20-7z"/></svg>
                                            </span>
                                            <span wire:loading wire:target="addNote" class="cu-pv-send-spin">
                                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><path d="M21 12a9 9 0 1 1-6.2-8.5"/></svg>
                                            </span>
                                        </button>
                                    </div>
                                </div>
                            </div>
                            @error('newNoteContent')
                                <span class="cu-pv-form-error">{{ $message }}</span>
                            @enderror
                        </form>

                        {{-- List --}}
                        <div class="cu-pv-notes-list">
                            @forelse ($p->notes as $note)
                                @php $nt = $noteTypes[$note->type] ?? $noteTypes['general']; @endphp
                                <article class="cu-pv-note {{ 'is-' . $note->type }}" wire:key="note-{{ $note->id }}">
                                    @if ($note->user)
                                        <span class="cu-avatar cu-pv-note-avatar">
                                            @include('livewire.projects.partials.avatar', ['user' => $note->user])
                                        </span>
                                    @else
                                        <span class="cu-avatar cu-pv-note-avatar cu-pv-note-avatar-unknown">?</span>
                                    @endif
                                    <div class="cu-pv-note-content">
                                        <div class="cu-pv-note-meta">
                                            <span class="cu-pv-note-author">{{ $note->user?->name ?? 'Tidak diketahui' }}</span>
                                            <time class="cu-pv-note-time"
                                                  datetime="{{ $note->created_at?->toIso8601String() }}"
                                                  title="{{ $note->created_at?->translatedFormat('j M Y, H:i') }}">
                                                {{ $note->created_at?->diffForHumans() }}
                                            </time>
                                            @if ($note->type !== 'general')
                                                <span class="cu-pv-note-tag is-{{ $note->type }}">
                                                    @if ($note->type === 'blocker')
                                                        <svg width="9" height="9" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round"><circle cx="12" cy="12" r="9"/><line x1="5" y1="5" x2="19" y2="19"/></svg>
                                                    @elseif ($note->type === 'important')
                                                        <svg width="9" height="9" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round"><line x1="12" y1="3" x2="12" y2="14"/><circle cx="12" cy="19" r="1.4" fill="currentColor"/></svg>
                                                    @endif
                                                    {{ $nt['label'] }}
                                                </span>
                                            @endif
                                        </div>
                                        <p class="cu-pv-note-body">{{ $note->content }}</p>
                                    </div>
                                </article>
                            @empty
                                <div class="cu-pv-notes-empty">
                                    <svg class="cu-pv-notes-empty-ico" width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6M9 13h6M9 17h4"/></svg>
                                    <p class="cu-pv-notes-empty-title">Belum ada catatan</p>
                                    <p class="cu-pv-notes-empty-sub">Catatan pertama membantu rekan tim mengikuti konteks proyek.</p>
                                </div>
                            @endforelse
                        </div>
                    </aside>

                </div>

                {{-- ============ FOOTER ============ --}}
                <footer class="cu-pv-foot">
                    <a href="{{ $this->viewUrl($p) }}" class="cu-pv-foot-cta">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M7 17 17 7"/><path d="M7 7h10v10"/></svg>
                        <span>Buka halaman lengkap proyek</span>
                    </a>
                </footer>
            </div>
        </div>
        @endif {{-- /skeleton-vs-loaded --}}
    </div> {{-- /x-show wrapper --}}

    @once
        <style>
            [x-cloak] { display: none !important; }

    /* ============================================
       Project list — restrained, confident, fast to scan.
       Chrome stays neutral; only status/priority pills
       carry color. One purple accent, used sparingly.
    ============================================ */
    .cu-root {
        --cu-ink: #0f172a;
        --cu-muted: #64748b;
        --cu-subtle: #94a3b8;
        --cu-line: #eef0f3;
        --cu-line-strong: #d8dde3;
        --cu-bg: #ffffff;
        --cu-bg-soft: #f7f8fa;
        --cu-bg-hover: #f4f5f7;
        --cu-accent: #6366f1;
        --cu-accent-ink: #4f46e5;
        --cu-accent-soft: #eef2ff;

        font-family: 'Plus Jakarta Sans', system-ui, sans-serif;
        color: var(--cu-ink);
        font-size: 13px;
        /* IMPORTANT: keep this as block layout (not flex/grid) — `position:
           sticky` inside flex/grid items needs special handling and was
           breaking the sticky group headers below. */
        display: block;
    }
    .cu-root > .cu-toolbar { margin-bottom: 16px; }
    .cu-root > .cu-table-head { margin-bottom: 0; }

    .dark .cu-root {
        --cu-ink: #f1f5f9;
        --cu-muted: #94a3b8;
        --cu-subtle: #64748b;
        --cu-line: #1f2733;
        --cu-line-strong: #2a3340;
        --cu-bg: #1a1f2a;
        --cu-bg-soft: #141923;
        --cu-bg-hover: #20262f;
        --cu-accent-soft: rgba(99, 102, 241, .12);
    }

    /* Toolbar — single row of evenly-sized neutral controls */
    .cu-toolbar {
        display: flex;
        align-items: center;
        gap: 8px;
        flex-wrap: wrap;
    }

    .cu-search {
        display: flex;
        align-items: center;
        gap: 10px;
        flex: 1 1 360px;
        max-width: 480px;
        height: 38px;
        padding: 0 8px 0 14px;
        background: var(--cu-bg-soft);
        border-radius: 10px;
        color: var(--cu-muted);
        transition: background .15s, color .15s, box-shadow .15s;
    }
    .cu-search:focus-within {
        background: var(--cu-bg);
        color: var(--cu-ink);
        box-shadow: 0 1px 4px rgba(15, 23, 42, .08);
    }
    .cu-search-icon { flex-shrink: 0; }
    .cu-search-kbd {
        font-family: 'SF Mono', Menlo, monospace;
        font-size: 10.5px;
        font-weight: 600;
        color: var(--cu-muted);
        background: var(--cu-bg);
        padding: 3px 7px;
        border-radius: 6px;
        box-shadow:
            inset 0 -1px 0 rgba(15, 23, 42, .06),
            0 0 0 1px rgba(15, 23, 42, .06);
        letter-spacing: .02em;
    }
    .cu-search:focus-within .cu-search-kbd { opacity: .5; }
    .cu-search input {
        background: transparent;
        border: 0; outline: 0;
        flex: 1;
        font: inherit;
        font-size: 13px;
        color: var(--cu-ink);
    }
    .cu-search input::placeholder { color: var(--cu-subtle); }

    .cu-control {
        display: flex; align-items: center; gap: 8px;
        font-size: 12px; color: var(--cu-muted);
    }
    .cu-control > label {
        font-weight: 500;
        color: var(--cu-muted);
    }

    /* ============================================
       Filter bar — each trigger shows its value
       inline. Active filters reveal a chip row.
    ============================================ */
    .cu-filter { position: relative; }

    .cu-filter-btn {
        display: inline-flex;
        align-items: center;
        gap: 7px;
        height: 38px;
        padding: 0 14px;
        background: var(--cu-bg);
        border: 0;
        border-radius: 10px;
        font: inherit;
        font-size: 13px;
        font-weight: 500;
        color: var(--cu-ink);
        cursor: pointer;
        box-shadow:
            inset 0 0 0 1px var(--cu-line-strong),
            0 1px 2px rgba(15, 23, 42, .03);
        transition: box-shadow .14s, transform .08s;
    }
    .cu-filter-btn:hover {
        box-shadow:
            inset 0 0 0 1px var(--cu-muted),
            0 2px 6px rgba(15, 23, 42, .07);
    }
    .cu-filter-btn:active { transform: translateY(1px); }
    .cu-filter-btn-icon {
        width: 38px;
        padding: 0;
        justify-content: center;
    }

    /* ----- Group-by stepper ---------------------------------------------
       Quiet, soft-filled pill in the same family as the search box:
       [ ‹ ]  GROUP BY / Value ⌄  [ › ]
       Arrows step through the options; the middle segment opens the
       full list. No ring or tint so it reads as one calm control. */
    .cu-groupby-ctl {
        position: relative;
        display: inline-flex;
        align-items: center;
        height: 38px;
        padding: 0 4px;
        gap: 2px;
        background: var(--cu-bg-soft);
        border-radius: 10px;
        transition: background .15s, box-shadow .15s;
    }
    .cu-groupby-ctl:hover,
    .cu-groupby-ctl:focus-within {
        background: var(--cu-bg);
        box-shadow: 0 1px 4px rgba(15, 23, 42, .08);
    }
    .dark .cu-groupby-ctl:hover,
    .dark .cu-groupby-ctl:focus-within {
        background: var(--cu-bg-hover);
        box-shadow: none;
    }
    .cu-groupby-arrow,
    .cu-groupby-current {
        background: transparent;
        border: 0;
        font: inherit;
        color: var(--cu-ink);
        cursor: pointer;
    }
    .cu-groupby-arrow {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 30px;
        height: 30px;
        border-radius: 8px;
        color: var(--cu-muted);
        transition: background .12s, color .12s, opacity .12s;
    }
    .cu-groupby-arrow:hover {
        background: var(--cu-bg-hover);
        color: var(--cu-ink);
    }
    .cu-groupby-arrow:active svg { transform: translateX(var(--nudge, 0)); }
    .cu-groupby-arrow:first-child { --nudge: -1px; }
    .cu-groupby-arrow:last-child  { --nudge:  1px; }
    .cu-groupby-arrow:disabled { opacity: .35; cursor: default; }
    .cu-groupby-arrow:disabled:hover { background: transparent; color: var(--cu-muted); }
    .cu-groupby-arrow svg { transition: transform .1s; }

    .cu-groupby-current {
        position: relative;
        display: flex;
        flex-direction: column;
        align-items: flex-start;
        justify-content: center;
        min-width: 96px;
        height: 30px;
        padding: 0 10px;
        border-radius: 8px;
        text-align: left;
        transition: background .12s;
    }
    .cu-groupby-current:hover { background: var(--cu-bg-hover); }
    .cu-groupby-cap {
        font-size: 9.5px;
        font-weight: 600;
        letter-spacing: .08em;
        text-transform: uppercase;
        line-height: 1;
        color: var(--cu-subtle);
        margin-bottom: 3px;
    }
    .cu-groupby-value {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        font-size: 13px;
        font-weight: 600;
        line-height: 1;
        color: var(--cu-ink);
        white-space: nowrap;
        animation: cu-groupby-in .16s ease-out;
    }
    .cu-groupby.is-none .cu-groupby-value { color: var(--cu-muted); font-weight: 500; }
    .cu-groupby-caret {
        color: var(--cu-subtle);
        transition: transform .15s;
    }
    .cu-groupby-current[aria-expanded="true"] .cu-groupby-caret { transform: rotate(180deg); }
    @keyframes cu-groupby-in {
        from { opacity: 0; transform: translateY(2px); }
        to   { opacity: 1; transform: none; }
    }

    /* Busy state: while Livewire re-renders, dim the value and spin a
       small ring in its place so the click is visibly acknowledged. */
    .cu-groupby-spin {
        position: absolute;
        right: 10px;
        top: 50%;
        width: 12px;
        height: 12px;
        margin-top: -6px;
        border-radius: 99px;
        border: 2px solid var(--cu-line-strong);
        border-top-color: var(--cu-accent);
        opacity: 0;
        transition: opacity .12s;
        pointer-events: none;
    }
    .cu-groupby-ctl.is-busy .cu-groupby-spin {
        opacity: 1;
        animation: cu-groupby-spin .7s linear infinite;
    }
    .cu-groupby-ctl.is-busy .cu-groupby-value { opacity: .45; }
    .cu-groupby-ctl.is-busy .cu-groupby-caret { visibility: hidden; }
    @keyframes cu-groupby-spin { to { transform: rotate(360deg); } }

    @media (max-width: 640px) {
        .cu-groupby-current { min-width: 0; }
    }

    /* ----- SOP quick-filter bar -----------------------------------------
       One calm row under the toolbar: a small "SOP" label, then chips.
       Chips are borderless soft-fill at rest and take the app's primary
       (Filament --primary-*, cyan) when selected, so they read as the
       same family as the rest of the admin. Only the top few SOPs show;
       the rest sit behind a "N lainnya" toggle so the row never crowds. */
    .cu-sop-bar {
        display: flex;
        align-items: flex-start;
        gap: 14px;
        margin: 2px 0 20px;
        transition: opacity .15s;
    }
    .cu-sop-bar.is-busy { opacity: .55; pointer-events: none; }
    .cu-sop-bar-label {
        flex-shrink: 0;
        padding-top: 10px;
        font-size: 10.5px;
        font-weight: 600;
        letter-spacing: .1em;
        text-transform: uppercase;
        color: var(--cu-subtle);
        line-height: 1;
    }
    .cu-sop-pills {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 8px 10px;
        min-width: 0;
    }
    .cu-sop-pill {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        height: 34px;
        padding: 0 6px 0 12px;
        max-width: 260px;
        background: var(--cu-bg);
        border: 0;
        border-radius: 99px;
        font: inherit;
        font-size: 12.5px;
        font-weight: 500;
        color: var(--cu-ink);
        cursor: pointer;
        box-shadow:
            inset 0 0 0 1px var(--cu-line-strong),
            0 1px 2px rgba(15, 23, 42, .04);
        transition: background .12s, color .12s, box-shadow .12s, transform .08s;
    }
    .cu-sop-pill:hover {
        box-shadow:
            inset 0 0 0 1px var(--cu-muted),
            0 2px 6px rgba(15, 23, 42, .08);
    }
    .cu-sop-pill:active { transform: translateY(1px); }
    .cu-sop-pill:focus-visible {
        outline: 2px solid rgb(var(--primary-500));
        outline-offset: 2px;
    }
    .dark .cu-sop-pill {
        background: var(--cu-bg-soft);
        box-shadow: inset 0 0 0 1px var(--cu-line-strong);
    }
    .dark .cu-sop-pill:hover { box-shadow: inset 0 0 0 1px var(--cu-muted); }
    .cu-sop-pill-ico { flex-shrink: 0; color: var(--cu-muted); }
    .cu-sop-pill-label {
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }
    .cu-sop-pill-count {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-width: 22px;
        height: 22px;
        padding: 0 7px;
        border-radius: 99px;
        background: var(--cu-bg-soft);
        font-size: 11px;
        font-weight: 600;
        font-variant-numeric: tabular-nums;
        color: var(--cu-muted);
        line-height: 1;
        transition: background .12s, color .12s;
    }
    .dark .cu-sop-pill-count { background: var(--cu-bg-hover); }

    /* Selected: solid primary fill, same voice as Filament's primary button. */
    .cu-sop-pill.is-on {
        background: rgb(var(--primary-600));
        color: #fff;
        box-shadow:
            0 1px 2px rgba(var(--primary-700), .35),
            0 2px 8px rgba(var(--primary-600), .25);
    }
    .cu-sop-pill.is-on:hover {
        background: rgb(var(--primary-700));
        box-shadow:
            0 1px 2px rgba(var(--primary-700), .4),
            0 3px 10px rgba(var(--primary-600), .3);
    }
    .cu-sop-pill.is-on .cu-sop-pill-ico { color: rgba(255, 255, 255, .85); }
    .cu-sop-pill.is-on .cu-sop-pill-count {
        background: rgba(255, 255, 255, .2);
        color: #fff;
    }
    .dark .cu-sop-pill.is-on {
        background: rgb(var(--primary-500));
        color: #08131a;
        box-shadow: 0 2px 8px rgba(var(--primary-500), .3);
    }
    .dark .cu-sop-pill.is-on:hover { background: rgb(var(--primary-400)); }
    .dark .cu-sop-pill.is-on .cu-sop-pill-ico { color: rgba(8, 19, 26, .75); }
    .dark .cu-sop-pill.is-on .cu-sop-pill-count {
        background: rgba(8, 19, 26, .16);
        color: #08131a;
    }

    /* "Tanpa SOP": dashed outline so it reads as the odd one out. */
    .cu-sop-pill-ghost {
        background: transparent;
        color: var(--cu-muted);
        box-shadow: none;
        border: 1px dashed var(--cu-line-strong);
    }
    .cu-sop-pill-ghost:hover { background: var(--cu-bg-soft); color: var(--cu-ink); box-shadow: none; }
    .dark .cu-sop-pill-ghost { background: transparent; box-shadow: none; }
    .cu-sop-pill-ghost.is-on { border-color: transparent; }

    /* "N lainnya" toggle: quiet pill, primary text. */
    .cu-sop-more {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        height: 34px;
        padding: 0 12px;
        background: transparent;
        border: 1px dashed rgba(var(--primary-600), .45);
        border-radius: 99px;
        font: inherit;
        font-size: 12.5px;
        font-weight: 600;
        color: rgb(var(--primary-700));
        cursor: pointer;
        transition: background .12s, border-color .12s;
    }
    .cu-sop-more:hover {
        background: rgba(var(--primary-500), .08);
        border-color: rgb(var(--primary-600));
    }
    .dark .cu-sop-more {
        color: rgb(var(--primary-300));
        border-color: rgba(var(--primary-400), .45);
    }
    .dark .cu-sop-more:hover { background: rgba(var(--primary-400), .12); }
    .cu-sop-more-caret { transition: transform .15s; }
    .cu-sop-more-caret.is-open { transform: rotate(180deg); }

    @media (max-width: 640px) {
        .cu-sop-bar { gap: 10px; }
        .cu-sop-bar-label { display: none; }
        .cu-sop-pill { max-width: 180px; }
    }

    .cu-filter-ico { color: var(--cu-muted); flex-shrink: 0; }
    .cu-filter-label { color: var(--cu-muted); font-weight: 500; }
    .cu-filter-sep { color: var(--cu-subtle); margin: 0 -2px; }
    .cu-filter-value {
        color: var(--cu-ink);
        font-weight: 600;
    }
    .cu-filter-count {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-width: 18px;
        height: 18px;
        padding: 0 6px;
        background: var(--cu-accent);
        color: white;
        font-size: 11px;
        font-weight: 700;
        border-radius: 99px;
        line-height: 1;
    }
    .cu-filter-caret {
        color: var(--cu-subtle);
        margin-left: 2px;
        transition: transform .15s;
    }

    /* Active filter trigger state */
    .cu-filter.is-active .cu-filter-btn {
        background: var(--cu-accent-soft);
        box-shadow:
            inset 0 0 0 1px var(--cu-accent),
            0 1px 2px rgba(99, 102, 241, .08);
    }
    .cu-filter.is-active .cu-filter-ico,
    .cu-filter.is-active .cu-filter-label { color: var(--cu-accent-ink); }

    /* Active clients toggle */
    .cu-active-clients-btn.is-on {
        background: var(--cu-accent-soft);
        box-shadow:
            inset 0 0 0 1px var(--cu-accent),
            0 1px 2px rgba(99, 102, 241, .08);
    }
    .cu-active-clients-btn.is-on .cu-filter-ico,
    .cu-active-clients-btn.is-on .cu-filter-label { color: var(--cu-accent-ink); }

    /* Dropdown panel */
    .cu-dropdown { position: relative; }
    .cu-dropdown-panel {
        position: absolute;
        top: calc(100% + 8px);
        left: 0;
        z-index: 50;
        background: var(--cu-bg);
        border-radius: 12px;
        padding: 6px;
        min-width: 240px;
        box-shadow:
            0 0 0 1px rgba(15, 23, 42, .04),
            0 12px 32px rgba(15, 23, 42, .12);
        max-height: 320px;
        overflow-y: auto;
    }

    .cu-dropdown-item {
        display: flex; align-items: center; gap: 10px;
        padding: 8px 10px;
        border-radius: 8px;
        cursor: pointer;
        font-size: 12.5px;
        color: var(--cu-ink);
        transition: background .1s;
    }
    .cu-dropdown-item:hover { background: var(--cu-bg-hover); }

    .cu-dropdown-row {
        display: flex;
        align-items: center;
        justify-content: space-between;
        width: 100%;
        padding: 8px 10px;
        background: transparent;
        border: 0;
        border-radius: 8px;
        font: inherit;
        font-size: 13px;
        color: var(--cu-ink);
        cursor: pointer;
        text-align: left;
        transition: background .1s;
    }
    .cu-dropdown-row:hover { background: var(--cu-bg-hover); }
    .cu-dropdown-row.is-active {
        color: var(--cu-accent-ink);
        font-weight: 600;
    }
    .cu-dropdown-row.is-active svg { color: var(--cu-accent); }

    /* ============================================
       Unified filter mega-panel
    ============================================ */
    .cu-filter-mega {
        position: absolute;
        top: calc(100% + 8px);
        left: 0;
        z-index: 50;
        width: 340px;
        max-height: 540px;
        background: var(--cu-bg);
        border-radius: 14px;
        box-shadow:
            0 0 0 1px rgba(15, 23, 42, .04),
            0 16px 40px rgba(15, 23, 42, .14);
        display: flex;
        flex-direction: column;
        overflow: hidden;
    }
    .cu-filter-mega-head {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 12px 16px;
        border-bottom: 1px solid var(--cu-line);
        flex-shrink: 0;
    }
    .cu-filter-mega-title {
        font-size: 13px;
        font-weight: 700;
        color: var(--cu-ink);
        letter-spacing: .01em;
    }
    .cu-filter-mega-clear {
        font: inherit;
        font-size: 12px;
        font-weight: 600;
        color: var(--cu-accent-ink);
        background: transparent;
        border: 0;
        cursor: pointer;
        padding: 4px 8px;
        border-radius: 6px;
        transition: background .12s;
    }
    .cu-filter-mega-clear:hover { background: var(--cu-accent-soft); }
    .cu-filter-mega-body {
        flex: 1;
        overflow-y: auto;
        padding: 4px;
    }

    /* Accordion sections */
    .cu-fs + .cu-fs { border-top: 1px solid var(--cu-line); }
    .cu-fs-head {
        display: flex;
        align-items: center;
        gap: 8px;
        width: 100%;
        padding: 10px 12px;
        background: transparent;
        border: 0;
        font: inherit;
        font-size: 12.5px;
        font-weight: 600;
        color: var(--cu-ink);
        cursor: pointer;
        text-align: left;
        border-radius: 8px;
        transition: background .1s;
    }
    .cu-fs-head:hover { background: var(--cu-bg-hover); }
    .cu-fs-name { flex: 1; }
    .cu-fs-toggle-row { cursor: pointer; }
    .cu-fs-toggle {
        width: 34px;
        height: 18px;
        appearance: none;
        -webkit-appearance: none;
        background: var(--cu-line-strong);
        border-radius: 99px;
        position: relative;
        cursor: pointer;
        transition: background .15s;
        flex-shrink: 0;
    }
    .cu-fs-toggle::after {
        content: '';
        position: absolute;
        top: 2px;
        left: 2px;
        width: 14px;
        height: 14px;
        border-radius: 50%;
        background: white;
        transition: transform .15s;
    }
    .cu-fs-toggle:checked { background: var(--cu-accent); }
    .cu-fs-toggle:checked::after { transform: translateX(16px); }
    .cu-fs-count {
        background: var(--cu-accent-soft);
        color: var(--cu-accent-ink);
        font-size: 10.5px;
        font-weight: 700;
        padding: 1px 7px;
        border-radius: 99px;
        line-height: 1.4;
    }
    .cu-fs-caret {
        color: var(--cu-subtle);
        transition: transform .18s;
    }
    .cu-fs-caret.is-open { transform: rotate(180deg); }
    .cu-fs-body {
        padding: 2px 6px 8px 6px;
    }
    .cu-fs-body-scroll {
        max-height: 200px;
        overflow-y: auto;
    }
    .cu-fs-option {
        font-size: 12.5px;
        font-weight: 500;
        color: var(--cu-ink);
    }

    /* Custom radio */
    .cu-dropdown-item input[type="radio"].cu-radio {
        appearance: none;
        -webkit-appearance: none;
        width: 16px; height: 16px;
        border: 1.5px solid var(--cu-line-strong);
        border-radius: 50%;
        background: var(--cu-bg);
        cursor: pointer;
        position: relative;
        flex-shrink: 0;
        transition: border-color .12s;
    }
    .cu-dropdown-item input[type="radio"].cu-radio:hover { border-color: var(--cu-accent); }
    .cu-dropdown-item input[type="radio"].cu-radio:checked {
        border-color: var(--cu-accent);
        border-width: 5px;
    }

    /* Custom checkbox */
    .cu-dropdown-item input[type="checkbox"] {
        appearance: none;
        -webkit-appearance: none;
        width: 16px; height: 16px;
        border: 1.5px solid var(--cu-line-strong);
        border-radius: 5px;
        background: var(--cu-bg);
        cursor: pointer;
        position: relative;
        flex-shrink: 0;
        transition: border-color .12s, background .12s;
    }
    .cu-dropdown-item input[type="checkbox"]:hover { border-color: var(--cu-accent); }
    .cu-dropdown-item input[type="checkbox"]:checked {
        background: var(--cu-accent);
        border-color: var(--cu-accent);
    }
    .cu-dropdown-item input[type="checkbox"]:checked::after {
        content: '';
        position: absolute; inset: 0;
        background-image: url("data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='white' stroke-width='3' stroke-linecap='round' stroke-linejoin='round'><polyline points='20 6 9 17 4 12'/></svg>");
        background-size: 11px;
        background-position: center;
        background-repeat: no-repeat;
    }

    /* ============================================
       Active filter chips row
    ============================================ */
    .cu-active-filters {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 8px;
        padding: 0 2px;
        animation: cuChipFade .2s ease-out;
    }
    @keyframes cuChipFade {
        from { opacity: 0; transform: translateY(-3px); }
        to   { opacity: 1; transform: translateY(0); }
    }
    .cu-active-label {
        font-size: 11px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: .08em;
        color: var(--cu-subtle);
        margin-right: 2px;
    }
    .cu-active-chip {
        display: inline-flex;
        align-items: center;
        gap: 7px;
        padding: 5px 7px 5px 11px;
        background: var(--cu-bg);
        border: 0;
        border-radius: 99px;
        font: inherit;
        font-size: 12px;
        color: var(--cu-ink);
        cursor: pointer;
        box-shadow:
            inset 0 0 0 1px var(--cu-line-strong),
            0 1px 2px rgba(15, 23, 42, .04);
        transition: box-shadow .12s, transform .08s;
    }
    .cu-active-chip:hover {
        box-shadow:
            inset 0 0 0 1px var(--cu-muted),
            0 2px 6px rgba(15, 23, 42, .08);
    }
    .cu-active-chip:active { transform: scale(.96); }
    .cu-active-chip svg {
        color: var(--cu-subtle);
        padding: 3px;
        margin-left: -2px;
        border-radius: 50%;
        box-sizing: content-box;
        transition: background .12s, color .12s;
    }
    .cu-active-chip:hover svg {
        background: var(--cu-bg-hover);
        color: var(--cu-ink);
    }
    .cu-chip-kind { color: var(--cu-muted); font-weight: 500; }
    .cu-chip-sep { color: var(--cu-subtle); }
    .cu-chip-value { font-weight: 600; }
    .cu-chip-pill {
        padding: 2px 8px;
        border-radius: 99px;
        font-size: 11px;
        font-weight: 600;
        display: inline-flex;
        align-items: center;
        gap: 5px;
    }
    .cu-chip-pill .cu-pill-dot { width: 5px; height: 5px; border-radius: 50%; }

    .cu-clear-all {
        font: inherit;
        font-size: 12px;
        font-weight: 600;
        color: var(--cu-muted);
        background: transparent;
        border: 0;
        cursor: pointer;
        padding: 6px 10px;
        margin-left: auto;
        border-radius: 6px;
        transition: color .12s, background .12s;
    }
    .cu-clear-all:hover { color: var(--cu-accent-ink); background: var(--cu-accent-soft); }

    .cu-toolbar-spacer { flex: 1; }

    .cu-view-toggle {
        display: flex;
        background: var(--cu-bg-soft);
        border: 1px solid var(--cu-line-strong);
        border-radius: 9px;
        padding: 3px;
        gap: 2px;
        height: 38px;
        box-shadow: 0 1px 2px rgba(15, 23, 42, .04);
    }
    .cu-view-toggle button {
        display: inline-flex; align-items: center; gap: 6px;
        padding: 0 14px;
        background: transparent;
        border: 0;
        border-radius: 6px;
        font: inherit;
        font-size: 13px;
        font-weight: 600;
        color: var(--cu-muted);
        cursor: pointer;
        transition: background 150ms, color 150ms, box-shadow 150ms;
    }
    .cu-view-toggle button svg { color: var(--cu-subtle); transition: color 150ms; }
    .cu-view-toggle button:hover:not(.active) {
        color: var(--cu-ink);
        background: var(--cu-bg-hover);
    }
    .cu-view-toggle button:hover:not(.active) svg { color: var(--cu-muted); }
    .cu-view-toggle button.active {
        background: var(--cu-accent);
        color: white;
        box-shadow:
            0 1px 2px rgba(99, 102, 241, .25),
            0 2px 6px rgba(99, 102, 241, .18);
    }
    .cu-view-toggle button.active svg { color: white; }
    .cu-view-toggle button:focus-visible {
        outline: 2px solid var(--cu-accent);
        outline-offset: 2px;
    }
    .cu-view-toggle button:disabled { opacity: .45; cursor: not-allowed; }
    .dark .cu-view-toggle { border-color: var(--cu-line-strong); }

    /* Table — clearer dividers, status icon column instead of status pill column */
    .cu-table-head {
        display: grid;
        gap: 10px;
        padding: 4px 18px 10px;
        font-size: 11px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: .06em;
        color: var(--cu-subtle);
        border-bottom: 1px solid var(--cu-line-strong);
    }
    .cu-sortable { cursor: pointer; user-select: none; transition: color .12s; }
    .cu-sortable:hover { color: var(--cu-ink); }
    .cu-sort-arrow { color: var(--cu-accent); margin-left: 2px; }

    /* Parent category section header — appears when grouping by status */
    .cu-cat-header {
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 28px 14px 4px;
    }
    .cu-cat-dot {
        width: 8px; height: 8px;
        border-radius: 50%;
        flex-shrink: 0;
    }
    .cu-cat-label {
        font-size: 10.5px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: .12em;
        color: var(--cu-muted);
    }
    .cu-cat-rule {
        flex: 1;
        height: 1px;
        background: var(--cu-line);
        margin-left: 6px;
    }
    /* Hierarchical status picker — grouped by parent category */
    .cu-status-picker {
        min-width: 240px;
        max-height: 420px;
        padding: 4px;
        overflow-y: auto;
    }
    .cu-status-section { padding: 4px 0; }
    .cu-status-section + .cu-status-section {
        border-top: 1px solid var(--cu-line);
        margin-top: 2px;
    }
    .cu-status-section-head {
        padding: 8px 10px 4px;
        font-size: 10px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: .1em;
        color: var(--cu-subtle);
    }
    .cu-status-option {
        display: flex;
        align-items: center;
        gap: 10px;
        width: 100%;
        padding: 7px 10px;
        background: transparent;
        border: 0;
        border-radius: 7px;
        font: inherit;
        font-size: 12.5px;
        color: var(--cu-ink);
        text-align: left;
        cursor: pointer;
        transition: background .1s;
    }
    .cu-status-option:hover { background: var(--cu-bg-hover); }
    .cu-status-option.is-current { background: var(--cu-accent-soft); }
    .cu-status-option-icon {
        display: inline-flex;
        align-items: center;
        flex-shrink: 0;
    }
    .cu-status-option-label {
        flex: 1;
        font-weight: 500;
    }
    .cu-status-option-check {
        color: var(--cu-accent);
        flex-shrink: 0;
    }

    /* ----- Group card — clean, flat, minimal ------------------------------
       Quiet container per group. The status color shows only inside the
       badge — the rest of the card stays neutral
       so rows are easy to scan. No shadows, just a thin border.
       NOTE: no overflow:hidden — would clip the status-picker dropdown that
       pops out of the card. Rounded corners are matched on first/last
       children instead. */
    .cu-group-card {
        position: relative;
        background: var(--cu-bg);
        border: 1px solid var(--cu-line);
        border-radius: 10px;
        margin-bottom: 20px;
        transition: border-color .15s;
    }
    .cu-group-card:hover {
        border-color: var(--cu-line-strong);
    }
    .dark .cu-group-card { border-color: var(--cu-line-strong); }
    .dark .cu-group-card:hover { border-color: var(--cu-muted); }

    /* Match the card's rounded corners on first/last children. */
    .cu-group-card > .cu-row:last-child,
    .cu-group-card > .cu-subrows:last-child .cu-subrow:last-child {
        border-bottom: 0;
        border-bottom-left-radius: 9px;
        border-bottom-right-radius: 9px;
    }
    .cu-group-card > .cu-group-more {
        border-bottom-left-radius: 9px;
        border-bottom-right-radius: 9px;
    }

    /* Group header — flat, with the status badge as the visual anchor. */
    .cu-group-row {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 13px 16px 12px 22px;     /* extra left padding for the accent bar */
        background: var(--cu-bg);
        border-bottom: 1px solid var(--cu-line);
        border-top-left-radius: 9px;
        border-top-right-radius: 9px;
    }
    /* ClickUp-style: solid pill, white text, no shadow */
    .cu-group-badge {
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
    .cu-group-badge-icon {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
    }
    .cu-group-badge-dot {
        width: 8px; height: 8px;
        border-radius: 50%;
        background: #ffffff;
        flex-shrink: 0;
    }
    .cu-group-badge-label {
        line-height: 1;
    }
    .cu-group-count-num {
        font-size: 13px;
        font-weight: 600;
        color: var(--cu-muted);
    }

    /* Status icon button — replaces status pill in rows */
    .cu-status-icon {
        width: 30px; height: 30px;
        display: inline-flex; align-items: center; justify-content: center;
        background: transparent;
        border: 0;
        border-radius: 8px;
        cursor: pointer;
        padding: 0;
        transition: background .12s, transform .08s;
    }
    .cu-status-icon:hover {
        background: var(--cu-bg-hover);
        transform: scale(1.06);
    }
    .cu-status-icon:active { transform: scale(.95); }
    .cu-status-icon svg { display: block; }

    /* Status badge — full labeled pill (new column) */
    .cu-col-status-badge {
        display: flex;
        align-items: center;
        min-width: 0;
    }
    .cu-status-badge {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        max-width: 100%;
        padding: 4px 10px 4px 8px;
        border: 0;
        border-radius: 999px;
        background: var(--s-bg, #f1f5f9);
        color: var(--s-color, #64748b);
        font: inherit;
        font-size: 11.5px;
        font-weight: 600;
        line-height: 1.1;
        letter-spacing: .005em;
        white-space: nowrap;
        cursor: pointer;
        box-shadow:
            inset 0 0 0 1px color-mix(in srgb, var(--s-color, #64748b) 22%, transparent),
            0 1px 0 rgba(15, 23, 42, .015);
        transition: box-shadow .14s, transform .08s, background .14s;
    }
    .cu-status-badge:hover {
        box-shadow:
            inset 0 0 0 1px color-mix(in srgb, var(--s-color, #64748b) 40%, transparent),
            0 2px 6px color-mix(in srgb, var(--s-color, #64748b) 18%, transparent);
        transform: translateY(-0.5px);
    }
    .cu-status-badge-icon {
        display: inline-flex;
        align-items: center;
        flex-shrink: 0;
        color: var(--s-color, #64748b);
    }
    .cu-status-badge-icon svg { display: block; }
    .cu-status-badge-label {
        overflow: hidden;
        text-overflow: ellipsis;
        min-width: 0;
    }
    /* Browsers without color-mix() — fall back to a static soft ring */
    @supports not (background: color-mix(in srgb, red 50%, transparent)) {
        .cu-status-badge { box-shadow: inset 0 0 0 1px rgba(15,23,42,.08); }
        .cu-status-badge:hover { box-shadow: inset 0 0 0 1px rgba(15,23,42,.18), 0 2px 6px rgba(15,23,42,.06); }
    }

    /* Priority — flag + text pill */
    .cu-flag-btn {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        height: 26px;
        padding: 0 10px;
        background: transparent;
        border: 0;
        border-radius: 7px;
        cursor: pointer;
        color: var(--p-color, var(--cu-muted));
        font: inherit;
        font-size: 12px;
        font-weight: 600;
        transition: background .12s, transform .08s;
    }
    .cu-flag-btn:hover {
        background: var(--cu-bg-hover);
        transform: translateY(-1px);
    }
    .cu-flag-btn:active { transform: translateY(0); }
    .cu-flag-ico { flex-shrink: 0; }
    .cu-flag-label {
        color: inherit;
        line-height: 1;
    }
    .cu-flag-empty { color: var(--cu-subtle); }
    .cu-flag-empty:hover { color: var(--cu-muted); }

    /* Show more button per group (footer of the card) */
    .cu-group-more {
        padding: 8px 18px;
        background: var(--cu-bg-soft);
        border-top: 1px solid var(--cu-line);
        display: flex;
        justify-content: flex-start;
    }
    .cu-show-more-btn {
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
        color: var(--cu-accent-ink);
        cursor: pointer;
        transition: background .12s;
    }
    .cu-show-more-btn:hover { background: var(--cu-accent-soft); }
    .cu-show-more-btn svg { color: var(--cu-accent); }

    /* List footer / total */
    .cu-list-footer {
        padding: 18px 4px 4px;
        display: flex;
        align-items: center;
        gap: 10px;
    }
    .cu-list-total {
        font-size: 12px;
        font-weight: 500;
        color: var(--cu-muted);
    }
    .cu-list-cap {
        color: var(--cu-subtle);
        font-weight: 500;
    }

    /* Substep dot indicator (replaces sub-step pill) */
    .cu-substep-dot {
        width: 10px; height: 10px;
        border-radius: 50%;
        box-shadow: 0 0 0 2.5px var(--cu-bg-soft);
        display: inline-block;
    }
    .cu-substep-priority {
        font-size: 10.5px;
        font-weight: 600;
        color: var(--cu-muted);
        text-transform: uppercase;
        letter-spacing: .05em;
    }

    /* Row — compact, clear divider */
    .cu-row {
        display: grid;
        gap: 10px;
        align-items: center;
        padding: 13px 16px 13px 22px;       /* extra left padding for the card's accent bar */
        background: transparent;
        border-bottom: 1px solid var(--cu-line);
        transition: background .12s;
    }
    /* Cell min-width: 0 lets long text inside a fixed/fr track ellipsis
       rather than push its column wider — only useful for the fr-based
       name column, but harmless on the rest. */
    .cu-row > *,
    .cu-table-head > *,
    .cu-subrow > * { min-width: 0; }
    .cu-row:hover { background: var(--cu-bg-hover); }
    .cu-row-link { cursor: pointer; }
    .cu-row-link:focus-visible {
        outline: 2px solid var(--cu-accent);
        outline-offset: -2px;
        border-radius: 6px;
    }
    .cu-row.expanded { background: var(--cu-bg-hover); }

    .cu-col-expand { display: flex; justify-content: center; }
    .cu-expand-btn {
        width: 20px; height: 20px;
        display: flex; align-items: center; justify-content: center;
        background: transparent;
        border: 0;
        border-radius: 4px;
        cursor: pointer;
        color: var(--cu-subtle);
        transition: background .12s, color .12s;
    }
    .cu-expand-btn:hover { background: var(--cu-line); color: var(--cu-ink); }

    .cu-project-name {
        /* Reset button defaults so it visually matches a clean text link */
        background: transparent;
        border: 0;
        padding: 0;
        cursor: pointer;
        text-align: left;
        font-family: inherit;

        font-weight: 600;
        font-size: 13.5px;
        color: var(--cu-ink);
        text-decoration: none;
        transition: color .12s;
        display: block;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
        max-width: 100%;
    }
    .cu-project-name:hover { color: var(--cu-accent-ink); }

    /* Row indicators — icon + number, no background. Alpine-driven tooltip. */
    .cu-row-indicators {
        display: inline-flex;
        align-items: center;
        gap: 10px;
        margin-left: 8px;
        vertical-align: middle;
    }
    .cu-indicator-wrap {
        position: relative;
        display: inline-flex;
        align-items: center;
        cursor: default;
    }
    .cu-indicator {
        display: inline-flex;
        align-items: center;
        gap: 3px;
        font-size: 11.5px;
        font-weight: 600;
        line-height: 1;
    }
    .cu-indicator svg { flex-shrink: 0; opacity: .9; }
    .cu-indicator-note    { color: var(--cu-muted); }
    .cu-indicator-review  { color: #d97706; }  /* amber-600 — needs attention */
    .dark .cu-indicator-review { color: #fbbf24; }  /* amber-400 */

    /* Tooltip — positioned above the indicator, guaranteed visible */
    .cu-indicator-tip {
        position: absolute;
        bottom: calc(100% + 6px);
        left: 50%;
        transform: translateX(-50%);
        padding: 5px 9px;
        background: #0f172a;          /* slate-900 */
        color: #f8fafc;               /* slate-50 */
        font-size: 11.5px;
        font-weight: 500;
        line-height: 1.3;
        border-radius: 5px;
        white-space: nowrap;
        z-index: 50;
        pointer-events: none;
        box-shadow: 0 4px 12px rgba(0, 0, 0, .15);
    }
    .cu-indicator-tip::after {
        content: '';
        position: absolute;
        top: 100%;
        left: 50%;
        transform: translateX(-50%);
        border: 4px solid transparent;
        border-top-color: #0f172a;
    }
    .dark .cu-indicator-tip {
        background: #f8fafc;
        color: #0f172a;
    }
    .dark .cu-indicator-tip::after { border-top-color: #f8fafc; }

    /* Client column */
    .cu-client-name {
        font-size: 12.5px;
        font-weight: 700;
        color: var(--cu-ink);
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
        display: block;
    }
    .cu-empty-cell { color: var(--cu-subtle); font-size: 13px; }

    /* Type column — quiet tinted chip */
    .cu-type-tag {
        display: inline-flex;
        align-items: center;
        padding: 3px 9px;
        border-radius: 6px;
        font-size: 11px;
        font-weight: 600;
        background: var(--cu-bg-soft);
        color: var(--cu-muted);
    }
    .cu-type-single  { background: #ecfeff; color: #0e7490; }
    .cu-type-monthly { background: #fdf4ff; color: #a21caf; }
    .cu-type-yearly  { background: #fff7ed; color: #c2410c; }

    /* Pills — colors come from inline style */
    .cu-pill-wrap { position: relative; display: inline-block; }
    /* Inside the status badge cell the pill-wrap must take full cell width
       so its inline-flex children can shrink/ellipsis instead of overflowing
       into the next column. */
    .cu-col-status-badge .cu-pill-wrap {
        display: flex;
        width: 100%;
        min-width: 0;
    }
    .cu-col-status-badge .cu-status-badge {
        max-width: 100%;
        min-width: 0;
    }
    .cu-pill {
        display: inline-flex; align-items: center;
        gap: 7px;
        padding: 4px 11px;
        border-radius: 99px;
        font-size: 11.5px;
        font-weight: 600;
        white-space: nowrap;
        line-height: 1.4;
        box-shadow:
            inset 0 1px 0 rgba(255, 255, 255, .4),
            0 1px 2px rgba(15, 23, 42, .06);
    }
    .cu-pill-sm { padding: 2px 8px; font-size: 10.5px; }
    .cu-pill-dot { width: 6px; height: 6px; border-radius: 50%; flex-shrink: 0; }
    .cu-pill-btn {
        border: 0;
        cursor: pointer;
        font: inherit;
    }

    /* Editable pills — chevron + hover affordance for inline edit */
    .cu-pill-editable {
        position: relative;
        padding-right: 9px;
        transition: box-shadow .12s, transform .08s;
    }
    .cu-pill-editable .cu-pill-caret {
        opacity: .45;
        margin-left: -1px;
        transition: opacity .12s, transform .12s;
    }
    .cu-pill-editable:hover {
        box-shadow:
            inset 0 1px 0 rgba(255, 255, 255, .4),
            0 0 0 1.5px currentColor,
            0 2px 6px rgba(15, 23, 42, .10);
    }
    .cu-pill-editable:hover .cu-pill-caret { opacity: 1; transform: translateY(1px); }
    .cu-pill-editable:active { transform: scale(.97); }
    .cu-pill-label { line-height: 1; }
    .cu-pill-menu { min-width: 200px; }
    .cu-pill-menu-item {
        display: block; width: 100%;
        text-align: left;
        padding: 6px 8px;
        background: transparent;
        border: 0;
        border-radius: 6px;
        cursor: pointer;
        font: inherit;
        transition: background .1s;
    }
    .cu-pill-menu-item:hover { background: var(--cu-bg-hover); }

    /* Avatars */
    .cu-avatar-trigger {
        background: transparent;
        border: 0;
        padding: 4px 6px;
        margin: -4px -6px;
        border-radius: 8px;
        cursor: pointer;
        transition: background .12s;
    }
    .cu-avatar-trigger:hover { background: var(--cu-bg-hover); }
    .cu-avatar-stack { display: flex; align-items: center; }
    .cu-avatar {
        position: relative;
        width: 30px; height: 30px;
        margin-left: -8px;
        border-radius: 50%;
        background: var(--cu-accent);
        color: white;
        display: flex; align-items: center; justify-content: center;
        font-size: 11.5px;
        font-weight: 700;
        overflow: hidden;
        flex-shrink: 0;
    }
    .cu-avatar:first-child { margin-left: 0; }
    /* Layering — later avatars sit BELOW so the first is fully visible */
    .cu-avatar:nth-child(1) { z-index: 4; }
    .cu-avatar:nth-child(2) { z-index: 3; }
    .cu-avatar:nth-child(3) { z-index: 2; }
    .cu-avatar:nth-child(4) { z-index: 1; }
    .cu-avatar-inner {
        position: relative;
        width: 100%; height: 100%;
        display: flex; align-items: center; justify-content: center;
    }
    .cu-avatar img { width: 100%; height: 100%; object-fit: cover; display: block; }
    .cu-avatar-initials {
        font-size: inherit;
        font-weight: inherit;
        letter-spacing: .02em;
    }
    .cu-avatar-add {
        background: transparent;
        color: var(--cu-subtle);
        border: 1.5px dashed var(--cu-line-strong);
        transition: color .12s, border-color .12s;
    }
    .cu-avatar-trigger:hover .cu-avatar-add {
        color: var(--cu-accent);
        border-color: var(--cu-accent);
    }
    .cu-avatar-sm {
        width: 22px; height: 22px;
        margin-left: 0;
        font-size: 9.5px;
        border: 0;
    }

    /* Assignee picker panel */
    .cu-assignee-panel {
        width: 260px;
        padding: 0;
        max-height: 360px;
        display: flex;
        flex-direction: column;
    }
    .cu-assignee-head {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 10px 14px;
        border-bottom: 1px solid var(--cu-line);
    }
    .cu-assignee-head-label {
        font-size: 12.5px;
        font-weight: 700;
        color: var(--cu-ink);
    }
    .cu-assignee-head-count {
        font-size: 11px;
        font-weight: 600;
        color: var(--cu-muted);
    }
    .cu-assignee-list {
        flex: 1;
        overflow-y: auto;
        padding: 4px;
    }
    .cu-assignee-row {
        display: flex;
        align-items: center;
        gap: 10px;
        width: 100%;
        padding: 6px 8px;
        background: transparent;
        border: 0;
        border-radius: 7px;
        cursor: pointer;
        font: inherit;
        text-align: left;
        transition: background .1s;
    }
    .cu-assignee-row:hover { background: var(--cu-bg-hover); }
    .cu-assignee-row.is-assigned { background: var(--cu-accent-soft); }
    .cu-assignee-row.is-assigned:hover { background: #e0e7ff; }
    .cu-assignee-name {
        flex: 1;
        font-size: 12.5px;
        font-weight: 500;
        color: var(--cu-ink);
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }
    .cu-assignee-check {
        color: var(--cu-accent);
        flex-shrink: 0;
    }
    .cu-assignee-empty {
        padding: 16px;
        text-align: center;
        font-size: 12px;
        color: var(--cu-muted);
    }
    .cu-avatar-pic { background: #f59e0b; }
    .cu-pic-badge {
        position: absolute;
        bottom: -1px; right: -1px;
        background: #f59e0b;
        color: white;
        font-size: 8px;
        font-weight: 800;
        width: 13px; height: 13px;
        border-radius: 50%;
        display: flex; align-items: center; justify-content: center;
    }
    .cu-avatar-more {
        background: var(--cu-bg-hover);
        color: var(--cu-muted);
    }
    .cu-unassigned { color: var(--cu-subtle); font-size: 13px; }

    /* Due date — quiet by default; red/amber are signals */
    .cu-due {
        display: inline-flex; align-items: center; gap: 5px;
        font-size: 11.5px;
        font-weight: 500;
        color: var(--cu-muted);
    }
    .cu-due.overdue { color: #dc2626; font-weight: 600; }
    .cu-due.due-soon { color: #d97706; font-weight: 600; }
    .cu-due-empty { color: var(--cu-subtle); font-size: 13px; }

    /* Department — quiet pill in the row, mirrors the badge aesthetic */
    .cu-dept-pill {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        max-width: 100%;
        padding: 3px 9px;
        background: var(--cu-bg-soft);
        color: var(--cu-muted);
        border: 1px solid var(--cu-line);
        border-radius: 6px;
        font-size: 11.5px;
        font-weight: 600;
        line-height: 1.2;
        white-space: nowrap;
    }
    .cu-dept-pill svg { color: var(--cu-subtle); flex-shrink: 0; }
    .cu-dept-pill-label {
        overflow: hidden;
        text-overflow: ellipsis;
        min-width: 0;
    }

    /* Step subrow inherits the department column slot — show step due date here */
    .cu-substep-due {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        font-size: 11px;
        color: var(--cu-subtle);
        font-weight: 500;
    }

    /* Progress */
    .cu-progress { display: flex; align-items: center; gap: 8px; }
    .cu-progress-bar {
        flex: 1;
        height: 6px;
        background: var(--cu-line);
        border-radius: 99px;
        overflow: hidden;
    }
    .cu-progress-fill {
        height: 100%;
        border-radius: 99px;
        background: var(--cu-accent);
        transition: width .3s ease;
    }
    .cu-progress-label {
        font-size: 11px;
        font-weight: 600;
        color: var(--cu-muted);
        min-width: 30px;
    }

    /* Actions */
    .cu-action-btn {
        width: 24px; height: 24px;
        background: transparent;
        border: 0;
        border-radius: 5px;
        color: var(--cu-subtle);
        cursor: pointer;
        display: flex; align-items: center; justify-content: center;
        transition: background .12s, color .12s;
    }
    .cu-action-btn:hover { background: var(--cu-line); color: var(--cu-ink); }
    .cu-action-menu { right: 0; left: auto; min-width: 130px; }
    .cu-action-item {
        display: block;
        padding: 6px 10px;
        font-size: 12.5px;
        color: var(--cu-ink);
        text-decoration: none;
        border-radius: 6px;
        transition: background .1s;
    }
    .cu-action-item:hover { background: var(--cu-bg-hover); }

    /* Sub-rows — inset, no extra container styling */
    .cu-subrow {
        display: grid;
        gap: 10px;
        align-items: center;
        padding: 7px 18px 7px 30px;
        font-size: 12.5px;
        background: var(--cu-bg-soft);
        border-bottom: 1px solid var(--cu-line-strong);
        transition: background .12s;
    }
    .cu-subrow:hover { background: var(--cu-bg-hover); }
    .cu-subrow:last-of-type { border-bottom: 1px solid var(--cu-line); }
    .cu-subrow-name {
        display: flex; align-items: center; gap: 8px;
        color: var(--cu-ink);
    }
    .cu-subrow-indent {
        color: var(--cu-subtle);
        font-size: 12px;
    }

    /* Empty state */
    .cu-empty {
        padding: 64px 20px;
        text-align: center;
    }
    .cu-empty-icon {
        font-size: 36px;
        margin-bottom: 12px;
        opacity: .5;
        display: inline-block;
    }
    .cu-empty h3 { font-size: 15px; font-weight: 600; margin: 0 0 4px; color: var(--cu-ink); }
    .cu-empty p { font-size: 13px; color: var(--cu-muted); margin: 0; }

    /* Pagination */
    .cu-pagination { padding: 18px 0 4px; }
    .cu-pager {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 16px;
        flex-wrap: wrap;
    }
    .cu-pager-info {
        font-size: 12.5px;
        color: var(--cu-muted);
    }
    .cu-pager-info strong {
        color: var(--cu-ink);
        font-weight: 600;
    }
    .cu-pager-controls {
        display: flex;
        align-items: center;
        gap: 4px;
    }
    .cu-pager-btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-width: 34px;
        height: 34px;
        padding: 0 11px;
        background: transparent;
        border: 0;
        border-radius: 8px;
        font: inherit;
        font-size: 12.5px;
        font-weight: 600;
        color: var(--cu-muted);
        cursor: pointer;
        transition: background .12s, color .12s;
    }
    .cu-pager-btn:hover { background: var(--cu-bg-hover); color: var(--cu-ink); }
    .cu-pager-btn.active {
        background: var(--cu-accent);
        color: white;
        cursor: default;
    }
    .cu-pager-btn.active:hover { background: var(--cu-accent); }
    .cu-pager-btn-icon { padding: 0; min-width: 34px; }
    .cu-pager-btn.disabled {
        opacity: .35;
        cursor: not-allowed;
        pointer-events: none;
    }
    .cu-pager-ellipsis {
        padding: 0 6px;
        color: var(--cu-subtle);
        font-size: 13px;
    }

    /* Responsive: columns are inlined via PHP; users can also toggle via Columns picker */

    /* ============================================
       Enlarged status picker dropdown
    ============================================ */
    .cu-status-picker {
        min-width: 300px !important;
        max-height: 480px !important;
        padding: 6px !important;
    }
    .cu-status-section-head {
        padding: 10px 12px 5px !important;
        font-size: 10.5px !important;
        letter-spacing: .12em !important;
    }
    .cu-status-option {
        padding: 9px 12px !important;
        font-size: 13px !important;
        border-radius: 8px !important;
        gap: 12px !important;
    }
    .cu-status-option-label { font-size: 13px !important; font-weight: 600 !important; }
    .cu-status-option:hover { background: var(--cu-bg-soft) !important; }
    .cu-status-option.is-current {
        background: var(--cu-accent-soft) !important;
    }

    /* Picker footer — Manage Statuses link */
    .cu-status-picker-footer {
        border-top: 1px solid var(--cu-line);
        margin-top: 4px;
        padding: 6px 4px 2px;
    }
    .cu-status-picker-manage {
        display: flex;
        align-items: center;
        gap: 8px;
        width: 100%;
        padding: 8px 12px;
        background: transparent;
        border: 0;
        border-radius: 8px;
        font: inherit;
        font-size: 12.5px;
        font-weight: 600;
        color: var(--cu-accent-ink);
        cursor: pointer;
        text-align: left;
        transition: background .12s;
    }
    .cu-status-picker-manage:hover { background: var(--cu-accent-soft); }
    .cu-status-picker-manage svg { flex-shrink: 0; }

    /* Statuses toolbar button accent */
    .cu-statuses-btn {
        background: var(--cu-accent-soft);
        box-shadow: inset 0 0 0 1px var(--cu-accent), 0 1px 2px rgba(99,102,241,.08);
    }
    .cu-statuses-btn .cu-filter-ico,
    .cu-statuses-btn .cu-filter-label { color: var(--cu-accent-ink); }
    .cu-statuses-btn:hover {
        box-shadow: inset 0 0 0 1.5px var(--cu-accent-ink), 0 3px 8px rgba(99,102,241,.14);
    }

    /* ============================================
       Status manager modal
    ============================================ */
    .cu-sm-overlay {
        position: fixed;
        inset: 0;
        z-index: 300;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 24px;
    }
    .cu-sm-backdrop {
        position: absolute;
        inset: 0;
        background: rgba(15, 23, 42, .5);
        backdrop-filter: blur(3px);
        -webkit-backdrop-filter: blur(3px);
        animation: smBackdropIn .18s ease-out;
    }
    @keyframes smBackdropIn {
        from { opacity: 0; }
        to   { opacity: 1; }
    }

    .cu-sm-modal {
        position: relative;
        z-index: 1;
        width: 100%;
        max-width: 780px;
        max-height: 85vh;
        background: var(--cu-bg);
        border-radius: 16px;
        box-shadow:
            0 0 0 1px rgba(15,23,42,.06),
            0 24px 64px rgba(15,23,42,.22);
        display: flex;
        flex-direction: column;
        overflow: hidden;
        animation: smModalIn .2s cubic-bezier(.4,0,.2,1);
    }
    @keyframes smModalIn {
        from { transform: translateY(16px) scale(.98); opacity: .5; }
        to   { transform: translateY(0)    scale(1);   opacity: 1; }
    }

    /* Modal header */
    .cu-sm-modal-head {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 16px;
        padding: 22px 24px 18px;
        border-bottom: 1px solid var(--cu-line-strong);
        flex-shrink: 0;
    }
    .cu-sm-modal-title {
        display: flex;
        align-items: center;
        gap: 10px;
        font-size: 16px;
        font-weight: 700;
        color: var(--cu-ink);
        margin-bottom: 4px;
    }
    .cu-sm-modal-title svg { color: var(--cu-accent); flex-shrink: 0; }
    .cu-sm-modal-sub {
        font-size: 12.5px;
        color: var(--cu-muted);
        margin: 0;
        padding-left: 28px;
    }
    .cu-sm-close {
        width: 32px; height: 32px;
        display: flex; align-items: center; justify-content: center;
        background: transparent;
        border: 0;
        border-radius: 8px;
        color: var(--cu-muted);
        cursor: pointer;
        flex-shrink: 0;
        transition: background .12s, color .12s;
    }
    .cu-sm-close:hover { background: var(--cu-bg-hover); color: var(--cu-ink); }

    /* Modal scrollable body */
    .cu-sm-modal-body {
        flex: 1;
        overflow-y: auto;
        padding: 0 0 24px;
    }

    /* ---- Filament-style table ---- */
    .cu-sm-table {
        width: 100%;
    }
    .cu-sm-thead {
        display: flex;
        align-items: center;
        padding: 10px 24px;
        background: var(--cu-bg-soft);
        border-bottom: 1px solid var(--cu-line-strong);
        gap: 12px;
    }
    .cu-sm-th {
        font-size: 11px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: .07em;
        color: var(--cu-subtle);
        white-space: nowrap;
    }
    .cu-sm-th-center { text-align: center; }

    /* Category separator row */
    .cu-sm-cat-sep {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 16px 24px 6px;
    }
    .cu-sm-cat-sep-badge {
        display: inline-flex;
        align-items: center;
        padding: 3px 10px;
        border-radius: 99px;
        font-size: 10.5px;
        font-weight: 700;
        letter-spacing: .08em;
        white-space: nowrap;
    }
    .cu-sm-cat-sep-rule {
        flex: 1;
        height: 1px;
        background: var(--cu-line);
    }

    /* Data rows */
    .cu-sm-tr {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 11px 24px;
        border-bottom: 1px solid var(--cu-line);
        transition: background .1s;
    }
    .cu-sm-tr:hover { background: var(--cu-bg-hover); }
    .cu-sm-td {
        font-size: 13px;
        color: var(--cu-ink);
        display: flex;
        align-items: center;
        overflow: hidden;
    }
    .cu-sm-td-center { justify-content: center; }
    .cu-sm-td-right  { justify-content: flex-end; }

    /* Status cell */
    .cu-sm-td-status {
        display: flex;
        align-items: center;
        gap: 9px;
        overflow: hidden;
    }
    .cu-sm-td-icon { display: inline-flex; align-items: center; flex-shrink: 0; }
    .cu-sm-td-label {
        font-size: 13px;
        font-weight: 600;
        color: var(--cu-ink);
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }
    .cu-sm-system-lock {
        color: var(--cu-subtle);
        flex-shrink: 0;
        margin-left: 2px;
    }

    /* Category badge */
    .cu-sm-cat-badge {
        display: inline-flex;
        align-items: center;
        padding: 3px 9px;
        border-radius: 99px;
        font-size: 11.5px;
        font-weight: 600;
        white-space: nowrap;
    }

    /* Key cell */
    .cu-sm-key {
        font-family: 'SF Mono', Menlo, ui-monospace, monospace;
        font-size: 11.5px;
        font-weight: 500;
        color: var(--cu-muted);
        background: var(--cu-bg-soft);
        padding: 3px 8px;
        border-radius: 5px;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    /* Projects count */
    .cu-sm-count-badge {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-width: 22px;
        height: 22px;
        padding: 0 7px;
        background: var(--cu-bg-soft);
        border: 1px solid var(--cu-line-strong);
        border-radius: 99px;
        font-size: 11.5px;
        font-weight: 700;
        color: var(--cu-ink);
    }
    .cu-sm-count-zero {
        font-size: 12px;
        color: var(--cu-subtle);
    }

    /* Action states */
    .cu-sm-action-locked,
    .cu-sm-action-in-use {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        font-size: 11.5px;
        font-weight: 600;
        padding: 4px 10px;
        border-radius: 7px;
    }
    .cu-sm-action-locked {
        background: var(--cu-bg-soft);
        color: var(--cu-muted);
    }
    .cu-sm-action-in-use {
        background: #fffbeb;
        color: #92400e;
    }
    .dark .cu-sm-action-in-use {
        background: rgba(251,191,36,.1);
        color: #fbbf24;
    }
    .cu-sm-delete-btn {
        padding: 5px 11px;
        font-size: 12px;
        font-weight: 600;
        color: var(--cu-muted);
        background: transparent;
        border: 1px solid var(--cu-line-strong);
    }
    .cu-sm-delete-btn:hover {
        background: #fef2f2;
        border-color: #fca5a5;
        color: #dc2626;
    }
    .dark .cu-sm-delete-btn:hover {
        background: rgba(220,38,38,.12);
        border-color: #ef4444;
        color: #f87171;
    }

    /* Action buttons */
    .cu-sm-edit-btn,
    .cu-sm-delete-btn,
    .cu-sm-save-btn,
    .cu-sm-cancel-btn {
        display: inline-flex;
        align-items: center;
        flex-direction: row;
        gap: 5px;
        white-space: nowrap;
        font: inherit;
        cursor: pointer;
        border-radius: 7px;
        transition: background .12s, border-color .12s, color .12s;
    }
    .cu-sm-edit-btn {
        padding: 5px 11px;
        font-size: 12px;
        font-weight: 600;
        color: var(--cu-muted);
        background: transparent;
        border: 1px solid var(--cu-line-strong);
    }
    .cu-sm-edit-btn:hover {
        background: var(--cu-bg-soft);
        border-color: var(--cu-accent);
        color: var(--cu-accent);
    }
    .cu-sm-save-btn {
        padding: 5px 13px;
        font-size: 12px;
        font-weight: 700;
        color: white;
        background: var(--cu-accent);
        border: 1px solid transparent;
    }
    .cu-sm-save-btn:hover { background: var(--cu-accent-ink); }
    .cu-sm-cancel-btn {
        padding: 5px 11px;
        font-size: 12px;
        font-weight: 600;
        color: var(--cu-muted);
        background: transparent;
        border: 1px solid var(--cu-line-strong);
    }
    .cu-sm-cancel-btn:hover { background: var(--cu-bg-soft); color: var(--cu-ink); }

    /* ---- Add new status section ---- */
    .cu-sm-add-section {
        margin: 20px 24px 0;
        padding: 20px;
        background: var(--cu-bg-soft);
        border-radius: 12px;
        border: 1px solid var(--cu-line-strong);
    }
    .cu-sm-add-head {
        display: flex;
        align-items: center;
        gap: 8px;
        font-size: 13px;
        font-weight: 700;
        color: var(--cu-ink);
        margin-bottom: 18px;
    }
    .cu-sm-add-head svg { color: var(--cu-accent); }
    .cu-sm-add-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 14px 20px;
        margin-bottom: 16px;
    }
    .cu-sm-field { display: flex; flex-direction: column; gap: 6px; }
    .cu-sm-label {
        font-size: 11px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: .06em;
        color: var(--cu-muted);
    }
    .cu-sm-input,
    .cu-sm-select {
        height: 36px;
        padding: 0 12px;
        background: var(--cu-bg);
        border: 1px solid var(--cu-line-strong);
        border-radius: 8px;
        font: inherit;
        font-size: 13px;
        color: var(--cu-ink);
        outline: 0;
        transition: border-color .14s, box-shadow .14s;
        box-sizing: border-box;
        width: 100%;
    }
    .cu-sm-select {
        appearance: none;
        -webkit-appearance: none;
        padding-right: 32px;
        background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' viewBox='0 0 24 24' fill='none' stroke='%236b7280' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpolyline points='6 9 12 15 18 9'%3E%3C/polyline%3E%3C/svg%3E");
        background-repeat: no-repeat;
        background-position: right 10px center;
        cursor: pointer;
    }
    .cu-sm-input:focus,
    .cu-sm-select:focus {
        border-color: var(--cu-accent);
        box-shadow: 0 0 0 3px rgba(99,102,241,.12);
    }
    .cu-sm-input::placeholder { color: var(--cu-subtle); }
    .cu-sm-error {
        font-size: 11.5px;
        color: #dc2626;
        font-weight: 500;
    }
    .cu-sm-color-wrap { display: flex; align-items: center; gap: 8px; }
    .cu-sm-color-input {
        width: 36px; height: 36px;
        padding: 2px;
        border: 1px solid var(--cu-line-strong);
        border-radius: 8px;
        background: var(--cu-bg);
        cursor: pointer;
        flex-shrink: 0;
    }
    .cu-sm-color-value {
        font-size: 11.5px;
        font-family: 'SF Mono', Menlo, monospace;
        color: var(--cu-muted);
    }

    /* Shape picker */
    .cu-sm-shapes { display: flex; flex-wrap: wrap; gap: 6px; }
    .cu-sm-shape-opt {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 34px; height: 34px;
        border-radius: 8px;
        border: 1.5px solid var(--cu-line-strong);
        cursor: pointer;
        transition: border-color .12s, background .12s;
    }
    .cu-sm-shape-opt:hover { border-color: var(--cu-accent); background: var(--cu-accent-soft); }
    .cu-sm-shape-opt.is-selected {
        border-color: var(--cu-accent);
        background: var(--cu-accent-soft);
        box-shadow: 0 0 0 1px var(--cu-accent);
    }
    .cu-sm-shape-radio { display: none; }

    .cu-sm-add-footer { display: flex; justify-content: flex-end; }
    .cu-sm-create-btn {
        display: inline-flex;
        align-items: center;
        gap: 7px;
        height: 38px;
        padding: 0 22px;
        background: var(--cu-accent);
        border: 0;
        border-radius: 9px;
        font: inherit;
        font-size: 13px;
        font-weight: 700;
        color: white;
        cursor: pointer;
        transition: background .14s, box-shadow .14s, transform .08s;
        box-shadow: 0 2px 8px rgba(99,102,241,.3);
    }
    .cu-sm-create-btn:hover {
        background: var(--cu-accent-ink);
        box-shadow: 0 4px 14px rgba(99,102,241,.38);
        transform: translateY(-1px);
    }
    .cu-sm-create-btn:active { transform: translateY(0); }

    /* ---- Inline edit row ---- */
    .cu-sm-tr-editing { background: var(--cu-accent-soft) !important; }
    .cu-sm-inline-edit {
        display: flex;
        align-items: center;
        gap: 8px;
        width: 100%;
    }
    .cu-sm-inline-color {
        width: 30px;
        height: 30px;
        padding: 2px;
        border: 1px solid var(--cu-line-strong);
        border-radius: 7px;
        background: var(--cu-bg);
        cursor: pointer;
        flex-shrink: 0;
    }
    .cu-sm-inline-shapes {
        display: flex;
        flex-wrap: nowrap;
        gap: 4px;
        flex-shrink: 0;
    }
    .cu-sm-shape-opt-sm {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 26px;
        height: 26px;
        border-radius: 6px;
        border: 1.5px solid var(--cu-line-strong);
        cursor: pointer;
        transition: border-color .12s, background .12s;
        flex-shrink: 0;
    }
    .cu-sm-shape-opt-sm:hover { border-color: var(--cu-accent); background: var(--cu-accent-soft); }
    .cu-sm-shape-opt-sm.is-selected {
        border-color: var(--cu-accent);
        background: var(--cu-accent-soft);
        box-shadow: 0 0 0 1px var(--cu-accent);
    }
    .cu-sm-inline-input-wrap {
        flex: 1;
        display: flex;
        flex-direction: column;
        gap: 3px;
        min-width: 0;
    }
    .cu-sm-inline-input {
        width: 100%;
        height: 32px;
        padding: 0 10px;
        background: var(--cu-bg);
        border: 1px solid var(--cu-line-strong);
        border-radius: 7px;
        font: inherit;
        font-size: 13px;
        font-weight: 600;
        color: var(--cu-ink);
        outline: 0;
        transition: border-color .14s, box-shadow .14s;
        box-sizing: border-box;
    }
    .cu-sm-inline-input:focus {
        border-color: var(--cu-accent);
        box-shadow: 0 0 0 3px rgba(99,102,241,.12);
    }
    .cu-sm-inline-input::placeholder { color: var(--cu-subtle); font-weight: 400; }

    /* =====================================================================
       Project View Modal — two-column workspace.
       Left: definition-list info + description + steps. No background
       containers, just rhythm via dividers and whitespace.
       Right: notes column with a functional compose form and chronological
       feed. Wider modal (1040px) because two columns need room to breathe.
       ===================================================================== */
    .cu-pv-overlay {
        position: fixed;
        inset: 0;
        z-index: 320;
        display: flex;
        align-items: center;            /* center vertically */
        justify-content: center;
        padding: 3vh 16px;
        animation: cu-pv-fade 160ms cubic-bezier(.22, 1, .36, 1);
    }
    @keyframes cu-pv-fade { from { opacity: 0; } to { opacity: 1; } }
    .cu-pv-backdrop {
        position: fixed;
        inset: 0;
        background: rgba(15, 23, 42, .42);
    }
    .cu-pv-modal {
        position: relative;
        z-index: 1;
        width: 100%;
        max-width: 1340px;
        background: var(--cu-bg);
        border-radius: 12px;
        border: 1px solid var(--cu-line-strong);
        box-shadow:
            0 30px 70px -30px rgba(15, 23, 42, .32),
            0 10px 24px -10px rgba(15, 23, 42, .12);
        display: flex;
        flex-direction: column;
        max-height: calc(100vh - 5vh);
        overflow: hidden;
        animation: cu-pv-pop 200ms cubic-bezier(.22, 1, .36, 1);
    }
    @keyframes cu-pv-pop {
        from { opacity: 0; transform: translateY(6px); }
        to   { opacity: 1; transform: translateY(0); }
    }

    /* ----- Header ----- */
    .cu-pv-head {
        display: flex;
        align-items: center;
        gap: 14px;
        padding: 18px 22px;
        border-bottom: 1px solid var(--cu-line);
    }
    .cu-pv-head-id {
        flex: 1;
        min-width: 0;
        display: flex;
        align-items: center;
        gap: 12px;
    }
    .cu-pv-head-id .cu-status-badge {
        flex-shrink: 0;
        cursor: default;
    }
    .cu-pv-title {
        margin: 0;
        font-size: 17px;
        font-weight: 650;
        line-height: 1.35;
        color: var(--cu-ink);
        letter-spacing: -.005em;
        word-break: break-word;
        min-width: 0;
        flex: 1;
    }
    .cu-pv-head-actions {
        display: flex;
        align-items: center;
        gap: 4px;
        flex-shrink: 0;
    }
    .cu-pv-action {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 6px 11px;
        background: transparent;
        border: 1px solid var(--cu-line-strong);
        border-radius: 7px;
        font: inherit;
        font-size: 12px;
        font-weight: 600;
        color: var(--cu-muted);
        text-decoration: none;
        cursor: pointer;
        transition: background 150ms, border-color 150ms, color 150ms;
        white-space: nowrap;
    }
    .cu-pv-action:hover {
        background: var(--cu-bg);
        border-color: var(--cu-muted);
        color: var(--cu-ink);
    }
    .cu-pv-icon-btn {
        width: 30px;
        height: 30px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border: 0;
        background: transparent;
        border-radius: 7px;
        color: var(--cu-muted);
        cursor: pointer;
        transition: background 150ms, color 150ms;
    }
    .cu-pv-icon-btn:hover { background: var(--cu-bg-hover); color: var(--cu-ink); }
    .cu-pv-icon-btn:focus-visible {
        outline: 2px solid var(--cu-accent);
        outline-offset: 2px;
    }

    /* ----- Body (two columns) ----- */
    .cu-pv-body {
        flex: 1;                        /* fills remaining height between head + foot */
        display: grid;
        grid-template-columns: minmax(0, 1.8fr) minmax(300px, .65fr);
        gap: 0;
        overflow: hidden;
        min-height: 0;
    }

    /* ----- Footer with primary CTA ----- */
    .cu-pv-foot {
        flex-shrink: 0;
        padding: 12px 18px;
        border-top: 1px solid var(--cu-line);
        background: var(--cu-bg);
    }
    .cu-pv-foot-cta {
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        width: 100%;
        padding: 11px 18px;
        background: var(--cu-accent);
        color: white;
        border: 1px solid var(--cu-accent);
        border-radius: 8px;
        font: inherit;
        font-size: 13px;
        font-weight: 600;
        text-decoration: none;
        cursor: pointer;
        transition: background 150ms, border-color 150ms, color 150ms;
    }
    .cu-pv-foot-cta:hover {
        background: var(--cu-bg);
        border-color: var(--cu-line-strong);
        color: var(--cu-ink);
    }
    .cu-pv-foot-cta:hover svg { color: var(--cu-muted); }
    .cu-pv-foot-cta svg { color: white; transition: color 150ms; }
    .cu-pv-foot-cta:focus-visible {
        outline: 2px solid var(--cu-accent);
        outline-offset: 2px;
    }

    /* ----- LEFT: info column ----- */
    .cu-pv-info {
        padding: 22px 24px 26px;
        overflow-y: auto;
        scrollbar-width: thin;
        scrollbar-color: var(--cu-line-strong) transparent;
        min-width: 0;
    }
    .cu-pv-info::-webkit-scrollbar { width: 8px; }
    .cu-pv-info::-webkit-scrollbar-thumb { background: var(--cu-line-strong); border-radius: 99px; }

    /* Definition list — clean label/value rows. No background. */
    .cu-pv-dl {
        display: grid;
        grid-template-columns: 96px 1fr;
        column-gap: 16px;
        row-gap: 12px;
        margin: 0 0 22px;
        font-size: 13px;
    }
    .cu-pv-dl dt {
        font-size: 11.5px;
        font-weight: 500;
        color: var(--cu-subtle);
        padding-top: 1px;
    }
    .cu-pv-dl dd {
        margin: 0;
        font-size: 13px;
        color: var(--cu-ink);
        min-width: 0;
    }

    .cu-pv-empty {
        color: var(--cu-subtle);
        font-size: 13px;
    }

    /* Priority — colored dot + label, no background */
    .cu-pv-prio {
        display: inline-flex;
        align-items: center;
        gap: 7px;
        font-size: 13px;
        font-weight: 600;
    }
    .cu-pv-prio-dot {
        width: 8px;
        height: 8px;
        border-radius: 50%;
        display: inline-block;
    }

    /* Due date */
    .cu-pv-due {
        font-size: 13px;
        font-weight: 500;
        color: var(--cu-ink);
    }
    .cu-pv-due.is-overdue  { color: #b91c1c; font-weight: 600; }
    .cu-pv-due.is-due-soon { color: #b45309; font-weight: 600; }

    /* Person + team — used inside dd */
    .cu-pv-person {
        display: inline-flex;
        align-items: center;
        gap: 8px;
    }
    .cu-pv-team {
        display: inline-flex;
        align-items: center;
        flex-wrap: wrap;
        gap: 0;
    }
    .cu-pv-team .cu-avatar:first-child { margin-left: 0; }

    /* Progress — slim bar with text beside */
    .cu-pv-progress {
        display: flex;
        align-items: center;
        gap: 12px;
    }
    .cu-pv-progress-bar {
        flex: 1;
        height: 6px;
        background: var(--cu-line);
        border-radius: 99px;
        overflow: hidden;
    }
    .cu-pv-progress-fill {
        height: 100%;
        background: var(--cu-accent);
        border-radius: 99px;
        transition: width 200ms cubic-bezier(.22, 1, .36, 1);
    }
    .cu-pv-progress-text {
        display: inline-flex;
        align-items: baseline;
        gap: 6px;
        white-space: nowrap;
    }
    .cu-pv-progress-text strong {
        font-size: 13px;
        font-weight: 700;
        color: var(--cu-ink);
    }
    .cu-pv-progress-sub {
        font-size: 11.5px;
        color: var(--cu-subtle);
    }

    /* Blocks (description, steps) — separated by top border + spacing,
       NO background containers. */
    .cu-pv-block {
        margin-top: 22px;
        padding-top: 18px;
        border-top: 1px solid var(--cu-line);
    }
    .cu-pv-h3 {
        margin: 0 0 12px;
        font-size: 11.5px;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: .07em;
        color: var(--cu-subtle);
        display: flex;
        align-items: center;
        gap: 8px;
    }
    .cu-pv-h3-count {
        font-size: 11px;
        font-weight: 600;
        color: var(--cu-muted);
        letter-spacing: 0;
        text-transform: none;
    }
    .cu-pv-prose {
        margin: 0;
        font-size: 13.5px;
        line-height: 1.6;
        color: var(--cu-ink);
        word-wrap: break-word;
        max-width: 65ch;
    }

    /* Render konten RichEditor (HTML) dengan rapi */
    .cu-pv-prose p {
        margin: 0 0 .6em;
    }

    .cu-pv-prose p:last-child {
        margin-bottom: 0;
    }

    .cu-pv-prose ul,
    .cu-pv-prose ol {
        margin: .4em 0 .6em;
        padding-left: 1.4em;
    }

    .cu-pv-prose ul {
        list-style: disc;
    }

    .cu-pv-prose ol {
        list-style: decimal;
    }

    .cu-pv-prose li {
        margin: .2em 0;
    }

    .cu-pv-prose strong {
        font-weight: 600;
    }

    .cu-pv-prose em {
        font-style: italic;
    }

    .cu-pv-prose a {
        color: var(--cu-accent, #3b82f6);
        text-decoration: underline;
    }

    .cu-pv-prose h1,
    .cu-pv-prose h2,
    .cu-pv-prose h3,
    .cu-pv-prose h4 {
        font-weight: 600;
        margin: .7em 0 .3em;
        line-height: 1.3;
    }

    .cu-pv-prose blockquote {
        margin: .5em 0;
        padding-left: .75em;
        border-left: 3px solid var(--cu-border, #e5e7eb);
        color: var(--cu-ink-soft, #6b7280);
    }

    /* Steps — flat list, no row container, separated by hairlines */
    .cu-pv-steps {
        list-style: none;
        margin: 0;
        padding: 0;
    }
    .cu-pv-step {
        border-bottom: 1px solid var(--cu-line);
    }
    .cu-pv-step:last-child { border-bottom: 0; }
    .cu-pv-step-row {
        display: grid;
        grid-template-columns: 22px 1fr auto auto;
        align-items: center;
        gap: 10px;
        padding: 10px 0;
    }
    .cu-pv-step-marker {
        width: 18px;
        height: 18px;
        border-radius: 50%;
        border: 1.5px solid var(--m-color, var(--cu-line-strong));
        display: inline-flex;
        align-items: center;
        justify-content: center;
        color: var(--m-color, var(--cu-line-strong));
        transition: background 150ms, color 150ms;
    }
    .cu-pv-step-marker.is-done {
        background: var(--m-color, var(--cu-accent));
        border-color: var(--m-color, var(--cu-accent));
        color: white;
    }
    .cu-pv-step-text {
        font-size: 13px;
        color: var(--cu-ink);
        min-width: 0;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }
    .cu-pv-step.is-done .cu-pv-step-text {
        color: var(--cu-muted);
        text-decoration: line-through;
    }
    .cu-pv-step-state {
        font-size: 10.5px;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: .06em;
        white-space: nowrap;
    }

    /* Document count badge — non-interactive, just shows the document count
       next to a step name. Documents are always visible below. */
    .cu-pv-step-doc-count {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        padding: 2px 7px;
        background: var(--cu-bg-soft);
        color: var(--cu-muted);
        border-radius: 99px;
        font-size: 10.5px;
        font-weight: 600;
        white-space: nowrap;
    }
    .cu-pv-step-doc-count svg { color: var(--cu-subtle); }

    .cu-pv-step-docs {
        padding: 8px 0 18px 32px;
        display: flex;
        flex-direction: column;
        gap: 16px;
    }
    .cu-pv-doc { display: flex; flex-direction: column; gap: 8px; }
    .cu-pv-doc-head {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        flex-wrap: wrap;
    }
    .cu-pv-doc-name {
        font-size: 12.5px;
        font-weight: 600;
        color: var(--cu-ink);
        flex: 1;
        min-width: 0;
    }
    .cu-pv-doc-req {
        color: #b91c1c;
        margin-left: 2px;
        font-weight: 700;
    }
    .cu-pv-doc-empty {
        font-size: 11px;
        color: var(--cu-subtle);
        font-style: italic;
    }

    /* Empty state — required doc tanpa file (dashed placeholder, subtle) */
    .cu-pv-doc-empty-state {
        display: flex;
        align-items: center;
        gap: 8px;
        padding: 10px 12px;
        background: transparent;
        border: 1px dashed var(--cu-line-strong);
        border-radius: 7px;
        font-size: 11.5px;
        color: var(--cu-subtle);
        font-style: italic;
    }
    .cu-pv-doc-empty-state svg {
        opacity: .55;
        flex-shrink: 0;
    }

    /* Required document status tag — semantic colors, sits next to the doc name */
    .cu-pv-doc-status-tag {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        padding: 2px 8px;
        border-radius: 99px;
        font-size: 10.5px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: .04em;
        white-space: nowrap;
    }
    .cu-pv-doc-status-tag.is-approved { background: #d1fae5; color: #065f46; }
    .cu-pv-doc-status-tag.is-rejected { background: #fee2e2; color: #b91c1c; }
    .cu-pv-doc-status-tag.is-pending  { background: #fef3c7; color: #92400e; }
    .cu-pv-doc-status-tag.is-draft    { background: var(--cu-bg-soft); color: var(--cu-muted); }
    .dark .cu-pv-doc-status-tag.is-approved { background: rgba(16,185,129,.15); color: #34d399; }
    .dark .cu-pv-doc-status-tag.is-rejected { background: rgba(248,113,113,.15); color: #f87171; }
    .dark .cu-pv-doc-status-tag.is-pending  { background: rgba(251,191,36,.15); color: #fbbf24; }

    /* Inline note shown when a doc is approved without an uploaded file */
    .cu-pv-doc-no-file-note {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 6px 10px;
        background: var(--cu-bg-soft);
        border: 1px dashed var(--cu-line-strong);
        border-radius: 6px;
        font-size: 11.5px;
        color: var(--cu-muted);
        line-height: 1.3;
    }
    .cu-pv-doc-no-file-note svg { color: #047857; flex-shrink: 0; }
    .dark .cu-pv-doc-no-file-note svg { color: #34d399; }

    .cu-pv-doc-files {
        list-style: none;
        margin: 0;
        padding: 0;
        display: flex;
        flex-direction: column;
        gap: 6px;
    }
    /* Whole row is one big button — clear "download me" affordance.
       Extension badge left, file meta middle, status right, arrow far right. */
    .cu-pv-doc-file {
        display: grid;
        grid-template-columns: 40px minmax(0, 1fr) auto auto;
        align-items: center;
        gap: 12px;
        width: 100%;
        padding: 10px 12px 10px 10px;
        background: var(--cu-bg);
        border: 1px solid var(--cu-line-strong);
        border-radius: 8px;
        font: inherit;
        text-align: left;
        cursor: pointer;
        transition: background 150ms, border-color 150ms, transform 80ms;
    }
    .cu-pv-doc-file:hover {
        background: var(--cu-bg-hover);
        border-color: var(--cu-accent);
    }
    .cu-pv-doc-file:active { transform: translateY(1px); }
    .cu-pv-doc-file:focus-visible {
        outline: 2px solid var(--cu-accent);
        outline-offset: 2px;
    }
    .cu-pv-doc-file:disabled { opacity: .6; cursor: progress; }

    /* Extension badge — file-type "stamp" tile on the left */
    .cu-pv-doc-file-ext {
        width: 40px;
        height: 40px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        background: var(--cu-bg-soft);
        color: var(--cu-muted);
        border-radius: 7px;
        font-size: 9.5px;
        font-weight: 700;
        letter-spacing: .04em;
        text-transform: uppercase;
        flex-shrink: 0;
        border: 1px solid var(--cu-line);
        font-family: 'SF Mono', Menlo, monospace;
    }
    .cu-pv-doc-file:hover .cu-pv-doc-file-ext {
        background: var(--cu-accent-soft);
        color: var(--cu-accent-ink);
        border-color: var(--cu-accent);
    }

    .cu-pv-doc-file-main {
        display: flex;
        flex-direction: column;
        gap: 2px;
        min-width: 0;
    }
    .cu-pv-doc-file-name {
        font-size: 12.5px;
        font-weight: 600;
        color: var(--cu-ink);
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }
    .cu-pv-doc-file-sub {
        display: flex;
        align-items: center;
        gap: 6px;
        font-size: 11px;
        color: var(--cu-subtle);
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }
    .cu-pv-doc-sep { opacity: .6; }

    .cu-pv-doc-file-status {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        font-size: 10px;
        font-weight: 700;
        padding: 3px 8px;
        border-radius: 99px;
        text-transform: uppercase;
        letter-spacing: .04em;
        background: var(--cu-bg-soft);
        color: var(--cu-muted);
        white-space: nowrap;
        box-shadow: inset 0 0 0 1px rgba(15, 23, 42, .05);
    }
    .cu-pv-doc-file-status.is-approved { background: #d1fae5; color: #065f46; box-shadow: inset 0 0 0 1px rgba(6, 95, 70, .12); }
    .cu-pv-doc-file-status.is-rejected { background: #fee2e2; color: #b91c1c; box-shadow: inset 0 0 0 1px rgba(185, 28, 28, .12); }
    .cu-pv-doc-file-status.is-pending  { background: #fef3c7; color: #92400e; box-shadow: inset 0 0 0 1px rgba(146, 64, 14, .12); }
    .cu-pv-doc-file-status.is-uploaded { background: #dbeafe; color: #1e40af; box-shadow: inset 0 0 0 1px rgba(30, 64, 175, .12); }

    .dark .cu-pv-doc-file-status.is-approved { background: rgba(16,185,129,.15);  color: #34d399; }
    .dark .cu-pv-doc-file-status.is-rejected { background: rgba(248,113,113,.15); color: #f87171; }
    .dark .cu-pv-doc-file-status.is-pending  { background: rgba(251,191,36,.15);  color: #fbbf24; }
    .dark .cu-pv-doc-file-status.is-uploaded { background: rgba(96,165,250,.15);  color: #60a5fa; }

    /* Download arrow on the right — becomes accent-filled on row hover */
    .cu-pv-doc-dl {
        width: 28px;
        height: 28px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        background: var(--cu-bg-soft);
        border-radius: 6px;
        color: var(--cu-muted);
        transition: background 150ms, color 150ms;
    }
    .cu-pv-doc-file:hover .cu-pv-doc-dl {
        background: var(--cu-accent);
        color: white;
    }
    .cu-pv-doc-dl-spin svg {
        animation: cu-pv-spin 700ms linear infinite;
    }
    @keyframes cu-pv-spin {
        to { transform: rotate(360deg); }
    }

    /* ----- RIGHT: notes column ----- */
    .cu-pv-notes-col {
        display: flex;
        flex-direction: column;
        border-left: 1px solid var(--cu-line);
        background: var(--cu-bg);
        min-width: 0;
        max-height: 100%;
    }
    .cu-pv-notes-head {
        padding: 18px 18px 0;
        flex-shrink: 0;
    }
    .cu-pv-notes-head .cu-pv-h3 {
        margin-bottom: 0;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        color: var(--cu-muted);
    }
    .cu-pv-notes-head .cu-pv-h3 svg { color: var(--cu-subtle); }
    .cu-pv-notes-head .cu-pv-h3-count {
        margin-left: 2px;
        padding: 1px 8px;
        background: var(--cu-bg-hover);
        border-radius: 99px;
        color: var(--cu-muted);
    }

    /* ----- Compose form ----- */
    .cu-pv-compose {
        padding: 14px 18px 16px;
        border-bottom: 1px solid var(--cu-line);
        flex-shrink: 0;
        display: flex;
        flex-direction: column;
        gap: 6px;
    }
    .cu-pv-form-error {
        font-size: 11.5px;
        color: #b91c1c;
        font-weight: 500;
    }

    /* The composer is one unified card containing textarea + toolbar.
       Border lights up via :focus-within so the textarea border doesn't
       have to compete with the container's. */
    .cu-pv-composer {
        background: var(--cu-bg);
        border: 1px solid var(--cu-line-strong);
        border-radius: 10px;
        transition: border-color 150ms, box-shadow 150ms;
        display: flex;
        flex-direction: column;
        overflow: hidden;
    }
    .cu-pv-composer:hover { border-color: var(--cu-muted); }
    .cu-pv-composer.is-focused,
    .cu-pv-composer:focus-within {
        border-color: var(--cu-accent);
        box-shadow: 0 0 0 3px rgba(99, 102, 241, .12);
    }

    .cu-pv-textarea {
        width: 100%;
        min-height: 80px;
        padding: 11px 12px 4px;
        font: inherit;
        font-size: 13.5px;
        line-height: 1.55;
        color: var(--cu-ink);
        background: transparent;
        border: 0;
        resize: none;                   /* manual resize would clash with the bar */
        outline: 0;
        box-sizing: border-box;
    }
    .cu-pv-textarea::placeholder { color: var(--cu-subtle); }

    .cu-pv-composer-bar {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 8px;
        padding: 8px 8px 8px 10px;
    }

    /* Type picker — small segmented chips with colored dot.
       Active type colors the chip's text only (no loud bg). */
    .cu-pv-type-pick {
        display: inline-flex;
        gap: 2px;
    }
    .cu-pv-type-opt {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        padding: 4px 8px;
        font-size: 11.5px;
        font-weight: 500;
        color: var(--cu-muted);
        background: transparent;
        border-radius: 6px;
        cursor: pointer;
        transition: background 150ms, color 150ms;
        --t-color: var(--cu-muted);
    }
    .cu-pv-type-opt:hover { background: var(--cu-bg-hover); color: var(--cu-ink); }
    .cu-pv-type-opt.is-selected {
        color: var(--t-color);
        background: var(--cu-bg-hover);
    }
    .cu-pv-type-dot {
        width: 6px;
        height: 6px;
        border-radius: 50%;
        background: var(--t-color);
        display: inline-block;
        flex-shrink: 0;
    }
    .cu-pv-type-radio { display: none; }

    /* Right end of composer bar: char count, hotkey hint, send button */
    .cu-pv-composer-end {
        display: inline-flex;
        align-items: center;
        gap: 8px;
    }
    .cu-pv-char-count {
        font-size: 10.5px;
        font-variant-numeric: tabular-nums;
        font-weight: 500;
        color: var(--cu-subtle);
        min-width: 30px;
        text-align: right;
    }
    .cu-pv-char-count.is-warn { color: #b91c1c; font-weight: 700; }

    .cu-pv-hotkey {
        font-family: 'SF Mono', Menlo, ui-monospace, monospace;
        font-size: 10px;
        font-weight: 600;
        padding: 2px 6px;
        background: var(--cu-bg-soft);
        color: var(--cu-subtle);
        border: 1px solid var(--cu-line);
        border-radius: 4px;
        line-height: 1;
        white-space: nowrap;
    }
    /* Hide hotkey hint on small viewports — it's a power-user nicety */
    @media (max-width: 1024px) { .cu-pv-hotkey { display: none; } }

    /* Send button — circular accent icon button, distinctive. */
    .cu-pv-send {
        width: 32px;
        height: 32px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        background: var(--cu-accent);
        color: white;
        border: 0;
        border-radius: 50%;
        cursor: pointer;
        transition: background 150ms, transform 80ms, opacity 150ms;
        flex-shrink: 0;
    }
    .cu-pv-send svg { transition: transform 150ms cubic-bezier(.22, 1, .36, 1); }
    .cu-pv-send:hover:not(:disabled) {
        background: var(--cu-accent-ink);
    }
    .cu-pv-send:hover:not(:disabled) svg {
        transform: translateX(1px) translateY(-1px);
    }
    .cu-pv-send:active:not(:disabled) { transform: scale(.95); }
    .cu-pv-send:disabled {
        background: var(--cu-line-strong);
        color: var(--cu-subtle);
        cursor: not-allowed;
    }
    .cu-pv-send:focus-visible {
        outline: 2px solid var(--cu-accent);
        outline-offset: 2px;
    }
    .cu-pv-send-spin svg { animation: cu-pv-spin 700ms linear infinite; }

    /* ----- Notes feed ----- */
    .cu-pv-notes-list {
        flex: 1;
        overflow-y: auto;
        scrollbar-width: thin;
        scrollbar-color: var(--cu-line-strong) transparent;
        min-height: 0;
    }
    .cu-pv-notes-list::-webkit-scrollbar { width: 8px; }
    .cu-pv-notes-list::-webkit-scrollbar-thumb { background: var(--cu-line-strong); border-radius: 99px; }

    .cu-pv-note {
        display: grid;
        grid-template-columns: 24px 1fr;
        gap: 10px;
        padding: 12px 18px;
        border-bottom: 1px solid var(--cu-line);
        transition: background 150ms;
    }
    .cu-pv-note-avatar {
        width: 24px !important;
        height: 24px !important;
        margin: 0 !important;
        font-size: 10px !important;
    }
    .cu-pv-note:hover { background: var(--cu-bg-hover); }
    .cu-pv-note:last-child { border-bottom: 0; }

    .cu-pv-note-avatar-unknown {
        background: var(--cu-bg-soft) !important;
        color: var(--cu-subtle) !important;
    }
    .cu-pv-note-content { min-width: 0; }
    .cu-pv-note-meta {
        display: flex;
        align-items: center;
        gap: 8px;
        font-size: 12px;
        color: var(--cu-muted);
        margin-bottom: 4px;
        flex-wrap: wrap;
    }
    .cu-pv-note-author {
        font-weight: 600;
        color: var(--cu-ink);
        font-size: 12px;
    }
    .cu-pv-note-time { color: var(--cu-subtle); }
    .cu-pv-note-tag {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        padding: 1px 7px;
        border-radius: 99px;
        font-size: 10.5px;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: .04em;
    }
    .cu-pv-note-tag.is-important {
        color: #b45309;
        background: #fef3c7;
    }
    .cu-pv-note-tag.is-blocker {
        color: #b91c1c;
        background: #fee2e2;
    }
    .dark .cu-pv-note-tag.is-important { background: rgba(251, 191, 36, .15); color: #fbbf24; }
    .dark .cu-pv-note-tag.is-blocker  { background: rgba(248, 113, 113, .15); color: #f87171; }

    .cu-pv-note-body {
        margin: 0;
        font-size: 12.5px;
        line-height: 1.55;
        color: var(--cu-ink);
        white-space: pre-wrap;
        word-wrap: break-word;
    }

    /* Empty state — quiet, educational */
    .cu-pv-notes-empty {
        padding: 48px 24px;
        text-align: center;
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 4px;
    }
    .cu-pv-notes-empty-ico {
        color: var(--cu-subtle);
        margin-bottom: 8px;
        opacity: .7;
    }
    .cu-pv-notes-empty-title {
        margin: 0;
        font-size: 13.5px;
        font-weight: 600;
        color: var(--cu-ink);
    }
    .cu-pv-notes-empty-sub {
        margin: 0;
        font-size: 12.5px;
        color: var(--cu-subtle);
        line-height: 1.5;
        max-width: 30ch;
    }

    /* ----- Inline edit dropdown (departemen, etc.) -----
       Looks like a real picker: persistent border + chevron, soft bg.
       Strong hover lift so users see "I can change this" at a glance. */
    .cu-pv-edit { position: relative; display: inline-block; max-width: 100%; }
    .cu-pv-edit-trigger {
        display: inline-flex;
        align-items: center;
        gap: 10px;
        max-width: 100%;
        padding: 6px 9px 6px 10px;
        background: var(--cu-bg-soft);
        border: 1px solid var(--cu-line-strong);
        border-radius: 7px;
        font: inherit;
        font-size: 13px;
        font-weight: 500;
        color: var(--cu-ink);
        cursor: pointer;
        text-align: left;
        transition: background 150ms, border-color 150ms, box-shadow 150ms, transform 80ms;
    }
    .cu-pv-edit-trigger:hover {
        background: var(--cu-bg);
        border-color: var(--cu-accent);
        box-shadow: 0 1px 3px rgba(15, 23, 42, .06);
    }
    .cu-pv-edit-trigger:active { transform: translateY(1px); }
    .cu-pv-edit-trigger:focus-visible {
        outline: 2px solid var(--cu-accent);
        outline-offset: 2px;
    }
    .cu-pv-edit-trigger.is-open {
        background: var(--cu-accent-soft);
        border-color: var(--cu-accent);
        color: var(--cu-accent-ink);
        box-shadow: 0 0 0 3px rgba(99, 102, 241, .12);
    }
    .cu-pv-edit-trigger.is-empty {
        border-style: dashed;
        background: transparent;
    }
    .cu-pv-edit-trigger.is-empty:hover { background: var(--cu-bg-soft); }

    .cu-pv-edit-value {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        min-width: 0;
        flex: 1;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }
    .cu-pv-edit-placeholder { color: var(--cu-subtle); font-weight: 500; font-style: italic; }
    .cu-pv-edit-ico { color: var(--cu-muted); flex-shrink: 0; }
    .cu-pv-edit-trigger.is-open .cu-pv-edit-ico { color: var(--cu-accent-ink); }
    .cu-pv-edit-trigger.is-empty .cu-pv-edit-ico { color: var(--cu-subtle); }
    .cu-pv-edit-caret {
        flex-shrink: 0;
        color: var(--cu-muted);
        transition: transform 150ms cubic-bezier(.22, 1, .36, 1), color 150ms;
    }
    .cu-pv-edit-trigger:hover .cu-pv-edit-caret { color: var(--cu-accent); }
    .cu-pv-edit-trigger.is-open .cu-pv-edit-caret { transform: rotate(180deg); color: var(--cu-accent-ink); }

    /* The dropdown panel */
    .cu-pv-edit-panel {
        position: absolute;
        top: calc(100% + 6px);
        left: -4px;
        z-index: 60;
        min-width: 240px;
        max-width: 320px;
        background: var(--cu-bg);
        border: 1px solid var(--cu-line-strong);
        border-radius: 10px;
        box-shadow:
            0 16px 32px -12px rgba(15, 23, 42, .18),
            0 4px 10px -4px rgba(15, 23, 42, .08);
        overflow: hidden;
    }
    .cu-pv-edit-enter { transition: opacity 140ms cubic-bezier(.22, 1, .36, 1), transform 140ms cubic-bezier(.22, 1, .36, 1); }
    .cu-pv-edit-enter-start { opacity: 0; transform: translateY(-4px) scale(.98); }
    .cu-pv-edit-enter-end   { opacity: 1; transform: translateY(0) scale(1); }

    .cu-pv-edit-panel-head {
        padding: 8px 12px 6px;
        font-size: 10.5px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: .07em;
        color: var(--cu-subtle);
        border-bottom: 1px solid var(--cu-line);
    }
    .cu-pv-edit-panel-list {
        max-height: 260px;
        overflow-y: auto;
        padding: 4px;
        scrollbar-width: thin;
        scrollbar-color: var(--cu-line-strong) transparent;
    }
    .cu-pv-edit-panel-list::-webkit-scrollbar { width: 6px; }
    .cu-pv-edit-panel-list::-webkit-scrollbar-thumb { background: var(--cu-line-strong); border-radius: 99px; }

    .cu-pv-edit-opt {
        display: flex;
        align-items: center;
        gap: 9px;
        width: 100%;
        padding: 7px 9px;
        background: transparent;
        border: 0;
        border-radius: 6px;
        font: inherit;
        font-size: 12.5px;
        color: var(--cu-ink);
        text-align: left;
        cursor: pointer;
        transition: background 120ms;
    }
    .cu-pv-edit-opt:hover { background: var(--cu-bg-hover); }
    .cu-pv-edit-opt.is-current {
        background: var(--cu-accent-soft);
        color: var(--cu-accent-ink);
    }
    .cu-pv-edit-opt-ico { color: var(--cu-subtle); flex-shrink: 0; }
    .cu-pv-edit-opt.is-current .cu-pv-edit-opt-ico { color: var(--cu-accent-ink); }
    .cu-pv-edit-opt-label {
        flex: 1;
        min-width: 0;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
        font-weight: 500;
    }
    .cu-pv-edit-opt-check { color: var(--cu-accent); flex-shrink: 0; }

    .cu-pv-edit-empty {
        padding: 14px 12px;
        font-size: 12px;
        color: var(--cu-subtle);
        text-align: center;
        font-style: italic;
    }

    .cu-pv-edit-panel-foot {
        border-top: 1px solid var(--cu-line);
        padding: 4px;
    }
    .cu-pv-edit-clear {
        display: flex;
        align-items: center;
        gap: 6px;
        width: 100%;
        padding: 6px 9px;
        background: transparent;
        border: 0;
        border-radius: 6px;
        font: inherit;
        font-size: 11.5px;
        font-weight: 500;
        color: var(--cu-muted);
        cursor: pointer;
        text-align: left;
        transition: background 120ms, color 120ms;
    }
    .cu-pv-edit-clear:hover {
        background: #fef2f2;
        color: #b91c1c;
    }
    .dark .cu-pv-edit-clear:hover { background: rgba(220,38,38,.12); color: #f87171; }

    /* Dark mode */
    .dark .cu-pv-modal { border-color: var(--cu-line-strong); }
    .dark .cu-pv-backdrop { background: rgba(0, 0, 0, .6); }

    /* ----- Responsive ----- */
    @media (max-width: 1024px) {
        .cu-pv-modal { max-width: 820px; }
        .cu-pv-body { grid-template-columns: 1fr; }
        .cu-pv-notes-col {
            border-left: 0;
            border-top: 1px solid var(--cu-line);
            max-height: 480px;          /* cap notes section height when stacked */
        }
        .cu-pv-notes-head { padding-top: 18px; }
        .cu-pv-dl { grid-template-columns: 84px 1fr; }
    }
    @media (max-width: 640px) {
        .cu-pv-overlay { padding: 0; align-items: stretch; }
        .cu-pv-modal {
            max-width: 100%;
            max-height: 100vh;
            border-radius: 0;
            border: 0;
        }
        .cu-pv-head { padding: 14px 16px; }
        .cu-pv-title { font-size: 15.5px; }
        .cu-pv-action span { display: none; }
        .cu-pv-action { padding: 6px 8px; }
        .cu-pv-info { padding: 18px 16px 20px; }
        .cu-pv-dl { grid-template-columns: 80px 1fr; row-gap: 10px; }
        .cu-pv-notes-head,
        .cu-pv-compose,
        .cu-pv-note { padding-left: 16px; padding-right: 16px; }
    }

    /* =====================================================================
       Single horizontal scroll wrapper for the entire table area.
       Wraps the table-head + all group cards so column labels, group
       headers, rows, and dividers all scroll together — perfectly aligned.
       Natural height (no max-height) so the page scrolls vertically as
       normal and you see all projects. */
    .cu-scroll-wrap {
        overflow-x: auto;
        scrollbar-width: thin;
        scrollbar-color: var(--cu-line-strong) transparent;
        margin-bottom: 8px;
    }
    .cu-scroll-wrap::-webkit-scrollbar { height: 9px; }
    .cu-scroll-wrap::-webkit-scrollbar-track { background: transparent; }
    .cu-scroll-wrap::-webkit-scrollbar-thumb {
        background: var(--cu-line-strong);
        border-radius: 99px;
    }
    .cu-scroll-wrap::-webkit-scrollbar-thumb:hover {
        background: var(--cu-muted);
    }
    /* Every direct child of the wrap should grow to fit its widest row so
       the table-head + each card all line up at the same scroll width. */
    .cu-scroll-wrap > .cu-table-head,
    .cu-scroll-wrap > .cu-group-card,
    .cu-scroll-wrap > .cu-cat-header {
        min-width: max-content;
    }

    /* =====================================================================
       Kanban / Board view
       Horizontal scroll container with fixed-width status columns.
       Drag-and-drop between columns updates the project's status via
       Alpine HTML5 drag events → $wire.updateStatus().
       Restrained palette: status color shows only on the column marker
       and a subtle 2px ring at the top of each column header.
       ===================================================================== */
    .cu-kanban {
        display: flex;
        align-items: stretch;
        gap: 14px;
        padding: 6px 2px 18px;
        overflow-x: auto;
        scrollbar-width: thin;
        scrollbar-color: var(--cu-line-strong) transparent;
        min-height: 480px;
    }
    .cu-kanban::-webkit-scrollbar { height: 10px; }
    .cu-kanban::-webkit-scrollbar-track { background: transparent; }
    .cu-kanban::-webkit-scrollbar-thumb { background: var(--cu-line-strong); border-radius: 99px; }

    .cu-kb-col {
        flex: 0 0 288px;
        display: flex;
        flex-direction: column;
        background: var(--cu-bg-soft);
        border: 1px solid var(--cu-line);
        border-radius: 10px;
        max-height: calc(100vh - 220px);
        min-height: 260px;
        transition: border-color 140ms, background 140ms;
    }
    .cu-kb-col.is-drop-target {
        background: var(--cu-accent-soft);
        border-color: var(--cu-accent);
        border-style: dashed;
    }

    .cu-kb-col-head {
        display: flex;
        align-items: center;
        gap: 8px;
        padding: 12px 14px 10px;
        border-bottom: 1px solid var(--cu-line);
        box-shadow: inset 0 2px 0 var(--col-color, var(--cu-line-strong));
        border-top-left-radius: 9px;
        border-top-right-radius: 9px;
    }
    .cu-kb-col-marker {
        display: inline-flex;
        align-items: center;
        flex-shrink: 0;
    }
    /* Fallback dot when not grouping by status (priority/pic/client) */
    .cu-kb-col-dot {
        width: 9px;
        height: 9px;
        border-radius: 50%;
        display: inline-block;
    }

    /* Status indicator on a card when grouped by something other than status */
    .cu-kb-card-stat {
        display: inline-flex;
        align-items: center;
        flex-shrink: 0;
        margin-top: 1px;
    }
    .cu-kb-col-name {
        flex: 1;
        font-size: 12px;
        font-weight: 700;
        color: var(--cu-ink);
        text-transform: uppercase;
        letter-spacing: .04em;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }
    .cu-kb-col-count {
        font-size: 11px;
        font-weight: 600;
        color: var(--cu-muted);
        padding: 1px 8px;
        background: var(--cu-bg);
        border-radius: 99px;
        flex-shrink: 0;
    }

    .cu-kb-col-body {
        flex: 1;
        overflow-y: auto;
        padding: 8px;
        display: flex;
        flex-direction: column;
        gap: 6px;
        scrollbar-width: thin;
        scrollbar-color: var(--cu-line-strong) transparent;
    }
    .cu-kb-col-body::-webkit-scrollbar { width: 6px; }
    .cu-kb-col-body::-webkit-scrollbar-thumb { background: var(--cu-line-strong); border-radius: 99px; }

    /* Card */
    .cu-kb-card {
        display: flex;
        flex-direction: column;
        gap: 6px;
        padding: 10px 12px;
        background: var(--cu-bg);
        border: 1px solid var(--cu-line);
        border-radius: 8px;
        cursor: grab;
        transition: border-color 140ms, box-shadow 140ms, transform 80ms, opacity 140ms;
        text-align: left;
    }
    .cu-kb-card:hover {
        border-color: var(--cu-line-strong);
        box-shadow:
            0 2px 6px rgba(15, 23, 42, .04),
            0 1px 2px rgba(15, 23, 42, .03);
    }
    .cu-kb-card:active { cursor: grabbing; }
    .cu-kb-card:focus-visible {
        outline: 2px solid var(--cu-accent);
        outline-offset: 2px;
    }
    .cu-kb-card.is-dragging {
        opacity: .4;
        transform: scale(.98);
        cursor: grabbing;
    }
    .cu-kb-card-top {
        display: flex;
        align-items: flex-start;
        gap: 8px;
    }
    .cu-kb-card-title {
        margin: 0;
        flex: 1;
        font-size: 13px;
        font-weight: 600;
        line-height: 1.35;
        color: var(--cu-ink);
        letter-spacing: -.005em;
        min-width: 0;
        overflow: hidden;
        display: -webkit-box;
        -webkit-line-clamp: 2;
        -webkit-box-orient: vertical;
        word-break: break-word;
    }
    .cu-kb-card-prio {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 18px;
        height: 18px;
        color: var(--p-color, var(--cu-muted));
        flex-shrink: 0;
        margin-top: 1px;
    }
    .cu-kb-card-meta {
        display: flex;
        align-items: center;
        gap: 5px;
        font-size: 11px;
        color: var(--cu-muted);
        min-width: 0;
        overflow: hidden;
    }
    .cu-kb-card-client {
        font-weight: 500;
        color: var(--cu-muted);
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
        max-width: 50%;
    }
    .cu-kb-card-sep { color: var(--cu-subtle); flex-shrink: 0; }
    .cu-kb-card-dept {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        color: var(--cu-subtle);
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
        min-width: 0;
    }
    .cu-kb-card-dept svg { color: var(--cu-subtle); flex-shrink: 0; }
    .cu-kb-card-foot {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 8px;
        margin-top: 2px;
    }
    .cu-kb-card-people {
        display: flex;
        align-items: center;
    }
    .cu-kb-card-avatar {
        width: 22px !important;
        height: 22px !important;
        margin-left: -6px;
        font-size: 9px !important;
    }
    .cu-kb-card-avatar:first-child { margin-left: 0; }
    .cu-kb-card-due {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        font-size: 11px;
        font-weight: 500;
        color: var(--cu-muted);
        white-space: nowrap;
    }
    .cu-kb-card-due svg { color: var(--cu-subtle); }
    .cu-kb-card-due.is-overdue  { color: #b91c1c; font-weight: 600; }
    .cu-kb-card-due.is-overdue svg  { color: #b91c1c; }
    .cu-kb-card-due.is-due-soon { color: #b45309; font-weight: 600; }
    .cu-kb-card-due.is-due-soon svg { color: #b45309; }

    /* Empty column */
    .cu-kb-empty {
        flex: 1;
        display: flex;
        align-items: center;
        justify-content: center;
        min-height: 80px;
        padding: 20px;
        font-size: 12px;
        color: var(--cu-subtle);
        font-style: italic;
        border: 1px dashed transparent;
        border-radius: 6px;
        text-align: center;
        transition: border-color 140ms, background 140ms, color 140ms;
    }
    .cu-kb-empty.is-target {
        border-color: var(--cu-accent);
        background: var(--cu-accent-soft);
        color: var(--cu-accent-ink);
        font-style: normal;
    }

    /* Dark mode */
    .dark .cu-kb-col { background: rgba(255, 255, 255, .015); }
    .dark .cu-kb-card { background: var(--cu-bg); }

    /* Responsive */
    @media (max-width: 640px) {
        .cu-kb-col { flex-basis: 260px; }
    }

    /* Compress toolbar on small screens */
    @media (max-width: 768px) {
        .cu-toolbar { flex-wrap: wrap; gap: 8px; }
        .cu-search { flex: 1 1 100%; order: -1; }
        .cu-search-kbd { display: none; }
        .cu-filter-label { display: none; }
        .cu-filter-btn { padding: 0 10px; }
        .cu-view-toggle { display: none; }
    }

    /* Status manager modal — responsive sizing */
    @media (max-width: 720px) {
        .cu-sm-modal { width: 95vw; max-height: 92vh; }
        .cu-sm-modal-body { padding: 12px; }
        .cu-sm-thead { padding: 8px 12px; }
        .cu-sm-tr, .cu-sm-cat-sep, .cu-sm-add-section { padding-left: 12px; padding-right: 12px; }
        .cu-sm-add-section { margin: 16px 12px 0; padding: 14px; }
        .cu-sm-add-grid { grid-template-columns: 1fr; }
        .cu-sm-inline-shapes { flex-wrap: wrap; }
    }

    /* ============================================
       SKELETON LOADING (project view modal)
       ============================================ */
    .cu-skel {
        background: linear-gradient(90deg, var(--cu-line) 0%, var(--cu-bg-hover) 50%, var(--cu-line) 100%);
        background-size: 200% 100%;
        border-radius: 6px;
        animation: cu-skel-shimmer 1.4s ease-in-out infinite;
    }
    @keyframes cu-skel-shimmer {
        0%   { background-position: 200% 0; }
        100% { background-position: -200% 0; }
    }
    .cu-skel-badge       { width: 84px;  height: 22px; border-radius: 99px; }
    .cu-skel-title       { width: 60%;   height: 20px; margin-top: 6px; }
    .cu-skel-label       { width: 70px;  height: 12px; }
    .cu-skel-value       { width: 100%;  height: 14px; }
    .cu-skel-section-title { width: 120px; height: 14px; margin: 18px 0 12px; }
    .cu-skel-bar         { width: 100%;  height: 8px;  border-radius: 99px; margin-top: 4px; }
    .cu-skel-step-icon   { width: 16px;  height: 16px; border-radius: 4px; flex-shrink: 0; }
    .cu-skel-step-text   { width: 75%;   height: 14px; }
    .cu-skel-note-meta   { width: 40%;   height: 12px; }
    .cu-skel-note-line   { width: 100%;  height: 12px; margin-top: 6px; }
    .cu-skel-note-line.short { width: 65%; }
    .cu-skel-dl {
        display: grid;
        grid-template-columns: 100px 1fr;
        gap: 16px 18px;
        align-items: center;
    }
    .cu-skel-dl-row {
        display: contents;
    }
    .cu-skel-progress { margin-top: 24px; }
    .cu-skel-steps    { margin-top: 8px; }
    .cu-skel-step {
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 10px 0;
        border-top: 1px solid var(--cu-line);
    }
    .cu-skel-step:first-of-type { border-top: 0; }
    .cu-skel-note {
        padding: 14px 0;
        border-top: 1px solid var(--cu-line);
    }
    .cu-skel-note:first-of-type { border-top: 0; }

    /* ============================================
       DOCUMENT PREVIEW MODAL (nested di dalam project view modal)
       ============================================ */
    .cu-pv-preview-modal {
        max-width: 1100px;
        max-height: 90vh;
        display: flex;
        flex-direction: column;
        z-index: 60;  /* di atas project view modal */
    }
    .cu-pv-preview-head {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 16px;
        padding: 16px 20px;
        border-bottom: 1px solid var(--cu-line);
        background: var(--cu-bg);
    }
    .cu-pv-preview-head-left {
        display: flex;
        align-items: flex-start;
        gap: 12px;
        min-width: 0;
        flex: 1;
    }
    .cu-pv-preview-icon {
        flex-shrink: 0;
        width: 40px;
        height: 40px;
        border-radius: 10px;
        background: var(--cu-accent-soft);
        color: var(--cu-accent-ink);
        display: inline-flex;
        align-items: center;
        justify-content: center;
    }
    .cu-pv-preview-meta {
        min-width: 0;
        flex: 1;
    }
    .cu-pv-preview-title {
        font-size: 15px;
        font-weight: 600;
        color: var(--cu-ink);
        margin: 0 0 4px;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
        line-height: 1.3;
    }
    .cu-pv-preview-sub {
        display: flex;
        align-items: center;
        gap: 6px;
        flex-wrap: wrap;
        font-size: 12px;
        color: var(--cu-muted);
    }
    .cu-pv-preview-sub-sep { opacity: .5; }
    .cu-pv-preview-status {
        display: inline-flex;
        align-items: center;
        padding: 2px 8px;
        border-radius: 99px;
        font-size: 10.5px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: .04em;
        color: var(--s-color);
        background: var(--s-bg);
    }
    .cu-pv-preview-head-actions {
        display: flex;
        align-items: center;
        gap: 8px;
        flex-shrink: 0;
    }
    .cu-pv-preview-btn {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 8px 14px;
        border: 1px solid var(--cu-line-strong);
        background: var(--cu-bg);
        color: var(--cu-ink);
        font-size: 12.5px;
        font-weight: 600;
        border-radius: 7px;
        cursor: pointer;
        transition: background .12s, border-color .12s;
    }
    .cu-pv-preview-btn:hover {
        background: var(--cu-bg-hover);
        border-color: var(--cu-accent);
    }
    .cu-pv-preview-btn-primary {
        background: var(--cu-accent);
        border-color: var(--cu-accent);
        color: #fff;
    }
    .cu-pv-preview-btn-primary:hover {
        background: var(--cu-accent-ink);
        border-color: var(--cu-accent-ink);
        color: #fff;
    }
    .cu-pv-preview-btn-lg { padding: 10px 18px; font-size: 13px; }

    .cu-pv-preview-body {
        flex: 1;
        min-height: 0;
        display: flex;
        background: var(--cu-bg-soft);
        overflow: hidden;
    }
    .cu-pv-preview-iframe {
        width: 100%;
        height: 75vh;
        min-height: 500px;
        border: 0;
        background: var(--cu-bg);
        display: block;
    }
    .cu-pv-preview-image-wrap {
        width: 100%;
        height: 75vh;
        min-height: 500px;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 20px;
        background: var(--cu-bg-soft);
        overflow: auto;
    }
    .cu-pv-preview-image {
        max-width: 100%;
        max-height: 100%;
        object-fit: contain;
        border-radius: 6px;
        box-shadow: 0 4px 20px rgba(0, 0, 0, .08);
    }
    .cu-pv-preview-fallback {
        width: 100%;
        min-height: 400px;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        gap: 12px;
        padding: 40px 24px;
        text-align: center;
    }
    .cu-pv-preview-fallback-icon {
        width: 64px;
        height: 64px;
        border-radius: 50%;
        background: var(--cu-bg);
        color: var(--cu-muted);
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border: 1px solid var(--cu-line);
        margin-bottom: 4px;
    }
    .cu-pv-preview-fallback-title {
        font-size: 15px;
        font-weight: 600;
        color: var(--cu-ink);
        margin: 0;
    }
    .cu-pv-preview-fallback-text {
        font-size: 13px;
        color: var(--cu-muted);
        margin: 0 0 8px;
        max-width: 380px;
        line-height: 1.5;
    }

    @media (max-width: 640px) {
        .cu-pv-preview-modal { max-width: 100%; max-height: 100vh; border-radius: 0; }
        .cu-pv-preview-head { padding: 12px 14px; flex-wrap: wrap; }
        .cu-pv-preview-btn span { display: none; }
        .cu-pv-preview-iframe,
        .cu-pv-preview-image-wrap { height: calc(100vh - 100px); }
    }

        </style>
    @endonce

    {{-- ============== DOCUMENT PREVIEW MODAL ============== --}}
    @if ($previewingSubmittedDocumentId && $this->previewingSubmittedDocument)
        @php
            $previewDoc = $this->previewingSubmittedDocument;
            $previewType = $this->previewFileType;
            $previewUrl = $this->previewUrl;
            $previewName = basename($previewDoc->file_path);
            $previewStatusMeta = match ($previewDoc->status) {
                'approved'       => ['label' => 'Disetujui',       'color' => '#16a34a', 'bg' => '#dcfce7'],
                'rejected'       => ['label' => 'Ditolak',         'color' => '#dc2626', 'bg' => '#fee2e2'],
                'pending_review' => ['label' => 'Menunggu Review', 'color' => '#d97706', 'bg' => '#fef3c7'],
                'uploaded'       => ['label' => 'Diunggah',        'color' => '#2563eb', 'bg' => '#dbeafe'],
                default          => ['label' => ucfirst($previewDoc->status ?? 'unknown'), 'color' => '#64748b', 'bg' => '#f1f5f9'],
            };
        @endphp
        <div class="cu-pv-overlay" wire:keydown.escape.window="closeSubmittedDocumentPreview">
            <div class="cu-pv-backdrop" wire:click="closeSubmittedDocumentPreview"></div>
            <div class="cu-pv-modal cu-pv-preview-modal" role="dialog" aria-modal="true" aria-label="Pratinjau dokumen">
                {{-- Header --}}
                <header class="cu-pv-preview-head">
                    <div class="cu-pv-preview-head-left">
                        <div class="cu-pv-preview-icon">
                            @if ($previewType === 'pdf')
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="9" y1="13" x2="15" y2="13"/><line x1="9" y1="17" x2="13" y2="17"/></svg>
                            @elseif (in_array($previewType, ['jpg', 'jpeg', 'png', 'gif', 'webp']))
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>
                            @else
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                            @endif
                        </div>
                        <div class="cu-pv-preview-meta">
                            <h2 class="cu-pv-preview-title" title="{{ $previewName }}">{{ $previewName }}</h2>
                            <div class="cu-pv-preview-sub">
                                <span class="cu-pv-preview-status" style="--s-color: {{ $previewStatusMeta['color'] }}; --s-bg: {{ $previewStatusMeta['bg'] }};">
                                    {{ $previewStatusMeta['label'] }}
                                </span>
                                @if ($previewDoc->user)
                                    <span class="cu-pv-preview-sub-sep">·</span>
                                    <span>Diunggah {{ $previewDoc->created_at?->diffForHumans() }} oleh {{ $previewDoc->user->name }}</span>
                                @endif
                                @if ($previewDoc->requiredDocument)
                                    <span class="cu-pv-preview-sub-sep">·</span>
                                    <span>{{ $previewDoc->requiredDocument->name }}</span>
                                @endif
                            </div>
                        </div>
                    </div>
                    <div class="cu-pv-preview-head-actions">
                        <button type="button"
                                wire:click="downloadSubmittedDocument({{ $previewDoc->id }})"
                                class="cu-pv-preview-btn cu-pv-preview-btn-primary"
                                title="Unduh file">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                            <span>Unduh</span>
                        </button>
                        <button type="button" wire:click="closeSubmittedDocumentPreview" class="cu-pv-icon-btn" aria-label="Tutup">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>
                        </button>
                    </div>
                </header>

                {{-- Body — preview area --}}
                <div class="cu-pv-preview-body">
                    @if ($previewUrl)
                        @if ($previewType === 'pdf')
                            <iframe src="{{ $previewUrl }}" class="cu-pv-preview-iframe" title="Pratinjau PDF"></iframe>
                        @elseif (in_array($previewType, ['jpg', 'jpeg', 'png', 'gif', 'webp']))
                            <div class="cu-pv-preview-image-wrap">
                                <img src="{{ $previewUrl }}" alt="{{ $previewName }}" class="cu-pv-preview-image">
                            </div>
                        @else
                            <div class="cu-pv-preview-fallback">
                                <div class="cu-pv-preview-fallback-icon">
                                    <svg width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                                </div>
                                <h3 class="cu-pv-preview-fallback-title">Pratinjau tidak tersedia</h3>
                                <p class="cu-pv-preview-fallback-text">Tipe file <strong>{{ strtoupper($previewType) }}</strong> tidak bisa di-preview langsung. Silakan unduh untuk membuka.</p>
                                <button type="button"
                                        wire:click="downloadSubmittedDocument({{ $previewDoc->id }})"
                                        class="cu-pv-preview-btn cu-pv-preview-btn-primary cu-pv-preview-btn-lg">
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                                    <span>Unduh File</span>
                                </button>
                            </div>
                        @endif
                    @else
                        <div class="cu-pv-preview-fallback">
                            <p class="cu-pv-preview-fallback-text">File tidak ditemukan.</p>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    @endif

    <x-filament-actions::modals />
</div>
