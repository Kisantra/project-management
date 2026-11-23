<?php

namespace App\Exports\TaxReport\PPN;

use App\Models\TaxReport;
use App\Services\TaxReport\PpnBreakdown;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Collection;

class TaxReportInvoicesPdfExport
{
    protected $taxReport;
    protected $selectedInvoiceIds;
    protected $isSelectionMode;

    public function __construct(TaxReport $taxReport, ?array $selectedInvoiceIds = null)
    {
        $this->taxReport = $taxReport;
        $this->selectedInvoiceIds = $selectedInvoiceIds;
        $this->isSelectionMode = !empty($selectedInvoiceIds);
    }

    /**
     * Get display values for invoice (0 if revised, actual values if latest)
     */
    private function getDisplayValues($invoice): array
    {
        $hasRevisions = $invoice->revisions()->exists();
        
        if ($hasRevisions && !$invoice->is_revision) {
            return [
                'dpp_nilai_lainnya' => 0,
                'dpp' => 0,
                'ppn' => 0,
                'is_revised' => true,
                'is_excluded_code' => false
            ];
        } else {
            // Faktur Keluaran 02, 03, 07, 08 and Faktur Masuk 07, 08 (see PpnBreakdown)
            $isExcludedCode = PpnBreakdown::isExcludedCode($invoice->type, $invoice->invoice_number);
            
            return [
                'dpp_nilai_lainnya' => $invoice->dpp_nilai_lainnya ?? 0,
                'dpp' => $invoice->dpp,
                'ppn' => $isExcludedCode ? 0 : $invoice->ppn,
                'is_revised' => false,
                'is_excluded_code' => $isExcludedCode
            ];
        }
    }

    /**
     * Generate the PDF and return Http response
     */
    public function download()
    {
        $data = $this->prepareData();
        
        $pdf = Pdf::loadView('exports.tax-report-invoices-pdf', $data);
        $pdf->setPaper('a4', 'landscape');
        $pdf->setOptions([
            'dpi' => 96,
            'defaultFont' => 'sans-serif',
            'isHtml5ParserEnabled' => true,
            'isRemoteEnabled' => true,
        ]);

        $filename = $this->generateFilename();
        
        return response()->streamDownload(function () use ($pdf) {
            echo $pdf->stream();
        }, $filename);
    }

    /**
     * Generate filename for download
     */
    private function generateFilename(): string
    {
        $clientName = preg_replace('/[^A-Za-z0-9_\-]/', '_', $this->taxReport->client->name ?? 'Unknown');
        $clientName = preg_replace('/_+/', '_', $clientName);
        $clientName = trim($clientName, '_');
        
        $monthYear = $this->getIndonesianMonth($this->taxReport->month) . '_' . date('Y');
        $prefix = $this->isSelectionMode ? 'Terpilih_' : '';
        
        return 'Rekap_Faktur_' . $prefix . $clientName . '_' . $monthYear . '.pdf';
    }

    /**
     * Prepare data for the PDF view
     */
    private function prepareData(): array
    {
        $baseQuery = $this->taxReport->invoices();
        
        if ($this->isSelectionMode) {
            $baseQuery->whereIn('id', $this->selectedInvoiceIds);
        }

        $fakturKeluaran = (clone $baseQuery)->where('type', 'Faktur Keluaran')
            ->orderBy('invoice_date')
            ->get();

        $fakturMasukan = (clone $baseQuery)->where('type', 'Faktur Masuk')
            ->orderBy('invoice_date')
            ->get();

        $fakturKeluaranRows = [];
        $totalDppNilaiLainnyaKeluaran = 0;
        $totalDppKeluaran = 0;
        $totalPpnKeluaran = 0;

        foreach ($fakturKeluaran as $index => $invoice) {
            $displayValues = $this->getDisplayValues($invoice);
            
            $keterangan = [];
            if ($displayValues['is_revised']) {
                $keterangan[] = 'Direvisi';
            }
            if ($displayValues['is_excluded_code']) {
                $keterangan[] = 'Kode Dikecualikan';
            }
            if (!$invoice->is_business_related) {
                $keterangan[] = 'Tidak Terkait Bisnis';
            }
            
            $fakturKeluaranRows[] = [
                'no' => $index + 1,
                'company_name' => $invoice->company_name,
                'invoice_number' => $invoice->invoice_number,
                'invoice_date' => date('d/m/Y', strtotime($invoice->invoice_date)),
                'dpp_nilai_lainnya' => $displayValues['dpp_nilai_lainnya'],
                'dpp' => $displayValues['dpp'],
                'ppn' => $displayValues['ppn'],
                'keterangan' => implode(' | ', $keterangan),
                'is_revised' => $displayValues['is_revised'],
                'is_excluded_code' => $displayValues['is_excluded_code'],
                'is_business_related' => $invoice->is_business_related,
            ];
            
            if (!$displayValues['is_revised'] && $invoice->is_business_related) {
                $totalDppNilaiLainnyaKeluaran += $displayValues['dpp_nilai_lainnya'];
                $totalDppKeluaran += $displayValues['dpp'];
                if (!$displayValues['is_excluded_code']) {
                    $totalPpnKeluaran += $displayValues['ppn'];
                }
            }
        }

        $fakturMasukanRows = [];
        $totalDppNilaiLainnyaMasukan = 0;
        $totalDppMasukan = 0;
        $totalPpnMasukan = 0;

        foreach ($fakturMasukan as $index => $invoice) {
            $displayValues = $this->getDisplayValues($invoice);
            
            $keterangan = [];
            if ($displayValues['is_revised']) {
                $keterangan[] = 'Direvisi';
            }
            if ($displayValues['is_excluded_code']) {
                $keterangan[] = 'Tidak Dapat Dikreditkan';
            }
            if (!$invoice->is_business_related) {
                $keterangan[] = 'Tidak Terkait Bisnis';
            }

            $fakturMasukanRows[] = [
                'no' => $index + 1,
                'company_name' => $invoice->company_name,
                'invoice_number' => $invoice->invoice_number,
                'invoice_date' => date('d/m/Y', strtotime($invoice->invoice_date)),
                'dpp_nilai_lainnya' => $displayValues['dpp_nilai_lainnya'],
                'dpp' => $displayValues['dpp'],
                'ppn' => $displayValues['ppn'],
                'keterangan' => implode(' | ', $keterangan),
                'is_revised' => $displayValues['is_revised'],
                'is_excluded_code' => $displayValues['is_excluded_code'],
                'is_business_related' => $invoice->is_business_related,
            ];
            
            if (!$displayValues['is_revised'] && $invoice->is_business_related) {
                $totalDppNilaiLainnyaMasukan += $displayValues['dpp_nilai_lainnya'];
                $totalDppMasukan += $displayValues['dpp'];
                $totalPpnMasukan += $displayValues['ppn'];
            }
        }

        $kurangLebihBayar = $totalPpnKeluaran - $totalPpnMasukan;
        $ppnDikompensasi = $this->isSelectionMode ? 0 : ($this->taxReport->ppn_dikompensasi_dari_masa_sebelumnya ?? 0);
        $finalAmount = $kurangLebihBayar - $ppnDikompensasi;

        if ($finalAmount > 0) {
            $status = 'KURANG BAYAR';
            $statusColor = '#DC2626';
        } elseif ($finalAmount < 0) {
            $status = 'LEBIH BAYAR';
            $statusColor = '#16A34A';
        } else {
            $status = 'NIHIL';
            $statusColor = '#F59E0B';
        }

        return [
            'clientName' => strtoupper($this->taxReport->client->name ?? 'UNKNOWN CLIENT'),
            'monthYear' => strtoupper($this->getIndonesianMonth($this->taxReport->month)) . ' ' . date('Y'),
            'isSelectionMode' => $this->isSelectionMode,
            'fakturKeluaran' => $fakturKeluaranRows,
            'fakturMasukan' => $fakturMasukanRows,
            'totals' => [
                'keluaran' => [
                    'dpp_nilai_lainnya' => $totalDppNilaiLainnyaKeluaran,
                    'dpp' => $totalDppKeluaran,
                    'ppn' => $totalPpnKeluaran,
                ],
                'masukan' => [
                    'dpp_nilai_lainnya' => $totalDppNilaiLainnyaMasukan,
                    'dpp' => $totalDppMasukan,
                    'ppn' => $totalPpnMasukan,
                ],
            ],
            'summary' => [
                'ppn_keluaran' => $totalPpnKeluaran,
                'ppn_masukan' => $totalPpnMasukan,
                'ppn_kompensasi' => $ppnDikompensasi,
                'final_amount' => $finalAmount,
                'status' => $status,
                'status_color' => $statusColor,
            ],
            'selectionInfo' => $this->isSelectionMode ? [
                'selected' => count($this->selectedInvoiceIds),
                'total' => $this->taxReport->invoices()->count(),
            ] : null,
        ];
    }

    /**
     * Get Indonesian month name
     */
    private function getIndonesianMonth($month): string
    {
        $monthNames = [
            '01' => 'Januari', '1' => 'Januari', 'january' => 'Januari',
            '02' => 'Februari', '2' => 'Februari', 'february' => 'Februari',
            '03' => 'Maret', '3' => 'Maret', 'march' => 'Maret',
            '04' => 'April', '4' => 'April', 'april' => 'April',
            '05' => 'Mei', '5' => 'Mei', 'may' => 'Mei',
            '06' => 'Juni', '6' => 'Juni', 'june' => 'Juni',
            '07' => 'Juli', '7' => 'Juli', 'july' => 'Juli',
            '08' => 'Agustus', '8' => 'Agustus', 'august' => 'Agustus',
            '09' => 'September', '9' => 'September', 'september' => 'September',
            '10' => 'Oktober', 'october' => 'Oktober',
            '11' => 'November', 'november' => 'November',
            '12' => 'Desember', 'december' => 'Desember',
        ];

        $cleanMonth = strtolower(trim($month));

        if (preg_match('/\d{4}-(\d{1,2})/', $month, $matches)) {
            $cleanMonth = $matches[1];
        }

        return $monthNames[$cleanMonth] ?? 'Unknown';
    }
}
