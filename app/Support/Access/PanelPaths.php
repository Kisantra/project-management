<?php

namespace App\Support\Access;

use Illuminate\Routing\Route;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Route as Router;

/**
 * The admin panel's GET pages as concrete sample paths, so permission
 * patterns can be previewed against real routes. Route parameters are filled
 * with "12" for matching and shown as "{id}".
 */
final class PanelPaths
{
    /** @return Collection<int, array{path: string, label: string}> */
    public static function all(string $panel = 'admin'): Collection
    {
        return collect(Router::getRoutes()->getRoutes())
            ->filter(fn (Route $route) => str_starts_with((string) $route->getName(), "filament.{$panel}.")
                && in_array('GET', $route->methods(), true))
            ->map(function (Route $route) {
                $uri = trim($route->uri(), '/');
                $label = preg_replace(['/\{[a-zA-Z_]+\?\}/', '/\{[a-zA-Z_]+\}/'], ['', '{id}'], $uri);
                $path = str_replace('{id}', '12', $label);

                return ['path' => trim($path, '/'), 'label' => '/' . trim($label, '/')];
            })
            ->unique('path')
            ->sortBy('path')
            ->values();
    }

    /** Sample paths matched by any of the given patterns. */
    public static function matching(array $patterns, string $panel = 'admin'): Collection
    {
        return self::all($panel)
            ->filter(fn (array $route) => collect($patterns)->contains(fn (string $pattern) => PathPermission::matches($pattern, $route['path'])))
            ->values();
    }
}
