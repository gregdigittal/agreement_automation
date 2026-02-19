<?php
namespace App\Jobs;

use App\Services\NotificationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SendPendingNotifications implements ShouldQueue
{
    use Queueable;
    public function handle(NotificationService $service): void { $service->sendPending(); }
}
