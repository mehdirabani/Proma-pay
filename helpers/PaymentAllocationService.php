<?php

/**
 * Deterministic allocation of one received amount across a quoted selection.
 * Order is independent of checkbox order and is shared by manual and gateway
 * paths: legal overdue, normal overdue, oldest due date, installment number,
 * then primary key. Within an installment, legal penalty, normal penalty and
 * principal are paid in that order. A reward is used only for a full
 * settlement of that installment in the current transaction.
 */
final class PaymentAllocationService
{
    public const ALLOCATION_VERSION = 'payment-allocation-v2';

    public static function quote(array $installments, $asOf = null, ?array $settings = null)
    {
        $states = InstallmentFinancialStateService::statesForRows($installments, $settings, $asOf);
        $rows = [];
        foreach ($installments as $installment) {
            $state = $states[(int) ($installment['id'] ?? 0)] ?? null;
            if ($state && !empty($state['payment_allowed'])) {
                $rows[] = array_merge($installment, $state);
            }
        }
        self::sortRows($rows);
        if (!$rows) {
            return [
                'calculation_version' => self::ALLOCATION_VERSION, 'rows' => [], 'allocations' => [], 'selected_count' => 0,
                'principal_total' => 0, 'normal_penalty_total' => 0, 'legal_penalty_total' => 0, 'reward_total' => 0,
                'full_settlement_total' => 0, 'received_amount' => 0, 'allocated_amount' => 0, 'unused_amount' => 0,
                'principal_applied_total' => 0, 'normal_penalty_applied_total' => 0, 'legal_penalty_applied_total' => 0, 'reward_applied_total' => 0,
            ];
        }
        return self::build($rows, null);
    }

    public static function plan(array $installments, $amount, $asOf = null, ?array $settings = null)
    {
        $quote = self::quote($installments, $asOf, $settings);
        return self::build($quote['rows'], normalize_money($amount), $quote);
    }

    public static function build(array $rows, $amount = null, ?array $quote = null)
    {
        self::sortRows($rows);
        $fullTotal = 0;
        foreach ($rows as $row) {
            $fullTotal += normalize_money($row['final_payable'] ?? $row['payable'] ?? 0);
        }
        $amount = $amount === null ? $fullTotal : normalize_money($amount);
        if ($amount <= 0) {
            throw new InvalidArgumentException('مبلغ پرداخت باید بیشتر از صفر باشد.');
        }
        if ($amount > $fullTotal) {
            throw new InvalidArgumentException('مبلغ پرداخت از مبلغ تسویهٔ اقساط انتخاب‌شده بیشتر است.');
        }

        $remaining = $amount;
        $allocations = [];
        $principalTotal = 0;
        $normalPenaltyTotal = 0;
        $legalPenaltyTotal = 0;
        $rewardTotal = 0;
        foreach ($rows as $row) {
            $fullPayable = normalize_money($row['final_payable'] ?? $row['payable'] ?? 0);
            $principalBefore = normalize_money($row['remaining_principal'] ?? $row['remaining_amount'] ?? 0);
            $normalBefore = normalize_money($row['normal_penalty'] ?? 0);
            $legalBefore = normalize_money($row['legal_penalty'] ?? 0);
            $reward = normalize_money($row['eligible_reward'] ?? $row['reward'] ?? 0);
            $allocated = 0;
            $principal = 0;
            $normal = 0;
            $legal = 0;
            $appliedReward = 0;
            $isFull = false;
            if ($remaining > 0 && $fullPayable > 0) {
                if ($remaining >= $fullPayable) {
                    $allocated = $fullPayable;
                    $principal = $principalBefore;
                    $normal = $normalBefore;
                    $legal = $legalBefore;
                    $appliedReward = min($reward, $principal + $normal + $legal);
                    $isFull = true;
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
            $statusAfter = ($principalAfter === 0 && $normalAfter === 0 && $legalAfter === 0) ? 'paid'
                : ($principalAfter < normalize_money($row['original_principal'] ?? $row['base_amount'] ?? 0) ? 'partial' : ($row['status'] ?? 'pending'));
            $allocations[] = [
                'installment_id' => (int) $row['id'],
                'contract_id' => (int) $row['contract_id'],
                'installment_number' => (int) ($row['installment_number'] ?? 0),
                'due_date' => $row['due_date'] ?? null,
                'remaining_before' => $principalBefore,
                'normal_penalty_before' => $normalBefore,
                'legal_penalty_before' => $legalBefore,
                'reward_eligible' => $reward,
                'allocated_amount' => $allocated,
                'principal_applied' => $principal,
                'normal_penalty_applied' => $normal,
                'legal_penalty_applied' => $legal,
                'reward_applied' => $appliedReward,
                'remaining_after' => $principalAfter,
                'normal_penalty_after' => $normalAfter,
                'legal_penalty_after' => $legalAfter,
                'final_status' => $statusAfter,
                'is_full_settlement' => $isFull,
            ];
            $principalTotal += $principal;
            $normalPenaltyTotal += $normal;
            $legalPenaltyTotal += $legal;
            $rewardTotal += $appliedReward;
        }
        if ($remaining !== 0) {
            throw new RuntimeException('مبلغ پرداخت در دامنهٔ انتخاب‌شده به‌طور کامل تخصیص پیدا نکرد.');
        }

        return [
            'calculation_version' => self::ALLOCATION_VERSION,
            'rows' => $rows,
            'allocations' => $allocations,
            'selected_count' => count($rows),
            'principal_total' => $quote['principal_total'] ?? array_sum(array_map(static function ($row) { return normalize_money($row['remaining_principal'] ?? $row['remaining_amount'] ?? 0); }, $rows)),
            'normal_penalty_total' => $quote['normal_penalty_total'] ?? array_sum(array_map(static function ($row) { return normalize_money($row['normal_penalty'] ?? 0); }, $rows)),
            'legal_penalty_total' => $quote['legal_penalty_total'] ?? array_sum(array_map(static function ($row) { return normalize_money($row['legal_penalty'] ?? 0); }, $rows)),
            'reward_total' => $quote['reward_total'] ?? array_sum(array_map(static function ($row) { return normalize_money($row['eligible_reward'] ?? $row['reward'] ?? 0); }, $rows)),
            'full_settlement_total' => $fullTotal,
            'received_amount' => $amount,
            'allocated_amount' => $amount,
            'unused_amount' => 0,
            'principal_applied_total' => $principalTotal,
            'normal_penalty_applied_total' => $normalPenaltyTotal,
            'legal_penalty_applied_total' => $legalPenaltyTotal,
            'reward_applied_total' => $rewardTotal,
        ];
    }

    public static function sortRows(array &$rows)
    {
        usort($rows, static function ($left, $right) {
            $leftPriority = (int) ($left['financial_priority'] ?? 99);
            $rightPriority = (int) ($right['financial_priority'] ?? 99);
            if ($leftPriority !== $rightPriority) return $leftPriority <=> $rightPriority;
            $due = strcmp((string) ($left['due_date'] ?? ''), (string) ($right['due_date'] ?? ''));
            if ($due !== 0) return $due;
            $number = (int) ($left['installment_number'] ?? 0) <=> (int) ($right['installment_number'] ?? 0);
            return $number !== 0 ? $number : ((int) ($left['id'] ?? 0) <=> (int) ($right['id'] ?? 0));
        });
    }
}
