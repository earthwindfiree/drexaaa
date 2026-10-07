<?php

namespace Tests\Feature;

use App\Enums\DepositStatus;
use App\Enums\TransactionStatus;
use App\Enums\WithdrawalStatus;
use App\Models\Account;
use App\Models\Asset;
use App\Models\AssetWallet;
use App\Models\Deposit;
use App\Models\MarketHistoryPoint;
use App\Models\MarketPrice;
use App\Models\PerformanceSnapshot;
use App\Models\Strategy;
use App\Models\Tier;
use App\Models\Transaction;
use App\Models\User;
use App\Models\UserNotification;
use App\Models\Withdrawal;
use App\Services\WithdrawalAvailabilityService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class DemoDataSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_database_seeder_creates_consistent_demo_data_and_is_safe_to_rerun(): void
    {
        $this->seed(DatabaseSeeder::class);
        $initialCounts = $this->demoRecordCounts();

        $this->seed(DatabaseSeeder::class);

        $this->assertSame($initialCounts, $this->demoRecordCounts());
        $this->assertSame(5, User::query()->count());
        $this->assertSame(3, Account::query()->count());
        $this->assertSame(4, Strategy::query()->count());
        $this->assertSame(4, Tier::query()->count());
        $this->assertSame(4, Asset::query()->count());
        $this->assertSame(4, AssetWallet::query()->count());
        $this->assertSame(6, Deposit::query()->count());
        $this->assertSame(3, Withdrawal::query()->count());
        $this->assertSame(9, Transaction::query()->count());
        $this->assertSame(8, PerformanceSnapshot::query()->count());
        $this->assertSame(4, UserNotification::query()->count());
        $this->assertSame(4, MarketPrice::query()->count());
        $this->assertSame(724, MarketHistoryPoint::query()->count());

        $admin = User::query()->where('email', 'admin@example.test')->firstOrFail();
        $superAdmin = User::query()->where('email', 'superadmin@example.test')->firstOrFail();
        $this->assertSame('admin', $admin->role);
        $this->assertSame('super_admin', $superAdmin->role);
        $this->assertTrue(Gate::forUser($admin)->allows('admin'));
        $this->assertTrue(Gate::forUser($superAdmin)->allows('super-admin'));
        $this->assertTrue(Hash::check('DemoPass123!', $admin->password));
        $this->postJson('/api/auth/login', [
            'email' => 'test@example.com',
            'password' => 'DemoPass123!',
        ])->assertOk()->assertJsonPath('user.email', 'test@example.com');

        $belowTier = User::query()->where('email', 'test@example.com')->firstOrFail()->account;
        $active = User::query()->where('email', 'alex.demo@example.test')->firstOrFail()->account;
        $highTier = User::query()->where('email', 'jordan.demo@example.test')->firstOrFail()->account;

        $this->assertNull($belowTier->tier_id);
        $this->assertSame('50.00', $belowTier->managed_balance);
        $this->assertSame('Elevation', $active->tier->name);
        $this->assertSame('8500.00', $active->managed_balance);
        $this->assertSame('500.00', $active->pending_balance);
        $this->assertSame('7500.00', app(WithdrawalAvailabilityService::class)->withdrawableAmount($active));
        $this->assertSame('Apex', $highTier->tier->name);
        $this->assertSame('30000.00', $highTier->managed_balance);

        $this->assertSame(1, $active->deposits()->where('status', DepositStatus::Pending)->count());
        $this->assertSame(1, $active->withdrawals()->where('status', WithdrawalStatus::Approved)->count());
        $this->assertSame(1, $active->withdrawals()->where('status', WithdrawalStatus::Pending)->count());
        $this->assertSame(2, $active->transactions()->where('status', TransactionStatus::Pending)->count());
        $this->assertGreaterThan(0, $active->user->notifications()->whereNull('read_at')->count());

        foreach ([$active, $highTier] as $account) {
            $snapshots = $account->performanceSnapshots()->orderBy('snapshot_at')->get();
            $this->assertCount(4, $snapshots);
            $this->assertTrue($snapshots->last()->snapshot_at->isToday());
            $this->assertSame($account->managed_balance, $snapshots->last()->managed_balance);
        }
    }

    private function demoRecordCounts(): array
    {
        return [
            User::query()->count(),
            Account::query()->count(),
            Strategy::query()->count(),
            Tier::query()->count(),
            Asset::query()->count(),
            AssetWallet::query()->count(),
            Deposit::query()->count(),
            Withdrawal::query()->count(),
            Transaction::query()->count(),
            PerformanceSnapshot::query()->count(),
            UserNotification::query()->count(),
            MarketPrice::query()->count(),
            MarketHistoryPoint::query()->count(),
        ];
    }
}
