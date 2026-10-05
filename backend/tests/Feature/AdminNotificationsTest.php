<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\UserNotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminNotificationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_active_admins_can_list_platform_notifications(): void
    {
        $owner = User::factory()->create([
            'name' => 'Notification Owner',
            'email' => 'notification-owner@example.test',
        ]);
        app(UserNotificationService::class)->create(
            $owner,
            'account',
            'Account updated',
            'A simulated balance was changed.',
        );

        $this->getJson('/api/admin/notifications')->assertUnauthorized();

        foreach ([
            User::factory()->create(),
            User::factory()->create(['role' => 'admin', 'status' => 'suspended']),
        ] as $user) {
            $this->actingAs($user)->getJson('/api/admin/notifications')->assertForbidden();
        }

        $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->getJson('/api/admin/notifications')
            ->assertOk()
            ->assertJsonPath('data.0.category', 'account')
            ->assertJsonPath('data.0.title', 'Account updated')
            ->assertJsonPath('data.0.user.name', 'Notification Owner')
            ->assertJsonPath('data.0.user.email', 'notification-owner@example.test')
            ->assertJsonMissingPath('data.0.user.password');
    }

    public function test_admin_notification_filters_and_pagination_are_server_side(): void
    {
        $service = app(UserNotificationService::class);
        $target = User::factory()->create(['name' => 'Notification Target', 'email' => 'target@example.test']);
        $service->create($target, 'account', 'Balance changed', 'Admin adjusted the balance.');
        $read = $service->create($target, 'security', 'Password changed', 'The password was changed.');
        $read->forceFill(['read_at' => now()])->save();
        $service->create(User::factory()->create(), 'account', 'Another update', 'Another account update.');
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)
            ->getJson('/api/admin/notifications?search=target%40example.test&category=account&status=unread')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'Balance changed')
            ->assertJsonPath('data.0.read_at', null)
            ->assertJsonPath('meta.total', 1);

        for ($index = 0; $index < 15; $index++) {
            $service->create($target, 'financial', 'Notice '.$index, 'A financial notification.');
        }

        $this->actingAs($admin)
            ->getJson('/api/admin/notifications?per_page=5&page=2')
            ->assertOk()
            ->assertJsonPath('meta.per_page', 5)
            ->assertJsonPath('meta.current_page', 2)
            ->assertJsonPath('meta.total', 18)
            ->assertJsonCount(5, 'data');
    }

    public function test_listing_notifications_does_not_change_user_read_status(): void
    {
        $notification = app(UserNotificationService::class)->create(
            User::factory()->create(),
            'account',
            'Unread update',
            'This should remain unread.',
        );

        $this->actingAs(User::factory()->create(['role' => 'super_admin']))
            ->getJson('/api/admin/notifications')
            ->assertOk();

        $this->assertNull($notification->fresh()->read_at);
    }

    public function test_empty_results_and_invalid_filters_have_explicit_api_responses(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)
            ->getJson('/api/admin/notifications')
            ->assertOk()
            ->assertJsonCount(0, 'data')
            ->assertJsonPath('meta.total', 0);

        $this->actingAs($admin)
            ->getJson('/api/admin/notifications?status=archived')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('status');
    }
}
