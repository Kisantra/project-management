<?php

namespace App\Console\Commands;

use App\Services\CalendarService;
use Illuminate\Console\Command;

class SendCalendarReminders extends Command
{
    protected $signature = 'calendar:send-reminders';

    protected $description = 'Kirim pengingat acara kalender yang sudah jatuh waktu ke pesertanya';

    public function handle(CalendarService $service): int
    {
        $sent = $service->sendDueReminders();

        $this->info("{$sent} pengingat dikirim.");

        return self::SUCCESS;
    }
}
