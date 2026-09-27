<?php

namespace Tests\Feature;

use App\Contracts\AssetPriceProvider;
use App\Enums\DepositStatus;
use App\Enums\TransactionStatus;
use App\Enums\TransactionType;
use App\Models\Account;
use App\Models\Asset;
use App\Models\Deposit;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Withdrawal;
use App\Services\AccountBalanceService;
use App\Services\DepositReviewService;
use App\Services\DepositSubmissionService;
use App\Services\TransactionHistoryService;
use App\Services\TransactionRecordService;
use App\Services\WithdrawalReviewService;
use App\Services\WithdrawalSubmissionService;
use Database\Seeders\AssetSeeder;
use Database\Seeders\StrategyTierSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Tests\TestCase;

class TransactionWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(StrategyTierSeeder::class);
        $this->seed(AssetSeeder::class);
        $this->app->instance(AssetPriceProvider::class, new FakeTransactionPriceProvider);
    }

    public function test_confirmed_deposit_creates_one_historical_transaction(): void
    {
        [$user, $account] = $this->createUserWithBalance('100');
        $deposit = $this->submitDeposit($user, '0.5');
        $reviewer = User::factory()->create(['role' => 'admin']);

        $confirmed = app(DepositReviewService::class)->confirm($deposit, $reviewer);
        $transaction = $confirmed->transaction;

        $this->assertDatabaseCount('transactions', 1);
        $this->assertSame(TransactionType::Deposit, $transaction->type);
        $this->assertSame(TransactionStatus::Completed, $transaction->status);
        $this->assertSame($user->id, $transaction->user_id);
        $this->assertSame($account->id, $transaction->account_id);
        $this->assertSame($deposit->asset_id, $transaction->asset_id);
        $this->assertSame($deposit->id, $transaction->deposit_id);
        $this->assertNull($transaction->withdrawal_id);
        $this->assertSame('0.500000000000000000', $transaction->crypto_amount);
        $this->assertSame('25000.00', $transaction->usd_amount);
        $this->assertSame('50000.00000000', $transaction->price_snapshot);
        $this->assertSame($deposit->transaction_reference, $transaction->reference);
        $this->assertTrue($transaction->occurred_at->equalTo($confirmed->reviewed_at));
    }

    public function test_approved_withdrawal_creates_one_historical_transaction(): void
    {
        [$user, $account] = $this->createUserWithBalance('10000');
        $withdrawal = $this->submitWithdrawal($user, '250.00');
        $reviewer = User::factory()->create(['role' => 'admin']);

        $approved = app(WithdrawalReviewService::class)->approve($withdrawal, $reviewer);
        $transaction = $approved->transaction;

        $this->assertDatabaseCount('transactions', 1);
        $this->assertSame(TransactionType::Withdrawal, $transaction->type);
        $this->assertSame(TransactionStatus::Completed, $transaction->status);
        $this->assertSame($user->id, $transaction->user_id);
        $this->assertSame($account->id, $transaction->account_id);
        $this->assertSame($withdrawal->asset_id, $transaction->asset_id);
        $this->assertSame($withdrawal->id, $transaction->withdrawal_id);
        $this->assertNull($transaction->deposit_id);
        $this->assertSame('250.00', $transaction->usd_amount);
        $this->assertSame($withdrawal->crypto_amount, $transaction->crypto_amount);
        $this->assertSame($withdrawal->price_snapshot, $transaction->price_snapshot);
        $this->assertSame('WD-'.$withdrawal->id, $transaction->reference);
        $this->assertTrue($transaction->occurred_at->equalTo($approved->reviewed_at));
    }

    public function test_pending_and_rejected_source_records_do_not_create_transactions(): void
    {
        [$user, $account] = $this->createUserWithBalance('1000');
        $pendingDeposit = $this->submitDeposit($user, '0.01');
        $pendingWithdrawal = $this->submitWithdrawal($user, '10.00');
        $this->assertDatabaseCount('transactions', 0);

        $reviewer = User::factory()->create(['role' => 'admin']);
        app(DepositReviewService::class)->reject($pendingDeposit, $reviewer, 'Rejected for test.');
        app(WithdrawalReviewService::class)->reject($pendingWithdrawal, $reviewer, 'Rejected for test.');

        $this->assertDatabaseCount('transactions', 0);
    }

    public function test_duplicate_reviews_cannot_duplicate_source_transactions(): void
    {
        [$user] = $this->createUserWithBalance('1000');
        $deposit = $this->submitDeposit($user, '0.01');
        $withdrawal = $this->submitWithdrawal($user, '10.00');
        $reviewer = User::factory()->create(['role' => 'admin']);
        $depositReview = app(DepositReviewService::class);
        $withdrawalReview = app(WithdrawalReviewService::class);

        $depositReview->confirm($deposit, $reviewer);
        $withdrawalReview->approve($withdrawal, $reviewer);

        foreach ([
            fn () => $depositReview->confirm($deposit, $reviewer),
            fn () => $withdrawalReview->approve($withdrawal, $reviewer),
        ] as $duplicateReview) {
            try {
                $duplicateReview();
                $this->fail('A completed source record must not be reviewed twice.');
            } catch (ValidationException) {
            }
        }

        $this->assertDatabaseCount('transactions', 2);
    }

    public function test_failed_deposit_confirmation_rolls_back_transaction_and_balance_changes(): void
    {
        [$user, $account] = $this->createUserWithBalance('0');
        $deposit = $this->submitDeposit($user, '0.01');
        $account->pending_balance = '0.00';
        $account->save();
        $reviewer = User::factory()->create(['role' => 'admin']);

        try {
            app(DepositReviewService::class)->confirm($deposit, $reviewer);
            $this->fail('Inconsistent pending balance must abort confirmation.');
        } catch (\LogicException) {
        }

        $this->assertDatabaseCount('transactions', 0);
        $this->assertSame('0.00', $account->fresh()->managed_balance);
        $this->assertSame('0.00', $account->fresh()->pending_balance);
        $this->assertSame(DepositStatus::Pending, $deposit->fresh()->status);
    }

    public function test_failed_withdrawal_approval_does_not_create_transaction(): void
    {
        [$user, $account] = $this->createUserWithBalance('1000');
        $withdrawal = $this->submitWithdrawal($user, '800.00');
        app(AccountBalanceService::class)->setManagedBalance($account, '500.00');
        $reviewer = User::factory()->create(['role' => 'admin']);

        try {
            app(WithdrawalReviewService::class)->approve($withdrawal, $reviewer);
            $this->fail('Insufficient managed balance must abort approval.');
        } catch (ValidationException) {
        }

        $this->assertDatabaseCount('transactions', 0);
        $this->assertSame('500.00', $account->fresh()->managed_balance);
    }

    public function test_recording_failure_rolls_back_deposit_confirmation_and_transaction(): void
    {
        [$user, $account] = $this->createUserWithBalance('0');
        $deposit = $this->submitDeposit($user, '0.01');
        $reviewer = User::factory()->create(['role' => 'admin']);
        $this->app->instance(TransactionRecordService::class, new class extends TransactionRecordService
        {
            public function recordConfirmedDeposit(Deposit $deposit): Transaction
            {
                parent::recordConfirmedDeposit($deposit);

                throw new RuntimeException('Force transaction rollback.');
            }
        });

        try {
            app(DepositReviewService::class)->confirm($deposit, $reviewer);
            $this->fail('Recording failure must roll back confirmation.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Force transaction rollback.', $exception->getMessage());
        }

        $this->assertDatabaseCount('transactions', 0);
        $this->assertSame('pending', $deposit->fresh()->status->value);
        $this->assertSame('500.00', $account->fresh()->pending_balance);
    }

    public function test_recording_failure_rolls_back_withdrawal_approval_and_transaction(): void
    {
        [$user, $account] = $this->createUserWithBalance('1000');
        $withdrawal = $this->submitWithdrawal($user, '100.00');
        $reviewer = User::factory()->create(['role' => 'admin']);
        $this->app->instance(TransactionRecordService::class, new class extends TransactionRecordService
        {
            public function recordApprovedWithdrawal(Withdrawal $withdrawal): Transaction
            {
                parent::recordApprovedWithdrawal($withdrawal);

                throw new RuntimeException('Force transaction rollback.');
            }
        });

        try {
            app(WithdrawalReviewService::class)->approve($withdrawal, $reviewer);
            $this->fail('Recording failure must roll back approval.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Force transaction rollback.', $exception->getMessage());
        }

        $this->assertDatabaseCount('transactions', 0);
        $this->assertSame('pending', $withdrawal->fresh()->status->value);
        $this->assertSame('1000.00', $account->fresh()->managed_balance);
    }

    public function test_history_filters_by_type_and_account_and_orders_by_occurrence(): void
    {
        [$user, $account] = $this->createUserWithBalance('10000');
        $olderDeposit = $this->submitDeposit($user, '0.01');
        app(DepositReviewService::class)->confirm($olderDeposit, User::factory()->create(['role' => 'admin']));
        $withdrawal = $this->submitWithdrawal($user, '10.00');
        app(WithdrawalReviewService::class)->approve($withdrawal, User::factory()->create(['role' => 'admin']));
        $this->createAdjustment($user, $account, '20.00', now()->addSecond());

        $history = app(TransactionHistoryService::class);
        $all = $history->forUser($user)->get();

        $this->assertCount(3, $all);
        $this->assertSame(TransactionType::Adjustment, $all->first()->type);
        $this->assertCount(1, $history->forUser($user, TransactionType::Deposit)->get());
        $this->assertCount(1, $history->forUser($user, TransactionType::Withdrawal)->get());
        $this->assertCount(1, $history->forUser($user, TransactionType::Adjustment)->get());
        $this->assertCount(3, $history->forUser($user, accountId: $account->id)->get());
        $this->assertCount(3, $history->forAccount($account)->get());
    }

    public function test_source_transaction_uniqueness_is_enforced_by_the_database(): void
    {
        [$user] = $this->createUserWithBalance('1000');
        $deposit = $this->submitDeposit($user, '0.01');
        app(DepositReviewService::class)->confirm($deposit, User::factory()->create(['role' => 'admin']));

        $this->expectException(QueryException::class);

        $transaction = new Transaction;
        $transaction->user_id = $deposit->user_id;
        $transaction->account_id = $deposit->account_id;
        $transaction->asset_id = $deposit->asset_id;
        $transaction->deposit_id = $deposit->id;
        $transaction->type = TransactionType::Deposit;
        $transaction->status = TransactionStatus::Completed;
        $transaction->usd_amount = $deposit->usd_value;
        $transaction->reference = $deposit->transaction_reference;
        $transaction->occurred_at = now();
        $transaction->save();
    }

    private function createUserWithBalance(string $balance): array
    {
        $user = User::factory()->create();
        $account = $user->account()->create([]);

        return [$user, app(AccountBalanceService::class)->setManagedBalance($account, $balance)];
    }

    private function submitDeposit(User $user, string $amount): Deposit
    {
        $asset = Asset::where('symbol', 'BTC')->firstOrFail();

        return app(DepositSubmissionService::class)->submit($user, $asset->id, $amount, 'test-tx-'.$user->id.'-'.$amount);
    }

    private function submitWithdrawal(User $user, string $amount): Withdrawal
    {
        $asset = Asset::where('symbol', 'BTC')->firstOrFail();

        return app(WithdrawalSubmissionService::class)->submit($user, $asset->id, $amount, 'destination-wallet');
    }

    private function createAdjustment(User $user, Account $account, string $amount, Carbon $occurredAt): Transaction
    {
        $transaction = new Transaction;
        $transaction->user_id = $user->id;
        $transaction->account_id = $account->id;
        $transaction->type = TransactionType::Adjustment;
        $transaction->status = TransactionStatus::Completed;
        $transaction->usd_amount = $amount;
        $transaction->reference = 'ADJ-'.$user->id;
        $transaction->description = 'Test adjustment';
        $transaction->occurred_at = $occurredAt;
        $transaction->save();

        return $transaction;
    }
}

final class FakeTransactionPriceProvider implements AssetPriceProvider
{
    public function currentUsdPriceFor(Asset $asset): string
    {
        return '50000';
    }
}
