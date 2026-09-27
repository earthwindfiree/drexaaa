<?php

namespace App\Services;

use App\Models\Tier;

class TierCalculator
{
    public function qualifyingTier(string|int $managedBalance): ?Tier
    {
        return Tier::query()
            ->where('minimum_balance', '<=', $managedBalance)
            ->orderByDesc('minimum_balance')
            ->first();
    }
}
