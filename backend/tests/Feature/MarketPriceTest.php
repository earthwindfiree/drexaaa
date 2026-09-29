<?php

namespace Tests\Feature;

use App\Contracts\AssetPriceProvider;
use App\Models\Asset;
use App\Models\Deposit;
use App\Models\PerformanceSnapshot;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Withdrawal;
use App\Services\AccountBalanceService;
use App\Services\DepositReviewService;
use App\Services\DepositSubmissionService;
use App\Services\MarketPriceService;
use App\Services\PerformanceSnapshotService;
use App\Services\WithdrawalReviewService;
use App\Services\WithdrawalSubmissionService;
use Database\Seeders\AssetSeeder;
use Database\Seeders\MarketPriceSeeder;
use Database\Seeders\StrategyTierSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class MarketPriceTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(StrategyTierSeeder::class);
        $this->seed(AssetSeeder::class);
        $this->seed(MarketPriceSeeder::class);
        $this->admin = User::factory()->create(['role' => 'super_admin']);
    }

    public function test_seeded_current_price_is_retrievable(): void
    {
        $btc = Asset::where('symbol', 'BTC')->firstOrFail();
        $marketPrice = app(MarketPriceService::class)->currentFor($btc);

        $this->assertSame('67540.00000000', $marketPrice->current_price);
        $this->assertSame('3.4200', $marketPrice->change_24h_percentage);
    }

    public function test_price_updates_normalize_decimals_and_allow_negative_24h_change(): void
    {
        $ltc = Asset::where('symbol', 'LTC')->firstOrFail();
        $marketPrice = app(MarketPriceService::class)->update($this->admin, $ltc, '88.125', '-1.2575');

        $this->assertSame('88.12500000', $marketPrice->current_price);
        $this->assertSame('-1.2575', $marketPrice->change_24h_percentage);
    }

    public function test_zero_or_invalid_prices_are_rejected(): void
    {
        $asset = Asset::where('symbol', 'ETH')->firstOrFail();

        try {
            app(MarketPriceService::class)->update($this->admin, $asset, '0', '1.0000');
            $this->fail('A zero simulated price must be rejected.');
        } catch (ValidationException) {
        }

        $this->expectException(ValidationException::class);
        app(MarketPriceService::class)->update($this->admin, $asset, '2.123456789', '1.0000');
    }

    public function test_inactive_assets_keep_readable_and_updatable_price_records(): void
    {
        $asset = Asset::where('symbol', 'ETH')->firstOrFail();
        $asset->update(['active' => false]);

        $updated = app(MarketPriceService::class)->update($this->admin, $asset, '3000', '-2.5');
        $providedPrice = app(AssetPriceProvider::class)->currentUsdPriceFor($asset);

        $this->assertFalse($asset->fresh()->active);
        $this->assertSame('3000.00000000', $updated->current_price);
        $this->assertSame('3000.00000000', $providedPrice);
    }

    public function test_existing_asset_price_provider_reads_persisted_current_price(): void
    {
        $asset = Asset::where('symbol', 'USDT')->firstOrFail();

        $this->assertSame('1.00000000', app(AssetPriceProvider::class)->currentUsdPriceFor($asset));

        app(MarketPriceService::class)->update($this->admin, $asset, '0.998', '-0.1200');

        $this->assertSame('0.99800000', app(AssetPriceProvider::class)->currentUsdPriceFor($asset));
    }

    public function test_price_change_does_not_rewrite_historical_financial_or_performance_snapshots(): void
    {
        $asset = Asset::where('symbol', 'BTC')->firstOrFail();
        $user = User::factory()->create();
        $account = $user->account()->create([]);
        $account = app(AccountBalanceService::class)->setManagedBalance($account, '1000');

        $deposit = app(DepositSubmissionService::class)->submit($user, $asset->id, '0.01', 'market-price-history-test');
        $withdrawal = app(WithdrawalSubmissionService::class)->submit($user, $asset->id, '100.00', 'destination-wallet');
        $reviewer = User::factory()->create(['role' => 'admin']);
        app(DepositReviewService::class)->confirm($deposit, $reviewer);
        $approvedWithdrawal = app(WithdrawalReviewService::class)->approve($withdrawal, $reviewer);
        $depositTransaction = $deposit->fresh()->transaction;
        $withdrawalTransaction = $approvedWithdrawal->transaction;
        $snapshot = app(PerformanceSnapshotService::class)->create(
            $this->admin,
            $account->fresh(),
            '1250.00',
            '250.00',
            '25.0000',
            Carbon::parse('2026-09-27 12:00:00'),
        );

        $historicalValues = [
            'deposit' => [$deposit->fresh()->price_snapshot, $deposit->fresh()->usd_value],
            'withdrawal' => [$withdrawal->fresh()->price_snapshot, $withdrawal->fresh()->crypto_amount],
            'transaction' => [$depositTransaction->price_snapshot, $depositTransaction->usd_amount],
            'withdrawal_transaction' => [$withdrawalTransaction->price_snapshot, $withdrawalTransaction->crypto_amount, $withdrawalTransaction->usd_amount],
            'snapshot' => [$snapshot->managed_balance, $snapshot->total_profit_loss, $snapshot->performance_percentage],
        ];

        app(MarketPriceService::class)->update($this->admin, $asset, '70000', '4.2500');

        $this->assertSame($historicalValues['deposit'], [$deposit->fresh()->price_snapshot, $deposit->fresh()->usd_value]);
        $this->assertSame($historicalValues['withdrawal'], [$withdrawal->fresh()->price_snapshot, $withdrawal->fresh()->crypto_amount]);
        $this->assertSame($historicalValues['transaction'], [$depositTransaction->fresh()->price_snapshot, $depositTransaction->fresh()->usd_amount]);
        $this->assertSame(
            $historicalValues['withdrawal_transaction'],
            [$withdrawalTransaction->fresh()->price_snapshot, $withdrawalTransaction->fresh()->crypto_amount, $withdrawalTransaction->fresh()->usd_amount],
        );
        $this->assertSame(
            $historicalValues['snapshot'],
            [$snapshot->fresh()->managed_balance, $snapshot->fresh()->total_profit_loss, $snapshot->fresh()->performance_percentage],
        );
        $this->assertSame(1, Transaction::where('deposit_id', $deposit->id)->count());
        $this->assertSame(1, Transaction::where('withdrawal_id', $withdrawal->id)->count());
        $this->assertSame(1, Withdrawal::whereKey($withdrawal->id)->count());
        $this->assertSame(1, Deposit::whereKey($deposit->id)->count());
        $this->assertSame(1, PerformanceSnapshot::whereKey($snapshot->id)->count());
        $this->assertSame('70000.00000000', app(AssetPriceProvider::class)->currentUsdPriceFor($asset));
    }

    public function test_price_updates_do_not_change_account_or_transaction_state(): void
    {
        $asset = Asset::where('symbol', 'BTC')->firstOrFail();
        $user = User::factory()->create();
        $account = $user->account()->create([]);
        $account->refresh();
        $currentState = $account->only([
            'managed_balance',
            'pending_balance',
            'total_profit_loss',
            'performance_percentage',
            'trading_status',
            'tier_id',
        ]);

        app(MarketPriceService::class)->update($this->admin, $asset, '70000', '-3.0000');

        $this->assertSame($currentState, $account->fresh()->only(array_keys($currentState)));
        $this->assertDatabaseCount('transactions', 0);
    }
}
