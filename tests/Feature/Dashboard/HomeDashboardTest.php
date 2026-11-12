<?php

namespace Tests\Feature\Dashboard;

use App\Filament\Pages\Dashboard;
use App\Livewire\Dashboard\Home\Section;
use App\Models\Client;
use App\Models\DailyTask;
use App\Models\Project;
use App\Models\TaxReport;
use App\Models\User;
use App\Services\Dashboard\HomeDashboard;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Main dashboard: section access, and the numbers in the "saya" scope, which
 * only cover the user's own clients and projects, so the data here is isolated
 * from whatever else is in the database. The date is frozen at 15 March 2031:
 * the billed period is February, the PPh deadline (10 March) has passed and
 * the PPN one (20 March) has not.
 */
class HomeDashboardTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        config(['broadcasting.default' => 'null']);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        Carbon::setTestNow(Carbon::create(2031, 3, 15, 10));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        parent::tearDown();
    }

    private function user(array $paths = [], bool $superAdmin = false): User
    {
        $user = User::forceCreate([
            'name' => 'Uji Beranda',
            'email' => 'uji-beranda-' . uniqid() . '@example.test',
            'password' => bcrypt('rahasia'),
            'status' => 'active',
        ]);

        if ($superAdmin) {
            Role::findOrCreate('super-admin', 'web');
            $user->assignRole('super-admin');
        }

        foreach ($paths as $path) {
            $user->givePermissionTo(Permission::forceCreate(['name' => 'uji-' . uniqid(), 'guard_name' => 'web', 'http_path' => $path]));
        }

        return $user->fresh();
    }

    /** A client of $user with PPN and PPh contracts, a report for $month and its summaries. */
    private function clientWithReport(User $user, string $month, array $summaries): Client
    {
        $client = Client::forceCreate(['name' => 'PT UJI BERANDA ' . uniqid(), 'status' => 'Active', 'ppn_contract' => true, 'pph_contract' => true, 'bupot_contract' => false]);
        DB::table('user_clients')->insert(['user_id' => $user->id, 'client_id' => $client->id, 'created_at' => now(), 'updated_at' => now()]);
        $this->report($client, $month, $summaries);

        return $client;
    }

    private function report(Client $client, string $month, array $summaries): void
    {
        $report = TaxReport::forceCreate(['client_id' => $client->id, 'month' => $month, 'year' => 2031]);
        foreach ($summaries as $type => $attributes) {
            $report->taxCalculationSummaries()->updateOrCreate(['tax_type' => $type], $attributes);
        }
    }

    public function test_section_access_follows_the_old_dashboard_permissions(): void
    {
        $none = $this->user();
        $tax = $this->user(['dashboard-tax-report*']);
        $admin = $this->user(superAdmin: true);

        foreach (['compliance', 'ontime', 'ppn', 'clients'] as $section) {
            $this->assertFalse(Section::allowed($section, 'kantor', $none), $section);
            $this->assertTrue(Section::allowed($section, 'kantor', $tax), $section);
        }
        $this->assertFalse(Section::allowed('workload', 'kantor', $tax), 'team workload needs project-dashboard access');
        $this->assertTrue(Section::allowed('workload', 'saya', $tax), 'own workload is always visible');
        $this->assertTrue(Section::allowed('workload', 'kantor', $admin));
        foreach (['agenda', 'flow', 'status', 'activity'] as $section) {
            $this->assertTrue(Section::allowed($section, 'kantor', $none), $section);
        }
        $this->assertFalse(Section::allowed('nope', 'kantor', $admin));
    }

    public function test_a_hidden_section_renders_nothing(): void
    {
        $this->actingAs($this->user());

        Livewire::withoutLazyLoading()->test(Section::class, ['section' => 'compliance'])
            ->assertDontSee('Kepatuhan pajak');
    }

    public function test_page_switches_scope_and_rejects_unknown_scopes(): void
    {
        $this->actingAs($this->user(superAdmin: true));

        Livewire::test(Dashboard::class)
            ->assertSee('Selamat pagi, Uji')
            ->assertSee('Sabtu, 15 Maret 2031')
            ->call('setScope', 'saya')
            ->assertSet('scope', 'saya')
            ->call('setScope', 'semua')
            ->assertSet('scope', 'kantor');
    }

    public function test_my_scope_counts_only_my_clients_and_projects(): void
    {
        $user = $this->user(superAdmin: true);
        $this->actingAs($user);

        // February (billed in March): PPN reported, PPh not (its deadline, 10 March, has passed).
        $client = $this->clientWithReport($user, 'February', [
            'ppn' => ['report_status' => 'Sudah Lapor', 'reported_at' => '2031-03-12', 'status_final' => 'Kurang Bayar', 'saldo_final' => 2000000],
            'pph' => ['report_status' => 'Belum Lapor'],
        ]);
        // January (all deadlines passed): PPN on time (by 20 Feb), PPh late (after 10 Feb).
        $this->report($client, 'January', [
            'ppn' => ['report_status' => 'Sudah Lapor', 'reported_at' => '2031-02-18'],
            'pph' => ['report_status' => 'Sudah Lapor', 'reported_at' => '2031-02-12'],
        ]);
        Project::forceCreate(['client_id' => $client->id, 'name' => 'Uji Proyek Telat', 'type' => 'single', 'due_date' => '2031-03-01', 'status' => 'in_progress', 'pic_id' => $user->id]);
        Project::forceCreate(['client_id' => $client->id, 'name' => 'Uji Proyek Segera', 'type' => 'single', 'due_date' => '2031-03-19', 'status' => 'in_progress', 'pic_id' => $user->id]);
        Project::forceCreate(['client_id' => $client->id, 'name' => 'Uji Proyek Orang Lain', 'type' => 'single', 'due_date' => '2031-03-01', 'status' => 'in_progress']);
        $task = DailyTask::forceCreate(['title' => 'Uji tugas', 'task_date' => '2031-03-15', 'status' => 'completed', 'priority' => 'normal', 'created_by' => $user->id]);
        DB::table('daily_task_assignments')->insert(['daily_task_id' => $task->id, 'user_id' => $user->id, 'created_at' => now(), 'updated_at' => now()]);

        $home = HomeDashboard::for($user, HomeDashboard::SAYA);

        $types = collect($home->taxCompliance()['types'])->keyBy('key');
        $this->assertSame(['total' => 1, 'done' => 0, 'pending' => 0, 'late' => 1], array_intersect_key($types['pph'], array_flip(['total', 'done', 'pending', 'late'])));
        $this->assertSame(['total' => 1, 'done' => 1, 'pending' => 0, 'late' => 0], array_intersect_key($types['ppn'], array_flip(['total', 'done', 'pending', 'late'])));
        $this->assertSame(0, $types['bupot']['total'], 'no PPh Unifikasi contract');
        $this->assertSame(50, $home->taxCompliance()['pct']);

        $this->assertSame(50, end($home->onTimeTrend()['points'])['value'], 'January: PPN on time, PPh late');
        $this->assertSame(['up' => 2000000.0, 'clients' => 1], array_intersect_key($home->ppnPosition()['latest'], array_flip(['up', 'clients'])));

        $actions = $home->actions(true);
        $cards = collect($actions['cards'])->keyBy('key');
        $this->assertSame(['projects', 'soon', 'tasks', 'tax'], $cards->keys()->all(), 'tax gets one card, projects the rest');
        $this->assertSame(1, $cards['projects']['value'], 'only my project counts');
        $this->assertSame(1, $cards['soon']['value']);
        $this->assertStringContainsString('19 Mar · Uji Proyek Segera', $cards['soon']['sub']);
        $this->assertStringEndsWith('?due=next_7_days', $cards['soon']['url']);
        $this->assertTrue($cards['tax']['alert'], 'the missed PPh deadline wins over the next one');
        // Tax reports also create daily tasks for the client's users (app observers), so count what is there.
        $myTasksToday = DB::table('daily_task_assignments as a')->join('daily_tasks as t', 'a.daily_task_id', '=', 't.id')
            ->where('a.user_id', $user->id)->whereDate('t.task_date', '2031-03-15')->where('t.status', '<>', 'cancelled')->distinct()->count('t.id');
        $myDoneToday = DB::table('daily_task_assignments as a')->join('daily_tasks as t', 'a.daily_task_id', '=', 't.id')
            ->where('a.user_id', $user->id)->whereDate('t.task_date', '2031-03-15')->where('t.status', 'completed')->distinct()->count('t.id');
        $this->assertGreaterThanOrEqual(1, $myTasksToday);
        $this->assertSame($myTasksToday, $cards['tasks']['value']);
        $this->assertStringContainsString($myDoneToday . ' selesai', $cards['tasks']['sub']);
        $this->assertSame('1 proyek', collect($actions['brief']['items'])->pluck('text')->first(fn ($t) => str_contains($t, 'proyek')));

        $workload = $home->teamWorkload()['rows'];
        $this->assertCount(1, $workload);
        $this->assertSame(['open' => 2, 'late' => 1], array_intersect_key($workload[0], array_flip(['open', 'late'])));

        // Same bucketing as a plain scoped count (the other client's project without me is left out).
        $this->assertSame($home->projects()->where('created_at', '>=', Carbon::now()->startOfMonth())->count(), end($home->projectFlow()['months'])['in']);
        $this->assertGreaterThanOrEqual(1, end($home->projectFlow()['months'])['in']);

        $agenda = $home->agenda(true);
        $titles = collect($agenda['list'])->pluck('title');
        $this->assertSame('Batas Lapor PPh 21 & Unifikasi terlewat', $titles->first(), 'missed tax first, on today');
        $this->assertContains('Batas Lapor PPN', $titles->all());
        $this->assertCount(14, $agenda['days']);
        $this->assertTrue($agenda['days'][0]['today']);
        $this->assertSame(['red'], $agenda['days'][0]['tones']);
        $this->assertSame(1, $agenda['days'][4]['count'], '19 March: the project due soon');
    }

    public function test_chart_tooltips_are_json_and_escape_names(): void
    {
        $html = (string) $this->blade('<x-kis.chart.line :labels="[\'Jan\', \'Feb\']" :series="$series" label="uji" />', [
            'series' => [['name' => '<b>PT Jahat</b>', 'color' => 'red', 'values' => [1, null]]],
        ]);

        $this->assertStringNotContainsString('<b>PT Jahat</b>', $html);
        $this->assertStringContainsString('data-kis-tip="{&quot;t&quot;:&quot;Jan&quot;', $html);
        $this->assertSame(1, substr_count($html, '<polyline'), 'a null value breaks the line');
    }

    public function test_old_dashboard_urls_redirect_home(): void
    {
        $this->get('/project-dashboard')->assertRedirect('/');
        $this->get('/dashboard-tax-report')->assertRedirect('/');
    }
}
