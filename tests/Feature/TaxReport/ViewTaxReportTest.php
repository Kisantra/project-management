<?php

namespace Tests\Feature\TaxReport;

use App\Filament\Resources\TaxReportResource;
use App\Filament\Resources\TaxReportResource\Pages\ViewTaxReport;
use App\Livewire\TaxReport\Ppn\InvoiceTable;
use App\Livewire\TaxReport\Ppn\TaxReportInvoices;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\TaxReport;
use App\Models\User;
use App\Services\TaxReport\PpnBreakdown;
use App\Services\TaxReport\ReportOverview;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Tax report page redesign (docs/mockups/tax-report-detail-redesign.html):
 * overview header and strip, period navigation, the PPN rules shared by the
 * stored summary and the screen, and refresh after invoice changes.
 * Data lives in 2031 and is rolled back.
 */
class ViewTaxReportTest extends TestCase
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
            'name' => 'Uji Detail Pajak',
            'email' => 'uji-detail-' . uniqid() . '@example.test',
            'password' => bcrypt('rahasia'),
            'status' => 'active',
        ]);
        Role::findOrCreate('super-admin', 'web');
        $user->assignRole('super-admin');
        $this->actingAs($user);

        $this->client = Client::forceCreate([
            'name' => 'PT UJI DETAIL ' . uniqid(),
            'NPWP' => '098765432101000',
            'status' => 'Active',
            'ppn_contract' => true,
            'pph_contract' => true,
            'bupot_contract' => false,
            'pph_badan_contract' => false,
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

    private function invoice(TaxReport $report, array $attributes): Invoice
    {
        return Invoice::forceCreate($attributes + [
            'tax_report_id' => $report->id,
            'invoice_number' => '0400' . random_int(100000000, 999999999),
            'invoice_date' => self::YEAR . '-02-10',
            'company_name' => 'PT LAWAN UJI',
            'npwp' => '0123456789012345',
            'type' => 'Faktur Keluaran',
            'ppn_percentage' => '11',
            'dpp' => 0,
            'ppn' => 0,
            'is_business_related' => true,
            'is_revision' => false,
        ]);
    }

    public function test_page_shows_the_client_period_and_progress_of_contracted_types_only(): void
    {
        $report = $this->report('February', ['ppn' => ['report_status' => 'Sudah Lapor', 'reported_at' => self::YEAR . '-03-14']]);

        Livewire::test(ViewTaxReport::class, ['record' => $report->getRouteKey()])
            ->assertSuccessful()
            ->assertSee($this->client->name)
            ->assertSee('Februari ' . self::YEAR)
            ->assertSee('NPWP 09.876.543.2-101.000')
            ->assertSee('Kontrak PPN, PPh')
            // Two contracted types; PPh Unifikasi and PPh Badan are not counted.
            ->assertSee('1 dari 2 jenis sudah lapor')
            ->assertSeeInOrder(['PPN', 'Sudah lapor', 'PPh', 'Belum lapor', 'PPh Unifikasi', 'Tidak dikontrak', 'PPh Badan', 'Tidak dikontrak']);
    }

    public function test_months_and_stepper_follow_the_clients_reports(): void
    {
        $this->report('January', ['ppn' => ['report_status' => 'Sudah Lapor'], 'pph' => ['report_status' => 'Sudah Lapor']]);
        $feb = $this->report('February');
        $this->report('April');

        $overview = ReportOverview::for($feb);
        $months = collect($overview->months())->keyBy('short');

        $this->assertSame('done', $months['Jan']['state']);
        $this->assertSame('pending', $months['Feb']['state']);
        $this->assertTrue($months['Feb']['current']);
        $this->assertSame('nodata', $months['Mar']['state']);
        $this->assertNull($months['Mar']['url']);
        $this->assertSame('Januari ' . self::YEAR, $overview->neighbours()['prev']['label']);
        $this->assertSame('April ' . self::YEAR, $overview->neighbours()['next']['label']);
        $this->assertSame(['ppn', 'pph'], $overview->openTabs(), 'Locked tabs cannot be opened');
    }

    public function test_tab_from_the_url_opens_on_first_render_and_locked_tabs_fall_back(): void
    {
        $report = $this->report('February');
        $url = TaxReportResource::getUrl('view', ['record' => $report]);

        $this->get($url . '?jenis=pph')->assertOk()->assertSee("value: 'pph'", false);
        $this->get($url . '?jenis=unifikasi')->assertOk()->assertSee("param: 'jenis', value: 'ppn'", false);
        $this->get($url . '?tab=perhitungan')->assertOk()->assertSee("value: 'perhitungan'", false);
    }

    public function test_ppn_rules_are_shared_by_the_stored_summary_and_the_screen(): void
    {
        $report = $this->report('February');
        $this->invoice($report, ['dpp' => 1000000, 'ppn' => 110000]);
        $this->invoice($report, ['invoice_number' => '0700123', 'dpp' => 500000, 'ppn' => 55000]); // excluded code
        $original = $this->invoice($report, ['dpp' => 200000, 'ppn' => 22000]);
        $this->invoice($report, ['invoice_number' => $original->invoice_number . '-REV1', 'is_revision' => true, 'original_invoice_id' => $original->id, 'revision_number' => 1, 'dpp' => 300000, 'ppn' => 33000]);
        $this->invoice($report, ['type' => 'Faktur Masuk', 'dpp' => 100000, 'ppn' => 11000]);
        $this->invoice($report, ['type' => 'Faktur Masuk', 'is_business_related' => false, 'dpp' => 50000, 'ppn' => 5500]);

        $breakdown = PpnBreakdown::for($report);
        $this->assertSame(143000.0, $breakdown->ppnKeluar(), '110.000 + the revision 33.000; code 07 left out');
        $this->assertSame(11000.0, $breakdown->ppnMasuk(), 'Non-business masukan is not credited');
        $this->assertSame(2, $breakdown->excludedKeluar + $breakdown->excludedMasuk);

        $summary = $report->fresh()->taxCalculationSummaries()->where('tax_type', 'ppn')->first();
        $this->assertSame(132000.0, (float) $summary->selisih, 'Stored summary uses the same rules');
    }

    public function test_calculation_ledger_includes_manual_compensation_like_the_stored_saldo(): void
    {
        $report = $this->report('February');
        $this->invoice($report, ['dpp' => 1000000, 'ppn' => 110000]);
        $summary = $report->taxCalculationSummaries()->where('tax_type', 'ppn')->first();
        $summary->update(['manual_kompensasi' => 10000, 'manual_kompensasi_notes' => 'Koreksi uji']);
        $summary->recalculate();

        $ledger = Livewire::test(TaxReportInvoices::class, ['taxReportId' => $report->id])
            ->assertSee('Perhitungan PPN Februari ' . self::YEAR)
            ->assertSee('Koreksi uji')
            ->instance()->ledger;

        $this->assertSame(100000.0, $ledger['saldo']);
        $this->assertSame((float) $summary->fresh()->saldo_final, $ledger['saldo']);
        $this->assertSame('Kurang Bayar', $ledger['status']);
    }

    public function test_changing_what_counts_recalculates_and_refreshes_the_page(): void
    {
        $report = $this->report('February');
        $invoice = $this->invoice($report, ['type' => 'Faktur Masuk', 'dpp' => 100000, 'ppn' => 11000]);
        $this->assertSame(-11000.0, (float) $report->fresh()->ppnSummary->selisih);

        // Only the business flag changes, no amount: the summary still follows.
        $invoice->update(['is_business_related' => false]);
        $this->assertSame(0.0, (float) $report->fresh()->ppnSummary->selisih);

        Livewire::test(InvoiceTable::class, ['taxReportId' => $report->id])
            ->call('invoicesChanged')
            ->assertDispatched('tax-report-changed');
    }
}
