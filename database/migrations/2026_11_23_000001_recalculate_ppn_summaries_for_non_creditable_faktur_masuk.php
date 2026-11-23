<?php

use App\Models\TaxCalculationSummary;
use App\Services\TaxReport\PpnBreakdown;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Faktur Masuk with code 07 or 08 are no longer credited (see PpnBreakdown).
 * Recalculate the stored PPN summary of unreported periods holding such
 * invoices so lists, the yearly summary and compensation match the PPN screen.
 * Reported periods keep the figures they were filed with.
 */
return new class extends Migration
{
    public function up(): void
    {
        $reportIds = DB::table('invoices')
            ->where('type', 'Faktur Masuk')
            ->whereIn(DB::raw('LEFT(invoice_number, 2)'), PpnBreakdown::NON_CREDITABLE_PREFIXES)
            ->distinct()
            ->pluck('tax_report_id');

        TaxCalculationSummary::where('tax_type', 'ppn')
            ->where('report_status', 'Belum Lapor')
            ->whereIn('tax_report_id', $reportIds)
            ->each(fn (TaxCalculationSummary $summary) => $summary->recalculate());
    }

    public function down(): void
    {
        // The figures follow the rule in code; there is nothing to restore.
    }
};
