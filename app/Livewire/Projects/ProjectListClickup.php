<?php

namespace App\Livewire\Projects;

use App\Filament\Resources\ProjectResource;
use App\Models\Client;
use App\Models\Project;
use App\Models\ProjectStatus;
use App\Models\SubmittedDocument;
use App\Models\User;
use App\Models\UserProject;
use Illuminate\Support\Facades\Storage;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Support\Contracts\TranslatableContentDriver;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Url;
use Livewire\Component;

class ProjectListClickup extends Component implements HasActions, HasForms
{
    use InteractsWithActions;
    use InteractsWithForms;

    public function makeFilamentTranslatableContentDriver(): ?TranslatableContentDriver
    {
        return null;
    }

    public const HARD_CAP = 500;
    public const DEFAULT_GROUP_LIMIT = 10;

    #[Url(as: 'q')]
    public string $search = '';

    #[Url(as: 'type')]
    public string $typeFilter = 'all';

    #[Url(as: 'status')]
    public array $statusFilter = [];

    #[Url(as: 'priority')]
    public array $priorityFilter = [];

    #[Url(as: 'due')]
    public string $dueDateFilter = 'any';

    #[Url(as: 'pic')]
    public array $picFilter = [];

    #[Url(as: 'client')]
    public array $clientFilter = [];

    /** Department id, 'none' for projects without one, or '' for all. Set by the sidebar sub-menu. */
    #[Url(as: 'dept')]
    public string $departmentFilter = '';

    /**
     * Quick SOP filter driven by the pill row under the toolbar.
     * Empty = all. Otherwise a list of SOP ids (as strings) and/or the
     * literal 'none' for projects without an SOP. Multi-select, OR-ed.
     *
     * Deliberately untyped: Livewire assigns the raw query-string value
     * before mount(), and bookmarked URLs from the single-select era carry
     * a scalar (?sop=3). A typed `array` would throw there. Always read
     * through $this->sopSelection(), never the raw property.
     */
    #[Url(as: 'sop')]
    public $sopFilter = [];

    #[Url(as: 'assignee')]
    public array $assigneeFilter = [];

    #[Url(as: 'active_clients')]
    public bool $activeClientsOnly = true;

    #[Url(as: 'group')]
    public string $groupBy = 'status';

    #[Url(as: 'sort')]
    public string $sortField = 'name';

    #[Url(as: 'dir')]
    public string $sortDirection = 'asc';

    #[Url(as: 'cols')]
    public array $visibleColumns = ['status', 'client', 'assignees', 'progress'];

    #[Url(as: 'view')]
    public string $viewMode = 'list';

    public function switchView(string $mode): void
    {
        if (in_array($mode, ['list', 'board'], true)) {
            $this->viewMode = $mode;
        }
    }

    public array $expandedGroups = [];

    // Status manager panel
    public bool $showStatusManager = false;
    public string $newStatusLabel = '';
    public string $newStatusColor = '#2563eb';
    public string $newStatusShape = 'empty';
    public string $newStatusCategory = 'active';

    // Inline edit state
    public ?int $editingStatusId = null;
    public string $editLabel = '';
    public string $editColor = '#2563eb';
    public string $editShape = 'empty';
    public string $editCategory = 'active';

    /**
     * Fix B: lazy-load filter options.
     * Filter dropdown options (picOptions, clientOptions, assigneeOptions) are
     * skipped from every render until the user opens the filter panel once.
     * Once loaded, stays true for the session.
     */
    public bool $filterOptionsLoaded = false;

    // Project view modal
    public ?int $viewingProjectId = null;
    /**
     * Set ke nilai viewingProjectId hanya SETELAH data heavy query selesai di-load.
     * Dipakai untuk deferred loading: modal buka instant dengan skeleton (idLoaded=null),
     * lalu request kedua memanggil loadProjectViewData() yang mengisi idLoaded
     * → computed property `viewingProject` baru menjalankan query saat itu.
     */
    public ?int $viewingProjectIdLoaded = null;
    public string $newNoteContent = '';
    public string $newNoteType = 'general';

    /**
     * Fix C: open + load in a SINGLE round-trip.
     *
     * Previously this was a 2-step skeleton-then-data flow that fired two
     * round-trips. With A+B making renders much faster (~10-15ms total),
     * the single-render approach actually feels snappier than the skeleton
     * flicker that used to happen.
     *
     * loadProjectViewData() is still here for backward compatibility with
     * any x-init calls that survived in cached/partial DOM.
     */
    public function openProjectView(int $projectId): void
    {
        $this->viewingProjectId = $projectId;
        $this->viewingProjectIdLoaded = $projectId; // load immediately, no skeleton step
        $this->resetNoteForm();
    }

    /**
     * Kept as a no-op-ish helper; just ensures the loaded id matches.
     * Safe to call multiple times — idempotent.
     */
    public function loadProjectViewData(): void
    {
        $this->viewingProjectIdLoaded = $this->viewingProjectId;
    }

    // Document preview modal — sub-modal di dalam project view modal.
    public ?int $previewingSubmittedDocumentId = null;

    public function openSubmittedDocumentPreview(int $id): void
    {
        $this->previewingSubmittedDocumentId = $id;
    }

    public function closeSubmittedDocumentPreview(): void
    {
        $this->previewingSubmittedDocumentId = null;
    }

    public function getPreviewingSubmittedDocumentProperty(): ?SubmittedDocument
    {
        if (! $this->previewingSubmittedDocumentId) {
            return null;
        }

        return SubmittedDocument::with(['user:id,name', 'requiredDocument:id,name'])
            ->find($this->previewingSubmittedDocumentId);
    }

    public function getPreviewUrlProperty(): ?string
    {
        $doc = $this->previewingSubmittedDocument;
        if (! $doc || empty($doc->file_path)) {
            return null;
        }
        return Storage::disk('public')->url($doc->file_path);
    }

    public function getPreviewFileTypeProperty(): ?string
    {
        $doc = $this->previewingSubmittedDocument;
        if (! $doc || empty($doc->file_path)) {
            return null;
        }
        return strtolower(pathinfo($doc->file_path, PATHINFO_EXTENSION));
    }

    public function closeProjectView(): void
    {
        $this->viewingProjectId = null;
        $this->viewingProjectIdLoaded = null;
        $this->resetNoteForm();

        // Fix C: skip the full component re-render. The modal is wrapped in
        // an Alpine `x-show="$wire.viewingProjectId"` so visibility flips
        // client-side as soon as the state syncs. Saves ~20 queries on close.
        $this->skipRender();
    }

    protected function resetNoteForm(): void
    {
        $this->newNoteContent = '';
        $this->newNoteType = 'general';
        $this->resetErrorBag(['newNoteContent']);
    }

    public function addNote(): void
    {
        $this->validate([
            'newNoteContent' => 'required|string|min:2|max:2000',
            'newNoteType'    => 'required|in:general,important,blocker',
        ], [], [
            'newNoteContent' => 'catatan',
            'newNoteType'    => 'tipe catatan',
        ]);

        if (! $this->viewingProjectId) {
            return;
        }

        \App\Models\ProjectNote::create([
            'project_id' => $this->viewingProjectId,
            'user_id'    => auth()->id(),
            'content'    => trim($this->newNoteContent),
            'type'       => $this->newNoteType,
        ]);

        $this->resetNoteForm();
    }

    public function getViewingProjectProperty(): ?Project
    {
        // Deferred: hanya jalankan heavy query setelah loadProjectViewData() dipanggil.
        // Modal buka instant dengan skeleton ketika viewingProjectId di-set tapi
        // viewingProjectIdLoaded masih null.
        if (! $this->viewingProjectIdLoaded) {
            return null;
        }

        return Project::query()
            ->with([
                'client:id,name,status',
                'department:id,name',
                'pic:id,name,avatar_url,avatar_path',
                'teamMembers:id,name,avatar_url,avatar_path',
                'steps' => fn ($q) => $q->orderBy('order')->with([
                    'requiredDocuments' => fn ($r) => $r->with([
                        'submittedDocuments' => fn ($s) => $s->latest(),
                    ]),
                ]),
                'statusRecord',
                'notes' => fn ($q) => $q->with('user:id,name,avatar_url,avatar_path')->latest(),
            ])
            ->withCount([
                'steps',
                'steps as steps_completed_count' => fn ($q) => $q->where('status', 'completed'),
            ])
            ->find($this->viewingProjectIdLoaded);
    }

    public function getDepartmentOptionsProperty()
    {
        // Cached across renders — departments rarely change.
        return \Illuminate\Support\Facades\Cache::remember(
            'project_list_department_options',
            now()->addSeconds(60),
            fn () => \App\Models\Department::query()->orderBy('name')->get(['id', 'name']),
        );
    }

    public function updateProjectDepartment(int $projectId, ?int $departmentId): void
    {
        $project = Project::find($projectId);
        if (! $project) {
            return;
        }

        // Validate the department exists when one is selected
        if ($departmentId !== null && ! \App\Models\Department::whereKey($departmentId)->exists()) {
            Notification::make()
                ->title('Departemen tidak ditemukan')
                ->danger()
                ->send();
            return;
        }

        $project->department_id = $departmentId;
        $project->save();

        Notification::make()
            ->title('Departemen diperbarui')
            ->success()
            ->send();
    }

    public function downloadSubmittedDocument(int $documentId)
    {
        $doc = \App\Models\SubmittedDocument::find($documentId);
        if (! $doc || ! $doc->file_path) {
            Notification::make()
                ->title('Dokumen tidak ditemukan')
                ->danger()
                ->send();
            return null;
        }

        if (! \Illuminate\Support\Facades\Storage::disk('public')->exists($doc->file_path)) {
            Notification::make()
                ->title('File tidak ada di server')
                ->danger()
                ->send();
            return null;
        }

        return response()->download(
            \Illuminate\Support\Facades\Storage::disk('public')->path($doc->file_path),
            basename($doc->file_path),
        );
    }

    public const TOGGLEABLE_COLUMNS = [
        'status' => 'Status',
        'client' => 'Client',
        'type' => 'Type',
        'assignees' => 'Assignees',
        'progress' => 'Progress',
    ];

    /**
     * Group-by choices in the order the toolbar stepper cycles through them.
     * The blade reads the same list, so labels live in one place.
     */
    public const GROUP_BY_OPTIONS = [
        'status'     => 'Status',
        'priority'   => 'Priority',
        'pic'        => 'PIC',
        'client'     => 'Client',
        'department' => 'Departemen',
        'sop'        => 'SOP',
        'none'       => 'None',
    ];

    public const PRIORITIES = [
        'urgent' => ['label' => 'Urgent', 'color' => '#b91c1c', 'bg' => '#fee2e2'],
        'normal' => ['label' => 'Normal', 'color' => '#0e7490', 'bg' => '#cffafe'],
        'low' => ['label' => 'Low', 'color' => '#64748b', 'bg' => '#f1f5f9'],
    ];

    public const STEP_STATUSES = [
        'pending' => ['label' => 'Pending', 'color' => '#94a3b8', 'bg' => '#f1f5f9'],
        'in_progress' => ['label' => 'In Progress', 'color' => '#3b82f6', 'bg' => '#dbeafe'],
        'waiting_for_documents' => ['label' => 'Waiting Docs', 'color' => '#eab308', 'bg' => '#fef9c3'],
        'completed' => ['label' => 'Completed', 'color' => '#22c55e', 'bg' => '#dcfce7'],
    ];

    public const TYPES = [
        'all' => 'Any',
        'single' => 'On Spot',
        'monthly' => 'Monthly',
        'yearly' => 'Yearly',
    ];

    public const DUE_DATE_PRESETS = [
        'any' => 'Any',
        'overdue' => 'Overdue',
        'today' => 'Today',
        'this_week' => 'This Week',
        'this_month' => 'This Month',
        'no_due_date' => 'No Due Date',
    ];

    public function clearFilters(): void
    {
        $this->reset([
            'search',
            'typeFilter',
            'statusFilter',
            'priorityFilter',
            'dueDateFilter',
            'picFilter',
            'clientFilter',
            'departmentFilter',
            'assigneeFilter',
            'sopFilter',
        ]);
    }

    /**
     * Pill row handler. '' clears everything ("Semua"); any other value is
     * added to or removed from the selection.
     */
    public function toggleSopFilter(string $value): void
    {
        if ($value === '') {
            $this->sopFilter = [];
            return;
        }

        $current = $this->sopSelection();

        $this->sopFilter = in_array($value, $current, true)
            ? array_values(array_diff($current, [$value]))
            : [...$current, $value];
    }

    /**
     * Normalised SOP selection: always a list of strings, accepting the
     * scalar / int / nested shapes a query string can produce.
     *
     * A plain method on purpose: Livewire memoises getXProperty computed
     * values for the whole request, which would hand toggleSopFilter() a
     * stale list after it mutates $sopFilter.
     */
    public function sopSelection(): array
    {
        $raw = $this->sopFilter;

        if ($raw === null || $raw === '' || $raw === []) {
            return [];
        }

        return collect(\Illuminate\Support\Arr::wrap($raw))
            ->flatten()
            ->map(fn ($v) => trim((string) $v))
            ->filter(fn (string $v) => $v === 'none' || ctype_digit($v))
            ->unique()
            ->values()
            ->all();
    }

    public function removeStatus(string $status): void
    {
        $this->statusFilter = array_values(array_diff($this->statusFilter, [$status]));
    }

    public function removePriority(string $priority): void
    {
        $this->priorityFilter = array_values(array_diff($this->priorityFilter, [$priority]));
    }

    public function removePic(int $id): void
    {
        $this->picFilter = array_values(array_filter($this->picFilter, fn ($v) => (int) $v !== $id));
    }

    public function removeClient(int $id): void
    {
        $this->clientFilter = array_values(array_filter($this->clientFilter, fn ($v) => (int) $v !== $id));
    }

    public function removeAssignee(int $id): void
    {
        $this->assigneeFilter = array_values(array_filter($this->assigneeFilter, fn ($v) => (int) $v !== $id));
    }

    public function resetType(): void
    {
        $this->typeFilter = 'all';
    }

    public function resetDueDate(): void
    {
        $this->dueDateFilter = 'any';
    }

    public function hasActiveFilters(): bool
    {
        return $this->search !== ''
            || $this->typeFilter !== 'all'
            || $this->dueDateFilter !== 'any'
            || !empty($this->statusFilter)
            || !empty($this->priorityFilter)
            || !empty($this->picFilter)
            || !empty($this->clientFilter)
            || $this->departmentFilter !== ''
            || !empty($this->assigneeFilter)
            || !empty($this->sopSelection());
    }

    public function getActiveFilterCountProperty(): int
    {
        $count = 0;
        $count += \count($this->statusFilter);
        $count += \count($this->priorityFilter);
        $count += \count($this->picFilter);
        $count += \count($this->clientFilter);
        $count += \count($this->assigneeFilter);
        if ($this->typeFilter !== 'all') $count++;
        if ($this->dueDateFilter !== 'any') $count++;
        if ($this->departmentFilter !== '') $count++;
        return $count;
    }

    /**
     * The status map used throughout the view.
     * Loaded from the project_statuses table — supports user-created statuses.
     *
     * Returns: [ key => ['label', 'color', 'bg', 'shape', 'category', 'sort_order', 'is_system'] ]
     * Ordered by category then sort_order.
     */
    public function getStatusMapProperty(): array
    {
        // Cached — statuses change very rarely (only via the status manager page).
        return \Illuminate\Support\Facades\Cache::remember(
            'project_list_status_map',
            now()->addSeconds(60),
            fn () => \App\Models\ProjectStatus::ordered()
                ->get()
                ->mapWithKeys(fn ($s) => [
                    $s->key => [
                        'label'      => $s->label,
                        'color'      => $s->color,
                        'bg'         => $s->bg_color,
                        'shape'      => $s->shape,
                        'category'   => $s->category,
                        'sort_order' => $s->sort_order,
                        'is_system'  => $s->is_system,
                    ],
                ])
                ->toArray(),
        );
    }

    /**
     * Called when the user opens the filter panel for the first time.
     * Sets the flag so the next render starts computing/caching the
     * filter dropdown option lists (PIC, Client, Assignee).
     */
    /**
     * Step the group-by option forward (+1) or backward (-1), wrapping around.
     * Driven by the left/right arrows on the toolbar stepper.
     */
    public function cycleGroupBy(int $direction): void
    {
        $keys = array_keys(self::GROUP_BY_OPTIONS);
        $count = count($keys);
        $current = array_search($this->groupBy, $keys, true);

        if ($current === false) {
            $current = 0;
        }

        $step = $direction < 0 ? -1 : 1;
        $this->groupBy = $keys[($current + $step + $count) % $count];
    }

    public function loadFilterOptions(): void
    {
        $this->filterOptionsLoaded = true;
    }

    public function toggleColumn(string $key): void
    {
        if (!\array_key_exists($key, self::TOGGLEABLE_COLUMNS)) {
            return;
        }
        if (\in_array($key, $this->visibleColumns, true)) {
            $this->visibleColumns = array_values(array_diff($this->visibleColumns, [$key]));
        } else {
            $this->visibleColumns[] = $key;
        }
    }

    public function isColumnVisible(string $key): bool
    {
        return \in_array($key, $this->visibleColumns, true);
    }

    public function getGridTemplateProperty(): string
    {
        $cols = ['30px', 'minmax(220px, 1.5fr)'];                  // status-icon, name
        if ($this->isColumnVisible('status'))    $cols[] = '150px'; // status badge
        if ($this->isColumnVisible('client'))    $cols[] = '160px'; // client
        if ($this->isColumnVisible('type'))      $cols[] = '90px';  // type
        $cols[] = '110px';                                          // priority (always)
        if ($this->isColumnVisible('assignees')) $cols[] = '140px'; // assignees
        $cols[] = '150px';                                          // department (always — replaces due)
        if ($this->isColumnVisible('progress'))  $cols[] = '140px'; // progress
        $cols[] = '40px';                                           // actions (always)
        return implode(' ', $cols);
    }

    public function toggleGroupExpand(string $groupKey): void
    {
        if (\in_array($groupKey, $this->expandedGroups, true)) {
            $this->expandedGroups = array_values(array_diff($this->expandedGroups, [$groupKey]));
        } else {
            $this->expandedGroups[] = $groupKey;
        }
    }

    public function sortBy(string $field): void
    {
        if ($this->sortField === $field) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortField = $field;
            $this->sortDirection = 'asc';
        }
    }

    public function updateStatus(int $projectId, string $status): void
    {
        $statusMap = $this->statusMap;
        if (!\array_key_exists($status, $statusMap)) {
            return;
        }

        $project = $this->baseQuery()->find($projectId);
        if (!$project || $project->status === $status) {
            return;
        }

        $project->status = $status;
        $project->save();

        Notification::make()
            ->title("Status diubah ke " . $statusMap[$status]['label'])
            ->success()
            ->send();
    }

    public function updatePriority(int $projectId, string $priority): void
    {
        if (!\array_key_exists($priority, self::PRIORITIES)) {
            return;
        }

        $project = $this->baseQuery()->find($projectId);
        if (!$project || $project->priority === $priority) {
            return;
        }

        $project->priority = $priority;
        $project->save();

        Notification::make()
            ->title("Prioritas diubah ke " . self::PRIORITIES[$priority]['label'])
            ->success()
            ->send();
    }

    public function addProjectMember(int $projectId, int $userId): void
    {
        $project = $this->baseQuery()->find($projectId);
        if (!$project) return;

        $exists = UserProject::where('project_id', $projectId)
            ->where('user_id', $userId)
            ->exists();
        if ($exists) return;

        $user = User::find($userId);
        if (!$user) return;

        UserProject::create([
            'project_id'    => $projectId,
            'user_id'       => $userId,
            'role'          => null,
            'assigned_date' => now()->toDateString(),
        ]);

        Notification::make()
            ->title("{$user->name} ditambahkan ke {$project->name}")
            ->success()
            ->send();
    }

    public function removeProjectMember(int $projectId, int $userId): void
    {
        $project = $this->baseQuery()->find($projectId);
        if (!$project) return;

        $user = User::find($userId);
        if (!$user) return;

        UserProject::where('project_id', $projectId)
            ->where('user_id', $userId)
            ->delete();

        Notification::make()
            ->title("{$user->name} dikeluarkan dari {$project->name}")
            ->success()
            ->send();
    }

    /**
     * Pool of users that can be added as project members.
     * Active users only, excludes anyone with the 'client' role.
     */
    public function getTeamPoolProperty()
    {
        // Cached — staff roster changes infrequently.
        return \Illuminate\Support\Facades\Cache::remember(
            'project_list_team_pool',
            now()->addSeconds(60),
            fn () => User::where('status', 'active')
                ->whereDoesntHave('roles', fn ($q) => $q->where('name', 'client'))
                ->orderBy('name')
                ->get(['id', 'name', 'avatar_url', 'avatar_path']),
        );
    }

    protected function baseQuery(): Builder
    {
        // No user_clients gate — every authenticated user can see all projects.
        // Access control is handled at the panel/role level, not via user_clients.
        return Project::query();
    }

    protected function filteredQuery(): Builder
    {
        $query = $this->baseQuery()
            ->with([
                'client:id,name',
                'department:id,name',
                'sop:id,name',
                'pic:id,name,avatar_url,avatar_path',
                'teamMembers:id,name,avatar_url,avatar_path',
                'steps:id,project_id,status,name,order,due_date,priority',
                'statusRecord',
            ])
            ->withCount([
                'steps',
                'steps as steps_completed_count' => fn ($q) => $q->where('status', 'completed'),
                'notes',
            ])
            // Correlated subquery — hitung submitted documents berstatus 'uploaded'
            // (file baru di-upload klien, staff belum review). Satu kolom hasil per row,
            // tetap single round-trip DB.
            ->addSelect([
                'uploaded_documents_count' => DB::table('submitted_documents')
                    ->selectRaw('COUNT(*)')
                    ->join('required_documents', 'required_documents.id', '=', 'submitted_documents.required_document_id')
                    ->join('project_steps', 'project_steps.id', '=', 'required_documents.project_step_id')
                    ->whereColumn('project_steps.project_id', 'projects.id')
                    ->where('submitted_documents.status', 'uploaded'),
            ]);

        return $this->applySort($this->applyFilters($query));
    }

    /**
     * Every WHERE clause the toolbar controls, applied to $query.
     * $withSop=false skips the SOP pill filter so the pill counts can be
     * computed against "everything else the user has filtered".
     */
    protected function applyFilters(Builder $query, bool $withSop = true): Builder
    {
        if ($this->search !== '') {
            $term = '%' . $this->search . '%';
            $query->where(function ($q) use ($term) {
                $q->where('name', 'like', $term)
                  ->orWhereHas('client', fn ($c) => $c->where('name', 'like', $term));
            });
        }

        if ($this->typeFilter !== 'all') {
            $query->where('type', $this->typeFilter);
        }

        if (!empty($this->statusFilter)) {
            $query->whereIn('status', $this->statusFilter);
        }

        if (!empty($this->priorityFilter)) {
            $query->whereIn('priority', $this->priorityFilter);
        }

        if (!empty($this->picFilter)) {
            $query->whereIn('pic_id', $this->picFilter);
        }

        if (!empty($this->clientFilter)) {
            $query->whereIn('client_id', $this->clientFilter);
        }

        if ($this->departmentFilter === 'none') {
            $query->whereNull('department_id');
        } elseif ($this->departmentFilter !== '' && ctype_digit($this->departmentFilter)) {
            $query->where('department_id', (int) $this->departmentFilter);
        }

        $selection = $this->sopSelection();

        if ($withSop && !empty($selection)) {
            $includeNone = in_array('none', $selection, true);
            $sopIds = array_values(array_filter(
                array_map('intval', array_diff($selection, ['none'])),
                fn (int $id) => $id > 0,
            ));

            $query->where(function ($q) use ($includeNone, $sopIds) {
                if ($sopIds) {
                    $q->whereIn('sop_id', $sopIds);
                }
                if ($includeNone) {
                    $sopIds ? $q->orWhereNull('sop_id') : $q->whereNull('sop_id');
                }
            });
        }

        if ($this->activeClientsOnly) {
            $query->whereHas('client', fn ($q) => $q->where('status', 'Active'));
        }

        if (!empty($this->assigneeFilter)) {
            $query->whereHas('userProjects', function ($q) {
                $q->whereIn('user_id', $this->assigneeFilter);
            });
        }

        $this->applyDueDateFilter($query);

        return $query;
    }

    /**
     * Counts for the SOP pill row, respecting every other active filter.
     *
     * Returns ['total' => int, 'none' => int, 'sops' => [['id','name','count'], ...]]
     * SOPs with zero matches are dropped, except the one currently selected
     * so the user can always click it again to deselect.
     */
    public function getSopFacetsProperty(): array
    {
        $counts = $this->applyFilters($this->baseQuery(), withSop: false)
            ->toBase()
            ->selectRaw('sop_id, COUNT(*) AS c')
            ->groupBy('sop_id')
            ->pluck('c', 'sop_id');

        $sops = \Illuminate\Support\Facades\Cache::remember(
            'project_list_sop_options',
            now()->addSeconds(60),
            fn () => \App\Models\Sop::query()->orderBy('name')->get(['id', 'name']),
        );

        $items = $sops
            ->map(fn ($sop) => [
                'id'    => (string) $sop->id,
                'name'  => $sop->name,
                'count' => (int) ($counts[$sop->id] ?? 0),
            ])
            ->filter(fn ($row) => $row['count'] > 0 || in_array($row['id'], $this->sopSelection(), true))
            ->sortBy([['count', 'desc'], ['name', 'asc']])
            ->values()
            ->all();

        return [
            'total' => (int) $counts->sum(),
            'none'  => (int) ($counts[''] ?? $counts[null] ?? 0),
            'sops'  => $items,
        ];
    }

    protected function applySort(Builder $query): Builder
    {
        $dir = $this->sortDirection;

        return match ($this->sortField) {
            // Sort by client name via subquery (no join — keeps the model hydrated cleanly)
            'client' => $query->orderBy(
                \App\Models\Client::select('name')
                    ->whereColumn('clients.id', 'projects.client_id'),
                $dir,
            ),
            // Sort by department name via subquery
            'department' => $query->orderBy(
                \App\Models\Department::select('name')
                    ->whereColumn('departments.id', 'projects.department_id'),
                $dir,
            ),
            // Sort by number of team members
            'assignees' => $query
                ->withCount('teamMembers')
                ->orderBy('team_members_count', $dir),
            // Sort by computed progress ratio (completed / total steps)
            'progress' => $query->orderByRaw(
                "CASE WHEN (SELECT COUNT(*) FROM project_steps WHERE project_steps.project_id = projects.id) > 0
                      THEN (SELECT COUNT(*) FROM project_steps WHERE project_steps.project_id = projects.id AND status = 'completed')
                         * 1.0 /
                         (SELECT COUNT(*) FROM project_steps WHERE project_steps.project_id = projects.id)
                      ELSE 0 END $dir"
            ),
            // Priority — enum-ish order: urgent > normal > low (or reverse)
            'priority' => $query->orderByRaw(
                "FIELD(priority, 'urgent', 'normal', 'low') " . ($dir === 'asc' ? 'asc' : 'desc')
            ),
            // Direct columns: name, status, type, due_date
            default => $query->orderBy($this->sortField, $dir),
        };
    }

    protected function applyDueDateFilter(Builder $query): void
    {
        match ($this->dueDateFilter) {
            'overdue' => $query->whereDate('due_date', '<', now())
                ->whereHas('statusRecord', fn ($q) => $q->whereNotIn('category', ['done', 'closed'])),
            'today' => $query->whereDate('due_date', now()->toDateString()),
            'this_week' => $query->whereBetween('due_date', [
                now()->startOfWeek()->toDateString(),
                now()->endOfWeek()->toDateString(),
            ]),
            'this_month' => $query->whereBetween('due_date', [
                now()->startOfMonth()->toDateString(),
                now()->endOfMonth()->toDateString(),
            ]),
            'no_due_date' => $query->whereNull('due_date'),
            default => null,
        };
    }

    public function getPicOptionsProperty()
    {
        // B: skip entirely until the user opens the filter panel.
        if (! $this->filterOptionsLoaded) return collect();

        // A: cache across renders.
        return \Illuminate\Support\Facades\Cache::remember(
            'project_list_pic_options',
            now()->addSeconds(60),
            function () {
                $ids = $this->baseQuery()
                    ->whereNotNull('pic_id')
                    ->distinct()
                    ->pluck('pic_id');

                return User::whereIn('id', $ids)
                    ->orderBy('name')
                    ->get(['id', 'name', 'avatar_url', 'avatar_path']);
            },
        );
    }

    public function getClientOptionsProperty()
    {
        if (! $this->filterOptionsLoaded) return collect();

        return \Illuminate\Support\Facades\Cache::remember(
            'project_list_client_options',
            now()->addSeconds(60),
            function () {
                $ids = $this->baseQuery()
                    ->distinct()
                    ->pluck('client_id');

                return Client::whereIn('id', $ids)
                    ->orderBy('name')
                    ->get(['id', 'name']);
            },
        );
    }

    public function getAssigneeOptionsProperty()
    {
        if (! $this->filterOptionsLoaded) return collect();

        return \Illuminate\Support\Facades\Cache::remember(
            'project_list_assignee_options',
            now()->addSeconds(60),
            function () {
                $projectIds = $this->baseQuery()->pluck('id');

                $userIds = UserProject::whereIn('project_id', $projectIds)
                    ->distinct()
                    ->pluck('user_id');

                return User::whereIn('id', $userIds)
                    ->orderBy('name')
                    ->get(['id', 'name', 'avatar_url', 'avatar_path']);
            },
        );
    }

    public function getTotalCountProperty(): int
    {
        return $this->filteredQuery()->count();
    }

    public function getProjectsProperty()
    {
        return $this->filteredQuery()->limit(self::HARD_CAP)->get();
    }

    /**
     * Columns for the kanban view, driven by $groupBy.
     * Each column: ['key', 'label', 'color'?, 'shape'?, 'count', 'projects'].
     * For PIC/client the 'key' is the foreign-key id (or null for unassigned).
     */
    public function getKanbanColumnsProperty(): array
    {
        $projects = $this->projects;

        return match ($this->groupBy) {
            'priority'   => $this->kanbanByPriority($projects),
            'pic'        => $this->kanbanByPic($projects),
            'client'     => $this->kanbanByClient($projects),
            'department' => $this->kanbanByDepartment($projects),
            'sop'        => $this->kanbanBySop($projects),
            'none'       => $this->kanbanSingle($projects),
            default      => $this->kanbanByStatus($projects),  // 'status' is the default
        };
    }

    protected function kanbanByStatus($projects): array
    {
        $statusMap = $this->statusMap;
        $cols = [];

        foreach ($statusMap as $key => $meta) {
            $items = $projects->where('status', $key)->values();
            $cols[$key] = [
                'key'      => $key,
                'label'    => $meta['label'],
                'color'    => $meta['color'],
                'shape'    => $meta['shape'] ?? 'empty',
                'count'    => $items->count(),
                'projects' => $items,
            ];
        }

        $unknown = $projects->whereNotIn('status', array_keys($statusMap))->values();
        if ($unknown->isNotEmpty()) {
            $cols['__unknown'] = [
                'key'      => null,
                'label'    => 'Lainnya',
                'color'    => '#94a3b8',
                'shape'    => 'empty',
                'count'    => $unknown->count(),
                'projects' => $unknown,
            ];
        }

        return $cols;
    }

    protected function kanbanByPriority($projects): array
    {
        $cols = [];
        foreach (self::PRIORITIES as $key => $meta) {
            $items = $projects->where('priority', $key)->values();
            $cols[$key] = [
                'key'      => $key,
                'label'    => $meta['label'],
                'color'    => $meta['color'],
                'count'    => $items->count(),
                'projects' => $items,
            ];
        }
        return $cols;
    }

    protected function kanbanByPic($projects): array
    {
        $cols = [];

        // Unassigned column always shown first
        $unassigned = $projects->whereNull('pic_id')->values();
        $cols['_none'] = [
            'key'      => null,
            'label'    => 'Belum ada PIC',
            'color'    => '#94a3b8',
            'count'    => $unassigned->count(),
            'projects' => $unassigned,
        ];

        $picIds = $projects->whereNotNull('pic_id')->pluck('pic_id')->unique();
        if ($picIds->isNotEmpty()) {
            $picUsers = User::whereIn('id', $picIds)->orderBy('name')->get(['id', 'name', 'avatar_url', 'avatar_path']);
            foreach ($picUsers as $pic) {
                $items = $projects->where('pic_id', $pic->id)->values();
                $cols['p_' . $pic->id] = [
                    'key'      => $pic->id,
                    'label'    => $pic->name,
                    'color'    => '#6366f1',
                    'count'    => $items->count(),
                    'projects' => $items,
                ];
            }
        }

        return $cols;
    }

    protected function kanbanByClient($projects): array
    {
        $cols = [];

        $unassigned = $projects->whereNull('client_id')->values();
        if ($unassigned->isNotEmpty()) {
            $cols['_none'] = [
                'key'      => null,
                'label'    => 'Tanpa klien',
                'color'    => '#94a3b8',
                'count'    => $unassigned->count(),
                'projects' => $unassigned,
            ];
        }

        $clientIds = $projects->whereNotNull('client_id')->pluck('client_id')->unique();
        if ($clientIds->isNotEmpty()) {
            $clients = Client::whereIn('id', $clientIds)->orderBy('name')->get(['id', 'name']);
            foreach ($clients as $client) {
                $items = $projects->where('client_id', $client->id)->values();
                $cols['c_' . $client->id] = [
                    'key'      => $client->id,
                    'label'    => $client->name,
                    'color'    => '#0ea5e9',
                    'count'    => $items->count(),
                    'projects' => $items,
                ];
            }
        }

        return $cols;
    }

    protected function kanbanByDepartment($projects): array
    {
        $cols = [];

        // Kolom "Tanpa Departemen" selalu pertama
        $unassigned = $projects->whereNull('department_id')->values();
        $cols['_none'] = [
            'key'      => null,
            'label'    => 'Tanpa Departemen',
            'color'    => '#94a3b8',
            'count'    => $unassigned->count(),
            'projects' => $unassigned,
        ];

        $deptIds = $projects->whereNotNull('department_id')->pluck('department_id')->unique();
        if ($deptIds->isNotEmpty()) {
            $departments = \App\Models\Department::whereIn('id', $deptIds)->orderBy('name')->get(['id', 'name']);
            foreach ($departments as $dept) {
                $items = $projects->where('department_id', $dept->id)->values();
                $cols['d_' . $dept->id] = [
                    'key'      => $dept->id,
                    'label'    => $dept->name,
                    'color'    => '#6366f1',
                    'count'    => $items->count(),
                    'projects' => $items,
                ];
            }
        }

        return $cols;
    }

    protected function kanbanBySop($projects): array
    {
        $cols = [];

        // Kolom "Tanpa SOP" selalu pertama
        $unassigned = $projects->whereNull('sop_id')->values();
        $cols['_none'] = [
            'key'      => null,
            'label'    => 'Tanpa SOP',
            'color'    => '#94a3b8',
            'count'    => $unassigned->count(),
            'projects' => $unassigned,
        ];

        $sopIds = $projects->whereNotNull('sop_id')->pluck('sop_id')->unique();
        if ($sopIds->isNotEmpty()) {
            $sops = \App\Models\Sop::whereIn('id', $sopIds)->orderBy('name')->get(['id', 'name']);
            foreach ($sops as $sop) {
                $items = $projects->where('sop_id', $sop->id)->values();
                $cols['s_' . $sop->id] = [
                    'key'      => $sop->id,
                    'label'    => $sop->name,
                    'color'    => '#6366f1',
                    'count'    => $items->count(),
                    'projects' => $items,
                ];
            }
        }

        return $cols;
    }

    protected function kanbanSingle($projects): array
    {
        return [
            '_all' => [
                'key'      => null,
                'label'    => 'Semua proyek',
                'color'    => '#64748b',
                'count'    => $projects->count(),
                'projects' => $projects,
            ],
        ];
    }

    public function updateProjectPic(int $projectId, $picId): void
    {
        $picId = $picId === null || $picId === '' ? null : (int) $picId;

        $project = $this->baseQuery()->find($projectId);
        if (! $project) return;
        if ((int) $project->pic_id === (int) $picId) return;

        if ($picId !== null && ! User::whereKey($picId)->exists()) {
            Notification::make()->title('PIC tidak ditemukan')->danger()->send();
            return;
        }

        $project->pic_id = $picId;
        $project->save();

        Notification::make()->title('PIC proyek diperbarui')->success()->send();
    }

    /**
     * Returns groups as: [groupKey => ['all' => Collection, 'visible' => Collection, 'hasMore' => bool, 'hidden' => int]]
     */
    public function getGroupedProjectsProperty(): array
    {
        $projects = $this->projects;

        if ($this->groupBy === 'none') {
            return ['' => $this->wrapGroup($projects)];
        }

        $grouped = [];
        foreach ($projects as $project) {
            $key = match ($this->groupBy) {
                'status'     => $project->status ?? 'unknown',
                'priority'   => $project->priority ?? 'unknown',
                'pic'        => $project->pic?->name ?? 'Unassigned',
                'client'     => $project->client?->name ?? 'No Client',
                'department' => $project->department?->name ?? 'Tanpa Departemen',
                'sop'        => $project->sop?->name ?? 'Tanpa SOP',
                default      => '',
            };
            $grouped[$key] ??= collect();
            $grouped[$key]->push($project);
        }

        if ($this->groupBy === 'status') {
            // Use the DB-driven status order (category then sort_order)
            $grouped = $this->reorder($grouped, array_keys($this->statusMap));
        } elseif ($this->groupBy === 'priority') {
            $grouped = $this->reorder($grouped, array_keys(self::PRIORITIES));
        } else {
            ksort($grouped);
        }

        $result = [];
        foreach ($grouped as $key => $items) {
            $result[$key] = $this->wrapGroup($items, (string) $key);
        }

        return $result;
    }

    protected function reorder(array $grouped, array $order): array
    {
        $sorted = [];
        foreach ($order as $key) {
            if (isset($grouped[$key])) {
                $sorted[$key] = $grouped[$key];
            }
        }
        foreach ($grouped as $k => $v) {
            if (!isset($sorted[$k])) {
                $sorted[$k] = $v;
            }
        }
        return $sorted;
    }

    protected function wrapGroup($items, string $groupKey = ''): array
    {
        $count = $items->count();
        $isExpanded = \in_array($groupKey, $this->expandedGroups, true);
        $limit = self::DEFAULT_GROUP_LIMIT;

        $visible = ($isExpanded || $count <= $limit) ? $items : $items->take($limit);

        return [
            'all' => $items,
            'visible' => $visible,
            'total' => $count,
            'shown' => $visible->count(),
            'hasMore' => $count > $limit,
            'hidden' => max(0, $count - $visible->count()),
            'expanded' => $isExpanded,
        ];
    }

    public function getGroupLabel(string $key): string
    {
        return match ($this->groupBy) {
            'status' => $this->statusMap[$key]['label'] ?? ucfirst($key),
            'priority' => self::PRIORITIES[$key]['label'] ?? ucfirst($key),
            default => $key ?: 'Other',
        };
    }

    public function getGroupColor(string $key): array
    {
        // Default badge color = primary accent (matches --cu-accent in blade).
        // Status/priority pakai warna spesifik mereka; sisanya (client, pic, dept, dst.)
        // di-fallback ke primary supaya konsisten dengan brand.
        $primary = ['color' => '#6366f1', 'bg' => '#eef2ff'];

        return match ($this->groupBy) {
            'status'   => $this->statusMap[$key] ?? $primary,
            'priority' => self::PRIORITIES[$key] ?? $primary,
            default    => $primary,
        };
    }

    public function viewUrl(Project $project): string
    {
        return ProjectResource::getUrl('view', ['record' => $project]);
    }

    public function editUrl(Project $project): string
    {
        return ProjectResource::getUrl('edit', ['record' => $project]);
    }

    public function deleteProjectAction(): Action
    {
        return Action::make('deleteProject')
            ->requiresConfirmation()
            ->modalHeading('Hapus Proyek')
            ->modalDescription(function (array $arguments): string {
                $project = Project::find($arguments['project'] ?? null);
                $name = $project?->name ?? 'proyek ini';
                return "Apakah Anda yakin ingin menghapus '{$name}'? Semua langkah, tugas, dan dokumen terkait akan ikut terhapus. Tindakan ini tidak dapat dibatalkan.";
            })
            ->modalSubmitActionLabel('Ya, Hapus')
            ->modalCancelActionLabel('Batal')
            ->modalIcon('heroicon-o-trash')
            ->color('danger')
            ->action(function (array $arguments): void {
                $project = Project::find($arguments['project'] ?? null);
                if (!$project) return;

                $name = $project->name;
                $project->delete();

                Notification::make()
                    ->title("Proyek '{$name}' dihapus")
                    ->success()
                    ->send();
            });
    }

    public function openStatusManager(): void
    {
        $this->showStatusManager = true;
    }

    public function closeStatusManager(): void
    {
        $this->showStatusManager = false;
        $this->newStatusLabel = '';
        $this->newStatusColor = '#2563eb';
        $this->newStatusShape = 'empty';
        $this->newStatusCategory = 'active';
        $this->editingStatusId = null;
        $this->editLabel = '';
        $this->editColor = '#2563eb';
        $this->editShape = 'empty';
        $this->editCategory = 'active';
        $this->resetErrorBag();
    }

    public function startEditStatus(int $id): void
    {
        if (!auth()->user()->hasRole('super-admin')) {
            return;
        }

        $status = ProjectStatus::find($id);
        if (!$status || $status->is_system) {
            return;
        }

        $this->editingStatusId = $id;
        $this->editLabel      = $status->label;
        $this->editColor      = $status->color;
        $this->editShape      = $status->shape;
        $this->editCategory   = $status->category;
        $this->resetErrorBag();
    }

    public function cancelEdit(): void
    {
        $this->editingStatusId = null;
        $this->editLabel = '';
        $this->editColor = '#2563eb';
        $this->editShape = 'empty';
        $this->editCategory = 'active';
        $this->resetErrorBag();
    }

    public function saveStatusEdit(): void
    {
        if (!auth()->user()->hasRole('super-admin') || !$this->editingStatusId) {
            return;
        }

        $status = ProjectStatus::find($this->editingStatusId);
        if (!$status || $status->is_system) {
            return;
        }

        $this->validate([
            'editLabel'    => 'required|string|min:2|max:60',
            'editColor'    => ['required', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'editShape'    => ['required', Rule::in(ProjectStatus::SHAPES)],
            'editCategory' => ['required', Rule::in(array_keys(ProjectStatus::CATEGORIES))],
        ]);

        $status->update([
            'label'    => $this->editLabel,
            'color'    => $this->editColor,
            'shape'    => $this->editShape,
            'category' => $this->editCategory,
        ]);

        $this->editingStatusId = null;
        $this->editLabel = '';

        Notification::make()
            ->title('Status berhasil diperbarui')
            ->success()
            ->send();
    }

    public function createStatus(): void
    {
        if (!auth()->user()->hasRole('super-admin')) {
            return;
        }

        $this->validate([
            'newStatusLabel'    => 'required|string|min:2|max:60',
            'newStatusColor'    => ['required', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'newStatusShape'    => ['required', Rule::in(ProjectStatus::SHAPES)],
            'newStatusCategory' => ['required', Rule::in(array_keys(ProjectStatus::CATEGORIES))],
        ]);

        $key = Str::slug($this->newStatusLabel, '_');

        if (ProjectStatus::where('key', $key)->exists()) {
            $this->addError('newStatusLabel', 'A status with a similar name already exists.');
            return;
        }

        $maxOrder = ProjectStatus::where('category', $this->newStatusCategory)->max('sort_order') ?? 0;

        ProjectStatus::create([
            'key'        => $key,
            'label'      => $this->newStatusLabel,
            'color'      => $this->newStatusColor,
            'shape'      => $this->newStatusShape,
            'category'   => $this->newStatusCategory,
            'sort_order' => $maxOrder + 1,
            'is_system'  => false,
        ]);

        $this->newStatusLabel = '';
        $this->newStatusColor = '#2563eb';
        $this->newStatusShape = 'empty';
        $this->newStatusCategory = 'active';
        $this->resetErrorBag();

        Notification::make()
            ->title('Status berhasil dibuat')
            ->success()
            ->send();
    }

    public function deleteStatus(int $id): void
    {
        if (!auth()->user()->hasRole('super-admin')) {
            return;
        }

        $status = ProjectStatus::find($id);
        if (!$status) {
            return;
        }

        if (!$status->canBeDeleted()) {
            $reason = $status->is_system
                ? 'Status sistem tidak dapat dihapus.'
                : 'Status ini masih digunakan oleh ' . $status->projects()->count() . ' proyek.';

            Notification::make()
                ->title('Tidak dapat menghapus status')
                ->body($reason)
                ->danger()
                ->send();
            return;
        }

        $label = $status->label;
        $status->delete();

        Notification::make()
            ->title("Status '{$label}' berhasil dihapus")
            ->success()
            ->send();
    }

    public function getStatusesForManagerProperty()
    {
        return ProjectStatus::ordered()->withCount('projects')->get();
    }

    public function render()
    {
        return view('livewire.projects.project-list-clickup', [
            'grouped' => $this->groupedProjects,
            'totalCount' => $this->totalCount,
            'isCapped' => $this->totalCount > self::HARD_CAP,
            'picOptions' => $this->picOptions,
            'clientOptions' => $this->clientOptions,
            'assigneeOptions' => $this->assigneeOptions,
            'teamPool' => $this->teamPool,
            'statuses' => $this->statusMap,
        ]);
    }
}
