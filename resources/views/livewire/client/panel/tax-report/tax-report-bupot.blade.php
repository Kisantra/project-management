{{-- PPh Unifikasi workspace (TaxReportBupot): status, count and total, the list of bukti potong. --}}
@php
    use App\Support\TaxPeriod;
    use App\Support\Ui\Tone;

    $rp = fn ($value) => 'Rp ' . number_format((float) $value, 0, ',', '.');
@endphp

<section class="kpt-card kpt-ws">
    @if (! $hasAccess)
        @include('livewire.client.panel.tax-report.partials.empty', [
            'icon' => 'heroicon-o-lock-closed',
            'title' => 'Akses Tidak Tersedia',
            'text' => 'Anda tidak memiliki akses ke data Bupot ini.',
        ])
    @elseif (! $taxReport)
        @include('livewire.client.panel.tax-report.partials.empty', [
            'icon' => 'heroicon-o-receipt-percent',
            'title' => 'Data Bupot Tidak Tersedia',
            'text' => 'Belum ada data Bupot untuk periode ini.',
        ])
    @else
        <div class="kpt-card__head">
            <div class="min-w-0">
                <h2 class="kpt-card__title">Detail PPh Unifikasi (Bupot)</h2>
                <p class="kpt-card__sub">Periode {{ $this->periodName }} · {{ $this->clientName }}</p>
            </div>
            <div class="kpt-card__aside">
                <span class="kpt-caption">Status Bupot</span>
                <x-kis.badge :tone="Tone::forReportStatus($reportStatus)" dot>
                    {{ $reportStatus }}@if ($reportedAt) · {{ TaxPeriod::date($reportedAt) }}@endif
                </x-kis.badge>
            </div>
        </div>

        <div class="kpt-strip kpt-strip--2">
            <div class="kpt-strip__cell">
                <span class="kpt-label">Total Jumlah Bupot</span>
                <b class="kpt-strip__value">{{ number_format($bupotCount, 0, ',', '.') }}</b>
            </div>
            <div class="kpt-strip__cell">
                <span class="kpt-label">Total Nominal Bupot</span>
                <b class="kpt-strip__value">{{ $rp($totalBupotAmount) }}</b>
            </div>
        </div>

        <div class="kpt-ws__body">
            <h3 class="kpt-section">Daftar Bukti Potong</h3>
            @if (count($bupots) > 0)
                <div class="kpt-scroll kpt-scroll--boxed">
                    <table class="kpt-table">
                        <thead>
                            <tr>
                                <th>Nama Perusahaan</th>
                                <th>NPWP</th>
                                <th>Tipe Bupot</th>
                                <th>Jenis PPh</th>
                                <th>Masa Pajak</th>
                                <th class="is-num">DPP</th>
                                <th class="is-num">Jumlah PPh</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($bupots as $bupot)
                                <tr>
                                    <td class="kpt-strong kpt-wrap">{{ $bupot['company_name'] ?? '-' }}</td>
                                    <td class="kpt-num">{{ $bupot['npwp'] ?? '-' }}</td>
                                    <td><x-kis.badge tone="neutral" size="sm">{{ $bupot['bupot_type'] ?? '-' }}</x-kis.badge></td>
                                    <td><x-kis.badge tone="neutral" size="sm">{{ $bupot['pph_type'] ?? '-' }}</x-kis.badge></td>
                                    <td>{{ $bupot['tax_period'] ?? '-' }}</td>
                                    <td class="is-num">{{ $rp($bupot['dpp'] ?? 0) }}</td>
                                    <td class="is-num kpt-strong">{{ $rp($bupot['bupot_amount'] ?? 0) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot>
                            <tr>
                                <td colspan="6">Total</td>
                                <td class="is-num">{{ $rp($totalBupotAmount) }}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            @else
                <div class="kpt-scroll--boxed">
                    @include('livewire.client.panel.tax-report.partials.empty', [
                        'icon' => 'heroicon-o-document-text',
                        'title' => 'Belum ada bukti potong',
                        'text' => 'Belum ada data bukti potong untuk periode ini.',
                    ])
                </div>
            @endif

            <div class="kpt-note">
                <x-heroicon-o-information-circle class="h-5 w-5" />
                <div>
                    <p class="kpt-note__title">Catatan</p>
                    <p>Data bupot PPh Unifikasi yang ditampilkan adalah bukti potong yang telah diinput oleh tim kami untuk periode pelaporan ini.</p>
                </div>
            </div>
        </div>
    @endif
</section>
