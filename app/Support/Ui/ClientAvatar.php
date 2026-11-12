<?php

namespace App\Support\Ui;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/** Client logo tile: logo URL with the same lookup as the Clients list, or initials. */
final class ClientAvatar
{
    /** Absolute URL, a file on the public disk, or a public path. */
    public static function url(?string $logo): ?string
    {
        if (blank($logo)) {
            return null;
        }

        if (Str::startsWith($logo, ['http://', 'https://'])) {
            return $logo;
        }

        return Storage::disk('public')->exists($logo)
            ? Storage::disk('public')->url($logo)
            : asset($logo);
    }

    /** First letters of the first and last word ("PT Mulia Makmur" -> "PM"), or two letters of a single word. */
    public static function initials(?string $name): string
    {
        // Letters and digits only, so "[CONTOH] PT X" or "PT. X" don't start with punctuation.
        $clean = preg_replace('/[^\pL\pN\s]+/u', ' ', (string) $name);
        $words = preg_split('/\s+/', trim($clean), -1, PREG_SPLIT_NO_EMPTY) ?: ['?'];

        return mb_strtoupper(count($words) > 1
            ? mb_substr($words[0], 0, 1) . mb_substr(end($words), 0, 1)
            : mb_substr($words[0], 0, 2));
    }
}
