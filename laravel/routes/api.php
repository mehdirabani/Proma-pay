<?php

use App\Http\Controllers\Api\ContractController;
use App\Http\Controllers\Api\CreditController;
use App\Http\Controllers\Api\CustomerController;
use App\Http\Controllers\Api\KycController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\PaymentCorrectionController;
use App\Http\Controllers\Api\SettlementQuoteController;
use App\Http\Controllers\Api\TokenController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->name('api.v1.')->group(function (): void {
    Route::post('/tokens', [TokenController::class, 'store'])->middleware('throttle:5,1')->name('tokens.store');

    Route::middleware(['auth:sanctum', 'active-user', 'abilities:proma-api'])->group(function (): void {
        Route::delete('/tokens/current', [TokenController::class, 'destroy'])->name('tokens.destroy');
        Route::get('/user', fn (Request $request) => $request->user()->only(['id', 'name', 'email']))->name('user');

        Route::middleware('permission:customers.view')->group(function (): void {
            Route::get('/customers', [CustomerController::class, 'index'])->name('customers.index');
            Route::get('/customers/{customer}', [CustomerController::class, 'show'])->name('customers.show');
        });
        Route::post('/customers', [CustomerController::class, 'store'])->middleware('permission:customers.create')->name('customers.store');
        Route::post('/contracts', [ContractController::class, 'store'])->middleware(['permission:contracts.create', 'throttle:10,1'])->name('contracts.store');
        Route::post('/contracts/{contract}/submit', [ContractController::class, 'submit'])->middleware('permission:contracts.submit')->name('contracts.submit');
        Route::put('/contracts/{contract}/approve', [ContractController::class, 'approve'])->middleware('permission:contracts.approve')->name('contracts.approve');
        Route::post('/contracts/{contract}/activate', [ContractController::class, 'activate'])->middleware('permission:contracts.activate')->name('contracts.activate');

        Route::post('/customers/{customer}/kyc-cases', [KycController::class, 'store'])
            ->middleware('permission:customers.update')->name('customers.kyc.store');
        Route::put('/kyc-cases/{kycCase}/review', [KycController::class, 'review'])
            ->middleware('permission:kyc.review')->name('kyc-cases.review');

        Route::get('/customers/{customer}/credit-account', [CreditController::class, 'show'])
            ->middleware('permission:credit.view')->name('customers.credit.show');
        Route::post('/customers/{customer}/credit-adjustments', [CreditController::class, 'adjust'])
            ->middleware('permission:credit.adjust')->name('customers.credit.adjust');

        Route::get('/payments', [PaymentController::class, 'index'])
            ->middleware('permission:payments.view')->name('payments.index');
        Route::post('/payments/{payment}/correction', [PaymentCorrectionController::class, 'store'])
            ->middleware('permission:payments.correct')->name('payments.correction.store');
        Route::post('/payment-intents', [PaymentController::class, 'store'])
            ->middleware('permission:payments.record')->name('payments.store');
        Route::post('/contracts/{contract}/settlement-quotes', [SettlementQuoteController::class, 'store'])
            ->middleware('permission:payments.record')->name('contracts.settlement-quotes.store');
        Route::get('/settlement-quotes/{settlementQuote}', [SettlementQuoteController::class, 'show'])
            ->middleware('permission:payments.record')->name('settlement-quotes.show');
        Route::post('/settlement-quotes/{settlementQuote}/capture', [SettlementQuoteController::class, 'capture'])
            ->middleware('permission:payments.record')->name('settlement-quotes.capture');
    });
});
