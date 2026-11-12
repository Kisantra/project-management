<?php

namespace App\Providers\Filament;


use App\Filament\Pages\DailyTask\DailyTaskList;
use App\Filament\Pages\DailyTask\DailyTaskDashboard;
use App\Filament\Pages\TaxChat;
use App\Filament\Pages\ClientCommunication\Index;
use Filament\FontProviders\GoogleFontProvider;
// use App\Filament\Resources\TaxReportResource\Pages\TaxReportDashboard;
use App\Http\Middleware\RedirectToProperPanelMiddleware;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Widgets;
use CharrafiMed\GlobalSearchModal\Customization\Position;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use Kisantra\Watchpost\Http\InjectBrowserScript;
use CharrafiMed\GlobalSearchModal\GlobalSearchModalPlugin;
use Devonab\FilamentEasyFooter\EasyFooterPlugin;
use Cmsmaxinc\FilamentErrorPages\FilamentErrorPagesPlugin;
use Illuminate\Support\HtmlString;
use Filament\Navigation\NavigationGroup;
use Filament\Support\Enums\MaxWidth;
use Kenepa\Banner\BannerPlugin;
use Njxqlus\FilamentProgressbar\FilamentProgressbarPlugin;
use Leandrocfe\FilamentApexCharts\FilamentApexChartsPlugin;
use EightCedars\FilamentInactivityGuard\FilamentInactivityGuardPlugin;
use Edwink\FilamentUserActivity\FilamentUserActivityPlugin;
use Moataz01\FilamentNotificationSound\FilamentNotificationSoundPlugin;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('')
            ->login(\App\Filament\Auth\Login::class)
            ->colors(self::palette())
            ->navigationGroups([
                'Project Management',
                'Tax Management',
                'Tugas Harian',
                'Client Management',
                'Standard Operating Procedures',
                'Master Data',
                'Administration',
            ])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\\Filament\\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\\Filament\\Pages')
            ->discoverClusters(in: app_path('Filament/Clusters'), for: 'App\\Filament\\Clusters')
            ->pages([
                // TaxReportDashboard::class,
                DailyTaskDashboard::class,
                DailyTaskList::class,
                TaxChat::class,
                Index::class,
                // Pages\Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\\Filament\\Widgets')
            ->widgets([
                Widgets\AccountWidget::class,
                Widgets\FilamentInfoWidget::class,
            ])
            ->sidebarFullyCollapsibleOnDesktop()
            ->sidebarWidth('17rem')
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
                \App\Http\Middleware\AuthorizePanelPath::class,
                
            ])
            ->maxContentWidth(MaxWidth::Full)
            ->databaseNotifications(
                fn() =>
                preg_match('/(android|iphone|ipad|mobile)/i', request()->header('User-Agent'))
            )
            ->plugins([
                FilamentNotificationSoundPlugin::make()
                    ->soundPath('sounds/notification.mp3') // Custom sound path
                    ->volume(0.8) // Volume (0.0 to 1.0)
                    ->showAnimation(true) // Show animation on notification badge
                    ->enabled(true), // Enable/disable the plugin
                FilamentInactivityGuardPlugin::make(),
                FilamentUserActivityPlugin::make(),
                \TomatoPHP\FilamentPWA\FilamentPWAPlugin::make(),
                FilamentApexChartsPlugin::make(),
                EasyFooterPlugin::make()
                    ->withFooterPosition('sidebar.footer')  
                    ->withSentence(new HtmlString('<img src="' . asset('images/Logo/Logo Vertical.png') . '" style="margin-right:.5rem;" alt="Laravel Logo" width="20" height="20"> Kisantra Management')),
                GlobalSearchModalPlugin::make(),
                BannerPlugin::make()
                    ->persistsBannersInDatabase(),
                FilamentProgressbarPlugin::make()->color('#1790b5'),
                FilamentErrorPagesPlugin::make(),
            ])
            ->renderHook(
                'panels::body.end',
                fn() => view('filament.components.patch-notes-modal-hook'),
            )
            ->renderHook(
                'panels::user-menu.profile.after',
                fn() => view('filament.components.patch-notes-menu-item'),
            )
            ->brandLogo(fn () => view('filament.brand.logo'))
            ->brandLogoHeight('2.25rem')
            ->viteTheme('resources/css/filament/admin/theme.css')
            ->font('Manrope', provider: GoogleFontProvider::class)
            ->authMiddleware([
                RedirectToProperPanelMiddleware::class,
                Authenticate::class,
            ]);
    }

    /**
     * Kisantra family palette, see DESIGN.md. Primary 500/600/700 are the
     * Project Management tile (#1790b5), action (#157a9c) and arm (#10657f)
     * blues. Gray is tinted toward the same hue, so custom views written with
     * gray-* utilities follow the theme. Success and info share the primary
     * blue (Three Voices Rule); warning keeps Filament's amber until each
     * page is migrated.
     */
    private static function palette(): array
    {
        $blue = [
            50 => '238, 249, 253',
            100 => '223, 244, 254',
            200 => '188, 232, 248',
            300 => '137, 210, 234',
            400 => '74, 180, 210',
            500 => '23, 144, 181',
            600 => '21, 122, 156',
            700 => '16, 101, 127',
            800 => '0, 80, 101',
            900 => '5, 59, 77',
            950 => '4, 38, 50',
        ];

        return [
            'primary' => $blue,
            'success' => $blue,
            'info' => $blue,
            'gray' => [
                50 => '241, 247, 249',
                100 => '235, 242, 244',
                200 => '222, 229, 231',
                300 => '204, 212, 215',
                400 => '139, 148, 151',
                500 => '98, 106, 111',
                600 => '72, 81, 86',
                700 => '54, 63, 67',
                800 => '39, 47, 52',
                900 => '21, 28, 32',
                950 => '13, 20, 23',
            ],
            'danger' => [
                50 => '255, 244, 242',
                100 => '255, 230, 225',
                200 => '255, 208, 199',
                300 => '254, 172, 158',
                400 => '239, 128, 111',
                500 => '207, 66, 50',
                600 => '193, 62, 46',
                700 => '175, 54, 40',
                800 => '137, 43, 32',
                900 => '104, 35, 27',
                950 => '64, 20, 14',
            ],
        ];
    }
}
