<?php

namespace App\Services;

use App\Models\Notification;
use App\Models\User;
use Illuminate\Support\Collection;

class NotificationService
{
    public function listNotifications(User $user, bool $unreadOnly = false): Collection
    {
        $query = Notification::where(function ($q) use ($user) {
            $q->where('recipient_user_id', $user->id)
                ->orWhere('recipient_email', $user->email);
        });
        if ($unreadOnly) {
            $query->whereNull('read_at');
        }
        return $query->orderByDesc('created_at')->get();
    }

    public function markRead(string $notificationId, User $user): void
    {
        Notification::where('id', $notificationId)
            ->where(function ($q) use ($user) {
                $q->where('recipient_user_id', $user->id)->orWhere('recipient_email', $user->email);
            })
            ->update(['read_at' => now()]);
    }

    public function markAllRead(User $user): int
    {
        return Notification::whereNull('read_at')
            ->where(function ($q) use ($user) {
                $q->where('recipient_user_id', $user->id)->orWhere('recipient_email', $user->email);
            })
            ->update(['read_at' => now()]);
    }

    public function create(array $data): Notification
    {
        return Notification::create(array_merge($data, [
            'status' => $data['status'] ?? 'pending',
            'created_at' => $data['created_at'] ?? now(),
        ]));
    }
    /**
     * Send a single notification by its channel; update status and sent_at or error_message.
     */
    public function sendNotification(Notification $notification): void
    {
        try {
            $this->dispatchChannel(
                $notification->channel,
                $notification->subject,
                $notification->body ?? '',
                $notification->recipient_user_id,
                $notification->recipient_email
            );
            $notification->update(['status' => 'sent', 'sent_at' => now()]);
        } catch (\Throwable $e) {
            $notification->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Route by channel: email, teams, or in_app (no-op for in_app when sending from job).
     */
    private function dispatchChannel(
        string $channel,
        string $subject,
        string $body,
        ?string $userId,
        ?string $recipientEmail
    ): void {
        match ($channel) {
            'email' => $this->sendEmail($recipientEmail, $subject, $body),
            'teams' => $this->sendTeams($subject, $body),
            default => \Illuminate\Support\Facades\Log::warning("Unknown or unhandled notification channel: {$channel}"),
        };
    }

    private function sendEmail(?string $email, string $subject, string $body): void
    {
        if (! $email) {
            return;
        }
        \Illuminate\Support\Facades\Mail::to($email)
            ->send(new \App\Mail\NotificationMail((object) ['subject' => $subject, 'body' => $body]));
    }

    private function sendTeams(string $subject, string $body): void
    {
        app(TeamsNotificationService::class)->sendToChannel($subject, $body);
    }
}
