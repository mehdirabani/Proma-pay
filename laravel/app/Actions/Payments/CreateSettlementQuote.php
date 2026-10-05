<?php

namespace App\Actions\Payments;

use App\Domain\Payments\InstallmentPaymentStateLoader;
use App\Domain\Payments\SettlementQuotePlanner;
use App\Domain\Payments\SettlementQuoteSnapshot;
use App\Models\AuditEvent;
use App\Models\Contract;
use App\Models\FinancialRule;
use App\Models\Installment;
use App\Models\SettlementQuote;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CreateSettlementQuote
{
    public function __construct(
        private readonly InstallmentPaymentStateLoader $stateLoader,
        private readonly SettlementQuotePlanner $planner,
        private readonly SettlementQuoteSnapshot $snapshotter,
    ) {}

    public function handle(Contract $contract, array $input, User $actor): SettlementQuote
    {
        $requestedIds = array_values(array_unique(array_map('intval', $input['installment_ids'] ?? [])));
        sort($requestedIds);
        $scope = (string) $input['scope'];
        $requestHash = hash('sha256', json_encode([
            'actor_id' => (int) $actor->id,
            'contract_id' => (int) $contract->id,
            'scope' => $scope,
            'installment_ids' => $requestedIds,
            'amount_toman' => (string) $input['amount_toman'],
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));

        try {
            return DB::transaction(function () use ($contract, $input, $actor, $requestedIds, $scope, $requestHash): SettlementQuote {
                $existing = SettlementQuote::query()->where('request_id', $input['request_id'])->first();
                if ($existing !== null) {
                    $this->assertSameRequest($existing, $requestHash);

                    return $existing;
                }

                $lockedContract = Contract::query()->whereKey($contract->id)->lockForUpdate()->firstOrFail();
                $this->assertPayableContract($lockedContract);
                $rules = FinancialRule::query()->whereKey(1)->lockForUpdate()->firstOrFail();
                if (! $rules->configured()) {
                    throw ValidationException::withMessages(['financial_rules' => 'قواعد مالی هنوز تنظیم نشده است؛ محاسبهٔ تسویه تا ثبت سیاست مصوب مسدود است.']);
                }

                $installments = Installment::query()->where('contract_id', $lockedContract->id)->orderBy('id')->lockForUpdate()->get();
                $asOf = now()->toDateString();
                $states = $this->stateLoader->load($lockedContract, $installments, $rules, $asOf);
                $calculation = $this->planner->plan($states, $requestedIds, $scope, $input['amount_toman']);
                $ruleSnapshot = $rules->snapshot();
                $quoteSnapshot = $this->snapshotter->create($lockedContract, (int) $actor->id, $scope, $requestedIds, $calculation, $ruleSnapshot, (int) $input['amount_toman'], $asOf);
                $now = now();
                $ttl = max(60, min(1800, (int) config('proma.settlement_quote_ttl_seconds', 300)));

                $quote = SettlementQuote::create([
                    'quote_uuid' => (string) Str::uuid(),
                    'request_id' => $input['request_id'],
                    'request_hash' => $requestHash,
                    'actor_id' => $actor->id,
                    'customer_id' => $lockedContract->customer_id,
                    'contract_id' => $lockedContract->id,
                    'scope' => $scope,
                    'requested_installment_ids' => $requestedIds,
                    'installment_ids' => $calculation['installment_ids'],
                    'amount_toman' => (string) $input['amount_toman'],
                    'full_settlement_toman' => (string) $calculation['quote']['full_settlement_total'],
                    'calculation_date' => $asOf,
                    'calculation_version' => $calculation['calculation_version'],
                    'financial_rules_version' => $rules->version,
                    'financial_rules_snapshot' => $ruleSnapshot,
                    'plan_snapshot' => $quoteSnapshot['snapshot'],
                    'snapshot_hash' => $quoteSnapshot['hash'],
                    'expires_at' => $now->copy()->addSeconds($ttl),
                    'created_at' => $now,
                ]);

                AuditEvent::create([
                    'actor_id' => $actor->id,
                    'request_id' => $input['request_id'],
                    'event_name' => 'payment.settlement_quoted',
                    'subject_type' => SettlementQuote::class,
                    'subject_id' => $quote->id,
                    'metadata' => [
                        'contract_id' => $lockedContract->id,
                        'amount_toman' => $quote->amount_toman,
                        'scope' => $scope,
                        'installment_ids' => $quote->installment_ids,
                        'calculation_version' => $quote->calculation_version,
                        'financial_rules_version' => $quote->financial_rules_version,
                    ],
                    'ip_address' => request()->ip(),
                    'created_at' => $now,
                ]);

                return $quote;
            }, attempts: 3);
        } catch (QueryException $exception) {
            $existing = SettlementQuote::query()->where('request_id', $input['request_id'])->first();
            if ($existing !== null) {
                $this->assertSameRequest($existing, $requestHash);

                return $existing;
            }

            throw $exception;
        }
    }

    private function assertPayableContract(Contract $contract): void
    {
        if ($contract->status !== 'active') {
            throw ValidationException::withMessages(['contract_id' => 'فقط قرارداد فعال برای دریافت قسط قابل محاسبه است.']);
        }
    }

    private function assertSameRequest(SettlementQuote $quote, string $requestHash): void
    {
        if (! hash_equals($quote->request_hash, $requestHash)) {
            throw ValidationException::withMessages(['request_id' => 'این شناسهٔ محاسبه قبلاً با مبلغ یا اقساط دیگری استفاده شده است.']);
        }
    }
}
