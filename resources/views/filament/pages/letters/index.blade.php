<x-filament-panels::page>
    @php
        $awaiting = $this->awaitingMe;
        $counts = $this->statusCounts;
        $statuses = \App\Models\Letter::STATUSES;
        $categories = \App\Models\LetterTemplate::CATEGORIES;
    @endphp

    <div class="space-y-5">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <p class="max-w-3xl text-sm text-gray-600 dark:text-gray-400">
                Pustaka template. Pilih template, isi poin-poinnya, sistem menyusun naskah berkop, lalu surat dirutekan untuk ditandatangani.
            </p>
            @if (auth()->user()->hasAnyRole(['super-admin', 'direktur']))
                @php $nextNumbers = $this->nextNumbers; @endphp
                <a href="{{ \App\Filament\Pages\Letters\NumberingSettings::getUrl() }}"
                   class="inline-flex shrink-0 items-center gap-2 rounded-lg bg-white px-3 py-2 text-xs ring-1 ring-gray-200 transition hover:ring-gray-400 dark:bg-white/5 dark:ring-white/10"
                   title="Pengaturan penomoran">
                    <x-heroicon-m-hashtag class="h-4 w-4 text-gray-400" />
                    <span class="text-gray-500 dark:text-gray-400">Nomor berikutnya</span>
                    @foreach ($nextNumbers as $n)
                        <span class="font-mono font-semibold text-gray-900 dark:text-white">{{ $n }}</span>
                    @endforeach
                    <x-heroicon-m-cog-6-tooth class="h-4 w-4 text-gray-400" />
                </a>
            @endif
        </div>

        @if ($awaiting > 0)
            <a href="{{ static::getUrl(['tab' => 'surat', 'status' => 'submitted']) }}"
               class="flex items-center gap-3 rounded-xl bg-warning-50 px-4 py-3 text-sm text-warning-800 ring-1 ring-warning-600/20 transition-colors hover:bg-warning-100 dark:bg-warning-400/10 dark:text-warning-200 dark:ring-warning-400/30">
                <x-heroicon-m-pencil-square class="h-5 w-5 shrink-0" />
                <span><span class="font-semibold">{{ $awaiting }} surat</span> menunggu tanda tangan Anda.</span>
                <x-heroicon-m-arrow-right class="ml-auto h-4 w-4" />
            </a>
        @endif

        {{-- Tabs + search --}}
        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-gray-200 dark:border-white/10">
            <nav class="flex gap-x-6" role="tablist">
                @foreach (['template' => 'Template', 'surat' => 'Surat'] as $key => $label)
                    <button type="button" role="tab" wire:click="$set('tab', '{{ $key }}')"
                            aria-selected="{{ $tab === $key ? 'true' : 'false' }}"
                            @class([
                                '-mb-px flex items-center gap-2 border-b-2 py-3 text-sm font-medium transition-colors',
                                'border-primary-600 text-gray-950 dark:border-primary-400 dark:text-white' => $tab === $key,
                                'border-transparent text-gray-500 hover:text-gray-800 dark:text-gray-400 dark:hover:text-gray-200' => $tab !== $key,
                            ])>
                        {{ $label }}
                        <span class="rounded-full bg-gray-100 px-1.5 py-0.5 text-[11px] font-semibold tabular-nums text-gray-600 dark:bg-white/5 dark:text-gray-400">
                            {{ $key === 'template' ? $this->templates->flatten(1)->count() : ($counts[''] ?? 0) }}
                        </span>
                    </button>
                @endforeach
            </nav>
            <div class="relative w-full sm:w-72">
                <x-heroicon-m-magnifying-glass class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400" />
                <input type="search" wire:model.live.debounce.300ms="search"
                       placeholder="{{ $tab === 'template' ? 'Cari template…' : 'Cari nomor, perihal, klien…' }}"
                       class="block w-full rounded-lg border-0 bg-white py-2 pl-9 pr-3 text-sm text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 placeholder:text-gray-500 focus:ring-2 focus:ring-inset focus:ring-primary-500 dark:bg-gray-900 dark:text-white dark:ring-white/10">
            </div>
        </div>

        {{-- ================= TEMPLATE LIBRARY ================= --}}
        @if ($tab === 'template')
            @forelse ($this->templates as $category => $templates)
                <section class="space-y-3" wire:key="cat-{{ $category }}">
                    <div class="flex items-baseline gap-2">
                        <h2 class="text-sm font-semibold text-gray-900 dark:text-white">{{ $categories[$category] ?? ucfirst($category) }}</h2>
                        <span class="text-xs tabular-nums text-gray-400">{{ $templates->count() }}</span>
                        <span class="ml-2 h-px flex-1 bg-gray-200 dark:bg-white/10"></span>
                    </div>

                    <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                        @foreach ($templates as $template)
                            @php $signers = collect($template->signers)->pluck('label'); @endphp
                            <button type="button" wire:click="pickTemplate({{ $template->id }})" wire:key="tpl-{{ $template->id }}"
                                    title="{{ $template->description }}"
                                    class="group flex h-full flex-col rounded-xl bg-white p-4 text-left shadow-sm ring-1 ring-gray-950/5 transition duration-150 ease-out hover:-translate-y-0.5 hover:shadow-md hover:ring-primary-400 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500 motion-reduce:transform-none dark:bg-gray-900 dark:ring-white/10 dark:hover:ring-primary-400">
                                {{-- Head: icon + title + critical flag --}}
                                <div class="mb-4 flex items-start gap-3">
                                    <span @class([
                                        'flex h-10 w-10 shrink-0 items-center justify-center rounded-lg transition-colors duration-150',
                                        'bg-danger-50 text-danger-600 dark:bg-danger-400/10 dark:text-danger-400' => $template->is_critical,
                                        'bg-gray-100 text-gray-600 group-hover:bg-primary-50 group-hover:text-primary-600 dark:bg-white/5 dark:text-gray-300 dark:group-hover:bg-primary-400/10 dark:group-hover:text-primary-300' => ! $template->is_critical,
                                    ])>
                                        @svg($template->icon ?: 'heroicon-o-document-text', 'h-5 w-5')
                                    </span>
                                    <span class="min-w-0 flex-1">
                                        <span class="flex items-start justify-between gap-2">
                                            <span class="line-clamp-2 text-sm font-semibold leading-5 text-gray-900 dark:text-white">{{ $template->name }}</span>
                                            @if ($template->is_critical)
                                                <span class="mt-0.5 shrink-0 rounded-full bg-danger-50 px-2 py-0.5 text-[11px] font-semibold text-danger-700 ring-1 ring-inset ring-danger-600/20 dark:bg-danger-400/10 dark:text-danger-400">kritis</span>
                                            @endif
                                        </span>
                                        <span class="mt-1 line-clamp-2 text-xs leading-5 text-gray-500 dark:text-gray-400">{{ $template->description }}</span>
                                    </span>
                                </div>

                                {{-- Foot: pinned to the bottom so every card reads the same --}}
                                <div class="mt-auto flex items-center justify-between gap-3 border-t border-gray-100 pt-3 dark:border-white/5">
                                    <span class="flex min-w-0 items-center gap-1.5 text-[11px] text-gray-500 dark:text-gray-400">
                                        @if ($signers->isEmpty())
                                            <span>Tanpa tanda tangan</span>
                                        @else
                                            @foreach ($signers as $i => $label)
                                                @if ($i > 0) <x-heroicon-m-chevron-right class="h-3 w-3 shrink-0 text-gray-300 dark:text-gray-600" /> @endif
                                                <span class="truncate">{{ $label }}</span>
                                            @endforeach
                                        @endif
                                        @if ($template->letters_count)
                                            <span class="mx-1 text-gray-300 dark:text-gray-600">&middot;</span>
                                            <span class="shrink-0 tabular-nums">{{ $template->letters_count }}&times; dipakai</span>
                                        @endif
                                    </span>
                                    <span class="flex shrink-0 items-center gap-1 text-[11px] font-medium text-primary-600 opacity-0 transition-opacity duration-150 group-hover:opacity-100 group-focus-visible:opacity-100 dark:text-primary-400">
                                        Buat surat <x-heroicon-m-arrow-right class="h-3.5 w-3.5" />
                                    </span>
                                </div>
                            </button>
                        @endforeach
                    </div>
                </section>
            @empty
                <div class="py-12 text-center text-sm text-gray-500 dark:text-gray-400">Tidak ada template yang cocok.</div>
            @endforelse
        @endif

        {{-- ================= LETTERS LIST ================= --}}
        @if ($tab === 'surat')
            <div class="flex flex-wrap items-center gap-1.5">
                @foreach (['' => 'Semua'] + $statuses as $key => $label)
                    @php $on = $statusFilter === $key; @endphp
                    <button type="button" wire:click="$set('statusFilter', '{{ $key }}')"
                            @class([
                                'inline-flex h-7 items-center gap-1.5 rounded-full px-2.5 text-xs font-medium ring-1 ring-inset transition-colors',
                                'bg-primary-50 text-primary-700 ring-primary-600/30 dark:bg-primary-400/10 dark:text-primary-300 dark:ring-primary-400/30' => $on,
                                'bg-white text-gray-600 ring-gray-200 hover:text-gray-900 dark:bg-white/5 dark:text-gray-400 dark:ring-white/10' => ! $on,
                            ])>
                        {{ $label }}
                        <span class="tabular-nums {{ $on ? 'text-primary-600/80' : 'text-gray-400' }}">{{ $counts[$key] ?? 0 }}</span>
                    </button>
                @endforeach
            </div>

            @php
                $letters = $this->letters;
                $actionItems = ($statusFilter === '' && $search === '') ? $this->actionItems : collect();
                $me = auth()->user();
                $rowGrid = 'grid-cols-[2.25rem_minmax(0,1fr)_auto] lg:grid-cols-[2.25rem_11rem_minmax(0,1fr)_11rem_11rem_9rem_1.5rem]';
            @endphp

            {{-- Needs your action --}}
            @if ($actionItems->isNotEmpty())
                <section class="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-warning-500/30 dark:bg-gray-900 dark:ring-warning-400/30" aria-label="Perlu tindakan Anda">
                    <div class="flex items-center gap-2 border-b border-warning-500/20 bg-warning-50 px-4 py-2.5 text-sm dark:bg-warning-400/10 sm:px-5">
                        <x-heroicon-m-bolt class="h-4 w-4 text-warning-600 dark:text-warning-400" />
                        <span class="font-semibold text-warning-800 dark:text-warning-200">Perlu tindakan Anda</span>
                        <span class="rounded-full bg-warning-100 px-1.5 py-0.5 text-[11px] font-semibold tabular-nums text-warning-800 dark:bg-warning-400/20 dark:text-warning-200">{{ $actionItems->count() }}</span>
                    </div>
                    <ul class="divide-y divide-gray-100 dark:divide-white/5">
                        @foreach ($actionItems as $letter)
                            @php $slot = $letter->currentSignature(); $rej = $letter->signatures->firstWhere('status', 'rejected'); @endphp
                            <li wire:key="act-{{ $letter->id }}">
                                <a href="{{ \App\Filament\Pages\Letters\Show::getUrl(['record' => $letter]) }}"
                                   class="flex items-center gap-3 px-4 py-2.5 transition-colors hover:bg-gray-50 dark:hover:bg-white/5 sm:px-5">
                                    <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg {{ $letter->status === 'rejected' ? 'bg-danger-50 text-danger-600 dark:bg-danger-400/10 dark:text-danger-400' : 'bg-warning-50 text-warning-700 dark:bg-warning-400/10 dark:text-warning-300' }}">
                                        @if ($letter->status === 'rejected') <x-heroicon-m-arrow-uturn-left class="h-4 w-4" /> @else <x-heroicon-m-pencil-square class="h-4 w-4" /> @endif
                                    </span>
                                    <span class="min-w-0 flex-1">
                                        <span class="block truncate text-sm text-gray-900 dark:text-white">
                                            <span class="font-mono text-xs text-gray-500 dark:text-gray-400">{{ $letter->number }}</span>
                                            <span class="font-medium">{{ $letter->subject }}</span>
                                            <span class="text-gray-500 dark:text-gray-400">&middot; {{ $letter->client?->name }}</span>
                                        </span>
                                        <span class="block truncate text-xs {{ $letter->status === 'rejected' ? 'text-danger-700 dark:text-danger-300' : 'text-warning-700 dark:text-warning-300' }}">
                                            @if ($letter->status === 'rejected')
                                                Ditolak {{ $rej?->user?->name ? 'oleh ' . $rej->user->name : '' }}: {{ \Illuminate\Support\Str::limit($rej?->note, 90) }}
                                            @else
                                                Giliran Anda menandatangani sebagai {{ $slot?->label }}
                                            @endif
                                        </span>
                                    </span>
                                    <span class="hidden shrink-0 text-xs font-medium text-primary-600 dark:text-primary-400 sm:inline">{{ $letter->status === 'rejected' ? 'Perbaiki' : 'Tinjau & tandatangani' }} &rarr;</span>
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </section>
            @endif

            {{-- Main list as an aligned table --}}
            <div class="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                @if ($letters->isNotEmpty())
                    <div class="hidden {{ $rowGrid }} items-center gap-3 border-b border-gray-100 bg-gray-50/70 px-4 py-2 text-[11px] font-semibold uppercase tracking-wide text-gray-500 dark:border-white/5 dark:bg-white/[.03] dark:text-gray-400 sm:px-5 lg:grid">
                        <span></span>
                        <span>Nomor</span>
                        <span>Perihal &amp; klien</span>
                        <span>Tanda tangan</span>
                        <span>Dibuat</span>
                        <span>Status</span>
                        <span></span>
                    </div>
                @endif

                @forelse ($letters as $letter)
                    @php
                        $slot = $letter->currentSignature();
                        $sigs = $letter->signatures;
                        $isMine = $letter->created_by === $me->id;
                    @endphp
                    <a href="{{ \App\Filament\Pages\Letters\Show::getUrl(['record' => $letter]) }}" wire:key="ltr-{{ $letter->id }}"
                       class="group grid {{ $rowGrid }} items-center gap-3 border-b border-gray-100 px-4 py-3 transition-colors last:border-b-0 hover:bg-gray-50 dark:border-white/5 dark:hover:bg-white/5 sm:px-5">
                        {{-- icon --}}
                        <span class="flex h-9 w-9 items-center justify-center rounded-lg bg-gray-100 text-gray-500 transition-colors group-hover:bg-primary-50 group-hover:text-primary-600 dark:bg-white/5 dark:text-gray-400 dark:group-hover:bg-primary-400/10 dark:group-hover:text-primary-300">
                            @svg($letter->template->icon ?: 'heroicon-o-document-text', 'h-4 w-4')
                        </span>

                        {{-- number (desktop column) --}}
                        <span class="hidden lg:block">
                            @if ($letter->number)
                                <span class="font-mono text-xs font-medium text-gray-700 dark:text-gray-200">{{ $letter->number }}</span>
                            @else
                                <span class="text-xs text-gray-400 dark:text-gray-500">Belum bernomor</span>
                            @endif
                        </span>

                        {{-- subject + client --}}
                        <span class="min-w-0">
                            <span class="block truncate text-sm font-medium text-gray-900 dark:text-white">{{ $letter->subject }}</span>
                            <span class="block truncate text-xs text-gray-500 dark:text-gray-400">
                                {{ $letter->client?->name }}
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

                        {{-- signature progress (desktop) --}}
                        <span class="hidden lg:block">
                            @if ($sigs->isEmpty())
                                <span class="text-xs text-gray-400 dark:text-gray-500">{{ $letter->isDraft() ? collect($letter->template->signers)->pluck('label')->join(' › ') ?: '—' : '—' }}</span>
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
                                        @if ($slot) menunggu {{ $slot->label }}
                                        @elseif ($letter->status === 'signed') lengkap
                                        @elseif ($letter->status === 'rejected') ditolak
                                        @endif
                                    </span>
                                </span>
                            @endif
                        </span>

                        {{-- creator + date (desktop) --}}
                        <span class="hidden min-w-0 lg:block">
                            <span class="block truncate text-xs text-gray-700 dark:text-gray-200">{{ $isMine ? 'Anda' : $letter->creator?->name }}</span>
                            <span class="block text-[11px] text-gray-400 dark:text-gray-500">{{ $letter->created_at->locale('id')->translatedFormat('d M Y') }}</span>
                        </span>

                        {{-- status --}}
                        <span class="flex justify-end lg:justify-start">
                            <x-filament::badge :color="$letter->status_color">{{ $letter->status_label }}</x-filament::badge>
                        </span>

                        <x-heroicon-m-chevron-right class="hidden h-4 w-4 text-gray-300 transition-colors group-hover:text-gray-500 dark:text-gray-600 lg:block" />
                    </a>
                @empty
                    <div class="px-4 py-12 text-center">
                        <x-heroicon-o-document-text class="mx-auto h-7 w-7 text-gray-400" />
                        <p class="mt-2 text-sm font-medium text-gray-900 dark:text-white">Belum ada surat</p>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Pilih template di tab Template untuk membuat surat pertama.</p>
                    </div>
                @endforelse
            </div>
            <div>{{ $letters->links() }}</div>
        @endif
    </div>

    {{-- ================= PICK CLIENT MODAL ================= --}}
    <x-filament::modal id="pilih-klien" width="md">
        <x-slot name="heading">Buat surat</x-slot>
        <x-slot name="description">
            @php $picked = $pickedTemplateId ? \App\Models\LetterTemplate::find($pickedTemplateId) : null; @endphp
            {{ $picked?->name }}
        </x-slot>

        <form wire:submit="createDraft">
            {{ $this->pickForm }}
        </form>

        <x-slot name="footerActions">
            <x-filament::button color="gray" x-on:click="$dispatch('close-modal', { id: 'pilih-klien' })">Batal</x-filament::button>
            <x-filament::button wire:click="createDraft" wire:loading.attr="disabled">Lanjut isi surat</x-filament::button>
        </x-slot>
    </x-filament::modal>
</x-filament-panels::page>
