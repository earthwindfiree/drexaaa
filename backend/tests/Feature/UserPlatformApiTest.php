<?php

namespace Tests\Feature;

use App\Contracts\AssetPriceProvider;
use App\Models\Account;
use App\Models\Asset;
use App\Models\Transaction;
use App\Models\User;
use App\Services\AccountBalanceService;
use App\Services\DepositSubmissionService;
use App\Services\PerformanceSnapshotService;
use App\Services\UserNotificationService;
use App\Services\WithdrawalSubmissionService;
use Database\Seeders\AssetSeeder;
use Database\Seeders\MarketPriceSeeder;
use Database\Seeders\StrategyTierSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class UserPlatformApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(StrategyTierSeeder::class);
        $this->seed(AssetSeeder::class);
        $this->seed(MarketPriceSeeder::class);
        $this->app->instance(AssetPriceProvider::class, new FixedUserApiPriceProvider);
    }

    public function test_guest_and_unverified_user_are_denied_verified_user_apis(): void
    {
        $this->getJson('/api/dashboard')->assertUnauthorized();

        $unverified = User::factory()->unverified()->create();
        $this->actingAs($unverified)
            ->getJson('/api/dashboard')
            ->assertForbidden()
            ->assertJsonPath('code', 'email_verification_required');
    }

    public function test_dashboard_and_portfolio_use_only_the_authenticated_users_account(): void
    {
        $owner = User::factory()->create();
        $account = $this->createAccount($owner, '1200.00');
        $account->forceFill([
            'total_profit_loss' => '125.50',
            'performance_percentage' => '10.4583',
            'trading_status' => 'active',
        ])->save();
        $other = User::factory()->create();
        $otherAccount = $this->createAccount($other, '9000.00');

        $this->createTransaction($account, 'adjustment', null, '125.50', 'OWN-ADJ');
        $this->createTransaction($otherAccount, 'adjustment', null, '9000.00', 'OTHER-ADJ');
        app(PerformanceSnapshotService::class)->create(
            User::factory()->create(['role' => 'super_admin']),
            $account,
            '1200.00',
            '125.50',
            '10.4583',
            Carbon::parse('2026-09-01 12:00:00'),
        );

        $this->actingAs($owner)
            ->getJson('/api/dashboard?user_id='.$other->id)
            ->assertOk()
            ->assertJsonPath('data.account.managed_balance', '1200.00')
            ->assertJsonPath('data.account.pending_balance', '0.00')
            ->assertJsonPath('data.account.total_profit_loss', '125.50')
            ->assertJsonPath('data.account.performance_percentage', '10.4583')
            ->assertJsonPath('data.account.trading_status', 'active')
            ->assertJsonPath('data.account.tier.name', 'Momentum')
            ->assertJsonPath('data.account.tier.strategy.name', 'Momentum')
            ->assertJsonPath('data.recent_transactions.0.reference', 'OWN-ADJ')
            ->assertJsonMissing(['reference' => 'OTHER-ADJ']);

        $this->actingAs($owner)
            ->getJson('/api/portfolio')
            ->assertOk()
            ->assertJsonPath('data.managed_balance', '1200.00')
            ->assertJsonPath('data.tier.strategy.description', $account->fresh()->tier->strategy->description)
            ->assertJsonPath('data.performance_history.0.account_value', '1200.00')
            ->assertJsonMissing(['account_value' => '9000.00']);
    }

    public function test_markets_returns_only_active_simulated_assets_and_persisted_prices(): void
    {
        Asset::where('symbol', 'LTC')->update(['active' => false]);

        $this->actingAs(User::factory()->create())
            ->getJson('/api/markets')
            ->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonPath('data.0.symbol', 'BTC')
            ->assertJsonPath('data.0.market_price.current_price', '67540.00000000')
            ->assertJsonPath('data.0.market_price.change_24h_percentage', '3.4200')
            ->assertJsonMissing(['symbol' => 'LTC']);
    }

    public function test_transactions_are_user_scoped_filterable_paginated_and_keep_stored_values(): void
    {
        $owner = User::factory()->create();
        $ownerAccount = $this->createAccount($owner, '1000.00');
        $otherAccount = $this->createAccount(User::factory()->create(), '500.00');
        $btc = Asset::where('symbol', 'BTC')->firstOrFail();
        $eth = Asset::where('symbol', 'ETH')->firstOrFail();

        $this->createTransaction($ownerAccount, 'deposit', $btc, '25.00', 'OWN-DEP-1', '0.010000000000000000', '2500.00000000');
        $this->createTransaction($ownerAccount, 'withdrawal', $eth, '10.00', 'OWN-WD-1');
        $this->createTransaction($ownerAccount, 'adjustment', null, '5.00', 'OWN-ADJ-1');
        $this->createTransaction($ownerAccount, 'deposit', $btc, '26.00', 'OWN-DEP-2', '0.010000000000000000', '2600.00000000');
        $this->createTransaction($otherAccount, 'deposit', $btc, '900.00', 'OTHER-DEP');

        $this->actingAs($owner)
            ->getJson('/api/transactions?type=deposit&asset_id='.$btc->id.'&per_page=1&page=2')
            ->assertOk()
            ->assertJsonPath('meta.total', 2)
            ->assertJsonPath('meta.current_page', 2)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.reference', 'OWN-DEP-1')
            ->assertJsonPath('data.0.usd_amount', '25.00')
            ->assertJsonPath('data.0.price_snapshot', '2500.00000000')
            ->assertJsonMissing(['reference' => 'OTHER-DEP']);
    }

    public function test_wallet_exposes_only_the_users_existing_requests_and_configured_active_deposit_wallets(): void
    {
        $owner = User::factory()->create();
        $ownerAccount = $this->createAccount($owner, '1000.00');
        $other = User::factory()->create();
        $otherAccount = $this->createAccount($other, '1000.00');
        $btc = Asset::where('symbol', 'BTC')->firstOrFail();

        app(DepositSubmissionService::class)->submit($owner, $btc->id, '0.001', 'OWN-PENDING-DEP');
        app(WithdrawalSubmissionService::class)->submit($owner, $btc->id, '100.00', 'own-destination-wallet');
        app(DepositSubmissionService::class)->submit($other, $btc->id, '0.002', 'OTHER-PENDING-DEP');
        app(WithdrawalSubmissionService::class)->submit($other, $btc->id, '200.00', 'other-destination-wallet');

        $this->actingAs($owner)
            ->getJson('/api/wallet')
            ->assertOk()
            ->assertJsonPath('data.account.managed_balance', '1000.00')
            ->assertJsonPath('data.account.pending_balance', '67.54')
            ->assertJsonPath('data.account.withdrawable_amount', '900.00')
            ->assertJsonPath('data.assets.0.symbol', 'BTC')
            ->assertJsonPath('data.assets.0.wallets.0.wallet_address', 'DEMO-ONLY-NOT-A-VALID-BTC-WALLET')
            ->assertJsonPath('data.deposits.0.transaction_reference', 'OWN-PENDING-DEP')
            ->assertJsonPath('data.withdrawals.0.destination_wallet', 'own-destination-wallet')
            ->assertJsonMissing(['transaction_reference' => 'OTHER-PENDING-DEP'])
            ->assertJsonMissing(['destination_wallet' => 'other-destination-wallet']);
    }

    public function test_notifications_are_scoped_readable_and_mark_read_operations_are_owned(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $service = app(UserNotificationService::class);
        $first = $service->create($owner, 'account', 'First notice', 'First message.');
        $second = $service->create($owner, 'security', 'Second notice', 'Second message.');
        $foreign = $service->create($other, 'account', 'Foreign notice', 'Other user message.');

        $this->actingAs($owner)
            ->getJson('/api/notifications?status=unread')
            ->assertOk()
            ->assertJsonPath('unread_count', 2)
            ->assertJsonCount(2, 'data')
            ->assertJsonMissing(['title' => 'Foreign notice']);

        $this->actingAs($owner)->patchJson('/api/notifications/'.$foreign->id.'/read')->assertNotFound();
        $this->actingAs($owner)
            ->patchJson('/api/notifications/'.$first->id.'/read')
            ->assertOk()
            ->assertJsonPath('data.id', $first->id)
            ->assertJsonPath('data.read_at', fn ($value) => is_string($value));

        $this->actingAs($owner)->patchJson('/api/notifications/read-all')->assertOk()->assertJsonPath('data.updated', 1);
        $this->assertNotNull($second->fresh()->read_at);
        $this->assertNull($foreign->fresh()->read_at);
    }

    public function test_settings_return_only_the_authenticated_users_safe_profile(): void
    {
        $owner = User::factory()->create(['name' => 'Profile Owner']);
        $other = User::factory()->create(['name' => 'Private Profile']);

        $this->actingAs($owner)
            ->getJson('/api/profile?user_id='.$other->id)
            ->assertOk()
            ->assertJsonPath('user.name', 'Profile Owner')
            ->assertJsonMissingPath('user.password')
            ->assertJsonMissingPath('user.remember_token')
            ->assertJsonMissingPath('user.personal_access_tokens')
            ->assertJsonMissing(['name' => 'Private Profile']);
    }

    private function createAccount(User $user, string $managedBalance): Account
    {
        $account = $user->account()->create([]);

        return app(AccountBalanceService::class)->setManagedBalance($account, $managedBalance);
    }

    private function createTransaction(
        Account $account,
        string $type,
        ?Asset $asset,
        string $usdAmount,
        string $reference,
        ?string $cryptoAmount = null,
        ?string $priceSnapshot = null,
    ): Transaction {
        return Transaction::query()->forceCreate([
            'user_id' => $account->user_id,
            'account_id' => $account->id,
            'asset_id' => $asset?->id,
            'type' => $type,
            'status' => 'completed',
            'crypto_amount' => $cryptoAmount,
            'usd_amount' => $usdAmount,
            'price_snapshot' => $priceSnapshot,
            'reference' => $reference,
            'occurred_at' => now(),
        ]);
    }
}

final class FixedUserApiPriceProvider implements AssetPriceProvider
{
    public function currentUsdPriceFor(Asset $asset): string
    {
        return match ($asset->symbol) {
            'BTC' => '67540',
            'ETH' => '3420',
            'LTC' => '92.3',
            'USDT' => '1',
            default => '100',
        };
    }
}
