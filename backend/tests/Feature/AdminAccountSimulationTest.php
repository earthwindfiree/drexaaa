<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\AuditLog;
use App\Models\Transaction;
use App\Models\User;
use App\Services\AccountBalanceService;
use App\Services\AdminAccountSimulationService;
use App\Services\AuditLogService;
use App\Services\TransactionRecordService;
use Database\Seeders\StrategyTierSeeder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class AdminAccountSimulationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(StrategyTierSeeder::class);
    }

    public function test_mutation_endpoint_rejects_guests_normal_users_and_suspended_admins(): void
    {
        $account = $this->createAccount('1000.00');
        $this->patchJson('/api/admin/accounts/'.$account->id.'/simulation', ['managed_balance' => '2000.00'])->assertUnauthorized();

        foreach ([
            User::factory()->create(),
            User::factory()->create(['role' => 'admin']),
            User::factory()->create(['role' => 'admin', 'status' => 'suspended']),
        ] as $user) {
            $this->actingAs($user)
                ->patchJson('/api/admin/accounts/'.$account->id.'/simulation', ['managed_balance' => '2000.00'])
                ->assertForbidden();
        }
    }

    public function test_active_admin_can_set_balance_recalculate_tier_and_create_audited_adjustment(): void
    {
        $account = $this->createAccount('1000.00', ['pending_balance' => '250.00']);
        $admin = User::factory()->create(['role' => 'super_admin']);

        $this->actingAs($admin)
            ->patchJson('/api/admin/accounts/'.$account->id.'/simulation', ['managed_balance' => '5000.00'])
            ->assertOk()
            ->assertJsonPath('data.managed_balance', '5000.00')
            ->assertJsonPath('data.pending_balance', '250.00')
            ->assertJsonPath('data.tier_id', $account->fresh()->tier_id);

        $account = $account->fresh();
        $this->assertSame('Elevation', $account->tier->name);
        $this->assertSame('0.00', $account->total_profit_loss);
        $this->assertSame('0.0000', $account->performance_percentage);
        $this->assertDatabaseCount('transactions', 1);
        $this->assertDatabaseHas('transactions', [
            'account_id' => $account->id,
            'user_id' => $account->user_id,
            'type' => 'adjustment',
            'status' => 'completed',
            'usd_amount' => '4000.00',
        ]);

        $audit = AuditLog::where('action', 'account.simulation.updated')->firstOrFail();
        $this->assertSame($admin->id, $audit->actor_id);
        $this->assertSame($account->user_id, $audit->user_id);
        $this->assertSame(Account::class, $audit->entity_type);
        $this->assertSame($account->id, $audit->entity_id);
        $this->assertSame('1000.00', $audit->old_values['managed_balance']);
        $this->assertSame('5000.00', $audit->new_values['managed_balance']);
        $this->assertSame($account->tier_id, $audit->new_values['tier_id']);
        $this->assertDatabaseHas('user_notifications', ['user_id' => $account->user_id, 'title' => 'Account adjusted']);
        $this->assertDatabaseHas('user_notifications', ['user_id' => $account->user_id, 'title' => 'Tier changed']);
    }

    public function test_super_admin_can_update_pl_and_performance_without_changing_balance(): void
    {
        $account = $this->createAccount('1000.00');
        $admin = User::factory()->create(['role' => 'super_admin']);

        $this->actingAs($admin)
            ->patchJson('/api/admin/accounts/'.$account->id.'/simulation', [
                'total_profit_loss' => '-125.50',
                'performance_percentage' => '-12.5000',
            ])
            ->assertOk()
            ->assertJsonPath('data.total_profit_loss', '-125.50')
            ->assertJsonPath('data.performance_percentage', '-12.5000');

        $account = $account->fresh();
        $this->assertSame('1000.00', $account->managed_balance);
        $this->assertSame('0.00', $account->pending_balance);
        $this->assertDatabaseCount('transactions', 1);
        $this->assertDatabaseHas('transactions', ['type' => 'adjustment', 'usd_amount' => '-125.50']);
        $this->assertDatabaseHas('user_notifications', ['user_id' => $account->user_id, 'title' => 'Account adjusted']);
    }

    public function test_status_only_update_is_audited_without_creating_a_financial_transaction(): void
    {
        $account = $this->createAccount('1000.00');
        $admin = User::factory()->create(['role' => 'super_admin']);

        $this->actingAs($admin)
            ->patchJson('/api/admin/accounts/'.$account->id.'/simulation', ['trading_status' => 'active'])
            ->assertOk()
            ->assertJsonPath('data.trading_status', 'active');

        $this->assertSame('1000.00', $account->fresh()->managed_balance);
        $this->assertDatabaseCount('transactions', 0);
        $this->assertDatabaseCount('audit_logs', 1);
    }

    public function test_invalid_values_and_protected_fields_are_rejected(): void
    {
        $account = $this->createAccount('1000.00');
        $admin = User::factory()->create(['role' => 'super_admin']);
        $endpoint = '/api/admin/accounts/'.$account->id.'/simulation';

        foreach ([
            ['managed_balance' => '-1.00'],
            ['performance_percentage' => '1.23456'],
            ['trading_status' => 'paused'],
            ['pending_balance' => '9999.00'],
            ['tier_id' => null],
            ['user_id' => User::factory()->create()->id],
        ] as $payload) {
            $this->actingAs($admin)->patchJson($endpoint, $payload)->assertUnprocessable();
        }

        $this->assertSame('1000.00', $account->fresh()->managed_balance);
        $this->assertDatabaseCount('transactions', 0);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_audit_failure_rolls_back_account_and_adjustment_transaction(): void
    {
        $account = $this->createAccount('1000.00');
        $admin = User::factory()->create(['role' => 'super_admin']);
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
            app(AdminAccountSimulationService::class)->update($account, $admin, ['managed_balance' => '2500.00']);
            $this->fail('Audit failure must roll back account simulation.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Forced audit failure.', $exception->getMessage());
        }

        $this->assertSame('1000.00', $account->fresh()->managed_balance);
        $this->assertDatabaseCount('transactions', 0);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_transaction_failure_rolls_back_account_and_audit(): void
    {
        $account = $this->createAccount('1000.00');
        $admin = User::factory()->create(['role' => 'super_admin']);
        $this->app->instance(TransactionRecordService::class, new class extends TransactionRecordService
        {
            public function recordAccountAdjustment(Account $account, string $usdAmount, string $description): Transaction
            {
                parent::recordAccountAdjustment($account, $usdAmount, $description);

                throw new RuntimeException('Forced transaction failure.');
            }
        });

        try {
            app(AdminAccountSimulationService::class)->update($account, $admin, ['managed_balance' => '2500.00']);
            $this->fail('Transaction failure must roll back account simulation.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Forced transaction failure.', $exception->getMessage());
        }

        $this->assertSame('1000.00', $account->fresh()->managed_balance);
        $this->assertDatabaseCount('transactions', 0);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    private function createAccount(string $managedBalance, array $attributes = []): Account
    {
        $user = User::factory()->create();
        $account = $user->account()->create([]);
        $account->forceFill($attributes)->save();

        return app(AccountBalanceService::class)->setManagedBalance($account, $managedBalance);
    }
}
