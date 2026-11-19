{{--
    Proyek (client portal), docs/mockups/client-portal-redesign.html: project list
    with the Total / Aktif / Selesai summary on the left, the chosen project on
    the right. Picking a project is instant (Alpine); everything is rendered
    up front. Styles: client-portal/projects.css (+ shared kp-a-* in overview.css).
--}}
@php
    use App\Support\Ui\Tone;
    use Carbon\Carbon;

    $projects = $this->projects;
    $date = fn ($value, string $format = 'd M Y') => Carbon::parse($value)->locale('id')->translatedFormat($format);
    $priority = fn (?string $value) => match ($value) {
        'urgent' => ['Mendesak', 'is-urgent'],
        'high' => ['Prioritas Tinggi', 'is-high'],
        'low' => ['Rendah', ''],
        default => ['Normal', ''],
    };
    // Label: Project::client_status_label (client wording). Tone: the admin's choice in Project Statuses.
    $tone = fn ($project) => Tone::forProjectStatus($project->statusRecord ?? $project->status);
    $fileOf = function ($file) {
        $name = is_string($file) ? basename($file) : ($file['name'] ?? basename($file['path'] ?? ''));

        return [$name ?: 'Berkas', strtoupper(pathinfo($name, PATHINFO_EXTENSION)) ?: 'FILE'];
    };
@endphp

<div class="kp-a kp-pj" x-data="{
        selected: @js($selectedProjectId),
        pick(id) {
            this.selected = id;
            if (window.innerWidth < 1024) this.$nextTick(() => this.$refs.detail?.scrollIntoView({ behavior: 'smooth', block: 'start' }));
        },
    }">

    {{-- List --}}
    <aside class="kp-a-card kp-pj-list" aria-label="Daftar proyek">
        <div class="kp-pj-sum" aria-label="Ringkasan Proyek">
            <div><span class="kp-a-label">Total</span><b>{{ $projects->count() }}</b></div>
            <div><span class="kp-a-label">Aktif</span><b>{{ $this->activeProjectsCount }}</b></div>
            <div><span class="kp-a-label">Selesai</span><b>{{ $this->completedProjectsCount }}</b></div>
        </div>

        <p class="kp-a-label kp-pj-list__cap">Daftar Proyek</p>

        @forelse ($projects as $project)
            @php [$prioLabel, $prioClass] = $priority($project->priority); @endphp
            <button type="button" wire:key="pj-item-{{ $project->id }}" class="kp-pj-item"
                    x-bind:class="selected === {{ $project->id }} && 'is-on'"
                    x-bind:aria-pressed="selected === {{ $project->id }}"
                    x-on:click="pick({{ $project->id }})">
                <span class="kp-pj-item__name">{{ $project->name }}</span>
                <span class="kp-pj-item__client">
                    <x-heroicon-o-building-office-2 class="h-3.5 w-3.5 flex-none" />
                    <span class="truncate">{{ $project->client?->name }}</span>
                </span>
                <span class="kp-pj-item__meta">
                    <x-kis.badge size="sm" :tone="$tone($project)" dot>{{ $project->client_status_label }}</x-kis.badge>
                    @if (in_array($project->priority, ['urgent', 'high'], true))
                        <span class="kp-pj-prio {{ $prioClass }}"><x-heroicon-m-flag class="h-3.5 w-3.5" />{{ $prioLabel }}</span>
                    @endif
                    @if ($project->due_date)
                        <span class="kp-pj-item__due"><x-heroicon-o-calendar class="h-3.5 w-3.5" />{{ $date($project->due_date, 'd M') }}</span>
                    @endif
                </span>
            </button>
        @empty
            <div class="kp-a-empty">
                <span class="kp-a-empty__icon"><x-heroicon-o-folder-open class="h-5 w-5" /></span>
                <b>Belum ada proyek</b>
                <span>Proyek Anda akan muncul di sini</span>
            </div>
        @endforelse
    </aside>

    {{-- Detail --}}
    <div class="kp-pj-detail" x-ref="detail">
        @forelse ($projects as $project)
            @php
                [$prioLabel, $prioClass] = $priority($project->priority);
                $files = is_array($project->deliverable_files) ? $project->deliverable_files : [];
                $description = $project->client_description ?? $project->description;
            @endphp
            <article wire:key="pj-detail-{{ $project->id }}" x-show="selected === {{ $project->id }}" x-cloak class="kp-pj-article">
                <header>
                    <p class="kp-pj-badges">
                        <x-kis.badge :tone="$tone($project)" dot>{{ $project->client_status_label }}</x-kis.badge>
                        <span class="kp-pj-prio {{ $prioClass }}">
                            @if ($prioClass)<x-heroicon-m-flag class="h-3.5 w-3.5" />@endif
                            Prioritas: {{ $prioLabel }}
                        </span>
                    </p>
                    <h1 class="kp-pj-title">{{ $project->name }}</h1>
                    <p class="kp-pj-facts">
                        <span><x-heroicon-o-building-office-2 class="h-4 w-4" />{{ $project->client?->name }}</span>
                        @if ($project->due_date)
                            <span><x-heroicon-o-calendar class="h-4 w-4" />Target: {{ $date($project->due_date) }}</span>
                        @endif
                        @if ($project->pic)
                            <span><x-heroicon-o-user class="h-4 w-4" />{{ $project->pic->name }}</span>
                        @endif
                    </p>
                    @if (filled($description))
                        <p class="kp-pj-desc">{{ $description }}</p>
                    @endif
                </header>

                @if (count($files) > 0)
                    <section class="kp-a-card">
                        <header class="kp-a-card__head kp-a-card__head--line">
                            <div>
                                <h2 class="kp-a-card__title">File Hasil Proyek</h2>
                                <p class="kp-a-card__sub">{{ count($files) }} file tersedia</p>
                            </div>
                        </header>
                        <div class="kp-pj-files">
                            @foreach ($files as $index => $file)
                                @php [$fileName, $ext] = $fileOf($file); @endphp
                                <button type="button" class="kp-pj-file" wire:click="downloadDeliverable({{ $project->id }}, {{ $index }})"
                                        wire:loading.attr="disabled" title="Unduh {{ $fileName }}">
                                    <span class="kp-pj-file__ext">{{ \Illuminate\Support\Str::limit($ext, 4, '') }}</span>
                                    <span class="kp-pj-file__text">
                                        <b>{{ $fileName }}</b>
                                        <small>Klik untuk unduh</small>
                                    </span>
                                    <x-heroicon-o-arrow-down-tray class="h-[18px] w-[18px] flex-none" />
                                </button>
                            @endforeach
                        </div>
                    </section>
                @endif

                @if ($project->result_notes)
                    <section class="kp-a-card">
                        <header class="kp-a-card__head kp-a-card__head--line">
                            <div>
                                <h2 class="kp-a-card__title">Catatan Hasil</h2>
                                <p class="kp-a-card__sub">Ringkasan dan catatan penting dari proyek</p>
                            </div>
                        </header>
                        <p class="kp-pj-notes">{{ $project->result_notes }}</p>
                    </section>
                @endif

                @if (count($files) === 0 && ! $project->result_notes)
                    <div class="kp-a-card kp-a-empty kp-pj-empty">
                        <span class="kp-a-empty__icon"><x-heroicon-o-document-arrow-down class="h-5 w-5" /></span>
                        <b>Belum Ada File Hasil</b>
                        <span>File hasil proyek akan tersedia ketika proyek telah selesai</span>
                    </div>
                @endif
            </article>
        @empty
            <div class="kp-a-card kp-a-empty kp-pj-empty kp-pj-empty--none">
                <span class="kp-a-empty__icon"><x-heroicon-o-folder-open class="h-5 w-5" /></span>
                <b>Belum ada proyek untuk ditampilkan</b>
                <span>Begitu tim Kisantra membuat proyek untuk perusahaan Anda, status, target, dan file hasilnya tampil di sini.</span>
            </div>
        @endforelse
    </div>
</div>
