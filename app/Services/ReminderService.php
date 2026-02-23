<?php

namespace App\Services;

use App\Mail\ContractReminderCalendar;
use App\Models\ContractKeyDate;
use App\Models\Notification;
use App\Models\Reminder;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

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
            if (! $contract) {
                continue;
            }

            if ($reminder->channel === 'calendar') {
                if (! $this->sendCalendarInvite($reminder, $contract)) {
                    continue;
                }
            } else {
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
            }

            $reminder->update([
                'last_sent_at' => now(),
                'next_due_at' => Carbon::parse($reminder->next_due_at)->addDays($reminder->lead_days),
            ]);
            $count++;
        }
        return $count;
    }

    private function sendCalendarInvite(Reminder $reminder, \App\Models\Contract $contract): bool
    {
        $keyDate = $reminder->key_date_id
            ? ContractKeyDate::find($reminder->key_date_id)
            : null;

        if (! $keyDate) {
            Log::warning('Calendar reminder skipped — no key_date_id', ['reminder_id' => $reminder->id]);
            return false;
        }

        $recipient = $reminder->recipient_email;
        if (! $recipient) {
            Log::warning('Calendar reminder skipped — no recipient_email', ['reminder_id' => $reminder->id]);
            return false;
        }

        Mail::to($recipient)->send(new ContractReminderCalendar($reminder, $contract, $keyDate));
        return true;
    }
}
