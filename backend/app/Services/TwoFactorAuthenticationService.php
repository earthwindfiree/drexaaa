<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Hash;

class TwoFactorAuthenticationService
{
    private const BASE32_ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    public function generateSecret(): string
    {
        return $this->encodeBase32(random_bytes(20));
    }

    public function provisioningUri(User $user, string $secret): string
    {
        $issuer = (string) config('app.name', 'Application');
        $label = rawurlencode($issuer.':'.$user->email);

        return 'otpauth://totp/'.$label.'?'.http_build_query([
            'secret' => $secret,
            'issuer' => $issuer,
            'algorithm' => 'SHA1',
            'digits' => 6,
            'period' => 30,
        ], '', '&', PHP_QUERY_RFC3986);
    }

    public function matchingCounter(string $secret, string $code, ?int $timestamp = null): ?int
    {
        if (! preg_match('/^\d{6}$/', $code)) {
            return null;
        }

        $currentCounter = intdiv($timestamp ?? time(), 30);

        foreach ([$currentCounter - 1, $currentCounter, $currentCounter + 1] as $counter) {
            if ($counter >= 0 && hash_equals($this->codeForCounter($secret, $counter), $code)) {
                return $counter;
            }
        }

        return null;
    }

    public function codeForCounter(string $secret, int $counter): string
    {
        $key = $this->decodeBase32($secret);
        $message = pack('N2', ($counter >> 32) & 0xffffffff, $counter & 0xffffffff);
        $hash = hash_hmac('sha1', $message, $key, true);
        $offset = ord($hash[19]) & 0x0f;
        $binary = unpack('N', substr($hash, $offset, 4))[1] & 0x7fffffff;

        return str_pad((string) ($binary % 1_000_000), 6, '0', STR_PAD_LEFT);
    }

    public function generateRecoveryCodes(): array
    {
        return array_map(
            fn (): string => implode('-', str_split(strtoupper(bin2hex(random_bytes(8))), 4)),
            range(1, 10),
        );
    }

    public function hashRecoveryCodes(array $codes): array
    {
        return array_map(fn (string $code): string => Hash::make($this->normalizeRecoveryCode($code)), $codes);
    }

    public function consumeRecoveryCode(User $user, string $code): bool
    {
        $candidate = $this->normalizeRecoveryCode($code);
        $codes = $user->two_factor_recovery_codes ?? [];

        foreach ($codes as $index => $hash) {
            if (Hash::check($candidate, $hash)) {
                unset($codes[$index]);
                $user->two_factor_recovery_codes = array_values($codes);
                $user->save();

                return true;
            }
        }

        return false;
    }

    private function normalizeRecoveryCode(string $code): string
    {
        return strtoupper(preg_replace('/[^A-Z0-9]/i', '', $code) ?? '');
    }

    private function encodeBase32(string $value): string
    {
        $bits = implode('', array_map(
            static fn (string $byte): string => str_pad(decbin(ord($byte)), 8, '0', STR_PAD_LEFT),
            str_split($value),
        ));
        $chunks = str_split($bits, 5);

        return implode('', array_map(
            fn (string $chunk): string => self::BASE32_ALPHABET[bindec(str_pad($chunk, 5, '0'))],
            $chunks,
        ));
    }

    private function decodeBase32(string $value): string
    {
        $value = strtoupper(rtrim($value, '='));
        $bits = '';

        foreach (str_split($value) as $character) {
            $index = strpos(self::BASE32_ALPHABET, $character);
            if ($index === false) {
                throw new \InvalidArgumentException('Invalid Base32 secret.');
            }

            $bits .= str_pad(decbin($index), 5, '0', STR_PAD_LEFT);
        }

        $bytes = '';
        foreach (str_split($bits, 8) as $byte) {
            if (strlen($byte) === 8) {
                $bytes .= chr(bindec($byte));
            }
        }

        return $bytes;
    }
}
