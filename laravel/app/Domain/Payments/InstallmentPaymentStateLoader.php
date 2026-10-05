<?php

namespace App\Domain\Payments;

use App\Models\Contract;
use App\Models\FinancialRule;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/** Replays captured allocation rows into the source-backed installment calculator. */
final class InstallmentPaymentStateLoader
{
    public function __construct(private readonly InstallmentFinancialStateCalculator $calculator) {}

    /** @return array<int, array<string, mixed>> */
    public function load(Contract $contract, Collection $installments, FinancialRule $rules, string $asOf): array
    {
        $installmentIds = $installments->modelKeys();
        $allocations = $installmentIds === [] ? collect() : DB::table('payment_allocations as allocations')
            ->join('payments', 'payments.id', '=', 'allocations.payment_id')
            ->whereIn('allocations.installment_id', $installmentIds)
            ->where('allocations.is_reversal', false)
            ->where('payments.status', 'paid')
            ->orderBy('payments.payment_date')
            ->orderBy('payments.id')
            ->lockForUpdate()
            ->get([
                'allocations.id',
                'allocations.installment_id',
                'allocations.allocated_amount_toman',
                'allocations.principal_applied_toman',
                'allocations.normal_penalty_applied_toman',
                'allocations.legal_penalty_applied_toman',
                'allocations.reward_applied_toman',
                'payments.id as payment_id',
                'payments.status as payment_status',
                'payments.payment_date',
                'payments.payment_type',
                'payments.method',
                'payments.provider_reference',
            ])
            ->groupBy('installment_id');

        $settings = $rules->calculatorSettings();
        $rows = [];

        foreach ($installments as $installment) {
            $history = ($allocations->get($installment->id) ?? collect())->map(function ($allocation): array {
                return [
                    'id' => (int) $allocation->payment_id,
                    'status' => $allocation->payment_status,
                    'payment_date' => $allocation->payment_date,
                    'payment_type' => $allocation->payment_type ?? 'installment',
                    'amount_toman' => (string) $allocation->allocated_amount_toman,
                    'principal_applied' => (string) $allocation->principal_applied_toman,
                    'normal_penalty_applied' => (string) $allocation->normal_penalty_applied_toman,
                    'legal_penalty_applied' => (string) $allocation->legal_penalty_applied_toman,
                    'reward_applied' => (string) $allocation->reward_applied_toman,
                    'is_corrected' => false,
                    'method' => $allocation->method,
                    'provider_reference' => $allocation->provider_reference,
                ];
            })->all();

            $input = [
                'id' => (int) $installment->id,
                'contract_id' => (int) $contract->id,
                'installment_number' => (int) $installment->installment_number,
                'due_date' => $installment->due_date->toDateString(),
                'base_amount' => (string) $installment->amount_toman,
                'amount_toman' => (string) $installment->amount_toman,
                'paid_toman' => (string) $installment->paid_toman,
                'status' => $installment->status,
                'contract_status' => $contract->status,
                'legal_started_at' => null,
                'inconsistent_legal_referral_count' => 0,
            ];
            $state = $this->calculator->state($input, $history, $settings, $asOf);
            $rows[] = $input + $state + ['payment_allowed' => $state['payment_allowed']];
        }

        return $rows;
    }
}
