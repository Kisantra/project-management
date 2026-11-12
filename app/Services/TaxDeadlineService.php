<?php

namespace App\Services;

use App\Support\TaxPeriod;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Tenggat pelaporan pajak bulanan Indonesia.
 *
 * Setiap bulan punya tiga tenggat tetap, dan semuanya menagih periode BULAN
 * SEBELUMNYA. Tenggat 20 Juli 2026 menagih laporan PPN periode Juni 2026.
 *
 * Sumber kebenaran periode adalah kolom tax_reports.month + tax_reports.year,
 * bukan created_at. Sebuah laporan periode Januari bisa saja dibuat bulan
 * Februari, atau dibuat di muka pada bulan Desember sebelumnya.
 */
class TaxDeadlineService
{
    public const PPH = 'pph';
    public const PPN = 'ppn';
    public const PAYMENT = 'payment';

    /** Ambang "mendekati tenggat", dalam hari. */
    public const SOON_THRESHOLD = 3;

    /**
     * Hanya kewajiban yang benar-benar dikontrakkan klien yang dihitung.
     *
     * tax_calculation_summaries membuat baris untuk setiap jenis pajak tanpa
     * memandang kontrak, sehingga klien yang tidak berkontrak PPh Unifikasi
     * tetap punya baris "Belum Lapor" untuk jenis itu. Tanpa penyaringan ini
     * dashboard melaporkan tunggakan yang tidak pernah menjadi kewajiban, dan
     * tim mengejar klien untuk pekerjaan yang tidak ada.
     *
     * pph_badan_contract tidak ikut: tidak ada tax_type padanannya di tabel
     * summary, karena PPh Badan bersifat tahunan dan tidak dilaporkan per masa.
     */
    private const CONTRACTED = "(
        (s.tax_type = 'ppn'   AND clients.ppn_contract = 1) OR
        (s.tax_type = 'pph'   AND clients.pph_contract = 1) OR
        (s.tax_type = 'bupot' AND clients.bupot_contract = 1)
    )";

    /**
     * Kondisi SQL yang sama untuk kueri yang tidak lewat periodQuery().
     *
     * Catatan: masa "tanpa aktivitas" tidak lagi ditebak dari ketiadaan data.
     * PPh Unifikasi dan PPh Badan dinyatakan secara eksplisit lewat kolom
     * no_activity, dan penandaan itu sekaligus menyetel report_status menjadi
     * "Sudah Lapor" sehingga masa tersebut otomatis keluar dari hitungan
     * tunggakan tanpa perlu aturan tambahan di sini.
     */
    public static function contractedCondition(): string
    {
        return self::CONTRACTED;
    }

    /**
     * Nama bulan Indonesia dipetakan eksplisit, bukan lewat Carbon::translatedFormat().
     * Locale aplikasi adalah 'en', dan mengubahnya secara global akan mengubah
     * tampilan tanggal di seluruh panel.
     */
    private const MONTHS_ID = TaxPeriod::NAMES;

    /**
     * Periode yang ditagih oleh tenggat di bulan tertentu: selalu bulan sebelumnya.
     */
    public function periodFor(Carbon $anchor): Carbon
    {
        return $anchor->copy()->startOfMonth()->subMonth();
    }

    /**
     * Kebalikan periodFor(): bulan tempat tenggat sebuah periode berada.
     * Periode Juni 2026 ditagih oleh tenggat-tenggat bulan Juli 2026.
     */
    public function anchorFor(Carbon $period): Carbon
    {
        return $period->copy()->startOfMonth()->addMonth();
    }

    /**
     * Tanggal tenggat, dijepit ke hari terakhir bulan berjalan.
     *
     * Tanpa penjepitan ini, Carbon::createFromDate($y, 2, 30) melimpah ke Maret
     * dan tenggat pembayaran hilang dari bulan Februari.
     */
    public function deadlineDate(Carbon $anchor, int $day): Carbon
    {
        $month = $anchor->copy()->startOfMonth();

        return $month->copy()->setDay(min($day, $month->daysInMonth));
    }

    /**
     * Ketiga tenggat untuk bulan tertentu, lengkap dengan jumlah tunggakan.
     * Menjalankan satu query agregat, bukan satu query per tenggat.
     *
     * @return array<int, array<string, mixed>>
     */
    public function deadlinesFor(Carbon $anchor, ?Carbon $today = null, int|array|null $clientId = null): array
    {
        $today = ($today ?? Carbon::today())->copy()->startOfDay();
        $period = $this->periodFor($anchor);
        $counts = $this->outstandingCounts($period, $clientId);

        $definitions = [
            [
                'key' => self::PPH,
                'day' => 10,
                // Tenggat tanggal 10 adalah payung untuk DUA kewajiban: PPh 21
                // (tax_type 'pph') dan PPh Unifikasi (tax_type 'bupot'). Menamainya
                // "Batas Lapor PPh 21" saja akan menghapus Unifikasi dari tenggat
                // yang sebenarnya juga menagihnya.
                'label' => 'Batas Lapor PPh 21 & Unifikasi',
                'short' => 'PPh 21 & Unifikasi',
                'detail' => 'SPT Masa PPh 21 dan PPh Unifikasi',
                'outstanding' => $counts[self::PPH],
            ],
            [
                'key' => self::PPN,
                'day' => 20,
                'label' => 'Batas Lapor PPN',
                'short' => 'PPN',
                'detail' => 'SPT Masa PPN',
                'outstanding' => $counts[self::PPN],
            ],
            [
                'key' => self::PAYMENT,
                'day' => 30,
                'label' => 'Batas Bayar',
                'short' => 'Bayar',
                'detail' => 'Pembayaran seluruh jenis pajak',
                'outstanding' => $counts[self::PAYMENT],
            ],
        ];

        return array_map(function (array $definition) use ($anchor, $today, $period) {
            $date = $this->deadlineDate($anchor, $definition['day']);
            $daysRemaining = $today->diffInDays($date, false);
            $outstanding = $definition['outstanding'];

            return $definition + [
                'date' => $date,
                'period' => $period,
                'period_label' => $this->periodLabel($period),
                'days_remaining' => $daysRemaining,
                'is_passed' => $daysRemaining < 0,
                'tone' => $this->toneFor($daysRemaining, $outstanding),
                'position' => $this->positionInMonth($date),
            ];
        }, $definitions);
    }

    /**
     * Warna hanya muncul kalau ada masalah nyata.
     *
     * Tenggat yang sudah lewat tapi tunggakannya nol adalah kabar baik, jadi ia
     * tetap netral. Yang berwarna hanya tenggat lewat yang masih menyisakan
     * tunggakan (merah), dan tenggat dekat yang masih menyisakan tunggakan (amber).
     */
    private function toneFor(int $daysRemaining, int $outstanding): string
    {
        if ($outstanding === 0) {
            return 'neutral';
        }

        if ($daysRemaining < 0) {
            return 'overdue';
        }

        if ($daysRemaining <= self::SOON_THRESHOLD) {
            return 'due';
        }

        return 'neutral';
    }

    /** Posisi tanggal dalam bulan sebagai persentase, untuk menempatkan penanda di garis waktu. */
    public function positionInMonth(Carbon $date): float
    {
        return round((($date->day - 1) / max(1, $date->daysInMonth - 1)) * 100, 3);
    }

    /**
     * Jumlah tunggakan per tenggat untuk satu periode, dalam satu query.
     *
     * @return array{pph: int, ppn: int, payment: int}
     */
    public function outstandingCounts(Carbon $period, int|array|null $clientId = null): array
    {
        $row = $this->periodQuery($period, $clientId)
            ->selectRaw("
                COUNT(DISTINCT CASE
                    WHEN s.tax_type IN ('pph', 'bupot') AND s.report_status = 'Belum Lapor'
                    THEN clients.id END) as pph_outstanding,
                COUNT(DISTINCT CASE
                    WHEN s.tax_type = 'ppn' AND s.report_status = 'Belum Lapor'
                    THEN clients.id END) as ppn_outstanding,
                COUNT(DISTINCT CASE
                    WHEN s.bayar_status = 'Belum Bayar' AND s.status_final <> 'Nihil'
                    THEN clients.id END) as payment_outstanding
            ")
            ->first();

        return [
            self::PPH => (int) ($row->pph_outstanding ?? 0),
            self::PPN => (int) ($row->ppn_outstanding ?? 0),
            self::PAYMENT => (int) ($row->payment_outstanding ?? 0),
        ];
    }

    /**
     * Query dasar untuk satu periode pelaporan, dibatasi ke klien aktif.
     *
     * Memfilter month DAN year. Kode sebelumnya hanya memfilter nama bulan,
     * sehingga periode Januari 2026 tercampur dengan Januari 2025.
     */
    public function periodQuery(Carbon $period, int|array|null $clientId = null): \Illuminate\Database\Query\Builder
    {
        // One client (int), a set of clients (array, e.g. the user's own on the dashboard), or all (null).
        return DB::table('tax_calculation_summaries as s')
            ->join('tax_reports', 's.tax_report_id', '=', 'tax_reports.id')
            ->join('clients', 'tax_reports.client_id', '=', 'clients.id')
            ->where('tax_reports.month', $period->format('F'))
            ->where('tax_reports.year', $period->year)
            ->where('clients.status', 'Active')
            ->whereRaw(self::CONTRACTED)
            ->when(is_int($clientId), fn ($q) => $q->where('clients.id', $clientId))
            ->when(is_array($clientId), fn ($q) => $q->whereIn('clients.id', $clientId));
    }

    /** Nama bulan Indonesia, contoh: "Juni". */
    public function monthName(Carbon $date): string
    {
        return self::MONTHS_ID[$date->month];
    }

    /** Singkatan bulan Indonesia, contoh: "Jun". Untuk sumbu chart yang sempit. */
    public function monthShort(Carbon $date): string
    {
        return match ($date->month) {
            5 => 'Mei',
            8 => 'Agu',
            10 => 'Okt',
            12 => 'Des',
            default => substr(self::MONTHS_ID[$date->month], 0, 3),
        };
    }

    /** Kunci periode sesuai penyimpanan di tax_reports, contoh: "2026-June". */
    public function periodKey(Carbon $period): string
    {
        return $period->year . '-' . $period->format('F');
    }

    /** Label periode Indonesia, contoh: "Juni 2026". */
    public function periodLabel(Carbon $period): string
    {
        return $this->monthName($period) . ' ' . $period->year;
    }

    /**
     * Jarak tenggat dalam bahasa manusia. Selalu berpasangan dengan label
     * tekstual, tidak pernah mengandalkan warna sendirian.
     */
    public function humanDistance(int $daysRemaining): string
    {
        return match (true) {
            $daysRemaining === 0 => 'Hari ini',
            $daysRemaining === 1 => 'Besok',
            $daysRemaining === -1 => 'Lewat 1 hari',
            $daysRemaining < 0 => 'Lewat ' . abs($daysRemaining) . ' hari',
            default => $daysRemaining . ' hari lagi',
        };
    }
}
