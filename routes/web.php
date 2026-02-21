<?php

use App\Http\Controllers\CryptoBalanceController;
use App\Http\Controllers\WebhookController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// Crypto balance routes (protected by auth middleware)
Route::middleware(['auth', 'crypto.limit:general'])->group(function () {
    Route::get('/balance', [CryptoBalanceController::class, 'getBalance'])->name('balance.get');
    Route::get('/balance/transactions', [CryptoBalanceController::class, 'getTransactionHistory'])->name('balance.transactions');

    // Routes with specific operation limits
    Route::middleware('crypto.limit:deposit')->group(function () {
        Route::post('/balance/deposit', [CryptoBalanceController::class, 'deposit'])->name('balance.deposit');
    });

    Route::middleware('crypto.limit:withdraw')->group(function () {
        Route::post('/balance/withdraw', [CryptoBalanceController::class, 'withdraw'])->name('balance.withdraw');
    });

    Route::middleware('crypto.limit:transfer')->group(function () {
        Route::post('/balance/transfer', [CryptoBalanceController::class, 'transfer'])->name('balance.transfer');
    });

    Route::get('/balance/transaction/{transactionId}', [CryptoBalanceController::class, 'getTransactionDetails'])->name('balance.transaction');
});

// Webhook route for external blockchain notifications (no auth required)
Route::post('/webhook/crypto', [WebhookController::class, 'handleCryptoWebhook'])->name('webhook.crypto');
