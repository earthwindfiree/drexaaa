<?php

namespace App\Services;

use App\Models\Account;
use InvalidArgumentException;

class AccountBalanceService
{
    public function __construct(private readonly TierCalculator $tierCalculator) {}

    public function setManagedBalance(Account $account, string|int $managedBalance): Account
    {
        $normalizedBalance = $this->normalizeBalance($managedBalance);
        $qualifyingTier = $this->tierCalculator->qualifyingTier($normalizedBalance);

        $account->managed_balance = $normalizedBalance;
        $account->tier_id = $qualifyingTier?->id;
        $account->save();

        return $account;
    }

    private function normalizeBalance(string|int $managedBalance): string
    {
        $value = (string) $managedBalance;

        if (! preg_match('/\A\d{1,18}(?:\.\d{1,2})?\z/', $value)) {
            throw new InvalidArgumentException('Managed balance must be a non-negative decimal with at most two fractional digits.');
        }

        [$whole, $fraction] = array_pad(explode('.', $value, 2), 2, '');
        $whole = ltrim($whole, '0') ?: '0';

        return $whole.'.'.str_pad($fraction, 2, '0');
    }
}
