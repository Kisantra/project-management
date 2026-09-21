<?php

namespace App\Livewire\Projects\Components;

use App\Models\Project;
use App\Models\ProjectStatus;
use App\Models\ProjectStep;
use App\Models\RequiredDocument;
use App\Models\SubmittedDocument;
use App\Models\Task;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Livewire\Attributes\Lazy;
use Livewire\Component;
use Spatie\Activitylog\Models\Activity;

/**
 * Activity feed for the project detail "Aktivitas" tab.
 *
 * Reads the Spatie activity_log rows whose subject is the project itself or
 * anything hanging off it (steps, tasks, required documents, submitted files),
 * normalises each row into a short Indonesian sentence, merges bursts of the
 * same action by the same person, and groups the result by day.
 *
 * Lazy: the tab is hidden on first paint, so the query only runs once the
 * panel scrolls into view.
 */
#[Lazy]
class ProjectActivityFeed extends Component
{
    public const PAGE = 30;

    public const FILTERS = [
        'semua'   => 'Semua',
        'tahapan' => 'Tahapan',
        'tugas'   => 'Tugas',
        'dokumen' => 'Dokumen',
        'proyek'  => 'Proyek',
    ];

    public Project $project;

    public string $filter = 'semua';

    public int $limit = self::PAGE;

    public function mount(Project $project): void
    {
        $this->project = $project;
    }

    public function placeholder(): string
    {
        return <<<'HTML'
        <div class="space-y-4 px-4 py-5 sm:px-5" aria-busy="true" aria-label="Memuat aktivitas">
            <div class="h-6 w-56 animate-pulse rounded bg-gray-100 dark:bg-white/5"></div>
            <div class="space-y-3">
                <div class="h-10 animate-pulse rounded-lg bg-gray-100 dark:bg-white/5"></div>
                <div class="h-10 animate-pulse rounded-lg bg-gray-100 dark:bg-white/5"></div>
                <div class="h-10 animate-pulse rounded-lg bg-gray-100 dark:bg-white/5"></div>
            </div>
        </div>
        HTML;
    }

    public function setFilter(string $filter): void
    {
        $this->filter = array_key_exists($filter, self::FILTERS) ? $filter : 'semua';
        $this->limit = self::PAGE;
    }

    public function loadMore(): void
    {
        $this->limit += self::PAGE;
    }

    /* ------------------------------------------------------------------ */
    /* Queries                                                             */
    /* ------------------------------------------------------------------ */

    /**
     * [category => [subject_type => [ids]]] for everything under this project.
     */
    protected function subjectMap(): array
    {
        $stepIds = ProjectStep::where('project_id', $this->project->id)->pluck('id');
        $taskIds = Task::whereIn('project_step_id', $stepIds)->pluck('id');
        $docIds = RequiredDocument::whereIn('project_step_id', $stepIds)->pluck('id');
        $fileIds = SubmittedDocument::whereIn('required_document_id', $docIds)->pluck('id');

        return [
            'proyek'  => [Project::class => collect([$this->project->id])],
            'tahapan' => [ProjectStep::class => $stepIds],
            'tugas'   => [Task::class => $taskIds],
            'dokumen' => [RequiredDocument::class => $docIds, SubmittedDocument::class => $fileIds],
        ];
    }

    protected function baseQuery(array $categories): Builder
    {
        $map = $this->subjectMap();

        return Activity::query()->where(function (Builder $query) use ($map, $categories) {
            foreach ($categories as $category) {
                foreach ($map[$category] as $type => $ids) {
                    $query->orWhere(fn (Builder $q) => $q->where('subject_type', $type)->whereIn('subject_id', $ids));
                }
            }
        });
    }

    public function getCountsProperty(): array
    {
        $counts = [];
        foreach (array_keys(self::FILTERS) as $key) {
            if ($key === 'semua') {
                continue;
            }
            $counts[$key] = $this->baseQuery([$key])->count();
        }
        $counts['semua'] = array_sum($counts);

        return $counts;
    }

    public function getTotalProperty(): int
    {
        return $this->counts[$this->filter] ?? 0;
    }

    /**
     * Grouped feed: [ ['label' => 'Hari ini', 'date' => Carbon, 'entries' => [...]], ... ]
     */
    public function getFeedProperty(): array
    {
        $categories = $this->filter === 'semua'
            ? ['proyek', 'tahapan', 'tugas', 'dokumen']
            : [$this->filter];

        $rows = $this->baseQuery($categories)
            ->with(['causer:id,name', 'subject'])
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit($this->limit)
            ->get();

        $entries = $this->mergeBursts($rows->map(fn (Activity $a) => $this->normalize($a)));

        return $entries
            ->groupBy(fn (array $e) => $e['at']->toDateString())
            ->map(fn (Collection $group, string $date) => [
                'label'   => $this->dayLabel(Carbon::parse($date)),
                'date'    => $date,
                'entries' => $group->values()->all(),
            ])
            ->values()
            ->all();
    }

    /* ------------------------------------------------------------------ */
    /* Normalisation                                                       */
    /* ------------------------------------------------------------------ */

    /**
     * Turn one activity_log row into a display entry.
     *
     * Keys: at, actor, category, kind, tone, icon, subject, title, detail, items
     */
    protected function normalize(Activity $a): array
    {
        $props = $a->properties ?? collect();
        $old = $props['old'] ?? [];
        $new = $props['attributes'] ?? [];
        $subject = $a->subject;

        $entry = [
            'id'       => $a->id,
            'at'       => $a->created_at,
            'actor'    => $a->causer?->name ?? 'Sistem',
            'actorId'  => $a->causer_id,
            'category' => 'proyek',
            'kind'     => 'lainnya',
            'tone'     => 'gray',
            'icon'     => 'pencil',
            'subject'  => null,
            'title'    => $this->cleanDescription($a->description),
            'detail'   => null,
            'items'    => [],
        ];

        switch ($a->subject_type) {
            case Project::class:
                $entry['category'] = 'proyek';
                if (($props['action'] ?? null) !== null && isset($props['new_pic'])) {
                    $entry += [];
                    $entry['kind'] = 'proyek.pic';
                    $entry['tone'] = 'gray';
                    $entry['icon'] = 'user';
                    $entry['title'] = 'PIC proyek diganti';
                    $entry['detail'] = ($props['previous_pic']['name'] ?? 'Belum ada') . ' → ' . ($props['new_pic']['name'] ?? '-');
                } elseif ($a->event === 'created') {
                    $entry['kind'] = 'proyek.dibuat';
                    $entry['tone'] = 'gray';
                    $entry['icon'] = 'plus';
                    $entry['title'] = 'Proyek dibuat';
                } elseif ($a->event === 'deleted') {
                    $entry['kind'] = 'proyek.dihapus';
                    $entry['tone'] = 'danger';
                    $entry['icon'] = 'trash';
                    $entry['title'] = 'Proyek dihapus';
                } elseif (array_key_exists('status', $new)) {
                    $entry['kind'] = 'proyek.status';
                    $entry['tone'] = $this->projectStatusTone($new['status']);
                    $entry['icon'] = 'flag';
                    $entry['title'] = 'Status proyek diubah';
                    $entry['detail'] = $this->projectStatusLabel($old['status'] ?? null) . ' → ' . $this->projectStatusLabel($new['status']);
                } elseif (array_key_exists('due_date', $new)) {
                    $entry['kind'] = 'proyek.tenggat';
                    $entry['tone'] = 'warning';
                    $entry['icon'] = 'calendar';
                    $entry['title'] = 'Tenggat proyek diubah';
                    $entry['detail'] = $this->date($old['due_date'] ?? null) . ' → ' . $this->date($new['due_date']);
                } elseif (array_key_exists('priority', $new)) {
                    $entry['kind'] = 'proyek.prioritas';
                    $entry['tone'] = 'warning';
                    $entry['icon'] = 'flag';
                    $entry['title'] = 'Prioritas proyek diubah';
                    $entry['detail'] = ucfirst($old['priority'] ?? '-') . ' → ' . ucfirst($new['priority']);
                } else {
                    $entry['kind'] = 'proyek.detail';
                    $entry['icon'] = 'pencil';
                    $entry['title'] = 'Detail proyek diperbarui';
                    $entry['detail'] = $this->changedFields($new);
                }
                break;

            case ProjectStep::class:
                $name = $subject?->name ?? $this->nameFromDescription($a->description, ': ');
                $entry['category'] = 'tahapan';
                $entry['subject'] = $name;
                if ($a->event === 'created') {
                    $entry['kind'] = 'tahapan.dibuat';
                    $entry['tone'] = 'gray';
                    $entry['icon'] = 'plus';
                    $entry['title'] = 'Tahapan ditambahkan';
                } elseif ($a->event === 'deleted') {
                    $entry['kind'] = 'tahapan.dihapus';
                    $entry['tone'] = 'danger';
                    $entry['icon'] = 'trash';
                    $entry['title'] = 'Tahapan dihapus';
                } elseif (array_key_exists('status', $new)) {
                    [$title, $tone, $icon] = match ($new['status']) {
                        'completed'   => ['Tahapan selesai', 'success', 'check'],
                        'in_progress' => ['Tahapan mulai dikerjakan', 'warning', 'play'],
                        default       => ['Tahapan dikembalikan ke belum mulai', 'gray', 'arrow-uturn'],
                    };
                    $entry['kind'] = 'tahapan.status.' . $new['status'];
                    $entry['tone'] = $tone;
                    $entry['icon'] = $icon;
                    $entry['title'] = $title;
                } elseif (array_key_exists('name', $new)) {
                    $entry['kind'] = 'tahapan.nama';
                    $entry['icon'] = 'pencil';
                    $entry['title'] = 'Tahapan diganti nama';
                    $entry['detail'] = ($old['name'] ?? '-') . ' → ' . $new['name'];
                    $entry['subject'] = null;
                } else {
                    $entry['kind'] = 'tahapan.detail';
                    $entry['icon'] = 'pencil';
                    $entry['title'] = 'Tahapan diperbarui';
                    $entry['detail'] = $this->changedFields($new);
                }
                break;

            case Task::class:
                $title = $subject?->title ?? $this->nameFromDescription($a->description, ': ', ' |');
                $entry['category'] = 'tugas';
                $entry['subject'] = $title;
                if ($a->event === 'created') {
                    $entry['kind'] = 'tugas.dibuat';
                    $entry['tone'] = 'gray';
                    $entry['icon'] = 'plus';
                    $entry['title'] = 'Tugas ditambahkan';
                } elseif ($a->event === 'deleted') {
                    $entry['kind'] = 'tugas.dihapus';
                    $entry['tone'] = 'danger';
                    $entry['icon'] = 'trash';
                    $entry['title'] = 'Tugas dihapus';
                } elseif (array_key_exists('status', $new)) {
                    [$t, $tone, $icon] = match ($new['status']) {
                        'completed'   => ['Tugas selesai', 'success', 'check'],
                        'in_progress' => ['Tugas mulai dikerjakan', 'warning', 'play'],
                        'blocked'     => ['Tugas terblokir', 'danger', 'no-symbol'],
                        default       => ['Tugas dikembalikan ke tertunda', 'gray', 'arrow-uturn'],
                    };
                    $entry['kind'] = 'tugas.status.' . $new['status'];
                    $entry['tone'] = $tone;
                    $entry['icon'] = $icon;
                    $entry['title'] = $t;
                } else {
                    $entry['kind'] = 'tugas.detail';
                    $entry['icon'] = 'pencil';
                    $entry['title'] = 'Tugas diperbarui';
                    $entry['detail'] = $this->changedFields($new);
                }
                break;

            case RequiredDocument::class:
                $name = $subject?->name ?? $this->requiredDocNameFromDescription($a->description);
                $entry['category'] = 'dokumen';
                $entry['subject'] = $name;
                if ($a->event === 'created') {
                    $entry['kind'] = 'dokumen.dibuat';
                    $entry['tone'] = 'gray';
                    $entry['icon'] = 'plus';
                    $entry['title'] = 'Persyaratan dokumen ditambahkan';
                } elseif ($a->event === 'deleted') {
                    $entry['kind'] = 'dokumen.dihapus';
                    $entry['tone'] = 'danger';
                    $entry['icon'] = 'trash';
                    $entry['title'] = 'Persyaratan dokumen dihapus';
                } elseif (array_key_exists('status', $new)) {
                    [$t, $tone, $icon] = match ($new['status']) {
                        'approved'                  => ['Dokumen disetujui', 'success', 'check'],
                        'approved_without_document' => ['Dokumen disetujui tanpa file', 'success', 'check'],
                        'pending_review'            => ['Dokumen menunggu review', 'warning', 'eye'],
                        'uploaded'                  => ['Dokumen menerima file baru', 'info', 'upload'],
                        'rejected'                  => ['Dokumen ditolak', 'danger', 'x'],
                        default                     => ['Dokumen kembali ke draft', 'gray', 'arrow-uturn'],
                    };
                    $entry['kind'] = 'dokumen.status.' . $new['status'];
                    $entry['tone'] = $tone;
                    $entry['icon'] = $icon;
                    $entry['title'] = $t;
                } else {
                    $entry['kind'] = 'dokumen.detail';
                    $entry['icon'] = 'pencil';
                    $entry['title'] = 'Persyaratan dokumen diperbarui';
                    $entry['detail'] = $this->changedFields($new);
                }
                break;

            case SubmittedDocument::class:
                $file = $subject?->file_path ? basename($subject->file_path) : $this->quotedFromDescription($a->description);
                $req = $subject?->requiredDocument?->name;
                $entry['category'] = 'dokumen';
                $entry['subject'] = $file;
                $entry['detail'] = $req ? 'untuk ' . $req : null;
                if ($a->event === 'created') {
                    $entry['kind'] = 'file.diunggah';
                    $entry['tone'] = 'info';
                    $entry['icon'] = 'upload';
                    $entry['title'] = 'File diunggah';
                } elseif ($a->event === 'deleted') {
                    $entry['kind'] = 'file.dihapus';
                    $entry['tone'] = 'danger';
                    $entry['icon'] = 'trash';
                    $entry['title'] = 'File dihapus';
                } elseif (array_key_exists('status', $new)) {
                    [$t, $tone, $icon] = match ($new['status']) {
                        'approved'       => ['File disetujui', 'success', 'check'],
                        'rejected'       => ['File ditolak', 'danger', 'x'],
                        'pending_review' => ['File sedang diperiksa', 'warning', 'eye'],
                        default          => ['File diperbarui', 'info', 'upload'],
                    };
                    $entry['kind'] = 'file.status.' . $new['status'];
                    $entry['tone'] = $tone;
                    $entry['icon'] = $icon;
                    $entry['title'] = $t;
                    if ($new['status'] === 'rejected' && filled($subject?->rejection_reason)) {
                        $entry['detail'] = 'Alasan: ' . $subject->rejection_reason;
                    }
                } else {
                    $entry['kind'] = 'file.detail';
                    $entry['icon'] = 'pencil';
                    $entry['title'] = 'File diperbarui';
                }
                break;
        }

        return $entry;
    }

    /**
     * Collapse runs of the same action by the same person within a few
     * minutes into one entry ("4 file disetujui") so bulk approvals do not
     * flood the feed. Entries with a change detail (old → new) are kept apart.
     */
    protected function mergeBursts(Collection $entries): Collection
    {
        $merged = collect();

        foreach ($entries as $entry) {
            $last = $merged->last();
            $mergeable = $last
                && $last['kind'] === $entry['kind']
                && $last['actorId'] === $entry['actorId']
                && $entry['subject'] !== null
                && ! str_contains((string) ($entry['detail'] ?? ''), '→')
                && ! str_contains((string) ($last['detail'] ?? ''), '→')
                && abs($last['at']->diffInMinutes($entry['at'])) <= 10;

            if ($mergeable) {
                $last['items'][] = ['subject' => $entry['subject'], 'detail' => $entry['detail'], 'at' => $entry['at']];
                $last['subject'] = null;
                $last['detail'] = null;
                $merged->put($merged->keys()->last(), $last);
                continue;
            }

            $entry['items'] = [['subject' => $entry['subject'], 'detail' => $entry['detail'], 'at' => $entry['at']]];
            $merged->push($entry);
        }

        // Single-item groups go back to their plain shape.
        return $merged->map(function (array $e) {
            if (count($e['items']) === 1) {
                $e['subject'] = $e['items'][0]['subject'];
                $e['detail'] = $e['items'][0]['detail'];
                $e['items'] = [];
            }

            return $e;
        });
    }

    /* ------------------------------------------------------------------ */
    /* Small helpers                                                       */
    /* ------------------------------------------------------------------ */

    protected function dayLabel(Carbon $date): string
    {
        if ($date->isToday()) {
            return 'Hari ini';
        }
        if ($date->isYesterday()) {
            return 'Kemarin';
        }

        return $date->locale('id')->translatedFormat('l, d M Y');
    }

    protected function date(?string $value): string
    {
        return $value ? Carbon::parse($value)->locale('id')->translatedFormat('d M Y') : '-';
    }

    protected function projectStatusLabel(?string $key): string
    {
        if ($key === null) {
            return '-';
        }

        return ProjectStatus::where('key', $key)->value('label') ?? ucwords(str_replace('_', ' ', $key));
    }

    protected function projectStatusTone(?string $key): string
    {
        return match (ProjectStatus::where('key', $key)->value('category')) {
            'done'   => 'success',
            'active' => 'warning',
            'closed' => 'danger',
            default  => 'gray',
        };
    }

    protected function changedFields(array $new): ?string
    {
        $labels = [
            'name' => 'nama', 'description' => 'deskripsi', 'title' => 'judul', 'order' => 'urutan',
            'type' => 'tipe', 'is_required' => 'wajib', 'requires_document' => 'butuh dokumen',
            'due_date' => 'tenggat', 'priority' => 'prioritas', 'start_date' => 'tanggal mulai',
        ];
        $fields = collect(array_keys($new))
            ->map(fn ($f) => $labels[$f] ?? str_replace('_', ' ', $f))
            ->unique()
            ->values();

        return $fields->isEmpty() ? null : 'Perubahan: ' . $fields->join(', ');
    }

    /** Strip the "[Client] emoji" prefix the model loggers put in front of descriptions. */
    protected function cleanDescription(?string $description): string
    {
        $text = preg_replace('/^\[[^\]]*\]\s*/u', '', (string) $description);
        $text = preg_replace('/^[^\p{L}\p{N}"\']+/u', '', $text);

        return trim($text) ?: 'Aktivitas';
    }

    protected function nameFromDescription(?string $description, string $after, ?string $before = null): ?string
    {
        $text = $this->cleanDescription($description);
        $pos = strpos($text, $after);
        if ($pos === false) {
            return null;
        }
        $text = substr($text, $pos + strlen($after));
        if ($before !== null && ($end = strpos($text, $before)) !== false) {
            $text = substr($text, 0, $end);
        }

        return trim($text) ?: null;
    }

    protected function requiredDocNameFromDescription(?string $description): ?string
    {
        $text = $this->cleanDescription($description);
        // "Semua dokumen X telah disetujui" / "Dokumen X menunggu peninjauan" / "...ditambahkan: X pada ..."
        if (preg_match('/ditambahkan: (.+?) pada /u', $text, $m)) {
            return $m[1];
        }
        if (preg_match('/dihapus: (.+?) dari /u', $text, $m)) {
            return $m[1];
        }
        if (preg_match('/^(?:Semua dokumen|Dokumen|Beberapa dokumen|Dokumen baru diunggah untuk) (.+?) (?:telah|menunggu|ditolak|masih)/u', $text, $m)) {
            return $m[1];
        }

        return null;
    }

    protected function quotedFromDescription(?string $description): ?string
    {
        return preg_match('/"([^"]+)"/u', (string) $description, $m) ? $m[1] : null;
    }

    public function render()
    {
        return view('livewire.projects.components.project-activity-feed', [
            'feed'   => $this->feed,
            'counts' => $this->counts,
            'total'  => $this->total,
        ]);
    }
}
