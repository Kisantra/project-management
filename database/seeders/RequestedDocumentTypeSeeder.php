<?php

namespace Database\Seeders;

use App\Models\RequestedDocumentType;
use Illuminate\Database\Seeder;

/**
 * Starter list of documents commonly requested from clients. Edit freely from
 * Master Data → Jenis Dokumen; this seeder only adds what is missing.
 */
class RequestedDocumentTypeSeeder extends Seeder
{
    public function run(): void
    {
        $list = [
            'keuangan' => [
                'Rekening koran seluruh rekening bank',
                'Laporan keuangan (neraca & laba rugi)',
                'Buku besar / general ledger',
                'Daftar aset tetap dan penyusutan',
                'Daftar piutang dan utang usaha',
                'Daftar persediaan akhir tahun',
                'Rekap penjualan bulanan',
                'Rekap pembelian bulanan',
                'Bukti pengeluaran kas / kwitansi',
                'Perjanjian pinjaman / kredit bank',
            ],
            'perpajakan' => [
                'Bukti potong PPh 21 / 1721-A1',
                'Bukti potong PPh 23',
                'Bukti potong PPh 22',
                'Bukti potong PPh 4 ayat (2)',
                'Faktur pajak keluaran',
                'Faktur pajak masukan',
                'SPT Masa PPN beserta bukti lapor',
                'SPT Masa PPh 21 beserta bukti lapor',
                'SPT Tahunan tahun sebelumnya',
                'Bukti setor pajak (NTPN) / SSP',
                'Surat Keterangan Terdaftar (SKT) / SPPKP',
                'Surat dari KPP (SP2DK, SPHP, STP, SKP)',
            ],
            'legal' => [
                'Akta pendirian dan perubahan terakhir',
                'SK Kemenkumham',
                'NIB / izin usaha (OSS)',
                'NPWP badan dan pengurus',
                'KTP pengurus dan pemegang saham',
                'Perjanjian / kontrak dengan pelanggan atau pemasok',
            ],
            'kepegawaian' => [
                'Daftar karyawan beserta gaji dan tunjangan',
                'Slip gaji / rekap payroll',
                'Bukti pembayaran BPJS Ketenagakerjaan dan Kesehatan',
                'Daftar pesangon / THR',
            ],
        ];

        foreach ($list as $category => $names) {
            foreach ($names as $i => $name) {
                RequestedDocumentType::firstOrCreate(
                    ['name' => $name],
                    ['category' => $category, 'sort_order' => $i + 1, 'is_active' => true],
                );
            }
        }
    }
}
