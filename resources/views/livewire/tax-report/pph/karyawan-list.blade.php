{{-- Employees of the client (PPh 21 recipients): totals in one strip, then the table. --}}
<div class="kis-ws">
    <div class="kis-sumstrip">
        <div class="kis-sumstrip__cell">
            <span class="kis-sumstrip__label">Total karyawan</span>
            <b class="kis-sumstrip__value">{{ number_format($totalKaryawan, 0, ',', '.') }}</b>
            <span class="kis-sumstrip__meta">Semua status</span>
        </div>
        <div class="kis-sumstrip__cell">
            <span class="kis-sumstrip__label">Aktif</span>
            <b class="kis-sumstrip__value">{{ number_format($activeKaryawan, 0, ',', '.') }}</b>
            <span class="kis-sumstrip__meta">Dihitung untuk PPh 21</span>
        </div>
        <div class="kis-sumstrip__cell">
            <span class="kis-sumstrip__label">Tidak aktif</span>
            <b class="kis-sumstrip__value">{{ number_format($inactiveKaryawan, 0, ',', '.') }}</b>
            <span class="kis-sumstrip__meta">Tidak dihitung</span>
        </div>
        <div class="kis-sumstrip__cell">
            <span class="kis-sumstrip__label">Total gaji</span>
            <b class="kis-sumstrip__value">Rp {{ number_format($totalGaji, 0, ',', '.') }}</b>
            <span class="kis-sumstrip__meta">Karyawan aktif, per bulan</span>
        </div>
    </div>

    {{ $this->table }}
</div>
