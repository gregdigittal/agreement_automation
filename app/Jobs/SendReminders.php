<?php
namespace App\Jobs;

use App\Services\ReminderService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SendReminders implements ShouldQueue
{
    use Queueable;
    public function handle(ReminderService $service): void { $service->processDueReminders(); }
}
