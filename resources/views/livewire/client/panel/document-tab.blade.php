{{--
    Client portal: Dokumen (docs/mockups/client-portal-redesign.html, "Dokumen").
    One card: summary strip, search + status filter, then four sections
    (legal documents, requirement groups, other requirements, extra documents).
    Rows are built in DocumentTab::viewData() and drawn by partials/document-row.
--}}
@php
    use App\Livewire\Client\Panel\DocumentTab;
@endphp

<div class="kpd"
     @if ($openUploadOnLoad)
         x-data
         x-init="setTimeout(() => {
             $dispatch('open-modal', { id: 'upload-document-modal' });
             const url = new URL(window.location.href);
             url.searchParams.delete('unggah');
             window.history.replaceState(window.history.state, '', url);
         }, 60)"
     @endif>

    @if ($clients->isEmpty())
        <div class="kpd-card kpd-empty">
            <span class="kpd-empty__icon"><x-heroicon-o-document-text class="h-5 w-5" /></span>
            <b>Tidak Ada Klien</b>
            <p>Anda belum memiliki akses. Hubungi administrator.</p>
        </div>
    @elseif ($selectedClientId && $currentClient)
        @php $clientId = $currentClient->id; @endphp

        <header class="kpd-head">
            <div>
                <h1 class="kpd-title">Dokumen</h1>
                <p class="kpd-sub">Dokumen legal, persyaratan proyek, dan berkas pendukung perusahaan Anda</p>
            </div>
            <x-filament::button icon="heroicon-m-plus" wire:click="openUploadModal({{ $clientId }}, null, true)">
                Tambah
            </x-filament::button>
        </header>

        <section class="kpd-card">
            {{-- Summary (all documents, not affected by the filters) --}}
            <div class="kpd-stats">
                <p class="kpd-stat"><b>{{ $stats['total'] }}</b> dokumen</p>
                <p class="kpd-stat"><i class="kpd-dot kpd-dot--blue"></i><b>{{ $stats['valid'] }}</b> valid</p>
                <p class="kpd-stat"><i class="kpd-dot kpd-dot--ring"></i><b>{{ $stats['review'] }}</b> review</p>
                <p class="kpd-stat"><i class="kpd-dot kpd-dot--neutral"></i><b>{{ $stats['need'] }}</b> perlu diunggah</p>
                <div class="kpd-meter" title="{{ $stats['pct'] }}% dokumen valid">
                    <span class="kpd-bar"><i style="width: {{ $stats['pct'] }}%"></i></span>
                    <b>{{ $stats['pct'] }}%</b>
                </div>
            </div>

            {{-- Search + status --}}
            <div class="kpd-tools">
                <label class="kpd-search">
                    <x-heroicon-m-magnifying-glass class="h-4 w-4 shrink-0" />
                    <span class="sr-only">Cari dokumen</span>
                    <input type="text" wire:model.live.debounce.300ms="searchQuery" placeholder="Cari nama dokumen..." autocomplete="off">
                    @if ($searchQuery)
                        <button type="button" class="kpd-search__clear" wire:click="$set('searchQuery', '')" title="Hapus pencarian" aria-label="Hapus pencarian">
                            <x-heroicon-m-x-mark class="h-4 w-4" />
                        </button>
                    @endif
                </label>

                <div class="kpd-filter" x-data="{ open: false }" x-on:keydown.escape="open = false" x-on:click.outside="open = false">
                    <button type="button" @class(['kpd-trig', 'is-active' => $statusFilter !== 'all'])
                            x-on:click="open = ! open" x-bind:aria-expanded="open" aria-haspopup="listbox">
                        <span class="kpd-trig__l">Status</span>
                        <span>{{ $statusLabel }}</span>
                        <x-heroicon-m-chevron-down class="h-4 w-4 kpd-trig__chev" />
                    </button>
                    <div class="kpd-pop" x-show="open" x-cloak x-transition.opacity.duration.150ms role="listbox" aria-label="Filter status">
                        @foreach (DocumentTab::STATUS_FILTERS as $key => $label)
                            <button type="button" role="option" aria-selected="{{ $statusFilter === $key ? 'true' : 'false' }}"
                                    @class(['kpd-pop__opt', 'is-on' => $statusFilter === $key])
                                    wire:click="$set('statusFilter', '{{ $key }}')" x-on:click="open = false">
                                <span>{{ $label }}</span>
                                <span class="kpd-pop__n">{{ $filterCounts[$key] }}</span>
                                <x-heroicon-m-check class="h-4 w-4 kpd-pop__check {{ $statusFilter === $key ? '' : 'invisible' }}" />
                            </button>
                        @endforeach
                    </div>
                </div>
            </div>

            @if ($filtering)
                <x-kis.active-filters class="kpd-active" label="Filter">
                    @if (trim($searchQuery) !== '')
                        <x-kis.filter-chip wire:click="$set('searchQuery', '')" remove-label="Hapus pencarian">“{{ $searchQuery }}”</x-kis.filter-chip>
                    @endif
                    @if ($statusFilter !== 'all')
                        <x-kis.filter-chip wire:click="$set('statusFilter', 'all')">Status: {{ $statusLabel }}</x-kis.filter-chip>
                    @endif
                    <x-slot name="reset" wire:click="resetFilters">Reset filter</x-slot>
                </x-kis.active-filters>
            @endif

            {{-- 1. Legal documents --}}
            <div class="kpd-sec">
                <h3 class="kpd-sec__label">Dokumen Legal Wajib</h3>
                <span class="kpd-sec__count">{{ $legalRows->count() }}</span>
            </div>
            <div class="kpd-list">
                @forelse ($legalRows as $row)
                    @include('livewire.client.panel.partials.document-row', ['row' => $row, 'clientId' => $clientId])
                @empty
                    <p class="kpd-empty kpd-empty--inline">
                        <x-heroicon-o-document class="h-4 w-4" />
                        {{ $filtering ? 'Tidak ada dokumen legal yang cocok dengan filter.' : 'Tidak ada dokumen legal wajib' }}
                    </p>
                @endforelse
            </div>

            {{-- 2. Requirement groups --}}
            @if ($groupCount > 0)
                <div class="kpd-sec">
                    <h3 class="kpd-sec__label">Grup Persyaratan</h3>
                    <span class="kpd-sec__count">{{ $groupCount }}</span>
                </div>
                <div class="kpd-list">
                    @forelse ($groupBlocks as $group)
                        <div class="kpd-group" x-data="{ open: true }" wire:key="group-{{ $group['id'] }}">
                            <button type="button" class="kpd-row kpd-row--group" x-on:click="open = ! open" x-bind:aria-expanded="open">
                                <span class="kpd-ft"><x-heroicon-o-rectangle-stack class="h-4 w-4" /></span>
                                <span class="kpd-main">
                                    <span class="kpd-name">
                                        <span>{{ $group['name'] }}</span>
                                        @if ($group['year'])
                                            <x-kis.badge tone="neutral" size="sm">{{ $group['year'] }}</x-kis.badge>
                                        @endif
                                        @if ($group['overdue'])
                                            <x-kis.badge tone="red" size="sm" dot>Terlambat</x-kis.badge>
                                        @endif
                                    </span>
                                    @if ($group['due'])
                                        <span class="kpd-meta"><span @class(['is-red' => $group['overdue']])>Tenggat {{ $group['due'] }}</span></span>
                                    @endif
                                </span>
                                <span class="kpd-status kpd-progress" title="{{ $group['done'] }} dari {{ $group['total'] }} persyaratan terpenuhi">
                                    <span class="kpd-bar"><i style="width: {{ $group['pct'] }}%"></i></span>
                                    <span>{{ $group['done'] }}/{{ $group['total'] }} · {{ $group['pct'] }}%</span>
                                </span>
                                <span class="kpd-acts">
                                    <span class="kpd-ib" aria-hidden="true">
                                        <x-heroicon-m-chevron-down class="h-4 w-4 kpd-chev" x-bind:class="open && 'is-open'" />
                                    </span>
                                    <span class="sr-only" x-text="open ? 'Tutup grup' : 'Buka grup'"></span>
                                </span>
                            </button>

                            <div class="kpd-group__rows" x-show="open">
                                @forelse ($group['rows'] as $row)
                                    @include('livewire.client.panel.partials.document-row', ['row' => $row, 'clientId' => $clientId])
                                @empty
                                    <p class="kpd-empty kpd-empty--inline kpd-empty--child">
                                        <x-heroicon-o-inbox class="h-4 w-4" />
                                        Tidak ada persyaratan dalam grup ini
                                    </p>
                                @endforelse
                            </div>
                        </div>
                    @empty
                        <p class="kpd-empty kpd-empty--inline">
                            <x-heroicon-o-rectangle-stack class="h-4 w-4" />
                            Tidak ada persyaratan yang cocok dengan filter.
                        </p>
                    @endforelse
                </div>
            @endif

            {{-- 3. Requirements outside a group --}}
            @if ($otherRequirementRows->isNotEmpty())
                <div class="kpd-sec">
                    <h3 class="kpd-sec__label">Persyaratan Lainnya</h3>
                    <span class="kpd-sec__count">{{ $otherRequirementRows->count() }}</span>
                </div>
                <div class="kpd-list">
                    @foreach ($otherRequirementRows as $row)
                        @include('livewire.client.panel.partials.document-row', ['row' => $row, 'clientId' => $clientId])
                    @endforeach
                </div>
            @endif

            {{-- 4. Extra documents uploaded by the client --}}
            <div class="kpd-sec">
                <h3 class="kpd-sec__label">Dokumen Lainnya</h3>
                <span class="kpd-sec__count">{{ $additionalRows->count() }}</span>
                <div class="kpd-sec__aside">
                    <button type="button" class="kpd-up kpd-up--plain" wire:click="openUploadModal({{ $clientId }}, null, true)">
                        <x-heroicon-m-plus class="h-4 w-4" />
                        Tambah
                    </button>
                </div>
            </div>
            <div class="kpd-list">
                @forelse ($additionalRows as $row)
                    @include('livewire.client.panel.partials.document-row', ['row' => $row, 'clientId' => $clientId])
                @empty
                    @if ($filtering && $additionalTotal > 0)
                        <p class="kpd-empty kpd-empty--inline">
                            <x-heroicon-o-paper-clip class="h-4 w-4" />
                            Tidak ada dokumen yang cocok dengan filter.
                        </p>
                    @else
                        <div class="kpd-empty">
                            <span class="kpd-empty__icon kpd-empty__icon--dashed"><x-heroicon-o-arrow-up-tray class="h-5 w-5" /></span>
                            <b>Belum ada dokumen tambahan</b>
                            <p>Unggah dokumen pendukung yang mungkin diperlukan</p>
                            <button type="button" class="kpd-up kpd-up--plain mt-3" wire:click="openUploadModal({{ $clientId }}, null, true)">
                                <x-heroicon-m-plus class="h-4 w-4" />
                                Unggah Dokumen Pertama
                            </button>
                        </div>
                    @endif
                @endforelse
            </div>
        </section>
    @endif

    {{-- Upload --}}
    <x-filament::modal id="upload-document-modal" width="xl" footer-actions-alignment="end"
                       :heading="$selectedRequirementId ? 'Unggah untuk Persyaratan' : 'Unggah Dokumen'"
                       description="Pilih file yang akan diunggah">
        <form wire:submit="uploadDocument" id="kpd-upload-form" class="kpd-upload">
            {{ $this->form }}
        </form>

        <x-slot name="footerActions">
            {{-- End-aligned footers are drawn in reverse: the primary action lands on the right. --}}
            <x-filament::button type="submit" form="kpd-upload-form" icon="heroicon-m-arrow-up-tray"
                                wire:loading.attr="disabled" wire:target="uploadDocument">
                <span wire:loading.remove wire:target="uploadDocument">Unggah</span>
                <span wire:loading wire:target="uploadDocument">Mengunggah...</span>
            </x-filament::button>
            <x-filament::button color="gray" wire:click="closeUploadModal">Batal</x-filament::button>
        </x-slot>
    </x-filament::modal>

    {{-- Preview --}}
    <x-filament::modal id="preview-document-modal" width="7xl">
        @if ($preview)
            <div class="kpd-pv" x-data="{ showInfo: window.innerWidth >= 1024 }">
                <div class="kpd-pv__head">
                    <span class="kpd-ft kpd-ft--ok kpd-pv__icon">
                        @if ($preview['kind'] === 'image')
                            <x-heroicon-o-photo class="h-5 w-5" />
                        @else
                            <x-heroicon-o-document-text class="h-5 w-5" />
                        @endif
                    </span>
                    <div class="kpd-pv__title">
                        <h3>{{ $preview['title'] }}</h3>
                        <p>File asli: {{ $preview['filename'] }} · Diunggah {{ $preview['uploadedAgo'] }} oleh {{ $preview['uploader'] }}</p>
                    </div>
                    <div class="kpd-pv__tools">
                        <x-kis.badge :tone="$preview['badge']['tone']" :dot="$preview['badge']['dot']">{{ $preview['badge']['label'] }}</x-kis.badge>
                        <button type="button" class="kpd-up kpd-up--plain" x-on:click="showInfo = ! showInfo" x-bind:aria-pressed="showInfo">
                            <x-heroicon-m-information-circle class="h-4 w-4" />
                            <span x-text="showInfo ? 'Tutup Detail' : 'Detail'">Detail</span>
                        </button>
                        <button type="button" class="kpd-ib" x-on:click="$dispatch('close-modal', { id: 'preview-document-modal' })"
                                wire:click="closePreviewModal" title="Tutup" aria-label="Tutup">
                            <x-heroicon-m-x-mark class="h-5 w-5" />
                        </button>
                    </div>
                </div>

                <div class="kpd-pv__body">
                    <div class="kpd-pv__viewer" x-data="{ failed: false }" wire:key="pv-{{ $preview['id'] }}">
                        @if ($preview['kind'] === 'image' && $preview['url'])
                            <img src="{{ $preview['url'] }}" alt="{{ $preview['title'] }}" x-show="! failed" x-on:error="failed = true">
                        @elseif ($preview['kind'] === 'pdf' && $preview['url'])
                            <iframe src="{{ $preview['url'] }}" title="{{ $preview['title'] }}"></iframe>
                        @endif
                        @if (! in_array($preview['kind'], ['image', 'pdf'], true) || ! $preview['url'] || $preview['kind'] === 'image')
                            <div class="kpd-empty" @if ($preview['kind'] === 'image' && $preview['url']) x-show="failed" x-cloak @endif>
                                <span class="kpd-empty__icon"><x-heroicon-o-document class="h-5 w-5" /></span>
                                <b>{{ $preview['kind'] === 'image' ? 'Gambar tidak dapat dimuat' : 'Pratinjau tidak tersedia untuk tipe file ini' }}</b>
                                <p>Silakan unduh file untuk melihat dokumen lengkap.</p>
                                <x-filament::button class="mt-3" icon="heroicon-m-arrow-down-tray" wire:click="downloadDocument({{ $preview['id'] }})">
                                    Unduh File
                                </x-filament::button>
                            </div>
                        @endif
                    </div>

                    <aside class="kpd-pv__info" x-show="showInfo" x-transition.opacity.duration.150ms>
                        <h4>Informasi Dokumen</h4>
                        <dl>
                            <div>
                                <dt>{{ $preview['label'] }}</dt>
                                <dd><b>{{ $preview['title'] }}</b><small>File asli: {{ $preview['filename'] }}</small></dd>
                            </div>
                            <div>
                                <dt>Diunggah oleh</dt>
                                <dd>{{ $preview['uploader'] }}</dd>
                            </div>
                            <div>
                                <dt>Tanggal unggah</dt>
                                <dd>{{ $preview['uploadedAt'] }}</dd>
                            </div>
                            @if ($preview['number'])
                                <div>
                                    <dt>Nomor dokumen</dt>
                                    <dd>{{ $preview['number'] }}</dd>
                                </div>
                            @endif
                            @if ($preview['expires'])
                                <div>
                                    <dt>Berlaku sampai</dt>
                                    <dd @class(['is-red' => $preview['expired']])>{{ $preview['expires'] }}{{ $preview['expired'] ? ', kedaluwarsa' : '' }}</dd>
                                </div>
                            @endif
                            @if ($preview['note'])
                                <div>
                                    <dt>Catatan</dt>
                                    <dd class="whitespace-pre-line">{{ $preview['note'] }}</dd>
                                </div>
                            @endif
                        </dl>
                        @if ($preview['adminNotes'])
                            <div class="kpd-pv__admin">
                                <span>Catatan Admin</span>
                                <p class="whitespace-pre-line">{{ $preview['adminNotes'] }}</p>
                            </div>
                        @endif
                    </aside>
                </div>
            </div>
        @endif
    </x-filament::modal>

    {{-- Delete --}}
    <x-filament::modal id="confirm-delete-modal" width="md" icon="heroicon-o-trash" icon-color="danger"
                       heading="Konfirmasi Hapus" footer-actions-alignment="end"
                       description="Apakah Anda yakin ingin menghapus dokumen ini? Tindakan ini tidak dapat dibatalkan.">
        <x-slot name="footerActions">
            <x-filament::button color="danger" wire:click="deleteDocument" wire:loading.attr="disabled" wire:target="deleteDocument">Hapus</x-filament::button>
            <x-filament::button color="gray" wire:click="closeDeleteModal">Batal</x-filament::button>
        </x-slot>
    </x-filament::modal>
</div>
