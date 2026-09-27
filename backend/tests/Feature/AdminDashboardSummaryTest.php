<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Asset;
use App\Models\AssetWallet;
use App\Models\Deposit;
use App\Models\Strategy;
use App\Models\Tier;
use App\Models\User;
use App\Models\Withdrawal;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminDashboardSummaryTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_access_the_admin_summary(): void
    {
        $this->getJson('/api/admin/dashboard/summary')->assertUnauthorized();
    }

    public function test_normal_user_cannot_access_the_admin_summary(): void
    {
        $this->actingAs(User::factory()->create())
            ->getJson('/api/admin/dashboard/summary')
            ->assertForbidden();
    }

    public function test_active_admin_and_super_admin_can_access_the_admin_summary(): void
    {
        foreach (['admin', 'super_admin'] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]))
                ->getJson('/api/admin/dashboard/summary')
                ->assertOk()
                ->assertJsonStructure(['data' => ['total_users', 'total_managed_balance']]);
        }
    }

    public function test_suspended_admin_cannot_access_the_admin_summary(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin', 'status' => 'suspended']))
            ->getJson('/api/admin/dashboard/summary')
            ->assertForbidden();
    }

    public function test_summary_uses_persisted_counts_and_fixed_precision_balances(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $normalUser = User::factory()->create();
        $suspendedAdmin = User::factory()->create(['role' => 'admin', 'status' => 'suspended']);
        $adminAccount = $this->createAccount($admin, '1000.00', '100.00');
        $userAccount = $this->createAccount($normalUser, '750.00', '50.00');
        $suspendedAccount = $this->createAccount($suspendedAdmin, '250.00', '25.00');
        $strategy = Strategy::create([
            'name' => 'Summary Strategy',
            'description' => 'Summary test strategy.',
            'risk_profile' => 'moderate',
        ]);
        Tier::create([
            'name' => 'Summary Tier One',
            'minimum_balance' => '100.00',
            'strategy_id' => $strategy->id,
            'description' => 'Summary test tier.',
        ]);
        Tier::create([
            'name' => 'Summary Tier Two',
            'minimum_balance' => '1000.00',
            'strategy_id' => $strategy->id,
            'description' => 'Summary test tier.',
        ]);
        $activeAsset = Asset::create(['symbol' => 'SUM', 'name' => 'Summary Asset', 'active' => true]);
        $inactiveAsset = Asset::create(['symbol' => 'OLD', 'name' => 'Inactive Asset', 'active' => false]);
        $wallet = AssetWallet::create([
            'asset_id' => $activeAsset->id,
            'network' => 'demo',
            'wallet_address' => 'summary-wallet',
        ]);

        foreach ([
            ['account' => $adminAccount, 'status' => 'pending', 'reference' => 'pending-one'],
            ['account' => $userAccount, 'status' => 'pending', 'reference' => 'pending-two'],
            ['account' => $suspendedAccount, 'status' => 'confirmed', 'reference' => 'confirmed-one'],
        ] as $deposit) {
            $this->createDeposit([
                'user_id' => $deposit['account']->user_id,
                'account_id' => $deposit['account']->id,
                'asset_id' => $activeAsset->id,
                'asset_wallet_id' => $wallet->id,
                'crypto_amount' => '1.000000000000000000',
                'price_snapshot' => '100.00000000',
                'usd_value' => '100.00',
                'transaction_reference' => $deposit['reference'],
                'status' => $deposit['status'],
            ]);
        }

        foreach ([
            ['account' => $adminAccount, 'status' => 'pending'],
            ['account' => $userAccount, 'status' => 'pending'],
            ['account' => $suspendedAccount, 'status' => 'approved'],
        ] as $withdrawal) {
            $this->createWithdrawal([
                'user_id' => $withdrawal['account']->user_id,
                'account_id' => $withdrawal['account']->id,
                'asset_id' => $inactiveAsset->id,
                'amount' => '50.00',
                'destination_wallet' => 'destination-wallet',
                'status' => $withdrawal['status'],
            ]);
        }

        $response = $this->actingAs($admin)->getJson('/api/admin/dashboard/summary');

        $response->assertOk()->assertJsonPath('data.total_users', 3)
            ->assertJsonPath('data.active_users', 2)
            ->assertJsonPath('data.suspended_users', 1)
            ->assertJsonPath('data.total_managed_balance', '2000.00')
            ->assertJsonPath('data.total_pending_balance', '175.00')
            ->assertJsonPath('data.total_pending_deposits', 2)
            ->assertJsonPath('data.total_pending_withdrawals', 2)
            ->assertJsonPath('data.total_completed_deposits', 1)
            ->assertJsonPath('data.total_approved_withdrawals', 1)
            ->assertJsonPath('data.total_assets', 2)
            ->assertJsonPath('data.active_assets', 1)
            ->assertJsonPath('data.total_tiers', 2)
            ->assertJsonPath('data.total_accounts', 3);
    }

    private function createAccount(User $user, string $managedBalance, string $pendingBalance): Account
    {
        $account = new Account;
        $account->forceFill([
            'user_id' => $user->id,
            'managed_balance' => $managedBalance,
            'pending_balance' => $pendingBalance,
        ])->save();

        return $account;
    }

    private function createDeposit(array $attributes): Deposit
    {
        $deposit = new Deposit;
        $deposit->forceFill($attributes)->save();

        return $deposit;
    }

    private function createWithdrawal(array $attributes): Withdrawal
    {
        $withdrawal = new Withdrawal;
        $withdrawal->forceFill($attributes)->save();

        return $withdrawal;
    }
}
