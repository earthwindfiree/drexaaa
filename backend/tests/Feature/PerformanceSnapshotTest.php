<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\PerformanceSnapshot;
use App\Models\User;
use App\Services\AccountBalanceService;
use App\Services\PerformanceHistoryService;
use App\Services\PerformanceSnapshotService;
use Database\Seeders\StrategyTierSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class PerformanceSnapshotTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(StrategyTierSeeder::class);
    }

    public function test_snapshot_stores_explicit_values_and_belongs_to_account(): void
    {
        $account = $this->createAccount();
        $snapshotAt = Carbon::parse('2026-09-01 10:30:00');

        $snapshot = $this->createSnapshot($account, '1234.5', '34.5', '2.375', $snapshotAt);

        $this->assertSame('1234.50', $snapshot->managed_balance);
        $this->assertSame('34.50', $snapshot->total_profit_loss);
        $this->assertSame('2.3750', $snapshot->performance_percentage);
        $this->assertTrue($snapshot->account->is($account));
        $this->assertTrue($snapshot->snapshot_at->equalTo($snapshotAt));
    }

    public function test_later_account_changes_do_not_change_historical_snapshots_or_current_state(): void
    {
        $account = $this->createAccount('100');
        $tierId = $account->tier_id;
        $snapshot = $this->createSnapshot($account, '100.00', '5.00', '5.0000', Carbon::parse('2026-09-01'));

        app(AccountBalanceService::class)->setManagedBalance($account, '12000');
        $account->refresh();
        $snapshot->refresh();

        $this->assertSame('100.00', $snapshot->managed_balance);
        $this->assertSame('5.00', $snapshot->total_profit_loss);
        $this->assertSame('5.0000', $snapshot->performance_percentage);
        $this->assertSame('12000.00', $account->managed_balance);
        $this->assertNotSame($tierId, $account->tier_id);
    }

    public function test_snapshot_does_not_modify_other_account_state_or_create_transactions(): void
    {
        $account = $this->createAccount('1000')->refresh();
        $before = $account->only([
            'managed_balance',
            'pending_balance',
            'total_profit_loss',
            'performance_percentage',
            'trading_status',
            'tier_id',
        ]);

        $this->createSnapshot($account, '950.00', '-50.00', '-5.0000', Carbon::parse('2026-09-02'));
        $account->refresh();

        $this->assertSame($before, $account->only(array_keys($before)));
        $this->assertDatabaseCount('transactions', 0);
    }

    public function test_multiple_snapshots_preserve_supplied_historical_values(): void
    {
        $account = $this->createAccount();
        $this->createSnapshot($account, '10000', '0', '0', Carbon::parse('2026-09-01'));
        $this->createSnapshot($account, '12000', '2000', '20', Carbon::parse('2026-09-02'));

        $snapshots = $account->performanceSnapshots()->orderBy('snapshot_at')->get();

        $this->assertSame(['10000.00', '12000.00'], $snapshots->pluck('managed_balance')->all());
        $this->assertSame(['0.00', '2000.00'], $snapshots->pluck('total_profit_loss')->all());
    }

    public function test_invalid_fixed_precision_values_are_rejected(): void
    {
        $account = $this->createAccount();

        $this->expectException(ValidationException::class);

        $this->createSnapshot($account, '1.001', '0', '0', Carbon::now());
    }

    public function test_duplicate_account_timestamp_is_rejected(): void
    {
        $account = $this->createAccount();
        $snapshotAt = Carbon::parse('2026-09-03 12:00:00');
        $this->createSnapshot($account, '10', '0', '0', $snapshotAt);

        $this->expectException(ValidationException::class);

        $this->createSnapshot($account, '20', '0', '0', $snapshotAt);
    }

    public function test_database_prevents_duplicate_account_timestamp(): void
    {
        $account = $this->createAccount();
        $snapshotAt = Carbon::parse('2026-09-04 12:00:00');
        $this->createSnapshot($account, '10', '0', '0', $snapshotAt);

        $this->expectException(QueryException::class);
        PerformanceSnapshot::query()->forceCreate([
            'account_id' => $account->id,
            'managed_balance' => '11.00',
            'total_profit_loss' => '0.00',
            'performance_percentage' => '0.0000',
            'snapshot_at' => $snapshotAt,
        ]);
    }

    public function test_history_is_account_scoped_ordered_and_date_filterable(): void
    {
        $account = $this->createAccount();
        $otherAccount = $this->createAccount();
        $this->createSnapshot($account, '1', '0', '0', Carbon::parse('2026-09-01'));
        $middle = $this->createSnapshot($account, '2', '0', '0', Carbon::parse('2026-09-02'));
        $this->createSnapshot($account, '3', '0', '0', Carbon::parse('2026-09-03'));
        $this->createSnapshot($otherAccount, '99', '0', '0', Carbon::parse('2026-09-02'));
        $history = app(PerformanceHistoryService::class);

        $this->assertSame(
            ['1.00', '2.00', '3.00'],
            $history->forAccount($account)->get()->pluck('managed_balance')->all(),
        );
        $this->assertSame(
            ['3.00', '2.00', '1.00'],
            $history->forAccount($account, 'desc')->get()->pluck('managed_balance')->all(),
        );
        $filtered = $history->forAccount(
            $account,
            from: Carbon::parse('2026-09-02'),
            to: Carbon::parse('2026-09-02 23:59:59'),
        )->get();
        $this->assertCount(1, $filtered);
        $this->assertTrue($filtered->first()->is($middle));
    }

    private function createAccount(string $managedBalance = '0'): Account
    {
        $account = User::factory()->create()->account()->create([]);

        return $managedBalance === '0'
            ? $account
            : app(AccountBalanceService::class)->setManagedBalance($account, $managedBalance);
    }

    private function createSnapshot(
        Account $account,
        string $managedBalance,
        string $profitLoss,
        string $percentage,
        Carbon $snapshotAt,
    ): PerformanceSnapshot {
        return app(PerformanceSnapshotService::class)->create(
            User::factory()->create(['role' => 'admin']),
            $account,
            $managedBalance,
            $profitLoss,
            $percentage,
            $snapshotAt,
        );
    }
}
