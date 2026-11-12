@php
    // ── Urutan ──
    $sortCol = $this->getTableSortColumn();
    $sortDir = $this->getTableSortDirection();
    $sortOptions = [
        ['label' => 'Periode terbaru', 'col' => null, 'dir' => null],
        ['label' => 'Periode terlama', 'col' => 'month', 'dir' => 'asc'],
        ['label' => 'Client A–Z', 'col' => 'client.name', 'dir' => 'asc'],
        ['label' => 'Client Z–A', 'col' => 'client.name', 'dir' => 'desc'],
    ];
    $current = collect($sortOptions)->first(fn ($o) => $o['col'] === $sortCol && ($o['col'] === null || $o['dir'] === $sortDir));
    $sortLabel = $current['label'] ?? 'Periode terbaru';

    // ── Filter aktif ──
    $tf = $this->tableFilters ?? [];
    $fKontrak = (bool) ($tf['has_contracts']['isActive'] ?? false);
    $fJenis = $tf['contract_types']['values'] ?? [];
    $fKlien = $tf['client_status']['values'] ?? [];
    $fPpn = $tf['ppn_report_status']['value'] ?? null;
    $fBayar = $tf['payment_status']['values'] ?? [];
    $jenisLabels = ['ppn_contract' => 'PPN', 'pph_contract' => 'PPh', 'bupot_contract' => 'Bupot', 'pph_badan_contract' => 'PPh Badan'];
    $klienLabels = ['Active' => 'Aktif', 'Inactive' => 'Nonaktif'];
    $periodChip = $this->periodSummary();
    $filterCount = ($fKontrak ? 1 : 0) + (count($fJenis) ? 1 : 0) + (count($fKlien) ? 1 : 0) + ($fPpn ? 1 : 0) + (count($fBayar) ? 1 : 0);
    $hasActive = $periodChip || $filterCount > 0;
    // Default filters are shown as chips too; "Kembalikan bawaan" only once something differs.
    $atDefaults = ! $periodChip && $fKontrak && $fKlien === ['Active'] && ! $fJenis && ! $fPpn && ! $fBayar;

    $control = 'inline-flex h-[2.375rem] items-center gap-2 rounded-[11px] bg-white px-3 text-[13px] font-bold text-gray-950 ring-1 ring-inset ring-gray-300 transition hover:ring-gray-400 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-600 dark:bg-white/5 dark:text-white dark:ring-white/10';
    $check = 'h-4 w-4 rounded-[5px] border-gray-300 text-primary-600 focus:ring-primary-600 dark:border-white/20 dark:bg-white/5';
    $section = 'mb-2 text-[11px] font-extrabold uppercase tracking-[0.08em] text-gray-500 dark:text-gray-400';
@endphp

<x-filament-panels::page>
    <style>
        /* Tabel di-render custom (tax-report-list): buang chrome tabel Filament. */
        .tax-reports-table .fi-ta-ctn,
        .tax-reports-table .fi-ta {
            background-color: transparent !important;
            border: 0 !important;
            box-shadow: none !important;
            border-radius: 0 !important;
        }

        .tax-reports-table .fi-ta-filter-indicators,
        .tax-reports-table .fi-ta-selection-indicator,
        .tax-reports-table .fi-ta-header-toolbar,
        .tax-reports-table .fi-ta-header,
        .tax-reports-table .gap-x-6.bg-gray-50 {
            display: none !important;
        }

        .tax-reports-table .fi-ta-content {
            border-top: 0 !important;
        }

        .tax-reports-table .fi-ta-pagination {
            margin-top: 0.75rem;
            padding: 0.5rem 0 0 !important;
            border: 0 !important;
            background: transparent !important;
            box-shadow: none !important;
        }
    </style>

    {{-- Mode Board sudah tidak ada; bersihkan pilihan lama yang tersimpan di browser. --}}
    <script>try { localStorage.removeItem('taxViewMode') } catch (e) {}</script>

    <div class="space-y-3">
        <div class="flex flex-wrap items-center gap-2">
            <label class="relative block min-w-[16rem] flex-1 sm:max-w-sm">
                <span class="sr-only">Cari client</span>
                <x-filament::icon icon="heroicon-m-magnifying-glass" class="pointer-events-none absolute start-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400" />
                <input type="search" wire:model.live.debounce.400ms="tableSearch" placeholder="Cari client…"
                    class="block h-[2.375rem] w-full rounded-[11px] border-0 bg-white ps-9 pe-3 text-sm text-gray-950 ring-1 ring-inset ring-gray-300 placeholder:text-gray-400 focus:ring-2 focus:ring-primary-600 dark:bg-white/5 dark:text-white dark:ring-white/10">
            </label>

            <x-kis.month-picker wire:model.live="period" :counts="$this->monthCounts" />

            <div class="ms-auto flex items-center gap-2">
                {{-- Filter --}}
                <div class="relative" x-data="{ open: false }" x-on:click.outside="open = false" x-on:keydown.escape.window="open = false">
                    <button type="button" x-on:click="open = ! open" x-bind:aria-expanded="open" aria-haspopup="dialog" class="{{ $control }}">
                        <x-filament::icon icon="heroicon-m-funnel" class="h-4 w-4 text-gray-500 dark:text-gray-400" />
                        Filter
                        @if ($filterCount)
                            <span class="grid h-5 min-w-[1.25rem] place-items-center rounded-full bg-primary-600 px-1.5 text-[11px] font-extrabold text-white">{{ $filterCount }}</span>
                        @endif
                        <svg class="kis-chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m19.5 8.25-7.5 7.5-7.5-7.5" /></svg>
                    </button>

                    <div x-show="open" x-cloak x-transition.opacity.duration.150ms class="kis-pop kis-pop--end w-80 p-4" role="dialog" aria-label="Filter laporan">
                        <label class="flex items-center justify-between gap-2 text-[13px] font-bold text-gray-950 dark:text-white">
                            Hanya klien dengan kontrak aktif
                            <input type="checkbox" wire:model.live="tableFilters.has_contracts.isActive" class="{{ $check }}">
                        </label>

                        <div class="mt-4">
                            <p class="{{ $section }}">Jenis kontrak</p>
                            <div class="flex flex-wrap gap-x-4 gap-y-2">
                                @foreach ($jenisLabels as $value => $label)
                                    <label class="inline-flex items-center gap-1.5 text-[13px] font-semibold text-gray-700 dark:text-gray-300">
                                        <input type="checkbox" value="{{ $value }}" wire:model.live="tableFilters.contract_types.values" class="{{ $check }}">{{ $label }}
                                    </label>
                                @endforeach
                            </div>
                        </div>

                        <div class="mt-4">
                            <p class="{{ $section }}">Status klien</p>
                            <div class="flex flex-wrap gap-x-4 gap-y-2">
                                @foreach ($klienLabels as $value => $label)
                                    <label class="inline-flex items-center gap-1.5 text-[13px] font-semibold text-gray-700 dark:text-gray-300">
                                        <input type="checkbox" value="{{ $value }}" wire:model.live="tableFilters.client_status.values" class="{{ $check }}">{{ $label }}
                                    </label>
                                @endforeach
                            </div>
                        </div>

                        <div class="mt-4">
                            <p class="{{ $section }}">Status lapor PPN</p>
                            <div class="inline-flex gap-0.5 rounded-[11px] bg-gray-100 p-1 dark:bg-white/5" role="radiogroup" aria-label="Status lapor PPN">
                                @foreach (['' => 'Semua', 'Sudah Lapor' => 'Sudah lapor', 'Belum Lapor' => 'Belum lapor'] as $value => $label)
                                    <label @class([
                                        'cursor-pointer rounded-[9px] px-2.5 py-1 text-xs font-bold transition',
                                        'bg-white text-primary-700 shadow-sm dark:bg-gray-800 dark:text-primary-300' => ($fPpn ?? '') === $value,
                                        'text-gray-600 hover:text-gray-950 dark:text-gray-300 dark:hover:text-white' => ($fPpn ?? '') !== $value,
                                    ])>
                                        <input type="radio" class="sr-only" value="{{ $value }}" wire:model.live="tableFilters.ppn_report_status.value">{{ $label }}
                                    </label>
                                @endforeach
                            </div>
                        </div>

                        <div class="mt-4">
                            <p class="{{ $section }}">Status bayar</p>
                            <div class="flex flex-wrap gap-x-4 gap-y-2">
                                @foreach (['Kurang Bayar', 'Lebih Bayar', 'Nihil'] as $value)
                                    <label class="inline-flex items-center gap-1.5 text-[13px] font-semibold text-gray-700 dark:text-gray-300">
                                        <input type="checkbox" value="{{ $value }}" wire:model.live="tableFilters.payment_status.values" class="{{ $check }}">{{ $value }}
                                    </label>
                                @endforeach
                            </div>
                        </div>

                        <div class="mt-4 border-t border-gray-200 pt-3 dark:border-white/10">
                            <button type="button" wire:click="resetTableFiltersForm" x-on:click="open = false" class="kis-btn kis-btn--outline kis-btn--sm w-full">Kembalikan filter bawaan</button>
                        </div>
                    </div>
                </div>

                {{-- Urutan --}}
                <div class="relative" x-data="{ open: false }" x-on:click.outside="open = false" x-on:keydown.escape.window="open = false">
                    <button type="button" x-on:click="open = ! open" x-bind:aria-expanded="open" aria-haspopup="listbox" class="{{ $control }}">
                        <x-filament::icon icon="heroicon-m-arrows-up-down" class="h-4 w-4 text-gray-500 dark:text-gray-400" />
                        {{ $sortLabel }}
                        <svg class="kis-chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m19.5 8.25-7.5 7.5-7.5-7.5" /></svg>
                    </button>
                    <div x-show="open" x-cloak x-transition.opacity.duration.150ms class="kis-pop kis-pop--end w-52 p-1.5" role="listbox" aria-label="Urutan">
                        @foreach ($sortOptions as $option)
                            @php $active = $option['label'] === $sortLabel; @endphp
                            <button type="button" role="option" aria-selected="{{ $active ? 'true' : 'false' }}" x-on:click="open = false"
                                wire:click="sortTable(@js($option['col']), @js($option['dir']))"
                                @class([
                                    'flex w-full items-center justify-between rounded-[9px] px-3 py-2 text-left text-[13px] font-bold transition',
                                    'bg-primary-50 text-primary-700 dark:bg-primary-500/10 dark:text-primary-300' => $active,
                                    'text-gray-700 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-white/5' => ! $active,
                                ])>
                                {{ $option['label'] }}
                                @if ($active)
                                    <x-filament::icon icon="heroicon-m-check" class="h-4 w-4" />
                                @endif
                            </button>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>

        @if ($hasActive)
            <x-kis.active-filters>
                @if ($periodChip)
                    <x-kis.filter-chip wire:click="clearPeriod">Periode: {{ $periodChip }}</x-kis.filter-chip>
                @endif
                @if ($fKontrak)
                    <x-kis.filter-chip wire:click="$set('tableFilters.has_contracts.isActive', false)">Kontrak aktif</x-kis.filter-chip>
                @endif
                @if (count($fJenis))
                    <x-kis.filter-chip wire:click="$set('tableFilters.contract_types.values', [])">Kontrak: {{ collect($fJenis)->map(fn ($v) => $jenisLabels[$v] ?? $v)->implode(', ') }}</x-kis.filter-chip>
                @endif
                @if (count($fKlien))
                    <x-kis.filter-chip wire:click="$set('tableFilters.client_status.values', [])">Klien: {{ collect($fKlien)->map(fn ($v) => $klienLabels[$v] ?? $v)->implode(', ') }}</x-kis.filter-chip>
                @endif
                @if ($fPpn)
                    <x-kis.filter-chip wire:click="$set('tableFilters.ppn_report_status.value', null)">PPN: {{ $fPpn }}</x-kis.filter-chip>
                @endif
                @if (count($fBayar))
                    <x-kis.filter-chip wire:click="$set('tableFilters.payment_status.values', [])">Bayar: {{ implode(', ', $fBayar) }}</x-kis.filter-chip>
                @endif
                @unless ($atDefaults)
                    <x-slot name="reset" wire:click="resetAllFilters">Kembalikan bawaan</x-slot>
                @endunless
            </x-kis.active-filters>
        @endif

        <div class="tax-reports-table pt-1">
            {{ $this->table }}
        </div>
    </div>
</x-filament-panels::page>
