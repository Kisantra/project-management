<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Melindungi endpoint yang dipanggil aplikasi Client Management (CM).
 * CM mengirim `Authorization: Bearer <token>` dengan token bersama
 * (services.client_management.token).
 */
class VerifyClientManagementToken
{
    public function handle(Request $request, Closure $next): Response
    {
        // CM tidak selalu mengirim Accept: application/json; paksa supaya error
        // validasi (422) dan 404 tetap berupa JSON, bukan redirect/HTML.
        $request->headers->set('Accept', 'application/json');

        $expected = (string) config('services.client_management.token');

        // Token kosong berarti integrasi belum dikonfigurasi: tolak semua,
        // jangan sampai string kosong cocok dengan header kosong.
        if ($expected === '') {
            return response()->json(['message' => 'Integrasi Client Management belum dikonfigurasi.'], 503);
        }

        $given = $request->bearerToken();

        if (! is_string($given) || ! hash_equals($expected, $given)) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        return $next($request);
    }
}
