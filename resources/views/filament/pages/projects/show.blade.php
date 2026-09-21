<x-filament-panels::page>
    <style>
        /* Helpers still used by the PIC picker component rendered inside the "Tim & PIC" tab. */
        .custom-scrollbar::-webkit-scrollbar { width: 5px; height: 5px; }
        .custom-scrollbar::-webkit-scrollbar-track { background: #f1f1f1; border-radius: 5px; }
        .custom-scrollbar::-webkit-scrollbar-thumb { background: #d1d5db; border-radius: 5px; }
        .custom-scrollbar::-webkit-scrollbar-thumb:hover { background: #9ca3af; }
        .dark .custom-scrollbar::-webkit-scrollbar-track { background: #1f2937; }
        .dark .custom-scrollbar::-webkit-scrollbar-thumb { background: #4b5563; }
        .dark .custom-scrollbar::-webkit-scrollbar-thumb:hover { background: #6b7280; }
        .pic-button { transition: box-shadow .2s cubic-bezier(0.4, 0, 0.2, 1), transform .2s cubic-bezier(0.4, 0, 0.2, 1); }
        .pic-button:hover { transform: translateY(-1px); box-shadow: 0 4px 12px 0 rgba(0, 0, 0, 0.1); }
        .pic-button:active { transform: translateY(0); }
        .pic-modal-backdrop { backdrop-filter: blur(4px); background-color: rgba(0, 0, 0, 0.25); }
        .user-selection-item { transition: box-shadow .15s ease-out, transform .15s ease-out; }
        .user-selection-item:hover { transform: translateY(-1px); box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06); }
        .avatar-ring { transition: box-shadow .2s ease-out; }
        @media (prefers-reduced-motion: reduce) {
            .pic-button, .user-selection-item, .avatar-ring { transition: none; }
            .pic-button:hover, .user-selection-item:hover { transform: none; }
        }
    </style>

    @php
        $steps = $record->steps->sortBy('order')->values();
        $statusRecord = $record->statusRecord;
        $statusLabel = $statusRecord?->label ?? ucwords(str_replace('_', ' ', $record->status));
        $statusColor = match ($statusRecord?->category) {
            'done'   => 'success',
            'active' => 'info',
            'closed' => 'danger',
            default  => 'gray',
        };

        $isManager = ! auth()->user()->hasRole(['staff', 'client']);
        $clientActive = $record->client?->status === 'Active';
        $canEditItems = $isManager && $clientActive;

        // Overall progress: tasks + required documents across every step.
        $totalItems = 0;
        $completedItems = 0;
        $totalDocs = 0;
        $completedDocs = 0;
        $completedSteps = 0;
        foreach ($steps as $s) {
            $totalItems += $s->tasks->count();
            $completedItems += $s->tasks->where('status', 'completed')->count();
            $docs = $s->requiredDocuments;
            $totalDocs += $docs->count();
            $completedDocs += $docs->whereIn('status', ['approved', 'approved_without_document'])->count();
            $totalItems += $docs->count();
            $completedItems += $docs->whereIn('status', ['approved', 'approved_without_document'])->count();
            if ($s->status === 'completed') {
                $completedSteps++;
            }
        }
        $progressPercentage = $totalItems > 0 ? (int) round($completedItems / $totalItems * 100) : 0;

        $deliverables = $record->deliverable_files_with_urls;
        $hasResult = $record->status === 'completed' || ! empty($deliverables) || filled($record->result_notes);

        // Chaining only applies once a project is done (or already has a child);
        // mirrors ProjectChaining::isEligible so the tab never opens onto nothing.
        $showChaining = $statusRecord?->category === 'done' || $record->childProjects()->exists();

        // Shared status vocabulary — one place, reused by every row.
        $docStatus = [
            'approved' => ['label' => 'Disetujui', 'chip' => 'bg-success-50 text-success-700 ring-success-600/20 dark:bg-success-400/10 dark:text-success-400 dark:ring-success-400/30', 'icon' => 'bg-success-50 text-success-600 dark:bg-success-400/10 dark:text-success-400'],
            'approved_without_document' => ['label' => 'Disetujui tanpa dokumen', 'chip' => 'bg-success-50 text-success-700 ring-success-600/20 dark:bg-success-400/10 dark:text-success-400 dark:ring-success-400/30', 'icon' => 'bg-success-50 text-success-600 dark:bg-success-400/10 dark:text-success-400'],
            'pending_review' => ['label' => 'Menunggu review', 'chip' => 'bg-warning-50 text-warning-700 ring-warning-600/20 dark:bg-warning-400/10 dark:text-warning-400 dark:ring-warning-400/30', 'icon' => 'bg-warning-50 text-warning-600 dark:bg-warning-400/10 dark:text-warning-400'],
            'uploaded' => ['label' => 'Diunggah', 'chip' => 'bg-info-50 text-info-700 ring-info-600/20 dark:bg-info-400/10 dark:text-info-400 dark:ring-info-400/30', 'icon' => 'bg-info-50 text-info-600 dark:bg-info-400/10 dark:text-info-400'],
            'rejected' => ['label' => 'Ditolak', 'chip' => 'bg-danger-50 text-danger-700 ring-danger-600/20 dark:bg-danger-400/10 dark:text-danger-400 dark:ring-danger-400/30', 'icon' => 'bg-danger-50 text-danger-600 dark:bg-danger-400/10 dark:text-danger-400'],
            'draft' => ['label' => 'Draft', 'chip' => 'bg-gray-50 text-gray-600 ring-gray-500/20 dark:bg-white/5 dark:text-gray-400 dark:ring-white/10', 'icon' => 'bg-gray-100 text-gray-500 dark:bg-white/5 dark:text-gray-400'],
        ];
        $docStatusDefault = ['label' => 'Belum disubmit', 'chip' => 'bg-gray-50 text-gray-600 ring-gray-500/20 dark:bg-white/5 dark:text-gray-400 dark:ring-white/10', 'icon' => 'bg-gray-100 text-gray-500 dark:bg-white/5 dark:text-gray-400'];

        $taskStatus = [
            'completed'   => ['label' => 'Selesai',   'chip' => 'bg-success-50 text-success-700 ring-success-600/20 dark:bg-success-400/10 dark:text-success-400 dark:ring-success-400/30', 'dot' => 'bg-success-500'],
            'in_progress' => ['label' => 'Berjalan',  'chip' => 'bg-warning-50 text-warning-700 ring-warning-600/20 dark:bg-warning-400/10 dark:text-warning-400 dark:ring-warning-400/30', 'dot' => 'bg-warning-500'],
            'blocked'     => ['label' => 'Terblokir', 'chip' => 'bg-danger-50 text-danger-700 ring-danger-600/20 dark:bg-danger-400/10 dark:text-danger-400 dark:ring-danger-400/30', 'dot' => 'bg-danger-500'],
            'pending'     => ['label' => 'Tertunda',  'chip' => 'bg-gray-50 text-gray-600 ring-gray-500/20 dark:bg-white/5 dark:text-gray-400 dark:ring-white/10', 'dot' => 'bg-gray-400'],
        ];

        $stepStatusLabel = fn (string $status) => match ($status) {
            'completed'   => 'Selesai',
            'in_progress' => 'Berjalan',
            default       => 'Belum mulai',
        };
    @endphp

    <div
        x-data="{
            tab: (window.location.hash || '#alur').slice(1),
            tabs: @js(array_values(array_filter(['alur', 'tim', 'legal', $showChaining ? 'lanjutan' : null, $hasResult ? 'hasil' : null, 'aktivitas']))),
            init() {
                if (! this.tabs.includes(this.tab)) this.tab = 'alur';
                this.$watch('tab', (v) => history.replaceState(null, '', '#' + v));
                window.addEventListener('hashchange', () => {
                    const next = window.location.hash.slice(1);
                    if (this.tabs.includes(next)) this.tab = next;
                });
            },
        }"
        class="space-y-6"
    >
        {{-- ============ LOCK NOTICE ============ --}}
        @if (! $clientActive)
            <div class="flex items-start gap-3 rounded-xl bg-danger-50 px-4 py-3 text-sm ring-1 ring-danger-600/15 dark:bg-danger-400/10 dark:ring-danger-400/30">
                <x-heroicon-m-lock-closed class="mt-0.5 h-5 w-5 shrink-0 text-danger-500 dark:text-danger-400" />
                <div>
                    <p class="font-semibold text-danger-800 dark:text-danger-200">Proyek terkunci</p>
                    <p class="mt-0.5 text-danger-700 dark:text-danger-300">
                        Klien "{{ $record->client->name }}" berstatus tidak aktif. Tidak ada perubahan yang bisa dilakukan sampai klien diaktifkan kembali.
                    </p>
                </div>
            </div>
        @endif

        {{-- ============ HEADER: open block, no card. The page heading already carries the name. ============ --}}
        <header class="space-y-4">
            {{-- Facts as pills: each fact is a self-contained chip with icon, muted label and value.
                 White surface + visible ring so they read as real objects on the page background. --}}
            @php
                $statusPill = match ($statusColor) {
                    'success' => 'bg-success-50 text-success-700 ring-success-600/20 dark:bg-success-400/10 dark:text-success-300 dark:ring-success-400/30',
                    'info'    => 'bg-info-50 text-info-700 ring-info-600/20 dark:bg-info-400/10 dark:text-info-300 dark:ring-info-400/30',
                    'danger'  => 'bg-danger-50 text-danger-700 ring-danger-600/20 dark:bg-danger-400/10 dark:text-danger-300 dark:ring-danger-400/30',
                    default   => 'bg-gray-100 text-gray-700 ring-gray-400/30 dark:bg-white/10 dark:text-gray-200 dark:ring-white/20',
                };
                $overdue = $record->due_date && $record->due_date->isPast() && $statusRecord?->category !== 'done';
                $pill = 'inline-flex h-[26px] max-w-full items-center gap-1 rounded-full bg-white px-2.5 text-xs text-gray-900 ring-1 ring-gray-200 dark:bg-white/5 dark:text-white dark:ring-white/10';
                $pillLabel = 'text-[11px] text-gray-500 dark:text-gray-400';
            @endphp
            <ul class="flex flex-wrap items-center gap-1.5" aria-label="Ringkasan proyek">
                <li>
                    <span class="inline-flex h-[26px] items-center gap-1.5 rounded-full px-2.5 text-xs font-semibold ring-1 ring-inset {{ $statusPill }}">
                        <span class="h-1.5 w-1.5 rounded-full bg-current opacity-70"></span>
                        {{ $statusLabel }}
                    </span>
                </li>
                @if ($record->client)
                    <li class="min-w-0">
                        <a href="{{ \App\Filament\Resources\ClientResource::getUrl('view', ['record' => $record->client]) }}"
                           wire:navigate
                           class="{{ $pill }} transition-colors hover:ring-gray-400 dark:hover:ring-white/20"
                           title="Buka detail klien">
                            <span class="{{ $pillLabel }}">Klien</span>
                            <span class="truncate font-medium">{{ $record->client->name }}</span>
                        </a>
                    </li>
                @endif
                @if ($record->due_date)
                    <li>
                        <span class="{{ $pill }} {{ $overdue ? '!text-danger-700 !ring-danger-200 dark:!text-danger-300 dark:!ring-danger-400/30' : '' }}">
                            <span class="{{ $overdue ? 'text-[11px] text-danger-600/80 dark:text-danger-300/80' : $pillLabel }}">Tenggat</span>
                            <span class="font-medium">{{ $record->due_date->translatedFormat('d M Y') }}</span>
                            @if ($overdue)
                                <span class="text-[11px]">(lewat {{ $record->due_date->locale('id')->diffForHumans(null, true) }})</span>
                            @endif
                        </span>
                    </li>
                @endif
                @if ($record->type)
                    <li>
                        <span class="{{ $pill }}">
                            <span class="{{ $pillLabel }}">Tipe</span>
                            <span class="font-medium">{{ ucwords(str_replace('_', ' ', $record->type)) }}</span>
                        </span>
                    </li>
                @endif
                @if ($record->department)
                    <li>
                        <span class="{{ $pill }}">
                            <span class="{{ $pillLabel }}">Departemen</span>
                            <span class="font-medium">{{ $record->department->name }}</span>
                        </span>
                    </li>
                @endif
                <li class="min-w-0">
                    @if ($record->sop)
                        <span class="{{ $pill }}" title="Tahapan proyek ini mengikuti SOP {{ $record->sop->name }}">
                            <span class="{{ $pillLabel }}">SOP</span>
                            <span class="truncate font-medium">{{ $record->sop->name }}</span>
                        </span>
                    @else
                        <span class="inline-flex h-[26px] items-center gap-1 rounded-full border border-dashed border-gray-300 px-2.5 text-xs text-gray-500 dark:border-gray-600 dark:text-gray-400">
                            Tanpa SOP
                        </span>
                    @endif
                </li>
            </ul>

            @if ($record->description)
                @php $plainDescription = strip_tags($record->description); @endphp
                <div x-data="{ expanded: false }" class="max-w-prose text-sm leading-6 text-gray-600 dark:text-gray-300">
                    <div x-show="!expanded">
                        {{ Str::limit($plainDescription, 200) }}
                        @if (Str::length($plainDescription) > 200)
                            <button type="button" x-on:click="expanded = true" class="ml-1 font-medium text-primary-600 hover:underline dark:text-primary-400">Selengkapnya</button>
                        @endif
                    </div>
                    <div x-show="expanded" x-cloak class="prose prose-sm max-w-none dark:prose-invert">
                        {!! str($record->description)->sanitizeHtml() !!}
                        <button type="button" x-on:click="expanded = false" class="mt-1 font-medium text-primary-600 hover:underline dark:text-primary-400">Ringkas</button>
                    </div>
                </div>
            @endif

            {{-- Progress: the bar IS the flow. One segment per step, coloured by its state;
                 the running step fills to its own completion. Click a segment to jump to it. --}}
            @if ($steps->isNotEmpty())
                <div>
                    <div class="flex flex-wrap items-baseline justify-between gap-x-4 gap-y-1">
                        <p class="text-sm text-gray-600 dark:text-gray-300">
                            <span class="text-base font-semibold tabular-nums text-gray-950 dark:text-white">{{ $progressPercentage }}%</span>
                            <span class="text-gray-500 dark:text-gray-400">&middot; {{ $completedItems }}/{{ $totalItems }} item selesai</span>
                        </p>
                        <p class="text-xs text-gray-500 dark:text-gray-400">
                            {{ $completedSteps }}/{{ $steps->count() }} tahapan
                            <span class="mx-1 text-gray-300 dark:text-gray-600">&middot;</span>
                            {{ $completedDocs }}/{{ $totalDocs }} dokumen
                        </p>
                    </div>
                    <ol class="mt-2 flex gap-1" aria-label="Kemajuan per tahapan">
                        @foreach ($steps as $s)
                            @php
                                $sTasks = $s->tasks->count();
                                $sDocs = $s->requiredDocuments->count();
                                $sTotal = $sTasks + $sDocs;
                                $sDone = $s->tasks->where('status', 'completed')->count()
                                    + $s->requiredDocuments->whereIn('status', ['approved', 'approved_without_document'])->count();
                                $sPct = $s->status === 'completed' ? 100 : ($sTotal > 0 ? (int) round($sDone / $sTotal * 100) : 0);
                                $sTone = $s->status === 'completed' ? 'bg-success-500'
                                    : ($s->status === 'in_progress' ? 'bg-primary-500' : 'bg-gray-400 dark:bg-gray-500');
                                // Running step gets a tinted track so it reads as active even at 0%.
                                $sTrack = $s->status === 'in_progress'
                                    ? 'bg-primary-100 group-hover:bg-primary-200 dark:bg-primary-400/20 dark:group-hover:bg-primary-400/30'
                                    : 'bg-gray-200 group-hover:bg-gray-300 dark:bg-white/10 dark:group-hover:bg-white/20';
                            @endphp
                            <li class="min-w-0 flex-1">
                                <button type="button"
                                        title="{{ $s->order }}. {{ $s->name }} &middot; {{ $sDone }}/{{ $sTotal }}"
                                        aria-label="Tahapan {{ $s->order }}: {{ $s->name }}, {{ $sPct }}%"
                                        x-on:click="tab = 'alur'; $nextTick(() => document.getElementById('step-{{ $s->id }}')?.scrollIntoView({ behavior: 'smooth', block: 'start' }))"
                                        class="group block w-full rounded-sm py-1.5 focus-visible:outline-none">
                                    <span class="block h-1.5 w-full overflow-hidden rounded-full transition-colors {{ $sTrack }}">
                                        <span class="block h-full rounded-full {{ $sTone }}" style="width: {{ $sPct }}%"></span>
                                    </span>
                                </button>
                            </li>
                        @endforeach
                    </ol>
                </div>
            @endif
        </header>

        {{-- ============ MAIN CONTAINER: tab strip + panels ============ --}}
        @php
            $tabItems = array_values(array_filter([
                ['key' => 'alur',     'label' => 'Alur & Tahapan', 'count' => $steps->count() ?: null],
                ['key' => 'tim',      'label' => 'Tim & PIC',      'count' => $record->userProject->count() ?: null],
                ['key' => 'legal',    'label' => 'Dokumen Legal',  'count' => null],
                $showChaining ? ['key' => 'lanjutan', 'label' => 'Proyek Lanjutan', 'count' => null] : null,
                $hasResult    ? ['key' => 'hasil',    'label' => 'Hasil',           'count' => count($deliverables) ?: null] : null,
                ['key' => 'aktivitas', 'label' => 'Aktivitas', 'count' => null],
            ]));
        @endphp
        <div>
            {{-- Tab strip: bare text + underline on the page background, hugging the panel below --}}
            <nav class="flex gap-x-6 overflow-x-auto border-b border-gray-200 px-1 dark:border-white/10" role="tablist" aria-label="Bagian proyek">
                @foreach ($tabItems as $t)
                    <button type="button" role="tab"
                            x-on:click="tab = '{{ $t['key'] }}'"
                            :aria-selected="tab === '{{ $t['key'] }}'"
                            :tabindex="tab === '{{ $t['key'] }}' ? 0 : -1"
                            x-on:keydown.arrow-right.prevent="$el.nextElementSibling?.focus()"
                            x-on:keydown.arrow-left.prevent="$el.previousElementSibling?.focus()"
                            class="-mb-px flex shrink-0 items-center gap-2 whitespace-nowrap border-b-2 py-3.5 text-sm font-medium transition-colors duration-150 focus-visible:outline-none focus-visible:text-gray-950 dark:focus-visible:text-white"
                            :class="tab === '{{ $t['key'] }}'
                                ? 'border-primary-600 text-gray-950 dark:border-primary-400 dark:text-white'
                                : 'border-transparent text-gray-500 hover:text-gray-800 dark:text-gray-400 dark:hover:text-gray-200'">
                        {{ $t['label'] }}
                        @if ($t['count'] !== null)
                            <span class="rounded-full px-1.5 py-0.5 text-[11px] font-semibold leading-none tabular-nums transition-colors duration-150"
                                  :class="tab === '{{ $t['key'] }}'
                                      ? 'bg-primary-50 text-primary-700 dark:bg-primary-400/10 dark:text-primary-300'
                                      : 'bg-gray-100 text-gray-600 dark:bg-white/5 dark:text-gray-400'">{{ $t['count'] }}</span>
                        @endif
                    </button>
                @endforeach
            </nav>

        <section class="mt-4 overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
            {{-- ============ PANEL: ALUR & TAHAPAN ============ --}}
            <div x-show="tab === 'alur'" x-cloak role="tabpanel" aria-label="Alur dan tahapan">
        {{-- ============ TAB: ALUR & TAHAPAN ============ --}}
        <section x-show="tab === 'alur'" x-cloak aria-label="Alur dan tahapan">
            @if ($steps->isEmpty())
                <div class="px-6 py-12 text-center">
                    <x-heroicon-o-queue-list class="mx-auto h-8 w-8 text-gray-400" />
                    <p class="mt-3 text-sm font-semibold text-gray-900 dark:text-white">Belum ada tahapan</p>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Tahapan mengikuti SOP yang dipilih saat proyek dibuat.</p>
                </div>
            @else
                <ol class="divide-y divide-gray-100 dark:divide-white/5">
                    @foreach ($steps as $step)
                        @php
                            $isCompleted = $step->status === 'completed';
                            $isActive = $step->status === 'in_progress';
                            $reviewCount = $step->requiredDocuments->where('status', 'uploaded')->count();

                            $stepTasks = $step->tasks;
                            $stepDocs = $step->requiredDocuments->sortBy('status')->values();
                            $stepTotal = $stepTasks->count() + $stepDocs->count();
                            $stepDone = $stepTasks->where('status', 'completed')->count()
                                + $stepDocs->whereIn('status', ['approved', 'approved_without_document'])->count();
                            $stepPct = $stepTotal > 0 ? (int) round($stepDone / $stepTotal * 100) : 0;
                            $stepDescription = strip_tags((string) $step->description);
                            // Read by the preserved "delete step" confirmation modal below.
                            $totalTasks = $stepTasks->count();
                            $submittedDocumentsCount = $stepDocs->sum(fn ($d) => $d->submittedDocuments->count());
                        @endphp

                        <li id="step-{{ $step->id }}" x-data="{ open: true }" wire:key="step-{{ $step->id }}" @class(['scroll-mt-24', 'bg-info-50/40 dark:bg-info-400/5' => $reviewCount > 0])>
                            {{-- Step row --}}
                            <div class="group flex cursor-pointer select-none items-center gap-3 px-4 py-3 transition-colors duration-150 hover:bg-gray-50 dark:hover:bg-white/[.03] sm:px-5"
                                 role="button" tabindex="0" :aria-expanded="open"
                                 x-on:click="open = !open"
                                 x-on:keydown.enter.prevent="open = !open"
                                 x-on:keydown.space.prevent="open = !open">
                                {{-- Node --}}
                                <span @class([
                                    'flex h-7 w-7 shrink-0 items-center justify-center rounded-full text-xs font-semibold ring-1',
                                    'bg-success-500 text-white ring-success-500' => $isCompleted,
                                    'bg-primary-500 text-white ring-primary-500' => ! $isCompleted && $isActive,
                                    'bg-white text-gray-500 ring-gray-300 dark:bg-gray-900 dark:text-gray-400 dark:ring-gray-600' => ! $isCompleted && ! $isActive,
                                ])>
                                    @if ($isCompleted)
                                        <x-heroicon-m-check class="h-4 w-4" />
                                    @else
                                        {{ $step->order }}
                                    @endif
                                </span>

                                {{-- Title + one-line description --}}
                                <div class="min-w-0 flex-1">
                                    <div class="flex min-w-0 flex-wrap items-center gap-x-2 gap-y-1">
                                        <h3 class="truncate text-sm font-semibold text-gray-900 dark:text-white">{{ $step->name }}</h3>
                                        @if ($reviewCount > 0)
                                            <span class="inline-flex items-center gap-1 rounded-full bg-info-50 px-2 py-0.5 text-[11px] font-semibold text-info-700 ring-1 ring-inset ring-info-600/20 dark:bg-info-400/10 dark:text-info-400 dark:ring-info-400/30">
                                                <x-heroicon-m-arrow-up-tray class="h-3 w-3" />
                                                {{ $reviewCount }} perlu review
                                            </span>
                                        @endif
                                    </div>
                                    @if ($stepDescription !== '')
                                        <p x-show="!open" class="mt-0.5 truncate text-xs text-gray-500 dark:text-gray-400" title="{{ $stepDescription }}">{{ $stepDescription }}</p>
                                    @endif
                                </div>

                                {{-- Meta --}}
                                <div class="flex shrink-0 items-center gap-3">
                                    @if ($stepTotal > 0)
                                        <div class="hidden items-center gap-2 sm:flex" title="{{ $stepDone }} dari {{ $stepTotal }} item selesai">
                                            <span class="text-xs font-medium tabular-nums text-gray-600 dark:text-gray-300">{{ $stepDone }}/{{ $stepTotal }}</span>
                                            <span class="h-1 w-14 overflow-hidden rounded-full bg-gray-200 dark:bg-white/10">
                                                <span class="block h-full rounded-full {{ $stepPct === 100 ? 'bg-success-500' : 'bg-primary-500' }}" style="width: {{ $stepPct }}%"></span>
                                            </span>
                                        </div>
                                    @endif
                                    <span @class([
                                        'text-[11px] font-semibold uppercase tracking-wide',
                                        'text-success-600 dark:text-success-400' => $isCompleted,
                                        'text-primary-600 dark:text-primary-400' => ! $isCompleted && $isActive,
                                        'text-gray-400 dark:text-gray-500' => ! $isCompleted && ! $isActive,
                                    ])>{{ $stepStatusLabel($step->status) }}</span>

                                    @if ($canDeleteProjectSteps)
                                        <button type="button" aria-label="Hapus tahapan" title="Hapus tahapan"
                                                x-on:click.stop="$dispatch('open-modal', { id: 'delete-step-modal-{{ $step->id }}' })"
                                                class="flex h-7 w-7 items-center justify-center rounded-md text-gray-400 opacity-0 transition hover:bg-gray-100 hover:text-danger-600 focus-visible:opacity-100 group-hover:opacity-100 dark:hover:bg-white/5 dark:hover:text-danger-400">
                                            <x-heroicon-m-trash class="h-4 w-4" />
                                        </button>
                                    @endif

                                    <x-heroicon-m-chevron-down class="h-4 w-4 text-gray-400 transition-transform duration-200 ease-out motion-reduce:transition-none" x-bind:class="open && 'rotate-180'" />
                                </div>
                            </div>

                            {{-- Step body --}}
                            <div x-show="open" x-cloak
                                 x-transition:enter="transition duration-150 ease-out motion-reduce:transition-none"
                                 x-transition:enter-start="opacity-0"
                                 x-transition:enter-end="opacity-100"
                                 class="border-t border-gray-100 bg-gray-50/70 px-4 py-4 dark:border-white/5 dark:bg-white/[.02] sm:px-5 sm:pl-[3.75rem]">
                                @if ($stepDescription !== '')
                                    <div class="prose prose-sm mb-4 max-w-prose text-gray-600 dark:prose-invert dark:text-gray-300">
                                        {!! str($step->description)->sanitizeHtml() !!}
                                    </div>
                                @endif

                                @if ($stepTotal === 0)
                                    <p class="text-sm text-gray-500 dark:text-gray-400">Tahapan ini tidak memiliki tugas atau dokumen.</p>
                                @endif

                                <div class="space-y-5">
                                    {{-- Tasks --}}
                                    @if ($stepTasks->isNotEmpty())
                                        <div>
                                            <div class="mb-2 flex items-center justify-between">
                                                <h4 class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Tugas</h4>
                                                <span class="text-xs tabular-nums text-gray-500 dark:text-gray-400">{{ $stepTasks->where('status', 'completed')->count() }}/{{ $stepTasks->count() }}</span>
                                            </div>
                                            <ul class="divide-y divide-gray-100 overflow-visible rounded-lg bg-white ring-1 ring-gray-950/5 dark:divide-white/5 dark:bg-gray-900 dark:ring-white/10">
                                                @foreach ($stepTasks as $task)
                                                    @php $ts = $taskStatus[$task->status] ?? $taskStatus['pending']; @endphp
                                                    <li x-data="{ showComments: false }" wire:key="task-{{ $task->id }}">
                                                        <div class="flex items-center gap-3 px-3 py-2">
                                                            <input type="checkbox"
                                                                   wire:click="toggleTaskStatus({{ $task->id }})"
                                                                   aria-label="Tandai tugas {{ $task->title }} selesai"
                                                                   @checked($task->status === 'completed')
                                                                   @disabled(! $canEditItems)
                                                                   class="h-4 w-4 shrink-0 rounded border-gray-300 text-primary-600 focus:ring-primary-500 disabled:cursor-not-allowed disabled:opacity-50 dark:border-gray-600 dark:bg-gray-800">

                                                            <div class="min-w-0 flex-1">
                                                                <p @class(['truncate text-sm text-gray-900 dark:text-gray-100', 'text-gray-500 line-through dark:text-gray-500' => $task->status === 'completed'])>{{ $task->title }}</p>
                                                                @if ($task->due_date)
                                                                    <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">
                                                                        Tenggat {{ $task->due_date->translatedFormat('d M Y') }}
                                                                        @if ($task->status === 'completed' && $task->completed_at)
                                                                            <span class="mx-1 text-gray-300 dark:text-gray-600">&middot;</span>
                                                                            Selesai {{ $task->completed_at->translatedFormat('d M Y') }}
                                                                        @endif
                                                                    </p>
                                                                @endif
                                                            </div>

                                                            {{-- Status --}}
                                                            <div x-data="{ open: false }" class="relative shrink-0">
                                                                <button type="button" x-on:click="open = !open" @disabled(! $isManager)
                                                                        class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-medium ring-1 ring-inset transition-colors duration-150 disabled:cursor-default {{ $ts['chip'] }}">
                                                                    <span class="h-1.5 w-1.5 rounded-full {{ $ts['dot'] }}"></span>
                                                                    {{ $ts['label'] }}
                                                                    @if ($isManager)
                                                                        <x-heroicon-m-chevron-down class="h-3 w-3 opacity-70" />
                                                                    @endif
                                                                </button>

                                                                @if ($isManager)
                                                                    <div x-show="open" x-cloak x-on:click.away="open = false"
                                                                         x-transition:enter="transition duration-150 ease-out motion-reduce:transition-none"
                                                                         x-transition:enter-start="opacity-0 scale-95"
                                                                         x-transition:enter-end="opacity-100 scale-100"
                                                                         class="absolute right-0 z-10 mt-1.5 w-44 origin-top-right overflow-hidden rounded-lg bg-white p-1 shadow-lg ring-1 ring-gray-950/5 dark:bg-gray-800 dark:ring-white/10">
                                                                        @foreach ($taskStatus as $key => $meta)
                                                                            <button type="button"
                                                                                    x-on:click="open = false; $dispatch('open-modal', { id: 'confirm-status-modal-{{ $task->id }}' }); $wire.updateTaskStatus({{ $task->id }}, '{{ $key }}')"
                                                                                    class="flex w-full items-center gap-2.5 rounded-md px-2.5 py-1.5 text-sm text-gray-700 hover:bg-gray-50 dark:text-gray-200 dark:hover:bg-white/5 {{ $task->status === $key ? 'font-semibold' : '' }}">
                                                                                <span class="h-1.5 w-1.5 rounded-full {{ $meta['dot'] }}"></span>
                                                                                {{ $meta['label'] }}
                                                                            </button>
                                                                        @endforeach
                                                                    </div>

                                                                    <div class="[&>.fi-modal]:block [&>.fi-modal]:h-0">
                                                                    <x-filament::modal id="confirm-status-modal-{{ $task->id }}"
                                                                        width="md">
                                                                        <div class="p-2 space-y-6">
                                                                            <!-- Header -->
                                                                            <div class="flex items-center gap-4">
                                                                                <div @class([ 'w-12 h-12 rounded-full flex items-center justify-center'
                                                                                    , 'bg-gray-100 dark:bg-gray-800'=>
                                                                                    $newTaskStatus === 'pending',
                                                                                    'bg-amber-100 dark:bg-amber-900' =>
                                                                                    $newTaskStatus === 'in_progress',
                                                                                    'bg-green-100 dark:bg-green-900' =>
                                                                                    $newTaskStatus === 'completed',
                                                                                    'bg-red-100 dark:bg-red-900' => $newTaskStatus
                                                                                    === 'blocked'
                                                                                    ])>
                                                                                    <x-heroicon-o-arrow-path-rounded-square
                                                                                        @class([ 'w-6 h-6'
                                                                                        , 'text-gray-600 dark:text-gray-400'=>
                                                                                        $newTaskStatus === 'pending',
                                                                                        'text-amber-600 dark:text-amber-400' =>
                                                                                        $newTaskStatus === 'in_progress',
                                                                                        'text-green-600 dark:text-green-400' =>
                                                                                        $newTaskStatus === 'completed',
                                                                                        'text-red-600 dark:text-red-400' =>
                                                                                        $newTaskStatus === 'blocked'
                                                                                        ]) />
                                                                                </div>

                                                                                <div>
                                                                                    <h2
                                                                                        class="text-lg font-medium text-gray-900 dark:text-white">
                                                                                        Update Task Status</h2>
                                                                                    <p
                                                                                        class="text-sm text-gray-500 dark:text-gray-400">
                                                                                        Are you sure you want to proceed?</p>
                                                                                </div>
                                                                            </div>

                                                                            <!-- Content -->
                                                                            <div class="bg-gray-50 dark:bg-gray-800 rounded-lg p-4">
                                                                                <div class="flex items-center justify-between">
                                                                                    <span
                                                                                        class="text-sm text-gray-600 dark:text-gray-400">Current
                                                                                        Status</span>
                                                                                    <x-filament::badge :color="match($task->status) {
                                                                                    'completed' => 'success',
                                                                                    'in_progress' => 'warning',
                                                                                    'blocked' => 'danger',
                                                                                    default => 'gray'
                                                                                }">
                                                                                        {{ ucfirst($task->status) }}
                                                                                    </x-filament::badge>
                                                                                </div>

                                                                                <div class="flex items-center justify-between mt-3">
                                                                                    <span
                                                                                        class="text-sm text-gray-600 dark:text-gray-400">New
                                                                                        Status</span>
                                                                                    <x-filament::badge :color="match($newTaskStatus) {
                                                                                    'completed' => 'success',
                                                                                    'in_progress' => 'warning',
                                                                                    'blocked' => 'danger',
                                                                                    default => 'gray'
                                                                                }">
                                                                                        {{ ucfirst($newTaskStatus) }}
                                                                                    </x-filament::badge>
                                                                                </div>
                                                                            </div>

                                                                            <!-- Actions -->
                                                                            <div class="flex justify-end gap-3">
                                                                                <x-filament::button color="gray"
                                                                                    x-on:click="$dispatch('close-modal', { id: 'confirm-status-modal-{{ $task->id }}' })">
                                                                                    Cancel
                                                                                </x-filament::button>
                                                                                <x-filament::button wire:click="confirmStatusChange"
                                                                                    :color="match($newTaskStatus) {
                                                                                    'completed' => 'success',
                                                                                    'in_progress' => 'warning',
                                                                                    'blocked' => 'danger',
                                                                                    default => 'gray'
                                                                                }">
                                                                                    Update Status
                                                                                </x-filament::button>
                                                                            </div>
                                                                        </div>
                                                                    </x-filament::modal>
                                                                    </div>
                                                                @endif
                                                            </div>

                                                            {{-- Comments toggle --}}
                                                            <button type="button" x-on:click="showComments = !showComments"
                                                                    class="inline-flex shrink-0 items-center gap-1 rounded-md px-2 py-1 text-xs font-medium text-gray-500 transition-colors duration-150 hover:bg-gray-100 hover:text-gray-900 dark:text-gray-400 dark:hover:bg-white/5 dark:hover:text-white"
                                                                    x-bind:class="showComments && 'bg-gray-100 text-gray-900 dark:bg-white/5 dark:text-white'"
                                                                    :aria-expanded="showComments"
                                                                    aria-label="Komentar tugas">
                                                                <x-heroicon-m-chat-bubble-left-right class="h-4 w-4" />
                                                                <span class="tabular-nums">{{ $task->comments->count() }}</span>
                                                            </button>
                                                        </div>

                                                        <div x-show="showComments" x-cloak class="border-t border-gray-100 px-3 py-3 dark:border-white/5">
                                                            <livewire:projects.forms.create-task-comment :task="$task" :wire:key="'comments-'.$task->id" />
                                                        </div>
                                                    </li>
                                                @endforeach
                                            </ul>
                                        </div>
                                    @endif

                                    {{-- Documents --}}
                                    @if ($stepDocs->isNotEmpty())
                                        <div>
                                            <div class="mb-2 flex items-center justify-between">
                                                <h4 class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Dokumen</h4>
                                                <span class="text-xs tabular-nums text-gray-500 dark:text-gray-400">{{ $stepDocs->whereIn('status', ['approved', 'approved_without_document'])->count() }}/{{ $stepDocs->count() }}</span>
                                            </div>
                                            <ul class="divide-y divide-gray-100 rounded-lg bg-white ring-1 ring-gray-950/5 dark:divide-white/5 dark:bg-gray-900 dark:ring-white/10">
                                                @foreach ($stepDocs as $document)
                                                    @php
                                                        $ds = $docStatus[$document->status] ?? $docStatusDefault;
                                                        $fileCount = $document->submittedDocuments->count();
                                                        $documentSubmittedCount = $fileCount; // read by the preserved delete modal
                                                        $docDescription = strip_tags((string) $document->description);
                                                    @endphp
                                                    <li wire:key="doc-{{ $document->id }}" class="group">
                                                        <div role="button" tabindex="0"
                                                             x-on:click="$dispatch('open-modal', { id: 'document-modal-{{ $document->id }}' })"
                                                             x-on:keydown.enter.prevent="$dispatch('open-modal', { id: 'document-modal-{{ $document->id }}' })"
                                                             x-on:keydown.space.prevent="$dispatch('open-modal', { id: 'document-modal-{{ $document->id }}' })"
                                                             class="flex cursor-pointer items-center gap-3 px-3 py-2 transition-colors duration-150 hover:bg-gray-50 focus-visible:bg-gray-50 focus-visible:outline-none dark:hover:bg-white/5 dark:focus-visible:bg-white/5">
                                                            <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-md {{ $ds['icon'] }}">
                                                                @if ($fileCount > 0)
                                                                    <x-heroicon-m-document-text class="h-4 w-4" />
                                                                @else
                                                                    <x-heroicon-m-document-plus class="h-4 w-4" />
                                                                @endif
                                                            </span>

                                                            <div class="min-w-0 flex-1">
                                                                <p class="truncate text-sm font-medium text-gray-900 dark:text-gray-100">
                                                                    {{ $document->name }}
                                                                    @if ($document->is_required)
                                                                        <span class="text-danger-500" title="Wajib">*</span>
                                                                    @endif
                                                                </p>
                                                                @if ($docDescription !== '')
                                                                    <p class="truncate text-xs text-gray-500 dark:text-gray-400" title="{{ $docDescription }}">{{ $docDescription }}</p>
                                                                @endif
                                                            </div>

                                                            @if ($fileCount > 0)
                                                                <span class="hidden shrink-0 text-xs tabular-nums text-gray-500 dark:text-gray-400 sm:inline">{{ $fileCount }} file</span>
                                                            @endif

                                                            <span class="shrink-0 rounded-full px-2.5 py-1 text-xs font-medium ring-1 ring-inset {{ $ds['chip'] }}">{{ $ds['label'] }}</span>

                                                            @if ($canDeleteProjectRequiredDocuments)
                                                                <button type="button" aria-label="Hapus dokumen" title="Hapus dokumen"
                                                                        x-on:click.stop="$dispatch('open-modal', { id: 'delete-required-document-modal-{{ $document->id }}' })"
                                                                        class="flex h-7 w-7 shrink-0 items-center justify-center rounded-md text-gray-400 opacity-0 transition hover:bg-gray-100 hover:text-danger-600 focus-visible:opacity-100 group-hover:opacity-100 dark:hover:bg-white/5 dark:hover:text-danger-400">
                                                                    <x-heroicon-m-trash class="h-4 w-4" />
                                                                </button>
                                                            @endif
                                                        </div>

                                                        {{-- Modal hosts: Filament renders each modal root as an empty inline-block,
                                                             which would add a phantom line of height to the row. --}}
                                                        <div class="[&>.fi-modal]:block [&>.fi-modal]:h-0">
                                                        <x-filament::modal id="document-modal-{{ $document->id }}" width="4xl" slide-over>
                                                            @livewire('projects.modals.document-modal', ['document' => $document], key('document-modal-' . $document->id . time()))
                                                        </x-filament::modal>

                                                        @if ($canDeleteProjectRequiredDocuments)
                                                        <x-filament::modal id="delete-required-document-modal-{{ $document->id }}"
                                                            width="md">
                                                            <div class="space-y-6 p-2">
                                                                <div class="flex items-start gap-4">
                                                                    <div
                                                                        class="flex h-12 w-12 flex-shrink-0 items-center justify-center rounded-full bg-gray-100 dark:bg-gray-800">
                                                                        <x-heroicon-o-trash
                                                                            class="h-6 w-6 text-red-500 dark:text-red-400" />
                                                                    </div>

                                                                    <div class="min-w-0">
                                                                        <h2
                                                                            class="text-lg font-semibold text-gray-900 dark:text-white">
                                                                            Delete required document
                                                                        </h2>
                                                                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                                                                            This will remove the requirement from this project step and
                                                                            recalculate project progress.
                                                                        </p>
                                                                    </div>
                                                                </div>

                                                                <div
                                                                    class="rounded-lg border border-gray-200 bg-gray-50 p-4 dark:border-gray-700 dark:bg-gray-900">
                                                                    <div class="flex items-start justify-between gap-4">
                                                                        <div class="min-w-0">
                                                                            <p
                                                                                class="truncate text-sm font-medium text-gray-900 dark:text-gray-100">
                                                                                {{ $document->name }}
                                                                            </p>
                                                                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                                                                {{ $step->name }} &bull; {{ $documentSubmittedCount }}
                                                                                submitted file(s)
                                                                            </p>
                                                                        </div>

                                                                        <x-filament::badge :color="match($document->status) {
                                                                            'approved' => 'success',
                                                                            'approved_without_document' => 'success',
                                                                            'pending_review' => 'warning',
                                                                            'uploaded' => 'info',
                                                                            'rejected' => 'danger',
                                                                            default => 'gray'
                                                                        }">
                                                                            {{ ucwords(str_replace('_', ' ', $document->status)) }}
                                                                        </x-filament::badge>
                                                                    </div>
                                                                </div>

                                                                <div
                                                                    class="flex gap-3 rounded-lg border border-gray-200 bg-gray-50 p-4 text-sm text-gray-600 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300">
                                                                    <x-heroicon-o-exclamation-triangle
                                                                        class="mt-0.5 h-5 w-5 flex-shrink-0 text-red-500 dark:text-red-400" />
                                                                    <p>
                                                                        File yang sudah dikirim dan komentar terkait untuk persyaratan
                                                                        ini juga akan dihapus. Tindakan ini tidak dapat dibatalkan.
                                                                    </p>
                                                                </div>

                                                                <div class="flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                                                                    <x-filament::button color="gray"
                                                                        x-on:click="$dispatch('close-modal', { id: 'delete-required-document-modal-{{ $document->id }}' })">
                                                                        Cancel
                                                                    </x-filament::button>
                                                                    <x-filament::button color="danger" icon="heroicon-o-trash"
                                                                        wire:click="deleteRequiredDocument({{ $document->id }})"
                                                                        wire:loading.attr="disabled"
                                                                        wire:target="deleteRequiredDocument">
                                                                        Delete Document
                                                                    </x-filament::button>
                                                                </div>
                                                            </div>
                                                        </x-filament::modal>
                                                        @endif
                                                        </div>
                                                    </li>
                                                @endforeach
                                            </ul>
                                        </div>
                                    @endif
                                </div>
                            </div>

                            @if ($canDeleteProjectSteps)
                            <div class="[&>.fi-modal]:block [&>.fi-modal]:h-0">
                                <x-filament::modal id="delete-step-modal-{{ $step->id }}" width="md">
                                    <div class="space-y-6 p-2">
                                        <div class="flex items-start gap-4">
                                            <div
                                                class="flex h-12 w-12 flex-shrink-0 items-center justify-center rounded-full bg-gray-100 dark:bg-gray-800">
                                                <x-heroicon-o-trash class="h-6 w-6 text-red-500 dark:text-red-400" />
                                            </div>

                                            <div class="min-w-0">
                                                <h2 class="text-lg font-semibold text-gray-900 dark:text-white">
                                                    Delete project step
                                                </h2>
                                                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                                                    This will remove the step from the project timeline and recalculate the
                                                    remaining project progress.
                                                </p>
                                            </div>
                                        </div>

                                        <div
                                            class="rounded-lg border border-gray-200 bg-gray-50 p-4 dark:border-gray-700 dark:bg-gray-900">
                                            <div class="flex items-start justify-between gap-4">
                                                <div class="min-w-0">
                                                    <p class="truncate text-sm font-medium text-gray-900 dark:text-gray-100">
                                                        {{ $step->name }}
                                                    </p>
                                                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                                        Step {{ $step->order }} &bull; {{ $totalTasks }} task(s) &bull; {{ $totalDocs }}
                                                        required document(s) &bull; {{ $submittedDocumentsCount }} submitted file(s)
                                                    </p>
                                                </div>

                                                <x-filament::badge :color="match($step->status) {
                                                    'completed' => 'success',
                                                    'in_progress' => 'warning',
                                                    'blocked' => 'danger',
                                                    default => 'gray'
                                                }">
                                                    {{ ucwords(str_replace('_', ' ', $step->status)) }}
                                                </x-filament::badge>
                                            </div>
                                        </div>

                                        <div
                                            class="flex gap-3 rounded-lg border border-gray-200 bg-gray-50 p-4 text-sm text-gray-600 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300">
                                            <x-heroicon-o-exclamation-triangle class="mt-0.5 h-5 w-5 flex-shrink-0 text-red-500 dark:text-red-400" />
                                            <p>
                                                Tasks, required documents, submitted files, related comments, and the matching
                                                daily-task subtask will be removed. This action cannot be undone.
                                            </p>
                                        </div>

                                        <div class="flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                                            <x-filament::button color="gray"
                                                x-on:click="$dispatch('close-modal', { id: 'delete-step-modal-{{ $step->id }}' })">
                                                Cancel
                                            </x-filament::button>
                                            <x-filament::button color="danger" icon="heroicon-o-trash"
                                                wire:click="deleteProjectStep({{ $step->id }})" wire:loading.attr="disabled"
                                                wire:target="deleteProjectStep">
                                                Delete Step
                                            </x-filament::button>
                                        </div>
                                    </div>
                                </x-filament::modal>
                            </div>
                            @endif
                        </li>
                    @endforeach
                </ol>
            @endif
            </div>

            {{-- ============ PANEL: TIM & PIC ============ --}}
            <div x-show="tab === 'tim'" x-cloak role="tabpanel" aria-label="Tim dan PIC" class="divide-y divide-gray-100 dark:divide-white/5">
                <div class="px-4 py-4 sm:px-5">
                    @livewire('projects.components.project-person-in-charge', ['project' => $record])
                </div>
                <div>
                    @livewire('projects.components.project-member', ['project' => $record])
                </div>
            </div>

            {{-- ============ PANEL: DOKUMEN LEGAL ============ --}}
            <div x-show="tab === 'legal'" x-cloak role="tabpanel" aria-label="Dokumen legal"
                 class="[&>div>div]:!rounded-none [&>div>div]:!shadow-none [&>div>div]:!bg-transparent">
                @livewire('projects.components.project-client-legal', ['project' => $record])
            </div>

            {{-- ============ PANEL: PROYEK LANJUTAN ============ --}}
            @if ($showChaining)
                <div x-show="tab === 'lanjutan'" x-cloak role="tabpanel" aria-label="Proyek lanjutan" class="p-4 sm:p-5 [&>div>div]:!mt-0">
                    @livewire('projects.components.project-chaining', ['project' => $record])
                </div>
            @endif

            {{-- ============ PANEL: HASIL ============ --}}
            @if ($hasResult)
                <div x-show="tab === 'hasil'" x-cloak role="tabpanel" aria-label="Hasil proyek"
                     class="grid divide-y divide-gray-100 dark:divide-white/5 lg:grid-cols-5 lg:divide-x lg:divide-y-0">
                    <div class="p-4 sm:p-5 lg:col-span-3">
                        <h2 class="text-sm font-semibold text-gray-900 dark:text-white">File deliverable</h2>
                        @if (empty($deliverables))
                            <p class="mt-3 text-sm text-gray-500 dark:text-gray-400">Belum ada file deliverable. Gunakan tombol "Perbarui Deliverable" di atas untuk mengunggah.</p>
                        @else
                            <ul class="mt-3 divide-y divide-gray-100 dark:divide-white/5">
                                @foreach ($deliverables as $file)
                                    <li class="flex items-center gap-3 py-2.5">
                                        <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-md bg-gray-100 text-gray-500 dark:bg-white/5 dark:text-gray-400">
                                            <x-heroicon-m-document class="h-4 w-4" />
                                        </span>
                                        <div class="min-w-0 flex-1">
                                            <p class="truncate text-sm font-medium text-gray-900 dark:text-gray-100">{{ $file['name'] }}</p>
                                            <p class="text-xs text-gray-500 dark:text-gray-400">
                                                @if ($file['size']) {{ number_format($file['size'] / 1024, 0) }} KB @endif
                                                @if ($file['size'] && $file['uploaded_at']) <span class="mx-1 text-gray-300 dark:text-gray-600">&middot;</span> @endif
                                                @if ($file['uploaded_at']) {{ \Carbon\Carbon::parse($file['uploaded_at'])->translatedFormat('d M Y') }} @endif
                                            </p>
                                        </div>
                                        @if ($file['url'])
                                            <a href="{{ $file['url'] }}" target="_blank" rel="noopener"
                                               class="inline-flex shrink-0 items-center gap-1.5 rounded-md px-2.5 py-1.5 text-xs font-medium text-primary-600 transition-colors hover:bg-primary-50 dark:text-primary-400 dark:hover:bg-primary-400/10">
                                                <x-heroicon-m-arrow-down-tray class="h-4 w-4" />
                                                Unduh
                                            </a>
                                        @endif
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                    </div>
                    <div class="p-4 sm:p-5 lg:col-span-2">
                        <h2 class="text-sm font-semibold text-gray-900 dark:text-white">Catatan penyelesaian</h2>
                        @if (filled($record->result_notes))
                            <div class="prose prose-sm mt-3 max-w-none text-gray-700 dark:prose-invert dark:text-gray-300">
                                {!! str($record->result_notes)->sanitizeHtml() !!}
                            </div>
                        @else
                            <p class="mt-3 text-sm text-gray-500 dark:text-gray-400">Belum ada catatan penyelesaian.</p>
                        @endif
                    </div>
                </div>
            @endif

            {{-- ============ PANEL: AKTIVITAS ============ --}}
            {{-- Lazy: the feed queries only once this panel is scrolled into view (i.e. the tab opens). --}}
            <div x-show="tab === 'aktivitas'" x-cloak role="tabpanel" aria-label="Aktivitas proyek">
                <livewire:projects.components.project-activity-feed :project="$record" lazy />
            </div>
        </section>
        </div>
    </div>

    <script>    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const documentId = new URLSearchParams(window.location.search).get('openDocument');
            if (documentId) {
                window.dispatchEvent(new CustomEvent('open-modal', { detail: { id: `document-modal-${documentId}` } }));
            }
        });
    </script>
</x-filament-panels::page>
