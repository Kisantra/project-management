<?php

namespace Database\Seeders;

use App\Models\PatchNote;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;

class PatchNoteSeeder extends Seeder
{
    /** Halaman Tax Management › Dashboard (App\Filament\Pages\DashboardTaxReport). */
    private const TAX_DASHBOARD = '/dashboard-tax-report';

    public function run(): void
    {
        PatchNote::updateOrCreate(
            ['version' => '1.5.0'],
            [
                'title'        => 'Peningkatan Modul Klien, Pajak & Proyek',
                'description'  => 'Beberapa penyempurnaan agar mengelola klien, faktur pajak, dan hasil proyek jadi lebih mudah.',
                'is_published' => true,
                'released_at'  => '2026-06-24',
                'created_by'   => User::query()->value('id'),
                'changes'      => [
                    // Fitur baru
                    [
                        'type' => 'feature',
                        'area' => 'Klien',
                        'text' => 'Saat menambah klien baru, kredensial Core Tax, DJP, dan Email bisa langsung diisi di form yang sama — tidak perlu buka menu lain.',
                    ],
                    [
                        'type' => 'feature',
                        'area' => 'Proyek',
                        'text' => 'Setelah proyek selesai, file hasil pengerjaan (deliverable) bisa langsung dikirim ke arsip Dokumen Klien.',
                    ],
                    [
                        'type' => 'feature',
                        'area' => 'Sistem',
                        'text' => 'Info pembaruan sistem kini tampil sebagai banner di halaman Dashboard (seperti yang Anda lihat sekarang) dan bisa ditutup.',
                    ],

                    // Peningkatan
                    [
                        'type' => 'improvement',
                        'area' => 'Klien',
                        'text' => 'Saat memilih PIC untuk klien Badan, pilihannya dikelompokkan: ambil dari daftar PIC, atau dari klien Pribadi yang sudah terdaftar sebagai PIC.',
                    ],
                    [
                        'type' => 'improvement',
                        'area' => 'Proyek',
                        'text' => 'Saat mengirim hasil proyek ke klien, file bisa langsung dipasang ke slot Dokumen Legal Wajib atau Persyaratan klien — bukan hanya jadi dokumen umum.',
                    ],
                    [
                        'type' => 'improvement',
                        'area' => 'Klien',
                        'text' => 'Halaman daftar Klien kini nyaman dibuka lewat tablet maupun ponsel; tampilannya otomatis menyesuaikan ukuran layar.',
                    ],

                    // Perbaikan
                    [
                        'type' => 'fix',
                        'area' => 'Pajak',
                        'text' => 'Nilai PPN di faktur sekarang boleh diketik manual. Tetap terisi otomatis, tapi bisa disesuaikan bila ada selisih pembulatan.',
                    ],
                ],
            ]
        );

        PatchNote::updateOrCreate(
            ['version' => '1.6.0'],
            [
                'title'        => 'Pelaporan SPT Masa & Penyempurnaan Modul Pajak',
                'description'  => 'Unggah SPT Masa dari Coretax di tiap jenis pajak, ringkasan SPT untuk klien, notifikasi otomatis, serta navigasi & filter laporan pajak yang lebih pintar.',
                'is_published' => true,
                'released_at'  => '2026-06-29',
                'created_by'   => User::query()->value('id'),
                'changes'      => [
                    // Fitur baru
                    [
                        'type' => 'feature',
                        'area' => 'Pajak',
                        'text' => 'Unggah SPT Masa: di tiap jenis pajak (PPN & PPh) kini ada tab "SPT" untuk mengunggah berkas SPT/BPE dari Coretax. Setelah diunggah, status masa otomatis menjadi Sudah Lapor & Sudah Bayar, lengkap dengan pratinjau dokumen.',
                    ],
                    [
                        'type' => 'feature',
                        'area' => 'Pajak',
                        'text' => 'Halaman klien menampilkan tabel "SPT Dilaporkan" — ringkasan seluruh SPT yang telah dilaporkan. Klik salah satu baris untuk membuka detail laporan masa tersebut.',
                    ],
                    [
                        'type' => 'feature',
                        'area' => 'Sistem',
                        'text' => 'Klien otomatis menerima notifikasi begitu SPT mereka diunggah & dilaporkan oleh tim.',
                    ],

                    // Peningkatan
                    [
                        'type' => 'improvement',
                        'area' => 'Pajak',
                        'text' => 'Navigasi bulan pada laporan pajak kini berwarna sesuai status pelaporan, dan hanya menghitung jenis pajak yang benar-benar dikontrak klien (berlaku di panel admin maupun klien).',
                    ],
                    [
                        'type' => 'improvement',
                        'area' => 'Pajak',
                        'text' => 'Daftar laporan pajak dilengkapi filter "Jenis Kontrak" untuk menyaring klien berdasarkan PPN, PPh, Bupot, atau PPh Badan.',
                    ],
                    [
                        'type' => 'improvement',
                        'area' => 'Pajak',
                        'text' => 'Tab PPh kini fokus pada PPh 21; jenis PPh lainnya (23 & 4(2)) akan dikelola di menu PPh Unifikasi.',
                    ],
                    [
                        'type' => 'improvement',
                        'area' => 'Pajak',
                        'text' => 'Tabel SPT pada halaman klien dilengkapi pencarian, filter (jenis & tahun), pengurutan kolom, dan pengelompokan (default per jenis SPT).',
                    ],
                ],
            ]
        );

        PatchNote::updateOrCreate(
            ['version' => '1.7.0'],
            [
                'title'        => 'Dashboard Laporan Pajak Baru',
                'description'  => 'Dashboard pajak dirombak agar menjawab satu pertanyaan lebih dulu: klien mana yang belum lapor dan tenggatnya sudah dekat. Ditambah beberapa perbaikan perhitungan yang membuat angkanya lebih tepat.',
                'is_published' => true,
                'released_at'  => '2026-07-20',
                'created_by'   => User::query()->value('id'),
                'changes'      => [
                    // Fitur baru
                    [
                        'type'         => 'feature',
                        'area'         => 'Pajak',
                        'title'        => 'Garis waktu tenggat',
                        'text'         => 'Satu bilah yang menunjukkan posisi hari ini terhadap batas lapor PPh 21 & Unifikasi (tgl 10), PPN (tgl 20), dan batas bayar (tgl 30), lengkap dengan jumlah klien yang masih tertunggak di tiap tenggat. Menggantikan kalender bulanan.',
                        'location'     => 'Tax Management › Dashboard › Panel Tenggat',
                        'location_url' => self::TAX_DASHBOARD,
                        'image_before' => $this->shot('1.7.0', 'deadline-before.webp'),
                        'image_after'  => $this->shot('1.7.0', 'deadline-after.webp'),
                    ],
                    [
                        'type'         => 'feature',
                        'area'         => 'Pajak',
                        'title'        => 'Daftar "Perlu ditindak"',
                        'text'         => 'Klien kini diurutkan berdasarkan tenggat terdekat, bukan berdasarkan besarnya omzet seperti daftar "Top Klien Belum Lapor" sebelumnya. Tiap baris bisa diklik langsung menuju laporan pajaknya.',
                        'location'     => 'Tax Management › Dashboard › Perlu ditindak',
                        'location_url' => self::TAX_DASHBOARD,
                        'image_before' => $this->shot('1.7.0', 'triage-before.webp'),
                        'image_after'  => $this->shot('1.7.0', 'triage-after.webp'),
                    ],
                    [
                        'type'         => 'feature',
                        'area'         => 'Pajak',
                        'title'        => 'Tombol "Sorot"',
                        'text'         => 'Menyaring daftar ke satu jenis tenggat saja; panel tenggat yang bersangkutan ikut tersorot. Pengingat ke seluruh project manager bisa dikirim dari situ (dengan konfirmasi terlebih dahulu).',
                        'location'     => 'Tax Management › Dashboard › Perlu ditindak › Sorot',
                        'location_url' => self::TAX_DASHBOARD,
                        'image_after'  => $this->shot('1.7.0', 'sorot-after.webp'),
                    ],
                    [
                        'type'         => 'feature',
                        'area'         => 'Pajak',
                        'title'        => 'Grafik saldo akhir',
                        'text'         => 'Saldo akhir 12 bulan terakhir dalam satu grafik. Klik salah satu bulan untuk langsung berpindah ke periode tersebut.',
                        'location'     => 'Tax Management › Dashboard › Saldo akhir',
                        'location_url' => self::TAX_DASHBOARD,
                        'image_after'  => $this->shot('1.7.0', 'saldo-after.webp'),
                    ],

                    // Peningkatan
                    [
                        'type'         => 'improvement',
                        'area'         => 'Pajak',
                        'title'        => 'Pemilih periode',
                        'text'         => 'Disederhanakan menjadi satu stepper bulan di pojok kanan atas, dan filter kini selalu terlihat. Rentang bebas seperti "kuartal ini" dihapus karena kewajiban pajak selalu dihitung per masa bulanan.',
                        'location'     => 'Tax Management › Dashboard › Pemilih periode',
                        'location_url' => self::TAX_DASHBOARD,
                        'image_before' => $this->shot('1.7.0', 'periode-before.webp'),
                        'image_after'  => $this->shot('1.7.0', 'periode-after.webp'),
                    ],
                    [
                        'type'         => 'improvement',
                        'area'         => 'Pajak',
                        'text'         => 'Filter dashboard (klien, jenis pajak, status lapor, status bayar) kini tersimpan di alamat halaman, sehingga tampilan yang sudah disaring bisa disimpan sebagai bookmark atau dibagikan ke rekan.',
                        'location'     => 'Tax Management › Dashboard › Filter',
                        'location_url' => self::TAX_DASHBOARD,
                    ],
                    [
                        'type'         => 'improvement',
                        'area'         => 'Pajak',
                        'text'         => 'Istilah diseragamkan: "Bupot" kini ditulis "PPh Unifikasi" dan "PPh" menjadi "PPh 21", mengikuti penamaan di modul Klien.',
                        'location'     => 'Tax Management › Dashboard',
                        'location_url' => self::TAX_DASHBOARD,
                    ],
                    [
                        'type'         => 'improvement',
                        'area'         => 'Sistem',
                        'text'         => 'Dashboard pajak lebih ringan dibuka. Pengambilan data daftar klien yang sebelumnya menjalankan belasan kueri sekarang cukup satu kali, berapa pun jumlah kliennya.',
                        'location'     => 'Tax Management › Dashboard',
                        'location_url' => self::TAX_DASHBOARD,
                    ],

                    // Perbaikan
                    [
                        'type'         => 'fix',
                        'area'         => 'Pajak',
                        'text'         => 'Angka pada dashboard sebelumnya menggabungkan masa dengan nama bulan yang sama dari tahun berbeda (misalnya Januari 2025 ikut terhitung di Januari 2026). Perhitungan kini memakai bulan sekaligus tahun.',
                        'location'     => 'Tax Management › Dashboard',
                        'location_url' => self::TAX_DASHBOARD,
                    ],
                    [
                        'type'         => 'fix',
                        'area'         => 'Pajak',
                        'text'         => 'Tenggat pembayaran tanggal 30 tidak pernah muncul pada bulan Februari. Tenggat kini otomatis menyesuaikan ke hari terakhir bulan tersebut.',
                        'location'     => 'Tax Management › Dashboard',
                        'location_url' => self::TAX_DASHBOARD,
                    ],
                    [
                        'type'         => 'fix',
                        'area'         => 'Pajak',
                        'text'         => 'Menyaring dashboard ke jenis pajak tertentu sebelumnya tetap menampilkan penanda "Bayar" milik jenis pajak lain. Penanda kini ikut menyesuaikan filter.',
                        'location'     => 'Tax Management › Dashboard',
                        'location_url' => self::TAX_DASHBOARD,
                    ],
                    [
                        'type'         => 'fix',
                        'area'         => 'Sistem',
                        'text'         => 'Kegagalan memuat data pada dashboard sebelumnya tampil sebagai daftar kosong, sehingga menyerupai kondisi "semua sudah beres". Kegagalan kini ditampilkan sebagai pesan yang jelas beserta tombol muat ulang.',
                        'location'     => 'Tax Management › Dashboard',
                        'location_url' => self::TAX_DASHBOARD,
                    ],
                    [
                        'type'         => 'fix',
                        'area'         => 'Sistem',
                        'text'         => 'Menu Dashboard pada Tax Management sebelumnya mengikuti hak akses modul Tugas Harian. Kini menggunakan hak akses miliknya sendiri.',
                        'location'     => 'Tax Management › Dashboard',
                        'location_url' => self::TAX_DASHBOARD,
                    ],
                ],
            ]
        );
    }

    /**
     * Salin screenshot bawaan seeder ke disk publik dan kembalikan path-nya.
     * File sumbernya ikut di-commit di database/seeders/patch-notes/{versi}/.
     */
    private function shot(string $version, string $file): string
    {
        $path = PatchNote::IMAGE_DIRECTORY . "/{$version}/{$file}";

        Storage::disk(PatchNote::IMAGE_DISK)->put($path, file_get_contents(__DIR__ . "/patch-notes/{$version}/{$file}"));

        return $path;
    }
}
