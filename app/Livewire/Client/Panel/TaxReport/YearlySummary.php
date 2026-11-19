<?php

namespace App\Livewire\Client\Panel\TaxReport;

use App\Exports\TaxReport\PPN\YearlyTaxReportsExport;
use App\Exports\TaxReport\PPN\YearlyTaxReportsPdfExport;
use App\Models\TaxReport;
use App\Support\ClientPortal\ClientContext;
use App\Support\TaxPeriod;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Log;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Maatwebsite\Excel\Facades\Excel;

/**
 * Ringkasan Tahunan of the PPN workspace: one row per period of the report's
 * year (tax_reports.year, the same year the period navigation uses), from the
 * stored PPN summaries, plus the yearly Excel/PDF exports.
 */
class YearlySummary extends Component
{
    #[Locked]
    public $taxReportId;

    #[Locked]
    public $clientId;

    public $currentMonth;
    public $currentYear;
    public $clientName;

    /** $clientId is kept for the call signature; the company always comes from the report. */
    public function mount($taxReportId, $clientId = null): void
    {
        $report = TaxReport::with('client:id,name')->findOrFail($taxReportId);
        app(ClientContext::class)->authorize($report->client_id);

        $this->taxReportId = $report->id;
        $this->clientId = $report->client_id;
        $this->currentMonth = TaxPeriod::index($report->month);
        $this->currentYear = (int) ($report->year ?: $report->created_at?->year);
        $this->clientName = $report->client->name;
    }

    protected function verifyAccess(): void
    {
        app(ClientContext::class)->authorize($this->clientId);
    }

    public function getTimelineDataProperty(): array
    {
        $reports = TaxReport::query()
            ->where('client_id', $this->clientId)
            ->where('year', $this->currentYear)
            ->with(['taxCalculationSummaries' => fn ($query) => $query->where('tax_type', 'ppn')])
            ->get(['id', 'client_id', 'month', 'year'])
            ->sortBy(fn (TaxReport $report) => TaxPeriod::index($report->month))
            ->values();

        $timeline = $reports->map(function (TaxReport $report) {
            $month = TaxPeriod::index($report->month);

            return [
                'id' => $report->id,
                'month_name' => TaxPeriod::label($month, $this->currentYear),
                'ppn_summary' => $report->taxCalculationSummaries->first(),
                'is_current' => $month === $this->currentMonth,
            ];
        })->all();

        return [
            'timeline' => $timeline,
            'year' => $this->currentYear,
            'months_with_reports' => collect($timeline)->filter(fn ($item) => $item['ppn_summary'])->count(),
        ];
    }

    private function hasReports(): bool
    {
        $exists = TaxReport::where('client_id', $this->clientId)->where('year', $this->currentYear)->exists();

        if (! $exists) {
            Notification::make()
                ->title('Tidak Ada Data')
                ->body('Tidak ada laporan pajak untuk tahun ' . $this->currentYear)
                ->warning()
                ->send();
        }

        return $exists;
    }

    public function exportYearlyReport()
    {
        try {
            $this->verifyAccess();

            if (! $this->hasReports()) {
                return null;
            }

            $cleanClientName = trim(preg_replace('/_+/', '_', preg_replace('/[^A-Za-z0-9_\-]/', '_', $this->clientName)), '_');

            Notification::make()
                ->title('Ekspor Dimulai')
                ->body('Sedang menyiapkan file Excel...')
                ->info()
                ->send();

            return Excel::download(
                new YearlyTaxReportsExport($this->clientId, $this->currentYear),
                'Rekap_Tahunan_' . $cleanClientName . '_' . $this->currentYear . '.xlsx'
            );
        } catch (\Exception $e) {
            Notification::make()
                ->title('Ekspor Gagal')
                ->body('Terjadi kesalahan: ' . $e->getMessage())
                ->danger()
                ->send();

            Log::error('Yearly tax report export failed', [
                'client_id' => $this->clientId,
                'year' => $this->currentYear,
                'error' => $e->getMessage(),
            ]);
        }

        return null;
    }

    public function exportYearlyReportPdf()
    {
        try {
            $this->verifyAccess();

            if (! $this->hasReports()) {
                return null;
            }

            Notification::make()
                ->title('Ekspor Dimulai')
                ->body('Sedang menyiapkan file PDF...')
                ->info()
                ->send();

            return (new YearlyTaxReportsPdfExport($this->clientId, $this->currentYear))->download();
        } catch (\Exception $e) {
            Notification::make()
                ->title('Ekspor Gagal')
                ->body('Terjadi kesalahan: ' . $e->getMessage())
                ->danger()
                ->send();

            Log::error('Yearly tax report PDF export failed', [
                'client_id' => $this->clientId,
                'year' => $this->currentYear,
                'error' => $e->getMessage(),
            ]);
        }

        return null;
    }

    public function render()
    {
        return view('livewire.client.panel.tax-report.yearly-summary', [
            'timelineData' => $this->timelineData,
        ]);
    }
}
