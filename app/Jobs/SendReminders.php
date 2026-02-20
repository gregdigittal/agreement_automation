<?php

namespace App\Jobs;

use App\Services\ReminderService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class SendReminders implements ShouldQueue
{
    use Queueable;

    public function handle(ReminderService $service): void
    {
        $count = $service->processReminders();
        Log::info("Sent {$count} reminders");
    }
}
