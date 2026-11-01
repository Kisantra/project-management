<?php

namespace App\Providers\Filament;

use App\Http\Middleware\RedirectToProperPanelMiddleware;
use Edwink\FilamentUserActivity\FilamentUserActivityPlugin;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Widgets;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use Kisantra\Watchpost\Http\InjectBrowserScript;
use Filament\Support\Enums\MaxWidth;
use EightCedars\FilamentInactivityGuard\FilamentInactivityGuardPlugin;
use Leandrocfe\FilamentApexCharts\FilamentApexChartsPlugin;
use Devonab\FilamentEasyFooter\EasyFooterPlugin;
use Njxqlus\FilamentProgressbar\FilamentProgressbarPlugin;
use Cmsmaxinc\FilamentErrorPages\FilamentErrorPagesPlugin;
use Illuminate\Support\HtmlString;

class ClientPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('klien')
            ->path('klien')
            ->colors([
                'primary' => Color::Cyan,
            ])
            ->discoverResources(in: app_path('Filament/Client/Resources'), for: 'App\\Filament\\Client\\Resources')
            ->discoverPages(in: app_path('Filament/Client/Pages'), for: 'App\\Filament\\Client\\Pages')
            ->pages([
                // Pages\Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Client/Widgets'), for: 'App\\Filament\\Client\\Widgets')
            ->widgets([
                Widgets\AccountWidget::class,
                Widgets\FilamentInfoWidget::class,
            ])
            ->databaseNotifications(
                fn() =>
                preg_match('/(android|iphone|ipad|mobile)/i', request()->header('User-Agent'))
            )
            ->maxContentWidth(MaxWidth::Full)
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
            ->topNavigation()
            ->plugins([
                FilamentInactivityGuardPlugin::make(),
                FilamentUserActivityPlugin::make(),
                \TomatoPHP\FilamentPWA\FilamentPWAPlugin::make(),
                FilamentApexChartsPlugin::make(),
                EasyFooterPlugin::make()
                    ->withFooterPosition('sidebar.footer')
                    ->withSentence(new HtmlString('<img src="' . asset('images/Logo/Logo Vertical.png') . '" style="margin-right:.5rem;" alt="Laravel Logo" width="20" height="20"> Kisantra Management')),
                FilamentProgressbarPlugin::make()->color('#f59e0b'),
                FilamentErrorPagesPlugin::make(),

            ])
            ->renderHook(
                'panels::topbar.start',
                fn() => view('livewire.client.panel.client-switcher-hook'),
            )
            ->renderHook(
                'panels::body.end',
                fn() => view('filament.client.chat-floating'),
            )
            ->brandLogo(asset('images/Logo/OnlyLogo.png'))
            ->brandLogoHeight('3rem')
            ->viteTheme('resources/css/filament/admin/theme.css')
            ->authMiddleware([
                RedirectToProperPanelMiddleware::class,
                Authenticate::class,
            ]);
    }
}
