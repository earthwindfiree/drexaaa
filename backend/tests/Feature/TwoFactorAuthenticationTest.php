<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\TwoFactorAuthenticationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

class TwoFactorAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withHeader('Origin', 'http://localhost:8000');
    }

    public function test_user_can_enroll_and_confirm_totp_with_recovery_codes(): void
    {
        $user = User::factory()->create(['password' => 'Password123!']);

        $setup = $this->actingAs($user)->postJson('/api/profile/two-factor/setup', [
            'current_password' => 'Password123!',
        ])->assertOk();

        $secret = $setup->json('secret');
        $this->assertIsString($secret);
        $this->assertNotSame($secret, $user->fresh()->getRawOriginal('two_factor_secret'));
        $this->assertFalse($user->fresh()->two_factor_confirmed_at !== null);

        $code = app(TwoFactorAuthenticationService::class)->codeForCounter($secret, intdiv(time(), 30));
        $confirmation = $this->postJson('/api/profile/two-factor/confirm', [
            'current_password' => 'Password123!',
            'code' => $code,
        ])->assertOk()
            ->assertJsonPath('enabled', true);

        $recoveryCodes = $confirmation->json('recovery_codes');
        $this->assertCount(10, $recoveryCodes);
        $enabledUser = $user->fresh();
        $this->assertNotNull($enabledUser->two_factor_confirmed_at);
        $this->assertCount(10, $enabledUser->two_factor_recovery_codes);
        $this->assertSame(10, count(array_filter(
            $enabledUser->two_factor_recovery_codes,
            static fn (string $hash): bool => password_get_info($hash)['algoName'] !== 'unknown',
        )));

        $this->actingAs($enabledUser)->getJson('/api/profile')
            ->assertOk()
            ->assertJsonPath('user.two_factor_enabled', true)
            ->assertJsonMissingPath('user.two_factor_secret')
            ->assertJsonMissingPath('user.two_factor_recovery_codes');
    }

    public function test_totp_is_required_for_login_and_a_code_cannot_be_replayed(): void
    {
        $secret = app(TwoFactorAuthenticationService::class)->generateSecret();
        $user = User::factory()->create([
            'email' => 'two-factor@example.com',
            'password' => 'Password123!',
            'two_factor_secret' => $secret,
            'two_factor_confirmed_at' => now(),
            'two_factor_recovery_codes' => [],
            'two_factor_last_used_counter' => intdiv(time(), 30) - 1,
        ]);

        $this->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => 'Password123!',
        ])->assertAccepted()
            ->assertJsonPath('two_factor_required', true);
        $this->assertGuest('web');

        $this->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => 'Password123!',
            'two_factor_code' => 'invalid',
        ])->assertUnprocessable()->assertJsonValidationErrors('two_factor_code');
        $this->assertGuest('web');

        $code = app(TwoFactorAuthenticationService::class)->codeForCounter($secret, intdiv(time(), 30));
        $this->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => 'Password123!',
            'two_factor_code' => $code,
        ])->assertOk()->assertJsonPath('user.two_factor_enabled', true);
        $this->assertAuthenticatedAs($user, 'web');

        $this->postJson('/api/auth/logout')->assertOk();
        Auth::forgetGuards();
        $this->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => 'Password123!',
            'two_factor_code' => $code,
        ])->assertUnprocessable()->assertJsonValidationErrors('two_factor_code');
        $this->assertGuest('web');
    }

    public function test_recovery_code_can_authenticate_once_and_regeneration_replaces_codes(): void
    {
        $service = app(TwoFactorAuthenticationService::class);
        $secret = $service->generateSecret();
        $recoveryCode = 'A1B2-C3D4';
        $user = User::factory()->create([
            'email' => 'recovery@example.com',
            'password' => 'Password123!',
            'two_factor_secret' => $secret,
            'two_factor_confirmed_at' => now(),
            'two_factor_recovery_codes' => $service->hashRecoveryCodes([$recoveryCode]),
            'two_factor_last_used_counter' => null,
        ]);

        $credentials = [
            'email' => $user->email,
            'password' => 'Password123!',
            'two_factor_code' => $recoveryCode,
        ];
        $this->postJson('/api/auth/login', $credentials)->assertOk();
        $this->assertCount(0, $user->fresh()->two_factor_recovery_codes);
        Auth::forgetGuards();
        $this->postJson('/api/auth/login', $credentials)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('two_factor_code');
    }

    public function test_user_can_disable_two_factor_with_password_and_valid_code(): void
    {
        $secret = app(TwoFactorAuthenticationService::class)->generateSecret();
        $user = User::factory()->create([
            'password' => 'Password123!',
            'two_factor_secret' => $secret,
            'two_factor_confirmed_at' => now(),
            'two_factor_recovery_codes' => [],
            'two_factor_last_used_counter' => intdiv(time(), 30) - 1,
        ]);
        $code = app(TwoFactorAuthenticationService::class)->codeForCounter($secret, intdiv(time(), 30));

        $this->actingAs($user)->deleteJson('/api/profile/two-factor', [
            'current_password' => 'wrong-password',
            'code' => $code,
        ])->assertUnprocessable();
        $this->assertNotNull($user->fresh()->two_factor_confirmed_at);

        $this->deleteJson('/api/profile/two-factor', [
            'current_password' => 'Password123!',
            'code' => $code,
        ])->assertOk()->assertJsonPath('enabled', false);

        $disabledUser = $user->fresh();
        $this->assertNull($disabledUser->two_factor_secret);
        $this->assertNull($disabledUser->two_factor_confirmed_at);
        $this->assertNull($disabledUser->two_factor_recovery_codes);
    }

    public function test_user_can_regenerate_recovery_codes_with_a_fresh_authenticator_code(): void
    {
        $twoFactor = app(TwoFactorAuthenticationService::class);
        $secret = $twoFactor->generateSecret();
        $oldCodes = $twoFactor->generateRecoveryCodes();
        $user = User::factory()->create([
            'password' => 'Password123!',
            'two_factor_secret' => $secret,
            'two_factor_confirmed_at' => now(),
            'two_factor_recovery_codes' => $twoFactor->hashRecoveryCodes($oldCodes),
            'two_factor_last_used_counter' => intdiv(time(), 30) - 1,
        ]);
        $code = $twoFactor->codeForCounter($secret, intdiv(time(), 30));

        $response = $this->actingAs($user)->postJson('/api/profile/two-factor/recovery-codes', [
            'current_password' => 'Password123!',
            'code' => $code,
        ])->assertOk();

        $newCodes = $response->json('recovery_codes');
        $this->assertCount(10, $newCodes);
        $this->assertFalse($twoFactor->consumeRecoveryCode($user->fresh(), $oldCodes[0]));
        $this->assertDatabaseHas('user_notifications', [
            'user_id' => $user->id,
            'title' => 'Two-factor recovery codes regenerated',
        ]);
    }

    public function test_generated_recovery_codes_accept_dashed_and_lowercase_undashed_input_once(): void
    {
        $service = app(TwoFactorAuthenticationService::class);
        $codes = $service->generateRecoveryCodes();
        $user = User::factory()->create([
            'two_factor_recovery_codes' => $service->hashRecoveryCodes($codes),
        ]);

        $this->assertTrue($service->consumeRecoveryCode($user, $codes[0]));
        $this->assertFalse($service->consumeRecoveryCode($user->fresh(), strtolower(str_replace('-', '', $codes[0]))));

        $this->assertTrue($service->consumeRecoveryCode($user->fresh(), strtolower(str_replace('-', '', $codes[1]))));
        $this->assertFalse($service->consumeRecoveryCode($user->fresh(), $codes[1]));
    }
}
