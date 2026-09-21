<?php

namespace Database\Seeders;

use App\Models\LetterTemplate;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Pustaka template Surat & Berita Acara.
 *
 * Redaksi di sini adalah DRAFT awal untuk ditinjau. Placeholder yang tersedia:
 *   {{klien}} {{nomor}} {{tanggal_surat}} {{proyek}} {{perusahaan}}
 *   plus setiap key isian yang didefinisikan pada 'fields'.
 * Paragraf dipisah baris kosong; baris berawalan "- " menjadi butir;
 * paragraf yang hanya berisi placeholder isian bertipe list menjadi daftar bernomor.
 */
class LetterTemplateSeeder extends Seeder
{
    private const MANAJER = ['label' => 'Manajer', 'roles' => ['project-manager']];
    private const DIREKTUR = ['label' => 'Direktur', 'roles' => ['direktur']];

    public function run(): void
    {
        $this->ensurePermission();
        $this->call(RequestedDocumentTypeSeeder::class);

        foreach ($this->templates() as $i => $template) {
            LetterTemplate::updateOrCreate(
                ['code' => $template['code']],
                $template + ['sort_order' => $i + 1, 'is_active' => true],
            );
        }
    }

    /** surat.* permission, granted to every internal role. */
    private function ensurePermission(): void
    {
        $permission = Permission::firstOrCreate(['name' => 'surat.*', 'guard_name' => 'web']);

        Role::whereIn('name', ['super-admin', 'direktur', 'project-manager', 'staff', 'verificator'])
            ->get()
            ->each(fn (Role $role) => $role->givePermissionTo($permission));
    }

    private function field(string $key, string $label, string $type = 'text', bool $required = true, array $extra = []): array
    {
        return ['key' => $key, 'label' => $label, 'type' => $type, 'required' => $required] + $extra;
    }

    private function templates(): array
    {
        $klienSignatory = $this->field('pihak_klien', 'Nama & jabatan wakil klien', 'text', true, ['placeholder' => 'mis. Budi Santoso, Direktur']);

        return [
            [
                'code'          => 'surat-permintaan-dokumen',
                'name'          => 'Surat Permintaan Dokumen kepada Klien',
                'description'   => 'Meminta data/dokumen pendukung dari klien dengan tenggat yang tegas. Daftar dokumen dicetak sebagai lampiran.',
                'category'      => 'surat',
                'number_prefix' => 'S',
                'icon'          => 'heroicon-o-envelope',
                'is_critical'   => false,
                'signers'       => [self::MANAJER],
                'checklist_as_attachment' => true,
                'fields'        => [
                    $this->field('keperluan', 'Keperluan / dasar permintaan', 'text', true, ['placeholder' => 'mis. tindak lanjut SP2DK Nomor S-445/P2DK/KPP.1410/2026 Tahun Pajak 2024']),
                    $this->field('tujuan_penggunaan', 'Data akan digunakan untuk', 'text', true, ['placeholder' => 'mis. penyusunan Laporan Keuangan Tahun 2024 dan surat tanggapan atas SP2DK', 'default' => 'penyusunan laporan keuangan dan surat tanggapan']),
                    $this->field('daftar_dokumen', 'Dokumen yang diminta', 'checklist', true, ['help' => 'Dicetak sebagai tabel di halaman Lampiran I. Tambahkan keterangan per dokumen untuk kolom "Rincian yang Diminta".']),
                    $this->field('kanal_pengiriman', 'Kirim dokumen ke', 'text', true, ['default' => 'WhatsApp (+62-811-8000-9787)']),
                    $this->field('tenggat', 'Tenggat penyerahan', 'date'),
                ],
                'body' => <<<'TXT'
Dengan hormat,

Sehubungan dengan {{keperluan}}, bersama ini kami mengajukan permohonan bantuan penyampaian data dan/atau dokumen pendukung sebagaimana tercantum dalam lampiran surat ini.

Data dan/atau dokumen tersebut hanya akan kami gunakan sebagai dasar {{tujuan_penggunaan}}, dan kami menjamin kerahasiaannya sesuai standar profesi konsultan pajak serta ketentuan perpajakan yang berlaku.

Mohon kelengkapan dokumen dikirimkan ke {{kanal_pengiriman}} paling lambat {{tenggat}}. Jika terdapat kendala dalam pengumpulan dokumen, silakan hubungi kami agar dapat dibantu.

Demikian surat permintaan data ini kami sampaikan. Atas perhatian dan kerja sama Bapak/Ibu, kami ucapkan terima kasih.
TXT,
            ],
            [
                'code'          => 'surat-tanggapan-sp2dk',
                'name'          => 'Surat Tanggapan SP2DK',
                'description'   => 'Surat pengantar tanggapan atas SP2DK beserta lampiran kertas kerja.',
                'category'      => 'surat',
                'number_prefix' => 'S',
                'icon'          => 'heroicon-o-document-text',
                'is_critical'   => false,
                'signers'       => [self::MANAJER, self::DIREKTUR],
                'fields'        => [
                    $this->field('nomor_sp2dk', 'Nomor SP2DK'),
                    $this->field('tanggal_sp2dk', 'Tanggal SP2DK', 'date'),
                    $this->field('kpp', 'Ditujukan kepada (KPP)', 'text', true, ['placeholder' => 'mis. KPP Pratama Samarinda Ulu']),
                    $this->field('pokok_tanggapan', 'Pokok tanggapan', 'textarea', true, ['default' => 'Setelah menelaah data yang Bapak/Ibu sampaikan, dapat kami jelaskan sebagai berikut …']),
                    $this->field('lampiran', 'Lampiran (satu per baris)', 'list', true, ['default' => "Lembar analisis kasus\nKertas kerja ekualisasi\nRekapitulasi faktur"]),
                ],
                'body' => <<<'TXT'
Kepada Yth.
{{kpp}}

Dengan hormat,

Menunjuk Surat Permintaan Penjelasan atas Data dan/atau Keterangan Nomor {{nomor_sp2dk}} tanggal {{tanggal_sp2dk}} yang ditujukan kepada {{klien}}, bersama ini kami selaku kuasa Wajib Pajak menyampaikan penjelasan sebagai berikut:

{{pokok_tanggapan}}

Sebagai kelengkapan, kami lampirkan:

{{lampiran}}

Demikian penjelasan ini kami sampaikan. Apabila diperlukan pembahasan lebih lanjut, kami siap hadir sesuai jadwal yang Bapak/Ibu tentukan.
TXT,
            ],
            [
                'code'          => 'surat-tanggapan-sphp',
                'name'          => 'Surat Tanggapan SPHP',
                'description'   => 'Tanggapan tertulis atas Surat Pemberitahuan Hasil Pemeriksaan (maks 5 hari kerja).',
                'category'      => 'surat',
                'number_prefix' => 'S',
                'icon'          => 'heroicon-o-pencil-square',
                'is_critical'   => false,
                'signers'       => [self::MANAJER, self::DIREKTUR],
                'fields'        => [
                    $this->field('nomor_sphp', 'Nomor SPHP'),
                    $this->field('tanggal_sphp', 'Tanggal SPHP', 'date'),
                    $this->field('kpp', 'Ditujukan kepada (KPP)'),
                    $this->field('pokok_setuju', 'Koreksi yang disetujui', 'textarea', false),
                    $this->field('pokok_tidak_setuju', 'Koreksi yang tidak disetujui beserta alasannya', 'textarea', true),
                    $this->field('lampiran', 'Lampiran (satu per baris)', 'list', false),
                ],
                'body' => <<<'TXT'
Kepada Yth.
{{kpp}}

Dengan hormat,

Sehubungan dengan Surat Pemberitahuan Hasil Pemeriksaan Nomor {{nomor_sphp}} tanggal {{tanggal_sphp}} atas {{klien}}, bersama ini kami selaku kuasa Wajib Pajak menyampaikan tanggapan tertulis dalam batas waktu yang ditentukan.

Koreksi yang kami setujui:

{{pokok_setuju}}

Koreksi yang tidak kami setujui beserta alasannya:

{{pokok_tidak_setuju}}

Lampiran:

{{lampiran}}

Kami mohon tanggapan ini menjadi bahan pertimbangan dalam pembahasan akhir hasil pemeriksaan.
TXT,
            ],
            [
                'code'          => 'surat-permohonan-qa',
                'name'          => 'Surat Permohonan Pembahasan Quality Assurance',
                'description'   => 'Permohonan tertulis ke Tim QA, maksimal 3 hari kerja sejak Risalah Pembahasan ditandatangani.',
                'category'      => 'surat',
                'number_prefix' => 'S',
                'icon'          => 'heroicon-o-shield-check',
                'is_critical'   => false,
                'signers'       => [self::MANAJER, self::DIREKTUR],
                'fields'        => [
                    $this->field('kanwil', 'Ditujukan kepada (Kanwil DJP)'),
                    $this->field('nomor_risalah', 'Nomor Risalah Pembahasan'),
                    $this->field('tanggal_risalah', 'Tanggal Risalah Pembahasan', 'date'),
                    $this->field('pokok_perbedaan', 'Pokok perbedaan pendapat', 'textarea'),
                    $this->field('dasar_hukum', 'Dasar hukum yang diajukan', 'textarea'),
                ],
                'body' => <<<'TXT'
Kepada Yth.
Tim Quality Assurance Pemeriksaan
{{kanwil}}

Dengan hormat,

Menunjuk Risalah Pembahasan Akhir Hasil Pemeriksaan Nomor {{nomor_risalah}} tanggal {{tanggal_risalah}} atas {{klien}}, dengan ini kami mengajukan permohonan pembahasan dengan Tim Quality Assurance Pemeriksaan atas perbedaan pendapat yang belum disepakati, yaitu:

{{pokok_perbedaan}}

Dasar hukum yang kami ajukan:

{{dasar_hukum}}

Permohonan ini kami sampaikan dalam jangka waktu 3 (tiga) hari kerja sejak Risalah Pembahasan ditandatangani. Kami bersedia hadir pada waktu yang ditetapkan.
TXT,
            ],
            [
                'code'          => 'ba-serah-terima-dokumen',
                'name'          => 'Berita Acara Serah Terima Dokumen',
                'description'   => 'Mencatat dokumen yang diterima dari atau diserahkan kepada klien.',
                'category'      => 'berita_acara',
                'number_prefix' => 'BA',
                'icon'          => 'heroicon-o-cube',
                'is_critical'   => false,
                'signers'       => [self::MANAJER],
                'fields'        => [
                    $this->field('arah', 'Arah serah terima', 'select', true, ['options' => ['diterima' => 'Diterima dari klien', 'diserahkan' => 'Diserahkan kepada klien'], 'default' => 'diterima']),
                    $this->field('tanggal_serah_terima', 'Tanggal serah terima', 'date'),
                    $this->field('daftar_dokumen', 'Daftar dokumen', 'list'),
                    $klienSignatory,
                    $this->field('catatan', 'Catatan', 'textarea', false),
                ],
                'body' => <<<'TXT'
Pada hari ini, {{tanggal_serah_terima}}, telah dilakukan serah terima dokumen antara {{perusahaan}} dan {{klien}} yang diwakili oleh {{pihak_klien}}, dengan status: {{arah}}.

Dokumen yang diserahterimakan:

{{daftar_dokumen}}

{{catatan}}

Dokumen di atas diterima dalam keadaan lengkap dan baik. Berita acara ini dibuat dalam rangkap dua dan ditandatangani oleh kedua belah pihak.
TXT,
            ],
            [
                'code'          => 'ba-pembahasan-klien',
                'name'          => 'Berita Acara Pembahasan dengan Klien',
                'description'   => 'Mencatat pokok pembahasan, kesepakatan, dan tindak lanjut.',
                'category'      => 'berita_acara',
                'number_prefix' => 'BA',
                'icon'          => 'heroicon-o-users',
                'is_critical'   => false,
                'signers'       => [self::MANAJER],
                'fields'        => [
                    $this->field('tanggal_pembahasan', 'Tanggal pembahasan', 'date'),
                    $this->field('tempat', 'Tempat / media', 'text', true, ['placeholder' => 'mis. Kantor klien / Zoom']),
                    $this->field('peserta', 'Peserta (satu per baris)', 'list'),
                    $this->field('pokok_pembahasan', 'Pokok pembahasan', 'list'),
                    $this->field('kesepakatan', 'Kesepakatan', 'list'),
                    $this->field('tindak_lanjut', 'Tindak lanjut & penanggung jawab', 'list'),
                ],
                'body' => <<<'TXT'
Pada {{tanggal_pembahasan}} bertempat di {{tempat}}, telah dilaksanakan pembahasan antara {{perusahaan}} dan {{klien}} dengan peserta:

{{peserta}}

Pokok pembahasan:

{{pokok_pembahasan}}

Kesepakatan:

{{kesepakatan}}

Tindak lanjut:

{{tindak_lanjut}}

Demikian berita acara ini dibuat untuk menjadi rujukan bersama.
TXT,
            ],
            [
                'code'          => 'ba-pendapat-profesional',
                'name'          => 'Berita Acara Penyampaian Pendapat Profesional',
                'description'   => 'Dipakai ketika klien memilih perlakuan pajak yang berbeda dari rekomendasi kantor. Mencatat rekomendasi kantor, pilihan klien, dan pemisahan tanggung jawab. Wajib persetujuan Direktur.',
                'category'      => 'berita_acara',
                'number_prefix' => 'BA',
                'icon'          => 'heroicon-o-shield-exclamation',
                'is_critical'   => true,
                'signers'       => [self::MANAJER, self::DIREKTUR],
                'fields'        => [
                    $this->field('pokok_masalah', 'Pokok masalah / transaksi', 'textarea'),
                    $this->field('rekomendasi_kantor', 'Rekomendasi kantor beserta dasar hukumnya', 'textarea'),
                    $this->field('pilihan_klien', 'Perlakuan yang dipilih klien', 'textarea'),
                    $this->field('risiko', 'Risiko yang telah dijelaskan kepada klien', 'textarea'),
                    $klienSignatory,
                ],
                'body' => <<<'TXT'
Berita acara ini dibuat untuk mencatat penyampaian pendapat profesional {{perusahaan}} kepada {{klien}} yang diwakili oleh {{pihak_klien}}, atas hal berikut:

{{pokok_masalah}}

Rekomendasi kantor:

{{rekomendasi_kantor}}

Perlakuan yang dipilih klien:

{{pilihan_klien}}

Risiko yang telah dijelaskan kepada klien:

{{risiko}}

Dengan ditandatanganinya berita acara ini, klien menyatakan telah memahami rekomendasi dan risiko di atas, dan memilih perlakuan tersebut atas pertimbangannya sendiri. Segala akibat perpajakan yang timbul dari pilihan tersebut menjadi tanggung jawab klien, sedangkan {{perusahaan}} telah melaksanakan kewajiban profesionalnya dengan menyampaikan pendapat ini secara tertulis.
TXT,
            ],
            [
                'code'          => 'ba-serah-terima-laporan',
                'name'          => 'Berita Acara Serah Terima Laporan',
                'description'   => 'Penutup penugasan: menyerahkan hasil pekerjaan kepada klien.',
                'category'      => 'berita_acara',
                'number_prefix' => 'BA',
                'icon'          => 'heroicon-o-clipboard-document-check',
                'is_critical'   => false,
                'signers'       => [self::MANAJER, self::DIREKTUR],
                'fields'        => [
                    $this->field('tanggal_serah_terima', 'Tanggal serah terima', 'date'),
                    $this->field('periode', 'Periode / tahun pajak', 'text', true, ['placeholder' => 'mis. Tahun Pajak 2025']),
                    $this->field('daftar_hasil', 'Hasil pekerjaan yang diserahkan', 'list'),
                    $klienSignatory,
                ],
                'body' => <<<'TXT'
Pada {{tanggal_serah_terima}}, {{perusahaan}} telah menyerahkan kepada {{klien}} yang diwakili oleh {{pihak_klien}} hasil pekerjaan{{proyek}} untuk {{periode}} berupa:

{{daftar_hasil}}

Dengan serah terima ini, penugasan dinyatakan selesai. Klien telah memeriksa dan menerima hasil pekerjaan di atas. Dokumen sumber yang dipinjam selama penugasan dikembalikan bersamaan dengan berita acara ini.
TXT,
            ],
            [
                'code'          => 'surat-pernyataan-klien',
                'name'          => 'Surat Pernyataan Klien atas Kebenaran Data',
                'description'   => 'Klien menyatakan data yang diserahkan benar dan lengkap. Perlindungan dasar bagi kantor.',
                'category'      => 'surat',
                'number_prefix' => 'S',
                'icon'          => 'heroicon-o-clipboard-document',
                'is_critical'   => false,
                'signers'       => [self::MANAJER],
                'fields'        => [
                    $this->field('nama_penandatangan', 'Nama penandatangan dari klien'),
                    $this->field('jabatan_penandatangan', 'Jabatan'),
                    $this->field('periode', 'Periode data', 'text', true, ['placeholder' => 'mis. Januari–Desember 2025']),
                    $this->field('daftar_data', 'Data yang diserahkan', 'list'),
                ],
                'body' => <<<'TXT'
Yang bertanda tangan di bawah ini, {{nama_penandatangan}}, selaku {{jabatan_penandatangan}} dari {{klien}}, dengan ini menyatakan bahwa data dan dokumen berikut untuk periode {{periode}} yang kami serahkan kepada {{perusahaan}} adalah benar, lengkap, dan sesuai dengan keadaan yang sebenarnya:

{{daftar_data}}

Kami memahami bahwa {{perusahaan}} menyusun pekerjaannya berdasarkan data tersebut, dan segala akibat yang timbul dari ketidakbenaran atau ketidaklengkapan data menjadi tanggung jawab kami.

Pernyataan ini dibuat dengan sebenarnya tanpa paksaan dari pihak mana pun.
TXT,
            ],
            [
                'code'          => 'pemberitahuan-kode-billing',
                'name'          => 'Pemberitahuan Kode Billing & Tenggat Setor',
                'description'   => 'Mengirim kode billing beserta batas setor dan lapor kepada klien.',
                'category'      => 'pemberitahuan',
                'number_prefix' => 'S',
                'icon'          => 'heroicon-o-credit-card',
                'is_critical'   => false,
                'signers'       => [self::MANAJER],
                'fields'        => [
                    $this->field('jenis_pajak', 'Jenis pajak', 'text', true, ['placeholder' => 'mis. PPh Pasal 21']),
                    $this->field('masa_pajak', 'Masa pajak', 'text', true, ['placeholder' => 'mis. Agustus 2026']),
                    $this->field('kode_billing', 'Kode billing'),
                    $this->field('jumlah', 'Jumlah yang harus disetor', 'currency', true, ['placeholder' => '12.500.000']),
                    $this->field('batas_setor', 'Batas waktu setor', 'date'),
                    $this->field('batas_lapor', 'Batas waktu lapor', 'date'),
                ],
                'body' => <<<'TXT'
Dengan hormat,

Bersama ini kami sampaikan kode billing untuk pembayaran {{jenis_pajak}} masa {{masa_pajak}} atas nama {{klien}}:

- Kode billing: {{kode_billing}}
- Jumlah: {{jumlah}}
- Batas waktu setor: {{batas_setor}}
- Batas waktu lapor: {{batas_lapor}}

Mohon pembayaran dilakukan sebelum batas waktu setor dan bukti setor dikirimkan kepada kami agar pelaporan dapat kami selesaikan tepat waktu. Keterlambatan setor dikenai sanksi bunga sesuai ketentuan yang berlaku.
TXT,
            ],
            [
                'code'          => 'pengingat-pertama',
                'name'          => 'Pengingat Pertama — Kelengkapan Data',
                'description'   => 'Mengingatkan klien atas data yang belum lengkap, memuat daftar yang sudah diterima dan yang masih ditunggu.',
                'category'      => 'pengingat',
                'number_prefix' => 'S',
                'icon'          => 'heroicon-o-bell',
                'is_critical'   => false,
                'signers'       => [self::MANAJER],
                'fields'        => [
                    $this->field('keperluan', 'Keperluan', 'text', true, ['placeholder' => 'mis. penyusunan SPT Tahunan 2025']),
                    $this->field('daftar_diterima', 'Data yang sudah diterima', 'list', false),
                    $this->field('daftar_ditunggu', 'Data yang masih ditunggu', 'list'),
                    $this->field('tenggat', 'Tenggat penyerahan', 'date'),
                ],
                'body' => <<<'TXT'
Dengan hormat,

Menindaklanjuti permintaan data untuk {{keperluan}}, kami sampaikan status kelengkapan data {{klien}} per {{tanggal_surat}}.

Data yang sudah kami terima:

{{daftar_diterima}}

Data yang masih kami tunggu:

{{daftar_ditunggu}}

Mohon data tersebut dapat kami terima paling lambat {{tenggat}} agar pekerjaan dapat berjalan sesuai jadwal. Terima kasih atas kerja samanya.
TXT,
            ],
            [
                'code'          => 'pengingat-kedua',
                'name'          => 'Pengingat Kedua — Batas Akhir Data',
                'description'   => 'Pengingat terakhir sebelum data difinalkan; menegaskan konsekuensi dan tenggat dari KPP.',
                'category'      => 'pengingat',
                'number_prefix' => 'S',
                'icon'          => 'heroicon-o-exclamation-triangle',
                'is_critical'   => false,
                'signers'       => [self::MANAJER, self::DIREKTUR],
                'fields'        => [
                    $this->field('keperluan', 'Keperluan'),
                    $this->field('daftar_ditunggu', 'Data yang masih ditunggu', 'list'),
                    $this->field('tenggat_akhir', 'Batas akhir dari kami', 'date'),
                    $this->field('tenggat_kpp', 'Tenggat kewajiban (KPP)', 'date'),
                    $this->field('konsekuensi', 'Konsekuensi bila data tidak diterima', 'textarea', true, ['default' => 'Pekerjaan akan kami finalkan berdasarkan data yang sudah tersedia, dan risiko sanksi keterlambatan atau ketidaklengkapan pelaporan menjadi tanggung jawab klien.']),
                ],
                'body' => <<<'TXT'
Dengan hormat,

Ini adalah pengingat kedua dan terakhir atas data {{klien}} untuk {{keperluan}} yang hingga {{tanggal_surat}} belum kami terima:

{{daftar_ditunggu}}

Batas akhir penyerahan data kepada kami adalah {{tenggat_akhir}}, mengingat tenggat kewajiban dari KPP jatuh pada {{tenggat_kpp}}.

Apabila sampai batas akhir tersebut data belum kami terima:

{{konsekuensi}}

Kami berharap kerja sama Bapak/Ibu agar kewajiban perpajakan dapat dipenuhi tepat waktu.
TXT,
            ],
            [
                'code'          => 'ba-finalisasi-data',
                'name'          => 'Berita Acara Finalisasi Data',
                'description'   => 'Menutup tahap pengumpulan data: mencatat apa yang diterima, apa yang tidak tersedia, dan atas dasar apa pekerjaan dilanjutkan.',
                'category'      => 'berita_acara',
                'number_prefix' => 'BA',
                'icon'          => 'heroicon-o-archive-box',
                'is_critical'   => false,
                'signers'       => [self::MANAJER, self::DIREKTUR],
                'fields'        => [
                    $this->field('tanggal_finalisasi', 'Tanggal finalisasi', 'date'),
                    $this->field('keperluan', 'Keperluan'),
                    $this->field('daftar_diterima', 'Data yang diterima', 'list'),
                    $this->field('daftar_tidak_tersedia', 'Data yang tidak tersedia', 'list', false),
                    $this->field('dasar_lanjut', 'Dasar pekerjaan dilanjutkan', 'textarea'),
                    $klienSignatory,
                ],
                'body' => <<<'TXT'
Pada {{tanggal_finalisasi}}, {{perusahaan}} dan {{klien}} yang diwakili oleh {{pihak_klien}} sepakat menutup tahap pengumpulan data untuk {{keperluan}} dengan rincian berikut.

Data yang diterima:

{{daftar_diterima}}

Data yang tidak tersedia:

{{daftar_tidak_tersedia}}

Pekerjaan dilanjutkan atas dasar:

{{dasar_lanjut}}

Data yang masuk setelah tanggal ini tidak lagi menjadi bagian dari pekerjaan, kecuali disepakati lain secara tertulis.
TXT,
            ],
        ];
    }
}
