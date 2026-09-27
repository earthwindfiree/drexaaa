<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

#[Fillable(['name', 'description', 'risk_profile', 'active'])]
class Strategy extends Model
{
    protected function casts(): array
    {
        return [
            'active' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::deleting(function (Strategy $strategy): void {
            if ($strategy->tiers()->exists()) {
                throw new LogicException('A strategy assigned to tiers cannot be deleted.');
            }
        });
    }

    public function tiers(): HasMany
    {
        return $this->hasMany(Tier::class);
    }
}
