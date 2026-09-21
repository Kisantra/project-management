<?php

namespace App\Filament\Pages\DailyTask;

use Filament\Pages\Page;

class DailyTaskDashboard extends Page
{
    protected static ?string $navigationGroup = 'Tugas Harian';
    
    protected static ?string $title = '';
    
    protected static ?string $navigationLabel = 'Dashboard';

    protected static ?int $navigationSort = 1;
    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    protected static string $view = 'filament.pages.daily-task.daily-task-dashboard';

    public static function shouldRegisterNavigation(): bool
    {
        // Hidden from the sidebar for now (menu parked); the page itself stays reachable.
        return false;
    }
    
    public static function canAccess(): bool
    {
        return auth()->user()->can('daily-task.dashboard.*');
    }
}
