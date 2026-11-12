<?php

namespace App\Livewire\TaxReport\Ppn;

use App\Models\TaxCalculationSummary;
use App\Models\TaxReport;
use App\Services\TaxReport\PpnBreakdown;
use App\Support\TaxPeriod;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * PPN workspace of the tax report page: invoices, the calculation, compensation,
 * the yearly summary and the SPT. The calculation uses PpnBreakdown, the same
 * rules as the stored summary, and includes manual compensation, so it always
 * matches the Kompensasi tab, the dashboard and the list.
 */
class TaxReportInvoices extends Component
{
    /** Sub-tabs, as they appear in ?tab= */
    public const TABS = ['faktur', 'perhitungan', 'kompensasi', 'tahunan', 'spt'];

    #[Locked]
    public int $taxReportId;

    /** Sub-tab open on first render (Alpine takes over afterwards). */
    public string $initialTab = 'faktur';

    public function mount(int $taxReportId): void
    {
        $this->taxReportId = $taxReportId;

        $tab = (string) request()->query('tab', '');
        $this->initialTab = request()->query('jenis', 'ppn') === 'ppn' && in_array($tab, self::TABS, true) ? $tab : 'faktur';
    }

    #[Computed]
    public function taxReport(): TaxReport
    {
        return TaxReport::with('client')->findOrFail($this->taxReportId);
    }

    #[Computed]
    public function summary(): ?TaxCalculationSummary
    {
        return $this->taxReport->taxCalculationSummaries()->where('tax_type', 'ppn')->first();
    }

    #[Computed]
    public function breakdown(): PpnBreakdown
    {
        return PpnBreakdown::for($this->taxReport);
    }

    /** Keluaran − masukan − kompensasi diterima − kompensasi manual = saldo akhir. */
    #[Computed]
    public function ledger(): array
    {
        $keluar = $this->breakdown->ppnKeluar();
        $masuk = $this->breakdown->ppnMasuk();
        $selisih = $keluar - $masuk;
        $received = (float) $this->taxReport->approvedCompensationsReceived()->where('tax_type', 'ppn')->sum('amount_compensated');
        $given = (float) $this->taxReport->approvedCompensationsGiven()->where('tax_type', 'ppn')->sum('amount_compensated');
        $manual = (float) ($this->summary?->manual_kompensasi ?? 0);
        $saldo = $selisih - $received - $manual;

        return [
            'keluar' => $keluar,
            'masuk' => $masuk,
            'selisih' => $selisih,
            'selisihStatus' => self::status($selisih),
            'received' => $received,
            'manual' => $manual,
            'manualNotes' => $this->summary?->manual_kompensasi_notes,
            'saldo' => $saldo,
            'status' => self::status($saldo),
            'given' => $given,
            // Lebih bayar still free to carry to the next period.
            'available' => $saldo < 0 ? max(0, abs($saldo) - $given) : 0,
        ];
    }

    public static function status(float $amount): string
    {
        return $amount > 0 ? 'Kurang Bayar' : ($amount < 0 ? 'Lebih Bayar' : 'Nihil');
    }

    #[Computed]
    public function invoiceCount(): int
    {
        return $this->taxReport->invoices()->count();
    }

    #[Computed]
    public function revisionCount(): int
    {
        return $this->taxReport->revisionInvoices()->count();
    }

    #[Computed]
    public function sptUploaded(): bool
    {
        return (bool) $this->summary?->bukti_lapor;
    }

    public function periodLabel(): string
    {
        return TaxPeriod::label($this->taxReport->month, $this->taxReport->year ?? $this->taxReport->created_at->year);
    }

    /** Computed values are rebuilt on every request, so a re-render is all a refresh needs. */
    #[On('tax-report-changed')]
    #[On('compensationUpdated')]
    #[On('spt-updated')]
    #[On('invoiceCreated')]
    #[On('invoiceUpdated')]
    #[On('invoiceDeleted')]
    public function refreshData(): void
    {
    }

    public function render(): View
    {
        return view('livewire.tax-report.ppn.tax-report-invoices');
    }
}
