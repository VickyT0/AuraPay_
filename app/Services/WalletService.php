<?php

namespace App\Services;

use App\Exceptions\InsufficientFundsException;
use App\Models\Wallet;
use Illuminate\Support\Facades\DB;

class WalletService
{
    /**
     * Credit a wallet. Simulated — no real money involved.
     */
    public function credit(Wallet $wallet, float $amount): Wallet
    {
        return DB::transaction(function () use ($wallet, $amount) {
            $locked = Wallet::whereKey($wallet->id)->lockForUpdate()->first();
            $locked->increment('balance', $amount);

            return $locked->refresh();
        });
    }

    /**
     * Debit a wallet, throwing if funds are insufficient.
     */
    public function debit(Wallet $wallet, float $amount): Wallet
    {
        return DB::transaction(function () use ($wallet, $amount) {
            $locked = Wallet::whereKey($wallet->id)->lockForUpdate()->first();

            if ($locked->balance < $amount) {
                throw new InsufficientFundsException();
            }

            $locked->decrement('balance', $amount);

            return $locked->refresh();
        });
    }

    /**
     * Move funds between two wallets atomically (debit then credit,
     * both inside one outer transaction so it's all-or-nothing).
     */
    public function transfer(Wallet $sender, Wallet $receiver, float $amount): void
    {
        DB::transaction(function () use ($sender, $receiver, $amount) {
            $this->debit($sender, $amount);
            $this->credit($receiver, $amount);
        });
    }
}
