{{-- Riwayat surat & berita acara untuk satu klien (tab di halaman detail klien). --}}
@php
    $statuses = \App\Models\Letter::STATUSES;
    $categories = \App\Models\LetterTemplate::CATEGORIES;
    $rowGrid = 'grid-cols-[2.25rem_minmax(0,1fr)_auto] lg:grid-cols-[2.25rem_11rem_minmax(0,1fr)_11rem_10rem_9rem_1.5rem]';
@endphp
<div class="space-y-4">
    {{-- Header: counts + create --}}
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div class="flex flex-wrap items-center gap-1.5" role="group" aria-label="Filter status surat">
            @foreach (['' => 'Semua'] + $statuses as $key => $label)
                @php $on = $statusFilter === $key; $n = $counts[$key] ?? 0; @endphp
                @if ($key === '' || $n > 0 || $on)
                    <button type="button" wire:click="$set('statusFilter', '{{ $key }}')"
                            @class([
                                'inline-flex h-7 items-center gap-1.5 rounded-full px-2.5 text-xs font-medium ring-1 ring-inset transition-colors',
                                'bg-primary-50 text-primary-700 ring-primary-600/30 dark:bg-primary-400/10 dark:text-primary-300 dark:ring-primary-400/30' => $on,
                                'bg-white text-gray-600 ring-gray-200 hover:text-gray-900 dark:bg-white/5 dark:text-gray-400 dark:ring-white/10' => ! $on,
                            ])>
                        {{ $label }}
                        <span class="tabular-nums {{ $on ? 'text-primary-600/80' : 'text-gray-400' }}">{{ $n }}</span>
                    </button>
                @endif
            @endforeach
        </div>

        @if ($this->canCreate)
            <x-filament::button size="sm" icon="heroicon-m-plus" x-on:click="$dispatch('open-modal', { id: 'buat-surat-klien' })">
                Buat surat
            </x-filament::button>
        @endif
    </div>

    {{-- List --}}
    <div class="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
        @if ($letters->isNotEmpty())
            <div class="hidden {{ $rowGrid }} items-center gap-3 border-b border-gray-100 bg-gray-50/70 px-4 py-2 text-[11px] font-semibold uppercase tracking-wide text-gray-500 dark:border-white/5 dark:bg-white/[.03] dark:text-gray-400 lg:grid">
                <span></span>
                <span>Nomor</span>
                <span>Perihal</span>
                <span>Tanda tangan</span>
                <span>Dibuat</span>
                <span>Status</span>
                <span></span>
            </div>
        @endif

        @forelse ($letters as $letter)
            @php $slot = $letter->currentSignature(); $sigs = $letter->signatures; @endphp
            <a href="{{ \App\Filament\Pages\Letters\Show::getUrl(['record' => $letter]) }}" wire:key="cl-ltr-{{ $letter->id }}"
               class="group grid {{ $rowGrid }} items-center gap-3 border-b border-gray-100 px-4 py-3 transition-colors last:border-b-0 hover:bg-gray-50 dark:border-white/5 dark:hover:bg-white/5">
                <span class="flex h-9 w-9 items-center justify-center rounded-lg bg-gray-100 text-gray-500 transition-colors group-hover:bg-primary-50 group-hover:text-primary-600 dark:bg-white/5 dark:text-gray-400 dark:group-hover:bg-primary-400/10 dark:group-hover:text-primary-300">
                    @svg($letter->template->icon ?: 'heroicon-o-document-text', 'h-4 w-4')
                </span>

                <span class="hidden lg:block">
                    @if ($letter->number)
                        <span class="font-mono text-xs font-medium text-gray-700 dark:text-gray-200">{{ $letter->number }}</span>
                    @else
                        <span class="text-xs text-gray-400 dark:text-gray-500">Belum bernomor</span>
                    @endif
                </span>

                <span class="min-w-0">
                    <span class="block truncate text-sm font-medium text-gray-900 dark:text-white">{{ $letter->subject }}</span>
                    <span class="block truncate text-xs text-gray-500 dark:text-gray-400">
                        {{ $categories[$letter->template->category] ?? $letter->template->category }}
                        @if ($letter->project)
                            <span class="mx-1 text-gray-300 dark:text-gray-600">&middot;</span>{{ $letter->project->name }}
                        @endif
                        <span class="lg:hidden">
                            <span class="mx-1 text-gray-300 dark:text-gray-600">&middot;</span>{{ $letter->number ?? 'Belum bernomor' }}
                            <span class="mx-1 text-gray-300 dark:text-gray-600">&middot;</span>{{ $letter->created_at->locale('id')->translatedFormat('d M') }}
                        </span>
                        @if ($letter->requestedDocuments->isNotEmpty())
                            @php $rd = $letter->requestedDocuments; $got = $rd->where('is_received', true)->count(); @endphp
                            <span class="mx-1 text-gray-300 dark:text-gray-600">&middot;</span>
                            <span class="{{ $got === $rd->count() ? 'text-success-600 dark:text-success-400' : '' }}">dokumen {{ $got }}/{{ $rd->count() }}</span>
                        @endif
                    </span>
                </span>

                <span class="hidden lg:block">
                    @if ($sigs->isEmpty())
                        <span class="text-xs text-gray-400 dark:text-gray-500">{{ collect($letter->template->signers)->pluck('label')->join(' › ') ?: '—' }}</span>
                    @else
                        <span class="flex items-center gap-1.5" title="{{ $sigs->map(fn ($s) => $s->label . ': ' . ($s->isSigned() ? 'sudah' : ($s->status === 'rejected' ? 'ditolak' : 'menunggu')))->join(', ') }}">
                            @foreach ($sigs as $sig)
                                <span @class([
                                    'h-2.5 w-2.5 rounded-full ring-2',
                                    'bg-success-500 ring-success-500/30' => $sig->isSigned(),
                                    'bg-danger-500 ring-danger-500/30' => $sig->status === 'rejected',
                                    'bg-warning-400 ring-warning-400/40' => $sig->isPending() && $slot?->id === $sig->id,
                                    'bg-gray-200 ring-transparent dark:bg-white/10' => $sig->isPending() && $slot?->id !== $sig->id,
                                ])></span>
                            @endforeach
                            <span class="ml-1 truncate text-xs text-gray-500 dark:text-gray-400">
                                @if ($slot) menunggu {{ $slot->label }} @elseif ($letter->status === 'signed') lengkap @elseif ($letter->status === 'rejected') ditolak @endif
                            </span>
                        </span>
                    @endif
                </span>

                <span class="hidden min-w-0 lg:block">
                    <span class="block truncate text-xs text-gray-700 dark:text-gray-200">{{ $letter->created_by === auth()->id() ? 'Anda' : $letter->creator?->name }}</span>
                    <span class="block text-[11px] text-gray-400 dark:text-gray-500">{{ $letter->created_at->locale('id')->translatedFormat('d M Y') }}</span>
                </span>

                <span class="flex justify-end lg:justify-start">
                    <x-filament::badge :color="$letter->status_color">{{ $letter->status_label }}</x-filament::badge>
                </span>

                <x-heroicon-m-chevron-right class="hidden h-4 w-4 text-gray-300 transition-colors group-hover:text-gray-500 dark:text-gray-600 lg:block" />
            </a>
        @empty
            <div class="px-4 py-12 text-center">
                <x-heroicon-o-envelope class="mx-auto h-7 w-7 text-gray-400" />
                <p class="mt-2 text-sm font-medium text-gray-900 dark:text-white">
                    {{ $statusFilter ? 'Tidak ada surat dengan status ini' : 'Belum ada surat untuk klien ini' }}
                </p>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    Surat permintaan dokumen, tanggapan SP2DK, berita acara, dan lainnya akan tercatat di sini.
                </p>
                @if ($this->canCreate && ! $statusFilter)
                    <x-filament::button size="sm" color="gray" class="mt-4" x-on:click="$dispatch('open-modal', { id: 'buat-surat-klien' })">
                        Buat surat pertama
                    </x-filament::button>
                @endif
            </div>
        @endforelse
    </div>

    @if ($letters->hasPages())
        <div>{{ $letters->links() }}</div>
    @endif

    {{-- Template picker --}}
    @if ($this->canCreate)
        <x-filament::modal id="buat-surat-klien" width="3xl">
            <x-slot name="heading">Buat surat untuk {{ $client->name }}</x-slot>
            <x-slot name="description">Pilih template. Draft dibuat langsung dan Anda diarahkan ke halaman pengisian.</x-slot>

            <div class="space-y-5">
                @foreach ($templates as $category => $items)
                    <div>
                        <p class="mb-2 text-[11px] font-semibold uppercase tracking-wide text-gray-400 dark:text-gray-500">{{ $categories[$category] ?? ucfirst($category) }}</p>
                        <div class="grid gap-2 sm:grid-cols-2">
                            @foreach ($items as $template)
                                <button type="button" wire:click="createDraft({{ $template->id }})" wire:loading.attr="disabled" wire:key="pick-{{ $template->id }}"
                                        class="group flex items-start gap-3 rounded-lg p-3 text-left ring-1 ring-gray-200 transition hover:bg-primary-50 hover:ring-primary-400 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500 dark:ring-white/10 dark:hover:bg-primary-400/10">
                                    <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-md {{ $template->is_critical ? 'bg-danger-50 text-danger-600 dark:bg-danger-400/10 dark:text-danger-400' : 'bg-gray-100 text-gray-500 group-hover:text-primary-600 dark:bg-white/5 dark:text-gray-400' }}">
                                        @svg($template->icon ?: 'heroicon-o-document-text', 'h-4 w-4')
                                    </span>
                                    <span class="min-w-0">
                                        <span class="block text-sm font-medium text-gray-900 dark:text-white">
                                            {{ $template->name }}
                                            @if ($template->is_critical) <span class="ml-1 text-[10px] font-semibold uppercase text-danger-600">kritis</span> @endif
                                        </span>
                                        <span class="mt-0.5 line-clamp-2 block text-xs text-gray-500 dark:text-gray-400">{{ $template->description }}</span>
                                    </span>
                                </button>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>
        </x-filament::modal>
    @endif
</div>
