<?php

namespace App\Services;

use App\Enums\SupportRequestStatus;
use App\Models\SupportRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Validation\ValidationException;

class SupportRequestService
{
    public function create(User $user, string $subject, string $message): SupportRequest
    {
        $subject = trim($subject);
        $message = trim($message);

        if ($subject === '' || mb_strlen($subject) > 255) {
            throw ValidationException::withMessages(['subject' => ['Subject must be between 1 and 255 characters.']]);
        }

        if ($message === '') {
            throw ValidationException::withMessages(['message' => ['A message is required.']]);
        }

        $request = new SupportRequest;
        $request->user()->associate($user);
        $request->subject = $subject;
        $request->message = $message;
        $request->status = SupportRequestStatus::Open;
        $request->save();

        return $request;
    }

    public function history(User $user): HasMany
    {
        return $user->supportRequests()->orderByDesc('created_at')->orderByDesc('id');
    }
}
