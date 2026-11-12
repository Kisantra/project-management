<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;

/**
 * Panel error page (/error/{code}). The access middleware redirects here on
 * 403; it replaces the page that came with the old access plugin.
 */
class ErrorPage extends Page
{
    protected static string $view = 'filament.pages.error';

    protected static bool $shouldRegisterNavigation = false;

    public ?int $code = null;

    public static function getSlug(): string
    {
        return 'error/{code?}';
    }

    public function mount(?int $code = null): void
    {
        $this->code = $code ?? 403;
    }

    public function getTitle(): string
    {
        return '';
    }

    protected function getViewData(): array
    {
        return [
            'code' => $this->code,
            'errorMessage' => 'Error',
        ];
    }
}
