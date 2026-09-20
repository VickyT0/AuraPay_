<?php

namespace App\Services;

use App\Exceptions\InsufficientFundsException;
use App\Models\PaymentRequest;
use App\Models\Transaction;
use App\Models\Wallet;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class TransactionService
{
    public function __construct(
        private WalletService $wallets,
        private RiskScoringService $risk,
        private AuditService $audit,
    ) {
    }

    public function sendA2A(
    Wallet $sender,
    Wallet $receiver,
    float $amount,
    ?string $idempotencyKey = null,
    ?string $correlationId = null

): Transaction {

    try {

        return $this->process(
            $sender,
            $receiver,
            $amount,
            'a2a',
            null,
            $idempotencyKey,
            $correlationId
        );

    } catch (
        InsufficientFundsException $e
    ) {

        $this->audit->log(
            'transaction.failed',
            null,
            $sender->user_id,
            'A2A transaction failed.',
            [
                'reason'
                    => 'insufficient_funds',

                'receiver_wallet_id'
                    => $receiver->id,

                'amount'
                    => $amount,

                    'correlation_id'
                => $correlationId,
            ]
        );

        throw $e;
    }
}

    public function payViaQr(
        Wallet $payer,
        PaymentRequest $request,
        ?float $amount = null
    ): Transaction {

        $finalAmount =
            $request->amount
            ?? $amount;

        if ($finalAmount === null) {

            throw new InvalidArgumentException(
                'An amount is required for this payment request.'
            );
        }

        $transaction = $this->process(
            $payer,
            $request->receiverWallet,
            (float) $finalAmount,
            'qr',
            $request,
            null
        );

        $request->update([
            'status' => 'completed',
        ]);

        return $transaction;
    }

    private function process(
        Wallet $sender,
        Wallet $receiver,
        float $amount,
        string $type,
        ?PaymentRequest $request = null,
        ?string $idempotencyKey = null,
        ?string $correlationId = null
    ): Transaction {

        return DB::transaction(
            function () use (
                $sender,
                $receiver,
                $amount,
                $type,
                $request,
                $idempotencyKey,
                $correlationId
            ) {

                $risk = $this->risk->score(
                    $sender,
                    $amount,
                    $type
                );

                $transaction =
                    Transaction::create([

                        'reference'
                            => (string) Str::uuid(),

                        'idempotency_key'
                            => $idempotencyKey,

                        'sender_wallet_id'
                            => $sender->id,

                        'receiver_wallet_id'
                            => $receiver->id,

                        'payment_request_id'
                            => $request?->id,

                        'amount'
                            => $amount,

                        'type'
                            => $type,

                        'status'
                            => 'pending',

                        'risk_score'
                            => $risk['score'],

                        'risk_level'
                            => $risk['level'],
                            'metadata' => [
    'correlation_id' =>
        $correlationId,
],
                    ]);

                if (
                    $risk['level'] === 'high'
                ) {

                    $transaction->update([
                        'status' => 'flagged',
                    ]);

                    $this->audit->log(
                        'transaction.flagged',
                        $transaction,
                        $sender->user_id,
                        'Transaction held for manual review due to high risk score.',
                        [
                            'risk_score'
                                => $risk['score'],
                        ],
                        [
    'correlation_id' =>
        $correlationId,

    'risk_score' =>
        $transaction->risk_score,
],
                    );

                    return $transaction;
                }

                try {

                    $this->wallets->transfer(
                        $sender,
                        $receiver,
                        $amount
                    );

                    $transaction->update([
                        'status' => 'completed',
                    ]);

                    $this->audit->log(
                        'transaction.completed',
                        $transaction,
                        $sender->user_id,
                        'Transaction completed successfully.',
                        [
        'correlation_id' =>
            $correlationId,

        'risk_score' =>
            $transaction->risk_score,
    ]
                    );

                } catch (
    InsufficientFundsException $e
) {

    throw $e;
}

                return $transaction;
            }
        );
    }
}