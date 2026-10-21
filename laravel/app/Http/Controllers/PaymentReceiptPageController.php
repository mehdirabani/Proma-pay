<?php

namespace App\Http\Controllers;

use App\Actions\Payments\ReviewPaymentReceipt;
use App\Actions\Payments\SubmitPaymentReceipt;
use App\Http\Requests\ReviewPaymentReceiptRequest;
use App\Http\Requests\UploadPaymentReceiptRequest;
use App\Models\Contract;
use App\Models\Installment;
use App\Models\PaymentReceipt;
use App\Services\PaymentReceiptEvidenceStorage;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class PaymentReceiptPageController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', PaymentReceipt::class);
        $receipts = PaymentReceipt::query()
            ->with(['customer', 'contract', 'installment', 'submittedBy'])
            ->whereHas('customer', fn ($query) => $query->visibleTo($request->user()))
            ->where('status', 'pending')
            ->latest('id')
            ->paginate(20);

        return view('payment-receipts.index', ['receipts' => $receipts]);
    }

    public function store(
        UploadPaymentReceiptRequest $request,
        Contract $contract,
        Installment $installment,
        SubmitPaymentReceipt $submitReceipt,
        PaymentReceiptEvidenceStorage $storage,
    ): RedirectResponse {
        $customer = $contract->customer;
        $this->authorize('view', $customer);
        abort_unless((int) $installment->contract_id === (int) $contract->id, 404);

        $file = $request->file('receipt');
        $evidence = $storage->store($file);
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

        return redirect()->route('customers.show', $customer)->with('success', 'مدرک انتقال بانکی ثبت شد و تا بررسی، پرداخت نهایی یا به قسط تخصیص داده نمی‌شود.');
    }

    public function file(PaymentReceipt $paymentReceipt, PaymentReceiptEvidenceStorage $storage)
    {
        $this->authorize('view', $paymentReceipt);

        return $storage->download($paymentReceipt);
    }

    public function review(ReviewPaymentReceiptRequest $request, PaymentReceipt $paymentReceipt, ReviewPaymentReceipt $reviewReceipt): RedirectResponse
    {
        $this->authorize('review', $paymentReceipt);
        $receipt = $reviewReceipt->handle($paymentReceipt, $request->validated(), $request->user());

        return redirect()->route('payment-receipts.index')->with(
            'success',
            $receipt->status === 'approved'
                ? 'مدرک تأیید و مبلغ طبق قواعد جاری به قسط تخصیص داده شد.'
                : 'مدرک رد شد؛ هیچ پرداختی ثبت یا تخصیص داده نشد.',
        );
    }
}
