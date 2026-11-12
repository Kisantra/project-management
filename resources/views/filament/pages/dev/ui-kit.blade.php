@php
    use App\Support\Ui\Tone;

    $sopLabels = collect($this->getSopOptions())->pluck('label', 'value');
    $monthNames = ['', 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
@endphp

<x-filament-panels::page>
    <div class="space-y-6">
        <x-filament::section heading="Badge" description="Tiga nada status: biru, netral, merah, ditambah garis biru untuk status menunggu.">
            <div class="flex flex-wrap items-center gap-2">
                <x-kis.badge tone="blue" dot>In Progress</x-kis.badge>
                <x-kis.badge tone="outline" dot>Menunggu Klien</x-kis.badge>
                <x-kis.badge tone="neutral" dot>Draft</x-kis.badge>
                <x-kis.badge :tone="Tone::forPaymentStatus('Kurang Bayar')" dot>Kurang Bayar</x-kis.badge>
                <x-kis.badge :tone="Tone::forPaymentStatus('Lebih Bayar')" dot>Lebih Bayar</x-kis.badge>
                <x-kis.badge :tone="Tone::forPaymentStatus('Nihil')" dot>Nihil</x-kis.badge>
                <x-kis.badge tone="neutral">Pribadi</x-kis.badge>
                <x-kis.badge tone="outline">PKP</x-kis.badge>
                <x-kis.badge tone="neutral">Tax</x-kis.badge>
                <x-kis.badge tone="blue" size="sm">✓ 28 Okt</x-kis.badge>
                <x-kis.badge :tone="Tone::forReportStatus('Belum Lapor')" size="sm">Belum</x-kis.badge>
            </div>
        </x-filament::section>

        <x-filament::section heading="Filter SOP" description="Satu baris; yang tidak muat masuk ke +N lainnya. Pilihan dari daftar tetap terlihat di ujung baris.">
            <x-kis.filter-rail
                wire:model.live="sop"
                label="SOP"
                :all-count="139"
                :options="$this->getSopOptions()"
                search-placeholder="Cari SOP…"
            />
            <p class="mt-3 text-xs font-semibold text-gray-500" data-test="sop-state">
                Nilai Livewire: <code>{{ json_encode($sop) }}</code>
            </p>
        </x-filament::section>

        <x-filament::section heading="Filter periode dan filter aktif">
            <div class="flex flex-wrap items-center gap-2">
                <x-kis.month-picker wire:model.live="period" :counts="$this->getMonthCounts()" :now="\Illuminate\Support\Carbon::create(2026, 11, 11)" />
            </div>

            <x-kis.active-filters class="mt-3">
                @if (! empty($period['months']))
                    <x-kis.filter-chip wire:click="clearPeriod">Periode: {{ count($period['months']) }} bulan · {{ $period['year'] }}</x-kis.filter-chip>
                @endif
                @if ($sop)
                    <x-kis.filter-chip wire:click="$set('sop', null)">SOP: {{ $sopLabels[$sop] ?? $sop }}</x-kis.filter-chip>
                @endif
                <x-kis.filter-chip>Kontrak Aktif</x-kis.filter-chip>
                <x-slot name="reset" wire:click="resetFilters">Reset semua</x-slot>
            </x-kis.active-filters>

            <p class="mt-3 text-xs font-semibold text-gray-500" data-test="period-state">
                Nilai Livewire: <code>{{ json_encode($period) }}</code>
            </p>
        </x-filament::section>

        <x-filament::section heading="Baris kelompok" description="Untuk tabel custom (Laporan Pajak, Projects).">
            <div class="-mx-6 -mb-6 overflow-hidden border-t border-gray-200 dark:border-white/10">
                <table class="w-full text-sm">
                    <tbody>
                        <x-kis.group-row :colspan="3" variant="section" label="Not started" />
                        <x-kis.group-row :colspan="3" label="Draft" :count="65" />
                        <tr class="border-b border-gray-200 dark:border-white/10"><td class="px-5 py-3 font-bold">Lapor SPT Tahunan Badan 2025</td><td class="px-3"><x-kis.badge tone="neutral" dot>Draft</x-kis.badge></td><td class="px-5 text-gray-500">PT Kinara Sadayatra</td></tr>
                        <x-kis.group-row :colspan="3" label="Oktober 2026" :count="2" count-label="laporan">
                            <x-kis.badge tone="blue" size="sm">Bulan ini</x-kis.badge>
                            <x-slot name="aside"><span class="kis-group-row__alert">1 belum lapor</span></x-slot>
                        </x-kis.group-row>
                        <tr><td class="px-5 py-3 font-bold">PT Sinar Abadi Logistik</td><td class="px-3"><x-kis.badge tone="red" dot>Kurang Bayar</x-kis.badge></td><td class="px-5 text-gray-500">Rp 62.769.500</td></tr>
                    </tbody>
                </table>
            </div>
        </x-filament::section>
    </div>
</x-filament-panels::page>
