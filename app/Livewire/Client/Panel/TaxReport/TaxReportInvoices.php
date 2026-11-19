<?php

namespace App\Livewire\Client\Panel\TaxReport;

use App\Models\TaxReport;
use App\Services\TaxReport\PpnBreakdown;
use App\Support\ClientPortal\ClientContext;
use App\Support\TaxPeriod;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * PPN workspace of one tax period in the client portal: the summary strip,
 * then Daftar Pajak (InvoiceTable), Kalkulasi and Ringkasan Tahunan.
 *
 * Figures come from PpnBreakdown, the one PPN rule shared with the stored
 * summary and the admin screens (codes 02/03/07/08 left out, business-related
 * Faktur Masuk only, latest revision of each invoice). The parent keys this
 * component by report id, so another period mounts a fresh instance.
 */
class TaxReportInvoices extends Component
{
    #[Locked]
    public $taxReportId;

    #[Locked]
    public $taxReport;

    public $ppnMasuk = 0;
    public $ppnKeluar = 0;
    public $fakturMasukCount = 0;
    public $fakturKeluarCount = 0;
    public $fakturKeluarExcludedCount = 0;
    public $fakturMasukExcludedCount = 0;
    public $totalDpp = 0;
    public $totalInvoices = 0;

    public $kompensasiDiterima = 0;
    public $kompensasiTersedia = 0;
    public $kompensasiTerpakai = 0;
    public $saldoFinal = 0;
    public $statusFinal = 'Nihil';

    protected $listeners = [
        'refresh-data' => 'refreshData',
    ];

    public function mount($taxReportId): void
    {
        $this->taxReportId = $taxReportId;
        $this->refreshData();
    }

    public function refreshData(): void
    {
        $report = TaxReport::with('client:id,name')->findOrFail($this->taxReportId);
        app(ClientContext::class)->authorize($report->client_id);

        $this->taxReport = $report;
        $this->loadSummary();
    }

    protected function loadSummary(): void
    {
        $ppn = PpnBreakdown::for($this->taxReport);

        $this->ppnKeluar = $ppn->ppnKeluar();
        $this->ppnMasuk = $ppn->ppnMasuk();
        $this->totalDpp = $ppn->dppKeluar();
        $this->fakturKeluarCount = $ppn->keluar->count();
        $this->fakturMasukCount = $ppn->masuk->count();
        $this->fakturKeluarExcludedCount = $ppn->excludedKeluar;
        $this->fakturMasukExcludedCount = $ppn->excludedMasuk;
        $this->totalInvoices = $this->taxReport->invoices()->count();

        $this->kompensasiDiterima = (float) $this->taxReport->approvedCompensationsReceived()->where('tax_type', 'ppn')->sum('amount_compensated');
        $this->kompensasiTerpakai = (float) $this->taxReport->approvedCompensationsGiven()->where('tax_type', 'ppn')->sum('amount_compensated');

        $this->saldoFinal = $this->ppnKeluar - $this->ppnMasuk - $this->kompensasiDiterima;
        $this->statusFinal = self::statusOf($this->saldoFinal);
        $this->kompensasiTersedia = $this->saldoFinal < 0 ? abs($this->saldoFinal) - $this->kompensasiTerpakai : 0;
    }

    /** Kurang Bayar / Lebih Bayar / Nihil for a balance (keluar minus masuk). */
    public static function statusOf(float $balance): string
    {
        return $balance > 0 ? 'Kurang Bayar' : ($balance < 0 ? 'Lebih Bayar' : 'Nihil');
    }

    public function getPeriodNameProperty(): string
    {
        return TaxPeriod::label($this->taxReport->month, $this->taxReport->year ?: $this->taxReport->created_at?->year);
    }

    public function render()
    {
        return view('livewire.client.panel.tax-report.tax-report-invoices');
    }
}
