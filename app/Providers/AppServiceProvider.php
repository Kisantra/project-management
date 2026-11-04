<?php

namespace App\Providers;


use App\Models\DailyTask;
use App\Models\DailyTaskSubTask;
use App\Models\ProjectStatus;
use App\Models\ProjectStep;
use App\Models\TaxReport;
use App\Observers\DailyTaskObserver;
use App\Observers\DailyTaskSubTaskObserver;
use App\Observers\ProjectStatusObserver;
use App\Observers\ProjectStepObserver;
use App\Observers\TaxReportObserver;
use Illuminate\Support\ServiceProvider;
use Illuminate\Database\Eloquent\Model;
use Filament\Notifications\Notification;
use Filament\Support\Assets\Js;
use Filament\Support\Facades\FilamentAsset;
use Filament\Support\Facades\FilamentView;
use Filament\View\PanelsRenderHook;
use Illuminate\Support\Facades\Blade;
use Filament\Notifications\Livewire\DatabaseNotifications;
use App\Models\Invoice;
use App\Observers\InvoiceObserver;
use Illuminate\Support\Facades\Vite;
use Livewire\Livewire;

class AppServiceProvider extends ServiceProvider
{
    public $singletons = [
        \Filament\Http\Responses\Auth\Contracts\LoginResponse::class => \App\Http\Responses\LoginResponse::class,
        \Filament\Http\Responses\Auth\Contracts\LogoutResponse::class => \App\Http\Responses\LogoutResponse::class,
    ];
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Ensure the file cache directory tree exists on every boot.
        // Prevents "file_put_contents: No such file or directory" on Linux
        // servers where storage/framework/cache/data/ sub-folders are missing.
        if (config('cache.default') === 'file') {
            $cacheDataPath = config('cache.stores.file.path', storage_path('framework/cache/data'));
            if (!is_dir($cacheDataPath)) {
                mkdir($cacheDataPath, 0775, true);
            }
        }

        Model::unguard();
        Invoice::observe(InvoiceObserver::class);
        TaxReport::observe(TaxReportObserver::class);
        DailyTask::observe(DailyTaskObserver::class);
        ProjectStep::observe(ProjectStepObserver::class);
        ProjectStatus::observe(ProjectStatusObserver::class);
        // DailyTaskSubTask::observe(DailyTaskSubTaskObserver::class);

        DatabaseNotifications::trigger('filament.notifications.database-notifications-trigger');

        Livewire::component('tax-report.ppn.bulk-invoice-ai-import', \App\Livewire\TaxReport\Ppn\BulkInvoiceAIImport::class);

        // FilamentAsset::register([
        //     Js::make('chart-js-plugins', Vite::asset('resources/js/filament-chart-js-plugins.js'))->module(),
        // ]);
        FilamentAsset::register([
            Js::make('filament-notification-sounds', __DIR__ . '/../../resources/js/filament-notification-sounds.js'),
        ]);

        // BODY_END is also rendered on the login pages. This modal downloads,
        // removes and approves documents by raw ID, so it must never be handed
        // to a guest (see the boot() guard in GlobalDocumentModal as well).
        FilamentView::registerRenderHook(
            PanelsRenderHook::BODY_END,
            fn(): string => auth()->check()
                ? Blade::render('@livewire(\'projects.modals.global-document-modal\')')
                : '',
        );

        FilamentView::registerRenderHook(
            PanelsRenderHook::SIDEBAR_NAV_START,
            fn(): string => Blade::render('@livewire(\'profile.user-profile\')'),
        );

        FilamentView::registerRenderHook(
            PanelsRenderHook::GLOBAL_SEARCH_BEFORE,
            fn(): string => Blade::render('@livewire(\'notification.notification-button\')'),
        );
    }
}
