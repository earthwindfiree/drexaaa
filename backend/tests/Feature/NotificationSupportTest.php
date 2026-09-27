<?php

namespace Tests\Feature;

use App\Enums\SupportRequestStatus;
use App\Models\SupportRequest;
use App\Models\User;
use App\Services\SupportRequestService;
use App\Services\UserNotificationService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificationSupportTest extends TestCase
{
    use RefreshDatabase;

    public function test_notification_creation_is_scoped_to_its_user(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $notification = app(UserNotificationService::class)->create($owner, 'account', 'Account update', 'Your account changed.');
        $service = app(UserNotificationService::class);

        $this->assertSame($owner->id, $notification->user_id);
        $this->assertCount(1, $service->history($owner)->get());
        $this->assertCount(0, $service->history($otherUser)->get());
    }

    public function test_notifications_can_be_marked_read_individually_and_all_at_once(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $service = app(UserNotificationService::class);
        $first = $service->create($user, 'security', 'Security notice', 'Review account security.');
        $second = $service->create($user, 'account', 'Account notice', 'Review account details.');
        $foreign = $service->create($otherUser, 'account', 'Private notice', 'Belongs to another user.');

        $read = $service->markRead($user, $first->id);
        $this->assertNotNull($read->read_at);

        try {
            $service->markRead($user, $foreign->id);
            $this->fail('A user must not mark another user notification as read.');
        } catch (ModelNotFoundException) {
        }

        $this->assertSame(1, $service->markAllRead($user));
        $this->assertNotNull($second->fresh()->read_at);
        $this->assertNull($foreign->fresh()->read_at);
    }

    public function test_notification_history_is_newest_first(): void
    {
        $user = User::factory()->create();
        $service = app(UserNotificationService::class);
        $older = $service->create($user, 'account', 'Older', 'Earlier notification.');
        $newer = $service->create($user, 'account', 'Newer', 'Later notification.');

        $ordered = $service->history($user)->get();

        $this->assertSame([$newer->id, $older->id], $ordered->pluck('id')->all());
    }

    public function test_support_request_creation_defaults_to_open_and_is_user_scoped(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $service = app(SupportRequestService::class);
        $request = $service->create($owner, 'Cannot access account', 'Please help restore account access.');

        $this->assertSame($owner->id, $request->user_id);
        $this->assertSame(SupportRequestStatus::Open, $request->status);
        $this->assertCount(1, $service->history($owner)->get());
        $this->assertCount(0, $service->history($otherUser)->get());
    }

    public function test_support_request_status_can_represent_open_and_resolved(): void
    {
        $user = User::factory()->create();
        $open = app(SupportRequestService::class)->create($user, 'Question', 'Need help with my profile.');

        $open->status = SupportRequestStatus::Resolved;
        $open->save();

        $this->assertSame(SupportRequestStatus::Resolved, $open->fresh()->status);
    }

    public function test_user_created_support_request_does_not_accept_status_from_mass_assignment(): void
    {
        $request = new SupportRequest;

        $this->assertFalse($request->isFillable('status'));
        $this->assertFalse($request->isFillable('user_id'));
    }
}
