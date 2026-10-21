<?php

namespace App\Actions\Payments;

use App\Models\AuditEvent;
use App\Models\Contract;
use App\Models\PaymentReceipt;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ReviewPaymentReceipt
{
    public function __construct(
        private readonly CreateSettlementQuote $createQuote,
        private readonly CaptureManualPayment $capturePayment,
    ) {}

    /** @param array{request_id:string,decision:string,review_note:string,approved_amount_toman?:?string} $input */
    public function handle(PaymentReceipt $receipt, array $input, User $actor): PaymentReceipt
    {
        $decision = (string) $input['decision'];
        $reviewNote = trim((string) $input['review_note']);
        $requestHash = hash('sha256', json_encode([
            'actor_id' => (int) $actor->id,
            'decision' => $decision,
            'review_note' => $reviewNote,
            'approved_amount_toman' => (string) ($input['approved_amount_toman'] ?? ''),
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));

        return DB::transaction(function () use ($receipt, $input, $actor, $decision, $reviewNote, $requestHash): PaymentReceipt {
            $lockedReceipt = PaymentReceipt::query()->whereKey($receipt->id)->lockForUpdate()->firstOrFail();
            if ($lockedReceipt->review_request_id === $input['request_id']) {
                $this->assertSameReview($lockedReceipt, $requestHash);

                return $lockedReceipt->load('payment');
            }
            if ($lockedReceipt->status !== 'pending') {
                throw ValidationException::withMessages(['receipt' => 'این مدرک قبلاً بررسی شده است.']);
            }

            $payment = null;
            $approvedAmount = null;
            if ($decision === 'approved') {
                $contract = Contract::query()->whereKey($lockedReceipt->contract_id)->lockForUpdate()->firstOrFail();
                if ((int) $contract->customer_id !== (int) $lockedReceipt->customer_id || $contract->status !== 'active') {
                    throw ValidationException::withMessages(['receipt' => 'قرارداد مرتبط دیگر برای دریافت وجه فعال نیست.']);
                }
                $approvedAmount = (string) ($input['approved_amount_toman'] ?? $lockedReceipt->amount_toman);
                $quote = $this->createQuote->handle($contract, [
                    'request_id' => (string) Str::uuid(),
                    'amount_toman' => $approvedAmount,
                    'scope' => 'selected',
                    'installment_ids' => [(int) $lockedReceipt->installment_id],
                ], $actor);
                $payment = $this->capturePayment->handle($quote, [
                    'idempotency_key' => (string) Str::uuid(),
                    'method' => 'bank_transfer',
                ], $actor);
            }

            $lockedReceipt->update([
                'payment_id' => $payment?->id,
                'approved_amount_toman' => $approvedAmount,
                'status' => $decision,
                'reviewed_by' => $actor->id,
                'reviewed_at' => now(),
                'review_note' => $reviewNote,
                'review_request_id' => $input['request_id'],
                'review_request_hash' => $requestHash,
            ]);

            AuditEvent::create([
                'actor_id' => $actor->id,
                'request_id' => $input['request_id'],
                'event_name' => 'payment_receipt.'.$decision,
                'subject_type' => PaymentReceipt::class,
                'subject_id' => $lockedReceipt->id,
                'metadata' => [
                    'customer_id' => $lockedReceipt->customer_id,
                    'contract_id' => $lockedReceipt->contract_id,
                    'installment_id' => $lockedReceipt->installment_id,
                    'payment_id' => $payment?->id,
                    'submitted_amount_toman' => $lockedReceipt->amount_toman,
                    'approved_amount_toman' => $approvedAmount,
                    'review_note' => $reviewNote,
                    'file_sha256' => $lockedReceipt->file_sha256,
                ],
                'ip_address' => request()->ip(),
                'created_at' => now(),
            ]);

            return $lockedReceipt->load('payment');
        }, attempts: 3);
    }

    private function assertSameReview(PaymentReceipt $receipt, string $requestHash): void
    {
        if ($receipt->review_request_hash === null || ! hash_equals($receipt->review_request_hash, $requestHash)) {
            throw ValidationException::withMessages(['request_id' => 'این شناسهٔ تصمیم قبلاً برای نتیجه یا شرح دیگری استفاده شده است.']);
        }
    }
}
