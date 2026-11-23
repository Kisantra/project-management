<?php
// app/Exports/TaxReport/PPN/YearlySummarySheet.php

namespace App\Exports\TaxReport\PPN;

use App\Models\TaxReport;
use App\Services\TaxReport\PpnBreakdown;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class YearlySummarySheet implements FromArray, WithStyles, WithColumnWidths, WithTitle
{
    protected $clientId;
    protected $year;
    protected $clientName;
    protected $monthlyData = [];

    public function __construct($clientId, $year, $clientName)
    {
        $this->clientId = $clientId;
        $this->year = $year;
        $this->clientName = $clientName;
        $this->calculateMonthlyData();
    }

    /**
     * Get display values for invoice (0 if revised, actual values if latest)
     * Same logic as TaxReportInvoicesExport
     */
    private function getDisplayValues($invoice)
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
     * Calculate monthly data using the EXACT same rules as TaxReportInvoicesExport
     */
    private function calculateMonthlyData()
    {
        $taxReports = TaxReport::where('client_id', $this->clientId)
            ->where('year', $this->year)
            ->orderByRaw("FIELD(month, 'January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December')")
            ->get();

        $months = [
            'January', 'February', 'March', 'April', 'May', 'June',
            'July', 'August', 'September', 'October', 'November', 'December'
        ];

        foreach ($months as $month) {
            $report = $taxReports->firstWhere('month', $month);
            
            if ($report) {
                $this->monthlyData[] = $this->calculateMonthReport($report, $month);
            } else {
                $this->monthlyData[] = [
                    'month' => $month,
                    'has_report' => false,
                    'ppn_masuk' => 0,
                    'ppn_keluar' => 0,
                    'dpp_nilai_lainnya' => 0,
                    'dpp' => 0,
                    'peredaran_bruto' => 0,
                    'selisih' => 0,
                    'kompensasi' => 0,
                    'saldo_final' => 0,
                    'status' => '-',
                ];
            }
        }
    }

    /**
     * Calculate report data following EXACT same rules as TaxReportInvoicesExport
     */
    private function calculateMonthReport($report, $month)
    {
        $allInvoices = $report->invoices()->orderBy('invoice_date')->get();

        // FAKTUR KELUARAN - Same logic as TaxReportInvoicesExport
        $fakturKeluaranAll = $allInvoices->where('type', 'Faktur Keluaran');
        
        $totalDppNilaiLainnyaKeluaran = 0;
        $totalDppKeluaran = 0;
        $totalPpnKeluaran = 0;

        foreach ($fakturKeluaranAll as $invoice) {
            $displayValues = $this->getDisplayValues($invoice);
            
            // Same calculation logic as TaxReportInvoicesExport
            if (!$displayValues['is_revised'] && $invoice->is_business_related) {
                $totalDppNilaiLainnyaKeluaran += $displayValues['dpp_nilai_lainnya'];
                $totalDppKeluaran += $displayValues['dpp'];
                
                // PPN only counted if not excluded code
                if (!$displayValues['is_excluded_code']) {
                    $totalPpnKeluaran += $displayValues['ppn'];
                }
            }
        }

        // FAKTUR MASUKAN - Same logic as TaxReportInvoicesExport
        $fakturMasukanAll = $allInvoices->where('type', 'Faktur Masuk');
        
        $totalDppNilaiLainnyaMasukan = 0;
        $totalDppMasukan = 0;
        $totalPpnMasukan = 0;

        foreach ($fakturMasukanAll as $invoice) {
            $displayValues = $this->getDisplayValues($invoice);
            
            // Same calculation logic as TaxReportInvoicesExport
            if (!$displayValues['is_revised'] && $invoice->is_business_related) {
                $totalDppNilaiLainnyaMasukan += $displayValues['dpp_nilai_lainnya'];
                $totalDppMasukan += $displayValues['dpp'];
                $totalPpnMasukan += $displayValues['ppn'];
            }
        }

        // Calculate totals
        $peredaranBruto = $totalDppKeluaran + $totalDppNilaiLainnyaKeluaran;
        $selisih = $totalPpnKeluaran - $totalPpnMasukan;
        
        // Get kompensasi from report (same as TaxReportInvoicesExport uses ppn_dikompensasi_dari_masa_sebelumnya)
        $kompensasi = $report->ppn_dikompensasi_dari_masa_sebelumnya ?? 0;
        $saldoFinal = $selisih - $kompensasi;

        // Determine status
        if ($saldoFinal > 0) {
            $status = 'Kurang Bayar';
        } elseif ($saldoFinal < 0) {
            $status = 'Lebih Bayar';
        } else {
            $status = 'Nihil';
        }

        return [
            'month' => $month,
            'has_report' => true,
            'ppn_masuk' => $totalPpnMasukan,
            'ppn_keluar' => $totalPpnKeluaran,
            'dpp_nilai_lainnya' => $totalDppNilaiLainnyaKeluaran,
            'dpp' => $totalDppKeluaran,
            'peredaran_bruto' => $peredaranBruto,
            'selisih' => $selisih,
            'kompensasi' => $kompensasi,
            'saldo_final' => $saldoFinal,
            'status' => $status,
        ];
    }

    public function array(): array
    {
        $data = [];

        // Title - matching TaxReportInvoicesExport format
        $data[] = ['', '', '', '', '', '', '', '', '']; // Empty row 1
        $data[] = ['', '', '', '', '', '', '', '', '']; // Empty row 2
        $data[] = ['', 'RINGKASAN TAHUNAN LAPORAN PPN ' . strtoupper($this->clientName) . ' - TAHUN ' . $this->year, '', '', '', '', '', '', ''];
        $data[] = ['', '', '', '', '', '', '', '', '']; // Empty row

        // Section header
        $data[] = ['', 'RINGKASAN BULANAN', '', '', '', '', '', '', ''];

        // Headers - start at column B
        $data[] = [
            '', 
            'Bulan', 
            'PPN Masuk', 
            'PPN Keluar', 
            'DPP',
            'DPP Nilai Lainnya',
            'Peredaran Bruto', 
            'Selisih',
            'Kompensasi Diterima'
        ];

        // Monthly data rows
        $yearlyPpnMasuk = 0;
        $yearlyPpnKeluar = 0;
        $yearlyDpp = 0;
        $yearlyDppNilaiLainnya = 0;
        $yearlyPeredaranBruto = 0;
        $yearlySelisih = 0;
        $yearlyKompensasi = 0;
        $yearlySaldoFinal = 0;

        foreach ($this->monthlyData as $monthData) {
            if ($monthData['has_report']) {
                $yearlyPpnMasuk += $monthData['ppn_masuk'];
                $yearlyPpnKeluar += $monthData['ppn_keluar'];
                $yearlyDpp += $monthData['dpp'];
                $yearlyDppNilaiLainnya += $monthData['dpp_nilai_lainnya'];
                $yearlyPeredaranBruto += $monthData['peredaran_bruto'];
                $yearlySelisih += $monthData['selisih'];
                $yearlyKompensasi += $monthData['kompensasi'];
                $yearlySaldoFinal += $monthData['saldo_final'];

                $data[] = [
                    '',
                    $this->getIndonesianMonth($monthData['month']),
                    'Rp ' . number_format($monthData['ppn_masuk'], 0, ',', '.'),
                    'Rp ' . number_format($monthData['ppn_keluar'], 0, ',', '.'),
                    'Rp ' . number_format($monthData['dpp'], 0, ',', '.'),
                    'Rp ' . number_format($monthData['dpp_nilai_lainnya'], 0, ',', '.'),
                    'Rp ' . number_format($monthData['peredaran_bruto'], 0, ',', '.'),
                    'Rp ' . number_format($monthData['selisih'], 0, ',', '.'),
                    'Rp ' . number_format($monthData['kompensasi'], 0, ',', '.')
                ];
            } else {
                $data[] = [
                    '',
                    $this->getIndonesianMonth($monthData['month']),
                    '-',
                    '-',
                    '-',
                    '-',
                    '-',
                    '-',
                    '-'
                ];
            }
        }

        // JUMLAH row - matching TaxReportInvoicesExport format
        $data[] = [
            '',
            'JUMLAH',
            'Rp ' . number_format($yearlyPpnMasuk, 0, ',', '.'),
            'Rp ' . number_format($yearlyPpnKeluar, 0, ',', '.'),
            'Rp ' . number_format($yearlyDpp, 0, ',', '.'),
            'Rp ' . number_format($yearlyDppNilaiLainnya, 0, ',', '.'),
            'Rp ' . number_format($yearlyPeredaranBruto, 0, ',', '.'),
            'Rp ' . number_format($yearlySelisih, 0, ',', '.'),
            'Rp ' . number_format($yearlyKompensasi, 0, ',', '.')
        ];

        // Add spacing
        $data[] = ['', '', '', '', '', '', '', '', ''];
        $data[] = ['', '', '', '', '', '', '', '', ''];

        // REKAP section - matching TaxReportInvoicesExport format
        $data[] = ['', 'REKAP KURANG ATAU LEBIH BAYAR PAJAK TAHUNAN', '', '', '', '', '', '', ''];

        $data[] = ['', 'TOTAL PPN FAKTUR KELUARAN', '', '', '', '', '', '', 'Rp ' . number_format($yearlyPpnKeluar, 0, ',', '.')];
        $data[] = ['', 'TOTAL PPN FAKTUR MASUKAN', '', '', '', '', '', '', 'Rp ' . number_format($yearlyPpnMasuk, 0, ',', '.')];
        $data[] = ['', 'TOTAL KOMPENSASI DITERIMA', '', '', '', '', '', '', 'Rp ' . number_format($yearlyKompensasi, 0, ',', '.')];
        $data[] = ['', 'TOTAL KURANG/ LEBIH BAYAR PAJAK', '', '', '', '', '', '', 'Rp ' . number_format(abs($yearlySaldoFinal), 0, ',', '.')];

        // Status - matching TaxReportInvoicesExport format
        if ($yearlySaldoFinal > 0) {
            $status = 'KURANG BAYAR';
        } elseif ($yearlySaldoFinal < 0) {
            $status = 'LEBIH BAYAR';
        } else {
            $status = 'NIHIL';
        }

        $data[] = ['', $status, '', '', '', '', '', '', ''];

        return $data;
    }

    public function styles(Worksheet $sheet)
    {
        // Title styling - matching TaxReportInvoicesExport
        $sheet->mergeCells('B3:I3');
        $sheet->getStyle('B3')->applyFromArray([
            'font' => ['bold' => true, 'size' => 14],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
        ]);

        // Section header (row 5) - matching TaxReportInvoicesExport blue header
        $sheet->mergeCells('B5:I5');
        $sheet->getStyle('B5:I5')->applyFromArray([
            'font' => ['bold' => true, 'size' => 12, 'color' => ['rgb' => 'FFFFFF']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'color' => ['rgb' => '4472C4']
            ],
            'borders' => [
                'allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '000000']]
            ]
        ]);

        // Headers row (row 6) - matching TaxReportInvoicesExport gray header
        $sheet->getStyle('B6:I6')->applyFromArray([
            'font' => ['bold' => true, 'size' => 11],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'color' => ['rgb' => 'E8E8E8']
            ],
            'borders' => [
                'allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '000000']]
            ]
        ]);

        // Data rows (7 to 18 - 12 months)
        $sheet->getStyle('B7:I18')->applyFromArray([
            'borders' => [
                'allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '000000']]
            ],
            'alignment' => [
                'vertical' => Alignment::VERTICAL_CENTER
            ]
        ]);

        // Align month names to left
        $sheet->getStyle('B7:B18')->applyFromArray([
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT]
        ]);

        // Align numbers to right
        $sheet->getStyle('C7:I18')->applyFromArray([
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_RIGHT]
        ]);

        // JUMLAH row (row 19) - matching TaxReportInvoicesExport blue total row
        $sheet->getStyle('B19:I19')->applyFromArray([
            'font' => ['bold' => true, 'size' => 11, 'color' => ['rgb' => 'FFFFFF']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'color' => ['rgb' => '4472C4']
            ],
            'borders' => [
                'allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '000000']]
            ]
        ]);

        // Align JUMLAH amounts to right
        $sheet->getStyle('C19:I19')->applyFromArray([
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_RIGHT]
        ]);

        // REKAP section header (row 22) - matching TaxReportInvoicesExport
        $sheet->mergeCells('B22:I22');
        $sheet->getStyle('B22:I22')->applyFromArray([
            'font' => ['bold' => true, 'size' => 12, 'color' => ['rgb' => 'FFFFFF']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'color' => ['rgb' => '4472C4']
            ],
            'borders' => [
                'allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '000000']]
            ]
        ]);

        // REKAP data rows (23-26) - matching TaxReportInvoicesExport
        $sheet->getStyle('B23:I26')->applyFromArray([
            'borders' => [
                'allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '000000']]
            ],
            'alignment' => [
                'vertical' => Alignment::VERTICAL_CENTER
            ]
        ]);

        // Merge B to H for labels, I for values
        for ($row = 23; $row <= 26; $row++) {
            $sheet->mergeCells("B{$row}:H{$row}");
            $sheet->getStyle("B{$row}:H{$row}")->applyFromArray([
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT, 'vertical' => Alignment::VERTICAL_CENTER],
                'font' => ['bold' => true]
            ]);
            
            $sheet->getStyle("I{$row}")->applyFromArray([
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_RIGHT, 'vertical' => Alignment::VERTICAL_CENTER],
                'font' => ['bold' => true]
            ]);
        }

        // Status row (row 27) - matching TaxReportInvoicesExport with color coding
        $sheet->mergeCells('B27:I27');

        // Calculate final amount for color determination
        $finalAmount = 0;
        foreach ($this->monthlyData as $monthData) {
            if ($monthData['has_report']) {
                $finalAmount += $monthData['saldo_final'];
            }
        }

        if ($finalAmount > 0) {
            $textColor = 'FF0000'; // Red
        } elseif ($finalAmount < 0) {
            $textColor = '008000'; // Green
        } else {
            $textColor = 'FF8C00'; // Orange
        }

        $sheet->getStyle('B27:I27')->applyFromArray([
            'font' => ['bold' => true, 'size' => 14, 'color' => ['rgb' => $textColor]],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
            'borders' => [
                'allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '000000']]
            ]
        ]);

        // Set row heights - matching TaxReportInvoicesExport
        $sheet->getRowDimension(5)->setRowHeight(25);
        $sheet->getRowDimension(6)->setRowHeight(20);
        $sheet->getRowDimension(19)->setRowHeight(18);
        $sheet->getRowDimension(22)->setRowHeight(25);
        $sheet->getRowDimension(27)->setRowHeight(30);

        return [];
    }

    public function columnWidths(): array
    {
        // Matching TaxReportInvoicesExport column widths
        return [
            'A' => 3,
            'B' => 15,
            'C' => 20,
            'D' => 20,
            'E' => 20,
            'F' => 20,
            'G' => 20,
            'H' => 20,
            'I' => 25,
        ];
    }

    public function title(): string
    {
        return 'Ringkasan_' . $this->year;
    }

    private function getIndonesianMonth($month): string
    {
        $monthNames = [
            'January' => 'Januari',
            'February' => 'Februari',
            'March' => 'Maret',
            'April' => 'April',
            'May' => 'Mei',
            'June' => 'Juni',
            'July' => 'Juli',
            'August' => 'Agustus',
            'September' => 'September',
            'October' => 'Oktober',
            'November' => 'November',
            'December' => 'Desember',
        ];

        return $monthNames[$month] ?? $month;
    }
}   