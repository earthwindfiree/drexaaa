<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AuditLogService;
use App\Services\TwoFactorAuthenticationService;
use App\Services\UserNotificationService;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function register(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique(User::class)],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'country' => ['required', 'string', 'size:2', Rule::in(array_keys(config('countries', [])))],
            'phone' => ['nullable', 'string', 'max:30'],
        ]);

        $user = DB::transaction(function () use ($validated): User {
            $user = User::create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'password' => $validated['password'],
                'country' => $validated['country'],
                'phone' => $validated['phone'] ?? null,
            ]);

            $user->account()->create([]);

            return $user;
        });

        event(new Registered($user));
        Auth::guard('web')->login($user);
        $request->session()->regenerate();

        return response()->json([
            'message' => 'User registered successfully.',
            'user' => $this->serializeUser($user),
        ], 201);
    }

    public function login(Request $request, TwoFactorAuthenticationService $twoFactor)
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
            'two_factor_code' => ['nullable', 'string', 'max:32'],
        ]);

        $user = User::where('email', $validated['email'])->first();

        if (! $user || ! Hash::check($validated['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        if ($user->status !== 'active') {
            throw ValidationException::withMessages([
                'email' => ['This account is currently suspended.'],
            ]);
        }

        if ($user->two_factor_confirmed_at !== null) {
            if (empty($validated['two_factor_code'])) {
                return response()->json([
                    'two_factor_required' => true,
                    'message' => 'Enter the code from your authenticator app or a recovery code.',
                ], 202);
            }

            $authenticated = DB::transaction(function () use ($user, $validated, $twoFactor): ?User {
                $user = User::query()->lockForUpdate()->findOrFail($user->id);

                if (! Hash::check($validated['password'], $user->password)) {
                    throw ValidationException::withMessages([
                        'email' => ['The provided credentials are incorrect.'],
                    ]);
                }

                if ($user->status !== 'active' || $user->two_factor_confirmed_at === null) {
                    return null;
                }

                $counter = $twoFactor->matchingCounter(
                    $user->two_factor_secret,
                    $validated['two_factor_code'],
                );

                if ($counter !== null && $counter > (int) ($user->two_factor_last_used_counter ?? -1)) {
                    $user->two_factor_last_used_counter = $counter;
                    $user->save();

                    return $user;
                }

                if ($twoFactor->consumeRecoveryCode($user, $validated['two_factor_code'])) {
                    return $user;
                }

                return null;
            }, 3);

            if (! $authenticated) {
                throw ValidationException::withMessages([
                    'two_factor_code' => ['The authentication code is invalid or has already been used.'],
                ]);
            }

            $user = $authenticated;
        }

        Auth::guard('web')->login($user);
        $request->session()->regenerate();

        return response()->json([
            'message' => 'Login successful.',
            'user' => $this->serializeUser($user),
        ]);
    }

    public function countries()
    {
        return response()->json([
            'data' => collect(config('countries', []))
                ->map(fn (string $name, string $code): array => ['code' => $code, 'name' => $name])
                ->values(),
        ]);
    }

    public function logout(Request $request)
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json(['message' => 'Logged out successfully.']);
    }

    public function me(Request $request)
    {
        return response()->json([
            'user' => $this->serializeUser($request->user()),
        ]);
    }

    public function forgotPassword(Request $request)
    {
        $request->validate(['email' => ['required', 'email']]);
        Password::sendResetLink($request->only('email'));

        return response()->json([
            'message' => 'If an account exists for that email, a password reset link has been sent.',
        ]);
    }

    public function resetPassword(Request $request)
    {
        $validated = $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email'],
            'password' => ['required', 'string', PasswordRule::min(8), 'confirmed'],
        ]);

        $status = Password::reset($validated, function (User $user, string $password): void {
            DB::transaction(function () use ($user, $password): void {
                $user->forceFill([
                    'password' => $password,
                    'remember_token' => Str::random(60),
                ])->save();

                event(new PasswordReset($user));
                app(UserNotificationService::class)->create(
                    $user,
                    'security',
                    'Password changed',
                    'Your account password was reset.',
                );
            });
        });

        if ($status !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages(['email' => [__($status)]]);
        }

        return response()->json(['message' => 'Password reset successfully.']);
    }

    public function verifyEmail(string $id, string $hash)
    {
        $user = User::query()->findOrFail($id);

        abort_unless(hash_equals(sha1($user->getEmailForVerification()), $hash), 403);

        if (! $user->hasVerifiedEmail() && $user->markEmailAsVerified()) {
            event(new Verified($user));
            app(UserNotificationService::class)->create(
                $user,
                'security',
                'Email verified',
                'Your email address has been verified.',
            );
        }

        return redirect()->away(rtrim((string) config('app.frontend_url'), '/').'/login?verified=1');
    }

    public function sendVerification(Request $request)
    {
        $user = $request->user();

        if ($user->hasVerifiedEmail()) {
            return response()->json(['message' => 'Email address is already verified.']);
        }

        $user->sendEmailVerificationNotification();

        return response()->json(['message' => 'Verification link sent.'], 202);
    }

    public function updateProfile(Request $request, AuditLogService $auditLogs)
    {
        $user = $request->user();
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique(User::class)->ignore($user->id)],
            'country' => ['required', 'string', 'size:2', Rule::in(array_keys(config('countries', [])))],
            'phone' => ['nullable', 'string', 'max:30'],
        ]);

        [$updatedUser, $emailChanged] = DB::transaction(function () use ($user, $validated, $auditLogs): array {
            $user = User::query()->lockForUpdate()->findOrFail($user->id);
            $oldValues = $user->only(['name', 'email', 'country', 'phone']);
            $emailChanged = $user->email !== $validated['email'];
            $user->fill($validated);

            if ($emailChanged) {
                $user->email_verified_at = null;
            }

            if ($user->isDirty()) {
                $user->save();

                if ($emailChanged) {
                    app(UserNotificationService::class)->create(
                        $user,
                        'security',
                        'Email address changed',
                        'Your account email address was changed. Verify the new address before accessing the user platform.',
                    );
                }

                if (Gate::forUser($user)->allows('admin')) {
                    $auditLogs->record(
                        $user,
                        'admin.profile.updated',
                        user: $user,
                        entity: $user,
                        oldValues: $oldValues,
                        newValues: $user->only(['name', 'email', 'country', 'phone']),
                        metadata: ['email_verification_reset' => $emailChanged],
                    );
                }
            }

            return [$user->fresh(), $emailChanged];
        }, 3);

        if ($emailChanged) {
            $updatedUser->sendEmailVerificationNotification();
        }

        return response()->json(['user' => $this->serializeUser($updatedUser)]);
    }

    public function updatePassword(Request $request, AuditLogService $auditLogs)
    {
        $validated = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'string', PasswordRule::min(8), 'confirmed'],
        ]);
        $user = $request->user();

        DB::transaction(function () use ($user, $validated, $auditLogs): void {
            $user = User::query()->lockForUpdate()->findOrFail($user->id);
            $user->password = $validated['password'];
            $user->save();
            app(UserNotificationService::class)->create(
                $user,
                'security',
                'Password changed',
                'Your account password was changed.',
            );

            if (Gate::forUser($user)->allows('admin')) {
                $auditLogs->record(
                    $user,
                    'admin.security.password.updated',
                    user: $user,
                    entity: $user,
                    metadata: ['changed_fields' => ['password']],
                );
            }
        }, 3);

        return response()->json(['message' => 'Password updated successfully.']);
    }

    public function setupTwoFactor(Request $request, TwoFactorAuthenticationService $twoFactor)
    {
        $request->validate([
            'current_password' => ['required', 'current_password'],
        ]);
        $user = $request->user();

        if ($user->two_factor_confirmed_at !== null) {
            throw ValidationException::withMessages([
                'two_factor' => ['Two-factor authentication is already enabled.'],
            ]);
        }

        $secret = $twoFactor->generateSecret();
        $user->forceFill([
            'two_factor_secret' => $secret,
            'two_factor_confirmed_at' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_last_used_counter' => null,
        ])->save();

        return response()->json([
            'secret' => $secret,
            'provisioning_uri' => $twoFactor->provisioningUri($user, $secret),
        ]);
    }

    public function confirmTwoFactor(Request $request, TwoFactorAuthenticationService $twoFactor, AuditLogService $auditLogs)
    {
        $validated = $request->validate([
            'current_password' => ['required', 'current_password'],
            'code' => ['required', 'string', 'regex:/^\d{6}$/'],
        ]);
        $user = $request->user();

        [$recoveryCodes] = DB::transaction(function () use ($user, $validated, $twoFactor, $auditLogs): array {
            $user = User::query()->lockForUpdate()->findOrFail($user->id);

            if ($user->two_factor_confirmed_at !== null || ! $user->two_factor_secret) {
                throw ValidationException::withMessages([
                    'two_factor' => ['Start two-factor setup again before confirming.'],
                ]);
            }

            $counter = $twoFactor->matchingCounter($user->two_factor_secret, $validated['code']);
            if ($counter === null) {
                throw ValidationException::withMessages([
                    'code' => ['The authenticator code is invalid. Check your device time and try again.'],
                ]);
            }

            $recoveryCodes = $twoFactor->generateRecoveryCodes();
            $user->forceFill([
                'two_factor_confirmed_at' => now(),
                'two_factor_last_used_counter' => $counter,
                'two_factor_recovery_codes' => $twoFactor->hashRecoveryCodes($recoveryCodes),
            ])->save();
            app(UserNotificationService::class)->create(
                $user,
                'security',
                'Two-factor authentication enabled',
                'Two-factor authentication was enabled for your account.',
            );

            if (Gate::forUser($user)->allows('admin')) {
                $auditLogs->record(
                    $user,
                    'admin.security.two_factor.enabled',
                    user: $user,
                    entity: $user,
                );
            }

            return [$recoveryCodes];
        }, 3);

        return response()->json([
            'enabled' => true,
            'recovery_codes' => $recoveryCodes,
        ]);
    }

    public function disableTwoFactor(Request $request, TwoFactorAuthenticationService $twoFactor, AuditLogService $auditLogs)
    {
        $validated = $request->validate([
            'current_password' => ['required', 'current_password'],
            'code' => ['required', 'string', 'max:32'],
        ]);
        $user = $request->user();

        $disabled = DB::transaction(function () use ($user, $validated, $twoFactor, $auditLogs): bool {
            $user = User::query()->lockForUpdate()->findOrFail($user->id);

            if ($user->two_factor_confirmed_at === null || ! $this->consumeTwoFactorProof($user, $validated['code'], $twoFactor)) {
                return false;
            }

            $user->forceFill([
                'two_factor_secret' => null,
                'two_factor_confirmed_at' => null,
                'two_factor_recovery_codes' => null,
                'two_factor_last_used_counter' => null,
            ])->save();
            app(UserNotificationService::class)->create(
                $user,
                'security',
                'Two-factor authentication disabled',
                'Two-factor authentication was disabled for your account.',
            );

            if (Gate::forUser($user)->allows('admin')) {
                $auditLogs->record(
                    $user,
                    'admin.security.two_factor.disabled',
                    user: $user,
                    entity: $user,
                );
            }

            return true;
        }, 3);

        if (! $disabled) {
            throw ValidationException::withMessages([
                'code' => ['The authentication or recovery code is invalid.'],
            ]);
        }

        return response()->json(['enabled' => false]);
    }

    public function regenerateTwoFactorRecoveryCodes(Request $request, TwoFactorAuthenticationService $twoFactor, AuditLogService $auditLogs)
    {
        $validated = $request->validate([
            'current_password' => ['required', 'current_password'],
            'code' => ['required', 'string', 'max:32'],
        ]);
        $user = $request->user();

        $recoveryCodes = DB::transaction(function () use ($user, $validated, $twoFactor, $auditLogs): ?array {
            $user = User::query()->lockForUpdate()->findOrFail($user->id);

            if ($user->two_factor_confirmed_at === null || ! $this->consumeTwoFactorProof($user, $validated['code'], $twoFactor)) {
                return null;
            }

            $recoveryCodes = $twoFactor->generateRecoveryCodes();
            $user->two_factor_recovery_codes = $twoFactor->hashRecoveryCodes($recoveryCodes);
            $user->save();
            app(UserNotificationService::class)->create(
                $user,
                'security',
                'Two-factor recovery codes regenerated',
                'Your two-factor recovery codes were replaced.',
            );

            if (Gate::forUser($user)->allows('admin')) {
                $auditLogs->record(
                    $user,
                    'admin.security.two_factor.recovery_codes_regenerated',
                    user: $user,
                    entity: $user,
                );
            }

            return $recoveryCodes;
        }, 3);

        if (! $recoveryCodes) {
            throw ValidationException::withMessages([
                'code' => ['The authenticator or recovery code is invalid.'],
            ]);
        }

        return response()->json(['recovery_codes' => $recoveryCodes]);
    }

    private function serializeUser(User $user): array
    {
        return [
            ...$user->only(['id', 'name', 'email', 'country', 'phone', 'role', 'status', 'email_verified_at']),
            'two_factor_enabled' => $user->two_factor_confirmed_at !== null,
        ];
    }

    private function consumeTwoFactorProof(User $user, string $code, TwoFactorAuthenticationService $twoFactor): bool
    {
        $counter = $twoFactor->matchingCounter($user->two_factor_secret, $code);

        if ($counter !== null && $counter > (int) ($user->two_factor_last_used_counter ?? -1)) {
            $user->two_factor_last_used_counter = $counter;
            $user->save();

            return true;
        }

        return $twoFactor->consumeRecoveryCode($user, $code);
    }
}
