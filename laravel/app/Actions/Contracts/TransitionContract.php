<?php

namespace App\Actions\Contracts;

use App\Enums\KycStatus;
use App\Models\AuditEvent;
use App\Models\Contract;
use App\Models\ContractStatusHistory;
use App\Models\CreditAccount;
use App\Models\CreditLedgerEntry;
use App\Models\Customer;
use App\Models\KycCase;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use JsonException;
use LogicException;

class TransitionContract
{
    /**
     * @throws JsonException
     */
    public function handle(int $contractId, string $transition, string $requestId, string $reason, User $actor): Contract
    {
        $definition = match ($transition) {
            'submit' => ['from' => 'draft', 'to' => 'pending_approval', 'event' => 'contract.submitted_for_approval'],
            'approve' => ['from' => 'pending_approval', 'to' => 'approved', 'event' => 'contract.approved'],
            'activate' => ['from' => 'approved', 'to' => 'active', 'event' => 'contract.activated'],
            default => throw new LogicException('Unsupported contract transition.'),
        };

        try {
            return DB::transaction(function () use ($contractId, $transition, $requestId, $reason, $actor, $definition): Contract {
                $contract = Contract::query()->whereKey($contractId)->lockForUpdate()->firstOrFail();
                $requestHash = $this->requestHash($contract, $transition, $reason, $actor);

                $existing = ContractStatusHistory::query()->where('request_id', $requestId)->first();
                if ($existing !== null) {
                    $this->assertSameRequest($existing, $requestHash);

                    return $contract;
                }

                if ($contract->status !== $definition['from']) {
                    throw ValidationException::withMessages(['status' => 'تغییر وضعیت قرارداد با وضعیت فعلی آن سازگار نیست.']);
                }

                $exposure = $this->financedAmount($contract);
                $metadata = ['financial_exposure_toman' => $exposure];

                if ($transition === 'submit') {
                    $this->assertLatestKycApproved($contract);
                    $this->reserveCredit($contract, $actor, $requestId, $requestHash, $reason, $exposure);
                } elseif ($transition === 'approve') {
                    if ((int) $contract->created_by === (int) $actor->id) {
                        throw ValidationException::withMessages(['status' => 'ثبت‌کنندهٔ قرارداد نمی‌تواند همان قرارداد را تأیید کند.']);
                    }
                    $this->consumeReservation($contract, $actor, $requestId, $requestHash, $reason, $exposure);
                } else {
                    $this->activateInstallments($contract);
                }

                $now = now();
                $contractUpdates = ['status' => $definition['to']];
                if ($transition === 'submit') {
                    $contractUpdates += ['submitted_by' => $actor->id, 'submitted_at' => $now];
                } elseif ($transition === 'approve') {
                    $contractUpdates += ['approved_by' => $actor->id, 'approved_at' => $now];
                } elseif ($transition === 'activate') {
                    $contractUpdates['activated_at'] = $now;
                }
                $contract->update($contractUpdates);

                ContractStatusHistory::create([
                    'contract_id' => $contract->id,
                    'actor_id' => $actor->id,
                    'request_id' => $requestId,
                    'request_hash' => $requestHash,
                    'from_status' => $definition['from'],
                    'to_status' => $definition['to'],
                    'reason' => $reason,
                    'metadata' => $metadata,
                    'created_at' => $now,
                ]);

                AuditEvent::create([
                    'actor_id' => $actor->id,
                    'request_id' => $requestId,
                    'event_name' => $definition['event'],
                    'subject_type' => Contract::class,
                    'subject_id' => $contract->id,
                    'metadata' => $metadata + ['from_status' => $definition['from'], 'to_status' => $definition['to']],
                    'ip_address' => request()->ip(),
                    'created_at' => $now,
                ]);

                return $contract->refresh();
            }, attempts: 3);
        } catch (QueryException $exception) {
            $existing = ContractStatusHistory::query()->where('request_id', $requestId)->first();
            if ($existing !== null) {
                $contract = Contract::query()->findOrFail($contractId);
                $this->assertSameRequest($existing, $this->requestHash($contract, $transition, $reason, $actor));

                return $contract;
            }

            throw $exception;
        }
    }

    private function assertLatestKycApproved(Contract $contract): void
    {
        $customer = Customer::query()->whereKey($contract->customer_id)->lockForUpdate()->firstOrFail();
        if ($customer->status !== 'active') {
            throw ValidationException::withMessages(['status' => 'برای مشتری غیرفعال نمی‌توان قرارداد را به مرحلهٔ تأیید فرستاد.']);
        }

        $latestKyc = KycCase::query()->where('customer_id', $customer->id)->latest('id')->first();
        if ($latestKyc?->status !== KycStatus::Approved) {
            throw ValidationException::withMessages(['status' => 'آخرین پروندهٔ احراز هویت مشتری باید تأییدشده باشد.']);
        }
    }

    private function reserveCredit(Contract $contract, User $actor, string $requestId, string $requestHash, string $reason, string $amount): void
    {
        if (bccomp($amount, '0', 0) === 0) {
            return;
        }

        $account = CreditAccount::query()->where('customer_id', $contract->customer_id)->lockForUpdate()->first();
        if ($account === null || $account->status !== 'active' || ($account->expires_at !== null && $account->expires_at->lessThanOrEqualTo(now()))) {
            throw ValidationException::withMessages(['credit_account' => 'حساب اعتبار مشتری فعال یا معتبر نیست.']);
        }

        $available = $account->availableToman();
        if (bccomp($available, '0', 0) < 0 || bccomp($available, $amount, 0) < 0) {
            throw ValidationException::withMessages(['credit_account' => 'اعتبار آزاد مشتری برای این قرارداد کافی نیست.']);
        }

        $account->update(['reserved_toman' => bcadd((string) $account->reserved_toman, $amount, 0)]);
        $this->recordCreditEntry($contract, $account, $actor, $requestId, $requestHash, 'contract_reservation', $amount, '0', $amount, $reason);
    }

    private function consumeReservation(Contract $contract, User $actor, string $requestId, string $requestHash, string $reason, string $amount): void
    {
        if (bccomp($amount, '0', 0) === 0) {
            return;
        }

        $account = CreditAccount::query()->where('customer_id', $contract->customer_id)->lockForUpdate()->first();
        if ($account === null) {
            throw ValidationException::withMessages(['credit_account' => 'حساب اعتبار مشتری پیدا نشد.']);
        }

        $reservedForContract = (string) DB::table('credit_ledger_entries')
            ->where('contract_id', $contract->id)
            ->sum('reserved_delta_toman');
        if (bccomp($reservedForContract, $amount, 0) !== 0
            || bccomp((string) $account->reserved_toman, $amount, 0) < 0) {
            throw ValidationException::withMessages(['credit_account' => 'رزرو اعتبار قرارداد با دفترکل و ماندهٔ حساب سازگار نیست.']);
        }

        $account->update([
            'reserved_toman' => bcsub((string) $account->reserved_toman, $amount, 0),
            'used_toman' => bcadd((string) $account->used_toman, $amount, 0),
        ]);
        $this->recordCreditEntry($contract, $account, $actor, $requestId, $requestHash, 'contract_reservation_consumed', $amount, $amount, '-'.$amount, $reason);
    }

    private function recordCreditEntry(Contract $contract, CreditAccount $account, User $actor, string $requestId, string $requestHash, string $type, string $amount, string $usedDelta, string $reservedDelta, string $reason): void
    {
        CreditLedgerEntry::create([
            'credit_account_id' => $account->id,
            'contract_id' => $contract->id,
            'actor_id' => $actor->id,
            'request_id' => $requestId,
            'request_hash' => $requestHash,
            'entry_type' => $type,
            'amount_toman' => $amount,
            'old_limit_toman' => (string) $account->limit_toman,
            'new_limit_toman' => (string) $account->limit_toman,
            'used_delta_toman' => $usedDelta,
            'reserved_delta_toman' => $reservedDelta,
            'reason' => $reason,
            'created_at' => now(),
        ]);
    }

    private function activateInstallments(Contract $contract): void
    {
        $installments = DB::table('installments')->where('contract_id', $contract->id)->where('status', 'draft');
        if ((int) $installments->count() !== (int) $contract->installment_count) {
            throw ValidationException::withMessages(['status' => 'برنامهٔ اقساط پیش‌نویس کامل نیست و قرارداد فعال نشد.']);
        }

        $today = now()->toDateString();
        DB::table('installments')->where('contract_id', $contract->id)->where('status', 'draft')->where('due_date', '<', $today)
            ->update(['status' => 'overdue', 'updated_at' => now()]);
        DB::table('installments')->where('contract_id', $contract->id)->where('status', 'draft')->where('due_date', '>=', $today)
            ->update(['status' => 'pending', 'updated_at' => now()]);
    }

    private function financedAmount(Contract $contract): string
    {
        if (bccomp((string) $contract->principal_toman, (string) $contract->down_payment_toman, 0) < 0) {
            throw ValidationException::withMessages(['status' => 'پیش‌پرداخت قرارداد از اصل آن بیشتر است.']);
        }

        return bcsub((string) $contract->principal_toman, (string) $contract->down_payment_toman, 0);
    }

    private function requestHash(Contract $contract, string $transition, string $reason, User $actor): string
    {
        return hash('sha256', json_encode([
            'contract_id' => (int) $contract->id,
            'transition' => $transition,
            'reason' => $reason,
            'actor_id' => (int) $actor->id,
            'pricing_snapshot_hash' => (string) $contract->pricing_snapshot_hash,
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));
    }

    private function assertSameRequest(ContractStatusHistory $history, string $requestHash): void
    {
        if (! hash_equals($history->request_hash, $requestHash)) {
            throw ValidationException::withMessages(['request_id' => 'این شناسهٔ درخواست قبلاً با اطلاعات دیگری استفاده شده است.']);
        }
    }
}
