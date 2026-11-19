{{-- Ringkasan Tahunan (YearlySummary): one row per period of the year; the open period starts expanded. --}}
@php
    use App\Support\TaxPeriod;
    use App\Support\Ui\Tone;

    $rp = fn ($value) => 'Rp ' . number_format(abs((float) $value), 0, ',', '.');
@endphp

<div class="kpt-year">
    <div class="kpt-year__head">
        <div class="min-w-0">
            <h3 class="kpt-card__title">Riwayat Laporan PPN {{ $timelineData['year'] }}</h3>
            <p class="kpt-card__sub">Ringkasan perhitungan pajak bulanan tahun {{ $timelineData['year'] }}</p>
        </div>
        <div class="kpt-year__actions">
            <x-kis.badge tone="neutral" size="sm">{{ $timelineData['months_with_reports'] }}/12 Bulan</x-kis.badge>
            <button type="button" class="kpt-btn kpt-btn--outline kpt-btn--sm" wire:click="exportYearlyReportPdf" wire:loading.attr="disabled" wire:target="exportYearlyReportPdf">
                <x-heroicon-m-document-arrow-down class="h-4 w-4" wire:loading.remove wire:target="exportYearlyReportPdf" />
                <x-filament::loading-indicator class="h-4 w-4" wire:loading wire:target="exportYearlyReportPdf" />
                <span wire:loading.remove wire:target="exportYearlyReportPdf">Ekspor PDF</span>
                <span wire:loading wire:target="exportYearlyReportPdf">Mengekspor…</span>
            </button>
            <button type="button" class="kpt-btn kpt-btn--outline kpt-btn--sm" wire:click="exportYearlyReport" wire:loading.attr="disabled" wire:target="exportYearlyReport">
                <x-heroicon-m-table-cells class="h-4 w-4" wire:loading.remove wire:target="exportYearlyReport" />
                <x-filament::loading-indicator class="h-4 w-4" wire:loading wire:target="exportYearlyReport" />
                <span wire:loading.remove wire:target="exportYearlyReport">Ekspor Excel</span>
                <span wire:loading wire:target="exportYearlyReport">Mengekspor…</span>
            </button>
        </div>
    </div>

    @if (empty($timelineData['timeline']))
        @include('livewire.client.panel.tax-report.partials.empty', [
            'icon' => 'heroicon-o-calendar-days',
            'title' => 'Belum ada laporan pajak untuk tahun ' . $timelineData['year'],
            'text' => 'Laporan pajak tahun ini akan muncul di sini setelah dibuat oleh tim Kisantra.',
        ])
    @else
        <ol class="kpt-year__list">
            @foreach ($timelineData['timeline'] as $item)
                @php $summary = $item['ppn_summary']; @endphp
                <li class="kpt-year__item" x-data="{ open: @js($item['is_current']) }" wire:key="year-{{ $item['id'] }}">
                    <button type="button" class="kpt-year__row" x-on:click="open = ! open" x-bind:aria-expanded="open">
                        <span class="kpt-year__month">
                            {{ $item['month_name'] }}
                            @if ($item['is_current'])
                                <x-kis.badge tone="outline" size="sm">Aktif</x-kis.badge>
                            @endif
                        </span>
                        @if ($summary)
                            <x-kis.badge :tone="Tone::forPaymentStatus($summary->status_final)" dot size="sm">{{ $summary->status_final ?: 'Nihil' }}</x-kis.badge>
                            <span class="kpt-year__amount">{{ $rp($summary->saldo_final) }}</span>
                        @else
                            <span class="kpt-caption">Belum ada data</span>
                            <span class="kpt-year__amount is-zero">–</span>
                        @endif
                        <x-heroicon-m-chevron-down class="kpt-year__chev" x-bind:class="open && 'is-open'" />
                    </button>

                    <div x-show="open" x-collapse x-cloak>
                        @if ($summary)
                            <dl class="kpt-facts kpt-facts--year">
                                <div><dt>PPN Masuk</dt><dd class="kpt-num">{{ $rp($summary->pajak_masuk) }}</dd><small>Kredit Pajak</small></div>
                                <div><dt>PPN Keluar</dt><dd class="kpt-num">{{ $rp($summary->pajak_keluar) }}</dd><small>Pajak Keluaran</small></div>
                                <div><dt>Saldo Final</dt><dd class="kpt-num">{{ $rp($summary->saldo_final) }}</dd><small>{{ $summary->status_final ?: 'Nihil' }}</small></div>
                                <div>
                                    <dt>Kompensasi</dt>
                                    @if ($summary->kompensasi_diterima > 0 || $summary->kompensasi_terpakai > 0)
                                        <dd class="kpt-lines">
                                            @if ($summary->kompensasi_diterima > 0)<span>Diterima <b>{{ $rp($summary->kompensasi_diterima) }}</b></span>@endif
                                            @if ($summary->kompensasi_terpakai > 0)<span>Terpakai <b>{{ $rp($summary->kompensasi_terpakai) }}</b></span>@endif
                                            @if ($summary->kompensasi_tersedia > $summary->kompensasi_terpakai)<span>Tersedia <b>{{ $rp($summary->kompensasi_tersedia - $summary->kompensasi_terpakai) }}</b></span>@endif
                                        </dd>
                                    @else
                                        <dd class="is-muted">Tidak ada</dd>
                                    @endif
                                </div>
                                <div>
                                    <dt>Status Lapor</dt>
                                    <dd>
                                        <x-kis.badge :tone="Tone::forReportStatus($summary->report_status)" dot size="sm">{{ $summary->report_status ?: 'Belum Lapor' }}</x-kis.badge>
                                    </dd>
                                    @if ($summary->reported_at)<small>{{ TaxPeriod::date($summary->reported_at) }}</small>@endif
                                </div>
                            </dl>
                        @else
                            <p class="kpt-year__none">Belum ada data</p>
                        @endif
                    </div>
                </li>
            @endforeach
        </ol>
    @endif
</div>
