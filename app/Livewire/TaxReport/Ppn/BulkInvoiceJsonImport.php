<?php

namespace App\Livewire\TaxReport\Ppn;

use App\Models\Invoice;
use App\Models\TaxReport;
use App\Services\InvoiceImportFileBag;
use App\Services\InvoiceJsonImportService;
use Filament\Notifications\Notification;
use Illuminate\Database\QueryException;
use InvalidArgumentException;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\WithFileUploads;
use RuntimeException;

/**
 * Imports a batch of invoices from AI-generated JSON plus their files.
 * Rendered inside the "Import JSON" modal of InvoiceTable.
 */
class BulkInvoiceJsonImport extends Component
{
    use WithFileUploads;

    #[Locked]
    public int $taxReportId;

    public string $json = '';

    /** @var array<int, \Livewire\Features\SupportFileUploads\TemporaryUploadedFile> */
    public array $uploads = [];

    /** Bound to the file input; merged into $uploads so picking files twice adds instead of replacing. */
    public array $newUploads = [];

    public string $step = 'input'; // input | review

    /** Display copy of the last analysis. save() recomputes it instead of trusting this. */
    public array $analysis = [];

    public ?string $inputError = null;

    public function mount(int $taxReportId): void
    {
        $this->taxReportId = $taxReportId;
    }

    public function updatedNewUploads(): void
    {
        $this->validate(
            ['newUploads.*' => 'file|mimes:pdf,jpg,jpeg,png,zip|max:12288'],
            [
                'newUploads.*.mimes' => 'Hanya PDF, JPG, PNG, atau ZIP yang diterima.',
                'newUploads.*.max' => 'Ukuran file maksimal 12 MB. Pecah ZIP menjadi beberapa bagian.',
            ]
        );

        foreach ($this->newUploads as $file) {
            $name = mb_strtolower($file->getClientOriginalName());
            $this->uploads = array_values(array_filter(
                $this->uploads,
                fn ($existing) => mb_strtolower($existing->getClientOriginalName()) !== $name
            ));
            $this->uploads[] = $file;
        }

        $this->newUploads = [];
    }

    public function removeUpload(int $index): void
    {
        unset($this->uploads[$index]);
        $this->uploads = array_values($this->uploads);
    }

    public function analyze(): void
    {
        $result = $this->runAnalysis();

        if ($result !== null) {
            $this->analysis = $result[0];
            $this->step = 'review';
        }
    }

    public function backToInput(): void
    {
        $this->step = 'input';
    }

    public function save(): void
    {
        $result = $this->runAnalysis();

        if ($result === null) {
            return;
        }

        [$analysis, $files] = $result;
        $this->analysis = $analysis;

        $rows = array_values(array_filter(
            $analysis['rows'],
            fn (array $row) => $row['status'] !== InvoiceJsonImportService::STATUS_ERROR
        ));

        // The review step already explains why nothing can be saved.
        if ($analysis['global_errors'] !== [] || $rows === []) {
            return;
        }

        @set_time_limit(300);

        try {
            $count = app(InvoiceJsonImportService::class)->import($this->taxReport(), $rows, $files);
        } catch (\Throwable $e) {
            report($e);

            Notification::make()
                ->danger()
                ->title('Import gagal, tidak ada faktur yang disimpan')
                ->body($this->friendlyError($e))
                ->persistent()
                ->send();

            return;
        }

        Notification::make()
            ->success()
            ->title("{$count} faktur berhasil diimpor")
            ->send();

        $this->dispatch('faktur-bulk-saved');
    }

    /**
     * @return array{0: array, 1: InvoiceImportFileBag}|null
     */
    private function runAnalysis(): ?array
    {
        $this->inputError = null;
        $taxReport = $this->taxReport();

        if (! $taxReport->client?->ppn_contract) {
            $this->inputError = 'Klien tidak memiliki kontrak PPN aktif.';
            $this->step = 'input';

            return null;
        }

        $service = app(InvoiceJsonImportService::class);

        try {
            $parsed = $service->parse($this->json);
            $files = InvoiceImportFileBag::fromUploads($this->uploads);
        } catch (InvalidArgumentException|RuntimeException $e) {
            $this->inputError = $e->getMessage();
            $this->step = 'input';

            return null;
        }

        $numbers = collect($parsed['rows'])
            ->map(fn ($row) => is_array($row) && is_string($row['invoice_number'] ?? null) ? trim($row['invoice_number']) : '')
            ->filter()
            ->unique()
            ->values()
            ->all();

        $existing = $numbers === []
            ? []
            : Invoice::whereIn('invoice_number', $numbers)->pluck('invoice_number')->all();

        $analysis = $service->analyze(
            $parsed,
            $service->contextFor($taxReport),
            $files->names(),
            $existing,
            $files->duplicates()
        );

        return [$analysis, $files];
    }

    private function taxReport(): TaxReport
    {
        return TaxReport::with('client')->findOrFail($this->taxReportId);
    }

    private function friendlyError(\Throwable $e): string
    {
        if ($e instanceof QueryException) {
            if (str_contains($e->getMessage(), 'Duplicate entry') && preg_match("/Duplicate entry '([^']+)'/", $e->getMessage(), $m)) {
                return "Nomor faktur {$m[1]} sudah terdaftar di sistem. Periksa ulang lalu coba lagi.";
            }

            return 'Terjadi kesalahan database saat menyimpan. Coba lagi atau hubungi admin.';
        }

        return $e instanceof RuntimeException
            ? $e->getMessage()
            : 'Terjadi kesalahan tak terduga. Coba lagi atau hubungi admin.';
    }

    public function render()
    {
        $taxReport = $this->taxReport();
        $service = app(InvoiceJsonImportService::class);
        $context = $service->contextFor($taxReport);

        return view('livewire.tax-report.ppn.bulk-invoice-json-import', [
            'prompt' => $this->step === 'input' ? $service->buildPrompt($taxReport) : '',
            'periodLabel' => InvoiceJsonImportService::periodLabel($context['month'], $context['year']),
            'clientName' => $context['client_name'],
        ]);
    }
}
