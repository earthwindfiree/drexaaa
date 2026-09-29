<?php

namespace Tests\Feature;

use App\Contracts\AssetPriceProvider;
use App\Enums\DepositStatus;
use App\Models\Asset;
use App\Models\AssetWallet;
use App\Models\Deposit;
use App\Models\User;
use App\Services\AccountBalanceService;
use App\Services\DepositReviewService;
use App\Services\DepositSubmissionService;
use App\Support\FixedDecimalMath;
use Database\Seeders\AssetSeeder;
use Database\Seeders\StrategyTierSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class DepositWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private FakeAssetPriceProvider $priceProvider;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(StrategyTierSeeder::class);
        $this->seed(AssetSeeder::class);
        $this->priceProvider = new FakeAssetPriceProvider;
        $this->app->instance(AssetPriceProvider::class, $this->priceProvider);
    }

    public function test_submission_uses_server_state_and_only_increases_pending_balance(): void
    {
        [$user, $account] = $this->createUserWithAccount();
        $account = app(AccountBalanceService::class)->setManagedBalance($account, '100');
        $account->pending_balance = '12.34';
        $account->save();

        $deposit = $this->submit($user, '0.5');
        $account->refresh();
        $tierId = $account->tier_id;

        $this->assertSame(DepositStatus::Pending, $deposit->status);
        $this->assertSame($user->id, $deposit->user_id);
        $this->assertSame($account->id, $deposit->account_id);
        $this->assertSame('BTC', $deposit->asset->symbol);
        $this->assertTrue($deposit->assetWallet->asset->is($deposit->asset));
        $this->assertSame($deposit->asset->wallets()->firstOrFail()->id, $deposit->asset_wallet_id);
        $this->assertSame('50000.00000000', $deposit->price_snapshot);
        $this->assertSame('25000.00', $deposit->usd_value);
        $this->assertSame('25012.34', $account->pending_balance);
        $this->assertSame('100.00', $account->managed_balance);
        $this->assertSame($tierId, $account->tier_id);
        $this->assertDatabaseHas('user_notifications', [
            'user_id' => $user->id,
            'category' => 'financial',
            'title' => 'Deposit submitted',
        ]);
    }

    public function test_transaction_reference_is_required(): void
    {
        [$user] = $this->createUserWithAccount();

        $this->expectException(ValidationException::class);

        $this->submit($user, '0.01', '  ');
    }

    public function test_inactive_asset_cannot_be_used_for_a_deposit(): void
    {
        [$user] = $this->createUserWithAccount();
        $asset = Asset::where('symbol', 'BTC')->firstOrFail();
        $asset->update(['active' => false]);

        $this->expectException(ValidationException::class);

        $this->submit($user);
    }

    public function test_inactive_wallet_cannot_be_used_for_a_deposit(): void
    {
        [$user] = $this->createUserWithAccount();
        Asset::where('symbol', 'BTC')->firstOrFail()->wallets()->update(['active' => false]);

        $this->expectException(ValidationException::class);

        $this->submit($user);
    }

    public function test_ambiguous_active_wallet_configuration_is_rejected(): void
    {
        [$user] = $this->createUserWithAccount();
        $asset = Asset::where('symbol', 'BTC')->firstOrFail();
        AssetWallet::create([
            'asset_id' => $asset->id,
            'wallet_address' => 'SECOND-DEMO-WALLET',
            'active' => true,
        ]);

        $this->expectException(ValidationException::class);

        $this->submit($user);
    }

    public function test_deposit_controlled_fields_are_not_mass_assignable(): void
    {
        $deposit = new Deposit;

        foreach ([
            'user_id',
            'account_id',
            'asset_id',
            'asset_wallet_id',
            'status',
            'reviewed_by',
            'reviewed_at',
            'rejection_reason',
            'price_snapshot',
            'usd_value',
        ] as $field) {
            $this->assertFalse($deposit->isFillable($field));
        }

        $this->assertSame('0.010000000000000000', FixedDecimalMath::normalize('0.01', 18, 18, 'crypto_amount'));
    }

    public function test_usd_value_rounds_half_cents_without_floating_point_arithmetic(): void
    {
        [$user, $account] = $this->createUserWithAccount();

        $deposit = $this->submit($user, '0.0000001');

        $this->assertSame('0.01', $deposit->usd_value);
        $this->assertSame('0.01', $account->fresh()->pending_balance);
    }

    public function test_confirmation_moves_pending_value_to_managed_and_recalculates_tier(): void
    {
        [$user, $account] = $this->createUserWithAccount();
        $account->pending_balance = '12.34';
        $account->save();
        $deposit = $this->submit($user, '0.1');
        $this->priceProvider->price = '100000';
        $reviewer = User::factory()->create(['role' => 'admin']);

        $confirmed = app(DepositReviewService::class)->confirm($deposit, $reviewer);
        $account->refresh();

        $this->assertSame(DepositStatus::Confirmed, $confirmed->status);
        $this->assertSame('5000.00', $confirmed->usd_value);
        $this->assertSame('50000.00000000', $confirmed->price_snapshot);
        $this->assertSame('12.34', $account->pending_balance);
        $this->assertSame('5000.00', $account->managed_balance);
        $this->assertSame('Elevation', $account->tier->name);
        $this->assertSame($reviewer->id, $confirmed->reviewed_by);
        $this->assertNotNull($confirmed->reviewed_at);
        $this->assertDatabaseHas('user_notifications', ['user_id' => $user->id, 'title' => 'Deposit approved']);
        $this->assertDatabaseHas('user_notifications', ['user_id' => $user->id, 'title' => 'Tier changed']);
    }

    public function test_confirmed_deposit_cannot_be_confirmed_twice(): void
    {
        [$user, $account] = $this->createUserWithAccount();
        $deposit = $this->submit($user, '0.01');
        $reviewer = User::factory()->create(['role' => 'super_admin']);
        $reviewService = app(DepositReviewService::class);
        $reviewService->confirm($deposit, $reviewer);

        try {
            $reviewService->confirm($deposit, $reviewer);
            $this->fail('A confirmed deposit must not be processed again.');
        } catch (ValidationException) {
        }

        $account->refresh();
        $this->assertSame('500.00', $account->managed_balance);
        $this->assertSame('0.00', $account->pending_balance);
        $this->assertSame(1, $user->notifications()->where('title', 'Deposit approved')->count());
    }

    public function test_rejection_releases_pending_value_without_changing_managed_balance_or_tier(): void
    {
        [$user, $account] = $this->createUserWithAccount();
        $account = app(AccountBalanceService::class)->setManagedBalance($account, '100');
        $account->pending_balance = '12.34';
        $account->save();
        $deposit = $this->submit($user, '0.01');
        $reviewer = User::factory()->create(['role' => 'admin']);

        $rejected = app(DepositReviewService::class)->reject($deposit, $reviewer, 'Reference could not be matched.');
        $account->refresh();

        $this->assertSame(DepositStatus::Rejected, $rejected->status);
        $this->assertSame('Reference could not be matched.', $rejected->rejection_reason);
        $this->assertSame($reviewer->id, $rejected->reviewed_by);
        $this->assertNotNull($rejected->reviewed_at);
        $this->assertSame('12.34', $account->pending_balance);
        $this->assertSame('100.00', $account->managed_balance);
        $this->assertSame('Foundation', $account->tier->name);
        $this->assertDatabaseHas('user_notifications', ['user_id' => $user->id, 'title' => 'Deposit rejected']);
    }

    public function test_rejected_deposit_cannot_be_rejected_twice(): void
    {
        [$user, $account] = $this->createUserWithAccount();
        $deposit = $this->submit($user, '0.01');
        $reviewer = User::factory()->create(['role' => 'admin']);
        $reviewService = app(DepositReviewService::class);
        $reviewService->reject($deposit, $reviewer, 'Not received.');

        try {
            $reviewService->reject($deposit, $reviewer, 'Second rejection.');
            $this->fail('A rejected deposit must not be processed again.');
        } catch (ValidationException) {
        }

        $account->refresh();
        $this->assertSame('0.00', $account->pending_balance);
        $this->assertSame('0.00', $account->managed_balance);
        $this->assertSame(1, $user->notifications()->where('title', 'Deposit rejected')->count());
    }

    public function test_normal_user_cannot_review_a_deposit(): void
    {
        [$user, $account] = $this->createUserWithAccount();
        $deposit = $this->submit($user, '0.01');
        $reviewer = User::factory()->create();

        try {
            app(DepositReviewService::class)->confirm($deposit, $reviewer);
            $this->fail('A normal user must not confirm deposits.');
        } catch (AuthorizationException) {
        }

        $account->refresh();
        $this->assertSame(DepositStatus::Pending, $deposit->fresh()->status);
        $this->assertSame('500.00', $account->fresh()->pending_balance);
        $this->assertSame('0.00', $account->managed_balance);
    }

    private function createUserWithAccount(): array
    {
        $user = User::factory()->create();

        return [$user, $user->account()->create([])];
    }

    private function submit(User $user, string $amount = '0.01', string $reference = 'demo-tx-reference'): Deposit
    {
        $asset = Asset::where('symbol', 'BTC')->firstOrFail();

        return app(DepositSubmissionService::class)->submit($user, $asset->id, $amount, $reference);
    }
}

final class FakeAssetPriceProvider implements AssetPriceProvider
{
    public string $price = '50000';

    public function currentUsdPriceFor(Asset $asset): string
    {
        return $this->price;
    }
}
