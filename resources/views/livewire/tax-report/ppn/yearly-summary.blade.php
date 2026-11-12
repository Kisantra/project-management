{{--
    PPN for the whole year: totals, saldo per month (kurang bayar up in red,
    lebih bayar down in blue) and one row per month with its stored summary.
--}}
@php
    use App\Support\Ui\Tone;

    $data = $this->year;
    $t = $data['totals'];
    $rp = fn ($amount) => 'Rp ' . number_format(abs((float) $amount), 0, ',', '.');
    $statusText = fn (?string $status) => $status ? ucfirst(mb_strtolower($status)) : 'Belum dihitung';
    $peak = max(array_merge($data['chart']['up'], $data['chart']['down'], [0]));
    [$divisor, $unit] = $peak >= 1e9 ? [1e9, 'miliar'] : [1e6, 'juta'];
@endphp

<div class="kis-year">
    <div class="kis-year__head">
        <div>
            <h3 class="kis-card__title">PPN tahun {{ $currentYear }}</h3>
            <p class="kis-card__sub">{{ $t['withReports'] }} dari 12 bulan punya laporan</p>
        </div>
        <div class="kis-year__acts">
            <x-filament::button color="gray" size="sm" icon="heroicon-m-arrow-down-tray" wire:click="exportYearlyReportPdf" wire:loading.attr="disabled" wire:target="exportYearlyReportPdf">
                Unduh PDF
            </x-filament::button>
            <x-filament::button color="gray" size="sm" icon="heroicon-m-arrow-down-tray" wire:click="exportYearlyReport" wire:loading.attr="disabled" wire:target="exportYearlyReport">
                Unduh Excel
            </x-filament::button>
        </div>
    </div>

    <div class="kis-sumstrip">
        <div class="kis-sumstrip__cell">
            <span class="kis-sumstrip__label">Total kurang bayar</span>
            <b @class(['kis-sumstrip__value', 'is-bad' => $t['kurang'] > 0])>{{ $rp($t['kurang']) }}</b>
            <span class="kis-sumstrip__meta">{{ $t['kurangMonths'] }} bulan</span>
        </div>
        <div class="kis-sumstrip__cell">
            <span class="kis-sumstrip__label">Total lebih bayar</span>
            <b class="kis-sumstrip__value">{{ $rp($t['lebih']) }}</b>
            <span class="kis-sumstrip__meta">{{ $t['lebihMonths'] }} bulan</span>
        </div>
        <div class="kis-sumstrip__cell">
            <span class="kis-sumstrip__label">Peredaran bruto</span>
            <b class="kis-sumstrip__value">{{ $rp($t['bruto']) }}</b>
            <span class="kis-sumstrip__meta">DPP faktur keluaran yang dihitung</span>
        </div>
        <div class="kis-sumstrip__cell">
            <span class="kis-sumstrip__label">Sudah lapor</span>
            <b class="kis-sumstrip__value">{{ $t['reported'] }} <small>/ {{ $t['withReports'] }}</small></b>
            <span @class(['kis-sumstrip__meta', 'is-bad' => $t['withReports'] - $t['reported'] > 0])>
                {{ $t['withReports'] - $t['reported'] > 0 ? ($t['withReports'] - $t['reported']) . ' bulan belum lapor' : 'Semua bulan sudah lapor' }}
            </span>
        </div>
    </div>

    <section class="kis-card kis-tbl">
        <div class="kis-card__head">
            <div>
                <h3 class="kis-card__title">Saldo PPN per bulan</h3>
                <p class="kis-card__sub">Saldo akhir setelah kompensasi, Rp {{ $unit }}</p>
            </div>
            <div class="kis-legend">
                <span><i style="background: rgb(var(--danger-500))"></i>Kurang bayar</span>
                <span><i style="background: var(--kis-ramp-b3)"></i>Lebih bayar</span>
            </div>
        </div>
        @if ($peak > 0)
            <div class="kis-year__chart">
                <x-kis.chart.diverging
                    :labels="$data['chart']['labels']" :titles="$data['chart']['titles']"
                    :up="$data['chart']['up']" :down="$data['chart']['down']"
                    up-label="Kurang bayar" down-label="Lebih bayar"
                    :divisor="$divisor" :unit="$unit"
                    label="Saldo PPN per bulan tahun {{ $currentYear }}" min-height="9rem"
                />
            </div>
        @endif

        <table>
            <thead>
                <tr>
                    <th>Bulan</th>
                    <th class="is-num">PPN masukan</th>
                    <th class="is-num">PPN keluaran</th>
                    <th class="is-num">Kompensasi</th>
                    <th class="is-num">Saldo akhir</th>
                    <th>Status</th>
                    <th>Lapor</th>
                    <th><span class="sr-only">Buka</span></th>
                </tr>
            </thead>
            <tbody>
                @foreach ($data['rows'] as $row)
                    <tr wire:key="year-{{ $row['number'] }}" @class(['is-current' => $row['current'], 'is-empty' => ! $row['hasReport']])>
                        <td>
                            <span class="kis-tbl__primary">{{ $row['name'] }}</span>
                            @if ($row['current'])
                                <span class="kis-tbl__meta">Bulan ini</span>
                            @endif
                        </td>
                        @if (! $row['hasReport'])
                            <td colspan="6" class="kis-tbl__muted">Belum ada laporan</td>
                            <td></td>
                        @elseif (! $row['summary'])
                            <td colspan="6" class="kis-tbl__muted">Belum dihitung</td>
                            <td class="kis-tbl__go">@unless ($row['current'])<a href="{{ $row['url'] }}" x-data x-on:click="kisFollow($event, $el)">Buka →</a>@endunless</td>
                        @else
                            <td class="is-num">{{ $rp($row['masuk']) }}</td>
                            <td class="is-num">{{ $rp($row['keluar']) }}</td>
                            <td class="is-num">
                                {{ $rp($row['received']) }}
                                @if ($row['given'] > 0)
                                    <span class="kis-tbl__meta">diberikan {{ $rp($row['given']) }}</span>
                                @endif
                            </td>
                            <td class="is-num"><b @class(['is-bad' => $row['status'] === 'Kurang Bayar'])>{{ $rp($row['saldo']) }}</b></td>
                            <td><x-kis.badge :tone="Tone::forPaymentStatus($row['status'])" dot size="sm">{{ $statusText($row['status']) }}</x-kis.badge></td>
                            <td>
                                @if ($row['reported'])
                                    <x-kis.badge tone="blue" size="sm">✓ {{ $row['reportedAt'] ?? 'Lapor' }}</x-kis.badge>
                                @else
                                    <x-kis.badge tone="red" size="sm">Belum</x-kis.badge>
                                @endif
                            </td>
                            <td class="kis-tbl__go">@unless ($row['current'])<a href="{{ $row['url'] }}" x-data x-on:click="kisFollow($event, $el)">Buka →</a>@endunless</td>
                        @endif
                    </tr>
                @endforeach
            </tbody>
        </table>
    </section>
</div>
