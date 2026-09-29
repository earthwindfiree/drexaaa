<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\URL;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class AuthApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withHeader('Origin', 'http://localhost:8000');
    }

    public function test_user_can_register_and_is_authenticated_without_a_personal_access_token(): void
    {
        $response = $this->withoutHeader('Origin')->postJson('/api/auth/register', [
            'name' => 'Demo User',
            'email' => 'demo@example.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'country' => 'US',
            'phone' => '+1-555-0100',
            'managed_balance' => '50000.00',
            'pending_balance' => '900.00',
            'total_profit_loss' => '10000.00',
            'performance_percentage' => '25.0000',
            'trading_status' => 'active',
            'tier_id' => 1,
            'asset_id' => 1,
            'network' => 'Ethereum',
            'wallet_address' => 'USER-SUPPLIED-WALLET',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('user.email', 'demo@example.com')
            ->assertJsonMissingPath('token');

        $this->assertNotNull($response->getCookie(config('session.cookie')));
        $this->assertDatabaseHas('users', [
            'email' => 'demo@example.com',
            'country' => 'US',
        ]);
        $this->assertDatabaseCount('accounts', 1);
        $account = User::where('email', 'demo@example.com')->firstOrFail()->account;
        $this->assertSame('0.00', $account->managed_balance);
        $this->assertSame('0.00', $account->pending_balance);
        $this->assertSame('0.00', $account->total_profit_loss);
        $this->assertSame('0.0000', $account->performance_percentage);
        $this->assertSame('inactive', $account->trading_status);
        $this->assertNull($account->tier_id);
        $this->assertDatabaseCount('asset_wallets', 0);
        $this->assertDatabaseCount('personal_access_tokens', 0);
        $this->assertAuthenticatedAs(User::where('email', 'demo@example.com')->first(), 'web');
    }

    public function test_registration_does_not_allow_role_or_status_assignment(): void
    {
        $this->postJson('/api/auth/register', [
            'name' => 'Demo User',
            'email' => 'demo@example.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'country' => 'US',
            'role' => 'admin',
            'status' => 'suspended',
        ])->assertCreated();

        $this->assertDatabaseHas('users', [
            'email' => 'demo@example.com',
            'role' => 'user',
            'status' => 'active',
        ]);
    }

    public function test_user_can_log_in_and_is_authenticated_without_a_personal_access_token(): void
    {
        $user = User::factory()->create([
            'email' => 'user@example.com',
            'password' => 'Password123!',
            'country' => 'GB',
            'email_verified_at' => null,
        ]);

        $response = $this->postJson('/api/auth/login', [
            'email' => 'user@example.com',
            'password' => 'Password123!',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('user.email', 'user@example.com')
            ->assertJsonPath('user.email_verified_at', null)
            ->assertJsonMissingPath('token');

        $this->assertNotNull($response->getCookie(config('session.cookie')));
        $this->assertAuthenticatedAs($user, 'web');
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_login_creates_a_session_when_request_has_no_frontend_origin_header(): void
    {
        $user = User::factory()->create(['password' => 'Password123!']);

        $response = $this->withoutHeader('Origin')->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => 'Password123!',
        ]);

        $response->assertOk()->assertJsonPath('user.email', $user->email);
        $this->assertNotNull($response->getCookie(config('session.cookie')));
        $this->assertAuthenticatedAs($user, 'web');
    }

    public function test_role_and_status_are_not_mass_assignable(): void
    {
        User::create([
            'name' => 'Demo User',
            'email' => 'mass-assignment@example.com',
            'password' => 'Password123!',
            'country' => 'US',
            'role' => 'admin',
            'status' => 'suspended',
        ]);

        $this->assertDatabaseHas('users', [
            'email' => 'mass-assignment@example.com',
            'role' => 'user',
            'status' => 'active',
        ]);
    }

    public function test_suspended_user_cannot_log_in(): void
    {
        $user = User::factory()->create([
            'email' => 'suspended@example.com',
            'password' => 'Password123!',
            'status' => 'suspended',
        ]);

        $this->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => 'Password123!',
        ])->assertUnprocessable();

        $this->assertGuest('web');
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_authenticated_session_can_read_the_current_user(): void
    {
        $user = User::factory()->create();

        $loginResponse = $this->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $this->assertNotNull($this->sessionCookieValue($loginResponse));
        Auth::forgetGuards();

        $this->withCredentials()
            ->withCookie(config('session.cookie'), $this->sessionCookieValue($loginResponse))
            ->getJson('/api/auth/me')
            ->assertOk()
            ->assertJsonPath('user.id', $user->id)
            ->assertJsonPath('user.email', $user->email)
            ->assertJsonMissingPath('token');
    }

    public function test_logout_invalidates_the_authenticated_session(): void
    {
        $user = User::factory()->create(['password' => 'Password123!']);
        $loginResponse = $this->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => 'Password123!',
        ]);

        $sessionCookie = $this->sessionCookieValue($loginResponse);
        Auth::forgetGuards();

        $logoutResponse = $this->withCredentials()
            ->withoutHeader('Origin')
            ->withCookie(config('session.cookie'), $sessionCookie)
            ->postJson('/api/auth/logout');
        $logoutResponse->assertOk();

        $this->assertGuest('web');
        Auth::forgetGuards();
        $unauthenticatedResponse = $this->withoutHeader('Origin')
            ->withCookie(config('session.cookie'), $this->sessionCookieValue($logoutResponse))
            ->get('/api/auth/me');
        $unauthenticatedResponse->assertUnauthorized();
        $this->assertStringContainsString('application/json', $unauthenticatedResponse->headers->get('content-type'));
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_profile_updates_reset_email_verification_and_audit_admin_changes(): void
    {
        Notification::fake();
        $admin = User::factory()->create([
            'role' => 'admin',
            'email_verified_at' => now(),
        ]);

        $this->actingAs($admin)->patchJson('/api/profile', [
            'name' => 'Updated Admin',
            'email' => 'updated-admin@example.com',
            'country' => 'CA',
            'phone' => '+1-555-0199',
        ])
            ->assertOk()
            ->assertJsonPath('user.name', 'Updated Admin')
            ->assertJsonPath('user.email', 'updated-admin@example.com')
            ->assertJsonPath('user.email_verified_at', null);

        $admin->refresh();
        $this->assertNull($admin->email_verified_at);
        Notification::assertSentTo($admin, VerifyEmail::class);
        $this->assertDatabaseHas('audit_logs', [
            'actor_id' => $admin->id,
            'action' => 'admin.profile.updated',
            'user_id' => $admin->id,
        ]);
        $this->assertDatabaseHas('user_notifications', [
            'user_id' => $admin->id,
            'category' => 'security',
            'title' => 'Email address changed',
        ]);
    }

    public function test_password_change_requires_current_password_and_audits_without_storing_secrets(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'password' => 'Password123!']);

        $this->actingAs($admin)->patchJson('/api/profile/password', [
            'current_password' => 'incorrect',
            'password' => 'NewPassword123!',
            'password_confirmation' => 'NewPassword123!',
        ])->assertUnprocessable();

        $this->actingAs($admin)->patchJson('/api/profile/password', [
            'current_password' => 'Password123!',
            'password' => 'NewPassword123!',
            'password_confirmation' => 'NewPassword123!',
        ])->assertOk();

        $this->assertTrue(Hash::check('NewPassword123!', $admin->fresh()->password));
        $audit = AuditLog::where('action', 'admin.security.password.updated')->firstOrFail();
        $this->assertSame(['password'], $audit->metadata['changed_fields']);
        $this->assertNull($audit->old_values);
        $this->assertNull($audit->new_values);
        $this->assertDatabaseHas('user_notifications', [
            'user_id' => $admin->id,
            'category' => 'security',
            'title' => 'Password changed',
        ]);
    }

    public function test_password_reset_request_is_generic_and_reset_token_updates_password(): void
    {
        Notification::fake();
        $user = User::factory()->create(['email' => 'reset@example.com']);

        $response = $this->postJson('/api/auth/forgot-password', ['email' => $user->email])
            ->assertOk()
            ->assertJsonPath('message', 'If an account exists for that email, a password reset link has been sent.');
        Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $notification) use ($user): bool {
            return str_contains($notification->toMail($user)->actionUrl, '/reset-password?token=');
        });

        $token = Password::createToken($user);
        $this->postJson('/api/auth/reset-password', [
            'email' => $user->email,
            'token' => $token,
            'password' => 'ResetPassword123!',
            'password_confirmation' => 'ResetPassword123!',
        ])->assertOk();

        $this->assertTrue(Hash::check('ResetPassword123!', $user->fresh()->password));
        $this->assertDatabaseMissing('password_reset_tokens', ['email' => $user->email]);
        $this->assertDatabaseHas('user_notifications', [
            'user_id' => $user->id,
            'title' => 'Password changed',
        ]);
    }

    public function test_signed_email_verification_and_resend_are_supported(): void
    {
        Notification::fake();
        $user = User::factory()->create(['email_verified_at' => null]);

        $this->actingAs($user)
            ->postJson('/api/auth/verification-notification')
            ->assertAccepted();
        Notification::assertSentTo($user, VerifyEmail::class);

        $url = URL::temporarySignedRoute('verification.verify', now()->addMinutes(30), [
            'id' => $user->id,
            'hash' => sha1($user->getEmailForVerification()),
        ]);

        $this->get($url)->assertRedirect(config('app.frontend_url').'/login?verified=1');
        $this->assertNotNull($user->fresh()->email_verified_at);
        $this->assertDatabaseHas('user_notifications', [
            'user_id' => $user->id,
            'title' => 'Email verified',
        ]);
        $this->get($url)->assertRedirect(config('app.frontend_url').'/login?verified=1');
    }

    public function test_unverified_users_are_denied_profile_access_but_admin_profiles_remain_available(): void
    {
        $unverifiedUser = User::factory()->unverified()->create();

        $this->actingAs($unverifiedUser)
            ->getJson('/api/auth/me')
            ->assertOk()
            ->assertJsonPath('user.email_verified_at', null);

        $this->actingAs($unverifiedUser)
            ->patchJson('/api/profile', [
                'name' => $unverifiedUser->name,
                'email' => $unverifiedUser->email,
                'country' => $unverifiedUser->country,
                'phone' => $unverifiedUser->phone,
            ])
            ->assertForbidden()
            ->assertJsonPath('code', 'email_verification_required');

        $verifiedUser = User::factory()->create();
        $this->actingAs($verifiedUser)
            ->patchJson('/api/profile', [
                'name' => $verifiedUser->name,
                'email' => $verifiedUser->email,
                'country' => $verifiedUser->country,
                'phone' => $verifiedUser->phone,
            ])
            ->assertOk();

        $unverifiedAdmin = User::factory()->unverified()->create(['role' => 'admin']);
        $this->actingAs($unverifiedAdmin)
            ->patchJson('/api/profile', [
                'name' => $unverifiedAdmin->name,
                'email' => $unverifiedAdmin->email,
                'country' => $unverifiedAdmin->country,
                'phone' => $unverifiedAdmin->phone,
            ])
            ->assertOk();

        $this->actingAs($unverifiedAdmin)
            ->getJson('/api/admin/dashboard/summary')
            ->assertOk();
    }

    public function test_registration_country_must_be_from_the_configured_country_list(): void
    {
        $this->getJson('/api/auth/countries')
            ->assertOk()
            ->assertJsonFragment(['code' => 'US', 'name' => 'United States']);

        $this->postJson('/api/auth/register', [
            'name' => 'Unknown Country',
            'email' => 'unknown-country@example.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'country' => 'ZZ',
        ])->assertUnprocessable()->assertJsonValidationErrors('country');
    }

    public function test_login_is_rate_limited(): void
    {
        for ($attempt = 0; $attempt < 6; $attempt++) {
            $this->postJson('/api/auth/login', [
                'email' => 'missing@example.com',
                'password' => 'Password123!',
            ])->assertUnprocessable();
        }

        $this->postJson('/api/auth/login', [
            'email' => 'missing@example.com',
            'password' => 'Password123!',
        ])->assertTooManyRequests();
    }

    public function test_registration_is_rate_limited(): void
    {
        for ($attempt = 0; $attempt < 6; $attempt++) {
            $this->postJson('/api/auth/register', [])->assertUnprocessable();
        }

        $this->postJson('/api/auth/register', [])->assertTooManyRequests();
    }

    private function sessionCookieValue(TestResponse $response): string
    {
        $cookie = $response->getCookie(config('session.cookie'));
        $this->assertNotNull($cookie);

        return $cookie->getValue();
    }
}
