<?php

namespace App\Actions\Payments;

use App\Domain\Payments\InstallmentPaymentStateLoader;
use App\Domain\Payments\SettlementQuotePlanner;
use App\Domain\Payments\SettlementQuoteSnapshot;
use App\Enums\PaymentStatus;
use App\Models\AuditEvent;
use App\Models\Contract;
use App\Models\FinancialRule;
use App\Models\Installment;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Models\SettlementQuote;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CaptureManualPayment
{
    public function __construct(
        private readonly InstallmentPaymentStateLoader $stateLoader,
        private readonly SettlementQuotePlanner $planner,
        private readonly SettlementQuoteSnapshot $snapshotter,
    ) {}

    public function handle(SettlementQuote $settlementQuote, array $input, User $actor): Payment
    {
        $requestHash = $this->requestHash($settlementQuote, $input, $actor);

        try {
            return DB::transaction(function () use ($settlementQuote, $input, $actor, $requestHash): Payment {
                $existing = Payment::query()->where('idempotency_key', $input['idempotency_key'])->lockForUpdate()->first();
                if ($existing !== null) {
                    $this->assertSameRequest($existing, $requestHash);

                    return $existing->load('allocations');
                }

                $quoteProbe = SettlementQuote::query()->findOrFail($settlementQuote->id);
                $contract = Contract::query()->whereKey($quoteProbe->contract_id)->lockForUpdate()->firstOrFail();
                if ($contract->status !== 'active') {
                    throw ValidationException::withMessages(['contract' => 'قرارداد دیگر فعال نیست و دریافت قسط متوقف شد.']);
                }

                $rules = FinancialRule::query()->whereKey(1)->lockForUpdate()->firstOrFail();
                if (! $rules->configured()) {
                    throw ValidationException::withMessages(['financial_rules' => 'قواعد مالی تنظیم نشده‌اند؛ ثبت پرداخت مسدود است.']);
                }

                $installments = Installment::query()->where('contract_id', $contract->id)->orderBy('id')->lockForUpdate()->get();
                $quote = SettlementQuote::query()->whereKey($settlementQuote->id)->lockForUpdate()->firstOrFail();
                $this->assertUsableQuote($quote, $actor, $rules);

                $asOf = now()->toDateString();
                $states = $this->stateLoader->load($contract, $installments, $rules, $asOf);
                $calculation = $this->planner->plan($states, $quote->requested_installment_ids, $quote->scope, $quote->amount_toman);
                $snapshot = $this->snapshotter->create(
                    $contract,
                    (int) $actor->id,
                    $quote->scope,
                    $quote->requested_installment_ids,
                    $calculation,
                    $rules->snapshot(),
                    (int) $quote->amount_toman,
                    $asOf,
                );
                if ($quote->calculation_date->toDateString() !== $asOf
                    || (int) $quote->financial_rules_version !== (int) $rules->version
                    || ! hash_equals($quote->snapshot_hash, $snapshot['hash'])) {
                    throw ValidationException::withMessages(['settlement_quote' => 'وضعیت قسط یا قواعد مالی پس از محاسبه تغییر کرده است؛ پیش‌فاکتور تازه بگیرید.']);
                }

                $now = now();
                $payment = Payment::create([
                    'customer_id' => $contract->customer_id,
                    'contract_id' => $contract->id,
                    'settlement_quote_id' => $quote->id,
                    'created_by' => $actor->id,
                    'idempotency_key' => $input['idempotency_key'],
                    'request_hash' => $requestHash,
                    'currency_code' => $contract->currency_code,
                    'amount_toman' => $quote->amount_toman,
                    'method' => $input['method'],
                    'status' => PaymentStatus::Paid,
                    'provider_reference' => $input['provider_reference'] ?? null,
                    'requested_at' => $now,
                    'completed_at' => $now,
                    'payment_date' => $asOf,
                    'payment_time' => $now->format('H:i:s'),
                    'payment_type' => 'installment',
                    'calculation_version' => $quote->calculation_version,
                    'financial_rules_version' => $rules->version,
                ]);

                $lockedInstallments = $installments->keyBy('id');
                $allocatedTotal = '0';
                foreach ($calculation['plan']['allocations'] as $allocation) {
                    if ((int) $allocation['allocated_amount'] === 0) {
                        continue;
                    }

                    $netComponents = bcsub(
                        bcadd(bcadd((string) $allocation['principal_applied'], (string) $allocation['normal_penalty_applied'], 0), (string) $allocation['legal_penalty_applied'], 0),
                        (string) $allocation['reward_applied'],
                        0,
                    );
                    if (bccomp($netComponents, (string) $allocation['allocated_amount'], 0) !== 0) {
                        throw ValidationException::withMessages(['settlement_quote' => 'جمع اجزای تخصیص با مبلغ دریافتی سازگار نیست.']);
                    }

                    $rowSnapshot = [
                        'calculation_version' => $quote->calculation_version,
                        'financial_rules_version' => (int) $rules->version,
                        'financial_rules_snapshot' => $rules->snapshot(),
                        'allocation' => $allocation,
                    ];
                    PaymentAllocation::create([
                        'payment_id' => $payment->id,
                        'settlement_quote_id' => $quote->id,
                        'contract_id' => $contract->id,
                        'installment_id' => $allocation['installment_id'],
                        'allocated_amount_toman' => $allocation['allocated_amount'],
                        'principal_applied_toman' => $allocation['principal_applied'],
                        'normal_penalty_applied_toman' => $allocation['normal_penalty_applied'],
                        'legal_penalty_applied_toman' => $allocation['legal_penalty_applied'],
                        'reward_applied_toman' => $allocation['reward_applied'],
                        'principal_before_toman' => $allocation['remaining_before'],
                        'principal_after_toman' => $allocation['remaining_after'],
                        'normal_penalty_before_toman' => $allocation['normal_penalty_before'],
                        'normal_penalty_after_toman' => $allocation['normal_penalty_after'],
                        'legal_penalty_before_toman' => $allocation['legal_penalty_before'],
                        'legal_penalty_after_toman' => $allocation['legal_penalty_after'],
                        'status_after' => $allocation['final_status'],
                        'calculation_snapshot' => $rowSnapshot,
                        'created_at' => $now,
                    ]);
                    $allocatedTotal = bcadd($allocatedTotal, (string) $allocation['allocated_amount'], 0);

                    $installment = $lockedInstallments->get($allocation['installment_id']);
                    if ($installment === null) {
                        throw ValidationException::withMessages(['settlement_quote' => 'یکی از اقساط پیش‌فاکتور دیگر در این قرارداد وجود ندارد.']);
                    }
                    $installment->update([
                        'paid_toman' => bcsub((string) $installment->amount_toman, (string) $allocation['remaining_after'], 0),
                        'status' => $allocation['final_status'],
                    ]);
                }

                if (bccomp($allocatedTotal, (string) $payment->amount_toman, 0) !== 0) {
                    throw ValidationException::withMessages(['settlement_quote' => 'مجموع تخصیص‌ها با مبلغ پرداخت برابر نیست.']);
                }

                $quote->update(['consumed_at' => $now, 'consumed_payment_id' => $payment->id]);
                AuditEvent::create([
                    'actor_id' => $actor->id,
                    'request_id' => $payment->idempotency_key,
                    'event_name' => 'payment.manual_captured',
                    'subject_type' => Payment::class,
                    'subject_id' => $payment->id,
                    'metadata' => [
                        'contract_id' => $contract->id,
                        'settlement_quote_id' => $quote->id,
                        'amount_toman' => $payment->amount_toman,
                        'method' => $payment->method->value,
                        'allocation_count' => count($calculation['plan']['allocations']),
                        'calculation_version' => $payment->calculation_version,
                        'financial_rules_version' => $payment->financial_rules_version,
                    ],
                    'ip_address' => request()->ip(),
                    'created_at' => $now,
                ]);

                return $payment->load('allocations');
            }, attempts: 3);
        } catch (QueryException $exception) {
            $existing = Payment::query()->where('idempotency_key', $input['idempotency_key'])->first();
            if ($existing !== null) {
                $this->assertSameRequest($existing, $requestHash);

                return $existing->load('allocations');
            }

            throw $exception;
        }
    }

    private function assertUsableQuote(SettlementQuote $quote, User $actor, FinancialRule $rules): void
    {
        if ((int) $quote->actor_id !== (int) $actor->id) {
            abort(404);
        }
        if (! $quote->isOpen()) {
            throw ValidationException::withMessages(['settlement_quote' => 'این پیش‌فاکتور قبلاً برای ثبت یک پرداخت مصرف شده است.']);
        }
        if ($quote->expires_at->lessThanOrEqualTo(now())) {
            throw ValidationException::withMessages(['settlement_quote' => 'مهلت پیش‌فاکتور پایان یافته است؛ مبلغ را دوباره محاسبه کنید.']);
        }
        if ((int) $quote->financial_rules_version !== (int) $rules->version) {
            throw ValidationException::withMessages(['settlement_quote' => 'قواعد مالی تغییر کرده‌اند؛ پیش‌فاکتور تازه بگیرید.']);
        }
    }

    private function requestHash(SettlementQuote $quote, array $input, User $actor): string
    {
        return hash('sha256', json_encode([
            'actor_id' => (int) $actor->id,
            'quote_uuid' => (string) $quote->quote_uuid,
            'method' => (string) $input['method'],
            'provider_reference' => (string) ($input['provider_reference'] ?? ''),
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));
    }

    private function assertSameRequest(Payment $payment, string $requestHash): void
    {
        if ($payment->status !== PaymentStatus::Paid || $payment->request_hash === null || ! hash_equals($payment->request_hash, $requestHash)) {
            throw ValidationException::withMessages(['idempotency_key' => 'این شناسهٔ ثبت پرداخت قبلاً برای درخواست دیگری استفاده شده است.']);
        }
    }
}
