<?php

namespace App\Livewire\TaxReport\Ppn;

use App\Filament\Resources\TaxReportResource;
use App\Models\TaxReport;
use App\Services\TaxReport\PpnBreakdown;
use App\Support\TaxPeriod;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;
use App\Exports\TaxReport\PPN\YearlyTaxReportsExport;
use Maatwebsite\Excel\Facades\Excel;
use Filament\Notifications\Notification;

class YearlySummary extends Component
{
    public $taxReportId;
    public $clientId;
    public $currentMonth;
    public $currentYear;
    public $clientName;

    public function mount($taxReportId, $clientId)
    {
        $this->taxReportId = $taxReportId;
        $this->clientId = $clientId;
        
        // Get current tax report to determine the month and year
        $currentReport = TaxReport::with('client')->find($taxReportId);
        $this->currentMonth = $currentReport->month;
        $this->currentYear = $currentReport->year ?? $currentReport->created_at->year;
        $this->clientName = $currentReport->client->name;
    }

    /**
     * Twelve months of the year with the stored PPN summary of each report,
     * the year totals and the points for the chart. Peredaran bruto comes from
     * PpnBreakdown (the summary has no such column).
     */
    #[Computed]
    public function year(): array
    {
        $reports = TaxReport::where('client_id', $this->clientId)
            ->where('year', $this->currentYear)
            ->with(['taxCalculationSummaries' => fn ($query) => $query->where('tax_type', 'ppn')])
            ->get()
            ->keyBy(fn (TaxReport $report) => TaxPeriod::index($report->month));

        $rows = [];
        $totals = ['kurang' => 0.0, 'kurangMonths' => 0, 'lebih' => 0.0, 'lebihMonths' => 0, 'bruto' => 0.0, 'reported' => 0, 'withReports' => 0];
        $chart = ['labels' => [], 'titles' => [], 'up' => [], 'down' => []];

        foreach (TaxPeriod::NAMES as $number => $name) {
            $report = $reports->get($number);
            $summary = $report?->taxCalculationSummaries->first();
            $saldo = (float) ($summary?->saldo_final ?? 0);
            $status = $summary?->status_final;

            if ($report) {
                $totals['withReports']++;
                $totals['bruto'] += PpnBreakdown::for($report)->dppKeluar();
            }
            if ($summary?->report_status === 'Sudah Lapor') {
                $totals['reported']++;
            }
            if ($status === 'Kurang Bayar') {
                $totals['kurang'] += abs($saldo);
                $totals['kurangMonths']++;
            } elseif ($status === 'Lebih Bayar') {
                $totals['lebih'] += abs($saldo);
                $totals['lebihMonths']++;
            }

            $rows[] = [
                'number' => $number,
                'name' => $name,
                'current' => $number === TaxPeriod::index($this->currentMonth),
                'url' => $report ? TaxReportResource::getUrl('view', ['record' => $report->id]) : null,
                'hasReport' => (bool) $report,
                'summary' => (bool) $summary,
                'masuk' => (float) ($summary?->pajak_masuk ?? 0),
                'keluar' => (float) ($summary?->pajak_keluar ?? 0),
                'received' => (float) ($summary?->kompensasi_diterima ?? 0) + (float) ($summary?->manual_kompensasi ?? 0),
                'given' => (float) ($summary?->kompensasi_terpakai ?? 0),
                'saldo' => $saldo,
                'status' => $status,
                'reported' => $summary?->report_status === 'Sudah Lapor',
                'reportedAt' => TaxPeriod::date($summary?->reported_at),
            ];

            $chart['labels'][] = TaxPeriod::SHORT[$number];
            $chart['titles'][] = TaxPeriod::label($number, $this->currentYear);
            $chart['up'][] = $status === 'Kurang Bayar' ? abs($saldo) : 0.0;
            $chart['down'][] = $status === 'Lebih Bayar' ? abs($saldo) : 0.0;
        }

        return ['rows' => $rows, 'totals' => $totals, 'chart' => $chart];
    }

    /** Computed values are rebuilt per request; re-rendering is the refresh. */
    #[On('tax-report-changed')]
    #[On('compensationUpdated')]
    #[On('spt-updated')]
    public function refreshYear(): void
    {
    }

    public function exportYearlyReport()
    {
        try {
            // Validate that there are reports to export
            $reportsCount = TaxReport::where('client_id', $this->clientId)
                ->where('year', $this->currentYear)
                ->count();

            if ($reportsCount === 0) {
                Notification::make()
                    ->title('Tidak Ada Data')
                    ->body('Tidak ada laporan pajak untuk tahun ' . $this->currentYear)
                    ->warning()
                    ->send();
                return;
            }

            // Clean client name for filename
            $cleanClientName = preg_replace('/[^A-Za-z0-9_\-]/', '_', $this->clientName);
            $cleanClientName = preg_replace('/_+/', '_', $cleanClientName);
            $cleanClientName = trim($cleanClientName, '_');

            $filename = 'Rekap_Tahunan_' . $cleanClientName . '_' . $this->currentYear . '.xlsx';

            Notification::make()
                ->title('Export Dimulai')
                ->body('Sedang menyiapkan file Excel...')
                ->info()
                ->send();

            return Excel::download(
                new YearlyTaxReportsExport($this->clientId, $this->currentYear),
                $filename
            );

        } catch (\Exception $e) {
            Notification::make()
                ->title('Export Gagal')
                ->body('Terjadi kesalahan: ' . $e->getMessage())
                ->danger()
                ->send();

            \Log::error('Yearly tax report export failed', [
                'client_id' => $this->clientId,
                'year' => $this->currentYear,
                'error' => $e->getMessage()
            ]);
        }
    }

    public function exportYearlyReportPdf()
    {
        try {
            // Validate that there are reports to export
            $reportsCount = TaxReport::where('client_id', $this->clientId)
                ->where('year', $this->currentYear)
                ->count();

            if ($reportsCount === 0) {
                Notification::make()
                    ->title('Tidak Ada Data')
                    ->body('Tidak ada laporan pajak untuk tahun ' . $this->currentYear)
                    ->warning()
                    ->send();
                return;
            }

            Notification::make()
                ->title('Export Dimulai')
                ->body('Sedang menyiapkan file PDF...')
                ->info()
                ->send();

            $exporter = new \App\Exports\TaxReport\PPN\YearlyTaxReportsPdfExport($this->clientId, $this->currentYear);
            return $exporter->download();

        } catch (\Exception $e) {
            Notification::make()
                ->title('Export Gagal')
                ->body('Terjadi kesalahan: ' . $e->getMessage())
                ->danger()
                ->send();

            \Log::error('Yearly tax report PDF export failed', [
                'client_id' => $this->clientId,
                'year' => $this->currentYear,
                'error' => $e->getMessage()
            ]);
        }
    }

    public function render()
    {
        return view('livewire.tax-report.ppn.yearly-summary');
    }
}