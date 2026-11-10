<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'mailgun' => [
        'domain' => env('MAILGUN_DOMAIN'),
        'secret' => env('MAILGUN_SECRET'),
        'endpoint' => env('MAILGUN_ENDPOINT', 'api.mailgun.net'),
        'scheme' => 'https',
    ],

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    // Serah terima klien dari aplikasi Client Management (marketing).
    // Token yang sama dipakai dua arah: CM -> PM (Bearer ke /api/v1/intake)
    // dan PM -> CM (Bearer ke /api/pm/...). Nilainya = PROJECT_MANAGEMENT_TOKEN di CM.
    'client_management' => [
        'url' => env('CLIENT_MANAGEMENT_URL'),
        'token' => env('CLIENT_MANAGEMENT_TOKEN'),
        'timeout' => (int) env('CLIENT_MANAGEMENT_TIMEOUT', 10),

        // Penerima notifikasi serah terima. Nama role yang tidak ada di
        // database diabaikan, jadi aman mencantumkan variasi penamaan.
        'notify_roles' => ['super-admin', 'direktur', 'director', 'manager', 'project-manager'],
    ],

];
