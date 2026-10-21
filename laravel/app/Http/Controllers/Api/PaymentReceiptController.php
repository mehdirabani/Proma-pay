<?php

namespace App\Http\Controllers\Api;

use App\Actions\Payments\ReviewPaymentReceipt;
use App\Actions\Payments\SubmitPaymentReceipt;
use App\Http\Controllers\Controller;
use App\Http\Requests\ReviewPaymentReceiptRequest;
use App\Http\Requests\UploadPaymentReceiptRequest;
use App\Http\Resources\PaymentReceiptResource;
use App\Models\Contract;
use App\Models\Installment;
use App\Models\PaymentReceipt;
use App\Services\PaymentReceiptEvidenceStorage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;

class PaymentReceiptController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('viewAny', PaymentReceipt::class);
        $receipts = PaymentReceipt::query()
            ->with(['customer', 'contract', 'installment'])
            ->whereHas('customer', fn ($query) => $query->visibleTo($request->user()))
            ->latest('id')
            ->paginate(20);

        return PaymentReceiptResource::collection($receipts);
    }

    public function store(
        UploadPaymentReceiptRequest $request,
        Contract $contract,
        Installment $installment,
        SubmitPaymentReceipt $submitReceipt,
        PaymentReceiptEvidenceStorage $storage,
    ): JsonResponse {
        $this->authorize('view', $contract->customer);
        abort_unless((int) $installment->contract_id === (int) $contract->id, 404);

        $evidence = $storage->store($request->file('receipt'));
        try {
            $receipt = $submitReceipt->handle($contract, $installment, [
                ...$request->safe()->except('receipt'),
                ...$evidence,
            ], $request->user());
            if ($receipt->file_path !== $evidence['file_path']) {
                $storage->delete($evidence['file_path']);
            }
        } catch (\Throwable $exception) {
            $storage->delete($evidence['file_path']);
            throw $exception;
        }

        return PaymentReceiptResource::make($receipt)->response()->setStatusCode($receipt->wasRecentlyCreated ? 201 : 200);
    }

    public function show(PaymentReceipt $paymentReceipt): PaymentReceiptResource
    {
        $this->authorize('view', $paymentReceipt);

        return PaymentReceiptResource::make($paymentReceipt);
    }

    public function file(PaymentReceipt $paymentReceipt, PaymentReceiptEvidenceStorage $storage)
    {
        $this->authorize('view', $paymentReceipt);

        return $storage->download($paymentReceipt);
    }

    public function temporaryUrl(PaymentReceipt $paymentReceipt): JsonResponse
    {
        $this->authorize('view', $paymentReceipt);
        $seconds = max(30, min(600, (int) config('proma.payment_receipt_temporary_url_ttl_seconds', 120)));
        $expiresAt = now()->addSeconds($seconds);
        $url = URL::temporarySignedRoute('api.v1.payment-receipts.temporary-file', $expiresAt, ['paymentReceipt' => $paymentReceipt->id]);

        return response()->json(['data' => ['url' => $url, 'expires_at' => $expiresAt->toIso8601String()]]);
    }

    public function temporaryFile(PaymentReceipt $paymentReceipt, PaymentReceiptEvidenceStorage $storage)
    {
        return $storage->download($paymentReceipt);
    }

    public function review(ReviewPaymentReceiptRequest $request, PaymentReceipt $paymentReceipt, ReviewPaymentReceipt $reviewReceipt): JsonResponse
    {
        $this->authorize('review', $paymentReceipt);
        $receipt = $reviewReceipt->handle($paymentReceipt, $request->validated(), $request->user());

        return PaymentReceiptResource::make($receipt)->response()->setStatusCode(200);
    }
}
