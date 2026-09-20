<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Wallet;
use App\Services\WalletService;
use Illuminate\Http\Request;

class WalletController extends Controller
{
    public function __construct(private WalletService $wallets) {}

    public function show(Wallet $wallet)
    {
        $this->authorizeOwner($wallet);

        return response()->json([
            'id' => $wallet->id,
            'balance' => $wallet->balance,
            'currency' => $wallet->currency,
            'status' => $wallet->status,
        ]);
    }

    /**
     * Mock top-up — adds simulated funds. No real payment gateway involved.
     */
    public function topUp(Request $request, Wallet $wallet)
    {
        $this->authorizeOwner($wallet);

        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01', 'max:100000'],
        ]);

        $wallet = $this->wallets->credit($wallet, $validated['amount']);

        return response()->json([
            'message' => 'Wallet topped up (simulated).',
            'balance' => $wallet->balance,
        ]);
    }

    private function authorizeOwner(Wallet $wallet): void
    {
        abort_unless($wallet->user_id === auth()->id(), 403);
    }
}
