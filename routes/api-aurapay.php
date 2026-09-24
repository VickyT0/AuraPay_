<?php

// This REPLACES routes/api-aurapay.php from the first round.
// Paste this into routes/api.php (merge the `use` statements with
// any already there).

use App\Http\Controllers\Api\ConsentController;
use App\Http\Controllers\Api\QrPaymentController;
use App\Http\Controllers\Api\TransactionController;
use App\Http\Controllers\Api\WalletController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AdminAuditController;
use App\Http\Controllers\Api\VopController;

// "Authentication and Session Management" — auth here is the session
// Fortify establishes after a passkey (or password) login. Everything
// below requires that session.
Route::middleware([
    'web',
    'auth',
    'idle.timeout',
    'api.gateway',
    'throttle:api-gateway',
])->group(function () {

    // "API Gateway" -> "Account Information and Test Operations"
    Route::middleware('consent:account_info')->group(function () {
    Route::get('/wallets/{wallet}', [WalletController::class, 'show']);
    Route::get('/transactions', [TransactionController::class, 'index']);
});

Route::post('/wallets/{wallet}/top-up', [WalletController::class, 'topUp']);
    Route::middleware('admin')->get(
    '/admin/audit-logs',
    [AdminAuditController::class, 'index']
);

    // "Consent Management"
    Route::get('/consents', [ConsentController::class, 'index']);
    Route::post('/consents', [ConsentController::class, 'store']);
    Route::delete('/consents/{consent}', [ConsentController::class, 'destroy']);

    Route::get('/qr/{reference}', [QrPaymentController::class, 'show']);
    Route::post(
    '/vop/check',
    [VopController::class, 'check']
);

    // "Consent Management" -> "Test Payments Orchestration" ->
    // "Risk Rules and Transaction Checks" -> "Bank and Payment
    // Services Test API" (TransactionService + WalletService).
    // Gated behind an active payment_initiation consent.
    Route::middleware('consent:payment_initiation')->group(function () {
        Route::post('/transactions/a2a', [TransactionController::class, 'sendA2A']);
        Route::post('/qr', [QrPaymentController::class, 'store']);
        Route::post('/qr/{reference}/pay', [QrPaymentController::class, 'pay']);
    });

});
