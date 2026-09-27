<?php

namespace Tests\Feature;

use App\Contracts\AssetPriceProvider;
use App\Models\Asset;
use App\Models\AuditLog;
use App\Models\Deposit;
use App\Models\MarketPrice;
use App\Models\PerformanceSnapshot;
use App\Models\User;
use App\Models\Withdrawal;
use App\Services\AccountBalanceService;
use App\Services\AuditLogService;
use App\Services\DepositReviewService;
use App\Services\DepositSubmissionService;
use App\Services\MarketPriceService;
use App\Services\PerformanceSnapshotService;
use App\Services\WithdrawalReviewService;
use App\Services\WithdrawalSubmissionService;
use Database\Seeders\AssetSeeder;
use Database\Seeders\MarketPriceSeeder;
use Database\Seeders\StrategyTierSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use RuntimeException;
use Tests\TestCase;

class AuditLogIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(StrategyTierSeeder::class);
        $this->seed(AssetSeeder::class);
        $this->seed(MarketPriceSeeder::class);
        $this->app->instance(AssetPriceProvider::class, new FakeAuditPriceProvider);
    }

    public function test_deposit_confirmation_and_rejection_are_audited_with_structured_state(): void
    {
        [$user, $account] = $this->createUserWithBalance('100');
        $reviewer = User::factory()->create(['role' => 'admin']);
        $deposit = $this->submitDeposit($user, '0.01', 'audit-confirm-ref');

        app(DepositReviewService::class)->confirm($deposit, $reviewer);

        $confirmedLog = AuditLog::where('action', 'deposit.confirmed')->firstOrFail();
        $this->assertSame($reviewer->id, $confirmedLog->actor_id);
        $this->assertSame($user->id, $confirmedLog->user_id);
        $this->assertSame(Deposit::class, $confirmedLog->entity_type);
        $this->assertSame($deposit->id, $confirmedLog->entity_id);
        $this->assertSame('pending', $confirmedLog->old_values['status']);
        $this->assertSame('confirmed', $confirmedLog->new_values['status']);
        $this->assertSame('100.00', $confirmedLog->old_values['managed_balance']);
        $this->assertSame('600.00', $confirmedLog->new_values['managed_balance']);

        $rejectedDeposit = $this->submitDeposit($user, '0.01', 'audit-reject-ref');
        app(DepositReviewService::class)->reject($rejectedDeposit, $reviewer, 'Rejected by test.');

        $rejectedLog = AuditLog::where('action', 'deposit.rejected')->firstOrFail();
        $this->assertSame($reviewer->id, $rejectedLog->actor_id);
        $this->assertSame($rejectedDeposit->id, $rejectedLog->entity_id);
        $this->assertSame('rejected', $rejectedLog->new_values['status']);
        $this->assertSame('Rejected by test.', $rejectedLog->new_values['rejection_reason']);
    }

    public function test_withdrawal_approval_and_rejection_are_audited_with_account_state(): void
    {
        [$user, $account] = $this->createUserWithBalance('1000');
        $reviewer = User::factory()->create(['role' => 'super_admin']);
        $withdrawal = $this->submitWithdrawal($user, '100.00');

        app(WithdrawalReviewService::class)->approve($withdrawal, $reviewer);

        $approvedLog = AuditLog::where('action', 'withdrawal.approved')->firstOrFail();
        $this->assertSame($reviewer->id, $approvedLog->actor_id);
        $this->assertSame($user->id, $approvedLog->user_id);
        $this->assertSame(Withdrawal::class, $approvedLog->entity_type);
        $this->assertSame($withdrawal->id, $approvedLog->entity_id);
        $this->assertSame('1000.00', $approvedLog->old_values['managed_balance']);
        $this->assertSame('900.00', $approvedLog->new_values['managed_balance']);
        $this->assertSame('approved', $approvedLog->new_values['status']);

        $rejectedWithdrawal = $this->submitWithdrawal($user, '100.00');
        app(WithdrawalReviewService::class)->reject($rejectedWithdrawal, $reviewer, 'Rejected by test.');
        $rejectedLog = AuditLog::where('action', 'withdrawal.rejected')->firstOrFail();

        $this->assertSame($rejectedWithdrawal->id, $rejectedLog->entity_id);
        $this->assertSame('rejected', $rejectedLog->new_values['status']);
        $this->assertSame('Rejected by test.', $rejectedLog->new_values['rejection_reason']);
        $this->assertSame('900.00', $account->fresh()->managed_balance);
    }

    public function test_market_price_change_and_performance_snapshot_are_audited(): void
    {
        [$user, $account] = $this->createUserWithBalance('1000');
        $admin = User::factory()->create(['role' => 'admin']);
        $asset = Asset::where('symbol', 'BTC')->firstOrFail();
        $priceBefore = $asset->marketPrice()->firstOrFail()->current_price;

        $updatedPrice = app(MarketPriceService::class)->update($admin, $asset, '70000', '4.2500');
        $priceLog = AuditLog::where('action', 'market_price.updated')->firstOrFail();
        $this->assertSame($admin->id, $priceLog->actor_id);
        $this->assertSame(MarketPrice::class, $priceLog->entity_type);
        $this->assertSame($priceBefore, $priceLog->old_values['current_price']);
        $this->assertSame('70000.00000000', $priceLog->new_values['current_price']);

        $snapshot = app(PerformanceSnapshotService::class)->create(
            $admin,
            $account,
            '1250.00',
            '250.00',
            '25.0000',
            Carbon::parse('2026-09-27 12:00:00'),
        );
        $snapshotLog = AuditLog::where('action', 'performance_snapshot.created')->firstOrFail();
        $this->assertSame($admin->id, $snapshotLog->actor_id);
        $this->assertSame($user->id, $snapshotLog->user_id);
        $this->assertSame(PerformanceSnapshot::class, $snapshotLog->entity_type);
        $this->assertSame($snapshot->id, $snapshotLog->entity_id);
        $this->assertSame('1250.00', $snapshotLog->new_values['managed_balance']);
        $this->assertSame('70000.00000000', $updatedPrice->fresh()->current_price);
        $this->assertSame('1000.00', $account->fresh()->managed_balance);
        $this->assertSame('Momentum', $account->fresh()->tier->name);
        $this->assertDatabaseCount('transactions', 0);
    }

    public function test_audit_failure_rolls_back_deposit_and_withdrawal_mutations(): void
    {
        [$user, $account] = $this->createUserWithBalance('1000');
        $admin = User::factory()->create(['role' => 'admin']);
        $deposit = $this->submitDeposit($user, '0.01', 'audit-rollback-ref');
        $this->failAfterAuditRecord();

        try {
            app(DepositReviewService::class)->confirm($deposit, $admin);
            $this->fail('Audit write failure must roll back deposit confirmation.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Forced audit failure.', $exception->getMessage());
        }

        $this->assertSame('pending', $deposit->fresh()->status->value);
        $this->assertSame('1000.00', $account->fresh()->managed_balance);
        $this->assertSame('500.00', $account->fresh()->pending_balance);
        $this->assertDatabaseCount('transactions', 0);
        $this->assertDatabaseCount('audit_logs', 0);

        $this->app->instance(AuditLogService::class, new AuditLogService);
        $withdrawal = $this->submitWithdrawal($user, '100.00');
        $this->failAfterAuditRecord();

        try {
            app(WithdrawalReviewService::class)->approve($withdrawal, $admin);
            $this->fail('Audit write failure must roll back withdrawal approval.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Forced audit failure.', $exception->getMessage());
        }

        $this->assertSame('pending', $withdrawal->fresh()->status->value);
        $this->assertSame('1000.00', $account->fresh()->managed_balance);
        $this->assertDatabaseCount('transactions', 0);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_audit_failure_rolls_back_price_and_snapshot_mutations(): void
    {
        [$user, $account] = $this->createUserWithBalance('1000');
        $admin = User::factory()->create(['role' => 'admin']);
        $asset = Asset::where('symbol', 'BTC')->firstOrFail();
        $priceBefore = $asset->marketPrice()->firstOrFail()->current_price;
        $this->failAfterAuditRecord();

        try {
            app(MarketPriceService::class)->update($admin, $asset, '70000', '5.0000');
            $this->fail('Audit write failure must roll back price update.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Forced audit failure.', $exception->getMessage());
        }

        $this->assertSame($priceBefore, $asset->marketPrice()->firstOrFail()->current_price);
        $this->assertDatabaseCount('audit_logs', 0);

        $this->app->instance(AuditLogService::class, new AuditLogService);
        $this->failAfterAuditRecord();

        try {
            app(PerformanceSnapshotService::class)->create(
                $admin,
                $account,
                '1250.00',
                '250.00',
                '25.0000',
                Carbon::parse('2026-09-27 12:00:00'),
            );
            $this->fail('Audit write failure must roll back snapshot creation.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Forced audit failure.', $exception->getMessage());
        }

        $this->assertDatabaseCount('performance_snapshots', 0);
        $this->assertDatabaseCount('audit_logs', 0);
        $this->assertSame('1000.00', $account->fresh()->managed_balance);
    }

    public function test_normal_user_cannot_change_market_prices_or_create_snapshots(): void
    {
        [$user, $account] = $this->createUserWithBalance('1000');
        $asset = Asset::where('symbol', 'BTC')->firstOrFail();
        $priceBefore = $asset->marketPrice()->firstOrFail()->current_price;

        foreach ([
            fn () => app(MarketPriceService::class)->update($user, $asset, '70000', '5.0000'),
            fn () => app(PerformanceSnapshotService::class)->create(
                $user,
                $account,
                '1250.00',
                '250.00',
                '25.0000',
                Carbon::parse('2026-09-27 12:00:00'),
            ),
        ] as $unauthorizedMutation) {
            try {
                $unauthorizedMutation();
                $this->fail('A normal user must not perform privileged mutations.');
            } catch (AuthorizationException) {
            }
        }

        $this->assertSame($priceBefore, $asset->marketPrice()->firstOrFail()->current_price);
        $this->assertSame('1000.00', $account->fresh()->managed_balance);
        $this->assertDatabaseCount('performance_snapshots', 0);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    private function createUserWithBalance(string $balance): array
    {
        $user = User::factory()->create();
        $account = $user->account()->create([]);

        return [$user, app(AccountBalanceService::class)->setManagedBalance($account, $balance)];
    }

    private function submitDeposit(User $user, string $amount, string $reference): Deposit
    {
        $asset = Asset::where('symbol', 'BTC')->firstOrFail();

        return app(DepositSubmissionService::class)->submit($user, $asset->id, $amount, $reference);
    }

    private function submitWithdrawal(User $user, string $amount): Withdrawal
    {
        $asset = Asset::where('symbol', 'BTC')->firstOrFail();

        return app(WithdrawalSubmissionService::class)->submit($user, $asset->id, $amount, 'destination-wallet');
    }

    private function failAfterAuditRecord(): void
    {
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
    }
}

final class FakeAuditPriceProvider implements AssetPriceProvider
{
    public function currentUsdPriceFor(Asset $asset): string
    {
        return '50000';
    }
}
