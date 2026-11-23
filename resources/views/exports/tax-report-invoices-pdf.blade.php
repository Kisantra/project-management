<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Rekap Faktur PPN</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: Arial, Helvetica, sans-serif;
            font-size: 6pt;
            line-height: 1.15;
            color: #333;
        }

        .container {
            padding: 6px 10px;
        }

        .title {
            text-align: center;
            font-size: 10pt;
            font-weight: bold;
            margin-bottom: 10px;
            padding: 5px 0;
        }

        .section-header {
            background-color: #4472C4;
            color: white;
            font-weight: bold;
            font-size: 7pt;
            text-align: center;
            padding: 3px 5px;
            margin-top: 8px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 6px;
        }

        th, td {
            border: 0.5pt solid #000;
            padding: 1.5px 2.5px;
            font-size: 5.5pt;
        }

        th {
            background-color: #E8E8E8;
            font-weight: bold;
            text-align: center;
            font-size: 6pt;
        }

        .col-no {
            width: 3%;
            text-align: center;
        }
        .col-company {
            width: 18%;
            text-align: left;
        }
        .col-invoice {
            width: 14%;
            text-align: left;
        }
        .col-date {
            width: 8%;
            text-align: center;
        }
        .col-money {
            width: 12%;
            text-align: right;
        }
        .col-notes {
            width: 15%;
            text-align: left;
            font-size: 5pt;
        }

        .jumlah-row {
            background-color: #4472C4;
            color: white;
            font-weight: bold;
        }
        .jumlah-row td {
            padding: 2.5px 3px;
            font-size: 6pt;
        }

        .row-revised {
            background-color: #F5F5F5;
            color: #999999;
        }
        .row-excluded {
            background-color: #E6F2FF;
        }
        .row-non-business {
            background-color: #FFF9E6;
        }

        .rekap-section {
            margin-top: 8px;
        }

        .rekap-table {
            width: 100%;
        }

        .rekap-table td {
            padding: 2.5px 4px;
            font-size: 6pt;
        }

        .rekap-label {
            font-weight: bold;
            text-align: left;
            width: 80%;
        }

        .rekap-value {
            text-align: right;
            font-weight: bold;
            width: 20%;
        }

        .status-row {
            text-align: center;
            font-size: 9pt;
            font-weight: bold;
            padding: 4px;
            margin-top: 2px;
        }

        .selection-info {
            text-align: center;
            font-size: 5.5pt;
            color: #666;
            margin-top: 5px;
            padding: 2px;
        }

        .legend {
            margin-top: 6px;
            font-size: 5pt;
            padding: 3px;
            border: 0.5pt solid #ddd;
            background-color: #fafafa;
        }
        .legend-title {
            font-weight: bold;
            margin-bottom: 1.5px;
        }
        .legend-item {
            display: inline-block;
            margin-right: 8px;
        }
        .legend-color {
            display: inline-block;
            width: 9px;
            height: 7px;
            border: 0.5pt solid #999;
            vertical-align: middle;
            margin-right: 1.5px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="title">
            {{ $isSelectionMode ? 'REKAP FAKTUR TERPILIH' : 'REKAP FAKTUR' }} {{ $clientName }} - {{ $monthYear }}
        </div>

        <div class="section-header">FAKTUR KELUARAN</div>
        <table>
            <thead>
                <tr>
                    <th class="col-no">No</th>
                    <th class="col-company">Nama Penjual</th>
                    <th class="col-invoice">Nomor Seri Faktur</th>
                    <th class="col-date">Tanggal</th>
                    <th class="col-money">DPP Nilai Lainnya</th>
                    <th class="col-money">DPP</th>
                    <th class="col-money">PPN</th>
                    <th class="col-notes">Keterangan</th>
                </tr>
            </thead>
            <tbody>
                @forelse($fakturKeluaran as $row)
                <tr class="@if($row['is_revised']) row-revised @elseif($row['is_excluded_code']) row-excluded @elseif(!$row['is_business_related']) row-non-business @endif">
                    <td class="col-no">{{ $row['no'] }}</td>
                    <td class="col-company">{{ $row['company_name'] }}</td>
                    <td class="col-invoice">{{ $row['invoice_number'] }}</td>
                    <td class="col-date">{{ $row['invoice_date'] }}</td>
                    <td class="col-money">Rp {{ number_format($row['dpp_nilai_lainnya'], 0, ',', '.') }}</td>
                    <td class="col-money">Rp {{ number_format($row['dpp'], 0, ',', '.') }}</td>
                    <td class="col-money">Rp {{ number_format($row['ppn'], 0, ',', '.') }}</td>
                    <td class="col-notes">{{ $row['keterangan'] }}</td>
                </tr>
                @empty
                <tr>
                    <td colspan="8" style="text-align: center; font-style: italic;">Tidak ada faktur keluaran</td>
                </tr>
                @endforelse
                <tr class="jumlah-row">
                    <td colspan="4" style="text-align: center;">JUMLAH</td>
                    <td class="col-money">Rp {{ number_format($totals['keluaran']['dpp_nilai_lainnya'], 0, ',', '.') }}</td>
                    <td class="col-money">Rp {{ number_format($totals['keluaran']['dpp'], 0, ',', '.') }}</td>
                    <td class="col-money">Rp {{ number_format($totals['keluaran']['ppn'], 0, ',', '.') }}</td>
                    <td></td>
                </tr>
            </tbody>
        </table>

        <div class="section-header">FAKTUR MASUKAN</div>
        <table>
            <thead>
                <tr>
                    <th class="col-no">No</th>
                    <th class="col-company">Nama Penjual</th>
                    <th class="col-invoice">Nomor Seri Faktur</th>
                    <th class="col-date">Tanggal</th>
                    <th class="col-money">DPP Nilai Lainnya</th>
                    <th class="col-money">DPP</th>
                    <th class="col-money">PPN</th>
                    <th class="col-notes">Keterangan</th>
                </tr>
            </thead>
            <tbody>
                @forelse($fakturMasukan as $row)
                <tr class="@if($row['is_revised']) row-revised @elseif($row['is_excluded_code']) row-excluded @elseif(!$row['is_business_related']) row-non-business @endif">
                    <td class="col-no">{{ $row['no'] }}</td>
                    <td class="col-company">{{ $row['company_name'] }}</td>
                    <td class="col-invoice">{{ $row['invoice_number'] }}</td>
                    <td class="col-date">{{ $row['invoice_date'] }}</td>
                    <td class="col-money">Rp {{ number_format($row['dpp_nilai_lainnya'], 0, ',', '.') }}</td>
                    <td class="col-money">Rp {{ number_format($row['dpp'], 0, ',', '.') }}</td>
                    <td class="col-money">Rp {{ number_format($row['ppn'], 0, ',', '.') }}</td>
                    <td class="col-notes">{{ $row['keterangan'] }}</td>
                </tr>
                @empty
                <tr>
                    <td colspan="8" style="text-align: center; font-style: italic;">Tidak ada faktur masukan</td>
                </tr>
                @endforelse
                <tr class="jumlah-row">
                    <td colspan="4" style="text-align: center;">JUMLAH</td>
                    <td class="col-money">Rp {{ number_format($totals['masukan']['dpp_nilai_lainnya'], 0, ',', '.') }}</td>
                    <td class="col-money">Rp {{ number_format($totals['masukan']['dpp'], 0, ',', '.') }}</td>
                    <td class="col-money">Rp {{ number_format($totals['masukan']['ppn'], 0, ',', '.') }}</td>
                    <td></td>
                </tr>
            </tbody>
        </table>

        <div class="rekap-section">
            <div class="section-header">REKAP KURANG ATAU LEBIH BAYAR PAJAK</div>
            <table class="rekap-table">
                <tbody>
                    <tr>
                        <td class="rekap-label">TOTAL PPN FAKTUR KELUARAN</td>
                        <td class="rekap-value">Rp {{ number_format($summary['ppn_keluaran'], 0, ',', '.') }}</td>
                    </tr>
                    <tr>
                        <td class="rekap-label">TOTAL PPN FAKTUR MASUKAN</td>
                        <td class="rekap-value">Rp {{ number_format($summary['ppn_masukan'], 0, ',', '.') }}</td>
                    </tr>
                    @if(!$isSelectionMode && $summary['ppn_kompensasi'] > 0)
                    <tr>
                        <td class="rekap-label">PPN DIKOMPENSASIKAN DARI MASA SEBELUMNYA</td>
                        <td class="rekap-value">Rp {{ number_format($summary['ppn_kompensasi'], 0, ',', '.') }}</td>
                    </tr>
                    @endif
                    <tr>
                        <td class="rekap-label">TOTAL KURANG/ LEBIH BAYAR PAJAK</td>
                        <td class="rekap-value">Rp {{ number_format(abs($summary['final_amount']), 0, ',', '.') }}</td>
                    </tr>
                </tbody>
            </table>
            
            <div class="status-row" style="color: {{ $summary['status_color'] }}; border: 1pt solid {{ $summary['status_color'] }};">
                {{ $summary['status'] }}
            </div>
        </div>

        @if($isSelectionMode && $selectionInfo)
        <div class="selection-info">
            DIPILIH: {{ $selectionInfo['selected'] }} dari {{ $selectionInfo['total'] }} faktur
        </div>
        @endif

        <div class="legend">
            <div class="legend-title">KETERANGAN WARNA:</div>
            <span class="legend-item">
                <span class="legend-color" style="background-color: #F5F5F5;"></span>Direvisi (nilai 0)
            </span>
            <span class="legend-item">
                <span class="legend-color" style="background-color: #E6F2FF;"></span>Kode Dikecualikan (keluaran 02, 03, 07, 08; masukan 07, 08)
            </span>
            <span class="legend-item">
                <span class="legend-color" style="background-color: #FFF9E6;"></span>Tidak Terkait Bisnis
            </span>
        </div>
    </div>
</body>
</html>
