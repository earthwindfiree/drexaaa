<?php

namespace Tests\Feature;

use App\Contracts\AssetPriceProvider;
use App\Models\Asset;
use App\Models\AuditLog;
use App\Models\Transaction;
use App\Models\User;
use App\Services\AccountBalanceService;
use App\Services\DepositReviewService;
use App\Services\DepositSubmissionService;
use App\Services\TransactionRecordService;
use App\Services\WithdrawalReviewService;
use App\Services\WithdrawalSubmissionService;
use Database\Seeders\AssetSeeder;
use Database\Seeders\MarketPriceSeeder;
use Database\Seeders\StrategyTierSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminTransactionsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(StrategyTierSeeder::class);
        $this->seed(AssetSeeder::class);
        $this->seed(MarketPriceSeeder::class);
        $this->app->instance(AssetPriceProvider::class, new FakeAdminTransactionPriceProvider);
    }

    public function test_guests_normal_users_and_suspended_admins_are_rejected(): void
    {
        $this->createTransactionSet();
        $this->getJson('/api/admin/transactions')->assertUnauthorized();

        foreach ([
            User::factory()->create(),
            User::factory()->create(['role' => 'admin', 'status' => 'suspended']),
        ] as $user) {
            $this->actingAs($user)->getJson('/api/admin/transactions')->assertForbidden();
        }
    }

    public function test_active_admin_and_super_admin_can_list_all_transaction_types_without_sensitive_fields(): void
    {
        $transactions = $this->createTransactionSet();

        foreach (['admin', 'super_admin'] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]))
                ->getJson('/api/admin/transactions')
                ->assertOk()
                ->assertJsonCount(3, 'data')
                ->assertJsonPath('meta.total', 3)
                ->assertJsonPath('data.0.type', 'adjustment')
                ->assertJsonPath('data.1.type', 'withdrawal')
                ->assertJsonPath('data.2.type', 'deposit')
                ->assertJsonPath('data.2.usd_amount', '500.00')
                ->assertJsonPath('data.2.price_snapshot', '50000.00000000')
                ->assertJsonPath('data.2.related_deposit.id', $transactions['deposit']->id)
                ->assertJsonPath('data.1.related_withdrawal.id', $transactions['withdrawal']->id)
                ->assertJsonMissingPath('data.0.user.password')
                ->assertJsonMissingPath('data.0.user.remember_token');
        }
    }

    public function test_filters_search_empty_results_and_pagination_are_server_side(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $transactions = $this->createTransactionSet();

        $this->actingAs($admin)
            ->getJson('/api/admin/transactions?type=deposit&status=completed&asset_id='.$transactions['deposit']->asset_id.'&search=Transaction%20Owner')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $transactions['depositTransaction']->id)
            ->assertJsonPath('meta.total', 1);

        $this->actingAs($admin)
            ->getJson('/api/admin/transactions?type=withdrawal&search=does-not-exist')
            ->assertOk()
            ->assertJsonCount(0, 'data')
            ->assertJsonPath('meta.total', 0);

        for ($index = 0; $index < 15; $index++) {
            $user = User::factory()->create();
            $account = $user->account()->create([]);
            app(TransactionRecordService::class)->recordAccountAdjustment($account, '10.00', 'Pagination adjustment');
        }

        $this->actingAs($admin)
            ->getJson('/api/admin/transactions?per_page=5&page=2')
            ->assertOk()
            ->assertJsonPath('meta.per_page', 5)
            ->assertJsonPath('meta.current_page', 2)
            ->assertJsonPath('meta.total', 18)
            ->assertJsonCount(5, 'data');
    }

    public function test_historical_values_and_relationships_are_read_without_mutating_financial_state(): void
    {
        $transactions = $this->createTransactionSet();
        $account = $transactions['account'];
        $before = $account->fresh()->only(['managed_balance', 'pending_balance', 'tier_id']);
        $transactionCount = Transaction::count();
        $auditCount = AuditLog::count();

        $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->getJson('/api/admin/transactions')
            ->assertOk()
            ->assertJsonPath('data.2.crypto_amount', '0.010000000000000000')
            ->assertJsonPath('data.2.usd_amount', '500.00')
            ->assertJsonPath('data.1.crypto_amount', '0.002000000000000000')
            ->assertJsonPath('data.1.usd_amount', '100.00')
            ->assertJsonPath('data.0.account.id', $account->id);

        $this->assertSame($before, $account->fresh()->only(array_keys($before)));
        $this->assertSame($transactionCount, Transaction::count());
        $this->assertSame($auditCount, AuditLog::count());
    }

    private function createTransactionSet(): array
    {
        $owner = User::factory()->create(['name' => 'Transaction Owner', 'email' => 'transaction-owner@example.com']);
        $account = $owner->account()->create([]);
        $account = app(AccountBalanceService::class)->setManagedBalance($account, '1000.00');
        $admin = User::factory()->create(['role' => 'admin']);
        $deposit = app(DepositSubmissionService::class)->submit($owner, Asset::where('symbol', 'BTC')->firstOrFail()->id, '0.01', 'transaction-deposit');
        app(DepositReviewService::class)->confirm($deposit, $admin);
        $withdrawal = app(WithdrawalSubmissionService::class)->submit($owner, Asset::where('symbol', 'BTC')->firstOrFail()->id, '100.00', 'transaction-withdrawal');
        app(WithdrawalReviewService::class)->approve($withdrawal, $admin);
        $adjustment = app(TransactionRecordService::class)->recordAccountAdjustment($account->fresh(), '25.00', 'Transaction review adjustment');

        return [
            'account' => $account,
            'deposit' => $deposit,
            'depositTransaction' => $deposit->fresh()->transaction,
            'withdrawal' => $withdrawal,
            'withdrawalTransaction' => $withdrawal->fresh()->transaction,
            'adjustment' => $adjustment,
        ];
    }
}

final class FakeAdminTransactionPriceProvider implements AssetPriceProvider
{
    public function currentUsdPriceFor(Asset $asset): string
    {
        return '50000';
    }
}
