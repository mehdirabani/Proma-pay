<?php

namespace App\Http\Controllers\Api;

use App\Actions\Payments\CaptureManualPayment;
use App\Actions\Payments\CreateSettlementQuote;
use App\Http\Controllers\Controller;
use App\Http\Requests\ManualPaymentCaptureRequest;
use App\Http\Requests\SettlementQuoteRequest;
use App\Http\Resources\PaymentResource;
use App\Http\Resources\SettlementQuoteResource;
use App\Models\Contract;
use App\Models\SettlementQuote;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SettlementQuoteController extends Controller
{
    public function store(SettlementQuoteRequest $request, Contract $contract, CreateSettlementQuote $createQuote): JsonResponse
    {
        $this->authorize('view', $contract->customer);
        $quote = $createQuote->handle($contract, $request->validated(), $request->user());

        return (new SettlementQuoteResource($quote))->response()->setStatusCode($quote->wasRecentlyCreated ? 201 : 200);
    }

    public function show(Request $request, SettlementQuote $settlementQuote): SettlementQuoteResource
    {
        $this->authorizeQuote($request, $settlementQuote);

        return new SettlementQuoteResource($settlementQuote);
    }

    public function capture(ManualPaymentCaptureRequest $request, SettlementQuote $settlementQuote, CaptureManualPayment $capturePayment): JsonResponse
    {
        $this->authorizeQuote($request, $settlementQuote);
        $payment = $capturePayment->handle($settlementQuote, $request->validated(), $request->user());

        return (new PaymentResource($payment))->response()->setStatusCode($payment->wasRecentlyCreated ? 201 : 200);
    }

    private function authorizeQuote(Request $request, SettlementQuote $quote): void
    {
        abort_unless((int) $quote->actor_id === (int) $request->user()->id, 404);
        $this->authorize('view', $quote->customer);
    }
}
