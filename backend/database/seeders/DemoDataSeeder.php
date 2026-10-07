<?php

namespace Database\Seeders;

use App\Enums\DepositStatus;
use App\Enums\TransactionStatus;
use App\Enums\TransactionType;
use App\Enums\WithdrawalStatus;
use App\Models\Account;
use App\Models\Asset;
use App\Models\AssetWallet;
use App\Models\Deposit;
use App\Models\PerformanceSnapshot;
use App\Models\Transaction;
use App\Models\User;
use App\Models\UserNotification;
use App\Models\Withdrawal;
use App\Services\TierCalculator;
use Illuminate\Database\Seeder;

class DemoDataSeeder extends Seeder
{
    public function run(): void
    {
        $admin = $this->user('admin@example.test', 'Demo Administrator', 'admin');
        $this->user('superadmin@example.test', 'Demo Super Admin', 'super_admin');

        $belowTier = $this->demoUser('test@example.com', 'Taylor Demo', '50.00', '0.00', '0.00', '0.0000');
        $active = $this->demoUser('alex.demo@example.test', 'Alex Demo', '8500.00', '500.00', '425.00', '5.2600');
        $highTier = $this->demoUser('jordan.demo@example.test', 'Jordan Demo', '30000.00', '0.00', '2000.00', '7.1400');

        $this->seedFinancialActivity($admin, $belowTier, $active, $highTier);
        $this->seedPerformanceHistory($active, [
            ['8100.00', '25.00', '0.3100'],
            ['8200.00', '125.00', '1.5500'],
            ['8350.00', '275.00', '3.4100'],
            ['8500.00', '425.00', '5.2600'],
        ]);
        $this->seedPerformanceHistory($highTier, [
            ['28000.00', '0.00', '0.0000'],
            ['28500.00', '500.00', '1.7900'],
            ['29200.00', '1200.00', '4.2900'],
            ['30000.00', '2000.00', '7.1400'],
        ]);
        $this->seedNotifications($belowTier, $active, $highTier);
    }

    private function user(string $email, string $name, string $role): User
    {
        $user = User::query()->firstOrNew(['email' => $email]);
        $user->forceFill([
            'name' => $name,
            'country' => 'US',
            'phone' => null,
            'email_verified_at' => now()->subMonths(2),
            'password' => 'DemoPass123!',
            'role' => $role,
            'status' => 'active',
        ])->save();

        return $user;
    }

    private function demoUser(
        string $email,
        string $name,
        string $managedBalance,
        string $pendingBalance,
        string $profitLoss,
        string $performancePercentage,
    ): Account {
        $user = $this->user($email, $name, 'user');
        $account = $user->account()->firstOrNew();
        $tier = app(TierCalculator::class)->qualifyingTier($managedBalance);
        $account->forceFill([
            'managed_balance' => $managedBalance,
            'pending_balance' => $pendingBalance,
            'total_profit_loss' => $profitLoss,
            'performance_percentage' => $performancePercentage,
            'trading_status' => 'active',
            'tier_id' => $tier?->id,
        ])->save();

        return $account;
    }

    private function seedFinancialActivity(User $admin, Account $belowTier, Account $active, Account $highTier): void
    {
        $usdt = Asset::query()->where('symbol', 'USDT')->firstOrFail();
        $btc = Asset::query()->where('symbol', 'BTC')->firstOrFail();
        $usdtWallet = $this->activeWallet($usdt);
        $btcWallet = $this->activeWallet($btc);

        $this->deposit($admin, $belowTier, $usdt, $usdtWallet, '50.000000000000000000', '1.00000000', '50.00', 'DEMO-DEP-TAYLOR-001', DepositStatus::Confirmed, 18);
        $this->deposit($admin, $active, $btc, $btcWallet, '0.100000000000000000', '67540.00000000', '6754.00', 'DEMO-DEP-ALEX-001', DepositStatus::Confirmed, 45);
        $this->deposit($admin, $active, $usdt, $usdtWallet, '2246.000000000000000000', '1.00000000', '2246.00', 'DEMO-DEP-ALEX-002', DepositStatus::Confirmed, 42);
        $this->deposit($admin, $active, $usdt, $usdtWallet, '500.000000000000000000', '1.00000000', '500.00', 'DEMO-DEP-ALEX-003', DepositStatus::Pending, 2);
        $this->deposit($admin, $highTier, $usdt, $usdtWallet, '31145.740000000000000000', '1.00000000', '31145.74', 'DEMO-DEP-JORDAN-001', DepositStatus::Confirmed, 55);
        $this->deposit($admin, $highTier, $btc, $btcWallet, '0.012648208469055375', '67540.00000000', '854.26', 'DEMO-DEP-JORDAN-002', DepositStatus::Confirmed, 50);

        $this->withdrawal($admin, $active, $usdt, '500.00', WithdrawalStatus::Approved, 'DEMO-DEST-ALEX-APPROVED', 38);
        $this->withdrawal($admin, $active, $usdt, '1000.00', WithdrawalStatus::Pending, 'DEMO-DEST-ALEX-PENDING', 1);
        $this->withdrawal($admin, $highTier, $usdt, '2000.00', WithdrawalStatus::Approved, 'DEMO-DEST-JORDAN-APPROVED', 45);
    }

    private function activeWallet(Asset $asset): AssetWallet
    {
        return $asset->wallets()->where('active', true)->firstOrFail();
    }

    private function deposit(
        User $admin,
        Account $account,
        Asset $asset,
        AssetWallet $wallet,
        string $cryptoAmount,
        string $price,
        string $usdValue,
        string $reference,
        DepositStatus $status,
        int $daysAgo,
    ): void {
        $createdAt = now()->subDays($daysAgo);
        $reviewedAt = $status === DepositStatus::Pending ? null : $createdAt->copy()->addHours(2);
        $deposit = Deposit::query()->firstOrNew(['transaction_reference' => $reference]);
        $deposit->forceFill([
            'user_id' => $account->user_id,
            'account_id' => $account->id,
            'asset_id' => $asset->id,
            'asset_wallet_id' => $wallet->id,
            'crypto_amount' => $cryptoAmount,
            'price_snapshot' => $price,
            'usd_value' => $usdValue,
            'status' => $status,
            'rejection_reason' => null,
            'reviewed_by' => $reviewedAt ? $admin->id : null,
            'reviewed_at' => $reviewedAt,
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ])->save();

        $transaction = Transaction::query()->firstOrNew(['deposit_id' => $deposit->id]);
        $transaction->forceFill([
            'user_id' => $account->user_id,
            'account_id' => $account->id,
            'type' => TransactionType::Deposit,
            'status' => $status === DepositStatus::Confirmed ? TransactionStatus::Completed : TransactionStatus::Pending,
            'asset_id' => $asset->id,
            'deposit_id' => $deposit->id,
            'withdrawal_id' => null,
            'crypto_amount' => $cryptoAmount,
            'usd_amount' => $usdValue,
            'price_snapshot' => $price,
            'reference' => $reference,
            'description' => 'Simulated demo deposit',
            'occurred_at' => $reviewedAt ?? $createdAt,
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ])->save();
    }

    private function withdrawal(
        User $admin,
        Account $account,
        Asset $asset,
        string $amount,
        WithdrawalStatus $status,
        string $destination,
        int $daysAgo,
    ): void {
        $createdAt = now()->subDays($daysAgo);
        $reviewedAt = $status === WithdrawalStatus::Approved ? $createdAt->copy()->addHours(3) : null;
        $withdrawal = Withdrawal::query()
            ->where('user_id', $account->user_id)
            ->where('destination_wallet', $destination)
            ->firstOrNew();
        $withdrawal->forceFill([
            'user_id' => $account->user_id,
            'account_id' => $account->id,
            'asset_id' => $asset->id,
            'amount' => $amount,
            'destination_wallet' => $destination,
            'crypto_amount' => $amount,
            'price_snapshot' => '1.00000000',
            'status' => $status,
            'rejection_reason' => null,
            'reviewed_by' => $reviewedAt ? $admin->id : null,
            'reviewed_at' => $reviewedAt,
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ])->save();

        $transaction = Transaction::query()->firstOrNew(['withdrawal_id' => $withdrawal->id]);
        $transaction->forceFill([
            'user_id' => $account->user_id,
            'account_id' => $account->id,
            'type' => TransactionType::Withdrawal,
            'status' => $status === WithdrawalStatus::Approved ? TransactionStatus::Completed : TransactionStatus::Pending,
            'asset_id' => $asset->id,
            'deposit_id' => null,
            'withdrawal_id' => $withdrawal->id,
            'crypto_amount' => $amount,
            'usd_amount' => $amount,
            'price_snapshot' => '1.00000000',
            'reference' => 'WD-'.$withdrawal->id,
            'description' => 'Simulated demo withdrawal',
            'occurred_at' => $reviewedAt ?? $createdAt,
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ])->save();
    }

    private function seedPerformanceHistory(Account $account, array $snapshots): void
    {
        foreach ($snapshots as $index => [$managedBalance, $profitLoss, $percentage]) {
            $snapshotAt = now()->subDays((count($snapshots) - 1 - $index) * 10)->startOfDay();
            $snapshot = PerformanceSnapshot::query()->firstOrNew([
                'account_id' => $account->id,
                'snapshot_at' => $snapshotAt,
            ]);
            $snapshot->forceFill([
                'managed_balance' => $managedBalance,
                'total_profit_loss' => $profitLoss,
                'performance_percentage' => $percentage,
                'created_at' => $snapshotAt,
                'updated_at' => $snapshotAt,
            ])->save();
        }
    }

    private function seedNotifications(Account $belowTier, Account $active, Account $highTier): void
    {
        $notifications = [
            [$belowTier, 'account', 'Welcome to the demo', 'Your simulated account is ready to explore.', 12, true],
            [$active, 'financial', 'Deposit approved', 'Your demo deposit was reviewed and added to managed funds.', 9, true],
            [$active, 'financial', 'Withdrawal submitted', 'Your simulated withdrawal is pending administrator review.', 1, false],
            [$highTier, 'account', 'Tier status', 'Your current simulated balance qualifies for the Apex tier.', 5, false],
        ];

        foreach ($notifications as [$account, $category, $title, $message, $daysAgo, $read]) {
            $createdAt = now()->subDays($daysAgo);
            $notification = UserNotification::query()->firstOrNew([
                'user_id' => $account->user_id,
                'category' => $category,
                'title' => $title,
            ]);
            $notification->forceFill([
                'message' => $message,
                'read_at' => $read ? $createdAt->copy()->addHours(1) : null,
                'created_at' => $createdAt,
                'updated_at' => $createdAt,
            ])->save();
        }
    }
}
