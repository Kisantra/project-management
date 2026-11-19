{{-- PPh Badan of the open period: its SPT row only (there are no transactions to list). --}}
<section class="kpt-card">
    <div class="kpt-card__head">
        <div class="min-w-0">
            <h2 class="kpt-card__title">SPT PPh Badan</h2>
            <p class="kpt-card__sub">Masa {{ $label }} · {{ $this->client->name }}</p>
        </div>
        @if ($badan)
            <div class="kpt-card__aside">
                <x-kis.badge :tone="$badan['tone']" dot>{{ $badan['statusLabel'] }}</x-kis.badge>
            </div>
        @endif
    </div>

    @if ($badan)
        <dl class="kpt-facts">
            <div><dt>Status lapor</dt><dd>{{ $badan['statusLabel'] }}</dd></div>
            <div><dt>Tanggal lapor</dt><dd>{{ $badan['reportedOn'] ?? 'Belum ada' }}</dd></div>
            <div><dt>Nomor bukti lapor</dt><dd class="kpt-num">{{ $badan['nomor'] ?: 'Belum ada' }}</dd></div>
            <div>
                <dt>Berkas SPT</dt>
                <dd>
                    @if ($badan['fileUrl'])
                        <span class="kpt-badges">
                            <a href="{{ $badan['fileUrl'] }}" target="_blank" rel="noopener" class="kpt-btn kpt-btn--outline kpt-btn--sm">
                                <x-heroicon-m-arrow-top-right-on-square class="h-4 w-4" /> Buka berkas SPT
                            </a>
                            <a href="{{ $badan['fileUrl'] }}" download class="kpt-btn kpt-btn--outline kpt-btn--sm">
                                <x-heroicon-m-arrow-down-tray class="h-4 w-4" /> Unduh
                            </a>
                        </span>
                    @else
                        Belum diunggah
                    @endif
                </dd>
            </div>
        </dl>
    @else
        @include('livewire.client.panel.tax-report.partials.empty', [
            'icon' => 'heroicon-o-building-office',
            'title' => 'Belum ada data PPh Badan',
            'text' => 'SPT PPh Badan untuk masa ini belum dicatat oleh tim Kisantra.',
        ])
    @endif
</section>
