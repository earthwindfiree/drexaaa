<?php

namespace App\Services;

use App\Models\Account;
use App\Models\PerformanceSnapshot;
use App\Models\User;
use App\Support\FixedDecimalMath;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

class PerformanceSnapshotService
{
    public function __construct(private readonly AuditLogService $auditLogService) {}

    public function create(
        User $actor,
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
            return DB::transaction(function () use ($actor, $account, $managedBalance, $totalProfitLoss, $performancePercentage, $snapshotAt): PerformanceSnapshot {
                $snapshot = new PerformanceSnapshot;
                $snapshot->account()->associate($account);
                $snapshot->managed_balance = $managedBalance;
                $snapshot->total_profit_loss = $totalProfitLoss;
                $snapshot->performance_percentage = $performancePercentage;
                $snapshot->snapshot_at = $snapshotAt;
                $snapshot->save();

                $this->auditLogService->record(
                    $actor,
                    'performance_snapshot.created',
                    $account->user,
                    $snapshot,
                    newValues: [
                        'account_id' => $account->getKey(),
                        'managed_balance' => $snapshot->managed_balance,
                        'total_profit_loss' => $snapshot->total_profit_loss,
                        'performance_percentage' => $snapshot->performance_percentage,
                        'snapshot_at' => $snapshot->snapshot_at->toISOString(),
                    ],
                );

                return $snapshot;
            }, 3);
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
