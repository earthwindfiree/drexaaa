<?php

namespace Tests\Feature;

use App\Models\Asset;
use App\Models\AuditLog;
use App\Models\User;
use App\Services\AuditLogService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminAuditLogsTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_normal_users_and_suspended_admins_are_rejected(): void
    {
        $this->createAuditLog();
        $this->getJson('/api/admin/audit-logs')->assertUnauthorized();

        foreach ([
            User::factory()->create(),
            User::factory()->create(['role' => 'admin', 'status' => 'suspended']),
        ] as $user) {
            $this->actingAs($user)->getJson('/api/admin/audit-logs')->assertForbidden();
        }
    }

    public function test_active_admin_and_super_admin_can_read_structured_audit_data(): void
    {
        $audit = $this->createAuditLog();

        foreach (['admin', 'super_admin'] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]))
                ->getJson('/api/admin/audit-logs')
                ->assertOk()
                ->assertJsonPath('data.0.id', $audit->id)
                ->assertJsonPath('data.0.actor.name', 'Audit Actor')
                ->assertJsonPath('data.0.target_user.name', 'Audit Target')
                ->assertJsonPath('data.0.action', 'market_price.updated')
                ->assertJsonPath('data.0.entity_type', Asset::class)
                ->assertJsonPath('data.0.entity_id', $audit->entity_id)
                ->assertJsonPath('data.0.old_values.current_price', '60000.00000000')
                ->assertJsonPath('data.0.new_values.current_price', '65000.00000000')
                ->assertJsonPath('data.0.metadata.source', 'manual')
                ->assertJsonMissingPath('data.0.actor.password')
                ->assertJsonMissingPath('data.0.actor.remember_token');
        }
    }

    public function test_audit_filters_and_pagination_are_server_side(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $audit = $this->createAuditLog();

        $this->actingAs($admin)
            ->getJson('/api/admin/audit-logs?actor_search=Audit%20Actor&target_search=Audit%20Target&action=market_price&entity_type='.urlencode(Asset::class).'&entity_id='.$audit->entity_id)
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $audit->id)
            ->assertJsonPath('meta.total', 1);

        $this->actingAs($admin)
            ->getJson('/api/admin/audit-logs?action=does-not-exist')
            ->assertOk()
            ->assertJsonCount(0, 'data')
            ->assertJsonPath('meta.total', 0);

        for ($index = 0; $index < 15; $index++) {
            $this->createAuditLog(action: 'account.simulation.updated.'.$index);
        }

        $this->actingAs($admin)
            ->getJson('/api/admin/audit-logs?per_page=5&page=2')
            ->assertOk()
            ->assertJsonPath('meta.per_page', 5)
            ->assertJsonPath('meta.current_page', 2)
            ->assertJsonPath('meta.total', 16)
            ->assertJsonCount(5, 'data');
    }

    public function test_reading_audit_logs_does_not_mutate_history(): void
    {
        $this->createAuditLog();
        $admin = User::factory()->create(['role' => 'admin']);
        $count = AuditLog::count();

        $this->actingAs($admin)->getJson('/api/admin/audit-logs')->assertOk();

        $this->assertSame($count, AuditLog::count());
    }

    private function createAuditLog(string $action = 'market_price.updated'): AuditLog
    {
        $actor = User::factory()->create(['name' => 'Audit Actor', 'email' => fake()->unique()->safeEmail(), 'role' => 'admin']);
        $target = User::factory()->create(['name' => 'Audit Target', 'email' => fake()->unique()->safeEmail()]);
        $asset = Asset::create(['symbol' => 'AUD'.str()->random(8), 'name' => 'Audit Asset']);

        return app(AuditLogService::class)->record(
            $actor,
            $action,
            $target,
            $asset,
            ['current_price' => '60000.00000000'],
            ['current_price' => '65000.00000000'],
            ['source' => 'manual'],
        );
    }
}
