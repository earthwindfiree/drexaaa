<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

#[Fillable([
    'name',
    'minimum_balance',
    'strategy_id',
    'description',
    'benefits',
    'feature_access',
    'display_settings',
])]
class Tier extends Model
{
    protected function casts(): array
    {
        return [
            'minimum_balance' => 'decimal:2',
            'benefits' => 'array',
            'feature_access' => 'array',
            'display_settings' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::deleting(function (Tier $tier): void {
            if ($tier->accounts()->exists()) {
                throw new LogicException('A tier assigned to accounts cannot be deleted.');
            }
        });
    }

    public function strategy(): BelongsTo
    {
        return $this->belongsTo(Strategy::class);
    }

    public function accounts(): HasMany
    {
        return $this->hasMany(Account::class);
    }
}
