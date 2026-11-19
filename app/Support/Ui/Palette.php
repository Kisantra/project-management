<?php

namespace App\Support\Ui;

/**
 * Colours shared by the admin panel and the client portal, so both read as the
 * same Kisantra app (DESIGN.md, Colors).
 */
final class Palette
{
    /**
     * Kisantra family palette, see DESIGN.md. Primary 500/600/700 are the
     * Project Management tile (#1790b5), action (#157a9c) and arm (#10657f)
     * blues. Gray is tinted toward the same hue, so custom views written with
     * gray-* utilities follow the theme. Success and info share the primary
     * blue (Three Voices Rule); warning keeps Filament's amber until each
     * page is migrated.
     */
    public static function kisantra(): array
    {
        $blue = [
            50 => '238, 249, 253',
            100 => '223, 244, 254',
            200 => '188, 232, 248',
            300 => '137, 210, 234',
            400 => '74, 180, 210',
            500 => '23, 144, 181',
            600 => '21, 122, 156',
            700 => '16, 101, 127',
            800 => '0, 80, 101',
            900 => '5, 59, 77',
            950 => '4, 38, 50',
        ];

        return [
            'primary' => $blue,
            'success' => $blue,
            'info' => $blue,
            'gray' => [
                50 => '241, 247, 249',
                100 => '235, 242, 244',
                200 => '222, 229, 231',
                300 => '204, 212, 215',
                400 => '139, 148, 151',
                500 => '98, 106, 111',
                600 => '72, 81, 86',
                700 => '54, 63, 67',
                800 => '39, 47, 52',
                900 => '21, 28, 32',
                950 => '13, 20, 23',
            ],
            'danger' => [
                50 => '255, 244, 242',
                100 => '255, 230, 225',
                200 => '255, 208, 199',
                300 => '254, 172, 158',
                400 => '239, 128, 111',
                500 => '207, 66, 50',
                600 => '193, 62, 46',
                700 => '175, 54, 40',
                800 => '137, 43, 32',
                900 => '104, 35, 27',
                950 => '64, 20, 14',
            ],
        ];
    }
}
