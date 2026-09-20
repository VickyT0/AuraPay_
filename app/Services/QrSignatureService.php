<?php

namespace App\Services;

use App\Models\PaymentRequest;
use RuntimeException;

class QrSignatureService
{
    public function sign(
        PaymentRequest $request
    ): string {

        $secret =
            (string) config(
                'services.aurapay.qr_secret'
            );

        if ($secret === '') {

            throw new RuntimeException(
                'AURAPAY_QR_SECRET is not configured.'
            );
        }

        return hash_hmac(
            'sha256',
            $this->payload($request),
            $secret
        );
    }

    public function verify(
        PaymentRequest $request,
        string $providedSignature
    ): bool {

        if (
            strlen($providedSignature)
            !== 64
        ) {
            return false;
        }

        $expected =
            $this->sign($request);

        if (
            ! hash_equals(
                $expected,
                $providedSignature
            )
        ) {
            return false;
        }

        if (
            ! is_string(
                $request->signature
            )
        ) {
            return false;
        }

        return hash_equals(
            $request->signature,
            $providedSignature
        );
    }

    private function payload(
        PaymentRequest $request
    ): string {

        return implode(
            '|',
            [
                (string)
                    $request->reference,

                (string)
                    $request->receiver_wallet_id,

                (string)
                    ($request->amount ?? ''),

                (string)
                    ($request->expires_at?->timestamp ?? ''),
            ]
        );
    }
}