<?php

namespace App\Actions\Payments;

use App\Domain\Payments\InstallmentPaymentStateLoader;
use App\Models\AuditEvent;
use App\Models\Contract;
use App\Models\FinancialRule;
use App\Models\Installment;
use App\Models\PaymentReceipt;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SubmitPaymentReceipt
{
    public function __construct(private readonly InstallmentPaymentStateLoader $stateLoader) {}

    /** @param array{request_id:string,amount_toman:string,file_disk:string,file_path:string,file_mime:string,file_size:int,file_sha256:string} $input */
    public function handle(Contract $contract, Installment $installment, array $input, User $actor): PaymentReceipt
    {
        $requestHash = hash('sha256', json_encode([
            'actor_id' => (int) $actor->id,
            'contract_id' => (int) $contract->id,
            'installment_id' => (int) $installment->id,
            'amount_toman' => (string) $input['amount_toman'],
            'file_mime' => (string) $input['file_mime'],
            'file_size' => (int) $input['file_size'],
            'file_sha256' => (string) $input['file_sha256'],
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));

        try {
            return DB::transaction(function () use ($contract, $installment, $input, $actor, $requestHash): PaymentReceipt {
                $existing = PaymentReceipt::query()->where('request_id', $input['request_id'])->lockForUpdate()->first();
                if ($existing !== null) {
                    $this->assertSameRequest($existing, $requestHash);

                    return $existing;
                }

                $lockedContract = Contract::query()->whereKey($contract->id)->lockForUpdate()->firstOrFail();
                $existingAfterContractLock = PaymentReceipt::query()->where('request_id', $input['request_id'])->lockForUpdate()->first();
                if ($existingAfterContractLock !== null) {
                    $this->assertSameRequest($existingAfterContractLock, $requestHash);

                    return $existingAfterContractLock;
                }
                if ((int) $lockedContract->customer_id !== (int) $installment->contract?->customer_id
                    || (int) $installment->contract_id !== (int) $lockedContract->id) {
                    abort(404);
                }
                if ($lockedContract->status !== 'active') {
                    throw ValidationException::withMessages(['contract' => 'فقط برای قرارداد فعال می‌توان مدرک پرداخت ثبت کرد.']);
                }

                $rules = FinancialRule::query()->whereKey(1)->lockForUpdate()->firstOrFail();
                if (! $rules->configured()) {
                    throw ValidationException::withMessages(['financial_rules' => 'قواعد مالی تنظیم نشده‌اند؛ ثبت مدرک پرداخت مسدود است.']);
                }

                $installments = Installment::query()->where('contract_id', $lockedContract->id)->orderBy('id')->lockForUpdate()->get();
                $lockedInstallment = $installments->firstWhere('id', $installment->id);
                if ($lockedInstallment === null) {
                    abort(404);
                }

                $states = $this->stateLoader->load($lockedContract, $installments, $rules, now()->toDateString());
                $state = collect($states)->firstWhere('installment_id', (int) $lockedInstallment->id);
                if (! $state || ! $state['payment_allowed']) {
                    throw ValidationException::withMessages(['installment' => 'این قسط در وضعیت فعلی قابل پرداخت نیست.']);
                }
                if (bccomp((string) $input['amount_toman'], (string) $state['final_payable'], 0) > 0) {
                    throw ValidationException::withMessages(['amount_toman' => 'مبلغ مدرک از ماندهٔ قابل پرداخت قسط بیشتر است.']);
                }

                $pendingExists = PaymentReceipt::query()
                    ->where('installment_id', $lockedInstallment->id)
                    ->where('status', 'pending')
                    ->exists();
                if ($pendingExists) {
                    throw ValidationException::withMessages(['receipt' => 'برای این قسط یک مدرک در انتظار بررسی وجود دارد.']);
                }

                $receipt = PaymentReceipt::create([
                    'request_id' => $input['request_id'],
                    'request_hash' => $requestHash,
                    'customer_id' => $lockedContract->customer_id,
                    'contract_id' => $lockedContract->id,
                    'installment_id' => $lockedInstallment->id,
                    'amount_toman' => (string) $input['amount_toman'],
                    'file_disk' => $input['file_disk'],
                    'file_path' => $input['file_path'],
                    'file_mime' => $input['file_mime'],
                    'file_size' => (int) $input['file_size'],
                    'file_sha256' => $input['file_sha256'],
                    'status' => 'pending',
                    'submitted_by' => $actor->id,
                ]);

                AuditEvent::create([
                    'actor_id' => $actor->id,
                    'request_id' => $input['request_id'],
                    'event_name' => 'payment_receipt.submitted',
                    'subject_type' => PaymentReceipt::class,
                    'subject_id' => $receipt->id,
                    'metadata' => [
                        'customer_id' => $receipt->customer_id,
                        'contract_id' => $receipt->contract_id,
                        'installment_id' => $receipt->installment_id,
                        'amount_toman' => $receipt->amount_toman,
                        'file_mime' => $receipt->file_mime,
                        'file_size' => $receipt->file_size,
                    ],
                    'ip_address' => request()->ip(),
                    'created_at' => now(),
                ]);

                return $receipt;
            }, attempts: 3);
        } catch (QueryException $exception) {
            $existing = PaymentReceipt::query()->where('request_id', $input['request_id'])->first();
            if ($existing !== null) {
                $this->assertSameRequest($existing, $requestHash);

                return $existing;
            }

            throw $exception;
        }
    }

    private function assertSameRequest(PaymentReceipt $receipt, string $requestHash): void
    {
        if (! hash_equals($receipt->request_hash, $requestHash)) {
            throw ValidationException::withMessages(['request_id' => 'این شناسه قبلاً برای مدرک یا درخواست دیگری استفاده شده است.']);
        }
    }
}
