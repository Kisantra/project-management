<?php

namespace App\Support\Access;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * URL-based access for the admin panel. Every Spatie permission carries one or
 * more path patterns in `http_path` (for example `clients*` or `projects/create`);
 * a user may open a panel URL when one of their permissions matches it.
 *
 * The matching rules are a straight port of the former Filament Access
 * Management plugin so existing roles keep exactly the same access.
 */
final class PathPermission
{
    public const SUPER_ADMIN_ROLE = 'super-admin';

    /** Paths any signed-in user may open: dashboard, auth, error pages, assets. */
    public const PUBLIC_PATTERNS = ['', 'login', 'logout', 'error*', 'assets*'];

    /** Split a stored `http_path` into patterns (one per line, commas allowed). */
    public static function patterns(?string $httpPath): array
    {
        return collect(preg_split('/[\r\n,]+/', (string) $httpPath))
            ->map(fn (string $pattern) => trim(trim($pattern), '/'))
            ->filter(fn (string $pattern) => $pattern !== '')
            ->values()
            ->all();
    }

    /**
     * `clients` matches only `clients`; `clients*` matches anything starting with
     * it. A pattern ending in a slash and a star means "the list and one record":
     * it matches `clients` and `clients/12`, but not `clients/12/edit` (that
     * needs its own pattern) and never `clients/create`.
     */
    public static function matches(string $pattern, string $path): bool
    {
        $pattern = trim($pattern, '/');
        $path = trim($path, '/');

        if (! Str::contains($pattern, '*')) {
            return $path === $pattern;
        }

        if (Str::endsWith($pattern, '/*')) {
            $pattern = (string) Str::of($pattern)->beforeLast('/');

            if (! Str::endsWith($path, '/create')) {
                $path = (string) Str::of($path)->beforeLast('/');
            }
        }

        return Str::is($pattern, $path);
    }

    public static function isPublic(string $path): bool
    {
        return collect(self::PUBLIC_PATTERNS)->contains(fn (string $pattern) => self::matches($pattern, $path));
    }

    public static function isSuperAdmin(?Authenticatable $user): bool
    {
        return $user !== null && method_exists($user, 'hasRole') && $user->hasRole(self::SUPER_ADMIN_ROLE);
    }

    /**
     * Permissions (from the given list) whose patterns match the path.
     *
     * @param  iterable<\Spatie\Permission\Models\Permission>  $permissions
     */
    public static function matching(iterable $permissions, string $path): Collection
    {
        return collect($permissions)
            ->filter(fn ($permission) => collect(self::patterns($permission->http_path))
                ->contains(fn (string $pattern) => self::matches($pattern, $path)))
            ->values();
    }

    public static function allows(?Authenticatable $user, string $path): bool
    {
        if ($user === null) {
            return false;
        }

        if (self::isPublic($path) || self::isSuperAdmin($user)) {
            return true;
        }

        return self::matching($user->getAllPermissions(), $path)->isNotEmpty();
    }
}
