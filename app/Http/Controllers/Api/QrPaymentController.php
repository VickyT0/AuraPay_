<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\InsufficientFundsException;
use App\Http\Controllers\Controller;
use App\Models\PaymentRequest;
use App\Services\QrSignatureService;
use App\Services\TransactionService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class QrPaymentController extends Controller
{
    public function __construct(
        private TransactionService $transactions,
        private QrSignatureService $qrSignatures,
    ) {
    }

    public function store(Request $request)
    {
        $validated =
            $request->validate([

                'amount' => [
                    'nullable',
                    'numeric',
                    'min:0.01',
                ],

                'note' => [
                    'nullable',
                    'string',
                    'max:255',
                ],

                'expires_in_minutes' => [
                    'nullable',
                    'integer',
                    'min:1',
                    'max:1440',
                ],
            ]);

        $paymentRequest =
            PaymentRequest::create([

                'reference'
                    => (string) Str::uuid(),

                'receiver_wallet_id'
                    => $request->user()->wallet->id,

                'amount'
                    => $validated['amount']
                        ?? null,

                'note'
                    => $validated['note']
                        ?? null,

                'status'
                    => 'pending',

                'expires_at'
                    => isset(
                        $validated['expires_in_minutes']
                    )
                        ? now()->addMinutes(
                            $validated['expires_in_minutes']
                        )
                        : now()->addHours(24),
            ]);

        $signature =
            $this->qrSignatures->sign(
                $paymentRequest
            );

        $paymentRequest->update([
            'signature' => $signature,
        ]);

        return response()->json(
            $paymentRequest->fresh(),
            201
        );
    }


    public function show(
        Request $request,
        string $reference
    ) {

        $paymentRequest =
            PaymentRequest::where(
                'reference',
                $reference
            )->firstOrFail();

        $signature =
            (string)
            $request->query(
                'signature',
                ''
            );

        abort_unless(
            $this->qrSignatures->verify(
                $paymentRequest,
                $signature
            ),
            403,
            'Invalid QR signature.'
        );

        abort_if(
            $paymentRequest->isExpired(),
            410,
            'This payment request has expired.'
        );

        abort_unless(
            $paymentRequest->status === 'pending',
            422,
            'This payment request is no longer active.'
        );

        return response()->json(
            $paymentRequest->load(
                'receiverWallet.user'
            )
        );
    }


    public function pay(
        Request $request,
        string $reference
    ) {

        $paymentRequest =
            PaymentRequest::where(
                'reference',
                $reference
            )->firstOrFail();

        $validated =
            $request->validate([

                'signature' => [
                    'required',
                    'string',
                    'size:64',
                ],

                'amount' => [
                    'nullable',
                    'numeric',
                    'min:0.01',
                ],
            ]);

        abort_unless(
            $this->qrSignatures->verify(
                $paymentRequest,
                $validated['signature']
            ),
            403,
            'Invalid QR signature.'
        );

        abort_if(
            $paymentRequest->isExpired(),
            410,
            'This payment request has expired.'
        );

        abort_unless(
            $paymentRequest->status === 'pending',
            422,
            'This payment request is no longer active.'
        );

        $payer =
            $request->user()->wallet;

        try {

            $transaction =
                $this->transactions->payViaQr(
                    $payer,
                    $paymentRequest,
                    $validated['amount']
                        ?? null
                );

        } catch (
            InsufficientFundsException $e
        ) {

            return response()->json([
                'message'
                    => $e->getMessage(),
            ], 422);
        }

        return response()->json(
            $transaction,
            $transaction->status === 'flagged'
                ? 202
                : 201
        );
    }
}