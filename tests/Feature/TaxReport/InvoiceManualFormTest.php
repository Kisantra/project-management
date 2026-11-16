<?php

namespace Tests\Feature\TaxReport;

use App\Livewire\TaxReport\Ppn\InvoiceTable;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\TaxReport;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Manual invoice entry and revisions on the tax report detail page
 * (InvoiceTable + InvoiceFormTrait). No AI is involved in either form.
 */
class InvoiceManualFormTest extends TestCase
{
    use DatabaseTransactions;

    private TaxReport $report;

    protected function setUp(): void
    {
        parent::setUp();

        config(['broadcasting.default' => 'null']);
        Storage::fake('public');

        $this->actingAs(User::forceCreate([
            'name' => 'Uji Faktur Manual',
            'email' => 'uji-faktur-manual-' . uniqid() . '@example.test',
            'password' => bcrypt('rahasia'),
        ]));

        $client = Client::forceCreate([
            'name' => 'CV. UJI FAKTUR MANUAL',
            'NPWP' => '01.234.567.8-901.000',
            'ppn_contract' => true,
        ]);

        $this->report = TaxReport::forceCreate(['client_id' => $client->id, 'month' => 'October', 'year' => 2031]);
    }

    private function table()
    {
        return Livewire::test(InvoiceTable::class, ['taxReportId' => $this->report->id]);
    }

    private function invoice(array $attributes = []): Invoice
    {
        return Invoice::forceCreate([
            'tax_report_id' => $this->report->id,
            'invoice_number' => '0400253100000' . random_int(1000, 9999),
            'invoice_date' => '2031-10-05',
            'client_type' => 'Swasta',
            'has_ppn' => true,
            'is_business_related' => false,
            'type' => 'Faktur Keluaran',
            'company_name' => 'PT PEMBELI UJI',
            'npwp' => '00.111.111.1-111.000',
            'ppn_percentage' => '11',
            'dpp' => 625000,
            'ppn' => 68750,
            ...$attributes,
        ]);
    }

    public function test_manual_invoice_saves_without_a_file(): void
    {
        $this->table()
            ->mountTableAction('create')
            ->setTableActionData([
                'invoice_number' => '02002531000000001',
                'invoice_date' => '2031-10-07',
                'client_type' => 'Pemerintah',
                'type' => 'Faktur Keluaran',
                'company_name' => 'DINAS UJI',
                'npwp' => '00.222.222.2-222.000',
                'is_business_related' => 1,
                'ppn_percentage' => '11',
                'dpp' => '1,000,000.00',
                'ppn' => '110,000.00',
            ])
            ->callMountedTableAction()
            ->assertHasNoTableActionErrors();

        $invoice = Invoice::where('invoice_number', '02002531000000001')->firstOrFail();

        $this->assertNull($invoice->file_path);
        $this->assertSame($this->report->id, $invoice->tax_report_id);
        $this->assertEquals(1000000, $invoice->dpp);
        $this->assertEquals(110000, $invoice->ppn);
        $this->assertFalse((bool) $invoice->has_ppn, 'has_ppn follows the client type and is saved');
    }

    public function test_revision_form_is_prefilled_from_the_latest_version(): void
    {
        $original = $this->invoice();

        $this->table()
            ->mountTableAction('create_revision', $original)
            ->assertTableActionDataSet([
                'invoice_number' => "{$original->invoice_number}-REV1",
                'revision_number' => 1,
                // Mask-friendly format; "625.000,00" used to be re-masked to 625.
                'dpp' => '625,000.00',
                'ppn' => '68,750.00',
                'is_business_related' => 0,
            ]);

        $this->invoice([
            'invoice_number' => "{$original->invoice_number}-REV1",
            'is_revision' => true,
            'original_invoice_id' => $original->id,
            'revision_number' => 1,
            'dpp' => 700000,
            'ppn' => 77000,
        ]);

        $this->table()
            ->mountTableAction('create_revision', $original)
            ->assertTableActionDataSet([
                'invoice_number' => "{$original->invoice_number}-REV2",
                'revision_number' => 2,
                'dpp' => '700,000.00',
                'ppn' => '77,000.00',
            ]);
    }

    public function test_revision_is_saved_manually_without_a_file(): void
    {
        $original = $this->invoice();

        $this->table()
            ->callTableAction('create_revision', $original, data: [
                'revision_reason' => 'Koreksi keterangan barang',
                // Tampered hidden fields must not relink or renumber the revision.
                'original_invoice_id' => 999999,
                'revision_number' => 7,
            ])
            ->assertHasNoTableActionErrors();

        $revision = Invoice::where('original_invoice_id', $original->id)->firstOrFail();

        $this->assertTrue($revision->is_revision);
        $this->assertSame(1, $revision->revision_number);
        $this->assertSame('Koreksi keterangan barang', $revision->revision_reason);
        $this->assertSame("{$original->invoice_number}-REV1", $revision->invoice_number);
        $this->assertSame($this->report->id, $revision->tax_report_id);
        $this->assertNull($revision->file_path);
        $this->assertEquals(625000, $revision->dpp, 'unchanged amounts survive the round trip');
        $this->assertEquals(68750, $revision->ppn);
        $this->assertFalse((bool) $revision->is_business_related, 'copied from the original');
    }

    public function test_revision_with_changed_amounts(): void
    {
        $original = $this->invoice();

        $this->table()
            ->callTableAction('create_revision', $original, data: [
                'revision_reason' => 'Koreksi nilai DPP',
                'invoice_number' => '04012531000000001',
                'dpp' => '650,000.00',
                'ppn' => '71,500.00',
            ])
            ->assertHasNoTableActionErrors();

        $revision = Invoice::where('invoice_number', '04012531000000001')->firstOrFail();

        $this->assertEquals(650000, $revision->dpp);
        $this->assertEquals(71500, $revision->ppn);
        $this->assertSame($original->id, $revision->original_invoice_id);
    }

    public function test_revision_requires_a_reason_and_a_new_number(): void
    {
        $original = $this->invoice();

        $this->table()
            ->callTableAction('create_revision', $original, data: [
                'revision_reason' => '',
                'invoice_number' => $original->invoice_number,
            ])
            ->assertHasTableActionErrors(['revision_reason' => 'required', 'invoice_number' => 'unique']);

        $this->assertSame(0, Invoice::where('original_invoice_id', $original->id)->count());
    }
}
