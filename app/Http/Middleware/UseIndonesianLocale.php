<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The client portal (/klien) is used only by Indonesian clients, so Filament's
 * own text (table paging, filters, modals, dates) is shown in Indonesian there.
 * The admin panel keeps the app locale until that is decided separately.
 * Registered as persistent so Livewire updates get it too.
 */
class UseIndonesianLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        app()->setLocale('id');

        return $next($request);
    }
}
