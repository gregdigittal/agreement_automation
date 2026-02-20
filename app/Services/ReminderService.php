<?php

namespace App\Services;

use App\Models\Notification;
use App\Models\Reminder;
use Carbon\Carbon;

class ReminderService
{
    public function processReminders(): int
    {
        $due = Reminder::where('is_active', true)
            ->where('next_due_at', '<=', now())
            ->where(function ($q) {
                $q->whereNull('last_sent_at')
                    ->orWhereRaw('last_sent_at < next_due_at');
            })
            ->with('contract')
            ->get();

        $count = 0;
        foreach ($due as $reminder) {
            $contract = $reminder->contract;
            $recipient = $reminder->recipient_email ?? $contract->created_by ?? null;
            if ($recipient) {
                app(NotificationService::class)->create([
                    'recipient_email' => $recipient,
                    'recipient_user_id' => $reminder->recipient_user_id,
                    'channel' => $reminder->channel ?? 'email',
                    'subject' => "Reminder: {$reminder->reminder_type}",
                    'body' => "Contract: " . ($contract->title ?? $contract->id) . ". Type: {$reminder->reminder_type}. Lead days: {$reminder->lead_days}.",
                    'related_resource_type' => 'contract',
                    'related_resource_id' => $reminder->contract_id,
                    'status' => 'pending',
                ]);
            }
            $reminder->update([
                'last_sent_at' => now(),
                'next_due_at' => Carbon::parse($reminder->next_due_at)->addDays($reminder->lead_days),
            ]);
            $count++;
        }
        return $count;
    }
}
