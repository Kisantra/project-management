<?php

namespace Tests\Feature;

use App\Livewire\TaxReport\Ppn\BulkInvoiceJsonImport;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\TaxReport;
use App\Models\User;
use App\Services\InvoiceImportFileBag;
use App\Services\InvoiceJsonImportService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use RuntimeException;
use Tests\TestCase;

class InvoiceJsonImportTest extends TestCase
{
    use DatabaseTransactions;

    private TaxReport $report;

    private InvoiceJsonImportService $service;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        Storage::fake('local'); // Livewire's temporary upload disk

        $this->actingAs(User::forceCreate([
            'name' => 'Uji Import JSON',
            'email' => 'uji-import-json-' . uniqid() . '@example.test',
            'password' => bcrypt('rahasia'),
        ]));

        $client = Client::forceCreate([
            'name' => 'CV. UJI IMPORT JSON',
            'NPWP' => '01.234.567.8-901.000',
            'ppn_contract' => true,
        ]);

        $this->report = TaxReport::forceCreate(['client_id' => $client->id, 'month' => 'February', 'year' => 2025]);
        $this->service = new InvoiceJsonImportService();
    }

    private function payload(): string
    {
        return json_encode(['masa' => '2025-02', 'faktur' => [
            [
                'invoice_number' => '99999900000000001',
                'invoice_date' => '2025-02-04',
                'type' => 'keluaran',
                'company_name' => 'PT PEMBELI UJI',
                'npwp' => '0011111111111000',
                'dpp' => 10000000,
                'ppn' => 1100000,
                'file' => 'keluaran.pdf',
            ],
            [
                // No "file": matched by the safe invoice number, UJI_IN_0001.pdf.
                'invoice_number' => 'UJI/IN/0001',
                'invoice_date' => '2025-02-05',
                'type' => 'masukan',
                'company_name' => 'PT PENJUAL UJI',
                'npwp' => '0022222222222000',
                'dpp' => 7774500,
                'dpp_nilai_lainnya' => 7126625,
                'ppn' => 855195,
            ],
        ]]);
    }

    private function uploads(bool $withMasukan = true): array
    {
        $uploads = [UploadedFile::fake()->createWithContent('keluaran.pdf', "%PDF-1.4\n% keluaran")];

        if ($withMasukan) {
            $uploads[] = UploadedFile::fake()->createWithContent('UJI_IN_0001.pdf', "%PDF-1.4\n% masukan");
        }

        return $uploads;
    }

    /**
     * @return array<int, array> rows ready to import
     */
    private function analyzedRows(): array
    {
        $files = InvoiceImportFileBag::fromUploads($this->uploads());
        $analysis = $this->service->analyze(
            $this->service->parse($this->payload()),
            $this->service->contextFor($this->report->load('client')),
            $files->names(),
            []
        );

        $this->assertSame(2, $analysis['summary']['saveable'], json_encode($analysis['rows']));

        return $analysis['rows'];
    }

    public function test_import_saves_invoices_and_files_and_recalculates_ppn(): void
    {
        $saved = $this->service->import($this->report, $this->analyzedRows(), InvoiceImportFileBag::fromUploads($this->uploads()));

        $this->assertSame(2, $saved);

        $keluaran = Invoice::where('invoice_number', '99999900000000001')->firstOrFail();
        $this->assertSame('Faktur Keluaran', $keluaran->type);
        $this->assertSame('11', $keluaran->ppn_percentage);
        $this->assertSame($this->report->id, $keluaran->tax_report_id);
        $this->assertSame(auth()->id(), $keluaran->created_by);
        $this->assertSame("%PDF-1.4\n% keluaran", Storage::disk('public')->get($keluaran->file_path));

        $masukan = Invoice::where('invoice_number', 'UJI/IN/0001')->firstOrFail();
        $this->assertSame('Faktur Masuk', $masukan->type);
        $this->assertSame('12', $masukan->ppn_percentage);
        $this->assertEquals(7126625, $masukan->dpp_nilai_lainnya);
        $this->assertSame("%PDF-1.4\n% masukan", Storage::disk('public')->get($masukan->file_path));

        $summary = $this->report->taxCalculationSummaries()->where('tax_type', 'ppn')->firstOrFail();
        $this->assertEquals(1100000, $summary->pajak_keluar);
        $this->assertEquals(855195, $summary->pajak_masuk);
    }

    public function test_a_failure_midway_keeps_neither_rows_nor_files(): void
    {
        $rows = $this->analyzedRows();

        // The masukan file disappears between review and save.
        try {
            $this->service->import($this->report, $rows, InvoiceImportFileBag::fromUploads($this->uploads(withMasukan: false)));
            $this->fail('Import seharusnya gagal.');
        } catch (RuntimeException) {
            // expected
        }

        $this->assertSame(0, Invoice::where('tax_report_id', $this->report->id)->count());
        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    public function test_component_goes_from_json_and_files_to_saved_invoices(): void
    {
        Livewire::test(BulkInvoiceJsonImport::class, ['taxReportId' => $this->report->id])
            ->assertSee('Salin prompt')
            ->set('json', "```json\n" . $this->payload() . "\n```")
            ->set('newUploads', $this->uploads())
            ->assertCount('uploads', 2)
            ->call('analyze')
            ->assertSet('step', 'review')
            ->assertSet('analysis.summary.saveable', 2)
            ->assertSee('Simpan 2 faktur')
            ->call('save')
            ->assertDispatched('faktur-bulk-saved');

        $this->assertSame(2, Invoice::where('tax_report_id', $this->report->id)->count());
    }

    public function test_component_refuses_json_for_another_period(): void
    {
        Livewire::test(BulkInvoiceJsonImport::class, ['taxReportId' => $this->report->id])
            ->set('json', str_replace('"2025-02"', '"2025-03"', $this->payload()))
            ->set('newUploads', $this->uploads())
            ->call('analyze')
            ->assertSee('Tidak ada yang bisa disimpan')
            ->call('save')
            ->assertNotDispatched('faktur-bulk-saved');

        $this->assertSame(0, Invoice::where('tax_report_id', $this->report->id)->count());
    }
}
