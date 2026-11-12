<?php

namespace Tests\Feature\TaxReport;

use App\Filament\Resources\TaxReportResource;
use App\Filament\Resources\TaxReportResource\Pages\ViewTaxReport;
use App\Filament\Resources\TaxReportResource\RelationManagers\BupotsRelationManager;
use App\Livewire\TaxReport\Pph\KaryawanList;
use App\Livewire\TaxReport\Pph\PphTaxList;
use App\Livewire\TaxReport\Pph\TaxReportPph;
use App\Models\Client;
use App\Models\IncomeTax;
use App\Models\TaxReport;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Tax report workspaces, stage 3: PPh (live figures, no placeholder tabs),
 * the bupot table on the page, and PPh Badan. Data lives in 2031.
 */
class TaxReportPphAndBupotTest extends TestCase
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
            'name' => 'Uji PPh',
            'email' => 'uji-pph-' . uniqid() . '@example.test',
            'password' => bcrypt('rahasia'),
            'status' => 'active',
        ]);
        Role::findOrCreate('super-admin', 'web');
        $user->assignRole('super-admin');
        $this->actingAs($user);

        $this->client = Client::forceCreate([
            'name' => 'PT UJI PPH ' . uniqid(),
            'status' => 'Active',
            'ppn_contract' => true,
            'pph_contract' => true,
            'bupot_contract' => true,
            'pph_badan_contract' => true,
        ]);
    }

    protected function tearDown(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        parent::tearDown();
    }

    private function report(): TaxReport
    {
        return TaxReport::forceCreate(['client_id' => $this->client->id, 'month' => 'February', 'year' => self::YEAR]);
    }

    private function incomeTax(TaxReport $report, ?string $kind, float $dpp, float $pph): void
    {
        IncomeTax::forceCreate([
            'tax_report_id' => $report->id,
            'jenis_pajak' => $kind,
            'nama' => 'Penerima Uji',
            'npwp' => '0123456789012345',
            'nomor_pemotongan' => (string) random_int(1000000000, 9999999999),
            'dasar_pengenaan_pajak' => $dpp,
            'pajak_penghasilan' => $pph,
            'status' => 'NORMAL',
        ]);
    }

    public function test_pph_workspace_shows_live_figures_per_kind_and_flags_unknown_values(): void
    {
        $report = $this->report();
        $this->incomeTax($report, 'Pasal 21', 5000000, 250000);
        $this->incomeTax($report, 'Pasal 21', 3000000, 150000);
        $this->incomeTax($report, '2031-02-01T00:00:00+07:00', 100000, 0);

        $pph = Livewire::test(TaxReportPph::class, ['taxReportId' => $report->id])
            ->assertSee('PPh · Februari ' . self::YEAR)
            ->assertDontSee('Client Name')
            ->assertDontSee('akan tersedia')
            ->assertSee('Jenis tidak dikenali');

        $breakdown = $pph->instance()->breakdown;
        $this->assertSame(['count' => 2, 'dpp' => 8000000.0, 'pph' => 400000.0], array_intersect_key($breakdown['pph21'], array_flip(['count', 'dpp', 'pph'])));
        $this->assertSame(['PPh 21', 'Jenis tidak dikenali'], array_column($breakdown['rows'], 'label'));

        // No cache: a new slip shows up on the next render.
        $this->incomeTax($report, 'Pasal 21', 1000000, 50000);
        $this->assertSame(3, $pph->call('refreshData')->instance()->breakdown['pph21']['count']);
    }

    public function test_pph_list_tells_the_page_after_changes_but_not_on_load(): void
    {
        $report = $this->report();

        Livewire::test(PphTaxList::class, ['taxReportId' => $report->id])
            ->assertNotDispatched('tax-report-changed')
            ->assertSee('Bukti potong PPh 21')
            ->call('calculateStatistics')
            ->assertDispatched('tax-report-changed');
    }

    public function test_employee_empty_state_opens_the_add_employee_form(): void
    {
        $report = $this->report();

        Livewire::test(KaryawanList::class, ['clientId' => $this->client->id, 'taxReportId' => $report->id])
            // No form of its own, so clicking runs it at once and swaps in the header form.
            ->call('mountTableAction', 'create_first')
            ->assertTableActionMounted('create');
    }

    public function test_unifikasi_tab_embeds_the_bupot_table_and_badan_gets_an_spt_panel(): void
    {
        $report = $this->report();

        $this->get(TaxReportResource::getUrl('view', ['record' => $report]) . '?jenis=unifikasi&tab=spt')
            ->assertOk()
            ->assertSee('Bukti potong PPh Unifikasi')
            ->assertSee('Tambah Bukti Potong')
            ->assertSee("value: 'spt'", false)
            ->assertSee('SPT Masa PPh Badan');

        Livewire::test(BupotsRelationManager::class, ['ownerRecord' => $report, 'pageClass' => ViewTaxReport::class])
            ->assertSuccessful()
            ->call('bupotsChanged')
            ->assertDispatched('tax-report-changed');
    }
}
