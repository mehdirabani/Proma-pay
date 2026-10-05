<?php

namespace App\Http\Controllers;

use App\Actions\Payments\CaptureManualPayment;
use App\Actions\Payments\CreateSettlementQuote;
use App\Http\Requests\ManualPaymentCaptureRequest;
use App\Http\Requests\SettlementQuoteRequest;
use App\Models\Contract;
use App\Models\Customer;
use App\Models\SettlementQuote;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

class SettlementQuotePageController extends Controller
{
    public function store(SettlementQuoteRequest $request, Customer $customer, Contract $contract, CreateSettlementQuote $createQuote): RedirectResponse
    {
        $this->authorize('view', $customer);
        abort_unless((int) $contract->customer_id === (int) $customer->id, 404);
        $quote = $createQuote->handle($contract, $request->validated(), $request->user());

        return redirect()->route('settlement-quotes.show', $quote);
    }

    public function show(SettlementQuote $settlementQuote): View
    {
        $this->authorizeQuote($settlementQuote);
        $settlementQuote->load('contract');

        return view('settlements.quote', ['quote' => $settlementQuote]);
    }

    public function capture(ManualPaymentCaptureRequest $request, SettlementQuote $settlementQuote, CaptureManualPayment $capturePayment): RedirectResponse
    {
        $this->authorizeQuote($settlementQuote);
        $capturePayment->handle($settlementQuote, $request->validated(), $request->user());

        return redirect()->route('customers.show', $settlementQuote->customer_id)->with('success', 'پرداخت دریافت و به اقساط طبق پیش‌فاکتور تخصیص داده شد.');
    }

    private function authorizeQuote(SettlementQuote $quote): void
    {
        abort_unless((int) $quote->actor_id === (int) auth()->id(), 404);
        $this->authorize('view', $quote->customer);
    }
}
