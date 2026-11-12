<?php

namespace App\Services\Dashboard;

use App\Models\Project;
use App\Models\ProjectStatus;
use App\Models\User;
use App\Services\TaxDeadlineService;
use App\Support\Ui\Tone;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Numbers behind the main dashboard (docs/mockups/dashboard-redesign.html).
 *
 * Two scopes: "kantor" (every active client and project) and "saya" (the
 * user's clients from user_clients, and projects where they are PIC or team
 * member). Results are cached per scope (and per user for "saya") for a few
 * minutes; the dashboard polls nothing, so a short TTL is enough.
 *
 * Which sections a user may see is decided by App\Filament\Pages\Dashboard.
 */
class HomeDashboard
{
    public const KANTOR = 'kantor';

    public const SAYA = 'saya';

    protected const TTL = 300;

    protected ?array $clientIds = null;

    public function __construct(
        protected User $user,
        protected string $scope,
        protected TaxDeadlineService $tax,
    ) {
        $this->scope = in_array($scope, [self::KANTOR, self::SAYA], true) ? $scope : self::KANTOR;
    }

    public static function for(User $user, string $scope): self
    {
        return new self($user, $scope, app(TaxDeadlineService::class));
    }

    public function isMine(): bool
    {
        return $this->scope === self::SAYA;
    }

    protected function remember(string $key, Closure $callback): mixed
    {
        $key = 'home:' . $key . ':' . $this->scope . ($this->isMine() ? ':' . $this->user->id : '') . ':' . Carbon::today()->toDateString();

        return Cache::remember($key, self::TTL, $callback);
    }

    // ─── Scope ───────────────────────────────────────────────────────

    /** Clients in scope: null means every active client. */
    public function clientIds(): ?array
    {
        if (! $this->isMine()) {
            return null;
        }

        return $this->clientIds ??= DB::table('user_clients')
            ->where('user_id', $this->user->id)
            ->pluck('client_id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    /** Projects of active clients in scope. */
    public function projects(): Builder
    {
        return Project::query()
            ->whereHas('client', fn ($q) => $q->where('status', 'Active'))
            ->when($this->isMine(), fn ($q) => $q->where(fn ($w) => $w
                ->where('pic_id', $this->user->id)
                ->orWhereHas('userProject', fn ($u) => $u->where('user_id', $this->user->id))));
    }

    /** Status keys of projects not done or closed. */
    protected function openStatuses(): array
    {
        return ProjectStatus::query()
            ->whereIn('category', [ProjectStatus::CATEGORY_NOT_STARTED, ProjectStatus::CATEGORY_ACTIVE])
            ->pluck('key')
            ->all();
    }

    protected function openProjects(): Builder
    {
        return $this->projects()->whereIn('projects.status', $this->openStatuses());
    }

    // ─── Tax ─────────────────────────────────────────────────────────

    /** This month's three deadlines (PPh 10, PPN 20, payment end of month) with outstanding clients. */
    public function deadlines(): array
    {
        return $this->remember('deadlines', fn () => $this->tax->deadlinesFor(Carbon::today()->startOfMonth(), Carbon::today(), $this->clientIds()));
    }

    /**
     * Filing state of the period billed this month, per obligation, plus
     * payment of underpaid returns. One row per client per tax type.
     */
    public function taxCompliance(): array
    {
        return $this->remember('compliance', function () {
            $today = Carbon::today();
            $period = $this->tax->periodFor($today);
            $anchor = $this->tax->anchorFor($period);
            $rows = $this->filingCounts($period);

            $types = [];
            foreach (['pph' => ['PPh 21', 10], 'bupot' => ['PPh Unifikasi', 10], 'ppn' => ['PPN', 20]] as $type => [$label, $day]) {
                $deadline = $this->tax->deadlineDate($anchor, $day);
                $total = (int) ($rows[$type]->total ?? 0);
                $done = (int) ($rows[$type]->done ?? 0);
                $open = $total - $done;
                $passed = $deadline->lt($today);
                $types[] = [
                    'key' => $type,
                    'label' => $label,
                    'sub' => 'lapor · tenggat ' . $deadline->day . ' ' . $this->tax->monthShort($deadline),
                    'total' => $total,
                    'done' => $done,
                    'pending' => $passed ? 0 : $open,
                    'late' => $passed ? $open : 0,
                ];
            }

            $payDeadline = $this->tax->deadlineDate($anchor, 30);
            $pay = $this->tax->periodQuery($period, $this->clientIds())
                ->where('s.status_final', 'Kurang Bayar')
                ->selectRaw("COUNT(*) AS total, SUM(s.bayar_status = 'Sudah Bayar') AS done")
                ->first();
            $payOpen = (int) $pay->total - (int) $pay->done;
            $payPassed = $payDeadline->lt($today);
            $types[] = [
                'key' => 'payment',
                'label' => 'Pembayaran',
                'sub' => 'kurang bayar · ' . $payDeadline->day . ' ' . $this->tax->monthShort($payDeadline),
                'total' => (int) $pay->total,
                'done' => (int) $pay->done,
                'pending' => $payPassed ? 0 : $payOpen,
                'late' => $payPassed ? $payOpen : 0,
            ];

            $filing = array_slice($types, 0, 3);
            $total = array_sum(array_column($filing, 'total'));
            $done = array_sum(array_column($filing, 'done'));

            $previous = $this->filingCounts($period->copy()->subMonth());
            $prevTotal = collect($previous)->sum(fn ($r) => (int) $r->total);
            $prevDone = collect($previous)->sum(fn ($r) => (int) $r->done);

            return [
                'period' => $this->tax->periodLabel($period),
                'anchor' => $this->tax->monthName($anchor) . ' ' . $anchor->year,
                'total' => $total,
                'done' => $done,
                'pct' => $total > 0 ? (int) round($done / $total * 100) : null,
                'previous' => [
                    'label' => $this->tax->periodLabel($period->copy()->subMonth()),
                    'pct' => $prevTotal > 0 ? (int) round($prevDone / $prevTotal * 100) : null,
                ],
                'types' => $types,
                'spine' => [
                    'month' => $this->tax->monthName($today) . ' ' . $today->year,
                    'days' => $today->daysInMonth,
                    'today' => $today->day,
                    'marks' => array_map(function (array $d) {
                        $verb = $d['key'] === TaxDeadlineService::PAYMENT ? 'belum bayar' : 'belum lapor';

                        return [
                            'day' => $d['date']->day,
                            'label' => $d['short'],
                            'late' => $d['is_passed'] && $d['outstanding'] > 0,
                            'sub' => $d['outstanding'] === 0 ? 'semua beres' : ($d['is_passed'] ? 'lewat · ' . $d['outstanding'] . ' klien' : $d['outstanding'] . ' klien ' . $verb),
                        ];
                    }, $this->deadlines()),
                ],
                'listUrl' => self::url('tax-reports'),
            ];
        });
    }

    /** Filing obligations of one period by tax type: [type => {total, done}]. */
    protected function filingCounts(Carbon $period): array
    {
        return $this->tax->periodQuery($period, $this->clientIds())
            ->whereIn('s.tax_type', ['pph', 'bupot', 'ppn'])
            // PPh Unifikasi periods flagged "no activity" carry no filing duty.
            ->where(fn ($q) => $q->where('s.tax_type', '<>', 'bupot')->orWhere('s.no_activity', 0))
            ->groupBy('s.tax_type')
            ->selectRaw("s.tax_type, COUNT(*) AS total, SUM(s.report_status = 'Sudah Lapor') AS done")
            ->get()
            ->keyBy('tax_type')
            ->all();
    }

    /**
     * Share of obligations reported on or before their deadline, for the last
     * twelve periods whose deadlines have all passed. Reports without a
     * reported_at date are left out (their timing is unknown).
     */
    public function onTimeTrend(): array
    {
        return $this->remember('ontime', function () {
            $latest = $this->tax->periodFor(Carbon::today())->subMonth();
            $points = [];

            for ($i = 11; $i >= 0; $i--) {
                $period = $latest->copy()->subMonths($i);
                $anchor = $this->tax->anchorFor($period);
                $due = [
                    'pph' => $this->tax->deadlineDate($anchor, 10)->endOfDay(),
                    'bupot' => $this->tax->deadlineDate($anchor, 10)->endOfDay(),
                    'ppn' => $this->tax->deadlineDate($anchor, 20)->endOfDay(),
                ];

                $rows = $this->tax->periodQuery($period, $this->clientIds())
                    ->whereIn('s.tax_type', ['pph', 'bupot', 'ppn'])
                    ->where(fn ($q) => $q->where('s.tax_type', '<>', 'bupot')->orWhere('s.no_activity', 0))
                    ->get(['s.tax_type', 's.report_status', 's.reported_at']);

                $counted = 0;
                $onTime = 0;
                foreach ($rows as $row) {
                    if ($row->report_status === 'Sudah Lapor' && $row->reported_at === null) {
                        continue;
                    }
                    $counted++;
                    if ($row->report_status === 'Sudah Lapor' && Carbon::parse($row->reported_at)->lte($due[$row->tax_type])) {
                        $onTime++;
                    }
                }

                $points[] = [
                    'label' => $this->tax->monthShort($period),
                    'title' => 'Masa ' . $this->tax->periodLabel($period),
                    'value' => $counted > 0 ? (int) round($onTime / $counted * 100) : null,
                ];
            }

            $known = array_filter($points, fn ($p) => $p['value'] !== null);
            $lowest = $known ? array_reduce($known, fn ($carry, $p) => $carry === null || $p['value'] < $carry['value'] ? $p : $carry) : null;

            return ['points' => $points, 'target' => 90, 'lowest' => $lowest];
        });
    }

    /** Total PPN underpaid (to pay) and overpaid per period, last twelve periods. */
    public function ppnPosition(): array
    {
        return $this->remember('ppn', function () {
            $latest = $this->tax->periodFor(Carbon::today());
            $points = [];

            for ($i = 11; $i >= 0; $i--) {
                $period = $latest->copy()->subMonths($i);
                $row = $this->tax->periodQuery($period, $this->clientIds())
                    ->where('s.tax_type', 'ppn')
                    ->selectRaw("
                        SUM(CASE WHEN s.status_final = 'Kurang Bayar' THEN s.saldo_final ELSE 0 END) AS underpaid,
                        SUM(CASE WHEN s.status_final = 'Lebih Bayar' THEN -s.saldo_final ELSE 0 END) AS overpaid,
                        SUM(s.status_final = 'Kurang Bayar') AS under_clients
                    ")
                    ->first();

                $points[] = [
                    'label' => $this->tax->monthShort($period),
                    'title' => 'Masa ' . $this->tax->periodLabel($period),
                    'up' => max(0.0, (float) $row->underpaid),
                    'down' => max(0.0, (float) $row->overpaid),
                    'clients' => (int) $row->under_clients,
                ];
            }

            return ['points' => $points, 'latest' => end($points)];
        });
    }

    /** Clients with the highest gross sales this year (faktur keluaran DPP), and who handles them. */
    public function topClients(int $limit = 6): array
    {
        return $this->remember('topclients', function () use ($limit) {
            $year = Carbon::today()->year;

            $ranked = DB::table('invoices as i')
                ->join('tax_reports as tr', 'i.tax_report_id', '=', 'tr.id')
                ->join('clients as c', 'tr.client_id', '=', 'c.id')
                ->where('tr.year', $year)
                ->where('c.status', 'Active')
                ->where('i.type', 'Faktur Keluaran')
                ->where('i.is_revision', false)
                ->when($this->clientIds() !== null, fn ($q) => $q->whereIn('c.id', $this->clientIds()))
                ->groupBy('c.id', 'c.name')
                ->selectRaw('c.id, c.name, SUM(i.dpp) AS bruto')
                ->orderByDesc('bruto')
                ->limit($limit)
                ->get();

            // Handler: whoever entered most of the client's invoices this year.
            $handlers = DB::table('invoices as i')
                ->join('tax_reports as tr', 'i.tax_report_id', '=', 'tr.id')
                ->join('users as u', 'i.created_by', '=', 'u.id')
                ->where('tr.year', $year)
                ->whereIn('tr.client_id', $ranked->pluck('id'))
                ->groupBy('tr.client_id', 'u.name')
                ->selectRaw('tr.client_id, u.name, COUNT(*) AS n')
                ->get()
                ->groupBy('client_id')
                ->map(fn ($rows) => $rows->sortByDesc('n')->first()->name);

            return [
                'year' => $year,
                'rows' => $ranked->map(fn ($r) => [
                    'name' => $r->name,
                    'bruto' => (float) $r->bruto,
                    'handler' => $handlers[$r->id] ?? null,
                    'url' => route('filament.admin.resources.clients.view', ['record' => $r->id]),
                ])->all(),
            ];
        });
    }

    // ─── Projects ────────────────────────────────────────────────────

    /** Projects started and completed per month, last twelve months (completion from the activity log). */
    public function projectFlow(): array
    {
        return $this->remember('flow', function () {
            $start = Carbon::today()->startOfMonth()->subMonths(11);

            $created = (clone $this->projects())
                ->where('created_at', '>=', $start)
                ->selectRaw("DATE_FORMAT(created_at, '%Y-%m') AS ym, COUNT(*) AS n")
                ->groupBy('ym')
                ->pluck('n', 'ym');

            $completed = DB::table('activity_log')
                ->where('subject_type', Project::class)
                ->where('properties->attributes->status', 'completed')
                ->where('created_at', '>=', $start)
                ->whereIn('subject_id', (clone $this->projects())->select('id'))
                ->selectRaw("DATE_FORMAT(created_at, '%Y-%m') AS ym, COUNT(DISTINCT subject_id) AS n")
                ->groupBy('ym')
                ->pluck('n', 'ym');

            $months = [];
            for ($i = 0; $i < 12; $i++) {
                $month = $start->copy()->addMonths($i);
                $key = $month->format('Y-m');
                $months[] = [
                    'label' => $this->tax->monthShort($month),
                    'title' => $this->tax->monthName($month) . ' ' . $month->year,
                    'in' => (int) ($created[$key] ?? 0),
                    'out' => (int) ($completed[$key] ?? 0),
                ];
            }

            $in = array_sum(array_column($months, 'in'));
            $out = array_sum(array_column($months, 'out'));

            return ['months' => $months, 'in' => $in, 'out' => $out, 'net' => $in - $out, 'url' => self::url('projects')];
        });
    }

    /** Projects per status in workflow order (ordinal ramp), with two quick facts. */
    public function projectStatus(): array
    {
        return $this->remember('status', function () {
            $statuses = ProjectStatus::ordered()->get();
            $colors = Tone::projectStatusChart($statuses);
            $counts = (clone $this->projects())->selectRaw('status, COUNT(*) AS n')->groupBy('status')->pluck('n', 'status');
            $total = (int) $counts->sum();

            $rows = $statuses->map(fn (ProjectStatus $status) => [
                'key' => $status->key,
                'label' => $status->label,
                'color' => $colors[$status->key],
                'count' => (int) ($counts[$status->key] ?? 0),
                'percent' => $total > 0 ? round(($counts[$status->key] ?? 0) / $total * 100, 1) : 0,
                'url' => self::url('projects') . '?' . http_build_query(['status' => [$status->key]]),
            ])->all();

            $open = (clone $this->openProjects());
            $avgAge = (int) round((float) (clone $open)->selectRaw('AVG(DATEDIFF(CURDATE(), created_at)) AS d')->value('d'));

            $facts = [['value' => $avgAge . ' hr', 'label' => 'umur rata-rata proyek berjalan']];
            $facts[] = $this->isMine()
                ? ['value' => (string) (clone $open)->whereBetween('due_date', [Carbon::today(), Carbon::today()->addDays(14)])->count(), 'label' => 'jatuh tempo 14 hari ke depan']
                : ['value' => (string) (clone $open)->whereNull('pic_id')->count(), 'label' => 'proyek berjalan tanpa PIC'];

            return ['rows' => $rows, 'total' => $total, 'facts' => $facts];
        });
    }

    /** Open projects per PIC split into on time and overdue, plus open daily tasks. */
    public function teamWorkload(int $limit = 8): array
    {
        return $this->remember('workload', function () use ($limit) {
            $today = Carbon::today();
            $rows = (clone $this->openProjects())
                ->leftJoin('users', 'projects.pic_id', '=', 'users.id')
                ->where(fn ($q) => $q->whereNull('projects.pic_id')->orWhere('users.status', 'active'))
                ->when($this->isMine(), fn ($q) => $q->where('projects.pic_id', $this->user->id))
                ->groupBy('projects.pic_id', 'users.name')
                ->selectRaw('projects.pic_id, users.name, COUNT(*) AS open, SUM(projects.due_date < ?) AS late', [$today->toDateString()])
                ->get();

            $tasks = DB::table('daily_task_assignments as a')
                ->join('daily_tasks as t', 'a.daily_task_id', '=', 't.id')
                ->whereIn('t.status', ['pending', 'in_progress'])
                ->groupBy('a.user_id')
                ->selectRaw('a.user_id, COUNT(DISTINCT t.id) AS n')
                ->pluck('n', 'user_id');

            $people = $rows->whereNotNull('pic_id')->sortByDesc('open')->take($limit)->values();
            $unassigned = $rows->firstWhere('pic_id', null);

            $list = $people->map(fn ($r) => [
                'name' => $r->name,
                'initials' => self::initials($r->name),
                'open' => (int) $r->open,
                'late' => (int) $r->late,
                'tasks' => (int) ($tasks[$r->pic_id] ?? 0),
                'unassigned' => false,
            ])->all();

            if ($unassigned && ! $this->isMine()) {
                $list[] = ['name' => 'Tanpa PIC', 'initials' => '–', 'open' => (int) $unassigned->open, 'late' => (int) $unassigned->late, 'tasks' => null, 'unassigned' => true];
            }

            return ['rows' => $list, 'max' => max(1, (int) collect($list)->max('open'))];
        });
    }

    // ─── Today ───────────────────────────────────────────────────────

    /**
     * Four action cards and one sentence that sums them up. One card is about
     * tax (the deadline that needs attention most); the others are about
     * projects: overdue, due within seven days, and documents or tasks.
     */
    public function actions(bool $canSeeTax): array
    {
        return $this->remember('actions:' . (int) $canSeeTax, function () use ($canSeeTax) {
            $today = Carbon::today();
            $projectsUrl = self::url('projects');

            $openCount = (clone $this->openProjects())->count();
            $late = (clone $this->openProjects())->whereDate('due_date', '<', $today);
            $lateCount = (clone $late)->count();
            $oldestLate = (clone $late)->min('due_date');
            $soon = (clone $this->openProjects())->whereBetween('due_date', [$today->toDateString(), $today->copy()->addDays(7)->toDateString()]);
            $soonCount = (clone $soon)->count();
            $nearest = (clone $soon)->orderBy('due_date')->first(['name', 'due_date']);

            $docs = DB::table('submitted_documents as sd')
                ->join('required_documents as rd', 'sd.required_document_id', '=', 'rd.id')
                ->join('project_steps as ps', 'rd.project_step_id', '=', 'ps.id')
                ->whereIn('ps.project_id', (clone $this->projects())->select('id'))
                ->whereIn('sd.status', ['uploaded', 'pending_review'])
                ->selectRaw("COUNT(*) AS total, SUM(sd.status = 'uploaded') AS uploaded, MIN(sd.created_at) AS oldest")
                ->first();
            $docTotal = (int) $docs->total;

            $tax = $canSeeTax ? $this->taxCard($this->isMine() ? 'Tenggat pajak klien saya' : 'Tenggat pajak') : null;

            $lateCard = [
                'key' => 'projects', 'alert' => $lateCount > 0, 'icon' => 'heroicon-o-flag',
                'label' => $this->isMine() ? 'Proyek saya lewat tenggat' : 'Proyek lewat tenggat',
                'value' => $lateCount, 'unit' => 'proyek',
                'sub' => $lateCount > 0 ? 'paling lama ' . (int) Carbon::parse($oldestLate)->diffInDays($today) . ' hari' : 'semua sesuai jadwal',
                'bar' => [[$lateCount, 'var(--kis-bar-red)'], [max($openCount - $lateCount, 0), 'var(--kis-ramp-b3)']],
                'url' => $projectsUrl . '?due=overdue',
            ];
            $soonCard = [
                'key' => 'soon', 'alert' => false, 'icon' => 'heroicon-o-calendar-days',
                'label' => $this->isMine() ? 'Proyek saya jatuh tempo' : 'Jatuh tempo 7 hari',
                'value' => $soonCount, 'unit' => 'proyek',
                'sub' => $nearest ? 'terdekat ' . $nearest->due_date->day . ' ' . $this->tax->monthShort($nearest->due_date) . ' · ' . $nearest->name : 'tidak ada dalam 7 hari ke depan',
                'bar' => [[$soonCount, 'var(--kis-ramp-b3)'], [max($openCount - $lateCount - $soonCount, 0), 'var(--kis-bar-track)']],
                'url' => $projectsUrl . '?due=next_7_days',
            ];
            $docCard = [
                'key' => 'documents', 'alert' => false, 'icon' => 'heroicon-o-document-arrow-up',
                'label' => $this->isMine() ? 'Dokumen klien saya' : 'Dokumen menunggu review',
                'value' => $docTotal, 'unit' => 'dokumen',
                'sub' => $docTotal > 0 ? 'tertua menunggu ' . (int) Carbon::parse($docs->oldest)->diffInDays($today) . ' hari' : 'tidak ada yang menunggu',
                'bar' => [[(int) $docs->uploaded, 'var(--kis-ramp-b1)'], [$docTotal - (int) $docs->uploaded, 'var(--kis-ramp-b4)']],
                'url' => $projectsUrl,
            ];

            if ($this->isMine()) {
                $taskRow = DB::table('daily_task_assignments as a')
                    ->join('daily_tasks as t', 'a.daily_task_id', '=', 't.id')
                    ->where('a.user_id', $this->user->id)
                    ->whereDate('t.task_date', $today)
                    ->where('t.status', '<>', 'cancelled')
                    ->selectRaw("COUNT(DISTINCT t.id) AS total, COUNT(DISTINCT CASE WHEN t.status = 'completed' THEN t.id END) AS done")
                    ->first();
                $taskCard = [
                    'key' => 'tasks', 'alert' => false, 'icon' => 'heroicon-o-check-circle',
                    'label' => 'Tugas saya hari ini', 'value' => (int) $taskRow->total, 'unit' => 'tugas',
                    'sub' => (int) $taskRow->done . ' selesai · ' . ((int) $taskRow->total - (int) $taskRow->done) . ' tersisa',
                    'bar' => [[(int) $taskRow->done, 'var(--kis-ramp-b3)'], [(int) $taskRow->total - (int) $taskRow->done, 'var(--kis-bar-track)']],
                    'url' => route('filament.admin.pages.daily-task-dashboard'),
                ];
                $cards = [$lateCard, $soonCard, $taskCard, $tax ?? $docCard];
            } else {
                $cards = array_values(array_filter([$tax, $lateCard, $soonCard, $docCard]));
            }

            return [
                'cards' => array_map(fn ($card) => array_diff_key($card, ['deadline' => true]), array_slice($cards, 0, 4)),
                'brief' => $this->brief($tax['deadline'] ?? null, $lateCount, $soonCount, $docTotal),
            ];
        });
    }

    /**
     * The one tax card: a missed deadline that still has outstanding clients,
     * otherwise the next deadline. Its bar uses the compliance card's counts.
     */
    protected function taxCard(string $label): ?array
    {
        $deadlines = collect($this->deadlines());
        $missed = $deadlines->first(fn ($d) => $d['is_passed'] && $d['outstanding'] > 0);
        $focus = $missed ?? $deadlines->first(fn ($d) => ! $d['is_passed']);

        if (! $focus) {
            return null;
        }

        $types = [TaxDeadlineService::PPH => ['pph', 'bupot'], TaxDeadlineService::PPN => ['ppn'], TaxDeadlineService::PAYMENT => ['payment']][$focus['key']];
        $done = (int) collect($this->taxCompliance()['types'])->whereIn('key', $types)->sum('done');
        $verb = $focus['key'] === TaxDeadlineService::PAYMENT ? 'bayar' : 'lapor';

        return [
            'key' => 'tax', 'alert' => (bool) $missed,
            'icon' => $missed ? 'heroicon-o-exclamation-triangle' : 'heroicon-o-clock',
            'label' => $label,
            'value' => $focus['outstanding'], 'unit' => 'klien belum ' . $verb,
            'sub' => $focus['short'] . ' · ' . $focus['date']->day . ' ' . $this->tax->monthShort($focus['date']) . ' · ' . mb_strtolower($this->tax->humanDistance($focus['days_remaining'])),
            'bar' => [[$done, 'var(--kis-ramp-b3)'], [$focus['outstanding'], $missed ? 'var(--kis-bar-red)' : 'var(--kis-bar-track)']],
            'url' => self::url('tax-reports'),
            'deadline' => $focus + ['missed' => (bool) $missed],
        ];
    }

    /** One plain sentence with at most three things that need attention: tax first, then projects. */
    protected function brief(?array $deadline, int $late, int $soon, int $docs): array
    {
        $items = [];
        if ($deadline && $deadline['missed']) {
            $items[] = ['bad' => true, 'text' => $deadline['short'] . ' lewat tenggat', 'tail' => ' untuk ' . $deadline['outstanding'] . ' klien'];
        } elseif ($deadline && $deadline['outstanding'] > 0) {
            $items[] = ['bad' => false, 'text' => $deadline['outstanding'] . ' klien', 'tail' => ' belum ' . ($deadline['key'] === TaxDeadlineService::PAYMENT ? 'bayar' : 'lapor ' . $deadline['short']) . ' sebelum tanggal ' . $deadline['date']->day];
        }
        if ($late > 0) {
            $items[] = ['bad' => ! ($deadline['missed'] ?? false), 'text' => $late . ' proyek', 'tail' => ' lewat tenggat'];
        }
        if ($soon > 0) {
            $items[] = ['bad' => false, 'text' => $soon . ' proyek', 'tail' => ' jatuh tempo 7 hari ke depan'];
        }
        if ($docs > 0) {
            $items[] = ['bad' => false, 'text' => $docs . ' dokumen', 'tail' => ' menunggu review'];
        }

        $items = array_slice($items, 0, 3);
        $lead = match (count($items)) {
            0 => null,
            1 => 'Satu hal perlu perhatian: ',
            2 => 'Dua hal perlu perhatian: ',
            default => 'Tiga hal perlu perhatian: ',
        };

        return ['lead' => $lead, 'items' => $items];
    }

    /**
     * Next 14 days (today included): tax deadlines, project due dates and
     * calendar events. Returns a day grid for the calendar strip (every item,
     * with a tooltip per day) and the nearest few items as a list. A missed tax
     * deadline that still has outstanding clients shows on today.
     */
    public function agenda(bool $canSeeTax, int $days = 14, int $limit = 4): array
    {
        return $this->remember('agenda:' . (int) $canSeeTax, function () use ($canSeeTax, $days, $limit) {
            $today = Carbon::today();
            $end = $today->copy()->addDays($days - 1);
            $items = [];

            if ($canSeeTax) {
                $all = array_merge(
                    $this->deadlines(),
                    $this->tax->deadlinesFor($today->copy()->addMonthNoOverflow()->startOfMonth(), $today, $this->clientIds()),
                );
                foreach ($all as $d) {
                    $verb = $d['key'] === TaxDeadlineService::PAYMENT ? 'belum bayar' : 'belum lapor';
                    if ($d['is_passed'] && $d['outstanding'] > 0 && $d['date']->isSameMonth($today)) {
                        $items[] = ['date' => $today, 'tone' => 'red', 'title' => $d['label'] . ' terlewat', 'sub' => $d['outstanding'] . ' klien ' . $verb, 'kind' => 'Pajak'];
                    } elseif (! $d['is_passed'] && $d['date']->lte($end)) {
                        $items[] = ['date' => $d['date'], 'tone' => $d['outstanding'] > 0 ? 'blue' : 'neutral', 'title' => $d['label'], 'sub' => $d['outstanding'] > 0 ? $d['outstanding'] . ' klien ' . $verb : 'semua beres', 'kind' => 'Pajak'];
                    }
                }
            }

            (clone $this->openProjects())
                ->with('client:id,name')
                ->whereBetween('due_date', [$today->toDateString(), $end->toDateString()])
                ->orderBy('due_date')
                ->limit(200)
                ->get(['id', 'name', 'client_id', 'due_date'])
                ->each(function (Project $p) use (&$items) {
                    $items[] = ['date' => $p->due_date, 'tone' => 'neutral', 'title' => $p->name, 'sub' => 'tenggat proyek · ' . ($p->client?->name ?? '–'), 'kind' => 'Proyek'];
                });

            \App\Models\CalendarEvent::query()
                ->with('client:id,name')
                ->whereBetween('starts_at', [$today, $end->copy()->endOfDay()])
                ->where('status', '<>', \App\Models\CalendarEvent::STATUS_CANCELED)
                ->when($this->isMine(), fn ($q) => $q->where(fn ($w) => $w
                    ->where('created_by', $this->user->id)
                    ->orWhereHas('participants', fn ($p) => $p->whereKey($this->user->id))))
                ->orderBy('starts_at')
                ->limit(200)
                ->get()
                ->each(function ($e) use (&$items) {
                    $time = $e->all_day ? 'Sepanjang hari' : $e->starts_at->format('H.i');
                    $items[] = ['date' => $e->starts_at->copy()->startOfDay(), 'tone' => 'blue', 'title' => $e->title, 'sub' => $time . ($e->client ? ' · ' . $e->client->name : ''), 'kind' => 'Jadwal'];
                });

            // Most urgent first within a day: missed tax, then tax, then the rest.
            $rank = ['red' => 0, 'blue' => 1, 'neutral' => 2];
            usort($items, fn ($a, $b) => $a['date'] <=> $b['date'] ?: ($rank[$a['tone']] <=> $rank[$b['tone']]) ?: strcmp($a['kind'], $b['kind']));

            $colors = ['red' => 'var(--kis-bar-red)', 'blue' => 'rgb(var(--primary-600))', 'neutral' => 'rgb(var(--gray-400))'];
            $short = ['Min', 'Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab'];
            $grid = [];
            for ($i = 0; $i < $days; $i++) {
                $date = $today->copy()->addDays($i);
                $dayItems = array_values(array_filter($items, fn ($item) => $item['date']->isSameDay($date)));
                $grid[] = [
                    'day' => $date->day,
                    'weekday' => $short[$date->dayOfWeek],
                    'today' => $i === 0,
                    'weekend' => $date->isWeekend(),
                    'count' => count($dayItems),
                    'tones' => array_values(array_unique(array_column($dayItems, 'tone'))),
                    'tip' => $dayItems ? [
                        't' => $this->dayLabel($date),
                        'r' => array_map(fn ($item) => [$colors[$item['tone']], $item['kind'], mb_strimwidth($item['title'], 0, 44, '…')], array_slice($dayItems, 0, 6)),
                    ] : null,
                ];
            }

            $list = array_map(fn ($item) => $item + ['when' => $this->whenLabel($item['date'])], array_slice($items, 0, $limit));

            return ['days' => $grid, 'list' => $list, 'more' => max(0, count($items) - $limit), 'total' => count($items), 'colors' => $colors];
        });
    }

    protected function dayLabel(Carbon $date): string
    {
        $days = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
        $label = $days[$date->dayOfWeek] . ' ' . $date->day . ' ' . $this->tax->monthShort($date);

        return $date->isToday() ? 'Hari ini · ' . $label : ($date->isTomorrow() ? 'Besok · ' . $label : $label);
    }

    /** Short date for the agenda list: "Hari ini", "Besok", "Jum 20 Nov". */
    protected function whenLabel(Carbon $date): string
    {
        $short = ['Min', 'Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab'];

        return $date->isToday() ? 'Hari ini' : ($date->isTomorrow() ? 'Besok' : $short[$date->dayOfWeek] . ' ' . $date->day . ' ' . $this->tax->monthShort($date));
    }

    /** Latest activity: everyone's in "kantor", the user's own in "saya". */
    public function activity(int $limit = 8): array
    {
        return \App\Models\UserActivity::query()
            ->with('user:id,name')
            ->when($this->isMine(), fn ($q) => $q->where('user_id', $this->user->id))
            ->latest()
            ->limit($limit)
            ->get()
            ->map(fn ($a) => [
                'initials' => self::initials($a->user?->name ?? '?'),
                'text' => $a->description,
                'when' => $a->created_at?->locale('id')->diffForHumans(short: true),
            ])
            ->all();
    }

    /** Admin resource index URLs (lazy sections render in a Livewire request with no current panel). */
    protected static function url(string $resource): string
    {
        return route("filament.admin.resources.{$resource}.index");
    }

    public static function initials(?string $name): string
    {
        $words = preg_split('/\s+/', trim((string) $name)) ?: [];
        $first = mb_substr($words[0] ?? '?', 0, 1);
        $last = count($words) > 1 ? mb_substr(end($words), 0, 1) : mb_substr($words[0] ?? '', 1, 1);

        return mb_strtoupper($first . $last);
    }
}
