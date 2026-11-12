<?php

namespace Tests\Feature\TaxReport;

use App\Livewire\TaxReport\ActivityConfirmation;
use App\Livewire\TaxReport\Ppn\TaxReportKompensasi;
use App\Livewire\TaxReport\Ppn\YearlySummary;
use App\Livewire\TaxReport\SptUpload;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\TaxReport;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Tax report workspaces, stage 2: compensation, yearly PPN, SPT and the
 * activity confirmation. Data lives in 2031 and is rolled back.
 */
class TaxReportWorkspacesTest extends TestCase
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
            'name' => 'Uji Ruang Kerja',
            'email' => 'uji-ruang-' . uniqid() . '@example.test',
            'password' => bcrypt('rahasia'),
            'status' => 'active',
        ]);
        Role::findOrCreate('super-admin', 'web');
        $user->assignRole('super-admin');
        $this->actingAs($user);

        $this->client = Client::forceCreate([
            'name' => 'PT UJI RUANG ' . uniqid(),
            'status' => 'Active',
            'ppn_contract' => true,
            'pph_contract' => true,
            'bupot_contract' => true,
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

    private function invoice(TaxReport $report, string $type, float $dpp, float $ppn): void
    {
        Invoice::forceCreate([
            'tax_report_id' => $report->id,
            'invoice_number' => '0400' . random_int(100000000, 999999999),
            'invoice_date' => self::YEAR . '-02-10',
            'company_name' => 'PT LAWAN UJI',
            'npwp' => '0123456789012345',
            'type' => $type,
            'ppn_percentage' => '11',
            'dpp' => $dpp,
            'ppn' => $ppn,
            'is_business_related' => true,
            'is_revision' => false,
        ]);
    }

    public function test_yearly_summary_lists_twelve_months_with_totals_and_peredaran_bruto(): void
    {
        $jan = $this->report('January');
        $this->invoice($jan, 'Faktur Keluaran', 1000000, 110000);
        $feb = $this->report('February');
        $this->invoice($feb, 'Faktur Masuk', 500000, 55000);

        $year = Livewire::test(YearlySummary::class, ['taxReportId' => $feb->id, 'clientId' => $this->client->id])
            ->assertSee('PPN tahun ' . self::YEAR)
            ->assertSee('2 dari 12 bulan punya laporan')
            ->assertSee('Belum ada laporan')
            ->instance()->year;

        $this->assertCount(12, $year['rows']);
        $this->assertTrue($year['rows'][1]['current']);
        $this->assertSame(110000.0, $year['totals']['kurang']);
        $this->assertSame(55000.0, $year['totals']['lebih']);
        $this->assertSame(1000000.0, $year['totals']['bruto'], 'DPP of counted keluaran, not a missing summary column');
        $this->assertSame([110000.0, 0.0], [$year['chart']['up'][0], $year['chart']['down'][0]]);
        $this->assertSame([0.0, 55000.0], [$year['chart']['up'][1], $year['chart']['down'][1]]);
    }

    public function test_compensation_offers_the_next_period_when_there_is_lebih_bayar(): void
    {
        $feb = $this->report('February');
        $this->invoice($feb, 'Faktur Masuk', 500000, 55000);
        $this->report('March');

        Livewire::test(TaxReportKompensasi::class, ['taxReportId' => $feb->id])
            ->assertSee('Rp 55.000 bisa dikompensasikan ke Maret ' . self::YEAR)
            ->assertSee('Buat kompensasi ke Maret')
            ->assertSee('Belum ada kompensasi diterima');
    }

    public function test_compensation_explains_why_nothing_can_be_carried_over(): void
    {
        $feb = $this->report('February');
        $this->invoice($feb, 'Faktur Keluaran', 1000000, 110000);

        Livewire::test(TaxReportKompensasi::class, ['taxReportId' => $feb->id])
            ->assertSee('Tidak ada lebih bayar untuk dikompensasikan')
            ->assertDontSee('Buat kompensasi ke');
    }

    public function test_activity_question_marks_no_activity_and_the_spt_panel_follows(): void
    {
        $feb = $this->report('February');

        Livewire::test(ActivityConfirmation::class, ['taxReportId' => $feb->id, 'type' => 'bupot'])
            ->assertSee('Ada pemotongan PPh Unifikasi pada Februari ' . self::YEAR . '?')
            ->assertSee('Ada, unggah SPT')
            ->callAction('markNoActivity')
            ->assertDispatched('spt-updated')
            ->assertSee('Tidak ada pemotongan PPh Unifikasi pada Februari ' . self::YEAR);

        Livewire::test(SptUpload::class, ['taxReportId' => $feb->id, 'type' => 'bupot'])
            ->assertSee('SPT Masa PPh Unifikasi')
            ->assertSee('Tidak ada aktivitas');
    }

    public function test_spt_panel_opens_the_upload_form_for_its_own_type_and_uses_the_period_year(): void
    {
        $feb = $this->report('February', ['ppn' => ['report_status' => 'Belum Lapor']]);

        $spt = Livewire::test(SptUpload::class, ['taxReportId' => $feb->id, 'type' => 'ppn'])
            ->assertSee('Belum ada berkas SPT')
            ->call('openUpload', 'bupot')
            ->assertActionNotMounted('uploadSpt')
            ->call('openUpload', 'ppn')
            ->assertActionMounted('uploadSpt');

        // The report row was created now, the period is 2031.
        $this->assertSame('Februari ' . self::YEAR, $spt->instance()->periodLabel());
    }
}
