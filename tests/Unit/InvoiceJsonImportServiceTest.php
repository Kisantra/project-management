<?php

namespace Tests\Unit;

use App\Models\Client;
use App\Models\TaxReport;
use App\Services\InvoiceJsonImportService;
use InvalidArgumentException;
use Tests\TestCase;

class InvoiceJsonImportServiceTest extends TestCase
{
    private InvoiceJsonImportService $service;

    private array $context = [
        'month' => 2,
        'year' => 2025,
        'client_name' => 'CV. Mulia Makmur',
        'client_npwp' => '01.234.567.8-901.000',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new InvoiceJsonImportService();
    }

    private function row(array $overrides = []): array
    {
        return array_merge([
            'invoice_number' => '04002500064522168',
            'invoice_date' => '2025-02-04',
            'type' => 'keluaran',
            'company_name' => 'PELAYARAN DUTA LINTAS SAMUDERA',
            'npwp' => '0024355034728000',
            'dpp' => 1390000,
            'dpp_nilai_lainnya' => 0,
            'ppn' => 152900,
            'notes' => null,
            'file' => '04002500064522168.pdf',
        ], $overrides);
    }

    private function analyze(array $rows, array $files = ['04002500064522168.pdf'], array $existing = [], ?string $masa = '2025-02'): array
    {
        return $this->service->analyze(['masa' => $masa, 'rows' => $rows], $this->context, $files, $existing);
    }

    public function test_parse_strips_code_fences_and_surrounding_text(): void
    {
        $raw = "Berikut hasilnya:\n```json\n{\"masa\": \"2025-02\", \"faktur\": [{\"invoice_number\": \"1\"}]}\n```\nSemoga membantu.";

        $parsed = $this->service->parse($raw);

        $this->assertSame('2025-02', $parsed['masa']);
        $this->assertCount(1, $parsed['rows']);
    }

    public function test_parse_finds_json_inside_prose_without_fences(): void
    {
        $parsed = $this->service->parse('Ini datanya: [{"invoice_number": "1"}] selesai.');

        $this->assertNull($parsed['masa']);
        $this->assertCount(1, $parsed['rows']);
    }

    public function test_parse_rejects_bad_input(): void
    {
        foreach (['', 'bukan json', '{"faktur": []}', '{"data": [1]}', '{"faktur": {"invoice_number": "1"}}'] as $raw) {
            try {
                $this->service->parse($raw);
                $this->fail("Input diterima padahal seharusnya ditolak: {$raw}");
            } catch (InvalidArgumentException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function test_parse_limits_batch_size(): void
    {
        $rows = array_fill(0, InvoiceJsonImportService::MAX_ROWS + 1, ['invoice_number' => '1']);

        $this->expectException(InvalidArgumentException::class);
        $this->service->parse(json_encode(['faktur' => $rows]));
    }

    public function test_valid_row_is_ready_and_normalized(): void
    {
        $result = $this->analyze([$this->row()]);
        $row = $result['rows'][0];

        $this->assertSame(InvoiceJsonImportService::STATUS_OK, $row['status'], implode(' ', $row['errors'] + $row['warnings']));
        $this->assertSame('Faktur Keluaran', $row['data']['type']);
        $this->assertSame('11', $row['data']['ppn_percentage']);
        $this->assertSame('04002500064522168.pdf', $row['file']);
        $this->assertSame(1, $result['summary']['saveable']);
        $this->assertSame(1, $result['summary']['keluaran']['count']);
        $this->assertEquals(152900, $result['summary']['keluaran']['ppn']);
    }

    public function test_dpp_nilai_lain_sets_twelve_percent_without_warning(): void
    {
        $result = $this->analyze([$this->row(['dpp' => 7774500, 'dpp_nilai_lainnya' => 7126625, 'ppn' => 855195])]);

        $this->assertSame(InvoiceJsonImportService::STATUS_OK, $result['rows'][0]['status']);
        $this->assertSame('12', $result['rows'][0]['data']['ppn_percentage']);
    }

    public function test_ppn_that_does_not_match_dpp_is_a_warning(): void
    {
        $row = $this->analyze([$this->row(['ppn' => 999999])])['rows'][0];

        $this->assertSame(InvoiceJsonImportService::STATUS_WARNING, $row['status']);
        $this->assertStringContainsString('berbeda dari hitungan sistem', $row['warnings'][0]);
    }

    public function test_file_defaults_to_safe_invoice_number(): void
    {
        $row = $this->analyze(
            [$this->row(['invoice_number' => '060225/IN/MM/001', 'type' => 'masukan', 'file' => null])],
            ['060225_IN_MM_001.PDF']
        )['rows'][0];

        $this->assertSame('060225_IN_MM_001.PDF', $row['file']);
        $this->assertSame('Faktur Masuk', $row['data']['type']);
    }

    public function test_missing_file_fails_and_suggests_near_miss(): void
    {
        $row = $this->analyze([$this->row()], ['04002500064522168 (1).pdf'])['rows'][0];

        $this->assertSame(InvoiceJsonImportService::STATUS_ERROR, $row['status']);
        $this->assertStringContainsString('04002500064522168 (1).pdf', $row['errors'][0]);
    }

    public function test_numeric_invoice_number_is_rejected(): void
    {
        $row = $this->analyze([$this->row(['invoice_number' => 4002500064522168])])['rows'][0];

        $this->assertSame(InvoiceJsonImportService::STATUS_ERROR, $row['status']);
        $this->assertStringContainsString('tanda kutip', $row['errors'][0]);
    }

    public function test_duplicates_in_json_and_in_database_fail(): void
    {
        $result = $this->analyze(
            [$this->row(), $this->row(), $this->row(['invoice_number' => '04002500000000009', 'file' => '04002500064522168.pdf'])],
            ['04002500064522168.pdf'],
            ['04002500000000009']
        );

        $this->assertSame(InvoiceJsonImportService::STATUS_ERROR, $result['rows'][0]['status']);
        $this->assertStringContainsString('baris 2', $result['rows'][0]['errors'][0]);
        $this->assertSame(InvoiceJsonImportService::STATUS_ERROR, $result['rows'][1]['status']);
        $this->assertStringContainsString('sudah terdaftar', implode(' ', $result['rows'][2]['errors']));
        // Failed rows will not be saved, so they do not "share" the file with anyone.
        $this->assertSame([], $result['rows'][2]['warnings']);
        $this->assertSame([], $result['unused_files']);
        $this->assertSame(0, $result['summary']['saveable']);
    }

    public function test_shared_file_is_a_warning_and_unused_files_are_listed(): void
    {
        $result = $this->analyze(
            [$this->row(), $this->row(['invoice_number' => '04002500064522169'])],
            ['04002500064522168.pdf', 'lain.pdf']
        );

        $this->assertSame(InvoiceJsonImportService::STATUS_WARNING, $result['rows'][1]['status']);
        $this->assertStringContainsString('juga dipakai oleh baris 1', $result['rows'][1]['warnings'][0]);
        $this->assertSame(['lain.pdf'], $result['unused_files']);
        $this->assertSame(2, $result['summary']['saveable']);
    }

    public function test_wrong_period_blocks_everything(): void
    {
        $result = $this->analyze([$this->row()], masa: '2025-03');

        $this->assertNotEmpty($result['global_errors']);
        $this->assertSame(0, $result['summary']['saveable']);
    }

    public function test_date_outside_period_is_a_warning(): void
    {
        $row = $this->analyze([$this->row(['invoice_date' => '28-01-2025', 'type' => 'masukan'])])['rows'][0];

        $this->assertSame(InvoiceJsonImportService::STATUS_WARNING, $row['status']);
        $this->assertSame('2025-01-28', $row['data']['invoice_date']);
        $this->assertStringContainsString('dikreditkan di masa lain', $row['warnings'][0]);
    }

    public function test_client_as_counterparty_means_type_is_reversed(): void
    {
        // 16-digit NPWP with a leading zero is the same taxpayer as the 15-digit one.
        $byNpwp = $this->analyze([$this->row(['npwp' => '0012345678901000'])])['rows'][0];
        $byName = $this->analyze([$this->row(['company_name' => 'MULIA MAKMUR, CV', 'npwp' => null])])['rows'][0];

        $this->assertSame(InvoiceJsonImportService::STATUS_ERROR, $byNpwp['status']);
        $this->assertStringContainsString('terbalik', $byNpwp['errors'][0]);
        $this->assertSame(InvoiceJsonImportService::STATUS_ERROR, $byName['status']);
    }

    public function test_amount_and_date_parsers(): void
    {
        $this->assertSame(1390000.0, InvoiceJsonImportService::parseAmount('1.390.000'));
        $this->assertSame(1390000.5, InvoiceJsonImportService::parseAmount('Rp 1.390.000,50'));
        $this->assertSame(1390000.0, InvoiceJsonImportService::parseAmount(1390000));
        $this->assertSame(152.5, InvoiceJsonImportService::parseAmount('152.5'));
        $this->assertNull(InvoiceJsonImportService::parseAmount('seratus'));

        $this->assertSame('2025-02-04', InvoiceJsonImportService::parseDate('2025-02-04'));
        $this->assertSame('2025-02-04', InvoiceJsonImportService::parseDate('04-02-2025'));
        $this->assertSame('2025-02-04', InvoiceJsonImportService::parseDate('4/2/2025'));
        $this->assertSame('2025-02-04', InvoiceJsonImportService::parseDate('2025-02-04T00:00:00+07:00'));
        $this->assertNull(InvoiceJsonImportService::parseDate('2025-02-30'));
    }

    public function test_safe_file_name_matches_pajak_downloader(): void
    {
        $this->assertSame('060225_IN_MM_001', InvoiceJsonImportService::safeFileName('060225/IN/MM/001'));
        $this->assertSame('ABC_123', InvoiceJsonImportService::safeFileName('/ABC 123/'));
        $this->assertSame('unnamed', InvoiceJsonImportService::safeFileName('///'));
    }

    public function test_prompt_carries_the_report_context(): void
    {
        $client = new Client();
        $client->forceFill(['name' => 'CV. MULIA MAKMUR', 'NPWP' => '01.234.567.8-901.000']);
        $report = new TaxReport();
        $report->forceFill(['month' => 'February', 'year' => '2025']);
        $report->setRelation('client', $client);

        $prompt = $this->service->buildPrompt($report);

        $this->assertStringContainsString('Klien: CV. MULIA MAKMUR', $prompt);
        $this->assertStringContainsString('NPWP klien: 01.234.567.8-901.000', $prompt);
        $this->assertStringContainsString('Masa pajak: Februari 2025', $prompt);
        $this->assertStringContainsString('"masa": "2025-02"', $prompt);
    }
}
