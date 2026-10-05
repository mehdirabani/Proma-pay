<?php

use App\Http\Controllers\Auth\WebAuthController;
use App\Http\Controllers\CustomerActionsController;
use App\Http\Controllers\CustomerPageController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\FinancialRulesController;
use App\Http\Controllers\PaymentCorrectionPageController;
use App\Http\Controllers\SettlementQuotePageController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => auth()->check() ? redirect()->route('dashboard') : redirect()->route('login'));

Route::middleware('guest')->group(function (): void {
    Route::get('/login', [WebAuthController::class, 'create'])->name('login');
    Route::post('/login', [WebAuthController::class, 'store'])->middleware('throttle:5,1')->name('login.store');
});

Route::middleware(['auth', 'active-user'])->group(function (): void {
    Route::get('/dashboard', DashboardController::class)->middleware('permission:dashboard.view')->name('dashboard');
    Route::get('/settings/financial-rules', [FinancialRulesController::class, 'edit'])
        ->middleware('permission:finance.rules.manage')->name('settings.financial-rules.edit');
    Route::put('/settings/financial-rules', [FinancialRulesController::class, 'update'])
        ->middleware('permission:finance.rules.manage')->name('settings.financial-rules.update');
    Route::get('/customers', [CustomerPageController::class, 'index'])->middleware('permission:customers.view')->name('customers.index');
    Route::get('/customers/create', [CustomerPageController::class, 'create'])->middleware('permission:customers.create')->name('customers.create');
    Route::get('/customers/{customer}', [CustomerPageController::class, 'show'])->middleware('permission:customers.view')->name('customers.show');
    Route::post('/customers', [CustomerPageController::class, 'store'])->middleware('permission:customers.create')->name('customers.store');
    Route::post('/customers/{customer}/contracts', [CustomerActionsController::class, 'createContractDraft'])->middleware('permission:contracts.create')->name('customers.contracts.store');
    Route::post('/contracts/{contract}/submit', [CustomerActionsController::class, 'submitContractForApproval'])->middleware('permission:contracts.submit')->name('contracts.submit');
    Route::put('/contracts/{contract}/approve', [CustomerActionsController::class, 'approveContract'])->middleware('permission:contracts.approve')->name('contracts.approve');
    Route::post('/contracts/{contract}/activate', [CustomerActionsController::class, 'activateContract'])->middleware('permission:contracts.activate')->name('contracts.activate');
    Route::post('/customers/{customer}/kyc-cases', [CustomerActionsController::class, 'submitKyc'])->middleware('permission:customers.update')->name('customers.kyc.store');
    Route::put('/kyc-cases/{kycCase}/review', [CustomerActionsController::class, 'reviewKyc'])->middleware('permission:kyc.review')->name('kyc-cases.review');
    Route::post('/customers/{customer}/credit-adjustments', [CustomerActionsController::class, 'adjustCredit'])->middleware('permission:credit.adjust')->name('customers.credit.adjust');
    Route::post('/customers/{customer}/payment-intents', [CustomerActionsController::class, 'registerPayment'])->middleware('permission:payments.record')->name('customers.payments.store');
    Route::post('/payments/{payment}/correction', [PaymentCorrectionPageController::class, 'store'])
        ->middleware('permission:payments.correct')->name('payments.correction.store');
    Route::post('/customers/{customer}/contracts/{contract}/settlement-quotes', [SettlementQuotePageController::class, 'store'])
        ->middleware('permission:payments.record')->name('customers.contracts.settlement-quotes.store');
    Route::get('/settlement-quotes/{settlementQuote}', [SettlementQuotePageController::class, 'show'])
        ->middleware('permission:payments.record')->name('settlement-quotes.show');
    Route::post('/settlement-quotes/{settlementQuote}/capture', [SettlementQuotePageController::class, 'capture'])
        ->middleware('permission:payments.record')->name('settlement-quotes.capture');
    Route::post('/logout', [WebAuthController::class, 'destroy'])->name('logout');
});
