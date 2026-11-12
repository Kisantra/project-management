<?php

namespace App\Support;

/**
 * Month names for tax periods. tax_reports.month stores English names
 * ("February"); the UI shows Indonesian ones. Mapped explicitly because the
 * app locale is 'en' (see TaxDeadlineService).
 */
final class TaxPeriod
{
    public const MONTHS = [
        1 => 'January', 2 => 'February', 3 => 'March', 4 => 'April', 5 => 'May', 6 => 'June',
        7 => 'July', 8 => 'August', 9 => 'September', 10 => 'October', 11 => 'November', 12 => 'December',
    ];

    public const NAMES = [
        1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April', 5 => 'Mei', 6 => 'Juni',
        7 => 'Juli', 8 => 'Agustus', 9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember',
    ];

    public const SHORT = [
        1 => 'Jan', 2 => 'Feb', 3 => 'Mar', 4 => 'Apr', 5 => 'Mei', 6 => 'Jun',
        7 => 'Jul', 8 => 'Agu', 9 => 'Sep', 10 => 'Okt', 11 => 'Nov', 12 => 'Des',
    ];

    /** 1-12 for an English or Indonesian month name, or a number; 0 when unknown. */
    public static function index(int|string|null $month): int
    {
        if (is_int($month) || ctype_digit((string) $month)) {
            $number = (int) $month;

            return $number >= 1 && $number <= 12 ? $number : 0;
        }

        $name = mb_strtolower(trim((string) $month));

        foreach ([self::MONTHS, self::NAMES] as $names) {
            foreach ($names as $number => $candidate) {
                if (mb_strtolower($candidate) === $name) {
                    return $number;
                }
            }
        }

        return 0;
    }

    public static function name(int|string|null $month): string
    {
        return self::NAMES[self::index($month)] ?? (string) $month;
    }

    public static function short(int|string|null $month): string
    {
        return self::SHORT[self::index($month)] ?? (string) $month;
    }

    /** "14 Sep 2025" for a date or date string; null when empty. */
    public static function date(mixed $value): ?string
    {
        if (blank($value)) {
            return null;
        }

        $date = \Illuminate\Support\Carbon::parse($value);

        return $date->format('j') . ' ' . self::SHORT[$date->month] . ' ' . $date->year;
    }

    /** "Februari 2025" */
    public static function label(int|string|null $month, int|string|null $year): string
    {
        return trim(self::name($month) . ' ' . $year);
    }
}
