<?php

namespace App\Services;

use App\Models\Account;
use App\Models\PerformanceSnapshot;
use App\Support\FixedDecimalMath;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

class PerformanceSnapshotService
{
    public function create(
        Account $account,
        string $managedBalance,
        string $totalProfitLoss,
        string $performancePercentage,
        Carbon $snapshotAt,
    ): PerformanceSnapshot {
        try {
            $managedBalance = FixedDecimalMath::normalize($managedBalance, 18, 2, 'managed_balance');
            $totalProfitLoss = $this->normalizeSignedDecimal($totalProfitLoss, 18, 2, 'total_profit_loss');
            $performancePercentage = $this->normalizeSignedDecimal($performancePercentage, 5, 4, 'performance_percentage');
        } catch (InvalidArgumentException $exception) {
            throw ValidationException::withMessages([
                'snapshot' => [$exception->getMessage()],
            ]);
        }

        try {
            $snapshot = new PerformanceSnapshot;
            $snapshot->account()->associate($account);
            $snapshot->managed_balance = $managedBalance;
            $snapshot->total_profit_loss = $totalProfitLoss;
            $snapshot->performance_percentage = $performancePercentage;
            $snapshot->snapshot_at = $snapshotAt;
            $snapshot->save();

            return $snapshot;
        } catch (QueryException $exception) {
            if (str_contains(strtolower($exception->getMessage()), 'unique')) {
                throw ValidationException::withMessages([
                    'snapshot_at' => ['A performance snapshot already exists for this account and timestamp.'],
                ]);
            }

            throw $exception;
        }
    }

    private function normalizeSignedDecimal(string $value, int $maxIntegerDigits, int $scale, string $field): string
    {
        $pattern = '/\A-?\d{1,'.$maxIntegerDigits.'}(?:\.\d{1,'.$scale.'})?\z/';

        if (! preg_match($pattern, $value)) {
            throw new InvalidArgumentException("{$field} must be a signed decimal with at most {$scale} fractional digits.");
        }

        $negative = str_starts_with($value, '-');
        $normalized = FixedDecimalMath::normalize($negative ? substr($value, 1) : $value, $maxIntegerDigits, $scale, $field);

        return $negative && ! FixedDecimalMath::isZero($normalized) ? '-'.$normalized : $normalized;
    }
}
