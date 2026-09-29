<?php

namespace Tests\Feature;

use App\Models\Asset;
use App\Models\AuditLog;
use App\Models\User;
use App\Services\AuditLogService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class AdminAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_audit_service_records_actor_target_entity_and_structured_state(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $target = User::factory()->create();
        $asset = Asset::create(['symbol' => 'BTC', 'name' => 'Bitcoin']);

        $auditLog = app(AuditLogService::class)->record(
            $admin,
            'market_price.updated',
            $target,
            $asset,
            ['current_price' => '60000.00000000'],
            ['current_price' => '65000.00000000'],
            ['source' => 'manual'],
        );

        $this->assertSame($admin->id, $auditLog->actor_id);
        $this->assertSame($target->id, $auditLog->user_id);
        $this->assertSame('market_price.updated', $auditLog->action);
        $this->assertSame(Asset::class, $auditLog->entity_type);
        $this->assertSame($asset->id, $auditLog->entity_id);
        $this->assertSame(['current_price' => '60000.00000000'], $auditLog->old_values);
        $this->assertSame(['current_price' => '65000.00000000'], $auditLog->new_values);
        $this->assertSame(['source' => 'manual'], $auditLog->metadata);
    }

    public function test_audit_log_fields_are_not_mass_assignable(): void
    {
        $auditLog = new AuditLog;

        foreach (['actor_id', 'user_id', 'action', 'entity_type', 'entity_id', 'old_values', 'new_values', 'metadata'] as $field) {
            $this->assertFalse($auditLog->isFillable($field));
        }
    }

    public function test_admin_gate_accepts_only_active_admin_roles(): void
    {
        $this->assertFalse(Gate::forUser(User::factory()->create())->allows('admin'));
        $this->assertTrue(Gate::forUser(User::factory()->create(['role' => 'admin']))->allows('admin'));
        $this->assertTrue(Gate::forUser(User::factory()->create(['role' => 'super_admin']))->allows('admin'));
        $this->assertFalse(Gate::forUser(User::factory()->create(['role' => 'admin', 'status' => 'suspended']))->allows('admin'));
    }

    public function test_super_admin_gate_accepts_only_active_super_admins(): void
    {
        $this->assertFalse(Gate::forUser(User::factory()->create())->allows('super-admin'));
        $this->assertFalse(Gate::forUser(User::factory()->create(['role' => 'admin']))->allows('super-admin'));
        $this->assertTrue(Gate::forUser(User::factory()->create(['role' => 'super_admin']))->allows('super-admin'));
        $this->assertFalse(Gate::forUser(User::factory()->create(['role' => 'super_admin', 'status' => 'suspended']))->allows('super-admin'));
    }

    public function test_can_admin_middleware_rejects_guests_and_normal_users(): void
    {
        Route::middleware(['auth:sanctum', 'can:admin'])
            ->get('/_test/admin-authorization', fn () => response()->json(['ok' => true]));

        $this->getJson('/_test/admin-authorization')->assertUnauthorized();

        $this->actingAs(User::factory()->create())
            ->getJson('/_test/admin-authorization')
            ->assertForbidden();
    }

    public function test_active_admin_and_super_admin_pass_can_admin_middleware(): void
    {
        Route::middleware(['auth:sanctum', 'can:admin'])
            ->get('/_test/admin-authorization', fn () => response()->json(['ok' => true]));

        foreach (['admin', 'super_admin'] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]))
                ->getJson('/_test/admin-authorization')
                ->assertOk()
                ->assertJsonPath('ok', true);
        }

        $this->actingAs(User::factory()->create(['role' => 'admin', 'status' => 'suspended']))
            ->getJson('/_test/admin-authorization')
            ->assertForbidden();
    }

    public function test_audit_service_rejects_normal_users(): void
    {
        $this->expectException(AuthorizationException::class);

        app(AuditLogService::class)->record(User::factory()->create(), 'privileged.change');
    }
}
