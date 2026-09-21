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
use Filament\Support\Colors\Color;
use Filament\Widgets;
use CharrafiMed\GlobalSearchModal\Customization\Position;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use DiogoGPinto\AuthUIEnhancer\AuthUIEnhancerPlugin;
use SolutionForest\FilamentAccessManagement\FilamentAccessManagementPanel;
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
            ->login()
            ->colors([
                'primary' => Color::Amber,
            ])
            ->navigationGroups([
                'Project Management',
                'Tax Management',
                'Tugas Harian',
                'Client Management',
                'Penyuratan',
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
                
            ])
            ->maxContentWidth(MaxWidth::Full)
            ->databaseNotifications(
                fn() =>
                preg_match('/(android|iphone|ipad|mobile)/i', request()->header('User-Agent'))
            )
            ->colors([
                'primary' => Color::Cyan,
            ])
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
                FilamentProgressbarPlugin::make()->color('#f59e0b'),
                FilamentAccessManagementPanel::make(),
                FilamentErrorPagesPlugin::make(),
                AuthUIEnhancerPlugin::make()
                    ->showEmptyPanelOnMobile(false)
                    ->formPanelPosition('right')
                    ->formPanelWidth('50%')
                    ->emptyPanelBackgroundImageOpacity('70%')
                    ->emptyPanelBackgroundImageUrl('https://images.unsplash.com/photo-1662808782878-941ea16adbdc?fm=jpg&q=60&w=3000&ixlib=rb-4.1.0&ixid=M3wxMjA3fDB8MHxzZWFyY2h8M3x8amFrYXJ0YSUyMGNpdHl8ZW58MHx8MHx8fDA%3D')
            ])
            ->brandLogo(asset('images/Logo/OnlyLogo.png'))
            ->brandLogoHeight('3rem')
            ->viteTheme('resources/css/filament/admin/theme.css')
            ->font('Plus Jakarta Sans', provider: GoogleFontProvider::class)
            ->authMiddleware([
                RedirectToProperPanelMiddleware::class,
                Authenticate::class,
            ]);
    }
}
