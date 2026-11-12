<?php

namespace App\Http\Middleware;

use App\Filament\Pages\ErrorPage;
use App\Support\Access\PathPermission;
use Closure;
use Filament\Facades\Filament;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Blocks admin-panel URLs the signed-in user has no permission for (see
 * PathPermission). Guests pass through; Filament's own Authenticate sends
 * them to the login page.
 */
class AuthorizePanelPath
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = Filament::auth()->user();

        if ($user === null || PathPermission::allows($user, $request->path())) {
            return $next($request);
        }

        if ($request->ajax() || $request->expectsJson()) {
            abort(403);
        }

        return redirect()->to(ErrorPage::getUrl(['code' => 403]));
    }
}
