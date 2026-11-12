{{--
    SPT of a tax type: status and details on the left, the file on the right.
    Uploading marks the type Sudah Lapor & Sudah Bayar and notifies the client.
--}}
<div class="kis-spt-list">
    @unless ($this->type)
        <div>
            <h3 class="kis-card__title">SPT Masa {{ $this->periodLabel() }}</h3>
            <p class="kis-card__sub">Unggah SPT/BPE dari Coretax untuk tiap jenis pajak yang dikontrak. Setelah diunggah, jenis itu ditandai sudah lapor dan sudah bayar.</p>
        </div>
    @endunless

    @forelse ($this->rows as $row)
        <section class="kis-card kis-spt" wire:key="spt-{{ $row['type'] }}">
            <div class="kis-spt__info">
                <div>
                    <span class="kis-spt__eyebrow">SPT Masa {{ $row['label'] }}</span>
                    <h3 class="kis-spt__title">{{ $this->periodLabel() }}</h3>
                </div>

                <div class="kis-spt__badges">
                    @if ($row['noActivity'])
                        <x-kis.badge tone="neutral" dot>Tidak ada aktivitas</x-kis.badge>
                    @else
                        <x-kis.badge :tone="$row['reported'] ? 'blue' : 'red'" dot>{{ $row['reported'] ? 'Sudah lapor' : 'Belum lapor' }}</x-kis.badge>
                        <x-kis.badge :tone="$row['paid'] ? 'blue' : 'red'" dot>{{ $row['paid'] ? 'Sudah bayar' : 'Belum bayar' }}</x-kis.badge>
                    @endif
                </div>

                <dl class="kis-kv kis-spt__kv">
                    <div><dt>Tanggal lapor</dt><dd>{{ $row['reportedAt'] ?? '–' }}</dd></div>
                    <div><dt>Tanggal bayar</dt><dd>{{ $row['paidAt'] ?? '–' }}</dd></div>
                    <div><dt>Nomor bukti (NTTE / BPE)</dt><dd>{{ $row['nomor'] ?: '–' }}</dd></div>
                </dl>

                @if ($row['fileUrl'])
                    <div class="kis-spt__acts">
                        <x-filament::button tag="a" href="{{ $row['fileUrl'] }}" target="_blank" size="sm" color="gray" icon="heroicon-m-arrow-top-right-on-square">Buka</x-filament::button>
                        <x-filament::button size="sm" color="gray" icon="heroicon-m-arrow-up-tray" wire:click="mountAction('uploadSpt', { type: '{{ $row['type'] }}' })">Ganti</x-filament::button>
                        <x-filament::button size="sm" color="danger" outlined icon="heroicon-m-trash" wire:click="mountAction('removeSpt', { type: '{{ $row['type'] }}' })">Hapus</x-filament::button>
                    </div>
                @else
                    <p class="kis-spt__note">Mengunggah SPT menandai {{ $row['label'] }} sudah lapor dan sudah bayar, lalu memberi tahu klien lewat notifikasi.</p>
                    <div class="kis-spt__acts">
                        <x-filament::button icon="heroicon-m-arrow-up-tray" wire:click="mountAction('uploadSpt', { type: '{{ $row['type'] }}' })">Unggah SPT</x-filament::button>
                    </div>
                @endif
            </div>

            <div class="kis-spt__file">
                @if ($row['fileUrl'])
                    @if ($row['isImage'])
                        <div class="kis-spt__image"><img src="{{ $row['fileUrl'] }}" alt="SPT {{ $row['label'] }} {{ $this->periodLabel() }}"></div>
                    @else
                        <iframe src="{{ $row['fileUrl'] }}#toolbar=1&view=FitH" title="SPT {{ $row['label'] }} {{ $this->periodLabel() }}" loading="lazy"></iframe>
                    @endif
                @else
                    <button type="button" class="kis-spt__drop" wire:click="mountAction('uploadSpt', { type: '{{ $row['type'] }}' })">
                        <x-heroicon-o-document-arrow-up class="h-7 w-7" />
                        <b>Belum ada berkas SPT</b>
                        <span>PDF atau gambar bukti lapor dari Coretax, maks. 10 MB</span>
                    </button>
                @endif
            </div>
        </section>
    @empty
        <div class="kis-card kis-tbl__empty">Klien ini belum punya kontrak untuk jenis pajak ini, jadi belum ada SPT yang perlu diunggah.</div>
    @endforelse

    <x-filament-actions::modals />
</div>
