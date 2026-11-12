<?php

namespace App\Observers;

use App\Models\Invoice;

class InvoiceObserver
{
    /**
     * Handle the Invoice "created" event.
     */
    public function created(Invoice $invoice): void
    {
        $this->updateTaxReportStatus($invoice);
    }

    /**
     * Handle the Invoice "updated" event.
     */
    public function updated(Invoice $invoice): void
    {
        $this->updateTaxReportStatus($invoice);
        
        // If tax_report_id changed, update both old and new tax reports
        if ($invoice->isDirty('tax_report_id') && $invoice->getOriginal('tax_report_id')) {
            $oldTaxReport = \App\Models\TaxReport::find($invoice->getOriginal('tax_report_id'));
            if ($oldTaxReport) {
                $this->calculateAndUpdateStatus($oldTaxReport);
            }
        }
    }

    /**
     * Handle the Invoice "deleted" event.
     */
    public function deleted(Invoice $invoice): void
    {
        $this->updateTaxReportStatus($invoice);
    }

    /**
     * Update tax report status based on invoice changes
     */
    private function updateTaxReportStatus(Invoice $invoice): void
    {
        if ($invoice->taxReport) {
            $this->calculateAndUpdateStatus($invoice->taxReport);
        }
    }

    /**
     * Recalculate the stored PPN summary. TaxCalculationSummary::recalculate() is the
     * only place with the PPN rules (PpnBreakdown); this observer used to carry an
     * older copy that overwrote it after every invoice change.
     */
    private function calculateAndUpdateStatus($taxReport): void
    {
        try {
            $taxReport->taxCalculationSummaries()
                ->firstOrCreate(
                    ['tax_type' => 'ppn'],
                    [
                        'pajak_masuk' => 0,
                        'pajak_keluar' => 0,
                        'selisih' => 0,
                        'status' => 'Nihil',
                        'kompensasi_diterima' => 0,
                        'kompensasi_tersedia' => 0,
                        'kompensasi_terpakai' => 0,
                        'manual_kompensasi' => 0,
                        'manual_kompensasi_notes' => null,
                        'saldo_final' => 0,
                        'status_final' => 'Nihil',
                        'report_status' => 'Belum Lapor',
                    ]
                )
                ->recalculate();
        } catch (\Exception $e) {
            // Log error but don't break the invoice operation
            \Log::error('Failed to update tax report summary for invoice change', [
                'tax_report_id' => $taxReport->id,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Determine status based on amount
     */
    private function determineStatus(float $amount): string
    {
        if ($amount > 0) {
            return 'Kurang Bayar';
        } elseif ($amount < 0) {
            return 'Lebih Bayar';
        }
        return 'Nihil';
    }
}