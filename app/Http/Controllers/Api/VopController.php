<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Wallet;
use App\Services\PaymentConfirmationService;
use App\Services\VopService;
use Illuminate\Http\Request;

class VopController extends Controller
{
    public function __construct(
        private VopService $vop,
        private PaymentConfirmationService $confirmations,
    ) {
    }

    public function check(Request $request)
    {
        $validated = $request->validate([
            'receiver_wallet_id' => [
                'required',
                'integer',
                'exists:wallets,id',
            ],

            'receiver_name' => [
                'required',
                'string',
                'max:255',
            ],

            'amount' => [
                'required',
                'numeric',
                'min:0.01',
            ],
        ]);

        $wallet = Wallet::with('user')
            ->findOrFail(
                $validated['receiver_wallet_id']
            );

        $vopResult = $this->vop->verify(
            $wallet,
            $validated['receiver_name']
        );

        /*
         * Confirmation token се създава само ако
         * Verification of Payee е успешно.
         *
         * Token-ът обвързва:
         * - потребителя;
         * - получателя;
         * - сумата;
         * - срока на валидност.
         */
        if ($vopResult['status'] === 'match') {
            $vopResult['confirmation_token'] =
                $this->confirmations->create(
                    $request->user(),
                    $wallet,
                    (float) $validated['amount']
                );

            $vopResult['confirmed_amount'] =
                number_format(
                    (float) $validated['amount'],
                    2,
                    '.',
                    ''
                );

            $vopResult['confirmed_receiver'] =
                $wallet->user->name;
        }

        return response()->json(
            $vopResult
        );
    }
}