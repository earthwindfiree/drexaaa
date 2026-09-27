<?php

namespace App\Services;

use App\Models\Account;
use App\Models\PerformanceSnapshot;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

class PerformanceHistoryService
{
    public function forAccount(
        Account $account,
        string $order = 'asc',
        ?Carbon $from = null,
        ?Carbon $to = null,
    ): Builder {
        $direction = strtolower($order) === 'desc' ? 'desc' : 'asc';

        return PerformanceSnapshot::query()
            ->where('account_id', $account->getKey())
            ->when($from, fn (Builder $query) => $query->where('snapshot_at', '>=', $from))
            ->when($to, fn (Builder $query) => $query->where('snapshot_at', '<=', $to))
            ->orderBy('snapshot_at', $direction)
            ->orderBy('id', $direction);
    }
}
