<x-filament-panels::page class="w-full">
    {{-- Patch notes — container closable di paling atas dashboard --}}
    @livewire('patch-notes-banner')

    <style>
        /* CSS Tambahan untuk Welcome Card */
        @keyframes float {

            0%,
            100% {
                transform: translateY(0px);
            }

            50% {
                transform: translateY(-10px);
            }
        }

        @keyframes glow {

            0%,
            100% {
                box-shadow: 0 0 20px rgba(59, 130, 246, 0.3);
            }

            50% {
                box-shadow: 0 0 30px rgba(59, 130, 246, 0.5);
            }
        }

        .animate-float {
            animation: float 3s ease-in-out infinite;
        }

        .animate-glow {
            animation: glow 2s ease-in-out infinite;
        }

        .welcome-card {
            position: relative;
            background: #f0f9ff;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .dark .welcome-card {
            background: #1e293b;
        }

        .welcome-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.15);
        }

        .dark .welcome-card:hover {
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.4);
        }

        /* Avatar hover effects */
        .avatar-container {
            transition: all 0.3s ease;
        }

        .avatar-container:hover {
            transform: scale(1.05);
        }

        /* Button hover effects */
        .action-button {
            transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            overflow: hidden;
        }

        /* Stats animation */
        .stat-item {
            transition: all 0.2s ease;
        }

        .stat-item:hover {
            transform: translateY(-2px);
        }

        /* Widget placeholder styles */
        .widget-placeholder {
            transition: all 0.3s ease;
        }

        .widget-placeholder:hover {
            transform: translateY(-2px);
        }

        /* Responsive improvements */
        @media (max-width: 640px) {
            .welcome-card {
                margin: 0 -1rem;
                border-radius: 0;
            }
        }
    </style>

    {{-- Greeting Card --}}
    @livewire('dashboard.widgets.greeting-card')

    {{-- Stats Overview --}}
    <div class="mt-6">
        @livewire('dashboard.widget.project-stats-overview')
    </div>

    {{-- Completed projects per month + most active users --}}
    <div class="mt-6 grid grid-cols-1 gap-6 items-stretch lg:grid-cols-3">
        <div class="h-full lg:col-span-2">
            @livewire('dashboard.widgets.completed-chart')
        </div>
        <div class="flex h-full flex-col gap-6 lg:col-span-1">
            @livewire('dashboard.widgets.upcoming-agenda')
            @livewire('dashboard.widgets.active-users')
        </div>
    </div>

    {{-- Tax filing status per masa pajak, 12 periods --}}
    <div class="mt-6">
        @livewire('dashboard.widgets.report-status-trend')
    </div>

    {{-- Recent Activity Feed - Full Width --}}
    <div class="mt-6">
        @livewire('dashboard.widgets.recent-activity-feed')
    </div>

</x-filament-panels::page>