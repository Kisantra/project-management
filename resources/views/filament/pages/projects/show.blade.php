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
        $statusTone = match ($statusRecord?->category) {
            'done'   => 'ok',
            'active' => 'info',
            'closed' => 'bad',
            default  => 'none',
        };

        $isManager = ! auth()->user()->hasRole(['staff', 'client']);
        $clientActive = $record->client?->status === 'Active';
        $canEditItems = $isManager && $clientActive;
        $canChangeTaskStatus = $canEditItems && $record->status !== 'completed';
        $isProjectLocked = $this->isProjectLocked();
        $overdue = $record->due_date && $record->due_date->isPast() && $statusRecord?->category !== 'done';
        $members = $record->teamMembers;

        // Countdown next to the due date ("6 hari lagi", "lewat 11 bulan"). Not
        // shown once the project is done: a past date is no longer a problem then.
        $dueText = null;
        $dueTone = 'none';
        if ($record->due_date && $statusRecord?->category !== 'done') {
            $dueDays = (int) now()->startOfDay()->diffInDays($record->due_date->copy()->startOfDay(), false);
            [$dueText, $dueTone] = match (true) {
                $dueDays < 0   => ['lewat ' . $record->due_date->locale('id')->diffForHumans(null, true), 'bad'],
                $dueDays === 0 => ['jatuh tempo hari ini', 'warn'],
                $dueDays === 1 => ['besok', 'warn'],
                $dueDays <= 7  => [$dueDays . ' hari lagi', 'warn'],
                $dueDays <= 60 => [$dueDays . ' hari lagi', 'none'],
                default        => [$record->due_date->locale('id')->diffForHumans(null, true) . ' lagi', 'none'],
            };
        }

        $deliverables = $record->deliverable_files_with_urls;
        $hasResult = $record->status === 'completed' || ! empty($deliverables) || filled($record->result_notes);

        // Chaining only applies once a project is done (or already has a child);
        // mirrors ProjectChaining::isEligible so the tab never opens onto nothing.
        $showChaining = $statusRecord?->category === 'done' || $record->childProjects()->exists();

        // One status vocabulary for every row. 'state' drives the filters:
        // open = still needs work, review = a file is waiting on a reviewer, done.
        $docStatus = [
            'approved'                  => ['label' => 'Disetujui',              'tone' => 'ok',   'state' => 'done',   'icon' => 'heroicon-m-check-circle'],
            'approved_without_document' => ['label' => 'Disetujui tanpa dokumen', 'tone' => 'ok',   'state' => 'done',   'icon' => 'heroicon-m-check-badge'],
            'pending_review'            => ['label' => 'Menunggu review',        'tone' => 'warn', 'state' => 'review', 'icon' => 'heroicon-m-clock'],
            'uploaded'                  => ['label' => 'Diunggah',               'tone' => 'info', 'state' => 'review', 'icon' => 'heroicon-m-document-arrow-up'],
            'rejected'                  => ['label' => 'Ditolak',                'tone' => 'bad',  'state' => 'open',   'icon' => 'heroicon-m-x-circle'],
            'draft'                     => ['label' => 'Belum diunggah',         'tone' => 'none', 'state' => 'open',   'icon' => 'heroicon-m-document'],
        ];
        $taskStatus = [
            'completed'   => ['label' => 'Selesai',   'tone' => 'ok',   'icon' => 'heroicon-m-check-circle'],
            'in_progress' => ['label' => 'Berjalan',  'tone' => 'info', 'icon' => 'heroicon-m-play-circle'],
            'blocked'     => ['label' => 'Terhambat', 'tone' => 'bad',  'icon' => 'heroicon-m-no-symbol'],
            'pending'     => ['label' => 'Tertunda',  'tone' => 'none', 'icon' => 'heroicon-m-clock'],
        ];
        $stepStatus = [
            'completed'   => ['label' => 'Selesai',  'tone' => 'ok'],
            'in_progress' => ['label' => 'Berjalan', 'tone' => 'info'],
        ];
        $stepStatusDefault = ['label' => 'Belum mulai', 'tone' => 'none'];
        $date = fn ($value) => $value ? \Illuminate\Support\Carbon::parse($value)->translatedFormat('d M Y') : null;

        // Buttons are ranked so the eye lands on the next step: Review is the
        // one solid button, uploads are outlined, "Lihat" stays quiet.
        $cta = [
            'Review'       => ['variant' => 'primary', 'icon' => 'heroicon-m-eye'],
            'Unggah'       => ['variant' => 'upload',  'icon' => 'heroicon-m-arrow-up-tray'],
            'Unggah ulang' => ['variant' => 'danger',  'icon' => 'heroicon-m-arrow-path'],
            'Lihat'        => ['variant' => 'quiet',   'icon' => 'heroicon-m-eye'],
        ];

        // Build the checklist once. The table, the filter counters and the
        // progress bar all read from these groups so they can never disagree.
        $groups = [];
        $tot = ['items' => 0, 'done' => 0, 'docsDone' => 0, 'tasks' => 0, 'tasksDone' => 0, 'review' => 0];
        foreach ($steps as $step) {
            $rows = [];
            foreach ($step->requiredDocuments as $document) {
                $meta = $docStatus[$document->status] ?? $docStatus['draft'];
                $isDone = $meta['state'] === 'done';
                $latest = $document->submittedDocuments->sortByDesc('created_at')->first();
                $rows[] = [
                    'kind'   => 'doc',
                    'model'  => $document,
                    'meta'   => $meta,
                    'state'  => $meta['state'],
                    'by'     => ($isDone && $document->reviewer) ? $document->reviewer : $latest?->user,
                    'at'     => ($isDone && $document->reviewed_at) ? $document->reviewed_at : $latest?->created_at,
                    'files'  => $document->submittedDocuments->count(),
                    'action' => match (true) {
                        $isProjectLocked                             => 'Lihat',
                        $document->status === 'draft'                => 'Unggah',
                        $document->status === 'rejected'             => 'Unggah ulang',
                        $meta['state'] === 'review' && $isManager    => 'Review',
                        default                                      => 'Lihat',
                    },
                ];
            }
            foreach ($step->tasks as $task) {
                $rows[] = [
                    'kind'  => 'task',
                    'model' => $task,
                    'meta'  => $taskStatus[$task->status] ?? $taskStatus['pending'],
                    'state' => $task->status === 'completed' ? 'done' : 'open',
                ];
            }

            $rowsC = collect($rows);
            $done = $rowsC->where('state', 'done')->count();

            // What is holding this step up, worst first. Each bucket carries the
            // rows so the first one can be named ("Laporan Keuangan belum diunggah").
            $docsWith = fn (string $status) => $rowsC->filter(fn ($r) => $r['kind'] === 'doc' && $r['model']->status === $status);
            $buckets = [
                ['rows' => $docsWith('rejected'), 'one' => 'ditolak, perlu diunggah ulang', 'many' => 'ditolak'],
                ['rows' => $rowsC->filter(fn ($r) => $r['kind'] === 'task' && $r['model']->status === 'blocked'), 'one' => 'terhambat', 'many' => 'tugas terhambat'],
                ['rows' => $rowsC->where('state', 'review'), 'one' => 'menunggu review', 'many' => 'menunggu review'],
                ['rows' => $docsWith('draft'), 'one' => 'belum diunggah', 'many' => 'belum diunggah'],
                ['rows' => $rowsC->filter(fn ($r) => $r['kind'] === 'task' && in_array($r['model']->status, ['pending', 'in_progress'], true)), 'one' => 'belum selesai', 'many' => 'tugas belum selesai'],
            ];
            $block = null;
            foreach ($buckets as $bucket) {
                if ($bucket['rows']->isEmpty()) {
                    continue;
                }
                $first = $bucket['rows']->first();
                $block ??= [
                    'name' => $first['kind'] === 'doc' ? $first['model']->name : $first['model']->title,
                    'verb' => $bucket['one'],
                    'more' => $rowsC->where('state', '!=', 'done')->count() - 1,
                    'parts' => [],
                ];
                $block['parts'][] = $bucket['rows']->count() . ' ' . $bucket['many'];
            }

            $groups[] = [
                'step'   => $step,
                'rows'   => $rows,
                'total'  => count($rows),
                'done'   => $done,
                'review' => $rowsC->where('state', 'review')->count(),
                'meta'   => $stepStatus[$step->status] ?? $stepStatusDefault,
                'desc'   => trim(strip_tags((string) $step->description)),
                'block'  => $step->status === 'completed' ? null : $block,
            ];

            $tot['items'] += count($rows);
            $tot['done'] += $done;
            $tot['docsDone'] += $rowsC->where('kind', 'doc')->where('state', 'done')->count();
            $tot['tasks'] += $rowsC->where('kind', 'task')->count();
            $tot['tasksDone'] += $rowsC->where('kind', 'task')->where('state', 'done')->count();
            $tot['review'] += $rowsC->where('state', 'review')->count();
        }
        $tot['open'] = $tot['items'] - $tot['done'] - $tot['review'];

        $filters = [
            'all'    => ['label' => 'Semua',           'count' => $tot['items']],
            'open'   => ['label' => 'Perlu tindakan',  'count' => $tot['items'] - $tot['done']],
            'review' => ['label' => 'Menunggu review', 'count' => $tot['review']],
            'done'   => ['label' => 'Selesai',         'count' => $tot['done']],
        ];

        // The step(s) being worked on now: every running step, or else the first
        // unfinished one. Only these show their blocker line in the table, so
        // later steps that simply haven't started don't all shout "belum diunggah".
        $activeStepIds = collect($groups)->filter(fn ($g) => $g['step']->status === 'in_progress')->map(fn ($g) => $g['step']->id);
        if ($activeStepIds->isEmpty()) {
            $firstOpen = collect($groups)->first(fn ($g) => $g['step']->status !== 'completed' && $g['total'] > 0);
            $activeStepIds = collect($firstOpen ? [$firstOpen['step']->id] : []);
        }
        $activeStepIds = $activeStepIds->values()->all();

        // An upload in a step nobody has started yet is real but not next:
        // keep the button, drop the emphasis, so the current step stands out.
        $ctaVariant = fn (string $action, int $stepId) => $action === 'Unggah' && ! in_array($stepId, $activeStepIds, true)
            ? 'quiet'
            : $cta[$action]['variant'];

        // "Perlu tindakan" queue for the summary tab: everything unfinished,
        // worst first, then by step order. Documents have no due date of their
        // own, so the step order stands in for urgency.
        $actionQueue = collect($groups)->flatMap(function ($g) use ($activeStepIds) {
            $active = in_array($g['step']->id, $activeStepIds, true);

            return collect($g['rows'])
                ->where('state', '!=', 'done')
                ->map(function ($row) use ($g, $active) {
                    $status = $row['model']->status;
                    $rank = match (true) {
                        $row['kind'] === 'doc' && $status === 'rejected' => 0,
                        $row['kind'] === 'task' && $status === 'blocked' => 1,
                        $row['state'] === 'review'                       => 2,
                        $active && $row['kind'] === 'doc'                => 3,
                        $active                                          => 4,
                        $row['kind'] === 'doc'                           => 5,
                        default                                          => 6,
                    };

                    return $row + ['step' => $g['step'], 'rank' => $rank];
                });
        })->sortBy([['rank', 'asc'], [fn ($r) => $r['step']->order, 'asc']])->values();
        $stepsDone = collect($groups)->filter(fn ($g) => $g['step']->status === 'completed')->count();

        // Finished steps start collapsed; everything still in play starts open.
        $openGroups = collect($groups)->mapWithKeys(fn ($g) => [(string) $g['step']->id => $g['step']->status !== 'completed'])->all();

        $tabItems = array_values(array_filter([
            ['key' => 'ringkasan', 'label' => 'Ringkasan',           'count' => null],
            ['key' => 'alur',      'label' => 'Checklist',           'count' => $tot['items'] ?: null],
            ['key' => 'tim',       'label' => 'Tim',                 'count' => $members->count() ?: null],
            ['key' => 'legal',     'label' => 'Dokumen legal klien', 'count' => null],
            $showChaining ? ['key' => 'lanjutan', 'label' => 'Proyek lanjutan', 'count' => null] : null,
            $hasResult    ? ['key' => 'hasil',    'label' => 'Hasil',           'count' => count($deliverables) ?: null] : null,
            ['key' => 'aktivitas', 'label' => 'Aktivitas',           'count' => null],
        ]));
    @endphp

    <div
        class="pd"
        x-data="{
            tab: (window.location.hash || '#ringkasan').slice(1),
            tabs: @js(array_column($tabItems, 'key')),
            filter: 'all',
            q: '',
            tick: 0,
            open: @js($openGroups),
            init() {
                if (! this.tabs.includes(this.tab)) this.tab = 'ringkasan';
                this.$watch('tab', (v) => history.replaceState(null, '', '#' + v));
                window.addEventListener('hashchange', () => {
                    const next = window.location.hash.slice(1);
                    if (this.tabs.includes(next)) this.tab = next;
                });
                // Row states live in data attributes that Livewire morphs on every
                // round trip; bump a counter so the filters re-read them.
                Livewire.hook('morphed', () => this.tick++);
            },
            rows() { this.tick; return [...this.$root.querySelectorAll('[data-pd-row]')]; },
            fits(el, f = this.filter) {
                const st = el.dataset.st;
                if (f === 'open') return st !== 'done';
                if (f === 'review') return st === 'review';
                if (f === 'done') return st === 'done';
                return true;
            },
            hit(el) {
                this.tick;
                const q = this.q.trim().toLowerCase();
                return this.fits(el) && (! q || el.dataset.n.includes(q));
            },
            get filtering() { return this.filter !== 'all' || this.q.trim() !== ''; },
            count(f) { return this.rows().filter((el) => this.fits(el, f)).length; },
            get shown() { return this.rows().filter((el) => this.hit(el)).length; },
            groupShown(g) { return ! this.filtering || this.rows().some((el) => el.dataset.g === String(g) && this.hit(el)); },
            groupOpen(g) { return this.filtering || (this.open[g] ?? true); },
            kindShown(g, k) { return ! this.filtering || this.rows().some((el) => el.dataset.g === String(g) && el.dataset.k === k && this.hit(el)); },
            toggle(g) { this.open[g] = ! (this.open[g] ?? true); },
            reset() { this.filter = 'all'; this.q = ''; },
            // Jump from the summary into the checklist, opening the step and
            // scrolling the row (or the step) into view.
            goTo(sid, elId = null, filter = 'all') {
                this.tab = 'alur'; this.filter = filter; this.q = ''; this.open[sid] = true;
                this.$nextTick(() => document.getElementById(elId || ('step-' + sid))?.scrollIntoView({ behavior: 'smooth', block: 'center' }));
            },
        }"
    >
        {{-- ============ META: one line of facts under the page heading ============ --}}
        <div class="pd-meta" aria-label="Ringkasan proyek">
            <span class="pd-pill pd-t-{{ $statusTone }}"><span class="pd-dot"></span>{{ $statusLabel }}</span>

            @if ($record->client)
                <a href="{{ \App\Filament\Resources\ClientResource::getUrl('view', ['record' => $record->client]) }}"
                   wire:navigate class="pd-meta-item pd-link" title="Buka detail klien">
                    <x-heroicon-m-building-office-2 class="pd-ic" />{{ $record->client->name }}
                </a>
            @endif

            <button type="button" class="pd-meta-item" x-on:click="tab = 'tim'" title="Lihat tim proyek">
                <span class="pd-k">PIC</span>
                @if ($record->pic)
                    <span class="pd-av">@include('livewire.projects.partials.avatar', ['user' => $record->pic])</span>{{ $record->pic->name }}
                @else
                    <span class="pd-k">Belum ditentukan</span>
                @endif
            </button>

            <button type="button" class="pd-meta-item" x-on:click="tab = 'tim'" title="Lihat tim proyek">
                <span class="pd-k">Tim</span>
                @if ($members->isNotEmpty())
                    <span class="pd-avs">
                        @foreach ($members->take(4) as $member)
                            <span class="pd-av" title="{{ $member->name }}">@include('livewire.projects.partials.avatar', ['user' => $member])</span>
                        @endforeach
                        @if ($members->count() > 4)
                            <span class="pd-av pd-av-more">+{{ $members->count() - 4 }}</span>
                        @endif
                    </span>
                @else
                    <span class="pd-k">Belum ada</span>
                @endif
            </button>

            @if ($record->due_date)
                <span class="pd-meta-item">
                    <x-heroicon-m-calendar class="pd-ic" />{{ $record->due_date->translatedFormat('d M Y') }}
                    @if ($dueText)
                        <b class="pd-due pd-due-{{ $dueTone }}">{{ $dueText }}</b>
                    @endif
                </span>
            @endif

            @if ($record->sop)
                <span class="pd-meta-item" title="Tahapan proyek ini mengikuti SOP {{ $record->sop->name }}">
                    <x-heroicon-m-square-3-stack-3d class="pd-ic" />{{ $record->sop->name }}
                </span>
            @endif

            @if ($record->parentProject)
                <a href="{{ \App\Filament\Resources\ProjectResource::getUrl('view', ['record' => $record->parentProject]) }}"
                   wire:navigate class="pd-meta-item pd-link-quiet">
                    <x-heroicon-m-arrow-uturn-left class="pd-ic" />Lanjutan dari {{ $record->parentProject->name }}
                </a>
            @endif
        </div>

        {{-- ============ LOCK NOTICE ============ --}}
        @if (! $clientActive)
            <div class="pd-lock" role="status">
                <x-heroicon-m-lock-closed class="pd-ic" />
                <div>
                    <p class="pd-lock-title">Proyek terkunci</p>
                    <p>Klien "{{ $record->client->name }}" berstatus tidak aktif. Tidak ada perubahan yang bisa dilakukan sampai klien diaktifkan kembali.</p>
                </div>
            </div>
        @endif

        {{-- ============ PROGRESS: one bar split by what is actually done ============ --}}
        @if ($tot['items'] > 0)
            <div class="pd-progress">
                <span class="pd-progress-num"><b>{{ $tot['done'] }}</b>/{{ $tot['items'] }} selesai</span>
                <div class="pd-pbar" role="img"
                     aria-label="{{ $tot['docsDone'] }} dokumen disetujui, {{ $tot['tasksDone'] }} tugas selesai, {{ $tot['review'] }} menunggu review, {{ $tot['open'] }} terbuka">
                    <i class="is-doc" style="flex: {{ $tot['docsDone'] }}"></i>
                    <i class="is-task" style="flex: {{ $tot['tasksDone'] }}"></i>
                    <i class="is-review" style="flex: {{ $tot['review'] }}"></i>
                    <i class="is-open" style="flex: {{ $tot['open'] }}"></i>
                </div>
                <div class="pd-legend" aria-hidden="true">
                    <span><i class="is-doc"></i>{{ $tot['docsDone'] }} dokumen disetujui</span>
                    @if ($tot['tasks'] > 0)
                        <span><i class="is-task"></i>{{ $tot['tasksDone'] }} tugas selesai</span>
                    @endif
                    @if ($tot['review'] > 0)
                        <span><i class="is-review"></i>{{ $tot['review'] }} menunggu review</span>
                    @endif
                    <span><i class="is-open"></i>{{ $tot['open'] }} terbuka</span>
                </div>
            </div>
        @endif

        {{-- ============ TABS ============ --}}
        <nav class="pd-tabs" role="tablist" aria-label="Bagian proyek">
            @foreach ($tabItems as $t)
                <button type="button" role="tab" class="pd-tab"
                        x-on:click="tab = '{{ $t['key'] }}'"
                        :class="tab === '{{ $t['key'] }}' && 'is-on'"
                        :aria-selected="tab === '{{ $t['key'] }}'"
                        :tabindex="tab === '{{ $t['key'] }}' ? 0 : -1"
                        x-on:keydown.arrow-right.prevent="$el.nextElementSibling?.focus()"
                        x-on:keydown.arrow-left.prevent="$el.previousElementSibling?.focus()">
                    {{ $t['label'] }}
                    @if ($t['count'] !== null)
                        <span class="n">{{ $t['count'] }}</span>
                    @endif
                </button>
            @endforeach
        </nav>

        {{-- ============ PANEL: CHECKLIST ============ --}}
        <div x-show="tab === 'alur'" x-cloak role="tabpanel" aria-label="Checklist">
            @if ($steps->isEmpty())
                <div class="pd-panel pd-empty">
                    <x-heroicon-o-queue-list class="pd-empty-ic" />
                    <p class="pd-empty-title">Belum ada tahapan</p>
                    <p>Tahapan mengikuti SOP yang dipilih saat proyek dibuat.</p>
                </div>
            @else
                <div class="pd-toolbar">
                    <div class="pd-seg" role="group" aria-label="Saring checklist">
                        @foreach ($filters as $key => $f)
                            <button type="button" x-on:click="filter = '{{ $key }}'"
                                    :class="filter === '{{ $key }}' && 'is-on'"
                                    :aria-pressed="filter === '{{ $key }}'">
                                {{ $f['label'] }} <b x-text="count('{{ $key }}')">{{ $f['count'] }}</b>
                            </button>
                        @endforeach
                    </div>
                    <label class="pd-search">
                        <x-heroicon-m-magnifying-glass class="pd-ic" />
                        <input type="search" x-model="q" placeholder="Cari dokumen atau tugas" aria-label="Cari dokumen atau tugas">
                    </label>
                </div>

                {{-- Each step is its own card. Inside it, documents and tasks are
                     shown differently on purpose: a document is a file that goes
                     through upload → review → approval; a task is a to-do that is
                     simply ticked off. --}}
                <div class="pd-steps">
                    @foreach ($groups as $g)
                        @php
                            $step = $g['step'];
                            $sid = $step->id;
                            $docRows = array_values(array_filter($g['rows'], fn ($r) => $r['kind'] === 'doc'));
                            $taskRows = array_values(array_filter($g['rows'], fn ($r) => $r['kind'] === 'task'));
                            $docsDone = count(array_filter($docRows, fn ($r) => $r['state'] === 'done'));
                            $tasksDone = count(array_filter($taskRows, fn ($r) => $r['state'] === 'done'));
                        @endphp
                        <section id="step-{{ $sid }}" wire:key="step-{{ $sid }}" x-show="groupShown({{ $sid }})"
                                 class="pd-group" :class="groupOpen({{ $sid }}) ? 'is-open' : 'is-closed'">
                            <div class="pd-grp" role="button" tabindex="0"
                                 :aria-expanded="groupOpen({{ $sid }})"
                                 x-on:click="toggle({{ $sid }})"
                                 x-on:keydown.enter.prevent="toggle({{ $sid }})"
                                 x-on:keydown.space.prevent="toggle({{ $sid }})">
                                <x-heroicon-m-chevron-down class="pd-grp-chev" x-bind:class="! groupOpen({{ $sid }}) && 'is-closed'" />
                                <span class="pd-grp-no">{{ str_pad($step->order, 2, '0', STR_PAD_LEFT) }}</span>
                                <h3 class="pd-grp-name">{{ $step->name }}</h3>
                                <span class="pd-pill pd-t-{{ $g['meta']['tone'] }}"><span class="pd-dot"></span>{{ $g['meta']['label'] }}</span>
                                <span class="pd-grp-prog">
                                    @if ($g['total'] > 0)
                                        {{ $g['done'] }}/{{ $g['total'] }}
                                        <span @class(['pd-minibar', 'is-full' => $g['done'] === $g['total']])><i style="width: {{ round($g['done'] / $g['total'] * 100) }}%"></i></span>
                                    @endif
                                </span>
                                @if ($canDeleteProjectSteps)
                                    <button type="button" class="pd-iconbtn" aria-label="Hapus tahapan {{ $step->name }}" title="Hapus tahapan"
                                            x-on:click.stop="$dispatch('open-modal', { id: 'delete-step-modal-{{ $sid }}' })">
                                        <x-heroicon-m-trash class="pd-ic" />
                                    </button>
                                @endif
                                @if ($g['block'] && in_array($sid, $activeStepIds, true))
                                    <p class="pd-grp-sub" title="{{ implode(' · ', $g['block']['parts']) }}">
                                        <span class="pd-k">Hambatan</span>
                                        <b>{{ $g['block']['name'] }}</b> {{ $g['block']['verb'] . ($g['block']['more'] > 0 ? ', +' . $g['block']['more'] . ' lainnya' : '') }}
                                    </p>
                                @endif
                            </div>

                            <div class="pd-grp-body" x-show="groupOpen({{ $sid }})">
                                @if ($g['desc'] !== '')
                                    <p class="pd-grp-desc">{{ $g['desc'] }}</p>
                                @endif
                                @if ($g['total'] === 0)
                                    <p class="pd-grp-desc">Tahapan ini tidak memiliki tugas atau dokumen.</p>
                                @endif

                                {{-- ---------- Documents ---------- --}}
                                @if ($docRows)
                                    <div class="pd-sec-cols" x-show="kindShown({{ $sid }}, 'doc')" aria-hidden="true">
                                        <span class="pd-kind-lbl"><x-heroicon-m-document-text class="pd-ic" />Dokumen <b>{{ $docsDone }}/{{ count($docRows) }}</b></span>
                                        <span class="c-flow">Alur</span><span>Status</span><span class="c-by">Oleh</span><span></span>
                                    </div>
                                    @foreach ($docRows as $row)
                                        @php
                                            $m = $row['model'];
                                            $tone = $row['meta']['tone'];
                                            $docDesc = trim(strip_tags((string) $m->description));
                                            // Upload → Review → Setuju. Each dot: '' (not yet), done, cur (waiting here), bad (rejected here).
                                            $flow = match ($m->status) {
                                                'approved', 'approved_without_document' => ['done', 'done', 'done'],
                                                'pending_review', 'uploaded'            => ['done', 'cur', ''],
                                                'rejected'                              => ['done', 'bad', ''],
                                                default                                 => ['', '', ''],
                                            };
                                            $docMeta = ($m->is_required ? 'Wajib' : 'Opsional')
                                                . ' · ' . ($row['files'] > 0 ? $row['files'] . ' berkas' : 'belum ada berkas')
                                                . ($docDesc !== '' ? ' · ' . $docDesc : '');
                                        @endphp
                                        <div wire:key="doc-{{ $m->id }}" id="pd-doc-{{ $m->id }}" data-pd-row data-k="doc" data-g="{{ $sid }}" data-st="{{ $row['state'] }}" data-n="{{ mb_strtolower($m->name) }}"
                                             x-show="hit($el)"
                                             @class(['pd-doc', 'is-done' => $row['state'] === 'done'])
                                             x-on:click="$dispatch('open-modal', { id: 'document-modal-{{ $m->id }}' })">
                                            <span class="pd-ft pd-t-{{ $tone }}" aria-hidden="true">
                                                <x-filament::icon :icon="$row['state'] === 'done' ? 'heroicon-m-document-check' : 'heroicon-m-document-text'" class="pd-ft-ic" />
                                                @if ($row['files'] > 0)
                                                    <span class="pd-ft-n">{{ $row['files'] }}</span>
                                                @endif
                                            </span>
                                            <span class="pd-item">
                                                <span class="pd-item-name" title="{{ $m->name }}">{{ $m->name }}</span>
                                                <span class="pd-item-desc" title="{{ $docMeta }}">{{ $docMeta }}</span>
                                            </span>
                                            <span class="pd-flow c-flow pd-t-{{ $tone }}" aria-hidden="true">
                                                <span class="pd-flow-track">
                                                    <i class="{{ $flow[0] }}"></i><b @class(['done' => $flow[1] !== ''])></b><i class="{{ $flow[1] }}"></i><b @class(['done' => $flow[2] !== ''])></b><i class="{{ $flow[2] }}"></i>
                                                </span>
                                                <span class="pd-flow-lbl"><span>Unggah</span><span>Review</span><span>Setuju</span></span>
                                            </span>
                                            <span class="pd-status">
                                                <span class="pd-pill pd-badge pd-t-{{ $tone }}"><x-filament::icon :icon="$row['meta']['icon']" class="pd-pill-ic" />{{ $row['meta']['label'] }}</span>
                                            </span>
                                            <span class="pd-by c-by">
                                                @if ($row['by'])
                                                    <span class="pd-av">@include('livewire.projects.partials.avatar', ['user' => $row['by']])</span>
                                                    <span class="pd-by-txt">
                                                        <span class="nm" title="{{ $row['by']->name }}">{{ $row['by']->name }}</span>
                                                        @if ($row['at'])
                                                            <small>{{ $date($row['at']) }}</small>
                                                        @endif
                                                    </span>
                                                @else
                                                    <span class="pd-k">Belum ada berkas</span>
                                                @endif
                                            </span>
                                            <span class="pd-act">
                                                <button type="button" class="pd-cta pd-cta-{{ $ctaVariant($row['action'], $sid) }}"
                                                        aria-label="{{ $row['action'] }} dokumen {{ $m->name }}">
                                                    <x-filament::icon :icon="$cta[$row['action']]['icon']" class="pd-cta-ic" />{{ $row['action'] }}
                                                </button>
                                                @if ($canDeleteProjectRequiredDocuments)
                                                    <button type="button" class="pd-iconbtn" aria-label="Hapus dokumen {{ $m->name }}" title="Hapus dokumen"
                                                            x-on:click.stop="$dispatch('open-modal', { id: 'delete-required-document-modal-{{ $m->id }}' })">
                                                        <x-heroicon-m-trash class="pd-ic" />
                                                    </button>
                                                @endif
                                            </span>
                                        </div>
                                    @endforeach
                                @endif

                                {{-- ---------- Tasks ---------- --}}
                                @if ($taskRows)
                                    <div @class(['pd-tasks', 'is-alone' => ! $docRows]) x-show="kindShown({{ $sid }}, 'task')">
                                        <div class="pd-tasks-hd">
                                            <span class="pd-kind-lbl"><x-heroicon-m-check class="pd-ic" />Tugas tahap ini <b>{{ $tasksDone }}/{{ count($taskRows) }}</b></span>
                                            @if ($canChangeTaskStatus)
                                                <span class="pd-k">Centang bila selesai</span>
                                            @endif
                                        </div>
                                        @foreach ($taskRows as $row)
                                            @php
                                                $m = $row['model'];
                                                $taskDesc = trim(strip_tags((string) $m->description));
                                            @endphp
                                            {{-- The filter state sits on the inner row, not on this x-data
                                                 root: Livewire's morph skipped attribute patches on the
                                                 Alpine root while a filter was active, leaving it stale. --}}
                                            <div wire:key="task-{{ $m->id }}" id="pd-task-{{ $m->id }}" x-show="hit($el.firstElementChild)" x-data="{ comments: false, menu: false }">
                                                <div data-pd-row data-k="task" data-g="{{ $sid }}" data-st="{{ $row['state'] }}" data-n="{{ mb_strtolower($m->title) }}"
                                                     @class(['pd-task', 'is-done' => $row['state'] === 'done'])>
                                                    <input type="checkbox" class="pd-check"
                                                           wire:click="toggleTaskStatus({{ $m->id }})"
                                                           aria-label="Tandai tugas {{ $m->title }} selesai"
                                                           @checked($m->status === 'completed')
                                                           @disabled(! $canChangeTaskStatus)>
                                                    <span class="pd-item">
                                                        <span class="pd-item-name" title="{{ $m->title }}">{{ $m->title }}</span>
                                                        @if ($taskDesc !== '')
                                                            <span class="pd-item-desc" title="{{ $taskDesc }}">{{ $taskDesc }}</span>
                                                        @endif
                                                    </span>
                                                    <span class="pd-task-at">{{ $row['state'] === 'done' ? 'Selesai ' . $date($m->updated_at) : '' }}</span>
                                                    <span class="pd-status">
                                                        @if ($canChangeTaskStatus)
                                                            <span class="pd-dd" x-on:click.outside="menu = false">
                                                                <button type="button" class="pd-pill pd-badge pd-t-{{ $row['meta']['tone'] }} is-btn"
                                                                        x-on:click="menu = ! menu" :aria-expanded="menu" aria-haspopup="menu" title="Ubah status tugas">
                                                                    <x-filament::icon :icon="$row['meta']['icon']" class="pd-pill-ic" />{{ $row['meta']['label'] }}
                                                                    <x-heroicon-m-chevron-down class="pd-pill-chev" />
                                                                </button>
                                                                <span class="pd-menu" role="menu" x-show="menu" x-cloak
                                                                      x-transition:enter="pd-fade" x-transition:enter-start="pd-fade-from">
                                                                    @foreach ($taskStatus as $key => $meta)
                                                                        <button type="button" role="menuitem"
                                                                                @class(['is-current' => $m->status === $key])
                                                                                x-on:click="menu = false; $dispatch('open-modal', { id: 'confirm-status-modal-{{ $m->id }}' }); $wire.updateTaskStatus({{ $m->id }}, '{{ $key }}')">
                                                                            <span class="pd-t-{{ $meta['tone'] }} pd-menu-ic"><x-filament::icon :icon="$meta['icon']" class="pd-pill-ic" /></span>{{ $meta['label'] }}
                                                                        </button>
                                                                    @endforeach
                                                                </span>
                                                            </span>
                                                        @else
                                                            <span class="pd-pill pd-badge pd-t-{{ $row['meta']['tone'] }}"><x-filament::icon :icon="$row['meta']['icon']" class="pd-pill-ic" />{{ $row['meta']['label'] }}</span>
                                                        @endif
                                                    </span>
                                                    <span class="pd-act">
                                                        <button type="button" class="pd-btn" x-on:click="comments = ! comments"
                                                                :class="comments && 'is-on'" :aria-expanded="comments"
                                                                aria-label="Komentar tugas {{ $m->title }}">
                                                            <x-heroicon-m-chat-bubble-left-right class="pd-ic" />{{ $m->comments->count() }}
                                                        </button>
                                                    </span>
                                                </div>
                                                <div x-show="comments" x-cloak class="pd-sub">
                                                    <livewire:projects.forms.create-task-comment :task="$m" :wire:key="'comments-'.$m->id" />
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>
                                @endif
                            </div>
                        </section>
                    @endforeach

                    <p class="pd-nomatch" x-show="filtering && shown === 0" x-cloak>
                        Tidak ada item yang cocok.
                        <button type="button" class="pd-textbtn" x-on:click="reset()">Hapus saringan</button>
                    </p>

                    <div class="pd-foot">
                        <span x-show="! filtering">{{ $tot['items'] }} item &middot; {{ $tot['done'] }} selesai</span>
                        <span x-show="filtering" x-cloak>
                            Menampilkan <b x-text="shown"></b> dari {{ $tot['items'] }} item
                            &middot; <button type="button" class="pd-textbtn" x-on:click="reset()">Hapus saringan</button>
                        </span>
                    </div>
                </div>
            @endif
        </div>

        {{-- ============ PANEL: RINGKASAN ============
             Entry point for a PIC who drops by to check on the project: the
             brief, what needs doing next, where each step stands, and the
             facts. The checklist stays the place where the work happens. --}}
        <div x-show="tab === 'ringkasan'" x-cloak role="tabpanel" aria-label="Ringkasan proyek" class="pd-sum">
            <div class="pd-sum-main">
                {{-- Brief --}}
                <section class="pd-panel">
                    <header class="pd-sec-hd">
                        <h2 class="pd-h">Brief proyek</h2>
                        @unless ($isProjectLocked)
                            <a href="{{ \App\Filament\Resources\ProjectResource::getUrl('edit', ['record' => $record]) }}" class="pd-btn">
                                <x-heroicon-m-pencil-square class="pd-ic" />Edit
                            </a>
                        @endunless
                    </header>
                    <div class="pd-pad">
                        @if (filled(strip_tags((string) $record->description)))
                            <div class="prose prose-sm max-w-none dark:prose-invert pd-brief">
                                {!! str($record->description)->sanitizeHtml() !!}
                            </div>
                        @else
                            <p class="pd-muted-text pd-flush">Belum ada brief. Tulis konteks kasus, ruang lingkup, dan hasil yang diharapkan supaya tim tidak perlu bertanya ulang.</p>
                        @endif
                    </div>
                </section>

                {{-- Action queue --}}
                <section class="pd-panel">
                    <header class="pd-sec-hd">
                        <h2 class="pd-h">Perlu tindakan <span class="pd-count">{{ $actionQueue->count() }}</span></h2>
                        @if ($actionQueue->count() > 0)
                            <button type="button" class="pd-btn" x-on:click="tab = 'alur'; filter = 'open'; q = ''">Lihat semua di checklist</button>
                        @endif
                    </header>
                    @if ($actionQueue->isEmpty())
                        <p class="pd-muted-text pd-pad pd-flush">{{ $tot['items'] > 0 ? 'Semua dokumen dan tugas sudah selesai.' : 'Belum ada dokumen atau tugas di proyek ini.' }}</p>
                    @else
                        <ul class="pd-queue">
                            @foreach ($actionQueue->take(6) as $item)
                                @php $m = $item['model']; $isDoc = $item['kind'] === 'doc'; @endphp
                                <li>
                                    @if ($isDoc)
                                        <span @class(['pd-glyph', 'pd-g-' . match ($m->status) { 'pending_review', 'uploaded' => 'review', 'rejected' => 'bad', default => 'open' }])></span>
                                    @else
                                        <span @class(['pd-glyph pd-glyph-sq', 'pd-g-bad' => $m->status === 'blocked', 'pd-g-open' => $m->status !== 'blocked'])></span>
                                    @endif
                                    <span class="pd-item">
                                        <span class="pd-item-name" title="{{ $isDoc ? $m->name : $m->title }}">{{ $isDoc ? $m->name : $m->title }}</span>
                                        <span class="pd-item-desc">{{ str_pad($item['step']->order, 2, '0', STR_PAD_LEFT) }} · {{ $item['step']->name }}</span>
                                    </span>
                                    <span class="pd-pill pd-badge pd-t-{{ $item['meta']['tone'] }}"><x-filament::icon :icon="$item['meta']['icon']" class="pd-pill-ic" />{{ $item['meta']['label'] }}</span>
                                    @if ($isDoc)
                                        <button type="button" class="pd-cta pd-cta-{{ $ctaVariant($item['action'], $item['step']->id) }}"
                                                x-on:click="$dispatch('open-modal', { id: 'document-modal-{{ $m->id }}' })">
                                            <x-filament::icon :icon="$cta[$item['action']]['icon']" class="pd-cta-ic" />{{ $item['action'] }}
                                        </button>
                                    @else
                                        <button type="button" class="pd-cta pd-cta-neutral" x-on:click="goTo({{ $item['step']->id }}, 'pd-task-{{ $m->id }}')">
                                            Buka<x-filament::icon icon="heroicon-m-arrow-right" class="pd-cta-ic" />
                                        </button>
                                    @endif
                                </li>
                            @endforeach
                        </ul>
                        @if ($actionQueue->count() > 6)
                            <p class="pd-sec-ft">+{{ $actionQueue->count() - 6 }} item lainnya di checklist</p>
                        @endif
                    @endif
                </section>

                {{-- Step position --}}
                @if ($groups)
                    <section class="pd-panel">
                        <header class="pd-sec-hd">
                            <h2 class="pd-h">Posisi tahapan</h2>
                            <span class="pd-k">{{ $stepsDone }}/{{ count($groups) }} tahap selesai</span>
                        </header>
                        <ol class="pd-stepper">
                            @foreach ($groups as $g)
                                @php $st = $g['step']->status; @endphp
                                <li>
                                    <button type="button" class="pd-step" x-on:click="goTo({{ $g['step']->id }})">
                                        <span @class(['pd-glyph', 'pd-g-done' => $st === 'completed', 'pd-g-run' => $st === 'in_progress', 'pd-g-open' => ! in_array($st, ['completed', 'in_progress'], true)])>
                                            @if ($st === 'completed')<x-heroicon-m-check />@endif
                                        </span>
                                        <span class="pd-step-body">
                                            <span class="pd-step-line">
                                                <span class="pd-grp-no">{{ str_pad($g['step']->order, 2, '0', STR_PAD_LEFT) }}</span>
                                                <span class="pd-step-name">{{ $g['step']->name }}</span>
                                                <span class="pd-pill pd-t-{{ $g['meta']['tone'] }}">{{ $g['meta']['label'] }}</span>
                                            </span>
                                            @if ($g['block'])
                                                <span class="pd-step-sub">{{ implode(' · ', $g['block']['parts']) }}</span>
                                            @endif
                                        </span>
                                        <span class="pd-step-prog">{{ $g['total'] > 0 ? $g['done'] . '/' . $g['total'] : '–' }}</span>
                                    </button>
                                </li>
                            @endforeach
                        </ol>
                    </section>
                @endif
            </div>

            <aside class="pd-sum-side">
                {{-- Facts --}}
                <section class="pd-panel">
                    <header class="pd-sec-hd"><h2 class="pd-h">Info proyek</h2></header>
                    <dl class="pd-info">
                        <div><dt>Status</dt><dd><span class="pd-pill pd-t-{{ $statusTone }}"><span class="pd-dot"></span>{{ $statusLabel }}</span></dd></div>
                        @if ($record->client)
                            <div><dt>Klien</dt><dd><a href="{{ \App\Filament\Resources\ClientResource::getUrl('view', ['record' => $record->client]) }}" wire:navigate class="pd-link">{{ $record->client->name }}</a></dd></div>
                        @endif
                        <div><dt>Tenggat</dt><dd>
                            @if ($record->due_date)
                                {{ $record->due_date->translatedFormat('d M Y') }}
                                @if ($dueText)<span class="pd-due pd-due-{{ $dueTone }}">{{ $dueText }}</span>@endif
                            @else
                                <span class="pd-k">Belum ditentukan</span>
                            @endif
                        </dd></div>
                        @if ($record->priority)
                            <div><dt>Prioritas</dt><dd>
                                @if ($record->priority === 'urgent')
                                    <span class="pd-pill pd-t-bad">Mendesak</span>
                                @else
                                    {{ ucfirst($record->priority) }}
                                @endif
                            </dd></div>
                        @endif
                        @if ($record->type)
                            <div><dt>Tipe</dt><dd>{{ ucwords(str_replace('_', ' ', $record->type)) }}</dd></div>
                        @endif
                        <div><dt>Departemen</dt><dd>{{ $record->department?->name ?? 'Belum ditentukan' }}</dd></div>
                        <div><dt>SOP</dt><dd>{{ $record->sop?->name ?? 'Tanpa SOP' }}</dd></div>
                        @if ($record->parentProject)
                            <div><dt>Lanjutan dari</dt><dd><a href="{{ \App\Filament\Resources\ProjectResource::getUrl('view', ['record' => $record->parentProject]) }}" wire:navigate class="pd-link">{{ $record->parentProject->name }}</a></dd></div>
                        @endif
                        <div><dt>Dibuat</dt><dd>{{ $record->created_at?->translatedFormat('d M Y') }}</dd></div>
                        <div><dt>Diperbarui</dt><dd>{{ $record->updated_at?->translatedFormat('d M Y') }}</dd></div>
                    </dl>
                </section>

                {{-- Team, read-only; managing it happens on the Tim tab --}}
                <section class="pd-panel">
                    <header class="pd-sec-hd">
                        <h2 class="pd-h">Tim <span class="pd-count">{{ $members->count() }}</span></h2>
                        <button type="button" class="pd-btn" x-on:click="tab = 'tim'">Kelola</button>
                    </header>
                    <ul class="pd-people">
                        <li>
                            @if ($record->pic)
                                <span class="pd-av pd-av-lg">@include('livewire.projects.partials.avatar', ['user' => $record->pic])</span>
                                <span class="pd-item"><span class="pd-item-name">{{ $record->pic->name }}</span><span class="pd-item-desc">Penanggung jawab (PIC)</span></span>
                            @else
                                <span class="pd-av pd-av-lg pd-av-more">?</span>
                                <span class="pd-item"><span class="pd-item-name">PIC belum ditentukan</span><span class="pd-item-desc">Atur di tab Tim</span></span>
                            @endif
                        </li>
                        @foreach ($members->reject(fn ($u) => $u->id === $record->pic_id)->take(5) as $member)
                            <li>
                                <span class="pd-av pd-av-lg">@include('livewire.projects.partials.avatar', ['user' => $member])</span>
                                <span class="pd-item"><span class="pd-item-name">{{ $member->name }}</span><span class="pd-item-desc">Anggota tim</span></span>
                            </li>
                        @endforeach
                    </ul>
                    @php $hiddenMembers = $members->reject(fn ($u) => $u->id === $record->pic_id)->count() - 5; @endphp
                    @if ($hiddenMembers > 0)
                        <p class="pd-sec-ft">+{{ $hiddenMembers }} anggota lainnya</p>
                    @endif
                </section>

                {{-- Result files, once there are any --}}
                @if (! empty($deliverables))
                    <section class="pd-panel">
                        <header class="pd-sec-hd">
                            <h2 class="pd-h">Berkas hasil <span class="pd-count">{{ count($deliverables) }}</span></h2>
                            <button type="button" class="pd-btn" x-on:click="tab = 'hasil'">Semua</button>
                        </header>
                        <ul class="pd-people">
                            @foreach (array_slice($deliverables, 0, 3) as $file)
                                <li>
                                    <span class="pd-file-ic"><x-heroicon-m-document class="pd-ic" /></span>
                                    <span class="pd-item"><span class="pd-item-name" title="{{ $file['name'] }}">{{ $file['name'] }}</span>
                                        <span class="pd-item-desc">@if ($file['size']){{ number_format($file['size'] / 1024, 0) }} KB @endif</span></span>
                                    @if ($file['url'])
                                        <a href="{{ $file['url'] }}" target="_blank" rel="noopener" class="pd-iconbtn is-visible" aria-label="Unduh {{ $file['name'] }}" title="Unduh">
                                            <x-heroicon-m-arrow-down-tray class="pd-ic" />
                                        </a>
                                    @endif
                                </li>
                            @endforeach
                        </ul>
                    </section>
                @endif
            </aside>
        </div>

        {{-- ============ PANEL: TIM ============ --}}
        <div x-show="tab === 'tim'" x-cloak role="tabpanel" aria-label="Tim proyek" class="pd-panel">
            <div class="pd-team-pic">
                @livewire('projects.components.project-person-in-charge', ['project' => $record])
            </div>
            <div>
                @livewire('projects.components.project-member', ['project' => $record])
            </div>
        </div>

        {{-- ============ PANEL: DOKUMEN LEGAL KLIEN ============ --}}
        <div x-show="tab === 'legal'" x-cloak role="tabpanel" aria-label="Dokumen legal klien"
             class="pd-panel [&>div>div]:!rounded-none [&>div>div]:!shadow-none [&>div>div]:!bg-transparent">
            @livewire('projects.components.project-client-legal', ['project' => $record])
        </div>

        {{-- ============ PANEL: PROYEK LANJUTAN ============ --}}
        @if ($showChaining)
            <div x-show="tab === 'lanjutan'" x-cloak role="tabpanel" aria-label="Proyek lanjutan" class="pd-panel pd-pad [&>div>div]:!mt-0">
                @livewire('projects.components.project-chaining', ['project' => $record])
            </div>
        @endif

        {{-- ============ PANEL: HASIL ============ --}}
        @if ($hasResult)
            <div x-show="tab === 'hasil'" x-cloak role="tabpanel" aria-label="Hasil proyek" class="pd-panel pd-result">
                <div class="pd-pad">
                    <h2 class="pd-h">File deliverable</h2>
                    @if (empty($deliverables))
                        <p class="pd-muted-text">Belum ada file deliverable. Gunakan tombol "Perbarui Deliverable" di atas untuk mengunggah.</p>
                    @else
                        <ul class="pd-files-list">
                            @foreach ($deliverables as $file)
                                <li>
                                    <span class="pd-file-ic"><x-heroicon-m-document class="pd-ic" /></span>
                                    <div class="pd-file-body">
                                        <p class="pd-file-name">{{ $file['name'] }}</p>
                                        <p class="pd-file-meta">
                                            @if ($file['size']) {{ number_format($file['size'] / 1024, 0) }} KB @endif
                                            @if ($file['size'] && $file['uploaded_at']) &middot; @endif
                                            @if ($file['uploaded_at']) {{ $date($file['uploaded_at']) }} @endif
                                        </p>
                                    </div>
                                    @if ($file['url'])
                                        <a href="{{ $file['url'] }}" target="_blank" rel="noopener" class="pd-btn is-accent">
                                            <x-heroicon-m-arrow-down-tray class="pd-ic" />Unduh
                                        </a>
                                    @endif
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>
                <div class="pd-pad">
                    <h2 class="pd-h">Catatan penyelesaian</h2>
                    @if (filled($record->result_notes))
                        <div class="prose prose-sm mt-3 max-w-none dark:prose-invert">
                            {!! str($record->result_notes)->sanitizeHtml() !!}
                        </div>
                    @else
                        <p class="pd-muted-text">Belum ada catatan penyelesaian.</p>
                    @endif
                </div>
            </div>
        @endif

        {{-- ============ PANEL: AKTIVITAS ============ --}}
        {{-- Lazy: the feed queries only once this panel is scrolled into view (i.e. the tab opens). --}}
        <div x-show="tab === 'aktivitas'" x-cloak role="tabpanel" aria-label="Aktivitas proyek" class="pd-panel">
            <livewire:projects.components.project-activity-feed :project="$record" lazy />
        </div>

        {{-- ============ MODAL HOSTS ============
             Kept outside the table and the tab panels: a modal inside a hidden
             panel can't open, and Filament renders each modal root as an
             inline-block that would otherwise add a phantom line to a row. --}}
        <div class="pd-hosts">
            @foreach ($groups as $g)
                @php $step = $g['step']; @endphp
                @foreach ($step->requiredDocuments as $document)
                    <x-filament::modal id="document-modal-{{ $document->id }}" width="4xl" slide-over>
                        @livewire('projects.modals.document-modal', ['document' => $document], key('document-modal-' . $document->id . time()))
                    </x-filament::modal>

                    @if ($canDeleteProjectRequiredDocuments)
                        <x-filament::modal id="delete-required-document-modal-{{ $document->id }}" width="md">
                            <div class="space-y-6 p-2">
                                <div class="flex items-start gap-4">
                                    <div class="flex h-12 w-12 flex-shrink-0 items-center justify-center rounded-full bg-gray-100 dark:bg-gray-800">
                                        <x-heroicon-o-trash class="h-6 w-6 text-red-500 dark:text-red-400" />
                                    </div>
                                    <div class="min-w-0">
                                        <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Hapus dokumen persyaratan</h2>
                                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Persyaratan ini akan dihapus dari tahapan dan progres proyek dihitung ulang.</p>
                                    </div>
                                </div>

                                <div class="rounded-lg border border-gray-200 bg-gray-50 p-4 dark:border-gray-700 dark:bg-gray-900">
                                    <div class="flex items-start justify-between gap-4">
                                        <div class="min-w-0">
                                            <p class="truncate text-sm font-medium text-gray-900 dark:text-gray-100">{{ $document->name }}</p>
                                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ $step->name }} &bull; {{ $document->submittedDocuments->count() }} file terkirim</p>
                                        </div>
                                        <span class="pd pd-pill pd-t-{{ ($docStatus[$document->status] ?? $docStatus['draft'])['tone'] }}">{{ ($docStatus[$document->status] ?? $docStatus['draft'])['label'] }}</span>
                                    </div>
                                </div>

                                <div class="flex gap-3 rounded-lg border border-gray-200 bg-gray-50 p-4 text-sm text-gray-600 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300">
                                    <x-heroicon-o-exclamation-triangle class="mt-0.5 h-5 w-5 flex-shrink-0 text-red-500 dark:text-red-400" />
                                    <p>File yang sudah dikirim dan komentar terkait untuk persyaratan ini juga akan dihapus. Tindakan ini tidak dapat dibatalkan.</p>
                                </div>

                                <div class="flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                                    <x-filament::button color="gray"
                                        x-on:click="$dispatch('close-modal', { id: 'delete-required-document-modal-{{ $document->id }}' })">
                                        Batal
                                    </x-filament::button>
                                    <x-filament::button color="danger" icon="heroicon-o-trash"
                                        wire:click="deleteRequiredDocument({{ $document->id }})"
                                        wire:loading.attr="disabled"
                                        wire:target="deleteRequiredDocument">
                                        Hapus dokumen
                                    </x-filament::button>
                                </div>
                            </div>
                        </x-filament::modal>
                    @endif
                @endforeach

                @if ($canChangeTaskStatus)
                    @foreach ($step->tasks as $task)
                        <x-filament::modal id="confirm-status-modal-{{ $task->id }}" width="md">
                            @php
                                $fromMeta = $taskStatus[$task->status] ?? $taskStatus['pending'];
                                $toMeta = $taskStatus[$newTaskStatus] ?? null;
                            @endphp
                            <div class="space-y-6 p-2">
                                <div>
                                    <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Ubah status tugas</h2>
                                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ $task->title }}</p>
                                </div>

                                <div class="pd flex items-center gap-3 rounded-lg bg-gray-50 p-4 text-sm dark:bg-white/5">
                                    <span class="pd-pill pd-t-{{ $fromMeta['tone'] }}"><span class="pd-dot"></span>{{ $fromMeta['label'] }}</span>
                                    <x-heroicon-m-arrow-long-right class="h-5 w-5 text-gray-400" />
                                    @if ($toMeta)
                                        <span class="pd-pill pd-t-{{ $toMeta['tone'] }}"><span class="pd-dot"></span>{{ $toMeta['label'] }}</span>
                                    @else
                                        <span class="text-gray-500 dark:text-gray-400">Memuat…</span>
                                    @endif
                                </div>

                                <div class="flex justify-end gap-3">
                                    <x-filament::button color="gray"
                                        x-on:click="$dispatch('close-modal', { id: 'confirm-status-modal-{{ $task->id }}' })">
                                        Batal
                                    </x-filament::button>
                                    <x-filament::button wire:click="confirmStatusChange" wire:loading.attr="disabled" wire:target="confirmStatusChange">
                                        Ubah status
                                    </x-filament::button>
                                </div>
                            </div>
                        </x-filament::modal>
                    @endforeach
                @endif

                @if ($canDeleteProjectSteps)
                    @php
                        $stepDocCount = $step->requiredDocuments->count();
                        $stepFileCount = $step->requiredDocuments->sum(fn ($d) => $d->submittedDocuments->count());
                    @endphp
                    <x-filament::modal id="delete-step-modal-{{ $step->id }}" width="md">
                        <div class="space-y-6 p-2">
                            <div class="flex items-start gap-4">
                                <div class="flex h-12 w-12 flex-shrink-0 items-center justify-center rounded-full bg-gray-100 dark:bg-gray-800">
                                    <x-heroicon-o-trash class="h-6 w-6 text-red-500 dark:text-red-400" />
                                </div>
                                <div class="min-w-0">
                                    <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Hapus tahapan</h2>
                                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Tahapan ini akan dihapus dari alur proyek dan progres dihitung ulang.</p>
                                </div>
                            </div>

                            <div class="rounded-lg border border-gray-200 bg-gray-50 p-4 dark:border-gray-700 dark:bg-gray-900">
                                <p class="truncate text-sm font-medium text-gray-900 dark:text-gray-100">{{ $step->name }}</p>
                                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                    Tahap {{ $step->order }} &bull; {{ $step->tasks->count() }} tugas &bull; {{ $stepDocCount }} dokumen persyaratan &bull; {{ $stepFileCount }} file terkirim
                                </p>
                            </div>

                            <div class="flex gap-3 rounded-lg border border-gray-200 bg-gray-50 p-4 text-sm text-gray-600 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300">
                                <x-heroicon-o-exclamation-triangle class="mt-0.5 h-5 w-5 flex-shrink-0 text-red-500 dark:text-red-400" />
                                <p>Tugas, dokumen persyaratan, file terkirim, komentar terkait, dan subtugas harian yang cocok juga akan dihapus. Tindakan ini tidak dapat dibatalkan.</p>
                            </div>

                            <div class="flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                                <x-filament::button color="gray"
                                    x-on:click="$dispatch('close-modal', { id: 'delete-step-modal-{{ $step->id }}' })">
                                    Batal
                                </x-filament::button>
                                <x-filament::button color="danger" icon="heroicon-o-trash"
                                    wire:click="deleteProjectStep({{ $step->id }})" wire:loading.attr="disabled"
                                    wire:target="deleteProjectStep">
                                    Hapus tahapan
                                </x-filament::button>
                            </div>
                        </div>
                    </x-filament::modal>
                @endif
            @endforeach
        </div>
    </div>

    <style>
        .pd {
            --pd-page: #fafafa;
            --pd-bg: #ffffff;
            --pd-bg-soft: #fafafa;
            --pd-hover: #f4f4f5;
            --pd-line: #ececef;
            --pd-line-strong: #d4d4d8;
            --pd-ink: #18181b;
            --pd-muted: #52525b;
            --pd-subtle: #71717a;
            --pd-accent: rgb(var(--primary-700));
            --pd-accent-solid: rgb(var(--primary-700));
            --pd-accent-solid-hover: rgb(var(--primary-800));
            --pd-on-accent: #ffffff;
            --pd-accent-bg: rgba(var(--primary-500), .08);
            --pd-accent-line: rgba(var(--primary-600), .38);
            --pd-ok: rgb(var(--success-600));     --pd-ok-bg: rgba(var(--success-500), .11);
            --pd-info: rgb(var(--info-600));      --pd-info-bg: rgba(var(--info-500), .10);
            --pd-warn: rgb(var(--warning-600));   --pd-warn-bg: rgba(var(--warning-500), .13);
            --pd-bad: rgb(var(--danger-600));     --pd-bad-bg: rgba(var(--danger-500), .10);
            --pd-none: #52525b;                   --pd-none-bg: rgba(113, 113, 122, .11);
            --pd-on-ok: #ffffff;
            color: var(--pd-ink);
        }
        .dark .pd {
            --pd-page: #09090b;
            --pd-bg: #18181b;
            --pd-bg-soft: #1e1e22;
            --pd-hover: #232328;
            --pd-line: #27272a;
            --pd-line-strong: #3f3f46;
            --pd-ink: #f4f4f5;
            --pd-muted: #a1a1aa;
            --pd-subtle: #8b8b94;
            --pd-accent: rgb(var(--primary-400));
            --pd-accent-solid: rgb(var(--primary-400));
            --pd-accent-solid-hover: rgb(var(--primary-300));
            --pd-on-accent: #04232b;
            --pd-accent-bg: rgba(var(--primary-400), .10);
            --pd-accent-line: rgba(var(--primary-400), .40);
            --pd-ok: rgb(var(--success-400));     --pd-ok-bg: rgba(var(--success-400), .11);
            --pd-info: rgb(var(--info-400));      --pd-info-bg: rgba(var(--info-400), .11);
            --pd-warn: rgb(var(--warning-400));   --pd-warn-bg: rgba(var(--warning-400), .12);
            --pd-bad: rgb(var(--danger-400));     --pd-bad-bg: rgba(var(--danger-400), .12);
            --pd-none: #a1a1aa;                   --pd-none-bg: rgba(161, 161, 170, .12);
            --pd-on-ok: #052e16;
        }
        .pd[x-data] { display: flex; flex-direction: column; gap: 18px; font-size: 14px; }
        .pd [x-cloak] { display: none !important; }
        .pd .pd-ic { width: 16px; height: 16px; flex-shrink: 0; color: var(--pd-subtle); }

        /* Lock notice */
        .pd-lock { display: flex; gap: 12px; padding: 12px 16px; border-radius: 12px; font-size: 13.5px; color: var(--pd-bad); background: var(--pd-bad-bg); }
        .pd-lock .pd-ic { margin-top: 2px; color: currentColor; }
        .pd-lock-title { font-weight: 650; margin-bottom: 2px; }

        /* Meta line, pulled up under the Filament page heading */
        .pd-meta { display: flex; flex-wrap: wrap; align-items: center; gap: 8px 18px; margin-top: -14px; font-size: 13px; color: var(--pd-muted); }
        .pd-meta-item { display: inline-flex; align-items: center; gap: 7px; min-width: 0; padding: 0; border: 0; background: none; font: inherit; color: inherit; }
        button.pd-meta-item { cursor: pointer; }
        button.pd-meta-item:hover { color: var(--pd-ink); }
        
        .pd-k { color: var(--pd-subtle); }
        .pd-link { color: var(--pd-accent); font-weight: 600; }
        .pd-link .pd-ic { color: currentColor; opacity: .8; }
        .pd-link:hover, .pd-link-quiet:hover { text-decoration: underline; text-underline-offset: 3px; }
        .pd-desc { max-width: 72ch; font-size: 13.5px; line-height: 1.6; color: var(--pd-muted); }
        .pd-textbtn { margin-left: 4px; padding: 0; border: 0; background: none; font: inherit; font-weight: 600; color: var(--pd-accent); cursor: pointer; }
        .pd-textbtn:hover { text-decoration: underline; text-underline-offset: 3px; }

        /* Pills and dots */
        .pd-pill { display: inline-flex; align-items: center; gap: 6px; height: 24px; padding: 0 9px; border: 0; border-radius: 7px; font: inherit; font-size: 12px; font-weight: 600; line-height: 1; white-space: nowrap; color: var(--c); background: var(--cb); }
        .pd-pill.is-btn { cursor: pointer; }
        .pd-pill.is-btn:hover { box-shadow: inset 0 0 0 1px color-mix(in srgb, var(--c) 35%, transparent); }
        .pd-pill-chev { width: 13px; height: 13px; opacity: .7; }
        /* Status badges: icon + label, no outline, so they never look pressable.
           (The task-status trigger adds a chevron and a hover ring instead.) */
        .pd-badge { gap: 5px; padding: 0 9px 0 7px; }
        .pd-pill-ic { width: 14px; height: 14px; flex-shrink: 0; }
        .pd-menu-ic { display: inline-flex; color: var(--c); }

        /* Action buttons, ranked by how much they want to be pressed. */
        .pd-cta {
            display: inline-flex; align-items: center; justify-content: center; gap: 6px;
            height: 30px; padding: 0 12px; border: 0; border-radius: 8px;
            font: inherit; font-size: 12.5px; font-weight: 650; white-space: nowrap; text-decoration: none; cursor: pointer;
            transition: background .12s, box-shadow .12s, color .12s, filter .12s;
        }
        .pd-cta-ic { width: 15px; height: 15px; flex-shrink: 0; }
        .pd-cta-primary { color: var(--pd-on-accent); background: var(--pd-accent-solid); box-shadow: 0 1px 2px rgba(0, 0, 0, .12); }
        .pd-cta-primary:hover { background: var(--pd-accent-solid-hover); }
        .pd-cta-upload { color: var(--pd-accent); background: var(--pd-accent-bg); box-shadow: inset 0 0 0 1px var(--pd-accent-line); }
        .pd-cta-upload:hover { box-shadow: inset 0 0 0 1.5px var(--pd-accent); }
        .pd-cta-danger { color: var(--pd-bad); background: var(--pd-bad-bg); box-shadow: inset 0 0 0 1px color-mix(in srgb, var(--pd-bad) 35%, transparent); }
        .pd-cta-danger:hover { box-shadow: inset 0 0 0 1.5px var(--pd-bad); }
        .pd-cta-neutral { color: var(--pd-ink); background: var(--pd-bg); box-shadow: inset 0 0 0 1px var(--pd-line-strong); }
        .pd-cta-neutral:hover { background: var(--pd-hover); }
        .pd-cta-quiet { color: var(--pd-muted); background: none; }
        .pd-cta-quiet:hover, .pd-doc:hover .pd-cta-quiet { color: var(--pd-ink); box-shadow: inset 0 0 0 1px var(--pd-line-strong); }
        .pd-dot { width: 6px; height: 6px; border-radius: 50%; flex-shrink: 0; background: currentColor; }
        .pd-menu .pd-dot { background: var(--c); }
        .pd-t-ok   { --c: var(--pd-ok);   --cb: var(--pd-ok-bg); }
        .pd-t-info { --c: var(--pd-info); --cb: var(--pd-info-bg); }
        .pd-t-warn { --c: var(--pd-warn); --cb: var(--pd-warn-bg); }
        .pd-t-bad  { --c: var(--pd-bad);  --cb: var(--pd-bad-bg); }
        .pd-t-none { --c: var(--pd-none); --cb: var(--pd-none-bg); }

        /* Avatars (markup from the shared projects avatar partial) */
        .pd-av { position: relative; display: inline-flex; align-items: center; justify-content: center; width: 22px; height: 22px; flex-shrink: 0; overflow: hidden; border-radius: 50%; font-size: 9.5px; font-weight: 700; color: #fff; background: rgb(var(--primary-600)); }
        .pd-av img { display: block; width: 100%; height: 100%; object-fit: cover; }
        .pd-av .cu-avatar-inner { display: flex; align-items: center; justify-content: center; width: 100%; height: 100%; }
        .pd-avs { display: inline-flex; }
        .pd-avs .pd-av { box-shadow: 0 0 0 2px var(--pd-page); }
        .pd-avs .pd-av + .pd-av { margin-left: -6px; }
        .pd-av-more { color: var(--pd-muted); background: var(--pd-hover); }

        /* Progress */
        .pd-progress { display: flex; flex-wrap: wrap; align-items: center; gap: 10px 16px; }
        .pd-progress-num { font-size: 13px; color: var(--pd-muted); white-space: nowrap; font-variant-numeric: tabular-nums; }
        .pd-progress-num b { font-weight: 700; color: var(--pd-ink); }
        .pd-pbar { display: flex; flex: 1 1 280px; gap: 2px; height: 8px; }
        .pd-pbar i { display: block; height: 100%; min-width: 0; border-radius: 2px; }
        .pd-legend { display: flex; flex-wrap: wrap; gap: 6px 14px; font-size: 12.5px; color: var(--pd-muted); white-space: nowrap; }
        .pd-legend i { display: inline-block; width: 8px; height: 8px; margin-right: 6px; border-radius: 2px; vertical-align: 0; }
        .pd .is-doc { background: var(--pd-ok); }
        .pd .is-task { background: color-mix(in srgb, var(--pd-ok) 50%, var(--pd-bg)); }
        .pd .is-review { background: var(--pd-warn); }
        .pd-pbar .is-open { background: var(--pd-line); }
        .pd-legend .is-open { background: var(--pd-line-strong); }

        /* Tabs */
        .pd-tabs { display: flex; gap: 22px; overflow-x: auto; border-bottom: 1px solid var(--pd-line); scrollbar-width: none; }
        .pd-tab { position: relative; display: flex; align-items: center; gap: 7px; padding: 10px 0 11px; border: 0; background: none; font: inherit; font-size: 13.5px; font-weight: 500; color: var(--pd-muted); white-space: nowrap; cursor: pointer; }
        .pd-tab:hover { color: var(--pd-ink); }
        .pd-tab.is-on { font-weight: 600; color: var(--pd-ink); }
        .pd-tab.is-on::after { content: ''; position: absolute; left: 0; right: 0; bottom: -1px; height: 2px; border-radius: 2px; background: var(--pd-accent); }
        .pd-tab .n { font-size: 11.5px; font-weight: 500; color: var(--pd-subtle); font-variant-numeric: tabular-nums; }

        /* Toolbar */
        .pd-toolbar { display: flex; flex-wrap: wrap; align-items: center; gap: 10px; margin-bottom: 12px; }
        .pd-seg { display: inline-flex; flex-wrap: wrap; padding: 3px; border-radius: 10px; background: var(--pd-bg); box-shadow: inset 0 0 0 1px var(--pd-line); }
        .pd-seg button { display: inline-flex; align-items: center; gap: 7px; height: 28px; padding: 0 12px; border: 0; border-radius: 7px; background: none; font: inherit; font-size: 13px; font-weight: 500; color: var(--pd-muted); white-space: nowrap; cursor: pointer; }
        .pd-seg button b { font-size: 11.5px; font-weight: 600; color: var(--pd-subtle); font-variant-numeric: tabular-nums; }
        .pd-seg button:hover { color: var(--pd-ink); }
        .pd-seg button.is-on { color: var(--pd-ink); background: var(--pd-bg-soft); box-shadow: inset 0 0 0 1px var(--pd-line-strong); }
        .pd-seg button.is-on b { color: var(--pd-accent); }
        .pd-search { display: flex; align-items: center; gap: 8px; width: min(260px, 100%); height: 34px; margin-left: auto; padding: 0 12px; border-radius: 9px; background: var(--pd-bg); box-shadow: inset 0 0 0 1px var(--pd-line); }
        .pd-search:focus-within { box-shadow: inset 0 0 0 1px var(--pd-accent); }
        .pd-search input { width: 100%; padding: 0 !important; border: 0 !important; outline: none; background: none !important; box-shadow: none !important; font: inherit; font-size: 13px; color: var(--pd-ink); }
        .pd-search input::placeholder { color: var(--pd-subtle); }
        .pd-search input::-webkit-search-cancel-button { cursor: pointer; }
        @media (max-width: 639px) { .pd-search { width: 100%; margin-left: 0; } }

        /* Checklist: one card per step */
        .pd-steps { container-type: inline-size; display: flex; flex-direction: column; gap: 14px; }
        .pd-group { border: 1px solid var(--pd-line); border-radius: 12px; background: var(--pd-bg); }
        .pd-grp { display: flex; align-items: center; gap: 12px; min-height: 52px; padding: 8px 14px 8px 18px; background: var(--pd-bg-soft); cursor: pointer; user-select: none; border-radius: 11px; }
        .pd-group.is-open .pd-grp { border-radius: 11px 11px 0 0; }
        .pd-grp-body { border-top: 1px solid var(--pd-line); }
        .pd-grp:hover { background: var(--pd-hover); }
        .pd-grp:focus-visible { outline: 2px solid var(--pd-accent); outline-offset: -2px; }
        .pd-grp-chev { width: 16px; height: 16px; flex-shrink: 0; color: var(--pd-subtle); transition: transform .15s ease-out; }
        .pd-grp-chev.is-closed { transform: rotate(-90deg); }
        .pd-grp-no { width: 20px; flex-shrink: 0; font-size: 11.5px; font-weight: 600; color: var(--pd-subtle); font-variant-numeric: tabular-nums; }
        .pd-grp-name { margin: 0; min-width: 0; font-size: 13.5px; font-weight: 650; color: var(--pd-ink); }
        .pd-grp-prog { display: flex; align-items: center; gap: 10px; margin-left: auto; font-size: 12.5px; color: var(--pd-subtle); font-variant-numeric: tabular-nums; white-space: nowrap; }
        .pd-minibar { width: 90px; height: 5px; overflow: hidden; border-radius: 99px; background: var(--pd-line); }
        .pd-minibar i { display: block; height: 100%; border-radius: inherit; background: var(--pd-info); }
        .pd-minibar.is-full i { background: var(--pd-ok); }
        .pd-grp { flex-wrap: wrap; }
        .pd-grp-sub { flex-basis: 100%; margin: -2px 0 4px; padding-left: 62px; font-size: 12.5px; color: var(--pd-muted); }
        .pd-grp-sub .pd-k { margin-right: 6px; font-size: 11px; font-weight: 700; letter-spacing: .06em; text-transform: uppercase; }
        .pd-grp-sub b { font-weight: 600; color: var(--pd-ink); }
        .pd-grp-desc { margin: 0; padding: 12px 18px 12px 62px; border-bottom: 1px solid var(--pd-line); font-size: 12.5px; line-height: 1.55; color: var(--pd-muted); }

        /* Documents: a file that goes through upload, review, approval */
        .pd-sec-cols, .pd-doc { display: grid; grid-template-columns: 44px minmax(0, 1fr) 150px 172px 150px 136px; align-items: center; column-gap: 16px; padding: 0 18px; }
        .pd-sec-cols { padding-top: 12px; padding-bottom: 9px; font-size: 10.5px; font-weight: 700; letter-spacing: .08em; text-transform: uppercase; color: var(--pd-subtle); }
        .pd-grp-desc + .pd-sec-cols, .pd-grp-desc + .pd-tasks { border-top: 0; }
        .pd-sec-cols .pd-kind-lbl { grid-column: 1 / span 2; }
        .pd-kind-lbl { display: inline-flex; align-items: center; gap: 7px; font-size: 10.5px; font-weight: 700; letter-spacing: .08em; text-transform: uppercase; color: var(--pd-muted); }
        .pd-kind-lbl .pd-ic { width: 14px; height: 14px; color: currentColor; }
        .pd-kind-lbl b { font-weight: 600; color: var(--pd-subtle); font-variant-numeric: tabular-nums; }
        .pd-doc { min-height: 66px; padding-top: 12px; padding-bottom: 12px; border-top: 1px solid var(--pd-line); font-size: 13px; cursor: pointer; }
        .pd-doc:hover { background: var(--pd-hover); }
        .pd-grp-body > .pd-doc:last-child { border-radius: 0 0 11px 11px; }
        .pd-doc .pd-item { padding: 0; gap: 3px; }
        .pd-doc .pd-item-name { font-weight: 600; }
        .pd-doc.is-done .pd-item-name { font-weight: 500; }
        .pd-ft { position: relative; display: flex; align-items: center; justify-content: center; width: 34px; height: 38px; margin-left: 2px; border-radius: 8px; color: var(--c); background: var(--cb); }
        .pd-ft-ic { width: 18px; height: 18px; }
        .pd-ft-n { position: absolute; right: -7px; bottom: -6px; display: flex; align-items: center; justify-content: center; min-width: 18px; height: 18px; padding: 0 4px; border-radius: 99px; font-size: 10px; font-weight: 700; color: var(--pd-ink); background: var(--pd-bg); box-shadow: 0 0 0 1px var(--pd-line-strong); font-variant-numeric: tabular-nums; }
        .pd-flow { display: flex; flex-direction: column; align-items: flex-start; }
        .pd-flow-track { display: flex; align-items: center; }
        .pd-flow-track i { width: 9px; height: 9px; border-radius: 50%; box-shadow: inset 0 0 0 1.5px var(--pd-line-strong); }
        .pd-flow-track i.done { background: var(--pd-ok); box-shadow: none; }
        .pd-flow-track i.cur { background: var(--c); box-shadow: 0 0 0 3px var(--cb); }
        .pd-flow-track i.bad { background: var(--pd-bad); box-shadow: 0 0 0 3px var(--pd-bad-bg); }
        .pd-flow-track b { width: 34px; height: 2px; background: var(--pd-line-strong); }
        .pd-flow-track b.done { background: color-mix(in srgb, var(--pd-ok) 65%, transparent); }
        .pd-flow-lbl { display: flex; justify-content: space-between; width: 122px; margin: 4px 0 0 -13.5px; font-size: 10px; color: var(--pd-subtle); }
        .pd-flow-lbl span { width: 36px; text-align: center; }
        .pd-by-txt { display: flex; flex-direction: column; min-width: 0; line-height: 1.3; }
        .pd-by-txt small { font-size: 11.5px; color: var(--pd-subtle); font-variant-numeric: tabular-nums; }

        /* Tasks: a to-do that is ticked off */
        .pd-tasks { margin: 8px 18px 18px 62px; border: 1px solid var(--pd-line); border-radius: 10px; background: var(--pd-bg-soft); }
        .pd-tasks.is-alone { margin-top: 16px; }
        .pd-tasks-hd { display: flex; align-items: center; justify-content: space-between; gap: 12px; padding: 12px 16px 10px; }
        .pd-tasks-hd .pd-k { font-size: 12px; }
        .pd-task { display: grid; grid-template-columns: 18px minmax(0, 1fr) auto auto auto; align-items: center; column-gap: 14px; min-height: 48px; padding: 6px 12px 6px 16px; border-top: 1px solid var(--pd-line); font-size: 13px; }
        .pd-task .pd-item { padding: 0; }
        .pd-task.is-done .pd-item-name { color: var(--pd-muted); text-decoration: line-through; text-decoration-color: var(--pd-line-strong); }
        .pd-task-at { font-size: 12px; color: var(--pd-subtle); white-space: nowrap; font-variant-numeric: tabular-nums; }
        .pd-tasks .pd-sub { padding: 12px 16px 14px 48px; border-top: 1px solid var(--pd-line); border-bottom: 0; background: transparent; }
        .pd-item { display: flex; flex-direction: column; gap: 1px; min-width: 0; padding: 8px 0; }
        .pd-item-line { display: flex; align-items: center; gap: 8px; min-width: 0; }
        .pd-item-name { overflow: hidden; font-size: 13.5px; font-weight: 500; color: var(--pd-ink); white-space: nowrap; text-overflow: ellipsis; }
        
        .pd-req { flex-shrink: 0; padding: 1px 6px; border-radius: 5px; font-size: 10.5px; font-weight: 700; letter-spacing: .04em; text-transform: uppercase; color: var(--pd-subtle); box-shadow: inset 0 0 0 1px var(--pd-line-strong); }
        .pd-item-desc { overflow: hidden; font-size: 12px; color: var(--pd-subtle); white-space: nowrap; text-overflow: ellipsis; }
        .pd-kind { display: inline-flex; align-items: center; gap: 6px; font-size: 12.5px; color: var(--pd-subtle); }
        .pd-kind .pd-ic { width: 14px; height: 14px; }
        .pd-status { display: flex; align-items: center; gap: 8px; min-width: 0; }
        .pd-files { font-size: 12px; color: var(--pd-subtle); white-space: nowrap; }
        .pd-by { display: flex; align-items: center; gap: 8px; min-width: 0; color: var(--pd-muted); }
        .pd-by .nm { overflow: hidden; white-space: nowrap; text-overflow: ellipsis; }
        .pd-at { font-size: 12.5px; color: var(--pd-subtle); white-space: nowrap; font-variant-numeric: tabular-nums; }
        .pd-act { display: flex; align-items: center; justify-content: flex-end; gap: 2px; }

        .pd-btn { display: inline-flex; align-items: center; gap: 6px; height: 28px; padding: 0 10px; border: 0; border-radius: 8px; background: none; font: inherit; font-size: 12.5px; font-weight: 600; color: var(--pd-muted); white-space: nowrap; cursor: pointer; text-decoration: none; transition: background .12s, box-shadow .12s, color .12s; }
        .pd-btn .pd-ic { width: 15px; height: 15px; color: currentColor; }
        .pd-btn.is-accent { color: var(--pd-accent); }
        .pd-task:hover .pd-btn, .pd-btn:hover, .pd-btn.is-on, .pd-btn:focus-visible { box-shadow: inset 0 0 0 1px var(--pd-line-strong); }
        .pd-btn:hover { color: var(--pd-ink); background: var(--pd-bg); }
        .pd-btn.is-accent:hover { color: var(--pd-accent); }
        .pd-iconbtn { display: inline-flex; align-items: center; justify-content: center; width: 28px; height: 28px; flex-shrink: 0; border: 0; border-radius: 7px; background: none; color: var(--pd-subtle); cursor: pointer; opacity: 0; transition: opacity .12s; }
        .pd-iconbtn .pd-ic { width: 15px; height: 15px; color: currentColor; }
        .pd-doc:hover .pd-iconbtn, .pd-grp:hover .pd-iconbtn, .pd-iconbtn:focus-visible { opacity: 1; }
        .pd-iconbtn:hover { color: var(--pd-bad); background: var(--pd-bad-bg); }
        .pd :is(button, a, input):focus-visible { outline: 2px solid var(--pd-accent); outline-offset: 2px; }

        /* Row glyphs */
        .pd-glyph { display: inline-flex; align-items: center; justify-content: center; width: 18px; height: 18px; flex-shrink: 0; border-radius: 50%; }
        .pd-glyph svg { width: 12px; height: 12px; }
        .pd-g-done { color: var(--pd-on-ok); background: var(--pd-ok); }
        .pd-g-review { box-shadow: inset 0 0 0 2px var(--pd-warn); }
        .pd-g-bad { box-shadow: inset 0 0 0 2px var(--pd-bad); }
        .pd-g-open { box-shadow: inset 0 0 0 1.5px var(--pd-line-strong); }
        .pd-glyph-cell { display: flex; }
        .pd .pd-check {
            appearance: none; -webkit-appearance: none; display: block; width: 16px; height: 16px; margin: 0 1px;
            border: 0 !important; border-radius: 5px; background-color: transparent; cursor: pointer;
            box-shadow: inset 0 0 0 1.5px var(--pd-line-strong) !important;
        }
        .pd .pd-check:checked {
            background: var(--pd-ok) url("data:image/svg+xml,%3csvg viewBox='0 0 16 16' fill='white' xmlns='http://www.w3.org/2000/svg'%3e%3cpath d='M12.207 4.793a1 1 0 010 1.414l-5 5a1 1 0 01-1.414 0l-2-2a1 1 0 011.414-1.414L6.5 9.086l4.293-4.293a1 1 0 011.414 0z'/%3e%3c/svg%3e") center / 14px no-repeat;
            box-shadow: none !important;
        }
        .pd .pd-check:disabled { cursor: not-allowed; opacity: .55; }

        /* Task status menu */
        .pd-dd { position: relative; display: inline-flex; }
        .pd-menu { position: absolute; top: calc(100% + 6px); left: 0; z-index: 20; display: flex; flex-direction: column; width: 172px; padding: 4px; border-radius: 10px; background: var(--pd-bg); box-shadow: 0 12px 32px -12px rgba(0, 0, 0, .35), 0 0 0 1px var(--pd-line-strong); }
        .pd-menu button { display: flex; align-items: center; gap: 9px; width: 100%; padding: 7px 9px; border: 0; border-radius: 7px; background: none; font: inherit; font-size: 13px; color: var(--pd-ink); text-align: left; cursor: pointer; }
        .pd-menu button:hover { background: var(--pd-hover); }
        .pd-menu button.is-current { font-weight: 650; }
        .pd-fade { transition: opacity .12s ease-out; }
        .pd-fade-from { opacity: 0; }

        .pd-sub { padding: 12px 16px 14px 56px; border-bottom: 1px solid var(--pd-line); background: var(--pd-bg-soft); }
        .pd-nomatch { margin: 0; padding: 28px 16px; border: 1px dashed var(--pd-line-strong); border-radius: 12px; font-size: 13px; text-align: center; color: var(--pd-subtle); }
        .pd-foot { display: flex; justify-content: space-between; gap: 12px; padding: 0 4px; font-size: 12.5px; color: var(--pd-subtle); font-variant-numeric: tabular-nums; }
        .pd-foot b { color: var(--pd-ink); }

        @container (max-width: 960px) {
            .pd-sec-cols, .pd-doc { grid-template-columns: 44px minmax(0, 1fr) 172px 140px 136px; }
            .pd .c-flow { display: none; }
        }
        @container (max-width: 760px) {
            .pd-sec-cols, .pd-doc { grid-template-columns: 44px minmax(0, 1fr) 172px 136px; }
            .pd .c-by { display: none; }
            .pd-task-at { display: none; }
        }
        @container (max-width: 560px) {
            .pd-sec-cols { grid-template-columns: 1fr; padding-left: 16px; }
            .pd-sec-cols > :not(.pd-kind-lbl) { display: none; }
            .pd-doc { grid-template-columns: 38px minmax(0, 1fr) auto; row-gap: 6px; padding-left: 16px; padding-right: 14px; }
            .pd-doc .pd-status { grid-column: 2; grid-row: 2; }
            .pd-doc .pd-act { grid-column: 3; grid-row: 1 / span 2; }
            .pd-doc .pd-item-name, .pd-task .pd-item-name { white-space: normal; }
            .pd-tasks { margin-left: 14px; margin-right: 14px; }
            .pd-task { grid-template-columns: 18px minmax(0, 1fr) auto; row-gap: 4px; }
            .pd-task .pd-status { grid-column: 2; grid-row: 2; }
            .pd-task .pd-act { grid-column: 3; grid-row: 1 / span 2; }
            .pd-grp { flex-wrap: wrap; row-gap: 6px; }
            .pd-grp-sub { padding-left: 0; }
            .pd-minibar { display: none; }
            .pd-grp-desc, .pd-tasks .pd-sub { padding-left: 16px; }
            .pd-cta { padding: 0 10px; }
        }

        /* Other panels */
        .pd-panel { border: 1px solid var(--pd-line); border-radius: 12px; background: var(--pd-bg); }
        .pd-pad { padding: 18px 20px; }
        .pd-empty { padding: 48px 24px; text-align: center; font-size: 13.5px; color: var(--pd-subtle); }
        .pd-empty-ic { width: 32px; height: 32px; margin: 0 auto 10px; color: var(--pd-subtle); }
        .pd-empty-title { margin-bottom: 4px; font-weight: 650; color: var(--pd-ink); }
        .pd-team-pic { padding: 16px 20px; border-bottom: 1px solid var(--pd-line); }

        /* Ringkasan. Media queries, not container queries: the PIC and member
           components open fixed-position modals that containment would trap. */
        .pd-sum { display: grid; grid-template-columns: minmax(0, 1fr) 320px; gap: 16px; align-items: start; }
        .pd-sum-main, .pd-sum-side { display: flex; flex-direction: column; gap: 16px; min-width: 0; }
        @media (max-width: 1199px) { .pd-sum { grid-template-columns: 1fr; } }
        .pd-sec-hd { display: flex; align-items: center; justify-content: space-between; gap: 12px; min-height: 52px; padding: 10px 14px 10px 20px; border-bottom: 1px solid var(--pd-line); }
        .pd-sec-hd .pd-h { display: flex; align-items: center; gap: 8px; }
        .pd-sec-hd .pd-k { font-size: 12.5px; font-variant-numeric: tabular-nums; }
        .pd-count { font-size: 12px; font-weight: 600; color: var(--pd-subtle); font-variant-numeric: tabular-nums; }
        .pd-sec-ft { margin: 0; padding: 10px 20px; border-top: 1px solid var(--pd-line); font-size: 12.5px; color: var(--pd-subtle); }
        .pd .pd-flush { margin: 0; }
        .pd-brief { max-width: 72ch; color: var(--pd-muted); }
        .pd-brief > :first-child { margin-top: 0; }
        .pd-brief > :last-child { margin-bottom: 0; }

        .pd-queue li { display: grid; grid-template-columns: 18px minmax(0, 1fr) auto auto; align-items: center; gap: 12px; padding: 8px 14px 8px 20px; border-top: 1px solid var(--pd-line); }
        .pd-queue li:first-child { border-top: 0; }
                .pd-glyph-sq { border-radius: 5px; }
        @media (max-width: 639px) {
            .pd-queue li { grid-template-columns: 18px minmax(0, 1fr) auto; row-gap: 4px; padding-left: 16px; }
            .pd-queue li > .pd-pill { grid-column: 2; grid-row: 2; justify-self: start; }
            .pd-queue li > .pd-cta { grid-column: 3; grid-row: 1 / span 2; }
            .pd-info > div { grid-template-columns: 92px minmax(0, 1fr); padding: 5px 16px; }
            .pd-sec-hd { padding-left: 16px; }
        }

        .pd-stepper li + li { border-top: 1px solid var(--pd-line); }
        .pd-step { display: grid; grid-template-columns: 18px minmax(0, 1fr) auto; align-items: center; gap: 12px; width: 100%; padding: 11px 20px; border: 0; background: none; font: inherit; color: inherit; text-align: left; cursor: pointer; }
        .pd-step:hover { background: var(--pd-hover); }
        .pd-stepper li:last-child .pd-step { border-radius: 0 0 11px 11px; }
        .pd-step-body { display: flex; flex-direction: column; gap: 2px; min-width: 0; }
        .pd-step-line { display: flex; flex-wrap: wrap; align-items: center; gap: 4px 10px; min-width: 0; }
        .pd-step-name { font-size: 13.5px; font-weight: 600; color: var(--pd-ink); }
        .pd-step-sub { font-size: 12px; color: var(--pd-subtle); padding-left: 30px; }
        .pd-step-prog { font-size: 12.5px; color: var(--pd-subtle); font-variant-numeric: tabular-nums; }
        .pd-g-run { box-shadow: inset 0 0 0 2px var(--pd-info); background: radial-gradient(circle, var(--pd-info) 0 3.5px, transparent 4px); }

        .pd-info { margin: 0; padding: 6px 0; }
        .pd-info > div { display: grid; grid-template-columns: 104px minmax(0, 1fr); align-items: center; gap: 10px; min-height: 36px; padding: 5px 20px; font-size: 13px; }
        .pd-info dt { color: var(--pd-subtle); }
        .pd-info dd { margin: 0; min-width: 0; color: var(--pd-ink); overflow-wrap: anywhere; }
        .pd-info dd .pd-due { display: block; font-size: 12px; }

        .pd-people li { display: flex; align-items: center; gap: 12px; padding: 9px 14px 9px 20px; }
        .pd-people li + li { border-top: 1px solid var(--pd-line); }
        .pd-people .pd-item { flex: 1; padding: 0; }
        .pd-av-lg { width: 30px; height: 30px; font-size: 11px; }
        .pd-iconbtn.is-visible { opacity: 1; }
        .pd-iconbtn.is-visible:hover { color: var(--pd-ink); background: var(--pd-hover); }

        /* Due-date countdown */
        .pd-due { font-weight: 600; }
        .pd-due-bad { color: var(--pd-bad); }
        .pd-due-warn { color: var(--pd-warn); }
        .pd-due-none { color: var(--pd-muted); font-weight: 500; }
        .pd-result { display: grid; grid-template-columns: minmax(0, 3fr) minmax(0, 2fr); }
        .pd-result > .pd-pad + .pd-pad { border-left: 1px solid var(--pd-line); }
        @media (max-width: 1023px) {
            .pd-result { grid-template-columns: 1fr; }
            .pd-result > .pd-pad + .pd-pad { border-left: 0; border-top: 1px solid var(--pd-line); }
        }
        .pd-h { margin: 0; font-size: 13.5px; font-weight: 650; color: var(--pd-ink); }
        .pd-muted-text { margin-top: 10px; font-size: 13px; color: var(--pd-subtle); }
        .pd-files-list { margin-top: 8px; }
        .pd-files-list li { display: flex; align-items: center; gap: 12px; padding: 10px 0; border-bottom: 1px solid var(--pd-line); }
        .pd-files-list li:last-child { border-bottom: 0; }
        .pd-file-ic { display: flex; align-items: center; justify-content: center; width: 32px; height: 32px; flex-shrink: 0; border-radius: 8px; background: var(--pd-hover); }
        .pd-file-body { flex: 1; min-width: 0; }
        .pd-file-name { overflow: hidden; font-size: 13.5px; font-weight: 500; white-space: nowrap; text-overflow: ellipsis; }
        .pd-file-meta { font-size: 12px; color: var(--pd-subtle); }

        .pd-hosts { height: 0; }
        .pd-hosts > .fi-modal { display: block; height: 0; }

        @media (prefers-reduced-motion: reduce) {
            .pd *, .pd *::after { transition: none !important; }
        }
    </style>

    <script>
        // ?openDocument={id} deep link. Wait for Livewire/Alpine to boot: at
        // DOMContentLoaded the modal's open-modal listener may not exist yet.
        (function () {
            const documentId = new URLSearchParams(window.location.search).get('openDocument');
            if (! documentId) return;
            let done = false;
            const open = () => {
                if (done) return;
                done = true;
                setTimeout(() => window.dispatchEvent(new CustomEvent('open-modal', { detail: { id: `document-modal-${documentId}` } })), 50);
            };
            document.addEventListener('livewire:initialized', open, { once: true });
            document.addEventListener('livewire:navigated', open, { once: true });
        })();
    </script>
</x-filament-panels::page>
