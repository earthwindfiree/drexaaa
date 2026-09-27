<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Strategy;
use App\Models\Tier;
use App\Models\Transaction;
use App\Models\User;
use App\Services\AccountBalanceService;
use App\Services\AdminTierService;
use App\Services\AuditLogService;
use Database\Seeders\StrategyTierSeeder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
use RuntimeException;
use Tests\TestCase;

class AdminTiersTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(StrategyTierSeeder::class);
    }

    public function test_guests_normal_users_and_suspended_admins_are_rejected(): void
    {
        $tier = Tier::where('name', 'Foundation')->firstOrFail();
        $this->getJson('/api/admin/tiers')->assertUnauthorized();
        $this->patchJson('/api/admin/tiers/'.$tier->id, ['minimum_balance' => '150.00'])->assertUnauthorized();

        foreach ([
            User::factory()->create(),
            User::factory()->create(['role' => 'admin', 'status' => 'suspended']),
        ] as $user) {
            $this->actingAs($user)->getJson('/api/admin/tiers')->assertForbidden();
            $this->actingAs($user)->patchJson('/api/admin/tiers/'.$tier->id, ['minimum_balance' => '150.00'])->assertForbidden();
        }
    }

    public function test_active_admin_and_super_admin_can_list_tiers_and_reusable_strategies(): void
    {
        foreach (['admin', 'super_admin'] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]))
                ->getJson('/api/admin/tiers')
                ->assertOk()
                ->assertJsonCount(4, 'data.tiers')
                ->assertJsonCount(4, 'data.strategies')
                ->assertJsonPath('data.tiers.0.name', 'Foundation')
                ->assertJsonPath('data.tiers.0.minimum_balance', '100.00')
                ->assertJsonPath('data.tiers.0.strategy.name', 'Foundation')
                ->assertJsonPath('data.tiers.0.strategy.risk_profile', 'conservative');
        }
    }

    public function test_tier_update_changes_configuration_recalculates_accounts_and_audits_without_financial_mutation(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $momentum = Tier::where('name', 'Momentum')->firstOrFail();
        $foundation = Tier::where('name', 'Foundation')->firstOrFail();
        $elevationStrategy = Strategy::where('name', 'Elevation')->firstOrFail();
        $owner = User::factory()->create();
        $account = $owner->account()->create([]);
        app(AccountBalanceService::class)->setManagedBalance($account, '1000.00');
        $beforeBalance = $account->fresh()->managed_balance;
        $beforeTransactionCount = Transaction::count();

        $this->actingAs($admin)
            ->patchJson('/api/admin/tiers/'.$momentum->id, [
                'minimum_balance' => '1500',
                'strategy_id' => $elevationStrategy->id,
                'description' => 'Updated Momentum description.',
                'benefits' => ['Updated benefit'],
                'feature_access' => ['performance'],
                'display_settings' => ['accent' => 'cyan'],
            ])
            ->assertOk()
            ->assertJsonPath('data.minimum_balance', '1500.00')
            ->assertJsonPath('data.strategy.name', 'Elevation')
            ->assertJsonPath('data.benefits.0', 'Updated benefit');

        $account = $account->fresh();
        $this->assertSame($beforeBalance, $account->managed_balance);
        $this->assertSame($foundation->id, $account->tier_id);
        $this->assertSame($beforeTransactionCount, Transaction::count());
        $audit = AuditLog::where('action', 'tier.configuration.updated')->firstOrFail();
        $this->assertSame($admin->id, $audit->actor_id);
        $this->assertSame($momentum->id, $audit->entity_id);
        $this->assertSame('1000.00', $audit->old_values['minimum_balance']);
        $this->assertSame('1500.00', $audit->new_values['minimum_balance']);
        $this->assertSame($elevationStrategy->id, $audit->new_values['strategy_id']);
        $this->assertSame(1, $audit->metadata['recalculated_accounts']);
    }

    public function test_duplicate_thresholds_names_invalid_precision_and_protected_fields_are_rejected(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $foundation = Tier::where('name', 'Foundation')->firstOrFail();
        $momentum = Tier::where('name', 'Momentum')->firstOrFail();
        $endpoint = '/api/admin/tiers/'.$foundation->id;

        $this->actingAs($admin)->patchJson($endpoint, ['minimum_balance' => $momentum->minimum_balance])->assertUnprocessable();
        $this->actingAs($admin)->patchJson($endpoint, ['minimum_balance' => '100.123'])->assertUnprocessable();
        $this->actingAs($admin)->patchJson($endpoint, ['name' => 'Momentum'])->assertUnprocessable();
        $this->actingAs($admin)->patchJson($endpoint, ['tier_id' => 999])->assertUnprocessable();

        $this->assertSame('100.00', $foundation->fresh()->minimum_balance);
        $this->assertSame(0, AuditLog::count());
    }

    public function test_audit_failure_rolls_back_tier_configuration_and_account_recalculation(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $momentum = Tier::where('name', 'Momentum')->firstOrFail();
        $owner = User::factory()->create();
        $account = $owner->account()->create([]);
        app(AccountBalanceService::class)->setManagedBalance($account, '1000.00');
        $this->app->instance(AuditLogService::class, new class extends AuditLogService
        {
            public function record(
                User $actor,
                string $action,
                ?User $user = null,
                ?Model $entity = null,
                ?array $oldValues = null,
                ?array $newValues = null,
                ?array $metadata = null,
            ): AuditLog {
                parent::record($actor, $action, $user, $entity, $oldValues, $newValues, $metadata);

                throw new RuntimeException('Forced audit failure.');
            }
        });

        try {
            app(AdminTierService::class)->update($momentum, $admin, ['minimum_balance' => '1500.00']);
            $this->fail('Audit failure must roll back tier configuration.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Forced audit failure.', $exception->getMessage());
        }

        $this->assertSame('1000.00', $momentum->fresh()->minimum_balance);
        $this->assertSame($momentum->id, $account->fresh()->tier_id);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_database_relationships_prevent_deleting_used_tiers_or_strategies(): void
    {
        $tier = Tier::where('name', 'Foundation')->firstOrFail();
        $strategy = $tier->strategy;
        $account = User::factory()->create()->account()->create([]);
        $account->tier()->associate($tier);
        $account->save();

        try {
            $tier->delete();
            $this->fail('A tier assigned to an account must not be deleted.');
        } catch (LogicException $exception) {
            $this->assertInstanceOf(LogicException::class, $exception);
        }

        try {
            $strategy->delete();
            $this->fail('A strategy assigned to a tier must not be deleted.');
        } catch (LogicException $exception) {
            $this->assertInstanceOf(LogicException::class, $exception);
        }
    }
}
