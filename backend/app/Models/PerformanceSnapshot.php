<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PerformanceSnapshot extends Model
{
    protected $guarded = ['*'];

    protected function casts(): array
    {
        return [
            'managed_balance' => 'decimal:2',
            'total_profit_loss' => 'decimal:2',
            'performance_percentage' => 'decimal:4',
            'snapshot_at' => 'datetime',
        ];
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }
}
