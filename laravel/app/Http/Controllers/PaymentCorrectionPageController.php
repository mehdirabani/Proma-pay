<?php

namespace App\Http\Controllers;

use App\Actions\Payments\CorrectCapturedPayment;
use App\Http\Requests\PaymentCorrectionRequest;
use App\Models\Payment;
use Illuminate\Http\RedirectResponse;

class PaymentCorrectionPageController extends Controller
{
    public function store(PaymentCorrectionRequest $request, Payment $payment, CorrectCapturedPayment $correctPayment): RedirectResponse
    {
        $this->authorize('view', $payment->customer);
        $correctPayment->handle($payment, $request->validated(), $request->user());

        return redirect()->route('customers.show', $payment->customer_id)
            ->with('success', 'اصلاحیهٔ داخلی پرداخت ثبت شد؛ برگشت وجه بانکی انجام نشده است.');
    }
}
