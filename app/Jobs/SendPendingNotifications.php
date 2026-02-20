<?php

namespace App\Jobs;

use App\Mail\NotificationMail;
use App\Models\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Mail;

class SendPendingNotifications implements ShouldQueue
{
    use Queueable;

    public function handle(): void
    {
        $notifications = Notification::where('status', 'pending')
            ->whereNotNull('recipient_email')
            ->limit(50)
            ->get();

        foreach ($notifications as $notification) {
            try {
                Mail::to($notification->recipient_email)
                    ->send(new NotificationMail($notification));
                $notification->update(['status' => 'sent', 'sent_at' => now()]);
            } catch (\Exception $e) {
                $notification->update(['status' => 'failed', 'error_message' => $e->getMessage()]);
            }
        }
    }
}
