<?php

namespace Tests\Feature;

use App\Models\Asset;
use App\Models\AssetWallet;
use App\Models\AuditLog;
use App\Models\Transaction;
use App\Models\User;
use App\Services\AdminMarketConfigurationService;
use App\Services\AuditLogService;
use App\Services\DepositSubmissionService;
use Database\Seeders\AssetSeeder;
use Database\Seeders\MarketPriceSeeder;
use Database\Seeders\StrategyTierSeeder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Tests\TestCase;

class AdminMarketsTest extends TestCase
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
        $btc = Asset::where('symbol', 'BTC')->firstOrFail();
        $this->getJson('/api/admin/markets')->assertUnauthorized();
        $this->patchJson('/api/admin/markets/'.$btc->id.'/price', [
            'current_price' => '70000',
            'change_24h_percentage' => '1.0000',
        ])->assertUnauthorized();

        foreach ([
            User::factory()->create(),
            User::factory()->create(['role' => 'admin', 'status' => 'suspended']),
        ] as $user) {
            $this->actingAs($user)->getJson('/api/admin/markets')->assertForbidden();
            $this->actingAs($user)->patchJson('/api/admin/markets/'.$btc->id.'/price', [
                'current_price' => '70000',
                'change_24h_percentage' => '1.0000',
            ])->assertForbidden();
        }
    }

    public function test_active_admin_and_super_admin_can_list_assets_prices_and_wallets(): void
    {
        foreach (['admin', 'super_admin'] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]))
                ->getJson('/api/admin/markets')
                ->assertOk()
                ->assertJsonCount(4, 'data')
                ->assertJsonPath('data.0.symbol', 'BTC')
                ->assertJsonPath('data.0.market_price.current_price', '67540.00000000')
                ->assertJsonPath('data.0.wallets.0.active', true)
                ->assertJsonPath('data.0.has_usable_wallet', true);
        }
    }

    public function test_market_and_wallet_configuration_mutations_reject_ordinary_admins(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $btc = Asset::where('symbol', 'BTC')->firstOrFail();
        $wallet = $btc->wallets()->firstOrFail();

        $this->actingAs($admin)->patchJson('/api/admin/markets/'.$btc->id.'/price', [
            'current_price' => '70000',
            'change_24h_percentage' => '1.0000',
        ])->assertForbidden();
        $this->actingAs($admin)->patchJson('/api/admin/markets/'.$btc->id.'/status', ['active' => false])->assertForbidden();
        $this->actingAs($admin)->postJson('/api/admin/markets/'.$btc->id.'/wallets', [
            'wallet_address' => 'forbidden-wallet',
            'active' => true,
        ])->assertForbidden();
        $this->actingAs($admin)->patchJson('/api/admin/wallets/'.$wallet->id.'/status', ['active' => false])->assertForbidden();

        $this->assertSame('67540.00000000', $btc->marketPrice->current_price);
        $this->assertTrue($btc->fresh()->active);
        $this->assertDatabaseMissing('asset_wallets', ['wallet_address' => 'forbidden-wallet']);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_asset_status_is_audited_and_inactive_assets_are_not_depositable(): void
    {
        $admin = User::factory()->create(['role' => 'super_admin']);
        $btc = Asset::where('symbol', 'BTC')->firstOrFail();

        $this->actingAs($admin)
            ->patchJson('/api/admin/markets/'.$btc->id.'/status', ['active' => false])
            ->assertOk()
            ->assertJsonPath('data.active', false);

        $audit = AuditLog::where('action', 'asset.status.updated')->firstOrFail();
        $this->assertSame($admin->id, $audit->actor_id);
        $this->assertSame(Asset::class, $audit->entity_type);
        $this->assertSame($btc->id, $audit->entity_id);
        $this->assertTrue($audit->old_values['active']);
        $this->assertFalse($audit->new_values['active']);
        $this->assertSame(0, Transaction::count());

        $user = User::factory()->create();
        $user->account()->create([]);

        $this->expectException(ValidationException::class);
        app(DepositSubmissionService::class)->submit($user, $btc->id, '0.01', 'inactive-asset-test');
    }

    public function test_market_price_update_uses_existing_service_and_does_not_change_financial_state(): void
    {
        $admin = User::factory()->create(['role' => 'super_admin']);
        $btc = Asset::where('symbol', 'BTC')->firstOrFail();
        $user = User::factory()->create();
        $account = $user->account()->create([]);
        $account->forceFill([
            'managed_balance' => '1000.00',
            'pending_balance' => '200.00',
            'total_profit_loss' => '50.00',
            'performance_percentage' => '5.0000',
        ])->save();
        $before = $account->fresh()->only(['managed_balance', 'pending_balance', 'total_profit_loss', 'performance_percentage', 'tier_id']);

        $this->actingAs($admin)
            ->patchJson('/api/admin/markets/'.$btc->id.'/price', [
                'current_price' => '70000',
                'change_24h_percentage' => '-3.1250',
            ])
            ->assertOk()
            ->assertJsonPath('data.current_price', '70000.00000000')
            ->assertJsonPath('data.change_24h_percentage', '-3.1250');

        $this->assertSame($before, $account->fresh()->only(array_keys($before)));
        $this->assertSame(0, Transaction::count());
        $this->assertDatabaseHas('audit_logs', ['action' => 'market_price.updated', 'actor_id' => $admin->id]);
    }

    public function test_invalid_price_precision_and_unknown_fields_are_rejected(): void
    {
        $admin = User::factory()->create(['role' => 'super_admin']);
        $btc = Asset::where('symbol', 'BTC')->firstOrFail();
        $endpoint = '/api/admin/markets/'.$btc->id.'/price';

        foreach ([
            ['current_price' => '-1', 'change_24h_percentage' => '1.0000'],
            ['current_price' => '70000.123456789', 'change_24h_percentage' => '1.0000'],
            ['current_price' => '70000', 'change_24h_percentage' => '1.12345'],
            ['current_price' => '70000', 'change_24h_percentage' => '1.0000', 'active' => false],
        ] as $payload) {
            $this->actingAs($admin)->patchJson($endpoint, $payload)->assertUnprocessable();
        }
    }

    public function test_wallets_can_be_added_and_switched_without_duplicate_active_wallets(): void
    {
        $admin = User::factory()->create(['role' => 'super_admin']);
        $btc = Asset::where('symbol', 'BTC')->firstOrFail();
        $oldWallet = $btc->wallets()->firstOrFail();

        $this->actingAs($admin)
            ->getJson('/api/admin/markets/'.$btc->id.'/wallets')
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $newWalletResponse = $this->actingAs($admin)
            ->postJson('/api/admin/markets/'.$btc->id.'/wallets', [
                'network' => 'demo-replacement',
                'wallet_address' => 'replacement-wallet',
                'active' => true,
            ])
            ->assertCreated()
            ->assertJsonPath('data.active', true);
        $newWalletId = $newWalletResponse->json('data.id');

        $this->assertFalse($oldWallet->fresh()->active);
        $this->assertTrue(AssetWallet::findOrFail($newWalletId)->active);
        $this->assertSame(1, $btc->wallets()->where('active', true)->count());

        $this->actingAs($admin)
            ->postJson('/api/admin/markets/'.$btc->id.'/wallets', [
                'network' => 'demo',
                'wallet_address' => 'replacement-wallet',
                'active' => false,
            ])
            ->assertUnprocessable();

        $this->actingAs($admin)
            ->patchJson('/api/admin/wallets/'.$newWalletId.'/status', ['active' => false])
            ->assertUnprocessable();

        $this->actingAs($admin)
            ->patchJson('/api/admin/wallets/'.$oldWallet->id.'/status', ['active' => true])
            ->assertOk()
            ->assertJsonPath('data.active', true);

        $this->assertSame(1, $btc->wallets()->where('active', true)->count());
        $this->assertGreaterThanOrEqual(2, AuditLog::where('action', 'asset_wallet.status.updated')->count());
        $this->assertSame(0, Transaction::count());
    }

    public function test_wallet_audit_failure_rolls_back_wallet_creation(): void
    {
        $admin = User::factory()->create(['role' => 'super_admin']);
        $btc = Asset::where('symbol', 'BTC')->firstOrFail();
        $this->app->instance(AuditLogService::class, new class extends AuditLogService
        {
            public function record(
                User $actor,
                string $action,
                ?User $user = null,
                ?Model $entity = null,
                ?array $oldValues = null,
                ?array $newValues = null,
                ?array $metadata = null,
            ): AuditLog {
                parent::record($actor, $action, $user, $entity, $oldValues, $newValues, $metadata);

                throw new RuntimeException('Forced audit failure.');
            }
        });

        try {
            app(AdminMarketConfigurationService::class)->createWallet($btc, $admin, 'rollback-wallet', 'demo', false);
            $this->fail('Wallet audit failure must roll back wallet creation.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Forced audit failure.', $exception->getMessage());
        }

        $this->assertDatabaseMissing('asset_wallets', ['wallet_address' => 'rollback-wallet']);
        $this->assertDatabaseCount('audit_logs', 0);
    }
}
