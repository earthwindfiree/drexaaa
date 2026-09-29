<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\AuditLog;
use App\Models\Strategy;
use App\Models\Tier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminUsersTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_normal_users_and_suspended_admins_are_rejected(): void
    {
        $this->getJson('/api/admin/users')->assertUnauthorized();

        foreach ([
            User::factory()->create(),
            User::factory()->create(['role' => 'admin', 'status' => 'suspended']),
        ] as $user) {
            $this->actingAs($user)->getJson('/api/admin/users')->assertForbidden();
        }
    }

    public function test_active_admins_can_list_users_without_sensitive_fields(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $user = User::factory()->create(['name' => 'Visible User', 'email' => 'visible@example.com']);

        $this->actingAs($admin)
            ->getJson('/api/admin/users')
            ->assertOk()
            ->assertJsonPath('data.0.id', $user->id)
            ->assertJsonPath('data.0.name', 'Visible User')
            ->assertJsonMissingPath('data.0.password')
            ->assertJsonMissingPath('data.0.remember_token')
            ->assertJsonMissingPath('data.0.personal_access_tokens')
            ->assertJsonStructure(['data', 'meta' => ['current_page', 'last_page', 'per_page', 'total']]);
    }

    public function test_super_admin_can_list_account_and_tier_summary_data(): void
    {
        $admin = User::factory()->create(['role' => 'super_admin']);
        $user = User::factory()->create();
        $strategy = Strategy::create([
            'name' => 'Users Strategy',
            'description' => 'Users test strategy.',
            'risk_profile' => 'moderate',
        ]);
        $tier = Tier::create([
            'name' => 'Users Tier',
            'minimum_balance' => '100.00',
            'strategy_id' => $strategy->id,
            'description' => 'Users test tier.',
        ]);
        $account = new Account;
        $account->forceFill([
            'user_id' => $user->id,
            'managed_balance' => '1250.00',
            'pending_balance' => '75.00',
            'tier_id' => $tier->id,
            'trading_status' => 'active',
        ])->save();

        $this->actingAs($admin)
            ->getJson('/api/admin/users')
            ->assertOk()
            ->assertJsonPath('data.0.account.id', $account->id)
            ->assertJsonPath('data.0.account.managed_balance', '1250.00')
            ->assertJsonPath('data.0.account.pending_balance', '75.00')
            ->assertJsonPath('data.0.account.tier.id', $tier->id)
            ->assertJsonPath('data.0.account.tier.name', 'Users Tier')
            ->assertJsonPath('data.0.account.trading_status', 'active');
    }

    public function test_search_status_role_and_tier_filters_are_applied_server_side(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $matchingUser = User::factory()->create([
            'name' => 'Ada Matching',
            'email' => 'ada@example.com',
            'status' => 'suspended',
            'role' => 'user',
        ]);
        $otherUser = User::factory()->create([
            'name' => 'Other User',
            'email' => 'other@example.com',
            'status' => 'active',
        ]);
        $strategy = Strategy::create([
            'name' => 'Filter Strategy',
            'description' => 'Filter test strategy.',
            'risk_profile' => 'moderate',
        ]);
        $tier = Tier::create([
            'name' => 'Filter Tier',
            'minimum_balance' => '100.00',
            'strategy_id' => $strategy->id,
            'description' => 'Filter test tier.',
        ]);
        $account = new Account;
        $account->forceFill(['user_id' => $matchingUser->id, 'tier_id' => $tier->id])->save();

        $this->actingAs($admin)
            ->getJson('/api/admin/users?search=Ada&status=suspended&role=user&tier=Filter')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $matchingUser->id)
            ->assertJsonMissing(['id' => $otherUser->id]);
    }

    public function test_users_are_paginated_with_a_conventional_default_and_requested_page_size(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        User::factory()->count(16)->create();

        $this->actingAs($admin)
            ->getJson('/api/admin/users')
            ->assertOk()
            ->assertJsonPath('meta.per_page', 15)
            ->assertJsonPath('meta.total', 17)
            ->assertJsonCount(15, 'data');

        $this->actingAs($admin)
            ->getJson('/api/admin/users?page=2&per_page=5')
            ->assertOk()
            ->assertJsonPath('meta.current_page', 2)
            ->assertJsonPath('meta.per_page', 5)
            ->assertJsonPath('meta.last_page', 4)
            ->assertJsonCount(5, 'data');
    }

    public function test_role_changes_and_admin_suspension_are_super_admin_only_and_audited(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $target = User::factory()->create(['role' => 'admin']);
        $endpoint = '/api/admin/users/'.$target->id.'/access';

        $this->actingAs($admin)->patchJson($endpoint, ['status' => 'suspended'])->assertForbidden();
        $this->actingAs($admin)->patchJson($endpoint, ['role' => 'super_admin'])->assertForbidden();
        $this->assertSame('admin', $target->fresh()->role);
        $this->assertSame('active', $target->fresh()->status);
        $this->assertDatabaseCount('audit_logs', 0);

        $superAdmin = User::factory()->create(['role' => 'super_admin']);
        $this->actingAs($superAdmin)
            ->patchJson($endpoint, ['status' => 'suspended'])
            ->assertOk()
            ->assertJsonPath('data.status', 'suspended');

        $audit = AuditLog::where('action', 'user.access.updated')->firstOrFail();
        $this->assertSame($superAdmin->id, $audit->actor_id);
        $this->assertSame($target->id, $audit->user_id);
        $this->assertSame('active', $audit->old_values['status']);
        $this->assertSame('suspended', $audit->new_values['status']);
        $this->assertDatabaseHas('user_notifications', [
            'user_id' => $target->id,
            'category' => 'security',
            'title' => 'Account access updated',
        ]);

        $this->actingAs($superAdmin)
            ->patchJson('/api/admin/users/'.$target->id.'/access', ['role' => 'user'])
            ->assertOk()
            ->assertJsonPath('data.role', 'user');
    }

    public function test_non_admin_status_changes_and_invalid_access_values_are_rejected(): void
    {
        $superAdmin = User::factory()->create(['role' => 'super_admin']);
        $user = User::factory()->create();
        $endpoint = '/api/admin/users/'.$user->id.'/access';

        $this->actingAs($superAdmin)->patchJson($endpoint, ['status' => 'suspended'])->assertUnprocessable();
        $this->actingAs($superAdmin)->patchJson($endpoint, ['role' => 'owner'])->assertUnprocessable();
        $this->actingAs($superAdmin)->patchJson($endpoint, ['status' => 'deleted'])->assertUnprocessable();
        $this->actingAs($superAdmin)->patchJson($endpoint, ['managed_balance' => '1000'])->assertUnprocessable();

        $this->assertSame('user', $user->fresh()->role);
        $this->assertSame('active', $user->fresh()->status);
        $this->assertDatabaseCount('audit_logs', 0);
    }
}
