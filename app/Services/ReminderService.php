<?php

namespace App\Services;

use App\Mail\ContractReminderCalendar;
use App\Models\ContractKeyDate;
use App\Models\Reminder;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class ReminderService
{
    public function processDueReminders(): int
    {
        $due = Reminder::where('is_active', true)
            ->where('next_due_at', '<=', now())
            ->with('contract', 'keyDate')
            ->limit(100)
            ->get();

        $processed = 0;
        foreach ($due as $reminder) {
            $contract = $reminder->contract;
            if (!$contract) continue;

            $this->dispatchReminderChannel($reminder, $contract);
            $reminder->update(['last_sent_at' => now()]);
            $processed++;
        }
        return $processed;
    }

    private function dispatchReminderChannel(Reminder $reminder, $contract): void
    {
        $keyDate = $reminder->keyDate;

        match ($reminder->channel) {
            'email' => $this->sendReminderEmail($reminder, $contract),
            'teams' => $this->sendReminderToTeams($reminder, $contract),
            'calendar' => $keyDate ? $this->sendCalendarInvite($reminder, $contract, $keyDate) : null,
            default => Log::warning("Unknown reminder channel: {$reminder->channel}"),
        };
    }

    private function sendReminderEmail(Reminder $reminder, $contract): void
    {
        $recipient = $reminder->recipient_email ?? $contract->created_by ?? 'unknown@example.com';
        $dateInfo = $reminder->keyDate?->date_value ?? 'N/A';

        app(NotificationService::class)->create(
            $recipient,
            "Reminder: {$reminder->reminder_type} for {$contract->title}",
            "Contract '{$contract->title}'. Key date: {$dateInfo}. Type: {$reminder->reminder_type}.",
            'email', 'contract', $contract->id
        );
    }

    private function sendReminderToTeams(Reminder $reminder, $contract): void
    {
        app(TeamsNotificationService::class)->sendNotification(
            "Reminder: {$reminder->reminder_type}",
            "Contract '{$contract->title}' has a due reminder."
        );
    }

    private function sendCalendarInvite(Reminder $reminder, $contract, ContractKeyDate $keyDate): void
    {
        $recipient = $reminder->recipient_email;
        if (!$recipient) {
            Log::warning('Calendar reminder skipped — no recipient_email', ['reminder_id' => $reminder->id]);
            return;
        }
        Mail::to($recipient)->send(new ContractReminderCalendar($reminder, $contract, $keyDate));
    }
}
