<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\TaxReport;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use JsonException;
use RuntimeException;

/**
 * Batch import of PPN invoices from AI-generated JSON plus the invoice files.
 *
 * parse() reads the pasted text, analyze() checks it against the tax report and
 * the uploaded file names without touching the database, and import() saves the
 * rows that passed in one transaction. Callers re-run analyze() right before
 * import() so nothing the browser sends back is trusted.
 */
class InvoiceJsonImportService
{
    public const MAX_ROWS = 300;

    public const STATUS_OK = 'ok';

    public const STATUS_WARNING = 'warning';

    public const STATUS_ERROR = 'error';

    private const MONTHS = [
        'january' => 1, 'february' => 2, 'march' => 3, 'april' => 4, 'may' => 5, 'june' => 6,
        'july' => 7, 'august' => 8, 'september' => 9, 'october' => 10, 'november' => 11, 'december' => 12,
        'januari' => 1, 'februari' => 2, 'maret' => 3, 'mei' => 5, 'juni' => 6, 'juli' => 7,
        'agustus' => 8, 'oktober' => 10, 'desember' => 12,
    ];

    private const MONTH_LABELS = [
        1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April', 5 => 'Mei', 6 => 'Juni',
        7 => 'Juli', 8 => 'Agustus', 9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember',
    ];

    /**
     * @return array{masa: ?string, rows: array<int, mixed>}
     */
    public function parse(string $raw): array
    {
        $text = trim($raw);

        if ($text === '') {
            throw new InvalidArgumentException('JSON masih kosong. Tempel jawaban dari AI terlebih dahulu.');
        }

        // Chat assistants usually wrap the answer in ```json fences.
        if (preg_match('/```(?:json)?\s*(.*?)\s*```/s', $text, $match)) {
            $text = $match[1];
        }

        $decoded = $this->decode($text);

        // Fall back to the outermost JSON value when the AI added a sentence around it.
        if ($decoded === null) {
            $start = strcspn($text, '{[');
            $end = max((int) strrpos($text, '}'), (int) strrpos($text, ']'));

            if ($start < strlen($text) && $end > $start) {
                $decoded = $this->decode(substr($text, $start, $end - $start + 1));
            }
        }

        if ($decoded === null) {
            throw new InvalidArgumentException('Teks yang ditempel bukan JSON yang valid. Pastikan seluruh jawaban AI tersalin, termasuk kurung pembuka dan penutupnya.');
        }

        $masa = null;

        if (is_array($decoded) && array_is_list($decoded)) {
            $rows = $decoded;
        } elseif (is_array($decoded) && isset($decoded['faktur']) && is_array($decoded['faktur']) && array_is_list($decoded['faktur'])) {
            $rows = $decoded['faktur'];
            $masa = isset($decoded['masa']) && is_scalar($decoded['masa']) ? trim((string) $decoded['masa']) : null;
        } else {
            throw new InvalidArgumentException('Format JSON tidak dikenali. Gunakan objek dengan kunci "faktur" yang berisi daftar faktur.');
        }

        if ($rows === []) {
            throw new InvalidArgumentException('Tidak ada faktur di dalam JSON.');
        }

        if (count($rows) > self::MAX_ROWS) {
            throw new InvalidArgumentException('Maksimal ' . self::MAX_ROWS . ' faktur per import, sedangkan JSON ini berisi ' . count($rows) . ' faktur. Pecah menjadi beberapa kali import.');
        }

        return ['masa' => $masa === '' ? null : $masa, 'rows' => $rows];
    }

    /**
     * @return array{month: int, year: int, client_name: string, client_npwp: string}
     */
    public function contextFor(TaxReport $taxReport): array
    {
        return [
            'month' => self::monthNumber((string) $taxReport->month) ?? 0,
            'year' => (int) ($taxReport->year ?: $taxReport->created_at?->year),
            'client_name' => (string) ($taxReport->client?->name ?? ''),
            'client_npwp' => (string) ($taxReport->client?->NPWP ?? ''),
        ];
    }

    /**
     * @param  array{masa: ?string, rows: array<int, mixed>}  $parsed
     * @param  array{month: int, year: int, client_name: string, client_npwp: string}  $context
     * @param  array<int, string>  $fileNames  base names of the uploaded files
     * @param  array<int, string>  $existingNumbers  invoice numbers already in the database
     * @param  array<int, string>  $duplicateFileNames  names uploaded more than once
     */
    public function analyze(array $parsed, array $context, array $fileNames, array $existingNumbers, array $duplicateFileNames = []): array
    {
        $masaCode = sprintf('%04d-%02d', $context['year'], $context['month']);
        $periodLabel = self::periodLabel($context['month'], $context['year']);
        $globalErrors = [];
        $globalWarnings = [];

        if ($parsed['masa'] !== null) {
            $given = preg_match('/^(\d{4})-(\d{1,2})$/', $parsed['masa'], $m) ? sprintf('%04d-%02d', $m[1], $m[2]) : null;

            if ($given === null) {
                $globalErrors[] = "Nilai \"masa\" ({$parsed['masa']}) tidak valid. Gunakan format YYYY-MM, misalnya {$masaCode}.";
            } elseif ($given !== $masaCode) {
                $globalErrors[] = "JSON ini untuk masa {$given}, sedangkan laporan yang sedang dibuka adalah masa {$periodLabel} ({$masaCode}). Pastikan JSON ditempel di laporan yang benar.";
            }
        }

        if ($duplicateFileNames !== []) {
            $globalWarnings[] = 'Ada beberapa file bernama sama: ' . implode(', ', $duplicateFileNames) . '. Yang dipakai adalah file pertama.';
        }

        $fileIndex = [];
        foreach ($fileNames as $name) {
            $fileIndex[mb_strtolower($name)] = $name;
        }

        $existing = array_flip(array_map(fn ($n) => mb_strtolower((string) $n), $existingNumbers));

        $rows = [];
        foreach ($parsed['rows'] as $i => $row) {
            $rows[] = ['line' => $i + 1] + $this->normalizeRow($row, $context, $periodLabel);
        }

        // Cross-row checks: duplicate numbers, numbers already saved, file matching.
        $linesByNumber = [];
        foreach ($rows as $row) {
            if (($row['data']['invoice_number'] ?? '') !== '') {
                $linesByNumber[mb_strtolower($row['data']['invoice_number'])][] = $row['line'];
            }
        }

        foreach ($rows as &$row) {
            $number = $row['data']['invoice_number'] ?? '';

            if ($number !== '') {
                $others = array_values(array_diff($linesByNumber[mb_strtolower($number)], [$row['line']]));

                if ($others !== []) {
                    $row['errors'][] = 'Nomor faktur yang sama juga ada di baris ' . implode(', ', $others) . '.';
                }

                if (isset($existing[mb_strtolower($number)])) {
                    $row['errors'][] = 'Nomor faktur sudah terdaftar di sistem. Untuk mengganti faktur yang sudah ada, gunakan "Buat Revisi" di tabel faktur.';
                }
            }

            $row['file'] = $this->matchFile($row, $fileIndex);
        }
        unset($row);

        // Only rows that will be saved count as sharing a file.
        $linesByFile = [];
        foreach ($rows as $row) {
            if ($row['file'] !== null && $row['errors'] === []) {
                $linesByFile[$row['file']][] = $row['line'];
            }
        }

        foreach ($rows as &$row) {
            if ($row['file'] !== null && $row['errors'] === [] && count($linesByFile[$row['file']]) > 1) {
                $others = array_values(array_diff($linesByFile[$row['file']], [$row['line']]));
                $row['warnings'][] = "File {$row['file']} juga dipakai oleh baris " . implode(', ', $others) . '. Tiap faktur akan menyimpan salinannya sendiri.';
            }

            $row['status'] = $row['errors'] !== [] ? self::STATUS_ERROR : ($row['warnings'] !== [] ? self::STATUS_WARNING : self::STATUS_OK);
            unset($row['requested_file']);
        }
        unset($row);

        $usedFiles = array_filter(array_column($rows, 'file'));
        $unusedFiles = array_values(array_filter($fileNames, fn ($name) => ! in_array($name, $usedFiles, true)));

        return [
            'masa' => $masaCode,
            'period_label' => $periodLabel,
            'global_errors' => $globalErrors,
            'global_warnings' => $globalWarnings,
            'rows' => $rows,
            'unused_files' => $unusedFiles,
            'summary' => $this->summarize($rows, $globalErrors !== []),
        ];
    }

    /**
     * Saves the analyzed rows that passed, with their files, all or nothing.
     *
     * @param  array<int, array>  $rows  rows from analyze() whose status is not error
     */
    public function import(TaxReport $taxReport, array $rows, InvoiceImportFileBag $files): int
    {
        $disk = Storage::disk('public');
        $stored = [];

        try {
            DB::transaction(function () use ($taxReport, $rows, $files, $disk, &$stored) {
                foreach ($rows as $row) {
                    $data = $row['data'];
                    $directory = FileManagementService::generateInvoiceDirectoryPath($taxReport, $data['type']);
                    $fileName = FileManagementService::generateInvoiceFileName($data['type'], $data['invoice_number'], $row['file']);
                    $path = $this->availablePath($directory, $fileName);

                    if (! $disk->put($path, $files->read($row['file']))) {
                        throw new RuntimeException("Gagal menyimpan file {$row['file']} ke storage.");
                    }

                    $stored[] = $path;
                    $clientType = ClientTypeService::getClientTypeFromInvoiceNumber($data['invoice_number']);

                    $invoice = new Invoice();
                    $invoice->tax_report_id = $taxReport->id;
                    $invoice->invoice_number = $data['invoice_number'];
                    $invoice->invoice_date = $data['invoice_date'];
                    $invoice->company_name = $data['company_name'];
                    $invoice->npwp = $data['npwp'];
                    $invoice->type = $data['type'];
                    $invoice->client_type = $clientType['type'];
                    $invoice->has_ppn = $clientType['has_ppn'];
                    $invoice->ppn_percentage = $data['ppn_percentage'];
                    $invoice->dpp = $data['dpp'];
                    $invoice->dpp_nilai_lainnya = $data['dpp_nilai_lainnya'];
                    $invoice->ppn = $data['ppn'];
                    $invoice->is_business_related = $data['is_business_related'];
                    $invoice->notes = $data['notes'];
                    $invoice->file_path = $path;
                    $invoice->created_by = auth()->id();
                    $invoice->save();
                }
            });
        } catch (\Throwable $e) {
            $disk->delete($stored);

            throw $e;
        }

        return count($rows);
    }

    public function buildPrompt(TaxReport $taxReport): string
    {
        $context = $this->contextFor($taxReport);
        $masa = sprintf('%04d-%02d', $context['year'], $context['month']);
        $period = self::periodLabel($context['month'], $context['year']);
        $client = $context['client_name'] !== '' ? $context['client_name'] : '(nama klien)';
        $npwp = trim($context['client_npwp']) !== '' ? trim($context['client_npwp']) : 'tidak tercatat';

        return <<<PROMPT
        Kamu membantu kantor konsultan pajak membaca dokumen Faktur Pajak dan menyalinnya menjadi data JSON.

        ## Konteks
        - Klien: {$client}
        - NPWP klien: {$npwp}
        - Masa pajak: {$period}

        ## Tugas
        Baca semua file faktur yang saya lampirkan. Buat satu objek untuk setiap faktur. Jika satu file berisi beberapa faktur, buat satu objek per faktur dengan nilai "file" yang sama. Lewati faktur yang berstatus dibatalkan.

        ## Menentukan jenis faktur
        - "keluaran": klien di atas adalah PENJUAL, yaitu Pengusaha Kena Pajak yang menerbitkan faktur.
        - "masukan": klien di atas adalah PEMBELI, yaitu Pembeli Barang Kena Pajak / Penerima Jasa Kena Pajak.

        "company_name" dan "npwp" selalu diisi data LAWAN TRANSAKSI, bukan data klien:
        - faktur keluaran: nama dan NPWP pembeli
        - faktur masukan: nama dan NPWP penjual

        ## Cara mengisi setiap field
        - "invoice_number": Nomor Seri Faktur Pajak, hanya angka tanpa titik, strip, atau spasi. Jika dokumen bukan Faktur Pajak, salin nomor dokumennya persis seperti tertulis.
        - "invoice_date": tanggal faktur dengan format YYYY-MM-DD.
        - "type": "keluaran" atau "masukan".
        - "company_name": nama lawan transaksi persis seperti tertulis di faktur.
        - "npwp": NPWP lawan transaksi, hanya angka. Isi null jika tidak ada.
        - "dpp": Harga Jual / Penggantian / Uang Muka / Termin setelah dikurangi potongan harga.
        - "dpp_nilai_lainnya": nilai Dasar Pengenaan Pajak, HANYA jika berbeda dari "dpp" (DPP Nilai Lain, misalnya 11/12 dari harga jual). Jika sama dengan "dpp", isi 0.
        - "ppn": Jumlah PPN yang tertera di faktur.
        - "notes": catatan singkat jika ada hal yang meragukan. Selain itu null.
        - "file": nama file lampiran tempat faktur ini berada, persis termasuk ekstensinya.

        ## Aturan penting
        1. Jangan menebak. Jika sebuah nilai tidak terbaca, isi null dan jelaskan di "notes".
        2. Tulis angka uang sebagai angka bulat tanpa pemisah ribuan, contoh 1390000, bukan "1.390.000".
        3. Tulis "invoice_number" dan "npwp" sebagai teks dalam tanda kutip supaya angka 0 di depan tidak hilang.
        4. Isi "masa" dengan "{$masa}".
        5. Jawab HANYA dengan JSON sesuai format di bawah, tanpa penjelasan sebelum atau sesudahnya.

        ## Format jawaban
        {
          "masa": "{$masa}",
          "faktur": [
            {
              "invoice_number": "04002500000000001",
              "invoice_date": "{$masa}-04",
              "type": "keluaran",
              "company_name": "PT CONTOH PEMBELI",
              "npwp": "0012345678901000",
              "dpp": 10000000,
              "dpp_nilai_lainnya": 0,
              "ppn": 1100000,
              "notes": null,
              "file": "04002500000000001.pdf"
            }
          ]
        }
        PROMPT;
    }

    /**
     * Same rule as safeName() in the Pajak Downloader extension, so files it
     * downloads match their invoice number without a "file" field.
     */
    public static function safeFileName(string $value): string
    {
        $name = trim((string) preg_replace('/[^A-Za-z0-9_.-]+/', '_', $value), '_');

        return $name === '' ? 'unnamed' : $name;
    }

    /**
     * Accepts JSON numbers and Indonesian-formatted strings such as "1.390.000" or "Rp 1.390.000,50".
     */
    public static function parseAmount(mixed $value): ?float
    {
        if (is_int($value) || is_float($value)) {
            return (float) $value;
        }

        if (! is_string($value)) {
            return null;
        }

        $text = trim(str_ireplace(['rp', ' ', "\u{00A0}"], '', $value));

        if (preg_match('/^-?\d{1,3}(\.\d{3})+(,\d+)?$/', $text) || preg_match('/^-?\d+,\d+$/', $text)) {
            $text = str_replace(['.', ','], ['', '.'], $text);
        }

        return is_numeric($text) ? (float) $text : null;
    }

    public static function parseDate(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $text = trim($value);

        if (preg_match('/^(\d{4}-\d{2}-\d{2})T/', $text, $m)) {
            $text = $m[1];
        }

        foreach (['Y-m-d', 'Y-n-j', 'd-m-Y', 'j-n-Y', 'd/m/Y', 'j/n/Y'] as $format) {
            $date = DateTimeImmutable::createFromFormat('!' . $format, $text);

            if ($date !== false && $date->format($format) === $text) {
                return $date->format('Y-m-d');
            }
        }

        return null;
    }

    public static function normalizeType(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        return match (strtolower(trim((string) preg_replace('/\s+/', ' ', $value)))) {
            'keluaran', 'faktur keluaran', 'pajak keluaran', 'penjualan' => 'Faktur Keluaran',
            'masukan', 'masuk', 'faktur masuk', 'faktur masukan', 'pajak masukan', 'pembelian' => 'Faktur Masuk',
            default => null,
        };
    }

    public static function monthNumber(string $month): ?int
    {
        $key = strtolower(trim($month));

        if (ctype_digit($key) && (int) $key >= 1 && (int) $key <= 12) {
            return (int) $key;
        }

        return self::MONTHS[$key] ?? null;
    }

    public static function periodLabel(int $month, int $year): string
    {
        return (self::MONTH_LABELS[$month] ?? '?') . ' ' . $year;
    }

    /**
     * @return array{data: array, errors: array<int, string>, warnings: array<int, string>, requested_file: ?string}
     */
    private function normalizeRow(mixed $row, array $context, string $periodLabel): array
    {
        if (! is_array($row) || array_is_list($row)) {
            return ['data' => [], 'errors' => ['Baris ini bukan objek faktur.'], 'warnings' => [], 'requested_file' => null];
        }

        $errors = [];
        $warnings = [];

        $number = $this->text($row, 'invoice_number', 'Nomor faktur', $errors);
        if ($number === '') {
            $errors[] = 'Nomor faktur kosong.';
        } elseif ($number !== null && mb_strlen($number) > 255) {
            $errors[] = 'Nomor faktur lebih dari 255 karakter.';
        }

        $date = self::parseDate($row['invoice_date'] ?? null);
        if ($date === null) {
            $errors[] = 'Tanggal faktur kosong atau formatnya tidak dikenali. Gunakan YYYY-MM-DD.';
        }

        $type = self::normalizeType($row['type'] ?? null);
        if ($type === null) {
            $errors[] = 'Jenis faktur harus "keluaran" atau "masukan".';
        }

        $company = $this->text($row, 'company_name', 'Nama lawan transaksi', $errors);
        if ($company === '') {
            $errors[] = 'Nama lawan transaksi kosong.';
        } elseif ($company !== null && mb_strlen($company) > 255) {
            $errors[] = 'Nama lawan transaksi lebih dari 255 karakter.';
        }

        $npwp = $this->text($row, 'npwp', 'NPWP', $errors);
        $npwpDigits = preg_replace('/\D/', '', (string) $npwp);
        if ($npwpDigits !== '' && ! in_array(strlen($npwpDigits), [15, 16], true)) {
            $warnings[] = 'NPWP lawan transaksi bukan 15 atau 16 digit.';
        }

        $dpp = $this->amount($row, 'dpp', 'DPP', true, $errors);
        $dppOther = $this->amount($row, 'dpp_nilai_lainnya', 'DPP Nilai Lain', false, $errors) ?? 0.0;
        $ppn = $this->amount($row, 'ppn', 'PPN', true, $errors);

        if ($dpp !== null && $ppn !== null) {
            $expected = $dppOther > 0 ? round($dppOther * 0.12) : round($dpp * 0.11);

            if (abs($ppn - $expected) > 1) {
                $warnings[] = 'PPN Rp ' . self::rupiah($ppn) . ' berbeda dari hitungan sistem Rp ' . self::rupiah($expected) . '. Periksa kembali angka di faktur.';
            }
        }

        $businessRelated = true;
        if (array_key_exists('is_business_related', $row) && $row['is_business_related'] !== null) {
            $parsed = $this->bool($row['is_business_related']);

            if ($parsed === null) {
                $warnings[] = 'Nilai is_business_related tidak dikenali, dianggap true.';
            } else {
                $businessRelated = $parsed;
            }
        }

        if ($date !== null && $type !== null) {
            $month = (int) substr($date, 5, 2);
            $year = (int) substr($date, 0, 4);

            if ($month !== $context['month'] || $year !== $context['year']) {
                $warnings[] = $type === 'Faktur Masuk'
                    ? "Tanggal faktur di luar masa {$periodLabel}. Ini wajar bila faktur masukan dikreditkan di masa lain."
                    : "Tanggal faktur di luar masa {$periodLabel}.";
            }
        }

        if ($this->isClientItself($company, $npwpDigits, $context)) {
            $errors[] = 'Lawan transaksi adalah klien sendiri, kemungkinan jenis faktur terbalik. Faktur keluaran diisi data pembeli, faktur masukan diisi data penjual.';
        }

        $notes = $row['notes'] ?? null;
        $requestedFile = isset($row['file']) && is_string($row['file']) && trim($row['file']) !== ''
            ? basename(str_replace('\\', '/', trim($row['file'])))
            : null;

        return [
            'data' => [
                'invoice_number' => (string) $number,
                'invoice_date' => $date,
                'type' => $type,
                'company_name' => (string) $company,
                'npwp' => $npwp !== null && $npwp !== '' ? $npwp : null,
                'dpp' => $dpp,
                'dpp_nilai_lainnya' => $dppOther,
                'ppn' => $ppn,
                'ppn_percentage' => $dppOther > 0 ? '12' : '11',
                'is_business_related' => $businessRelated,
                'notes' => is_scalar($notes) && trim((string) $notes) !== '' ? trim((string) $notes) : null,
            ],
            'errors' => $errors,
            'warnings' => $warnings,
            'requested_file' => $requestedFile,
        ];
    }

    private function matchFile(array &$row, array $fileIndex): ?string
    {
        $requested = $row['requested_file'] ?? null;
        $number = $row['data']['invoice_number'] ?? '';

        if ($requested !== null) {
            $match = $fileIndex[mb_strtolower($requested)] ?? null;

            if ($match === null) {
                $row['errors'][] = "File {$requested} tidak ada di unggahan." . $this->suggestFile(pathinfo($requested, PATHINFO_FILENAME), $fileIndex);
            }

            return $match;
        }

        if ($number === '') {
            return null;
        }

        $stem = self::safeFileName($number);

        foreach (InvoiceImportFileBag::ALLOWED_EXTENSIONS as $extension) {
            if (isset($fileIndex[mb_strtolower("{$stem}.{$extension}")])) {
                return $fileIndex[mb_strtolower("{$stem}.{$extension}")];
            }
        }

        $row['errors'][] = "File faktur tidak ditemukan. Isi field \"file\", atau beri nama file {$stem}.pdf." . $this->suggestFile($stem, $fileIndex);

        return null;
    }

    /**
     * Points at near misses such as "123 (1).pdf", which browsers create for repeated downloads.
     */
    private function suggestFile(string $stem, array $fileIndex): string
    {
        $stem = mb_strtolower($stem);

        foreach ($fileIndex as $lower => $name) {
            if ($stem !== '' && str_starts_with(pathinfo($lower, PATHINFO_FILENAME), $stem)) {
                return " Mungkin maksudnya {$name}?";
            }
        }

        return '';
    }

    private function summarize(array $rows, bool $blocked): array
    {
        $summary = [
            'total' => count($rows),
            self::STATUS_OK => 0,
            self::STATUS_WARNING => 0,
            self::STATUS_ERROR => 0,
            'saveable' => 0,
            'keluaran' => ['count' => 0, 'dpp' => 0.0, 'ppn' => 0.0],
            'masukan' => ['count' => 0, 'dpp' => 0.0, 'ppn' => 0.0],
        ];

        foreach ($rows as $row) {
            $summary[$row['status']]++;

            if ($row['status'] === self::STATUS_ERROR) {
                continue;
            }

            $key = $row['data']['type'] === 'Faktur Masuk' ? 'masukan' : 'keluaran';
            $summary[$key]['count']++;
            $summary[$key]['dpp'] += $row['data']['dpp'];
            $summary[$key]['ppn'] += $row['data']['ppn'];
        }

        $summary['saveable'] = $blocked ? 0 : $summary[self::STATUS_OK] + $summary[self::STATUS_WARNING];

        return $summary;
    }

    private function isClientItself(?string $company, string $npwpDigits, array $context): bool
    {
        $clientNpwp = self::npwpKey(preg_replace('/\D/', '', $context['client_npwp']));

        if ($clientNpwp !== '' && $npwpDigits !== '' && self::npwpKey($npwpDigits) === $clientNpwp) {
            return true;
        }

        $clientName = self::nameKey($context['client_name']);

        return $clientName !== '' && $company !== null && self::nameKey($company) === $clientName;
    }

    /**
     * 16-digit NPWPs for companies are the 15-digit number with a leading zero.
     */
    private static function npwpKey(string $digits): string
    {
        return strlen($digits) === 16 && $digits[0] === '0' ? substr($digits, 1) : $digits;
    }

    private static function nameKey(string $name): string
    {
        $name = mb_strtolower($name);
        $name = (string) preg_replace('/\b(pt|cv|ud|tbk|persero)\b/u', ' ', $name);
        $name = (string) preg_replace('/[^a-z0-9]+/u', ' ', $name);

        return trim((string) preg_replace('/\s+/', ' ', $name));
    }

    private function text(array $row, string $key, string $label, array &$errors): ?string
    {
        $value = $row[$key] ?? null;

        if ($value === null) {
            return $key === 'npwp' ? null : '';
        }

        if (is_int($value) || is_float($value)) {
            $errors[] = "{$label} harus ditulis sebagai teks dalam tanda kutip supaya angka 0 di depan tidak hilang.";

            return null;
        }

        return is_string($value) ? trim($value) : '';
    }

    private function amount(array $row, string $key, string $label, bool $required, array &$errors): ?float
    {
        if (! array_key_exists($key, $row) || $row[$key] === null || $row[$key] === '') {
            if ($required) {
                $errors[] = "{$label} kosong.";
            }

            return null;
        }

        $value = self::parseAmount($row[$key]);

        if ($value === null) {
            $errors[] = "{$label} bukan angka.";
        } elseif ($value < 0) {
            $errors[] = "{$label} tidak boleh negatif.";

            return null;
        }

        return $value;
    }

    private function bool(mixed $value): ?bool
    {
        if (is_bool($value)) {
            return $value;
        }

        return match (strtolower(trim((string) $value))) {
            '1', 'true', 'ya', 'yes' => true,
            '0', 'false', 'tidak', 'no' => false,
            default => null,
        };
    }

    private function availablePath(string $directory, string $fileName): string
    {
        $disk = Storage::disk('public');
        $path = "{$directory}/{$fileName}";
        $stem = pathinfo($fileName, PATHINFO_FILENAME);
        $extension = pathinfo($fileName, PATHINFO_EXTENSION);

        for ($n = 2; $disk->exists($path); $n++) {
            $path = "{$directory}/{$stem}-{$n}.{$extension}";
        }

        return $path;
    }

    private static function rupiah(float $value): string
    {
        return number_format($value, 0, ',', '.');
    }

    private function decode(string $text): mixed
    {
        try {
            return json_decode($text, true, 64, JSON_THROW_ON_ERROR | JSON_BIGINT_AS_STRING);
        } catch (JsonException) {
            return null;
        }
    }
}
