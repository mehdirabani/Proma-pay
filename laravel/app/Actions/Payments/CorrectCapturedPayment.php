<?php

namespace App\Actions\Payments;

use App\Domain\Payments\InstallmentPaymentStateLoader;
use App\Enums\PaymentStatus;
use App\Models\AuditEvent;
use App\Models\Contract;
use App\Models\FinancialRule;
use App\Models\Installment;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Models\PaymentCorrection;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CorrectCapturedPayment
{
    public function __construct(private readonly InstallmentPaymentStateLoader $stateLoader) {}

    public function handle(Payment $payment, array $input, User $actor): PaymentCorrection
    {
        $reason = trim((string) $input['reason']);
        $requestHash = hash('sha256', json_encode([
            'payment_id' => (int) $payment->id,
            'actor_id' => (int) $actor->id,
            'reason' => $reason,
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));

        try {
            return DB::transaction(function () use ($payment, $input, $actor, $reason, $requestHash): PaymentCorrection {
                $replay = PaymentCorrection::query()->where('request_id', $input['request_id'])->first();
                if ($replay !== null) {
                    $this->assertSameRequest($replay, $requestHash);

                    return $replay;
                }

                $probe = Payment::query()->findOrFail($payment->id);
                abort_unless($probe->contract_id !== null, 404);
                $contract = Contract::query()->whereKey($probe->contract_id)->lockForUpdate()->firstOrFail();
                $lockedPayment = Payment::query()->whereKey($probe->id)->lockForUpdate()->firstOrFail();
                $replay = PaymentCorrection::query()->where('request_id', $input['request_id'])->first();
                if ($replay !== null) {
                    $this->assertSameRequest($replay, $requestHash);

                    return $replay;
                }
                if ($lockedPayment->status !== PaymentStatus::Paid) {
                    throw ValidationException::withMessages(['payment' => 'فقط پرداخت موفق و اصلاح‌نشده قابل اصلاح است.']);
                }
                if ($lockedPayment->payment_type === 'down_payment') {
                    throw ValidationException::withMessages(['payment' => 'پیش‌پرداخت قرارداد با اصلاحیهٔ قسط تغییر نمی‌کند.']);
                }
                if ($lockedPayment->payment_type !== 'installment' || (int) $lockedPayment->amount_toman <= 0) {
                    throw ValidationException::withMessages(['payment' => 'این نوع پرداخت در این جریان اصلاح پشتیبانی نمی‌شود.']);
                }

                $rules = FinancialRule::query()->whereKey(1)->lockForUpdate()->firstOrFail();
                if (! $rules->configured()) {
                    throw ValidationException::withMessages(['financial_rules' => 'قواعد مالی تنظیم نشده‌اند؛ بازسازی ماندهٔ قسط مسدود است.']);
                }

                $allocations = PaymentAllocation::query()
                    ->where('payment_id', $lockedPayment->id)
                    ->where('is_reversal', false)
                    ->orderBy('installment_id')
                    ->lockForUpdate()
                    ->get();
                if ($allocations->isEmpty()) {
                    throw ValidationException::withMessages(['payment' => 'برای این پرداخت، تخصیص قسطی قابل بازسازی پیدا نشد.']);
                }

                $installmentIds = $allocations->pluck('installment_id')->map(fn ($id): int => (int) $id)->unique()->sort()->values();
                $installments = Installment::query()
                    ->where('contract_id', $contract->id)
                    ->whereIn('id', $installmentIds)
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->get();
                if ($installments->count() !== $installmentIds->count()) {
                    throw ValidationException::withMessages(['payment' => 'یکی از اقساط تخصیص‌یافته دیگر به این قرارداد تعلق ندارد.']);
                }

                $asOf = now()->toDateString();
                $beforeStates = $this->stateLoader->load($contract, $installments, $rules, $asOf);
                $lockedPayment->status = PaymentStatus::Corrected;
                $lockedPayment->save();
                $afterStates = $this->stateLoader->load($contract, $installments, $rules, $asOf);

                $snapshot = [
                    'payment_id' => (int) $lockedPayment->id,
                    'contract_id' => (int) $contract->id,
                    'customer_id' => (int) $lockedPayment->customer_id,
                    'amount_toman' => (string) $lockedPayment->amount_toman,
                    'payment_date' => $lockedPayment->payment_date?->toDateString(),
                    'method' => $lockedPayment->method->value,
                    'provider_reference' => $lockedPayment->provider_reference,
                    'payment_type' => $lockedPayment->payment_type,
                    'financial_rules_version_at_correction' => (int) $rules->version,
                    'bank_refund_performed' => false,
                    'allocations' => $allocations->map(fn (PaymentAllocation $allocation): array => [
                        'allocation_id' => (int) $allocation->id,
                        'installment_id' => (int) $allocation->installment_id,
                        'allocated_amount_toman' => (string) $allocation->allocated_amount_toman,
                        'principal_applied_toman' => (string) $allocation->principal_applied_toman,
                        'normal_penalty_applied_toman' => (string) $allocation->normal_penalty_applied_toman,
                        'legal_penalty_applied_toman' => (string) $allocation->legal_penalty_applied_toman,
                        'reward_applied_toman' => (string) $allocation->reward_applied_toman,
                    ])->all(),
                    'before' => $this->indexStates($beforeStates),
                    'after' => $this->indexStates($afterStates),
                ];
                $now = now();
                $correction = PaymentCorrection::create([
                    'payment_id' => $lockedPayment->id,
                    'installment_id' => $installmentIds->count() === 1 ? $installmentIds->first() : null,
                    'contract_id' => $contract->id,
                    'customer_id' => $lockedPayment->customer_id,
                    'request_id' => $input['request_id'],
                    'request_hash' => $requestHash,
                    'amount_toman' => $lockedPayment->amount_toman,
                    'reason' => $reason,
                    'snapshot' => $snapshot,
                    'corrected_by' => $actor->id,
                    'created_at' => $now,
                ]);

                foreach ($allocations as $allocation) {
                    $state = collect($afterStates)->firstWhere('id', (int) $allocation->installment_id);
                    if ($state === null) {
                        throw ValidationException::withMessages(['payment' => 'وضعیت بازسازی‌شدهٔ یکی از اقساط پیدا نشد.']);
                    }
                    PaymentAllocation::create([
                        'payment_id' => $allocation->payment_id,
                        'settlement_quote_id' => $allocation->settlement_quote_id,
                        'contract_id' => $allocation->contract_id,
                        'installment_id' => $allocation->installment_id,
                        'allocated_amount_toman' => $allocation->allocated_amount_toman,
                        'principal_applied_toman' => $allocation->principal_applied_toman,
                        'normal_penalty_applied_toman' => $allocation->normal_penalty_applied_toman,
                        'legal_penalty_applied_toman' => $allocation->legal_penalty_applied_toman,
                        'reward_applied_toman' => $allocation->reward_applied_toman,
                        'principal_before_toman' => $allocation->principal_after_toman,
                        'principal_after_toman' => $allocation->principal_before_toman,
                        'normal_penalty_before_toman' => $allocation->normal_penalty_after_toman,
                        'normal_penalty_after_toman' => $allocation->normal_penalty_before_toman,
                        'legal_penalty_before_toman' => $allocation->legal_penalty_after_toman,
                        'legal_penalty_after_toman' => $allocation->legal_penalty_before_toman,
                        'status_after' => 'corrected',
                        'calculation_snapshot' => [
                            'type' => 'payment_correction_reversal',
                            'correction_id' => $correction->id,
                            'reversal_of_allocation_id' => $allocation->id,
                            'recomputed_installment_status' => $state['status'],
                        ],
                        'is_reversal' => true,
                        'reversal_of_id' => $allocation->id,
                        'created_at' => $now,
                    ]);
                }

                foreach ($installments as $installment) {
                    $state = collect($afterStates)->firstWhere('id', (int) $installment->id);
                    $installment->update([
                        'paid_toman' => (string) $state['effective_paid_principal'],
                        'status' => $state['status'],
                    ]);
                }

                AuditEvent::create([
                    'actor_id' => $actor->id,
                    'request_id' => $input['request_id'],
                    'event_name' => 'payment.corrected',
                    'subject_type' => Payment::class,
                    'subject_id' => $lockedPayment->id,
                    'metadata' => [
                        'correction_id' => $correction->id,
                        'contract_id' => $contract->id,
                        'customer_id' => $lockedPayment->customer_id,
                        'amount_toman' => $lockedPayment->amount_toman,
                        'reason' => $reason,
                        'installment_ids' => $installmentIds->all(),
                        'bank_refund_performed' => false,
                    ],
                    'ip_address' => request()->ip(),
                    'created_at' => $now,
                ]);

                return $correction;
            }, attempts: 3);
        } catch (QueryException $exception) {
            $replay = PaymentCorrection::query()->where('request_id', $input['request_id'])->first();
            if ($replay !== null) {
                $this->assertSameRequest($replay, $requestHash);

                return $replay;
            }

            $existingForPayment = PaymentCorrection::query()->where('payment_id', $payment->id)->first();
            if ($existingForPayment !== null) {
                throw ValidationException::withMessages(['payment' => 'این پرداخت قبلاً اصلاح شده است.']);
            }

            throw $exception;
        }
    }

    /** @param array<int, array<string, mixed>> $states */
    private function indexStates(array $states): array
    {
        $indexed = [];
        foreach ($states as $state) {
            $indexed[(string) $state['id']] = $state;
        }

        return $indexed;
    }

    private function assertSameRequest(PaymentCorrection $correction, string $requestHash): void
    {
        if (! hash_equals($correction->request_hash, $requestHash)) {
            throw ValidationException::withMessages(['request_id' => 'این شناسهٔ درخواست برای اصلاحیهٔ دیگری استفاده شده است.']);
        }
    }
}
