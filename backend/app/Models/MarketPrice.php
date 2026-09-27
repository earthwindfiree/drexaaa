<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MarketPrice extends Model
{
    protected $guarded = ['*'];

    protected function casts(): array
    {
        return [
            'current_price' => 'decimal:8',
            'change_24h_percentage' => 'decimal:4',
        ];
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }
}
