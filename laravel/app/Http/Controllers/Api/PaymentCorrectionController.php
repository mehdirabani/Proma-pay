<?php

namespace App\Http\Controllers\Api;

use App\Actions\Payments\CorrectCapturedPayment;
use App\Http\Controllers\Controller;
use App\Http\Requests\PaymentCorrectionRequest;
use App\Http\Resources\PaymentCorrectionResource;
use App\Models\Payment;
use Illuminate\Http\JsonResponse;

class PaymentCorrectionController extends Controller
{
    public function store(PaymentCorrectionRequest $request, Payment $payment, CorrectCapturedPayment $correctPayment): JsonResponse
    {
        $this->authorize('view', $payment->customer);
        $correction = $correctPayment->handle($payment, $request->validated(), $request->user());

        return (new PaymentCorrectionResource($correction))->response()->setStatusCode($correction->wasRecentlyCreated ? 201 : 200);
    }
}
