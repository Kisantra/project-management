{{--
    Laporan Pajak (client portal): the reported SPT list, or the detail of one
    period when ?masa= is set. docs/mockups/client-portal-redesign.html
--}}
<div class="kpt" x-data x-on:spt-detail-opened.window="window.scrollTo({ top: 0, behavior: 'smooth' })">
    @if (! $this->client)
        <div class="kpt-card">
            @include('livewire.client.panel.tax-report.partials.empty', [
                'icon' => 'heroicon-o-document-chart-bar',
                'title' => 'Tidak Ada Data Laporan Pajak',
                'text' => 'Anda belum memiliki akses ke laporan pajak manapun. Hubungi administrator untuk informasi lebih lanjut.',
            ])
        </div>
    @elseif ($this->report)
        @include('livewire.client.panel.tax-report.partials.detail', ['detail' => $this->detail, 'report' => $this->report])
    @else
        @include('livewire.client.panel.tax-report.partials.spt-list')
    @endif
</div>
