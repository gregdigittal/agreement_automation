<?php

namespace App\Jobs;

use App\Models\Notification;
use App\Services\NotificationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SendPendingNotifications implements ShouldQueue
{
    use Queueable;

    public function handle(): void
    {
        $notifications = Notification::where('status', 'pending')
            ->limit(50)
            ->get();

        $service = app(NotificationService::class);

        foreach ($notifications as $notification) {
            try {
                $service->sendNotification($notification);
            } catch (\Exception $e) {
                // sendNotification already updates status to failed and error_message
                // Re-throw only if we want the job to fail; otherwise continue with next
            }
        }
    }
}
