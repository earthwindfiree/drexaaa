<?php

namespace App\Models;

use App\Enums\SupportRequestStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupportRequest extends Model
{
    protected $guarded = ['*'];

    protected function casts(): array
    {
        return [
            'status' => SupportRequestStatus::class,
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
