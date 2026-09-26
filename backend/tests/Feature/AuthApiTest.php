<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
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
        $response = $this->postJson('/api/auth/register', [
            'name' => 'Demo User',
            'email' => 'demo@example.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'country' => 'US',
            'phone' => '+1-555-0100',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('user.email', 'demo@example.com')
            ->assertJsonMissingPath('token');

        $this->assertNotNull($response->getCookie(config('session.cookie')));
        $this->assertDatabaseHas('users', [
            'email' => 'demo@example.com',
            'country' => 'US',
        ]);
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
        ]);

        $response = $this->postJson('/api/auth/login', [
            'email' => 'user@example.com',
            'password' => 'Password123!',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('user.email', 'user@example.com')
            ->assertJsonMissingPath('token');

        $this->assertNotNull($response->getCookie(config('session.cookie')));
        $this->assertAuthenticatedAs($user, 'web');
        $this->assertDatabaseCount('personal_access_tokens', 0);
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
            ->withCookie(config('session.cookie'), $sessionCookie)
            ->postJson('/api/auth/logout');
        $logoutResponse->assertOk();

        $this->assertGuest('web');
        Auth::forgetGuards();
        $this->withCookie(config('session.cookie'), $this->sessionCookieValue($logoutResponse))
            ->getJson('/api/auth/me')
            ->assertUnauthorized();
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    private function sessionCookieValue(TestResponse $response): string
    {
        $cookie = $response->getCookie(config('session.cookie'));
        $this->assertNotNull($cookie);

        return $cookie->getValue();
    }
}
