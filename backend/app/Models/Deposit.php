<?php

namespace App\Models;

use App\Enums\DepositStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Deposit extends Model
{
    protected $guarded = ['*'];

    protected function casts(): array
    {
        return [
            'crypto_amount' => 'decimal:18',
            'price_snapshot' => 'decimal:8',
            'usd_value' => 'decimal:2',
            'status' => DepositStatus::class,
            'reviewed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    public function assetWallet(): BelongsTo
    {
        return $this->belongsTo(AssetWallet::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function transaction(): HasOne
    {
        return $this->hasOne(Transaction::class);
    }
}
