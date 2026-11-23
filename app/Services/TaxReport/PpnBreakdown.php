<?php

namespace App\Services\TaxReport;

use App\Models\Invoice;
use App\Models\TaxReport;
use Illuminate\Support\Collection;

/**
 * Which invoices of a tax report count toward PPN, shared by the stored
 * summary (TaxCalculationSummary::calculatePpn) and the PPN screens so both
 * always show the same numbers:
 * - Faktur Keluaran with code 02, 03, 07 or 08 are left out;
 * - Faktur Masuk is credited only when it is business related and its code
 *   is not 07 or 08;
 * - an invoice with revisions counts with its latest revision.
 */
final class PpnBreakdown
{
    /** Faktur Keluaran whose PPN the buyer collects (02, 03) or nobody collects (07, 08). */
    public const EXCLUDED_PREFIXES = ['02', '03', '07', '08'];

    /** Faktur Masuk the buyer paid no PPN on (07 tidak dipungut/DTP, 08 dibebaskan), so nothing to credit. */
    public const NON_CREDITABLE_PREFIXES = ['07', '08'];

    /**
     * @param Collection<int, Invoice> $keluar counted Faktur Keluaran (latest versions)
     * @param Collection<int, Invoice> $masuk credited Faktur Masuk (latest versions)
     */
    private function __construct(
        public readonly Collection $keluar,
        public readonly Collection $masuk,
        public readonly int $excludedKeluar,
        public readonly int $excludedMasuk,
    ) {
    }

    public static function for(TaxReport $report): self
    {
        $originals = $report->originalInvoices()
            ->with(['revisions' => fn ($query) => $query->orderByDesc('revision_number')])
            ->get();

        $latest = fn (Invoice $invoice) => $invoice->revisions->first() ?? $invoice;
        $keluar = $originals->where('type', 'Faktur Keluaran');
        $masuk = $originals->where('type', 'Faktur Masuk');
        $excluded = fn (Invoice $invoice) => self::isExcludedCode($invoice->type, $invoice->invoice_number);
        $credited = $masuk->filter(fn (Invoice $invoice) => (bool) $invoice->is_business_related && !$excluded($invoice));

        return new self(
            $keluar->reject($excluded)->map($latest)->values(),
            $credited->map($latest)->values(),
            $keluar->filter($excluded)->count(),
            $masuk->count() - $credited->count(),
        );
    }

    /** Whether the transaction code (first two digits) keeps this invoice's PPN out. */
    public static function isExcludedCode(?string $type, ?string $invoiceNumber): bool
    {
        $prefixes = match ($type) {
            'Faktur Keluaran' => self::EXCLUDED_PREFIXES,
            'Faktur Masuk' => self::NON_CREDITABLE_PREFIXES,
            default => [],
        };

        return in_array(substr((string) $invoiceNumber, 0, 2), $prefixes, true);
    }

    public function ppnKeluar(): float
    {
        return (float) $this->keluar->sum('ppn');
    }

    public function ppnMasuk(): float
    {
        return (float) $this->masuk->sum('ppn');
    }

    /** Peredaran bruto: DPP of the counted Faktur Keluaran. */
    public function dppKeluar(): float
    {
        return (float) $this->keluar->sum('dpp');
    }
}
