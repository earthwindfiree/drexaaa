<?php

namespace Tests\Feature;

use App\Contracts\AssetPriceProvider;
use App\Models\Asset;
use App\Models\AuditLog;
use App\Models\User;
use App\Models\Withdrawal;
use App\Services\AccountBalanceService;
use App\Services\WithdrawalSubmissionService;
use Database\Seeders\AssetSeeder;
use Database\Seeders\StrategyTierSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminWithdrawalsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(StrategyTierSeeder::class);
        $this->seed(AssetSeeder::class);
        $this->app->instance(AssetPriceProvider::class, new FakeAdminWithdrawalPriceProvider);
    }

    public function test_guests_normal_users_and_suspended_admins_are_rejected(): void
    {
        $withdrawal = $this->submitWithdrawal(User::factory()->create(), '100.00', 'auth-destination');
        $this->getJson('/api/admin/withdrawals')->assertUnauthorized();
        $this->getJson('/api/admin/withdrawals/'.$withdrawal->id)->assertUnauthorized();

        foreach ([
            User::factory()->create(),
            User::factory()->create(['role' => 'admin', 'status' => 'suspended']),
        ] as $user) {
            $this->actingAs($user)->getJson('/api/admin/withdrawals')->assertForbidden();
            $this->actingAs($user)->postJson('/api/admin/withdrawals/'.$withdrawal->id.'/approve')->assertForbidden();
        }
    }

    public function test_active_admin_and_super_admin_can_list_withdrawals_with_persisted_values(): void
    {
        $withdrawal = $this->submitWithdrawal(User::factory()->create(['name' => 'Withdrawal Owner', 'email' => 'withdrawal-owner@example.com']), '100.00', 'history-withdrawal');

        foreach (['admin', 'super_admin'] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]))
                ->getJson('/api/admin/withdrawals')
                ->assertOk()
                ->assertJsonPath('data.0.id', $withdrawal->id)
                ->assertJsonPath('data.0.user.name', 'Withdrawal Owner')
                ->assertJsonPath('data.0.asset.symbol', 'BTC')
                ->assertJsonPath('data.0.amount', '100.00')
                ->assertJsonPath('data.0.crypto_amount', '0.002000000000000000')
                ->assertJsonPath('data.0.price_snapshot', '50000.00000000')
                ->assertJsonPath('data.0.destination_wallet', 'history-withdrawal')
                ->assertJsonPath('data.0.status', 'pending')
                ->assertJsonMissingPath('data.0.user.password')
                ->assertJsonMissingPath('data.0.user.remember_token');
        }
    }

    public function test_filters_and_pagination_are_server_side(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $matching = $this->submitWithdrawal(User::factory()->create(['name' => 'Matching Withdrawer']), '100.00', 'matching-destination');
        $other = $this->submitWithdrawal(User::factory()->create(['name' => 'Other Withdrawer']), '100.00', 'other-destination');
        $other->status = 'rejected';
        $other->save();

        $this->actingAs($admin)
            ->getJson('/api/admin/withdrawals?status=pending&asset_id='.$matching->asset_id.'&search=Matching')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $matching->id)
            ->assertJsonPath('meta.total', 1);

        for ($index = 0; $index < 15; $index++) {
            $this->submitWithdrawal(User::factory()->create(), '100.00', 'pagination-'.$index);
        }

        $this->actingAs($admin)
            ->getJson('/api/admin/withdrawals?per_page=5&page=2')
            ->assertOk()
            ->assertJsonPath('meta.per_page', 5)
            ->assertJsonPath('meta.current_page', 2)
            ->assertJsonCount(5, 'data');
    }

    public function test_detail_includes_account_withdrawable_amount_and_review_information(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $owner = User::factory()->create(['name' => 'Detail Withdrawer']);
        $withdrawal = $this->submitWithdrawal($owner, '100.00', 'detail-destination');

        $this->actingAs($admin)
            ->getJson('/api/admin/withdrawals/'.$withdrawal->id)
            ->assertOk()
            ->assertJsonPath('data.id', $withdrawal->id)
            ->assertJsonPath('data.user.name', 'Detail Withdrawer')
            ->assertJsonPath('data.account.managed_balance', '1000.00')
            ->assertJsonPath('data.account.withdrawable_amount', '900.00')
            ->assertJsonPath('data.destination_wallet', 'detail-destination')
            ->assertJsonPath('data.transaction', null)
            ->assertJsonPath('data.rejection_reason', null);
    }

    public function test_pending_withdrawal_can_be_approved_once_with_balance_reservation_tier_transaction_and_audit_updates(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $owner = User::factory()->create();
        $account = $owner->account()->create([]);
        app(AccountBalanceService::class)->setManagedBalance($account, '1000.00');
        $withdrawal = $this->submitWithdrawal($owner, '100.00', 'approve-destination');

        $this->actingAs($admin)
            ->postJson('/api/admin/withdrawals/'.$withdrawal->id.'/approve')
            ->assertOk()
            ->assertJsonPath('data.status', 'approved')
            ->assertJsonPath('data.transaction.status', 'completed')
            ->assertJsonPath('data.transaction.crypto_amount', '0.002000000000000000')
            ->assertJsonPath('data.transaction.price_snapshot', '50000.00000000')
            ->assertJsonPath('data.account.managed_balance', '900.00')
            ->assertJsonPath('data.account.withdrawable_amount', '900.00');

        $this->assertSame('900.00', $account->fresh()->managed_balance);
        $this->assertSame('Foundation', $account->fresh()->tier->name);
        $this->assertDatabaseCount('transactions', 1);
        $audit = AuditLog::where('action', 'withdrawal.approved')->firstOrFail();
        $this->assertSame($admin->id, $audit->actor_id);
        $this->assertSame($owner->id, $audit->user_id);
        $this->assertSame('pending', $audit->old_values['status']);
        $this->assertSame('approved', $audit->new_values['status']);

        $this->actingAs($admin)
            ->postJson('/api/admin/withdrawals/'.$withdrawal->id.'/approve')
            ->assertUnprocessable();
        $this->actingAs($admin)
            ->postJson('/api/admin/withdrawals/'.$withdrawal->id.'/reject', ['rejection_reason' => 'Too late'])
            ->assertUnprocessable();
        $this->assertDatabaseCount('transactions', 1);
    }

    public function test_pending_withdrawal_can_be_rejected_without_changing_balance_or_tier(): void
    {
        $admin = User::factory()->create(['role' => 'super_admin']);
        $owner = User::factory()->create();
        $account = $owner->account()->create([]);
        app(AccountBalanceService::class)->setManagedBalance($account, '1000.00');
        $tierId = $account->fresh()->tier_id;
        $withdrawal = $this->submitWithdrawal($owner, '100.00', 'reject-destination');

        $this->actingAs($admin)
            ->postJson('/api/admin/withdrawals/'.$withdrawal->id.'/reject', ['rejection_reason' => 'Destination is not approved.'])
            ->assertOk()
            ->assertJsonPath('data.status', 'rejected')
            ->assertJsonPath('data.rejection_reason', 'Destination is not approved.')
            ->assertJsonPath('data.account.managed_balance', '1000.00')
            ->assertJsonPath('data.account.withdrawable_amount', '1000.00');

        $account = $account->fresh();
        $this->assertSame('1000.00', $account->managed_balance);
        $this->assertSame($tierId, $account->tier_id);
        $this->assertDatabaseCount('transactions', 0);
        $this->assertDatabaseHas('audit_logs', ['action' => 'withdrawal.rejected', 'actor_id' => $admin->id]);

        $this->actingAs($admin)
            ->postJson('/api/admin/withdrawals/'.$withdrawal->id.'/reject', ['rejection_reason' => 'Again'])
            ->assertUnprocessable();
        $this->actingAs($admin)
            ->postJson('/api/admin/withdrawals/'.$withdrawal->id.'/approve')
            ->assertUnprocessable();
    }

    public function test_rejection_reason_is_required_and_approval_fails_when_balance_is_no_longer_sufficient(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $owner = User::factory()->create();
        $account = $owner->account()->create([]);
        app(AccountBalanceService::class)->setManagedBalance($account, '1000.00');
        $withdrawal = $this->submitWithdrawal($owner, '800.00', 'validation-destination');

        $this->actingAs($admin)
            ->postJson('/api/admin/withdrawals/'.$withdrawal->id.'/reject')
            ->assertUnprocessable();

        app(AccountBalanceService::class)->setManagedBalance($account, '500.00');
        $this->actingAs($admin)
            ->postJson('/api/admin/withdrawals/'.$withdrawal->id.'/approve')
            ->assertUnprocessable();

        $this->assertSame('pending', $withdrawal->fresh()->status->value);
        $this->assertSame('500.00', $account->fresh()->managed_balance);
        $this->assertDatabaseCount('transactions', 0);
    }

    private function submitWithdrawal(User $user, string $amount, string $destination): Withdrawal
    {
        $account = $user->account()->firstOrCreate([]);
        app(AccountBalanceService::class)->setManagedBalance($account, '1000.00');

        return app(WithdrawalSubmissionService::class)->submit(
            $user,
            Asset::where('symbol', 'BTC')->firstOrFail()->id,
            $amount,
            $destination,
        );
    }
}

final class FakeAdminWithdrawalPriceProvider implements AssetPriceProvider
{
    public function currentUsdPriceFor(Asset $asset): string
    {
        return '50000';
    }
}
