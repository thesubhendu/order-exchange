<?php

namespace App\Services;

use App\Models\Asset;

class AssetTransferService
{
    /**
     * Credit assets to a user's account.
     * This method assumes it's being called within a database transaction.
     */
    public function creditAsset(int $userId, string $symbol, float $amount): void
    {
        $asset = Asset::query()
            ->where(['symbol' => $symbol, 'user_id' => $userId])
            ->lockForUpdate()
            ->first();

        if (!$asset) {
            Asset::query()->create([
                'symbol' => $symbol,
                'user_id' => $userId,
                'amount' => $amount,
                'locked_amount' => 0,
            ]);
        } else {
            $asset->amount += $amount;
            $asset->save();
        }
    }

    /**
     * Debit locked assets from a user's account.
     * This method assumes it's being called within a database transaction.
     */
    public function debitLockedAsset(int $userId, string $symbol, float $amount): void
    {
        $asset = Asset::query()
            ->where(['symbol' => $symbol, 'user_id' => $userId])
            ->lockForUpdate()
            ->first();

        if (!$asset) {
            throw new \Exception('Asset not found for debit');
        }

        if ($asset->locked_amount < $amount) {
            throw new \Exception('Insufficient locked assets');
        }

        $asset->locked_amount -= $amount;
        $asset->save();
    }
}
