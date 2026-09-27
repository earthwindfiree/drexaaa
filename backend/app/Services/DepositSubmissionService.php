<?php

namespace App\Services;

use App\Contracts\AssetPriceProvider;
use App\Enums\DepositStatus;
use App\Models\Account;
use App\Models\Asset;
use App\Models\Deposit;
use App\Models\User;
use App\Support\FixedDecimalMath;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use LogicException;

class DepositSubmissionService
{
    public function __construct(private readonly AssetPriceProvider $assetPriceProvider) {}

    public function submit(User $user, int $assetId, string $cryptoAmount, string $transactionReference): Deposit
    {
        $transactionReference = trim($transactionReference);

        if ($transactionReference === '' || mb_strlen($transactionReference) > 255) {
            throw ValidationException::withMessages([
                'transaction_reference' => ['A transaction reference of 1 to 255 characters is required.'],
            ]);
        }

        try {
            $cryptoAmount = FixedDecimalMath::normalize($cryptoAmount, 18, 18, 'crypto_amount');
        } catch (InvalidArgumentException $exception) {
            throw ValidationException::withMessages(['crypto_amount' => [$exception->getMessage()]]);
        }

        if (FixedDecimalMath::isZero($cryptoAmount)) {
            throw ValidationException::withMessages(['crypto_amount' => ['The crypto amount must be greater than zero.']]);
        }

        return DB::transaction(function () use ($user, $assetId, $cryptoAmount, $transactionReference): Deposit {
            $asset = Asset::query()
                ->whereKey($assetId)
                ->where('active', true)
                ->lockForUpdate()
                ->first();

            if (! $asset) {
                throw ValidationException::withMessages(['asset_id' => ['The selected asset is unavailable.']]);
            }

            $account = Account::query()
                ->where('user_id', $user->getKey())
                ->lockForUpdate()
                ->first();

            if (! $account) {
                throw new ModelNotFoundException('The user does not have an account.');
            }

            $activeWallets = $asset->wallets()->where('active', true)->lockForUpdate()->get();

            if ($activeWallets->count() !== 1) {
                throw ValidationException::withMessages([
                    'asset_id' => ['The selected asset must have exactly one active wallet configuration.'],
                ]);
            }

            $wallet = $activeWallets->first();
            $priceSnapshot = $this->priceSnapshotFor($asset);
            $usdValue = FixedDecimalMath::multiplyToScale($cryptoAmount, 18, $priceSnapshot, 8, 2, 18);

            if (FixedDecimalMath::isZero($usdValue)) {
                throw ValidationException::withMessages([
                    'crypto_amount' => ['The deposit value must be at least one cent.'],
                ]);
            }

            $pendingBalance = FixedDecimalMath::normalize($account->pending_balance, 18, 2, 'pending_balance');
            $account->pending_balance = FixedDecimalMath::add($pendingBalance, $usdValue, 18, 2);
            $account->save();

            $deposit = new Deposit;
            $deposit->user()->associate($user);
            $deposit->account()->associate($account);
            $deposit->asset()->associate($asset);
            $deposit->assetWallet()->associate($wallet);
            $deposit->crypto_amount = $cryptoAmount;
            $deposit->price_snapshot = $priceSnapshot;
            $deposit->usd_value = $usdValue;
            $deposit->transaction_reference = $transactionReference;
            $deposit->status = DepositStatus::Pending;
            $deposit->save();

            return $deposit;
        }, 3);
    }

    private function priceSnapshotFor(Asset $asset): string
    {
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

        return $price;
    }
}
