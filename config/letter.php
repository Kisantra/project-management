<?php

/*
|--------------------------------------------------------------------------
| Surat & Berita Acara
|--------------------------------------------------------------------------
| Letterhead identity and numbering for the letters module. Override any
| value in .env; the defaults are placeholders and should be filled in
| before letters go out.
*/

return [
    'company' => [
        'name'       => env('LETTER_COMPANY_NAME', 'Kisantra'),
        'legal_name' => env('LETTER_COMPANY_LEGAL_NAME', 'PT Kinara Sadayatra Nusantara'), // printed under "Hormat kami,"
        'initials'   => env('LETTER_COMPANY_INITIALS', 'KS'),
        'tagline'    => env('LETTER_COMPANY_TAGLINE', 'Clear. Trusted. Reliable'),
        'address'    => env('LETTER_COMPANY_ADDRESS', 'Jln. P. Suryanata, Gg. Kopta No. 47'),
        'city'       => env('LETTER_COMPANY_CITY', 'Samarinda'),
        'phone'      => env('LETTER_COMPANY_PHONE', '0811-8000-9787'),
        'email'      => env('LETTER_COMPANY_EMAIL', 'kisantra.official@gmail.com'),
        'license'    => env('LETTER_COMPANY_LICENSE', ''), // Izin Praktik Konsultan Pajak No.
    ],

    // Full-page A4 letterhead artwork (header + footer), relative to /public. Null for a plain page.
    'letterhead_image' => env('LETTER_LETTERHEAD_IMAGE', 'images/letterhead/kisantra-a4.jpg'),

    // Arial from the office template, embedded in PDFs. Files live in storage/fonts.
    'pdf_fonts' => [
        'regular'    => 'arial.ttf',
        'bold'       => 'arialbd.ttf',
        'italic'     => 'ariali.ttf',
        'bolditalic' => 'arialbi.ttf',
    ],

    // Number format tokens: {prefix} {seq} {code} {roman} {year}
    'number_format' => env('LETTER_NUMBER_FORMAT', '{prefix}-{seq}/{code}/{roman}/{year}'),
    'number_code'   => env('LETTER_NUMBER_CODE', 'KSN'),
    'sequence_digits' => 3,

    // Roles that may sign letters. Super-admin always may.
    'signer_roles' => ['project-manager', 'direktur', 'super-admin'],

    'pdf_disk' => 'public',
    'pdf_dir'  => 'letters',
];
