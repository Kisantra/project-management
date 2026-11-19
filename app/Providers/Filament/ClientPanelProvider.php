<?php

namespace App\Providers\Filament;

use App\Http\Middleware\RedirectToProperPanelMiddleware;
use App\Http\Middleware\UseIndonesianLocale;
use App\Support\Ui\Palette;
use Cmsmaxinc\FilamentErrorPages\FilamentErrorPagesPlugin;
use Devonab\FilamentEasyFooter\EasyFooterPlugin;
use EightCedars\FilamentInactivityGuard\FilamentInactivityGuardPlugin;
use Filament\FontProviders\GoogleFontProvider;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Enums\MaxWidth;
use Filament\View\PanelsRenderHook;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\HtmlString;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use Kisantra\Watchpost\Http\InjectBrowserScript;
use Njxqlus\FilamentProgressbar\FilamentProgressbarPlugin;
use TomatoPHP\FilamentPWA\Services\ManifestService;

/**
 * Client portal (/klien), docs/mockups/client-portal-redesign.html. Same frame
 * as the admin panel (light sidebar on the ground, content in one inset panel,
 * Kisantra palette and Manrope); one page per section in app/Filament/Client/Pages.
 *
 * Staff-only plugins stay off this panel on purpose: the user-activity plugin
 * listed every user's visited URLs and the PWA plugin brought the site
 * settings hub, both reachable by any client account.
 */
class ClientPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('klien')
            ->path('klien')
            ->colors(Palette::kisantra())
            ->font('Manrope', provider: GoogleFontProvider::class)
            ->brandName('Portal Klien')
            ->brandLogo(fn () => view('filament.brand.logo', ['title' => 'Portal Klien']))
            ->brandLogoHeight('2.25rem')
            ->homeUrl(fn () => \App\Filament\Client\Pages\DashboardClient::getUrl(panel: 'klien'))
            ->discoverPages(in: app_path('Filament/Client/Pages'), for: 'App\\Filament\\Client\\Pages')
            ->sidebarFullyCollapsibleOnDesktop()
            ->sidebarWidth('17rem')
            ->maxContentWidth(MaxWidth::Full)
            ->databaseNotifications(
                fn () => preg_match('/(android|iphone|ipad|mobile)/i', (string) request()->header('User-Agent'))
            )
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
                InjectBrowserScript::class,
            ])
            ->middleware([UseIndonesianLocale::class], isPersistent: true)
            ->plugins([
                FilamentInactivityGuardPlugin::make(),
                EasyFooterPlugin::make()
                    ->withFooterPosition('sidebar.footer')
                    ->withSentence(new HtmlString('<img src="' . asset('images/Logo/Logo Vertical.png') . '" style="margin-right:.5rem;" alt="" width="20" height="20"> Kisantra Management')),
                FilamentProgressbarPlugin::make()->color('#1790b5'),
                FilamentErrorPagesPlugin::make(),
            ])
            // Install-as-app meta (manifest, icons), without the PWA settings pages.
            ->renderHook(
                PanelsRenderHook::HEAD_END,
                fn () => view('filament-pwa::meta', ['config' => ManifestService::generate()]),
            )
            ->renderHook(
                PanelsRenderHook::SIDEBAR_NAV_START,
                fn () => view('filament.client.switcher-hook', ['variant' => 'sidebar']),
            )
            ->renderHook(
                PanelsRenderHook::TOPBAR_START,
                fn () => view('filament.client.topbar-start'),
            )
            ->renderHook(
                PanelsRenderHook::BODY_END,
                fn () => view('filament.client.mobile-nav'),
            )
            ->renderHook(
                PanelsRenderHook::BODY_END,
                fn () => view('filament.client.chat-floating'),
            )
            ->viteTheme('resources/css/filament/admin/theme.css')
            ->authMiddleware([
                RedirectToProperPanelMiddleware::class,
                Authenticate::class,
            ]);
    }
}
