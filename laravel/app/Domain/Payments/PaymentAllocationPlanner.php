<?php

namespace App\Domain\Payments;

use DomainException;

/**
 * Deterministic port of the Legacy payment-allocation-v2 ordering and split.
 * Input rows must already contain a canonical financial state; this class does
 * not calculate penalties or accept display/materialized balances as truth.
 */
final class PaymentAllocationPlanner
{
    public const ALLOCATION_VERSION = 'payment-allocation-v2';

    /** @param array<int, array<string, mixed>> $installments */
    public function quote(array $installments): array
    {
        $rows = array_values(array_filter($installments, static fn (array $row): bool => ! empty($row['payment_allowed'])));
        $this->sortRows($rows);

        $principal = 0;
        $normalPenalty = 0;
        $legalPenalty = 0;
        $reward = 0;
        $total = 0;

        foreach ($rows as $row) {
            $principal = $this->add($principal, $this->money($row['remaining_principal'] ?? $row['remaining_amount'] ?? 0));
            $normalPenalty = $this->add($normalPenalty, $this->money($row['normal_penalty'] ?? 0));
            $legalPenalty = $this->add($legalPenalty, $this->money($row['legal_penalty'] ?? 0));
            $reward = $this->add($reward, $this->money($row['eligible_reward'] ?? $row['reward'] ?? 0));
            $total = $this->add($total, $this->money($row['final_payable'] ?? $row['payable'] ?? 0));
        }

        return [
            'calculation_version' => self::ALLOCATION_VERSION,
            'rows' => $rows,
            'selected_count' => count($rows),
            'principal_total' => $principal,
            'normal_penalty_total' => $normalPenalty,
            'legal_penalty_total' => $legalPenalty,
            'reward_total' => $reward,
            'full_settlement_total' => $total,
        ];
    }

    /** @param array<int, array<string, mixed>> $installments */
    public function plan(array $installments, int|string $amount): array
    {
        $quote = $this->quote($installments);
        $amount = $this->money($amount);
        if ($amount <= 0) {
            throw new DomainException('مبلغ پرداخت باید بیشتر از صفر باشد.');
        }
        if ($amount > $quote['full_settlement_total']) {
            throw new DomainException('مبلغ پرداخت از مبلغ تسویهٔ اقساط انتخاب‌شده بیشتر است.');
        }

        $remaining = $amount;
        $allocations = [];
        $principalApplied = 0;
        $normalPenaltyApplied = 0;
        $legalPenaltyApplied = 0;
        $rewardApplied = 0;

        foreach ($quote['rows'] as $row) {
            $fullPayable = $this->money($row['final_payable'] ?? $row['payable'] ?? 0);
            $principalBefore = $this->money($row['remaining_principal'] ?? $row['remaining_amount'] ?? 0);
            $normalBefore = $this->money($row['normal_penalty'] ?? 0);
            $legalBefore = $this->money($row['legal_penalty'] ?? 0);
            $eligibleReward = $this->money($row['eligible_reward'] ?? $row['reward'] ?? 0);

            $allocated = 0;
            $principal = 0;
            $normal = 0;
            $legal = 0;
            $reward = 0;
            $fullSettlement = false;

            if ($remaining > 0 && $fullPayable > 0) {
                if ($remaining >= $fullPayable) {
                    $allocated = $fullPayable;
                    $principal = $principalBefore;
                    $normal = $normalBefore;
                    $legal = $legalBefore;
                    $reward = min($eligibleReward, $this->add($this->add($principal, $normal), $legal));
                    $fullSettlement = true;
                } else {
                    $allocated = $remaining;
                    $legal = min($legalBefore, $allocated);
                    $normal = min($normalBefore, $allocated - $legal);
                    $principal = min($principalBefore, $allocated - $legal - $normal);
                }
                $remaining -= $allocated;
            }

            $principalAfter = max(0, $principalBefore - $principal);
            $normalAfter = max(0, $normalBefore - $normal);
            $legalAfter = max(0, $legalBefore - $legal);
            $statusAfter = $principalAfter === 0 && $normalAfter === 0 && $legalAfter === 0
                ? 'paid'
                : ($principalAfter < $this->money($row['original_principal'] ?? $row['base_amount'] ?? 0)
                    ? 'partial'
                    : (string) ($row['status'] ?? 'pending'));

            $allocations[] = [
                'installment_id' => (int) ($row['id'] ?? 0),
                'contract_id' => (int) ($row['contract_id'] ?? 0),
                'installment_number' => (int) ($row['installment_number'] ?? 0),
                'due_date' => $row['due_date'] ?? null,
                'remaining_before' => $principalBefore,
                'normal_penalty_before' => $normalBefore,
                'legal_penalty_before' => $legalBefore,
                'reward_eligible' => $eligibleReward,
                'allocated_amount' => $allocated,
                'principal_applied' => $principal,
                'normal_penalty_applied' => $normal,
                'legal_penalty_applied' => $legal,
                'reward_applied' => $reward,
                'remaining_after' => $principalAfter,
                'normal_penalty_after' => $normalAfter,
                'legal_penalty_after' => $legalAfter,
                'final_status' => $statusAfter,
                'is_full_settlement' => $fullSettlement,
            ];

            $principalApplied += $principal;
            $normalPenaltyApplied += $normal;
            $legalPenaltyApplied += $legal;
            $rewardApplied += $reward;
        }

        if ($remaining !== 0) {
            throw new DomainException('مبلغ پرداخت در دامنهٔ انتخاب‌شده به‌طور کامل تخصیص پیدا نکرد.');
        }

        return $quote + [
            'allocations' => $allocations,
            'received_amount' => $amount,
            'allocated_amount' => $amount,
            'unused_amount' => 0,
            'principal_applied_total' => $principalApplied,
            'normal_penalty_applied_total' => $normalPenaltyApplied,
            'legal_penalty_applied_total' => $legalPenaltyApplied,
            'reward_applied_total' => $rewardApplied,
        ];
    }

    /** @param array<int, array<string, mixed>> $rows */
    private function sortRows(array &$rows): void
    {
        usort($rows, static function (array $left, array $right): int {
            $priority = ((int) ($left['financial_priority'] ?? 99)) <=> ((int) ($right['financial_priority'] ?? 99));
            if ($priority !== 0) {
                return $priority;
            }

            $dueDate = strcmp((string) ($left['due_date'] ?? ''), (string) ($right['due_date'] ?? ''));
            if ($dueDate !== 0) {
                return $dueDate;
            }

            $number = ((int) ($left['installment_number'] ?? 0)) <=> ((int) ($right['installment_number'] ?? 0));
            if ($number !== 0) {
                return $number;
            }

            return ((int) ($left['id'] ?? 0)) <=> ((int) ($right['id'] ?? 0));
        });
    }

    private function money(int|string $value): int
    {
        $value = (string) $value;
        if (! preg_match('/^(0|[1-9]\d{0,18})$/', $value)
            || strlen($value) > strlen((string) PHP_INT_MAX)
            || (strlen($value) === strlen((string) PHP_INT_MAX) && strcmp($value, (string) PHP_INT_MAX) > 0)) {
            throw new DomainException('مبلغ مالی خارج از محدودهٔ محاسبه است.');
        }

        return (int) $value;
    }

    private function add(int $left, int $right): int
    {
        if ($left > PHP_INT_MAX - $right) {
            throw new DomainException('مجموع مبلغ مالی خارج از محدودهٔ محاسبه است.');
        }

        return $left + $right;
    }
}
