{{-- PPh workspace (TaxReportPph): status, four figures (PPh 21, 23, 4(2), total), the breakdown table. --}}
@php
    use App\Support\TaxPeriod;
    use App\Support\Ui\Tone;

    $rp = fn ($value) => 'Rp ' . number_format((float) $value, 0, ',', '.');
    $rows = [
        ['PPh 21', 'Karyawan', $pph21Total, $pph21Bruto, $pph21Count],
        ['PPh 23', 'Jasa/Dividen', $pph23Total, $pph23Bruto, $pph23Count],
        ['PPh 4(2)', 'Final', $pph42Total, $pph42Bruto, $pph42Count],
    ];
    // Rows whose jenis_pajak is none of the three (bad input) still count in the totals;
    // show them as their own line so the table adds up.
    $other = [
        $totalPph - $pph21Total - $pph23Total - $pph42Total,
        $totalDpp - $pph21Bruto - $pph23Bruto - $pph42Bruto,
        $totalBuktiPotong - $pph21Count - $pph23Count - $pph42Count,
    ];
    $tableRows = $other[2] > 0 ? [...$rows, ['Jenis lain', null, ...$other]] : $rows;
@endphp

<section class="kpt-card kpt-ws">
    @if (! $hasAccess)
        @include('livewire.client.panel.tax-report.partials.empty', [
            'icon' => 'heroicon-o-lock-closed',
            'title' => 'Akses Tidak Tersedia',
            'text' => 'Anda tidak memiliki akses ke data PPh ini.',
        ])
    @elseif (! $taxReport)
        @include('livewire.client.panel.tax-report.partials.empty', [
            'icon' => 'heroicon-o-banknotes',
            'title' => 'Data PPh Tidak Tersedia',
            'text' => 'Belum ada data PPh untuk periode ini.',
        ])
    @else
        <div class="kpt-card__head">
            <div class="min-w-0">
                <h2 class="kpt-card__title">Detail Laporan PPh</h2>
                <p class="kpt-card__sub">Periode {{ $this->periodName }} · {{ $this->clientName }}</p>
            </div>
            <div class="kpt-card__aside">
                <span class="kpt-caption">Status PPh</span>
                <x-kis.badge :tone="Tone::forReportStatus($reportStatus)" dot>
                    {{ $reportStatus }}@if ($reportedAt) · {{ TaxPeriod::date($reportedAt) }}@endif
                </x-kis.badge>
            </div>
        </div>

        <div class="kpt-strip">
            @foreach ($rows as [$label, $kind, $tax, $dpp, $count])
                <div class="kpt-strip__cell">
                    <span class="kpt-label">{{ $label }} <x-kis.badge tone="neutral" size="sm">{{ $kind }}</x-kis.badge></span>
                    <span class="kpt-strip__caption">Pajak Terutang</span>
                    <b class="kpt-strip__value">{{ $rp($tax) }}</b>
                    <span class="kpt-strip__pair"><span>DPP</span><b>{{ $rp($dpp) }}</b></span>
                    <span class="kpt-strip__pair"><span>Bukti Potong</span><b>{{ $count }}</b></span>
                </div>
            @endforeach
            <div class="kpt-strip__cell is-total">
                <span class="kpt-label">Total PPh <x-kis.badge tone="neutral" size="sm">Keseluruhan</x-kis.badge></span>
                <span class="kpt-strip__caption">Total Pajak</span>
                <b class="kpt-strip__value">{{ $rp($totalPph) }}</b>
                <span class="kpt-strip__pair"><span>Total DPP</span><b>{{ $rp($totalDpp) }}</b></span>
                <span class="kpt-strip__pair"><span>Total Bukti Potong</span><b>{{ $totalBuktiPotong }}</b></span>
            </div>
        </div>

        <div class="kpt-ws__body">
            <h3 class="kpt-section">Rincian Perhitungan</h3>
            <div class="kpt-scroll kpt-scroll--boxed">
                <table class="kpt-table">
                    <thead>
                        <tr>
                            <th>Jenis PPh</th>
                            <th class="is-num">DPP</th>
                            <th class="is-num">Pajak Terutang</th>
                            <th class="is-num">Jumlah</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($tableRows as [$label, $kind, $tax, $dpp, $count])
                            <tr>
                                <td class="kpt-strong">{{ $label }}</td>
                                <td @class(['is-num', 'is-zero' => ! $dpp])>{{ $rp($dpp) }}</td>
                                <td @class(['is-num kpt-strong', 'is-zero' => ! $tax])>{{ $rp($tax) }}</td>
                                <td @class(['is-num', 'is-zero' => ! $count])>{{ $count }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr>
                            <td>Total</td>
                            <td class="is-num">{{ $rp($totalDpp) }}</td>
                            <td class="is-num">{{ $rp($totalPph) }}</td>
                            <td class="is-num">{{ $totalBuktiPotong }}</td>
                        </tr>
                    </tfoot>
                </table>
            </div>

            <div class="kpt-note">
                <x-heroicon-o-information-circle class="h-5 w-5" />
                <div>
                    <p class="kpt-note__title">Catatan Perhitungan</p>
                    <p>Data dihitung berdasarkan bukti potong yang telah diinput. PPh 21 untuk karyawan, PPh 23 untuk jasa/dividen, dan PPh 4(2) untuk pajak final.</p>
                </div>
            </div>
        </div>
    @endif
</section>
