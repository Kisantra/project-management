{{--
    One tax period (TaxReportTab, ?masa=). Header, period bar (years + twelve
    months), one card per tax type that doubles as its tab, then the open
    type's workspace. Child components are keyed by report id, so opening
    another period mounts them fresh instead of syncing them by event.
--}}
@php
    $progress = $detail['progress'];
    $counts = $detail['counts'];
    $navTargets = 'selectYear,selectMonth,setType,backToSptList';
@endphp

<div class="kpt-detail" wire:loading.class="kpt-busy" wire:target="{{ $navTargets }}">
    <button type="button" class="kpt-back" wire:click="backToSptList">
        <x-heroicon-m-arrow-left class="h-4 w-4" />
        Kembali ke Daftar SPT
    </button>

    <header class="kpt-head">
        <div class="min-w-0">
            <h1 class="kpt-head__title">Laporan Pajak {{ $detail['label'] }}</h1>
            <p class="kpt-head__meta">
                <span>{{ $this->client->name }}</span>
                <span class="kpt-num">PPN {{ $counts['ppn'] }} · PPh {{ $counts['pph'] }} · Bupot {{ $counts['bupot'] }}</span>
            </p>
        </div>
        <x-kis.badge :tone="$progress['tone']" dot>{{ $progress['label'] }}</x-kis.badge>
    </header>

    {{-- Period: years, then the twelve months of the open year --}}
    <section class="kpt-card kpt-period" aria-label="Masa pajak">
        <div class="kpt-period__row">
            <div class="kpt-period__years">
                <span class="kpt-label">Tahun</span>
                <nav class="kpt-rail" aria-label="Tahun">
                    @foreach ($detail['years'] as $year)
                        <button type="button" @class(['kpt-rail__opt', 'is-on' => $year['year'] === $detail['year']])
                                wire:click="selectYear({{ $year['year'] }})" @if ($year['year'] === $detail['year']) aria-current="true" @endif>
                            {{ $year['year'] }} <em>{{ $year['count'] }}</em>
                        </button>
                    @endforeach
                </nav>
            </div>

            <nav class="kpt-months" aria-label="Bulan {{ $detail['year'] }}">
                @foreach ($detail['months'] as $month)
                    @php
                        $mark = match ($month['state']) {
                            'done' => 'Semua kontrak sudah lapor',
                            'pending' => 'Belum semua kontrak lapor',
                            'nocontract' => 'Ada data',
                            default => 'Belum ada data',
                        };
                    @endphp
                    @if ($month['state'] === 'nodata')
                        <span class="kpt-month is-nodata" title="{{ $month['name'] }}: belum ada data">
                            <b>{{ $month['short'] }}</b>
                        </span>
                    @else
                        <button type="button" @class(['kpt-month', 'is-' . $month['state'], 'is-current' => $month['current']])
                                wire:click="selectMonth({{ $month['number'] }})" data-kis-tip='@json($month['tip'])'
                                aria-label="{{ $month['name'] }}: {{ $mark }}" @if ($month['current']) aria-current="page" @endif>
                            <b>{{ $month['short'] }}</b>
                            @if ($month['state'] === 'done')
                                <i class="kpt-mark kpt-mark--ok" aria-hidden="true"><x-heroicon-m-check class="h-2.5 w-2.5" /></i>
                            @elseif ($month['state'] === 'pending')
                                <i class="kpt-mark kpt-mark--bad" aria-hidden="true">!</i>
                            @endif
                            @if ($month['activity'])
                                <i class="kpt-month__act" aria-hidden="true"></i>
                            @endif
                        </button>
                    @endif
                @endforeach
            </nav>
        </div>

        <div class="kpt-legend" aria-hidden="true">
            <span><i class="kpt-mark kpt-mark--ok kpt-mark--inline"><x-heroicon-m-check class="h-2.5 w-2.5" /></i>Semua kontrak sudah lapor</span>
            <span><i class="kpt-mark kpt-mark--bad kpt-mark--inline">!</i>Belum semua kontrak lapor</span>
            <span><i class="kpt-legend__act"></i>Ada transaksi</span>
            <span><i class="kpt-legend__current"></i>Masa yang dibuka</span>
            <span><i class="kpt-legend__nodata"></i>Belum ada data</span>
        </div>
    </section>

    {{-- Tax types: each card opens its workspace --}}
    <div class="kpt-types" role="tablist" aria-label="Jenis pajak">
        @foreach ($detail['cards'] as $card)
            @if ($card['locked'])
                <div class="kpt-type is-locked" tabindex="0" aria-disabled="true">
                    <span class="kpt-type__top">
                        <span class="kpt-label">{{ $card['label'] }}</span>
                        <x-kis.badge tone="neutral" size="sm"><x-heroicon-m-lock-closed class="h-3 w-3" />Tidak Aktif</x-kis.badge>
                    </span>
                    <span class="kpt-type__figure">
                        @if ($card['count'] !== null)
                            <b>{{ $card['count'] }}</b> {{ $card['unit'] }}
                        @else
                            <b>–</b>
                        @endif
                    </span>
                    <span class="kpt-type__tip" role="tooltip">
                        <b>Fitur {{ $card['label'] }} tidak tersedia</b>
                        Kontrak {{ $card['type'] === 'bupot' ? 'PPh Unifikasi (Bupot)' : $card['label'] }} untuk klien ini belum aktif. Hubungi admin untuk mengaktifkan fitur ini.
                    </span>
                </div>
            @else
                <button type="button" role="tab" @class(['kpt-type', 'is-on' => $type === $card['type']])
                        aria-selected="{{ $type === $card['type'] ? 'true' : 'false' }}" wire:click="setType('{{ $card['type'] }}')">
                    <span class="kpt-type__top">
                        <span class="kpt-label">{{ $card['label'] }}</span>
                        <x-kis.badge :tone="$card['tone']" :dot="$card['status'] !== 'not_contracted'" size="sm">{{ $card['statusLabel'] }}</x-kis.badge>
                    </span>
                    <span class="kpt-type__figure">
                        @if ($card['count'] !== null)
                            <b>{{ $card['count'] }}</b> {{ $card['unit'] }}
                        @else
                            <span class="kpt-type__note">{{ $card['reportedOn'] ? 'Dilapor ' . $card['reportedOn'] : 'SPT tahunan' }}</span>
                        @endif
                    </span>
                </button>
            @endif
        @endforeach
    </div>

    {{-- Workspace of the open type --}}
    <div class="kpt-workspace">
        @switch ($type)
            @case ('pph')
                @livewire('client.panel.tax-report.tax-report-pph', ['taxReportId' => $report->id], key('pph-' . $report->id))
                @break
            @case ('bupot')
                @livewire('client.panel.tax-report.tax-report-bupot', ['taxReportId' => $report->id], key('bupot-' . $report->id))
                @break
            @case ('pph_badan')
                @include('livewire.client.panel.tax-report.partials.pph-badan', ['badan' => $this->pphBadan, 'label' => $detail['label']])
                @break
            @default
                @livewire('client.panel.tax-report.tax-report-invoices', ['taxReportId' => $report->id], key('ppn-' . $report->id))
        @endswitch
    </div>
</div>
