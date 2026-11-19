{{-- PPN workspace (TaxReportInvoices): summary strip, then Daftar Pajak / Kalkulasi / Ringkasan Tahunan. --}}
@php
    use App\Livewire\Client\Panel\TaxReport\TaxReportInvoices;
    use App\Support\Ui\Tone;

    $rp = fn ($value) => 'Rp ' . number_format(abs((float) $value), 0, ',', '.');
    $selisih = $ppnKeluar - $ppnMasuk;
    $selisihStatus = TaxReportInvoices::statusOf($selisih);
    $finalTone = Tone::forPaymentStatus($statusFinal);
    $hasKompensasi = $kompensasiDiterima > 0 || $kompensasiTersedia > 0 || $kompensasiTerpakai > 0;
@endphp

<section class="kpt-card kpt-ws" x-data="{ tab: 'daftar' }">
    <div class="kpt-card__head">
        <div class="min-w-0">
            <h2 class="kpt-card__title">Detail Laporan PPN</h2>
            <p class="kpt-card__sub">Periode {{ $this->periodName }} · {{ $taxReport->client->name }}</p>
        </div>
        <div class="kpt-card__aside">
            <span class="kpt-caption">Status Pajak</span>
            <x-kis.badge :tone="$finalTone" dot>{{ $statusFinal }}</x-kis.badge>
        </div>
    </div>

    {{-- Ringkasan Utama --}}
    <p class="kpt-strip-head">
        <span class="kpt-label">Ringkasan Utama @include('livewire.client.panel.tax-report.partials.info', ['text' => 'Ringkasan perhitungan PPN untuk periode ini, menampilkan total PPN Masuk, PPN Keluar, dan status akhir'])</span>
    </p>
    <div class="kpt-strip kpt-strip--headed" aria-label="Ringkasan Utama">
        <div class="kpt-strip__cell">
            <span class="kpt-label">PPN Keluar @include('livewire.client.panel.tax-report.partials.info', ['text' => 'Total PPN yang dipungut dari penjualan/output (wajib disetor)'])</span>
            <b class="kpt-strip__value">{{ $rp($ppnKeluar) }}</b>
            <span class="kpt-strip__note">{{ $fakturKeluarCount }} faktur</span>
        </div>
        <div class="kpt-strip__cell">
            <span class="kpt-label">PPN Masuk @include('livewire.client.panel.tax-report.partials.info', ['text' => 'Total PPN yang dibayarkan saat pembelian/input (dapat dikreditkan)'])</span>
            <b class="kpt-strip__value">{{ $rp($ppnMasuk) }}</b>
            <span class="kpt-strip__note">{{ $fakturMasukCount }} faktur</span>
        </div>
        <div class="kpt-strip__cell">
            <span class="kpt-label">Selisih @include('livewire.client.panel.tax-report.partials.info', ['text' => 'Selisih antara PPN Keluar dan PPN Masuk sebelum kompensasi'])</span>
            <b class="kpt-strip__value">{{ $rp($selisih) }}</b>
            <span @class(['kpt-strip__note', 'is-bad' => $selisihStatus === 'Kurang Bayar', 'is-good' => $selisihStatus === 'Lebih Bayar'])>{{ $selisihStatus }}</span>
        </div>
        <div class="kpt-strip__cell">
            <span class="kpt-label">Saldo Akhir @include('livewire.client.panel.tax-report.partials.info', ['text' => 'Saldo akhir setelah diperhitungkan dengan kompensasi (jika ada)'])</span>
            <b class="kpt-strip__value">{{ $rp($saldoFinal) }}</b>
            <span @class(['kpt-strip__note', 'is-bad' => $statusFinal === 'Kurang Bayar', 'is-good' => $statusFinal === 'Lebih Bayar'])>{{ $statusFinal }} · setelah kompensasi</span>
        </div>
    </div>

    {{-- Sub-tabs --}}
    <div class="kpt-subtabs">
        <div class="kpt-rail" role="tablist" aria-label="Isi laporan PPN">
            <button type="button" role="tab" class="kpt-rail__opt" x-bind:class="tab === 'daftar' && 'is-on'" x-bind:aria-selected="tab === 'daftar'" x-on:click="tab = 'daftar'">
                <span class="hidden sm:inline">Daftar Pajak</span><span class="sm:hidden">Pajak</span>
                <em>{{ $totalInvoices }}</em>
            </button>
            <button type="button" role="tab" class="kpt-rail__opt" x-bind:class="tab === 'kalkulasi' && 'is-on'" x-bind:aria-selected="tab === 'kalkulasi'" x-on:click="tab = 'kalkulasi'">Kalkulasi</button>
            <button type="button" role="tab" class="kpt-rail__opt" x-bind:class="tab === 'tahunan' && 'is-on'" x-bind:aria-selected="tab === 'tahunan'" x-on:click="tab = 'tahunan'">
                <span class="hidden sm:inline">Ringkasan Tahunan</span><span class="sm:hidden">Tahunan</span>
            </button>
        </div>
    </div>

    {{-- Daftar Pajak --}}
    <div class="kpt-ws__table" x-show="tab === 'daftar'">
        @livewire('client.panel.tax-report.components.invoice-table', ['taxReportId' => $taxReportId], key('invoice-table-' . $taxReportId))
    </div>

    {{-- Kalkulasi --}}
    <div class="kpt-ws__body" x-show="tab === 'kalkulasi'" x-cloak>
        <h3 class="kpt-section">Detail Perhitungan @include('livewire.client.panel.tax-report.partials.info', ['text' => 'Rincian lengkap perhitungan PPN, peredaran bruto, dan ringkasan faktur'])</h3>
        <div class="kpt-ledgers">
            <div class="kpt-ledger">
                <p class="kpt-ledger__title">Perhitungan PPN @include('livewire.client.panel.tax-report.partials.info', ['text' => 'Formula: PPN Keluar - PPN Masuk = Selisih'])</p>
                <dl>
                    <div><dt>PPN Keluar</dt><dd>{{ $rp($ppnKeluar) }}</dd></div>
                    <div><dt>PPN Masuk</dt><dd>{{ $rp($ppnMasuk) }}</dd></div>
                    <div class="is-total"><dt>Selisih</dt><dd>{{ $rp($selisih) }}</dd></div>
                </dl>
            </div>
            <div class="kpt-ledger">
                <p class="kpt-ledger__title">Peredaran Bruto @include('livewire.client.panel.tax-report.partials.info', ['text' => 'Total nilai DPP (Dasar Pengenaan Pajak) dari seluruh transaksi'])</p>
                <dl>
                    <div class="is-total">
                        <dt>Total DPP @include('livewire.client.panel.tax-report.partials.info', ['text' => 'Dasar Pengenaan Pajak dari Faktur Keluar'])</dt>
                        <dd>{{ $rp($totalDpp) }}</dd>
                    </div>
                </dl>
            </div>
            <div class="kpt-ledger">
                <p class="kpt-ledger__title">Ringkasan Faktur @include('livewire.client.panel.tax-report.partials.info', ['text' => 'Jumlah total faktur yang dihitung dalam periode ini'])</p>
                <dl>
                    <div>
                        <dt>Faktur Masuk</dt>
                        <dd>{{ $fakturMasukCount }} @if ($fakturMasukExcludedCount > 0)<small>({{ $fakturMasukExcludedCount }} dikecualikan)</small>@endif</dd>
                    </div>
                    <div>
                        <dt>Faktur Keluar</dt>
                        <dd>{{ $fakturKeluarCount }} @if ($fakturKeluarExcludedCount > 0)<small>({{ $fakturKeluarExcludedCount }} dikecualikan)</small>@endif</dd>
                    </div>
                    <div class="is-total"><dt>Total Faktur</dt><dd>{{ $fakturMasukCount + $fakturKeluarCount }}</dd></div>
                </dl>
            </div>
        </div>

        @if ($hasKompensasi)
            <h3 class="kpt-section">Detail Kompensasi @include('livewire.client.panel.tax-report.partials.info', ['text' => 'Informasi kompensasi lebih bayar yang digunakan atau tersedia untuk periode mendatang'])</h3>
            <div class="kpt-ledgers">
                @if ($kompensasiDiterima > 0)
                    <div class="kpt-ledger kpt-ledger--figure">
                        <p class="kpt-ledger__title">Kompensasi Diterima @include('livewire.client.panel.tax-report.partials.info', ['text' => 'Kompensasi lebih bayar dari periode sebelumnya yang digunakan'])</p>
                        <b>{{ $rp($kompensasiDiterima) }}</b>
                        <span>Dari periode sebelumnya</span>
                    </div>
                @endif
                @if ($kompensasiTersedia > 0)
                    <div class="kpt-ledger kpt-ledger--figure">
                        <p class="kpt-ledger__title">Kompensasi Tersedia @include('livewire.client.panel.tax-report.partials.info', ['text' => 'Sisa kompensasi yang dapat digunakan untuk periode berikutnya'])</p>
                        <b>{{ $rp($kompensasiTersedia) }}</b>
                        <span>Dapat digunakan</span>
                    </div>
                @endif
                @if ($kompensasiTerpakai > 0)
                    <div class="kpt-ledger kpt-ledger--figure">
                        <p class="kpt-ledger__title">Kompensasi Terpakai @include('livewire.client.panel.tax-report.partials.info', ['text' => 'Kompensasi yang sudah digunakan di periode berikutnya'])</p>
                        <b>{{ $rp($kompensasiTerpakai) }}</b>
                        <span>Sudah digunakan</span>
                    </div>
                @endif
            </div>
        @endif

        <div class="kpt-note">
            <x-heroicon-o-information-circle class="h-5 w-5" />
            <div>
                <p class="kpt-note__title">Aturan Perhitungan</p>
                <p>Faktur dengan kode 02, 03, 07, 08 dikecualikan · Hitung revisi terbaru · Faktur Masuk yang terkait usaha · Peredaran Bruto berdasarkan DPP</p>
            </div>
        </div>
    </div>

    {{-- Ringkasan Tahunan --}}
    <div class="kpt-ws__body" x-show="tab === 'tahunan'" x-cloak>
        @livewire('client.panel.tax-report.yearly-summary', ['taxReportId' => $taxReportId, 'clientId' => $taxReport->client_id], key('yearly-summary-' . $taxReportId))
    </div>
</section>
