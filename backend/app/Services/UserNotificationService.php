<?php

namespace App\Services;

use App\Models\User;
use App\Models\UserNotification;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Validation\ValidationException;

class UserNotificationService
{
    public function create(User $user, string $category, string $title, string $message): UserNotification
    {
        $category = trim($category);
        $title = trim($title);
        $message = trim($message);

        if ($category === '' || mb_strlen($category) > 100) {
            throw ValidationException::withMessages(['category' => ['Category must be between 1 and 100 characters.']]);
        }

        if ($title === '' || mb_strlen($title) > 255) {
            throw ValidationException::withMessages(['title' => ['Title must be between 1 and 255 characters.']]);
        }

        if ($message === '') {
            throw ValidationException::withMessages(['message' => ['A message is required.']]);
        }

        $notification = new UserNotification;
        $notification->user()->associate($user);
        $notification->category = $category;
        $notification->title = $title;
        $notification->message = $message;
        $notification->save();

        return $notification;
    }

    public function history(User $user): HasMany
    {
        return $user->notifications()->orderByDesc('created_at')->orderByDesc('id');
    }

    public function markRead(User $user, int $notificationId): UserNotification
    {
        $notification = $user->notifications()->whereKey($notificationId)->first();

        if (! $notification) {
            throw (new ModelNotFoundException)->setModel(UserNotification::class, [$notificationId]);
        }

        if ($notification->read_at === null) {
            $notification->read_at = now();
            $notification->save();
        }

        return $notification;
    }

    public function markAllRead(User $user): int
    {
        return $user->notifications()
            ->whereNull('read_at')
            ->update(['read_at' => now(), 'updated_at' => now()]);
    }
}
