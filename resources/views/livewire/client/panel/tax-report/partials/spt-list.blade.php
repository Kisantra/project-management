{{-- Reported SPT of the selected company (TaxReportTab, list mode). --}}
@php
    $allSpts = $this->reportedSpts;
    $spts = $this->filteredSpts;
    $grandTotal = $allSpts->count();
    $total = $spts->count();
    $hasReports = $this->reports->isNotEmpty();
    $hasFilter = $sptSearch !== '' || count($sptJenis) > 0 || $sptYear !== '';

    $lastPage = max(1, (int) ceil($total / max(1, $sptPerPage)));
    $page = min(max(1, $sptPage), $lastPage);
    $offset = ($page - 1) * $sptPerPage;
    $rows = $spts->slice($offset, $sptPerPage);
    $from = $total ? $offset + 1 : 0;
    $to = min($offset + $sptPerPage, $total);

    $groupLabels = ['jenis' => 'Jenis SPT', 'tahun' => 'Tahun', 'status' => 'Status', 'none' => 'Tanpa Grup'];
    $groupOf = fn ($r) => match ($sptGroupBy) {
        'tahun' => (string) $r['year'],
        'status' => $r['paid'] ? 'Sudah Lapor & Bayar' : 'Sudah Lapor',
        default => $r['jenis'],
    };
    $groupCounts = $sptGroupBy === 'none' ? collect() : $spts->groupBy($groupOf)->map->count();

    $sortable = ['jenis' => 'Jenis SPT', 'masa' => 'Masa Pajak', 'nominal' => 'Nominal', 'status' => 'Status'];
@endphp

<header class="kpt-head">
    <div class="min-w-0">
        <h1 class="kpt-head__title">Laporan Pajak</h1>
        <p class="kpt-head__sub">Riwayat SPT yang telah dilaporkan. Klik baris untuk membuka detail.</p>
    </div>
    @if ($hasReports)
        <button type="button" class="kpt-btn kpt-btn--outline" wire:click="selectYear({{ $this->reports->last()->period_year }})">
            <x-heroicon-m-calendar-days class="h-4 w-4" />
            Lihat per masa pajak
        </button>
    @endif
</header>

<section class="kpt-card">
    <div class="kpt-card__head">
        <h2 class="kpt-card__title">SPT Dilaporkan</h2>
        <x-kis.badge tone="neutral" size="sm">{{ $grandTotal }} SPT</x-kis.badge>

        @if ($grandTotal > 0)
            <div class="kpt-card__aside" x-data="{ open: false }" x-on:keydown.escape="open = false">
                <button type="button" class="kpt-trigger" x-on:click="open = ! open" x-bind:aria-expanded="open">
                    <x-heroicon-m-rectangle-group class="kpt-trigger__icon" />
                    <span class="kpt-trigger__label">Grup</span>
                    {{ $groupLabels[$sptGroupBy] ?? 'Jenis SPT' }}
                    <x-heroicon-m-chevron-down class="kpt-trigger__chev" />
                </button>
                <div class="kpt-pop kpt-pop--right" x-show="open" x-cloak x-transition.opacity.duration.150ms x-on:click.outside="open = false">
                    <p class="kpt-pop__label">Kelompokkan menurut</p>
                    @foreach ($groupLabels as $value => $label)
                        <button type="button" @class(['kpt-pop__opt', 'is-on' => $sptGroupBy === $value])
                                wire:click="setSptGroup('{{ $value }}')" x-on:click="open = false">
                            {{ $label }}
                            @if ($sptGroupBy === $value)<x-heroicon-m-check class="h-4 w-4" />@endif
                        </button>
                    @endforeach
                </div>
            </div>
        @endif
    </div>

    @if ($grandTotal === 0)
        @include('livewire.client.panel.tax-report.partials.empty', [
            'icon' => $hasReports ? 'heroicon-o-document-check' : 'heroicon-o-document-chart-bar',
            'title' => $hasReports ? 'Belum ada SPT yang dilaporkan' : 'Belum ada laporan pajak',
            'text' => $hasReports
                ? 'SPT akan muncul di sini setelah berkas SPT/BPE diunggah pada masa pajak terkait.'
                : 'Laporan pajak perusahaan Anda akan muncul di sini setelah dibuat oleh tim Kisantra.',
            'action' => $hasReports
                ? new \Illuminate\Support\HtmlString('<button type="button" class="kpt-btn kpt-btn--outline kpt-btn--sm" wire:click="selectYear(' . $this->reports->last()->period_year . ')">Lihat masa pajak terbaru</button>')
                : null,
        ])
    @else
        <div class="kpt-toolbar">
            <label class="kpt-search">
                <x-heroicon-m-magnifying-glass class="kpt-search__icon" />
                <input type="search" wire:model.live.debounce.300ms="sptSearch" placeholder="Cari jenis, masa, atau no. bukti…" aria-label="Cari SPT">
            </label>

            @if ($this->sptJenisOptions->count() > 1)
                <div class="relative" x-data="{ open: false }" x-on:keydown.escape="open = false">
                    <button type="button" @class(['kpt-trigger', 'is-active' => count($sptJenis)]) x-on:click="open = ! open" x-bind:aria-expanded="open">
                        <span class="kpt-trigger__label">Jenis</span>
                        {{ count($sptJenis) ? count($sptJenis) . ' dipilih' : 'Semua' }}
                        <x-heroicon-m-chevron-down class="kpt-trigger__chev" />
                    </button>
                    <div class="kpt-pop" x-show="open" x-cloak x-transition.opacity.duration.150ms x-on:click.outside="open = false">
                        <p class="kpt-pop__label">Filter Jenis SPT</p>
                        @foreach ($this->sptJenisOptions as $value => $label)
                            <label class="kpt-pop__opt kpt-pop__opt--check">
                                <input type="checkbox" value="{{ $value }}" wire:model.live="sptJenis">
                                {{ $label }}
                            </label>
                        @endforeach
                    </div>
                </div>
            @endif

            @if ($this->sptYears->count() > 1)
                <div class="relative" x-data="{ open: false }" x-on:keydown.escape="open = false">
                    <button type="button" @class(['kpt-trigger', 'is-active' => $sptYear !== '']) x-on:click="open = ! open" x-bind:aria-expanded="open">
                        <span class="kpt-trigger__label">Tahun</span>
                        {{ $sptYear !== '' ? $sptYear : 'Semua Tahun' }}
                        <x-heroicon-m-chevron-down class="kpt-trigger__chev" />
                    </button>
                    <div class="kpt-pop" x-show="open" x-cloak x-transition.opacity.duration.150ms x-on:click.outside="open = false">
                        <button type="button" @class(['kpt-pop__opt', 'is-on' => $sptYear === '']) wire:click="setSptYear('')" x-on:click="open = false">
                            Semua Tahun
                            @if ($sptYear === '')<x-heroicon-m-check class="h-4 w-4" />@endif
                        </button>
                        @foreach ($this->sptYears as $year)
                            <button type="button" @class(['kpt-pop__opt', 'is-on' => (string) $sptYear === (string) $year]) wire:click="setSptYear('{{ $year }}')" x-on:click="open = false">
                                {{ $year }}
                                @if ((string) $sptYear === (string) $year)<x-heroicon-m-check class="h-4 w-4" />@endif
                            </button>
                        @endforeach
                    </div>
                </div>
            @endif

            @if ($hasFilter)
                <button type="button" class="kpt-reset" wire:click="resetSptFilters">
                    Reset
                    <x-heroicon-m-x-mark class="h-4 w-4" />
                </button>
            @endif
        </div>

        @if ($total === 0)
            @include('livewire.client.panel.tax-report.partials.empty', [
                'icon' => 'heroicon-o-magnifying-glass',
                'title' => 'Tidak ada SPT yang cocok',
                'text' => 'Coba ubah kata kunci atau filter.',
                'action' => new \Illuminate\Support\HtmlString('<button type="button" class="kpt-link" wire:click="resetSptFilters">Reset filter</button>'),
            ])
        @else
            <div class="kpt-scroll" wire:loading.class="kpt-busy" wire:target="sortBy,setSptGroup,setSptYear,sptJenis,sptSearch,gotoSptPage,sptPerPage,resetSptFilters">
                <table class="kpt-table kpt-table--spt">
                    <thead>
                        <tr>
                            @foreach ($sortable as $key => $label)
                                <th @class(['is-num' => $key === 'nominal'])>
                                    <button type="button" class="kpt-sort" wire:click="sortBy('{{ $key }}')"
                                            aria-label="Urutkan menurut {{ $label }}">
                                        {{ $label }}
                                        @if ($sptSort === $key)
                                            <x-filament::icon :icon="$sptSortDir === 'asc' ? 'heroicon-m-chevron-up' : 'heroicon-m-chevron-down'" class="kpt-sort__on" />
                                        @else
                                            <x-heroicon-m-chevron-up-down class="kpt-sort__off" />
                                        @endif
                                    </button>
                                </th>
                            @endforeach
                            <th class="is-num">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @php $previous = null; @endphp
                        @foreach ($rows as $row)
                            @php $group = $sptGroupBy === 'none' ? null : $groupOf($row); @endphp
                            @if ($group !== null && $group !== $previous)
                                @php $previous = $group; @endphp
                                <tr class="kpt-group" wire:key="spt-group-{{ md5($group) }}">
                                    <td colspan="5"><span>{{ $group }}</span><em>{{ $groupCounts[$group] ?? 0 }}</em></td>
                                </tr>
                            @endif
                            <tr class="kpt-row" wire:key="spt-{{ $row['id'] }}" tabindex="0"
                                wire:click="openSptDetail({{ $row['month'] }}, {{ $row['year'] }}, '{{ $row['type'] }}')"
                                x-on:keydown.enter="$wire.openSptDetail({{ $row['month'] }}, {{ $row['year'] }}, '{{ $row['type'] }}')">
                                <td class="kpt-strong">{{ $row['jenis'] }}</td>
                                <td>{{ $row['masa'] }}</td>
                                <td @class(['is-num kpt-strong', 'is-zero' => $row['nominal'] == 0])>Rp {{ number_format($row['nominal'], 0, ',', '.') }}</td>
                                <td>
                                    <span class="kpt-badges">
                                        <x-kis.badge tone="blue" dot size="sm">Sudah Lapor</x-kis.badge>
                                        @if ($row['paid'])
                                            <x-kis.badge tone="outline" size="sm">Sudah Bayar</x-kis.badge>
                                        @endif
                                    </span>
                                </td>
                                <td class="is-num">
                                    <span class="kpt-acts">
                                        <button type="button" class="kpt-icon-btn" title="Lihat detail laporan" aria-label="Lihat detail laporan"
                                                wire:click.stop="openSptDetail({{ $row['month'] }}, {{ $row['year'] }}, '{{ $row['type'] }}')">
                                            <x-heroicon-o-eye class="h-4 w-4" />
                                        </button>
                                        @if ($row['fileUrl'])
                                            <a href="{{ $row['fileUrl'] }}" target="_blank" rel="noopener" class="kpt-icon-btn" x-on:click.stop
                                               title="Buka berkas SPT" aria-label="Buka berkas SPT">
                                                <x-heroicon-o-arrow-top-right-on-square class="h-4 w-4" />
                                            </a>
                                            <a href="{{ $row['fileUrl'] }}" download class="kpt-icon-btn" x-on:click.stop
                                               title="Unduh berkas SPT" aria-label="Unduh berkas SPT">
                                                <x-heroicon-o-arrow-down-tray class="h-4 w-4" />
                                            </a>
                                        @endif
                                    </span>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <footer class="kpt-pager">
                <label class="kpt-pager__size">
                    <span>Baris per halaman</span>
                    <select wire:model.live="sptPerPage">
                        @foreach ([5, 10, 25, 50] as $size)
                            <option value="{{ $size }}">{{ $size }}</option>
                        @endforeach
                    </select>
                </label>
                <span class="kpt-pager__range">{{ $from }}–{{ $to }} dari {{ $total }}</span>
                @if ($lastPage > 1)
                    <nav class="kpt-pager__pages" aria-label="Halaman">
                        <button type="button" class="kpt-icon-btn" wire:click="gotoSptPage({{ $page - 1 }})" @disabled($page <= 1) aria-label="Sebelumnya">
                            <x-heroicon-m-chevron-left class="h-4 w-4" />
                        </button>
                        @for ($p = 1; $p <= $lastPage; $p++)
                            <button type="button" @class(['kpt-page', 'is-on' => $p === $page]) wire:click="gotoSptPage({{ $p }})" @if ($p === $page) aria-current="page" @endif>{{ $p }}</button>
                        @endfor
                        <button type="button" class="kpt-icon-btn" wire:click="gotoSptPage({{ $page + 1 }})" @disabled($page >= $lastPage) aria-label="Berikutnya">
                            <x-heroicon-m-chevron-right class="h-4 w-4" />
                        </button>
                    </nav>
                @endif
            </footer>
        @endif
    @endif
</section>
