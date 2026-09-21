<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

/**
 * Single-row settings for letter numbering. Read through ::current().
 */
class LetterNumberingSetting extends Model
{
    public const RESET_PERIODS = [
        'yearly'  => 'Setiap tahun',
        'monthly' => 'Setiap bulan',
        'never'   => 'Tidak pernah (berlanjut terus)',
    ];

    public const TOKENS = [
        '{prefix}' => 'Awalan dari template (S, BA)',
        '{seq}'    => 'Nomor urut, dengan nol di depan',
        '{code}'   => 'Kode kantor',
        '{roman}'  => 'Bulan romawi (IX)',
        '{month}'  => 'Bulan dua digit (09)',
        '{year}'   => 'Tahun empat digit (2026)',
        '{yy}'     => 'Tahun dua digit (26)',
    ];

    protected $fillable = ['number_format', 'office_code', 'sequence_digits', 'reset_period', 'sequence_per_prefix'];

    protected $casts = [
        'sequence_digits'     => 'integer',
        'sequence_per_prefix' => 'boolean',
    ];

    public static function current(): self
    {
        return static::query()->first() ?? static::create([
            'number_format'       => config('letter.number_format', '{prefix}-{seq}/{code}/{roman}/{year}'),
            'office_code'         => config('letter.number_code', 'KSN'),
            'sequence_digits'     => (int) config('letter.sequence_digits', 3),
            'reset_period'        => 'yearly',
            'sequence_per_prefix' => true,
        ]);
    }

    /** Counter bucket for a date: '2026', '2026-09' or 'all'. */
    public function periodKey(Carbon $date): string
    {
        return match ($this->reset_period) {
            'monthly' => $date->format('Y-m'),
            'never'   => 'all',
            default   => $date->format('Y'),
        };
    }

    /** Counter prefix bucket: the template prefix, or '*' for one shared sequence. */
    public function counterPrefix(string $prefix): string
    {
        return $this->sequence_per_prefix ? $prefix : '*';
    }

    public function format(string $prefix, int $sequence, Carbon $date): string
    {
        $roman = ['', 'I', 'II', 'III', 'IV', 'V', 'VI', 'VII', 'VIII', 'IX', 'X', 'XI', 'XII'][(int) $date->format('n')];

        return strtr($this->number_format, [
            '{prefix}' => $prefix,
            '{seq}'    => str_pad((string) $sequence, max(1, $this->sequence_digits), '0', STR_PAD_LEFT),
            '{code}'   => $this->office_code,
            '{roman}'  => $roman,
            '{month}'  => $date->format('m'),
            '{year}'   => $date->format('Y'),
            '{yy}'     => $date->format('y'),
        ]);
    }

    public function periodLabel(string $periodKey): string
    {
        return match (true) {
            $periodKey === 'all'                     => 'Semua periode',
            preg_match('/^\d{4}-\d{2}$/', $periodKey) === 1 => Carbon::createFromFormat('Y-m', $periodKey)->locale('id')->translatedFormat('F Y'),
            default                                  => $periodKey,
        };
    }
}
