<?php

namespace App\Services;

use App\Models\Asset;
use App\Models\MarketPrice;
use App\Models\User;
use App\Support\FixedDecimalMath;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

class MarketPriceService
{
    public function __construct(private readonly AuditLogService $auditLogService) {}

    public function currentFor(Asset $asset): MarketPrice
    {
        return $asset->marketPrice()->firstOrFail();
    }

    public function update(User $actor, Asset $asset, string $currentPrice, string $change24hPercentage): MarketPrice
    {
        try {
            $currentPrice = FixedDecimalMath::normalize($currentPrice, 12, 8, 'current_price');
            $change24hPercentage = $this->normalizeSignedDecimal($change24hPercentage, 5, 4, 'change_24h_percentage');
        } catch (InvalidArgumentException $exception) {
            throw ValidationException::withMessages([
                'market_price' => [$exception->getMessage()],
            ]);
        }

        if (FixedDecimalMath::isZero($currentPrice)) {
            throw ValidationException::withMessages([
                'current_price' => ['The current asset price must be greater than zero.'],
            ]);
        }

        return DB::transaction(function () use ($actor, $asset, $currentPrice, $change24hPercentage): MarketPrice {
            $lockedAsset = Asset::query()->lockForUpdate()->find($asset->getKey());

            if (! $lockedAsset) {
                throw (new ModelNotFoundException)->setModel(Asset::class, [$asset->getKey()]);
            }

            $marketPrice = $lockedAsset->marketPrice()->first() ?? new MarketPrice;
            $oldValues = $marketPrice->exists ? [
                'current_price' => $marketPrice->current_price,
                'change_24h_percentage' => $marketPrice->change_24h_percentage,
            ] : null;
            $marketPrice->asset()->associate($lockedAsset);
            $marketPrice->current_price = $currentPrice;
            $marketPrice->change_24h_percentage = $change24hPercentage;
            $marketPrice->save();
            $this->auditLogService->record(
                $actor,
                'market_price.updated',
                entity: $marketPrice,
                oldValues: $oldValues,
                newValues: [
                    'asset_id' => $lockedAsset->getKey(),
                    'current_price' => $marketPrice->current_price,
                    'change_24h_percentage' => $marketPrice->change_24h_percentage,
                ],
            );

            return $marketPrice;
        }, 3);
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
