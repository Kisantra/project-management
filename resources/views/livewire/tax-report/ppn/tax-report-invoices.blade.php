{{--
    PPN workspace. Sub-tab in ?tab= (kisUrlTab). The calculation is a ledger:
    keluaran − masukan − kompensasi = saldo akhir, from PpnBreakdown.
--}}
@php
    use App\Livewire\TaxReport\Ppn\TaxReportInvoices;
    use App\Services\TaxReport\ReportOverview;
    use App\Support\Ui\Tone;

    $l = $this->ledger;
    $b = $this->breakdown;
    $rp = fn (float $amount) => ReportOverview::rupiah(abs($amount));
    $statusLabel = fn (string $status) => ucfirst(mb_strtolower($status));
@endphp

<div class="kis-ws" x-data="kisUrlTab({ param: 'tab', value: @js($initialTab), fallback: 'faktur', allowed: @js(TaxReportInvoices::TABS) })">
    <div class="kis-subtabs">
        <nav class="kis-subtabs__nav" role="tablist" aria-label="Bagian PPN">
            <button type="button" role="tab" class="kis-subtab" x-bind:class="tab === 'faktur' && 'is-on'" x-bind:aria-selected="tab === 'faktur'" x-on:click="show('faktur')">
                Faktur <i>{{ number_format($this->invoiceCount, 0, ',', '.') }}</i>
            </button>
            <button type="button" role="tab" class="kis-subtab" x-bind:class="tab === 'perhitungan' && 'is-on'" x-bind:aria-selected="tab === 'perhitungan'" x-on:click="show('perhitungan')">
                Perhitungan
            </button>
            <button type="button" role="tab" class="kis-subtab" x-bind:class="tab === 'kompensasi' && 'is-on'" x-bind:aria-selected="tab === 'kompensasi'" x-on:click="show('kompensasi')">
                Kompensasi
                @if ($l['available'] > 0)
                    <i class="is-blue">Tersedia</i>
                @endif
            </button>
            <button type="button" role="tab" class="kis-subtab" x-bind:class="tab === 'tahunan' && 'is-on'" x-bind:aria-selected="tab === 'tahunan'" x-on:click="show('tahunan')">
                Ringkasan tahunan
            </button>
            <button type="button" role="tab" class="kis-subtab" x-bind:class="tab === 'spt' && 'is-on'" x-bind:aria-selected="tab === 'spt'" x-on:click="show('spt')">
                SPT
                @if ($this->sptUploaded)
                    <em class="is-done">Terunggah</em>
                @else
                    <em>Belum</em>
                @endif
            </button>
        </nav>
        <span class="kis-subtabs__ctx">PPN · {{ $this->periodLabel() }}</span>
    </div>

    {{-- Faktur --}}
    <div x-show="tab === 'faktur'" @style(['display: none' => $initialTab !== 'faktur'])>
        @livewire('tax-report.ppn.invoice-table', ['taxReportId' => $taxReportId], key('invoice-table-' . $taxReportId))
    </div>

    {{-- Perhitungan --}}
    <div x-show="tab === 'perhitungan'" @style(['display: none' => $initialTab !== 'perhitungan'])>
        <div class="kis-split">
            <section class="kis-card kis-ledger" aria-labelledby="ppn-ledger-title">
                <div class="kis-card__head">
                    <div>
                        <h3 class="kis-card__title" id="ppn-ledger-title">Perhitungan PPN {{ $this->periodLabel() }}</h3>
                        <p class="kis-card__sub">Dihitung ulang setiap kali faktur atau kompensasi berubah.</p>
                    </div>
                </div>
                <dl class="kis-ledger__rows">
                    <div class="kis-ledger__row">
                        <dt><span class="kis-ledger__op" aria-hidden="true"></span>PPN keluaran</dt>
                        <dd class="kis-ledger__note">{{ $b->keluar->count() }} faktur</dd>
                        <dd class="kis-ledger__amt">{{ $rp($l['keluar']) }}</dd>
                    </div>
                    <div class="kis-ledger__row">
                        <dt><span class="kis-ledger__op" aria-hidden="true">−</span>PPN masukan yang dikreditkan</dt>
                        <dd class="kis-ledger__note">{{ $b->masuk->count() }} faktur</dd>
                        <dd class="kis-ledger__amt">{{ $rp($l['masuk']) }}</dd>
                    </div>
                    <div class="kis-ledger__row is-sub">
                        <dt><span class="kis-ledger__op" aria-hidden="true">=</span><b>Selisih</b></dt>
                        <dd class="kis-ledger__note">{{ $statusLabel($l['selisihStatus']) }}</dd>
                        <dd class="kis-ledger__amt">{{ $rp($l['selisih']) }}</dd>
                    </div>
                    <div class="kis-ledger__row">
                        <dt><span class="kis-ledger__op" aria-hidden="true">−</span>Kompensasi diterima</dt>
                        <dd class="kis-ledger__note">dari periode sebelumnya</dd>
                        <dd class="kis-ledger__amt">{{ $rp($l['received']) }}</dd>
                    </div>
                    <div class="kis-ledger__row">
                        <dt>
                            <span class="kis-ledger__op" aria-hidden="true">−</span>Kompensasi manual
                            <button type="button" class="kis-ledger__edit" x-on:click="show('kompensasi'); $nextTick(() => $wire.dispatch('open-manual-kompensasi'))">Ubah</button>
                        </dt>
                        <dd class="kis-ledger__note" @if ($l['manualNotes']) title="{{ $l['manualNotes'] }}" @endif>{{ $l['manualNotes'] ? \Illuminate\Support\Str::limit($l['manualNotes'], 40) : 'tanpa catatan' }}</dd>
                        <dd class="kis-ledger__amt">{{ $rp($l['manual']) }}</dd>
                    </div>
                    <div class="kis-ledger__row is-total">
                        <dt>Saldo akhir</dt>
                        <dd class="kis-ledger__note"><x-kis.badge :tone="Tone::forPaymentStatus($l['status'])" dot>{{ $statusLabel($l['status']) }}</x-kis.badge></dd>
                        <dd @class(['kis-ledger__amt', 'is-bad' => $l['status'] === 'Kurang Bayar'])>{{ $rp($l['saldo']) }}</dd>
                    </div>
                    @if ($l['status'] === 'Lebih Bayar')
                        <div class="kis-ledger__row is-after">
                            <dt><span class="kis-ledger__op" aria-hidden="true">−</span>Dikompensasikan ke periode berikutnya</dt>
                            <dd class="kis-ledger__note"></dd>
                            <dd class="kis-ledger__amt">{{ $rp($l['given']) }}</dd>
                        </div>
                        <div class="kis-ledger__row is-after">
                            <dt><span class="kis-ledger__op" aria-hidden="true">=</span><b>Sisa lebih bayar</b></dt>
                            <dd class="kis-ledger__note">bisa dikompensasikan</dd>
                            <dd class="kis-ledger__amt">{{ $rp($l['available']) }}</dd>
                        </div>
                    @endif
                </dl>
            </section>

            <div class="kis-split__side">
                <section class="kis-card">
                    <div class="kis-card__head">
                        <div>
                            <h3 class="kis-card__title">Peredaran bruto</h3>
                            <p class="kis-card__sub">Total DPP faktur keluaran yang dihitung</p>
                        </div>
                    </div>
                    <div class="kis-card__body"><span class="kis-figure">{{ $rp($b->dppKeluar()) }}</span></div>
                </section>

                <section class="kis-card">
                    <div class="kis-card__head"><h3 class="kis-card__title">Faktur</h3></div>
                    <dl class="kis-kv">
                        <div><dt>Keluaran dihitung</dt><dd>{{ $b->keluar->count() }}</dd></div>
                        <div><dt>Masukan dikreditkan</dt><dd>{{ $b->masuk->count() }}</dd></div>
                        <div>
                            <dt>Dikecualikan <span class="kis-kv__hint">kode 02, 03, 07, 08 dan masukan non-usaha</span></dt>
                            <dd>{{ $b->excludedKeluar + $b->excludedMasuk }}</dd>
                        </div>
                        @if ($this->revisionCount > 0)
                            <div><dt>Revisi <span class="kis-kv__hint">menggantikan faktur asli</span></dt><dd>{{ $this->revisionCount }}</dd></div>
                        @endif
                        <div class="is-total"><dt>Total faktur</dt><dd>{{ $this->invoiceCount }}</dd></div>
                    </dl>
                </section>

                <section class="kis-card">
                    <div class="kis-card__head"><h3 class="kis-card__title">Aturan perhitungan</h3></div>
                    <ul class="kis-rules">
                        <li>Faktur keluaran kode 02, 03, 07, 08 tidak dihitung</li>
                        <li>Faktur yang direvisi dihitung dengan revisi terbarunya</li>
                        <li>Faktur masukan dihitung bila terkait usaha</li>
                        <li>Peredaran bruto dari DPP faktur keluaran</li>
                    </ul>
                </section>
            </div>
        </div>
    </div>

    {{-- Kompensasi --}}
    <div x-show="tab === 'kompensasi'" @style(['display: none' => $initialTab !== 'kompensasi'])>
        @livewire('tax-report.ppn.tax-report-kompensasi', ['taxReportId' => $taxReportId], key('kompensasi-' . $taxReportId))
    </div>

    {{-- Ringkasan tahunan --}}
    <div x-show="tab === 'tahunan'" @style(['display: none' => $initialTab !== 'tahunan'])>
        @livewire('tax-report.ppn.yearly-summary', ['taxReportId' => $taxReportId, 'clientId' => $this->taxReport->client_id], key('yearly-' . $taxReportId))
    </div>

    {{-- SPT --}}
    <div x-show="tab === 'spt'" @style(['display: none' => $initialTab !== 'spt'])>
        @livewire('tax-report.spt-upload', ['taxReportId' => $taxReportId, 'type' => 'ppn'], key('spt-ppn-' . $taxReportId))
    </div>
</div>
