<?php

namespace Tests\Feature;

use App\Contracts\AssetPriceProvider;
use App\Models\Asset;
use App\Models\User;
use App\Services\AccountBalanceService;
use App\Services\DepositSubmissionService;
use App\Services\WithdrawalAvailabilityService;
use App\Services\WithdrawalSubmissionService;
use Database\Seeders\AssetSeeder;
use Database\Seeders\MarketPriceSeeder;
use Database\Seeders\StrategyTierSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserFinancialWorkflowApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(StrategyTierSeeder::class);
        $this->seed(AssetSeeder::class);
        $this->seed(MarketPriceSeeder::class);
        $this->app->instance(AssetPriceProvider::class, new FixedUserFinancialApiPriceProvider);
    }

    public function test_deposit_submission_uses_server_valuation_and_only_updates_pending_balance(): void
    {
        $owner = User::factory()->create();
        $account = $owner->account()->create([]);
        app(AccountBalanceService::class)->setManagedBalance($account, '100.00');

        $this->actingAs($owner)
            ->postJson('/api/deposits', [
                'asset_id' => Asset::where('symbol', 'BTC')->value('id'),
                'crypto_amount' => '0.1',
                'transaction_reference' => 'user-api-deposit-1',
            ])
            ->assertCreated()
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.price_snapshot', '50000.00000000')
            ->assertJsonPath('data.usd_value', '5000.00')
            ->assertJsonPath('data.transaction_reference', 'user-api-deposit-1');

        $account->refresh();
        $this->assertSame('100.00', $account->managed_balance);
        $this->assertSame('5000.00', $account->pending_balance);
        $this->assertSame('Foundation', $account->tier->name);
        $this->assertDatabaseHas('user_notifications', [
            'user_id' => $owner->id,
            'title' => 'Deposit submitted',
        ]);
    }

    public function test_deposit_submission_rejects_client_controlled_fields_without_mutation(): void
    {
        $owner = User::factory()->create();
        $account = $owner->account()->create([]);

        $this->actingAs($owner)
            ->postJson('/api/deposits', [
                'asset_id' => Asset::where('symbol', 'BTC')->value('id'),
                'crypto_amount' => '0.1',
                'transaction_reference' => 'client-fields-deposit',
                'usd_value' => '1.00',
                'price_snapshot' => '1',
                'status' => 'confirmed',
                'user_id' => User::factory()->create()->id,
                'account_id' => 999,
            ])
            ->assertUnprocessable();

        $this->assertSame('0.00', $account->fresh()->pending_balance);
        $this->assertDatabaseCount('deposits', 0);
        $this->assertDatabaseCount('user_notifications', 0);
    }

    public function test_deposit_submission_rejects_inactive_assets_and_invalid_references(): void
    {
        $owner = User::factory()->create();
        $owner->account()->create([]);
        $btc = Asset::where('symbol', 'BTC')->firstOrFail();

        $this->actingAs($owner)->postJson('/api/deposits', [
            'asset_id' => $btc->id,
            'crypto_amount' => '0.01',
            'transaction_reference' => 'inactive-asset',
        ])->assertCreated();

        $btc->update(['active' => false]);
        $this->actingAs($owner)->postJson('/api/deposits', [
            'asset_id' => $btc->id,
            'crypto_amount' => '0.01',
            'transaction_reference' => 'inactive-asset-2',
        ])->assertUnprocessable();
        $this->actingAs($owner)->postJson('/api/deposits', [
            'asset_id' => Asset::where('symbol', 'ETH')->value('id'),
            'crypto_amount' => '0.01',
            'transaction_reference' => '   ',
        ])->assertUnprocessable();

        $this->assertDatabaseCount('deposits', 1);
    }

    public function test_user_deposit_history_and_details_are_owner_scoped(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $owner->account()->create([]);
        $other->account()->create([]);
        $assetId = Asset::where('symbol', 'BTC')->value('id');
        $ownDeposit = app(DepositSubmissionService::class)->submit($owner, $assetId, '0.01', 'own-history');
        $otherDeposit = app(DepositSubmissionService::class)->submit($other, $assetId, '0.02', 'foreign-history');

        $this->actingAs($owner)
            ->getJson('/api/deposits?status=pending&per_page=1')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.id', $ownDeposit->id)
            ->assertJsonMissing(['transaction_reference' => 'foreign-history']);
        $this->actingAs($owner)->getJson('/api/deposits/'.$ownDeposit->id)
            ->assertOk()->assertJsonPath('data.transaction_reference', 'own-history');
        $this->actingAs($owner)->getJson('/api/deposits/'.$otherDeposit->id)->assertNotFound();
    }

    public function test_withdrawal_submission_reserves_amount_without_changing_managed_balance_or_tier(): void
    {
        $owner = User::factory()->create();
        $account = $owner->account()->create([]);
        app(AccountBalanceService::class)->setManagedBalance($account, '1000.00');

        $this->actingAs($owner)
            ->postJson('/api/withdrawals', [
                'asset_id' => Asset::where('symbol', 'BTC')->value('id'),
                'amount' => '800.00',
                'destination_wallet' => 'owner-destination',
            ])
            ->assertCreated()
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.amount', '800.00')
            ->assertJsonPath('data.crypto_amount', '0.016000000000000000')
            ->assertJsonPath('data.price_snapshot', '50000.00000000');

        $account->refresh();
        $this->assertSame('1000.00', $account->managed_balance);
        $this->assertSame('Momentum', $account->tier->name);
        $this->assertSame('200.00', app(WithdrawalAvailabilityService::class)->withdrawableAmount($account));
        $this->assertDatabaseHas('user_notifications', [
            'user_id' => $owner->id,
            'title' => 'Withdrawal submitted',
        ]);
    }

    public function test_withdrawal_submission_rejects_insufficient_funds_inactive_asset_and_client_calculations(): void
    {
        $owner = User::factory()->create();
        $account = $owner->account()->create([]);
        app(AccountBalanceService::class)->setManagedBalance($account, '1000.00');
        $btcId = Asset::where('symbol', 'BTC')->value('id');

        $this->actingAs($owner)->postJson('/api/withdrawals', [
            'asset_id' => $btcId,
            'amount' => '1000.01',
            'destination_wallet' => 'owner-destination',
        ])->assertUnprocessable();

        $this->actingAs($owner)->postJson('/api/withdrawals', [
            'asset_id' => $btcId,
            'amount' => '10.00',
            'destination_wallet' => 'owner-destination',
            'crypto_amount' => '100',
            'price_snapshot' => '1',
            'status' => 'approved',
            'user_id' => User::factory()->create()->id,
            'account_id' => 999,
        ])->assertUnprocessable();

        Asset::whereKey($btcId)->update(['active' => false]);
        $this->actingAs($owner)->postJson('/api/withdrawals', [
            'asset_id' => $btcId,
            'amount' => '10.00',
            'destination_wallet' => 'owner-destination',
        ])->assertUnprocessable();

        $this->assertSame('1000.00', $account->fresh()->managed_balance);
        $this->assertDatabaseCount('withdrawals', 0);
        $this->assertDatabaseCount('user_notifications', 0);
    }

    public function test_user_withdrawal_history_and_details_are_owner_scoped(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $ownerAccount = $owner->account()->create([]);
        $otherAccount = $other->account()->create([]);
        app(AccountBalanceService::class)->setManagedBalance($ownerAccount, '1000.00');
        app(AccountBalanceService::class)->setManagedBalance($otherAccount, '1000.00');
        $assetId = Asset::where('symbol', 'BTC')->value('id');
        $ownWithdrawal = app(WithdrawalSubmissionService::class)->submit($owner, $assetId, '100.00', 'own-destination');
        $otherWithdrawal = app(WithdrawalSubmissionService::class)->submit($other, $assetId, '200.00', 'foreign-destination');

        $this->actingAs($owner)
            ->getJson('/api/withdrawals?status=pending')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.id', $ownWithdrawal->id)
            ->assertJsonMissing(['destination_wallet' => 'foreign-destination']);
        $this->actingAs($owner)->getJson('/api/withdrawals/'.$ownWithdrawal->id)
            ->assertOk()->assertJsonPath('data.destination_wallet', 'own-destination');
        $this->actingAs($owner)->getJson('/api/withdrawals/'.$otherWithdrawal->id)->assertNotFound();
    }

    public function test_unverified_and_unauthenticated_users_cannot_submit_financial_requests(): void
    {
        $assetId = Asset::where('symbol', 'BTC')->value('id');
        $this->postJson('/api/deposits', [
            'asset_id' => $assetId,
            'crypto_amount' => '0.01',
            'transaction_reference' => 'guest',
        ])->assertUnauthorized();
        $this->postJson('/api/withdrawals', [
            'asset_id' => $assetId,
            'amount' => '10.00',
            'destination_wallet' => 'guest-destination',
        ])->assertUnauthorized();

        $unverified = User::factory()->unverified()->create();
        $unverified->account()->create([]);
        $this->actingAs($unverified)->postJson('/api/deposits', [
            'asset_id' => $assetId,
            'crypto_amount' => '0.01',
            'transaction_reference' => 'unverified',
        ])->assertForbidden()->assertJsonPath('code', 'email_verification_required');
        $this->actingAs($unverified)->postJson('/api/withdrawals', [
            'asset_id' => $assetId,
            'amount' => '10.00',
            'destination_wallet' => 'unverified-destination',
        ])->assertForbidden()->assertJsonPath('code', 'email_verification_required');

        $this->assertDatabaseCount('deposits', 0);
        $this->assertDatabaseCount('withdrawals', 0);
    }
}

final class FixedUserFinancialApiPriceProvider implements AssetPriceProvider
{
    public function currentUsdPriceFor(Asset $asset): string
    {
        return '50000';
    }
}
