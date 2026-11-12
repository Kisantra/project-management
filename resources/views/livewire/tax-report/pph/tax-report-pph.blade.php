{{--
    PPh workspace. Sub-tab in ?tab= (kisUrlTab). The "Ringkasan tahunan",
    "Catatan" and "Riwayat" tabs only said "akan tersedia" and are left out
    until those features exist.
--}}
@php
    use App\Livewire\TaxReport\Pph\TaxReportPph;
    use App\Services\TaxReport\ReportOverview;

    $b = $this->breakdown;
    $rp = fn (float $amount) => ReportOverview::rupiah($amount);
@endphp

<div class="kis-ws" x-data="kisUrlTab({ param: 'tab', value: @js($initialTab), fallback: 'daftar', allowed: @js(TaxReportPph::TABS) })">
    <div class="kis-subtabs">
        <nav class="kis-subtabs__nav" role="tablist" aria-label="Bagian PPh">
            <button type="button" role="tab" class="kis-subtab" x-bind:class="tab === 'daftar' && 'is-on'" x-bind:aria-selected="tab === 'daftar'" x-on:click="show('daftar')">
                PPh 21 <i>{{ number_format($b['pph21']['count'], 0, ',', '.') }}</i>
            </button>
            <button type="button" role="tab" class="kis-subtab" x-bind:class="tab === 'perhitungan' && 'is-on'" x-bind:aria-selected="tab === 'perhitungan'" x-on:click="show('perhitungan')">
                Perhitungan
            </button>
            <button type="button" role="tab" class="kis-subtab" x-bind:class="tab === 'karyawan' && 'is-on'" x-bind:aria-selected="tab === 'karyawan'" x-on:click="show('karyawan')">
                Karyawan <i>{{ number_format($this->activeEmployees, 0, ',', '.') }}</i>
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
        <span class="kis-subtabs__ctx">PPh · {{ $this->periodLabel() }}</span>
    </div>

    {{-- PPh 21 --}}
    <div x-show="tab === 'daftar'" @style(['display: none' => $initialTab !== 'daftar'])>
        @livewire('tax-report.pph.pph-tax-list', ['taxReportId' => $taxReportId], key('pph21-' . $taxReportId))
    </div>

    {{-- Perhitungan --}}
    <div x-show="tab === 'perhitungan'" @style(['display: none' => $initialTab !== 'perhitungan'])>
        <section class="kis-card kis-tbl">
            <div class="kis-card__head">
                <div>
                    <h3 class="kis-card__title">Perhitungan PPh {{ $this->periodLabel() }}</h3>
                    <p class="kis-card__sub">Langsung dari data bukti potong, selalu terbaru.</p>
                </div>
            </div>
            @if ($b['rows'] === [])
                <p class="kis-tbl__empty"><b>Belum ada data PPh.</b> Impor bukti potong PPh 21 di tab PPh 21.</p>
            @else
                <table>
                    <thead>
                        <tr><th>Jenis</th><th class="is-num">Bukti potong</th><th class="is-num">DPP</th><th class="is-num">PPh</th></tr>
                    </thead>
                    <tbody>
                        @foreach ($b['rows'] as $row)
                            <tr>
                                <td>
                                    <span @class(['kis-tbl__primary', 'is-bad' => ! $row['known']])>{{ $row['label'] }}</span>
                                    @unless ($row['known'])
                                        <span class="kis-tbl__meta is-bad">Kolom jenis pajak berisi nilai yang tidak dikenali. Periksa berkas impornya.</span>
                                    @endunless
                                    @if (in_array($row['label'], ['PPh 23', 'PPh 4(2)'], true))
                                        <span class="kis-tbl__meta">Dilapor lewat PPh Unifikasi</span>
                                    @endif
                                </td>
                                <td class="is-num">{{ number_format($row['count'], 0, ',', '.') }}</td>
                                <td class="is-num">{{ $rp($row['dpp']) }}</td>
                                <td class="is-num"><b>{{ $rp($row['pph']) }}</b></td>
                            </tr>
                        @endforeach
                    </tbody>
                    @if (count($b['rows']) > 1)
                        <tfoot>
                            <tr class="kis-tbl__total">
                                <td>Total</td>
                                <td class="is-num">{{ number_format($b['total']['count'], 0, ',', '.') }}</td>
                                <td class="is-num">{{ $rp($b['total']['dpp']) }}</td>
                                <td class="is-num"><b>{{ $rp($b['total']['pph']) }}</b></td>
                            </tr>
                        </tfoot>
                    @endif
                </table>
            @endif
        </section>
    </div>

    {{-- Karyawan --}}
    <div x-show="tab === 'karyawan'" @style(['display: none' => $initialTab !== 'karyawan'])>
        @livewire('tax-report.pph.karyawan-list', ['clientId' => $this->taxReport->client_id, 'taxReportId' => $taxReportId], key('karyawan-' . $taxReportId))
    </div>

    {{-- SPT --}}
    <div x-show="tab === 'spt'" @style(['display: none' => $initialTab !== 'spt'])>
        @livewire('tax-report.spt-upload', ['taxReportId' => $taxReportId, 'type' => 'pph'], key('spt-pph-' . $taxReportId))
    </div>
</div>
