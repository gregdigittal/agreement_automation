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
}
