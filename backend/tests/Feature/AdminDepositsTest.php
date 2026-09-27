<?php

namespace Tests\Feature;

use App\Enums\DepositStatus;
use App\Models\Asset;
use App\Models\AuditLog;
use App\Models\Deposit;
use App\Models\User;
use App\Services\AccountBalanceService;
use App\Services\DepositSubmissionService;
use Database\Seeders\AssetSeeder;
use Database\Seeders\MarketPriceSeeder;
use Database\Seeders\StrategyTierSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminDepositsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(StrategyTierSeeder::class);
        $this->seed(AssetSeeder::class);
        $this->seed(MarketPriceSeeder::class);
    }

    public function test_guests_normal_users_and_suspended_admins_are_rejected(): void
    {
        $deposit = $this->submitDeposit(User::factory()->create(), '0.01', 'auth-deposit');
        $this->getJson('/api/admin/deposits')->assertUnauthorized();
        $this->getJson('/api/admin/deposits/'.$deposit->id)->assertUnauthorized();

        foreach ([
            User::factory()->create(),
            User::factory()->create(['role' => 'admin', 'status' => 'suspended']),
        ] as $user) {
            $this->actingAs($user)->getJson('/api/admin/deposits')->assertForbidden();
            $this->actingAs($user)->postJson('/api/admin/deposits/'.$deposit->id.'/confirm')->assertForbidden();
        }
    }

    public function test_active_admin_and_super_admin_can_list_deposits_with_safe_historical_fields(): void
    {
        $deposit = $this->submitDeposit(User::factory()->create(['name' => 'Deposit Owner', 'email' => 'deposit-owner@example.com']), '0.01', 'history-reference');

        foreach (['admin', 'super_admin'] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]))
                ->getJson('/api/admin/deposits')
                ->assertOk()
                ->assertJsonPath('data.0.id', $deposit->id)
                ->assertJsonPath('data.0.user.name', 'Deposit Owner')
                ->assertJsonPath('data.0.asset.symbol', 'BTC')
                ->assertJsonPath('data.0.wallet.network', null)
                ->assertJsonPath('data.0.crypto_amount', '0.010000000000000000')
                ->assertJsonPath('data.0.price_snapshot', '67540.00000000')
                ->assertJsonPath('data.0.usd_value', '675.40')
                ->assertJsonPath('data.0.status', 'pending')
                ->assertJsonMissingPath('data.0.user.password')
                ->assertJsonMissingPath('data.0.user.remember_token');
        }
    }

    public function test_deposit_filters_and_pagination_are_server_side(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $matching = $this->submitDeposit(User::factory()->create(['name' => 'Matching Depositor']), '0.01', 'matching-reference');
        $otherUser = User::factory()->create(['name' => 'Other Depositor']);
        $other = $this->submitDeposit($otherUser, '0.01', 'other-reference');
        $other->status = DepositStatus::Rejected;
        $other->save();

        $this->actingAs($admin)
            ->getJson('/api/admin/deposits?status=pending&asset_id='.$matching->asset_id.'&search=Matching')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $matching->id)
            ->assertJsonPath('meta.total', 1);

        for ($index = 0; $index < 15; $index++) {
            $this->submitDeposit(User::factory()->create(), '0.01', 'pagination-'.$index);
        }

        $this->actingAs($admin)
            ->getJson('/api/admin/deposits?per_page=5&page=2')
            ->assertOk()
            ->assertJsonPath('meta.per_page', 5)
            ->assertJsonPath('meta.current_page', 2)
            ->assertJsonCount(5, 'data');
    }

    public function test_detail_includes_account_review_and_related_transaction_data(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $owner = User::factory()->create(['name' => 'Detail Depositor']);
        $deposit = $this->submitDeposit($owner, '0.01', 'detail-reference');

        $this->actingAs($admin)
            ->getJson('/api/admin/deposits/'.$deposit->id)
            ->assertOk()
            ->assertJsonPath('data.id', $deposit->id)
            ->assertJsonPath('data.user.name', 'Detail Depositor')
            ->assertJsonPath('data.account.pending_balance', '675.40')
            ->assertJsonPath('data.wallet.network', null)
            ->assertJsonPath('data.wallet.wallet_address', 'DEMO-ONLY-NOT-A-VALID-BTC-WALLET')
            ->assertJsonPath('data.transaction', null)
            ->assertJsonPath('data.rejection_reason', null)
            ->assertJsonMissingPath('data.user.password')
            ->assertJsonMissingPath('data.user.remember_token');
    }

    public function test_pending_deposit_can_be_confirmed_once_with_balance_tier_transaction_and_audit_updates(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $owner = User::factory()->create();
        $account = $owner->account()->create([]);
        $deposit = $this->submitDeposit($owner, '0.01', 'confirm-reference');

        $this->actingAs($admin)
            ->postJson('/api/admin/deposits/'.$deposit->id.'/confirm')
            ->assertOk()
            ->assertJsonPath('data.status', 'confirmed')
            ->assertJsonPath('data.transaction.status', 'completed')
            ->assertJsonPath('data.transaction.price_snapshot', '67540.00000000')
            ->assertJsonPath('data.transaction.usd_amount', '675.40');

        $account = $account->fresh();
        $this->assertSame('675.40', $account->managed_balance);
        $this->assertSame('0.00', $account->pending_balance);
        $this->assertSame('Foundation', $account->tier->name);
        $this->assertDatabaseCount('transactions', 1);
        $audit = AuditLog::where('action', 'deposit.confirmed')->firstOrFail();
        $this->assertSame($admin->id, $audit->actor_id);
        $this->assertSame($owner->id, $audit->user_id);
        $this->assertSame('pending', $audit->old_values['status']);
        $this->assertSame('confirmed', $audit->new_values['status']);

        $this->actingAs($admin)
            ->postJson('/api/admin/deposits/'.$deposit->id.'/confirm')
            ->assertUnprocessable();
        $this->actingAs($admin)
            ->postJson('/api/admin/deposits/'.$deposit->id.'/reject', ['rejection_reason' => 'Too late'])
            ->assertUnprocessable();
        $this->assertDatabaseCount('transactions', 1);
    }

    public function test_pending_deposit_can_be_rejected_and_cannot_later_be_confirmed(): void
    {
        $admin = User::factory()->create(['role' => 'super_admin']);
        $owner = User::factory()->create();
        $account = $owner->account()->create([]);
        app(AccountBalanceService::class)->setManagedBalance($account, '100.00');
        $tierId = $account->fresh()->tier_id;
        $deposit = $this->submitDeposit($owner, '0.01', 'reject-reference');

        $this->actingAs($admin)
            ->postJson('/api/admin/deposits/'.$deposit->id.'/reject', ['rejection_reason' => 'Insufficient evidence'])
            ->assertOk()
            ->assertJsonPath('data.status', 'rejected')
            ->assertJsonPath('data.rejection_reason', 'Insufficient evidence');

        $account = $account->fresh();
        $this->assertSame('100.00', $account->managed_balance);
        $this->assertSame('0.00', $account->pending_balance);
        $this->assertSame($tierId, $account->tier_id);
        $this->assertDatabaseCount('transactions', 0);
        $this->assertDatabaseHas('audit_logs', ['action' => 'deposit.rejected', 'actor_id' => $admin->id]);

        $this->actingAs($admin)
            ->postJson('/api/admin/deposits/'.$deposit->id.'/reject', ['rejection_reason' => 'Again'])
            ->assertUnprocessable();
        $this->actingAs($admin)
            ->postJson('/api/admin/deposits/'.$deposit->id.'/confirm')
            ->assertUnprocessable();
    }

    public function test_rejection_reason_is_required_and_unknown_review_fields_are_not_accepted(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $deposit = $this->submitDeposit(User::factory()->create(), '0.01', 'validation-reference');
        $endpoint = '/api/admin/deposits/'.$deposit->id.'/reject';

        $this->actingAs($admin)->postJson($endpoint)->assertUnprocessable();
        $this->actingAs($admin)->postJson($endpoint, ['rejection_reason' => '', 'status' => 'confirmed'])->assertUnprocessable();
        $this->assertSame('pending', $deposit->fresh()->status->value);
        $this->assertDatabaseCount('transactions', 0);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    private function submitDeposit(User $user, string $cryptoAmount, string $reference): Deposit
    {
        $user->account()->firstOrCreate([]);

        return app(DepositSubmissionService::class)->submit(
            $user,
            Asset::where('symbol', 'BTC')->firstOrFail()->id,
            $cryptoAmount,
            $reference,
        );
    }
}
