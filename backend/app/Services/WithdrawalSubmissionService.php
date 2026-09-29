<?php

namespace App\Services;

use App\Contracts\AssetPriceProvider;
use App\Enums\WithdrawalStatus;
use App\Models\Account;
use App\Models\Asset;
use App\Models\User;
use App\Models\Withdrawal;
use App\Support\FixedDecimalMath;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use LogicException;

class WithdrawalSubmissionService
{
    public function __construct(
        private readonly WithdrawalAvailabilityService $availabilityService,
        private readonly ?AssetPriceProvider $assetPriceProvider = null,
    ) {}

    public function submit(User $user, int $assetId, string $amount, string $destinationWallet): Withdrawal
    {
        $amount = trim($amount);
        $destinationWallet = trim($destinationWallet);

        try {
            $amount = FixedDecimalMath::normalize($amount, 18, 2, 'amount');
        } catch (InvalidArgumentException $exception) {
            throw ValidationException::withMessages(['amount' => [$exception->getMessage()]]);
        }

        if (FixedDecimalMath::isZero($amount)) {
            throw ValidationException::withMessages(['amount' => ['The withdrawal amount must be greater than zero.']]);
        }

        if ($destinationWallet === '' || mb_strlen($destinationWallet) > 255) {
            throw ValidationException::withMessages([
                'destination_wallet' => ['A destination wallet of 1 to 255 characters is required.'],
            ]);
        }

        return DB::transaction(function () use ($user, $assetId, $amount, $destinationWallet): Withdrawal {
            $account = Account::query()
                ->where('user_id', $user->getKey())
                ->lockForUpdate()
                ->first();

            if (! $account) {
                throw new ModelNotFoundException('The user does not have an account.');
            }

            $asset = Asset::query()
                ->whereKey($assetId)
                ->where('active', true)
                ->lockForUpdate()
                ->first();

            if (! $asset) {
                throw ValidationException::withMessages(['asset_id' => ['The selected asset is unavailable.']]);
            }

            $withdrawable = $this->availabilityService->withdrawableAmount($account, lockPending: true);

            try {
                FixedDecimalMath::subtractNonNegative($withdrawable, $amount, 18, 2);
            } catch (InvalidArgumentException) {
                throw ValidationException::withMessages([
                    'amount' => ['The withdrawal amount exceeds the available withdrawable amount.'],
                ]);
            }

            [$cryptoAmount, $priceSnapshot] = $this->priceDataFor($asset, $amount);

            $withdrawal = new Withdrawal;
            $withdrawal->user()->associate($user);
            $withdrawal->account()->associate($account);
            $withdrawal->asset()->associate($asset);
            $withdrawal->amount = $amount;
            $withdrawal->destination_wallet = $destinationWallet;
            $withdrawal->crypto_amount = $cryptoAmount;
            $withdrawal->price_snapshot = $priceSnapshot;
            $withdrawal->status = WithdrawalStatus::Pending;
            $withdrawal->save();

            app(UserNotificationService::class)->create(
                $user,
                'financial',
                'Withdrawal submitted',
                'Withdrawal #'.$withdrawal->id.' was submitted and is pending review.',
            );

            return $withdrawal;
        }, 3);
    }

    private function priceDataFor(Asset $asset, string $amount): array
    {
        if ($this->assetPriceProvider === null) {
            return [null, null];
        }

        try {
            $price = FixedDecimalMath::normalize(
                $this->assetPriceProvider->currentUsdPriceFor($asset),
                12,
                8,
                'price_snapshot',
            );
        } catch (InvalidArgumentException $exception) {
            throw new LogicException('The asset price provider returned an invalid USD price.', 0, $exception);
        }

        if (FixedDecimalMath::isZero($price)) {
            throw new LogicException('The asset price provider must return a positive USD price.');
        }

        $cryptoAmount = FixedDecimalMath::divideToScale($amount, 2, $price, 8, 18, 18);

        if (FixedDecimalMath::isZero($cryptoAmount)) {
            throw ValidationException::withMessages([
                'amount' => ['The withdrawal amount is too small for the available crypto precision.'],
            ]);
        }

        return [$cryptoAmount, $price];
    }
}
