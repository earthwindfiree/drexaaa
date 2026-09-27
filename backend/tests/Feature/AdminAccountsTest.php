<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Asset;
use App\Models\AssetWallet;
use App\Models\AuditLog;
use App\Models\Deposit;
use App\Models\PerformanceSnapshot;
use App\Models\Strategy;
use App\Models\Tier;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Withdrawal;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminAccountsTest extends TestCase
{
    use RefreshDatabase;

    public function test_account_endpoints_reject_guests_normal_users_and_suspended_admins(): void
    {
        $account = $this->createAccount(User::factory()->create());

        $this->getJson('/api/admin/accounts')->assertUnauthorized();
        $this->getJson('/api/admin/accounts/'.$account->id)->assertUnauthorized();

        foreach ([
            User::factory()->create(),
            User::factory()->create(['role' => 'admin', 'status' => 'suspended']),
        ] as $user) {
            $this->actingAs($user)->getJson('/api/admin/accounts')->assertForbidden();
            $this->actingAs($user)->getJson('/api/admin/accounts/'.$account->id)->assertForbidden();
        }
    }

    public function test_active_admin_and_super_admin_can_list_accounts(): void
    {
        $account = $this->createAccount(User::factory()->create(['name' => 'Account Owner', 'email' => 'owner@example.com']), [
            'managed_balance' => '2500.00',
            'pending_balance' => '125.00',
            'total_profit_loss' => '250.00',
            'performance_percentage' => '11.2500',
            'trading_status' => 'active',
        ]);

        foreach (['admin', 'super_admin'] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]))
                ->getJson('/api/admin/accounts')
                ->assertOk()
                ->assertJsonPath('data.0.id', $account->id)
                ->assertJsonPath('data.0.user.name', 'Account Owner')
                ->assertJsonPath('data.0.managed_balance', '2500.00')
                ->assertJsonPath('data.0.pending_balance', '125.00')
                ->assertJsonPath('data.0.total_profit_loss', '250.00')
                ->assertJsonPath('data.0.performance_percentage', '11.2500')
                ->assertJsonPath('data.0.trading_status', 'active')
                ->assertJsonMissingPath('data.0.user.password')
                ->assertJsonMissingPath('data.0.user.remember_token');
        }
    }

    public function test_account_list_filters_by_user_search_tier_and_trading_status(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $strategy = Strategy::create([
            'name' => 'Accounts Strategy',
            'description' => 'Accounts test strategy.',
            'risk_profile' => 'moderate',
        ]);
        $tier = Tier::create([
            'name' => 'Accounts Tier',
            'minimum_balance' => '100.00',
            'strategy_id' => $strategy->id,
            'description' => 'Accounts test tier.',
        ]);
        $matching = $this->createAccount(User::factory()->create(['name' => 'Matching Owner', 'email' => 'matching@example.com']), [
            'tier_id' => $tier->id,
            'trading_status' => 'active',
        ]);
        $this->createAccount(User::factory()->create(['name' => 'Other Owner', 'email' => 'other@example.com']), [
            'trading_status' => 'inactive',
        ]);

        $this->actingAs($admin)
            ->getJson('/api/admin/accounts?search=Matching&tier=Accounts&trading_status=active')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $matching->id)
            ->assertJsonPath('meta.total', 1)
            ->assertJsonMissingPath('data.1');
    }

    public function test_account_list_is_paginated(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        User::factory()->count(16)->create()->each(fn (User $user) => $this->createAccount($user));

        $this->actingAs($admin)
            ->getJson('/api/admin/accounts')
            ->assertOk()
            ->assertJsonPath('meta.per_page', 15)
            ->assertJsonPath('meta.total', 16)
            ->assertJsonCount(15, 'data');

        $this->actingAs($admin)
            ->getJson('/api/admin/accounts?page=2&per_page=5')
            ->assertOk()
            ->assertJsonPath('meta.current_page', 2)
            ->assertJsonPath('meta.last_page', 4)
            ->assertJsonCount(5, 'data');
    }

    public function test_account_detail_returns_read_only_account_and_history_data(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $owner = User::factory()->create(['name' => 'Detail Owner', 'email' => 'detail@example.com']);
        $strategy = Strategy::create([
            'name' => 'Detail Strategy',
            'description' => 'Detail test strategy.',
            'risk_profile' => 'balanced',
        ]);
        $tier = Tier::create([
            'name' => 'Detail Tier',
            'minimum_balance' => '1000.00',
            'strategy_id' => $strategy->id,
            'description' => 'Detail test tier.',
            'benefits' => ['monitoring'],
            'feature_access' => ['performance'],
        ]);
        $account = $this->createAccount($owner, [
            'managed_balance' => '5000.00',
            'pending_balance' => '200.00',
            'total_profit_loss' => '500.00',
            'performance_percentage' => '10.0000',
            'trading_status' => 'active',
            'tier_id' => $tier->id,
        ]);
        $asset = Asset::create(['symbol' => 'DET', 'name' => 'Detail Asset']);
        $wallet = AssetWallet::create(['asset_id' => $asset->id, 'network' => 'demo', 'wallet_address' => 'detail-wallet']);
        $this->createRecord(PerformanceSnapshot::class, [
            'account_id' => $account->id,
            'managed_balance' => '5000.00',
            'total_profit_loss' => '500.00',
            'performance_percentage' => '10.0000',
            'snapshot_at' => now()->subDay(),
        ]);
        $deposit = $this->createRecord(Deposit::class, [
            'user_id' => $owner->id,
            'account_id' => $account->id,
            'asset_id' => $asset->id,
            'asset_wallet_id' => $wallet->id,
            'crypto_amount' => '1.000000000000000000',
            'price_snapshot' => '5000.00000000',
            'usd_value' => '5000.00',
            'transaction_reference' => 'detail-deposit',
            'status' => 'confirmed',
        ]);
        $withdrawal = $this->createRecord(Withdrawal::class, [
            'user_id' => $owner->id,
            'account_id' => $account->id,
            'asset_id' => $asset->id,
            'amount' => '300.00',
            'destination_wallet' => 'detail-destination',
            'status' => 'pending',
        ]);
        $transaction = $this->createRecord(Transaction::class, [
            'user_id' => $owner->id,
            'account_id' => $account->id,
            'asset_id' => $asset->id,
            'deposit_id' => $deposit->id,
            'type' => 'deposit',
            'status' => 'completed',
            'crypto_amount' => '1.000000000000000000',
            'usd_amount' => '5000.00',
            'price_snapshot' => '5000.00000000',
            'reference' => 'detail-transaction',
            'description' => 'Detail transaction.',
            'occurred_at' => now()->subHour(),
        ]);
        $countsBefore = [
            'transactions' => Transaction::count(),
            'snapshots' => PerformanceSnapshot::count(),
            'audit_logs' => AuditLog::count(),
        ];

        $this->actingAs($admin)
            ->getJson('/api/admin/accounts/'.$account->id)
            ->assertOk()
            ->assertJsonPath('data.id', $account->id)
            ->assertJsonPath('data.user.name', 'Detail Owner')
            ->assertJsonPath('data.tier.name', 'Detail Tier')
            ->assertJsonPath('data.tier.strategy.name', 'Detail Strategy')
            ->assertJsonPath('data.performance_snapshots.0.managed_balance', '5000.00')
            ->assertJsonPath('data.transactions.0.id', $transaction->id)
            ->assertJsonPath('data.deposits.0.id', $deposit->id)
            ->assertJsonPath('data.withdrawals.0.id', $withdrawal->id)
            ->assertJsonMissingPath('data.user.password')
            ->assertJsonMissingPath('data.user.remember_token');

        $this->assertSame($countsBefore['transactions'], Transaction::count());
        $this->assertSame($countsBefore['snapshots'], PerformanceSnapshot::count());
        $this->assertSame($countsBefore['audit_logs'], AuditLog::count());
        $this->assertSame('5000.00', $account->fresh()->managed_balance);
    }

    private function createAccount(User $user, array $attributes = []): Account
    {
        $account = new Account;
        $account->forceFill(array_merge(['user_id' => $user->id], $attributes))->save();

        return $account;
    }

    private function createRecord(string $model, array $attributes): object
    {
        $record = new $model;
        $record->forceFill($attributes)->save();

        return $record;
    }
}
