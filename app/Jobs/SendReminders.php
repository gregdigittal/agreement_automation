<?php

namespace App\Jobs;

use App\Services\ReminderService;
use Illuminate\Support\Facades\Log;

class SendReminders extends TenantAwareJob
{
    public function handle(ReminderService $service): void
    {
        $count = $service->processReminders();
        Log::info("Sent {$count} reminders");
    }
}
