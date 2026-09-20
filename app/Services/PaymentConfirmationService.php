<?php

namespace App\Services;

use App\Models\User;
use App\Models\Wallet;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Contracts\Encryption\DecryptException;

class PaymentConfirmationService
{
    public function create(
        User $user,
        Wallet $receiver,
        float $amount
    ): string {

        return Crypt::encryptString(
            json_encode([
                'user_id' => $user->id,
                'receiver_wallet_id' => $receiver->id,
                'amount' => number_format(
                    $amount,
                    2,
                    '.',
                    ''
                ),
                'expires_at' => now()
                    ->addMinutes(5)
                    ->timestamp,
            ], JSON_THROW_ON_ERROR)
        );
    }

    public function verify(
        string $token,
        User $user,
        Wallet $receiver,
        float $amount
    ): bool {

        try {

            $payload = json_decode(
                Crypt::decryptString($token),
                true,
                512,
                JSON_THROW_ON_ERROR
            );

        } catch (
            DecryptException|\JsonException
        ) {

            return false;
        }

        if (
            (int) ($payload['user_id'] ?? 0)
            !== $user->id
        ) {
            return false;
        }

        if (
            (int) ($payload['receiver_wallet_id'] ?? 0)
            !== $receiver->id
        ) {
            return false;
        }

        if (
            (string) ($payload['amount'] ?? '')
            !== number_format(
                $amount,
                2,
                '.',
                ''
            )
        ) {
            return false;
        }

        if (
            (int) ($payload['expires_at'] ?? 0)
            < now()->timestamp
        ) {
            return false;
        }

        return true;
    }
}