<?php

namespace Tests\Feature;

use App\Contracts\AssetPriceProvider;
use App\Enums\WithdrawalStatus;
use App\Models\Asset;
use App\Models\User;
use App\Models\Withdrawal;
use App\Services\AccountBalanceService;
use App\Services\WithdrawalAvailabilityService;
use App\Services\WithdrawalReviewService;
use App\Services\WithdrawalSubmissionService;
use Database\Seeders\AssetSeeder;
use Database\Seeders\StrategyTierSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class WithdrawalWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(StrategyTierSeeder::class);
        $this->seed(AssetSeeder::class);
        $this->app->instance(AssetPriceProvider::class, new FakeWithdrawalPriceProvider);
    }

    public function test_submission_reserves_amount_without_changing_account_balances_or_tier(): void
    {
        [$user, $account] = $this->createUserWithBalance('1000');
        $account->pending_balance = '17.25';
        $account->save();

        $withdrawal = $this->submit($user, '100.00');
        $account->refresh();

        $this->assertSame(WithdrawalStatus::Pending, $withdrawal->status);
        $this->assertSame($user->id, $withdrawal->user_id);
        $this->assertSame($account->id, $withdrawal->account_id);
        $this->assertSame('BTC', $withdrawal->asset->symbol);
        $this->assertSame('100.00', $withdrawal->amount);
        $this->assertSame('external-destination-wallet', $withdrawal->destination_wallet);
        $this->assertSame('0.002000000000000000', $withdrawal->crypto_amount);
        $this->assertSame('50000.00000000', $withdrawal->price_snapshot);
        $this->assertSame('1000.00', $account->managed_balance);
        $this->assertSame('17.25', $account->pending_balance);
        $this->assertSame('Momentum', $account->tier->name);
        $this->assertSame('900.00', app(WithdrawalAvailabilityService::class)->withdrawableAmount($account));
    }

    public function test_amount_must_be_positive(): void
    {
        [$user] = $this->createUserWithBalance('1000');

        $this->expectException(ValidationException::class);

        $this->submit($user, '0.00');
    }

    public function test_destination_wallet_is_required(): void
    {
        [$user] = $this->createUserWithBalance('1000');

        $this->expectException(ValidationException::class);

        $this->submit($user, '1.00', '   ');
    }

    public function test_inactive_asset_cannot_be_selected(): void
    {
        [$user] = $this->createUserWithBalance('1000');
        Asset::where('symbol', 'BTC')->update(['active' => false]);

        $this->expectException(ValidationException::class);

        $this->submit($user, '1.00');
    }

    public function test_pending_withdrawals_reserve_funds_and_allow_exact_remaining_amount(): void
    {
        [$user, $account] = $this->createUserWithBalance('10000');
        $first = $this->submit($user, '2000.00');
        $this->assertSame('8000.00', app(WithdrawalAvailabilityService::class)->withdrawableAmount($account));

        $second = $this->submit($user, '8000.00');
        $this->assertSame(WithdrawalStatus::Pending, $first->status);
        $this->assertSame(WithdrawalStatus::Pending, $second->status);
        $this->assertSame('0.00', app(WithdrawalAvailabilityService::class)->withdrawableAmount($account));
    }

    public function test_amount_above_remaining_withdrawable_value_is_rejected(): void
    {
        [$user, $account] = $this->createUserWithBalance('10000');
        $this->submit($user, '8000.00');

        try {
            $this->submit($user, '2000.01');
            $this->fail('The request must not exceed the remaining reservation amount.');
        } catch (ValidationException) {
        }

        $this->assertSame(1, $account->withdrawals()->count());
        $this->assertSame('2000.00', app(WithdrawalAvailabilityService::class)->withdrawableAmount($account));
    }

    public function test_submission_works_without_a_market_price_provider_and_leaves_estimate_null(): void
    {
        [$user] = $this->createUserWithBalance('1000');
        $asset = Asset::where('symbol', 'ETH')->firstOrFail();
        $service = new WithdrawalSubmissionService(app(WithdrawalAvailabilityService::class));

        $withdrawal = $service->submit($user, $asset->id, '25.00', 'external-destination-wallet');

        $this->assertNull($withdrawal->crypto_amount);
        $this->assertNull($withdrawal->price_snapshot);
    }

    public function test_server_controlled_fields_are_not_mass_assignable(): void
    {
        $withdrawal = new Withdrawal;

        foreach (['user_id', 'account_id', 'asset_id', 'amount', 'destination_wallet', 'status', 'reviewed_by', 'reviewed_at', 'rejection_reason', 'crypto_amount', 'price_snapshot'] as $field) {
            $this->assertFalse($withdrawal->isFillable($field));
        }
    }

    public function test_approval_deducts_managed_balance_recalculates_tier_and_releases_reservation(): void
    {
        [$user, $account] = $this->createUserWithBalance('1000');
        $account->pending_balance = '17.25';
        $account->save();
        $withdrawal = $this->submit($user, '100.00');
        $reviewer = User::factory()->create(['role' => 'admin']);

        $approved = app(WithdrawalReviewService::class)->approve($withdrawal, $reviewer);
        $account->refresh();

        $this->assertSame(WithdrawalStatus::Approved, $approved->status);
        $this->assertSame('900.00', $account->managed_balance);
        $this->assertSame('17.25', $account->pending_balance);
        $this->assertSame('Foundation', $account->tier->name);
        $this->assertSame($reviewer->id, $approved->reviewed_by);
        $this->assertNotNull($approved->reviewed_at);
        $this->assertSame('900.00', app(WithdrawalAvailabilityService::class)->withdrawableAmount($account));
    }

    public function test_approved_withdrawal_cannot_be_processed_twice(): void
    {
        [$user, $account] = $this->createUserWithBalance('1000');
        $withdrawal = $this->submit($user, '100.00');
        $reviewer = User::factory()->create(['role' => 'super_admin']);
        $reviewService = app(WithdrawalReviewService::class);
        $reviewService->approve($withdrawal, $reviewer);

        try {
            $reviewService->approve($withdrawal, $reviewer);
            $this->fail('An approved withdrawal must not be processed again.');
        } catch (ValidationException) {
        }

        $this->assertSame('900.00', $account->fresh()->managed_balance);
    }

    public function test_insufficient_managed_balance_at_approval_keeps_withdrawal_pending(): void
    {
        [$user, $account] = $this->createUserWithBalance('1000');
        $withdrawal = $this->submit($user, '800.00');
        app(AccountBalanceService::class)->setManagedBalance($account, '500.00');
        $reviewer = User::factory()->create(['role' => 'admin']);

        try {
            app(WithdrawalReviewService::class)->approve($withdrawal, $reviewer);
            $this->fail('Approval must fail when managed funds no longer cover the withdrawal.');
        } catch (ValidationException) {
        }

        $this->assertSame(WithdrawalStatus::Pending, $withdrawal->fresh()->status);
        $this->assertSame('500.00', $account->fresh()->managed_balance);
        $this->assertNull($withdrawal->fresh()->reviewed_at);
    }

    public function test_rejection_releases_reservation_without_changing_balance_or_tier(): void
    {
        [$user, $account] = $this->createUserWithBalance('1000');
        $account->pending_balance = '13.45';
        $account->save();
        $withdrawal = $this->submit($user, '100.00');
        $reviewer = User::factory()->create(['role' => 'admin']);

        $rejected = app(WithdrawalReviewService::class)->reject($withdrawal, $reviewer, 'Destination could not be approved.');
        $account->refresh();

        $this->assertSame(WithdrawalStatus::Rejected, $rejected->status);
        $this->assertSame('Destination could not be approved.', $rejected->rejection_reason);
        $this->assertSame($reviewer->id, $rejected->reviewed_by);
        $this->assertNotNull($rejected->reviewed_at);
        $this->assertSame('1000.00', $account->managed_balance);
        $this->assertSame('13.45', $account->pending_balance);
        $this->assertSame('Momentum', $account->tier->name);
        $this->assertSame('1000.00', app(WithdrawalAvailabilityService::class)->withdrawableAmount($account));
    }

    public function test_rejected_withdrawal_cannot_be_rejected_or_approved_again(): void
    {
        [$user] = $this->createUserWithBalance('1000');
        $withdrawal = $this->submit($user, '100.00');
        $reviewer = User::factory()->create(['role' => 'admin']);
        $reviewService = app(WithdrawalReviewService::class);
        $reviewService->reject($withdrawal, $reviewer, 'Not approved.');

        foreach ([
            fn () => $reviewService->reject($withdrawal, $reviewer, 'Second reason.'),
            fn () => $reviewService->approve($withdrawal, $reviewer),
        ] as $processAgain) {
            try {
                $processAgain();
                $this->fail('A rejected withdrawal must not be processed again.');
            } catch (ValidationException) {
            }
        }

        $this->assertSame(WithdrawalStatus::Rejected, $withdrawal->fresh()->status);
    }

    public function test_normal_user_cannot_review_a_withdrawal(): void
    {
        [$user, $account] = $this->createUserWithBalance('1000');
        $withdrawal = $this->submit($user, '100.00');
        $reviewer = User::factory()->create();

        $this->expectException(AuthorizationException::class);

        app(WithdrawalReviewService::class)->approve($withdrawal, $reviewer);
    }

    private function createUserWithBalance(string $balance): array
    {
        $user = User::factory()->create();
        $account = $user->account()->create([]);

        return [$user, app(AccountBalanceService::class)->setManagedBalance($account, $balance)];
    }

    private function submit(User $user, string $amount, string $destinationWallet = 'external-destination-wallet'): Withdrawal
    {
        $asset = Asset::where('symbol', 'BTC')->firstOrFail();

        return app(WithdrawalSubmissionService::class)->submit($user, $asset->id, $amount, $destinationWallet);
    }
}

final class FakeWithdrawalPriceProvider implements AssetPriceProvider
{
    public function currentUsdPriceFor(Asset $asset): string
    {
        return '50000';
    }
}
