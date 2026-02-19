<?php

namespace App\Services;

use App\Models\Reminder;

class ReminderService
{
    public function processDueReminders(): int
    {
        $due = Reminder::where('is_active', true)
            ->where('next_due_at', '<=', now())
            ->with('contract', 'keyDate')
            ->limit(100)
            ->get();

        $notificationService = app(NotificationService::class);
        $processed = 0;

        foreach ($due as $reminder) {
            $contractTitle = $reminder->contract->title ?? 'Contract';
            $dateInfo = $reminder->keyDate?->date_value?->format('Y-m-d') ?? 'N/A';

            $notificationService->create(
                $reminder->recipient_email ?? $reminder->contract->created_by ?? 'unknown@example.com',
                "Reminder: {$reminder->reminder_type} for {$contractTitle}",
                "This is a reminder for contract '{$contractTitle}'. Key date: {$dateInfo}. Type: {$reminder->reminder_type}.",
                $reminder->channel,
                'contract',
                $reminder->contract_id
            );

            $reminder->update(['last_sent_at' => now()]);
            $processed++;
        }
        return $processed;
    }
}
