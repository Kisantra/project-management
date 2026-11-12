<?php

namespace Tests\Feature\TaxReport;

use App\Filament\Resources\TaxReportResource;
use App\Filament\Resources\TaxReportResource\Pages\ListTaxReports;
use App\Models\Client;
use App\Models\TaxReport;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Uses DatabaseTransactions like the other tests in this repo; data lives in
 * year 2031 so it never mixes with real reports and is rolled back afterwards.
 */
class ListTaxReportsTest extends TestCase
{
    use DatabaseTransactions;

    private const YEAR = 2031;

    private Client $client;

    protected function setUp(): void
    {
        parent::setUp();

        config(['broadcasting.default' => 'null']);
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $user = User::forceCreate([
            'name' => 'Uji Laporan',
            'email' => 'uji-laporan-' . uniqid() . '@example.test',
            'password' => bcrypt('rahasia'),
            'status' => 'active',
        ]);
        Role::findOrCreate('super-admin', 'web');
        $user->assignRole('super-admin');
        $this->actingAs($user);

        $this->client = Client::forceCreate([
            'name' => 'PT UJI LAPORAN ' . uniqid(),
            'NPWP' => '09.876.543.2-101.000',
            'status' => 'Active',
            'ppn_contract' => true,
            'pph_contract' => true,
        ]);
    }

    protected function tearDown(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        parent::tearDown();
    }

    private function report(string $month, array $summaries = []): TaxReport
    {
        $report = TaxReport::forceCreate(['client_id' => $this->client->id, 'month' => $month, 'year' => self::YEAR]);

        foreach ($summaries as $type => $attributes) {
            $report->taxCalculationSummaries()->updateOrCreate(['tax_type' => $type], $attributes);
        }

        return $report->fresh(['client', 'taxCalculationSummaries']);
    }

    public function test_slots_describe_each_tax_type(): void
    {
        $report = $this->report('October', [
            'ppn' => ['report_status' => 'Sudah Lapor', 'reported_at' => '2031-10-28', 'bayar_status' => 'Sudah Bayar'],
            'pph' => ['report_status' => 'Belum Lapor'],
        ]);

        $slots = ListTaxReports::slots($report);

        $this->assertSame('done', $slots['ppn']['state']);
        $this->assertSame('28 Okt', $slots['ppn']['date']);
        $this->assertTrue($slots['ppn']['paid']);
        $this->assertSame('pending', $slots['pph']['state']);
        $this->assertSame('none', $slots['bupot']['state'], 'no Bupot contract');
        $this->assertSame('none', $slots['pph_badan']['state']);
    }

    public function test_period_filter_selects_several_months(): void
    {
        $january = $this->report('January');
        $march = $this->report('March');
        $october = $this->report('October');

        Livewire::test(ListTaxReports::class)
            ->set('period', ['year' => self::YEAR, 'months' => [1, 3]])
            ->loadTable()
            ->assertCanSeeTableRecords([$january, $march])
            ->assertCanNotSeeTableRecords([$october])
            ->set('period', ['year' => self::YEAR, 'months' => []])
            ->assertCanSeeTableRecords([$january, $march, $october]);
    }

    public function test_period_months_are_sanitised(): void
    {
        $page = Livewire::test(ListTaxReports::class)
            ->set('period', ['year' => self::YEAR, 'months' => ['13', 'x', 5, '5', 0]]);

        $this->assertSame([5], $page->instance()->periodMonths());
        $this->assertSame(['year' => self::YEAR, 'months' => [5]], $page->get('period'));
    }

    public function test_default_order_is_newest_period_first_and_periode_sort_is_chronological(): void
    {
        $january = $this->report('January');
        $october = $this->report('October');
        $march = $this->report('March');

        Livewire::test(ListTaxReports::class)
            ->set('period', ['year' => self::YEAR, 'months' => []])
            ->loadTable()
            ->assertCanSeeTableRecords([$october, $march, $january], inOrder: true)
            ->call('sortTable', 'month', 'asc')
            ->assertCanSeeTableRecords([$january, $march, $october], inOrder: true);
    }

    public function test_client_sort_keeps_newest_period_first_per_client(): void
    {
        $march = $this->report('March');
        $october = $this->report('October');
        $january = $this->report('January');

        Livewire::test(ListTaxReports::class)
            ->set('period', ['year' => self::YEAR, 'months' => []])
            ->loadTable()
            ->call('sortTable', 'client.name', 'asc')
            ->assertCanSeeTableRecords([$october, $march, $january], inOrder: true);
    }

    public function test_month_counts_and_group_summary(): void
    {
        $this->report('October', ['ppn' => ['report_status' => 'Sudah Lapor'], 'pph' => ['report_status' => 'Sudah Lapor']]);
        $this->report('October', ['ppn' => ['report_status' => 'Belum Lapor']]);
        $this->report('March');

        $page = Livewire::test(ListTaxReports::class)->set('period', ['year' => self::YEAR, 'months' => []])->instance();

        $this->assertSame(2, $page->monthCounts[self::YEAR][10]);
        $this->assertSame(1, $page->monthCounts[self::YEAR][3]);
        $this->assertArrayHasKey(now()->year, $page->monthCounts);
        $this->assertSame(['total' => 2, 'pending' => 1], $page->monthSummary[self::YEAR . '-10']);
    }

    public function test_period_chip_label(): void
    {
        $page = Livewire::test(ListTaxReports::class);

        $page->set('period', ['year' => 2026, 'months' => [9, 10, 11]]);
        $this->assertSame('Sep – Nov 2026', $page->instance()->periodSummary());

        $page->set('period', ['year' => 2026, 'months' => [1, 3]]);
        $this->assertSame('Jan, Mar 2026', $page->instance()->periodSummary());

        $page->set('period', ['year' => 2026, 'months' => []]);
        $this->assertNull($page->instance()->periodSummary());
    }

    public function test_labels_are_no_longer_pluralised(): void
    {
        $this->assertSame('Laporan Pajak', TaxReportResource::getPluralModelLabel());
        $this->assertSame('Laporan Pajak', TaxReportResource::getModelLabel());
    }
}
