<?php

namespace App\Services;

use App\Models\Notification;
use Illuminate\Support\Facades\Mail;

class NotificationService
{
    public function create(string $recipientEmail, string $subject, string $body, string $channel = 'email', ?string $resourceType = null, ?string $resourceId = null): Notification
    {
        return Notification::create([
            'recipient_email' => $recipientEmail,
            'channel' => $channel,
            'subject' => $subject,
            'body' => $body,
            'related_resource_type' => $resourceType,
            'related_resource_id' => $resourceId,
            'status' => 'pending',
            'created_at' => now(),
        ]);
    }

    public function sendPending(): int
    {
        $pending = Notification::where('status', 'pending')
            ->where('channel', 'email')
            ->limit(50)
            ->get();

        $sent = 0;
        foreach ($pending as $notification) {
            try {
                Mail::raw($notification->body, function ($message) use ($notification) {
                    $message->to($notification->recipient_email)
                        ->subject($notification->subject);
                });
                $notification->update(['status' => 'sent', 'sent_at' => now()]);
                $sent++;
            } catch (\Exception $e) {
                $notification->update(['status' => 'failed', 'error_message' => $e->getMessage()]);
            }
        }
        return $sent;
    }
}
