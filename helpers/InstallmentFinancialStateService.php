<?php

require_once __DIR__ . '/PenaltyComparisonService.php';

/**
 * Canonical, integer-only state of an installment at a point in time.
 *
 * This service is intentionally the only place that derives outstanding
 * principal, penalties, early-settlement reward and payment eligibility.
 * Existing payment rows created before the settlement-engine migration are
 * reconstructed from their persisted remaining balance; newer rows use their
 * explicit allocation breakdown.
 */
final class InstallmentFinancialStateService
{
    public const CALCULATION_VERSION = 'installment-financial-state-v3';

    public static function state(array $installment, ?array $payments = null, ?array $settings = null, $asOf = null)
    {
        $settings = $settings ?? Settings::allKeyed();
        $asOf = self::date($asOf ?: date('Y-m-d'));
        $payments = $payments ?? Payment::forInstallment((int) ($installment['id'] ?? 0));
        $base = normalize_money($installment['base_amount'] ?? 0);
        $id = (int) ($installment['id'] ?? 0);
        $contractId = (int) ($installment['contract_id'] ?? 0);

        if (($installment['status'] ?? '') === 'cancelled' || ($installment['contract_status'] ?? '') === 'cancelled') {
            return self::closedState($id, $contractId, $installment, $base, $asOf, 'cancelled');
        }

        $normalRate = MoneyMath::rateUnits($settings['monthly_penalty_rate'] ?? 0);
        // A missing/zero legal rate is never silently replaced with the normal
        // rate.  This prevents an incomplete setting from creating a debt.
        $legalRate = MoneyMath::rateUnits($settings['legal_monthly_penalty_rate'] ?? 0);
        $graceDays = max(0, min(365, (int) to_english_digits($settings['late_penalty_grace_days'] ?? 0)));
        $dueDate = self::date($installment['due_date'] ?? $asOf);
        $graceEnd = self::addDays($dueDate, $graceDays);
        $legalStartedAt = self::legalStartedAt($installment);
        $calculationStatus = 'calculated';
        $calculationWarnings = [];
        // A formal case without its persisted activation date is not a zero
        // legal penalty.  It is an inconsistent legal state which must be
        // repaired by an authorised user before accepting a payment quote.
        if ((int) ($installment['inconsistent_legal_referral_count'] ?? 0) > 0) {
            $calculationStatus = 'inconsistent_referral';
            $calculationWarnings[] = 'برای پرونده حقوقی فعال، تاریخ ارجاع رسمی ثبت نشده است.';
        } elseif ($legalStartedAt && $asOf >= $legalStartedAt && $base > 0 && $legalRate <= 0) {
            $calculationStatus = 'configuration_missing';
            $calculationWarnings[] = 'نرخ جریمه حقوقی برای ارجاع رسمی تنظیم نشده است.';
        }

        usort($payments, static function ($left, $right) {
            $leftDate = (string) ($left['payment_date'] ?? $left['paid_at'] ?? $left['created_at'] ?? '');
            $rightDate = (string) ($right['payment_date'] ?? $right['paid_at'] ?? $right['created_at'] ?? '');
            $compare = strcmp($leftDate, $rightDate);
            return $compare !== 0 ? $compare : ((int) ($left['id'] ?? 0) <=> (int) ($right['id'] ?? 0));
        });

        $remainingPrincipal = $base;
        // Manual adjustments do not have a separate dated ledger in legacy
        // data. Treat them as an approved due penalty so every new payment
        // uses the documented penalty-before-principal rule.
        $normalPenalty = normalize_money($installment['manual_penalty_adjustment'] ?? 0);
        $legalPenalty = 0;
        $discount = normalize_money($installment['penalty_discount_amount'] ?? 0);
        if ($discount > 0) {
            $normalPenalty = max(0, $normalPenalty - $discount);
        }
        $penaltyCursor = $dueDate;
        $settlementDate = null;
        $effectivePayments = [];
        foreach ($payments as $payment) {
            if (($payment['status'] ?? '') !== 'paid'
                || (int) ($payment['is_corrected'] ?? 0) === 1
                || ($payment['payment_type'] ?? 'installment') === 'down_payment') {
                continue;
            }
            $paymentDate = self::date(substr((string) ($payment['payment_date'] ?? $payment['paid_at'] ?? $payment['created_at'] ?? $asOf), 0, 10));
            if ($paymentDate > $asOf) {
                continue;
            }
            self::accrueTo($normalPenalty, $legalPenalty, $remainingPrincipal, $penaltyCursor, $paymentDate, $dueDate, $graceEnd, $normalRate, $legalRate, $legalStartedAt);
            $allocation = self::paymentAllocation($payment, $remainingPrincipal, $normalPenalty, $legalPenalty);
            $legalPenalty = max(0, $legalPenalty - $allocation['legal_penalty_applied']);
            $normalPenalty = max(0, $normalPenalty - $allocation['normal_penalty_applied']);
            $remainingPrincipal = max(0, $remainingPrincipal - $allocation['principal_applied']);
            // A payment inside grace reduces the future penalty base but does
            // not move the charge origin away from the original due date.
            if ($paymentDate > $graceEnd) {
                $penaltyCursor = max($penaltyCursor, $paymentDate);
            }
            $effectivePayments[] = $payment;
            if ($remainingPrincipal === 0 && $normalPenalty === 0 && $legalPenalty === 0) {
                $settlementDate = $paymentDate;
            }
        }

        self::accrueTo($normalPenalty, $legalPenalty, $remainingPrincipal, $penaltyCursor, $asOf, $dueDate, $graceEnd, $normalRate, $legalRate, $legalStartedAt);
        $reward = self::eligibleReward($installment, $remainingPrincipal, $settings, $asOf);
        $comparison = PenaltyComparisonService::projectedLegalPenalty(
            $installment,
            $effectivePayments,
            $settings,
            $asOf,
            $legalStartedAt
        );
        $totalPenalty = $normalPenalty + $legalPenalty;
        $finalPayable = max(0, $remainingPrincipal + $totalPenalty - $reward);
        $paidPrincipal = max(0, $base - $remainingPrincipal);
        $isPaid = $remainingPrincipal === 0 && $totalPenalty === 0;
        $lastEffectivePayment = $effectivePayments ? $effectivePayments[count($effectivePayments) - 1] : null;
        $status = $isPaid ? 'paid' : self::openStatus($installment, $remainingPrincipal, $dueDate, $asOf);
        $paymentAllowed = $calculationStatus === 'calculated' && !$isPaid
            && $finalPayable > 0
            // `completed` is a materialised contract status. A historical
            // partial payment can leave it stale, so it must never prevent a
            // positive canonical installment balance from being settled.
            && !in_array((string) ($installment['contract_status'] ?? ''), ['cancelled', 'closed'], true);

        return [
            'installment_id' => $id,
            'contract_id' => $contractId,
            'status' => $status,
            'financial_status' => self::financialStatus($installment, $remainingPrincipal, $dueDate, $asOf, $graceEnd, $legalStartedAt),
            'financial_priority' => self::priority($installment, $remainingPrincipal, $dueDate, $asOf, $graceEnd, $legalStartedAt, $isPaid),
            'due_date' => $dueDate,
            'original_principal' => $base,
            'base_amount' => $base,
            'effective_paid_principal' => $paidPrincipal,
            'paid_amount' => $paidPrincipal,
            'remaining_principal' => $remainingPrincipal,
            'remaining_amount' => $remainingPrincipal,
            'normal_penalty' => $normalPenalty,
            'legal_penalty' => $legalPenalty,
            'total_penalty' => $totalPenalty,
            'penalty' => $totalPenalty,
            // These explicit names make the distinction safe for every API
            // consumer: only effective_penalty_payable participates in debt.
            'normal_penalty_accrued' => $normalPenalty,
            'legal_penalty_accrued' => $legalPenalty,
            'effective_penalty_payable' => $totalPenalty,
            'projected_legal_penalty' => normalize_money($comparison['projected_legal_penalty'] ?? 0),
            'projected_legal_penalty_available' => !empty($comparison['projected_legal_penalty_available']),
            'projected_legal_penalty_rate' => $comparison['projected_legal_penalty_rate'] ?? MoneyMath::rateLabel($legalRate),
            'projected_legal_penalty_reason' => $comparison['projected_legal_penalty_reason'] ?? 'not_available',
            'penalty_mode' => ($legalPenalty > 0 || ($legalStartedAt && $asOf >= $legalStartedAt)) ? 'legal' : 'normal',
            'eligible_reward' => $reward,
            'reward' => $reward,
            'reward_applied' => 0,
            'effective_settlement_date' => $settlementDate ?: self::storedSettlementDate($installment, $isPaid),
            'last_payment_id' => $lastEffectivePayment ? (int) ($lastEffectivePayment['id'] ?? 0) : null,
            'last_payment_method' => $lastEffectivePayment['method'] ?? null,
            'last_payment_group_id' => $lastEffectivePayment ? (int) ($lastEffectivePayment['payment_group_id'] ?? 0) : null,
            'last_payment_reference' => $lastEffectivePayment['gateway_ref_id'] ?? ($lastEffectivePayment['gateway_track_id'] ?? null),
            'final_payable' => $finalPayable,
            'payable' => $finalPayable,
            'payment_allowed' => $paymentAllowed,
            'future_penalty' => $isPaid ? 0 : 1,
            'calculated_at' => $asOf,
            'calculation_version' => self::CALCULATION_VERSION,
            'calculation_status' => $calculationStatus,
            'calculation_warnings' => $calculationWarnings,
            'grace_days' => $graceDays,
            'legal_started_at' => $legalStartedAt,
            'canonical_legal_referral_at' => $legalStartedAt,
            'normal_penalty_rate' => MoneyMath::rateLabel($normalRate),
            'legal_penalty_rate' => MoneyMath::rateLabel($legalRate),
            'penalty_rate' => MoneyMath::rateLabel(($legalPenalty > 0 || ($legalStartedAt && $asOf >= $legalStartedAt)) ? $legalRate : $normalRate),
            'penalty_start_date' => $graceEnd,
            'overdue_days' => max(0, self::daysBetween($dueDate, $asOf)),
            'effective_payment_count' => count($effectivePayments),
        ];
    }

    public static function statesForRows(array $installments, ?array $settings = null, $asOf = null)
    {
        if (!$installments) {
            return [];
        }
        $ids = array_values(array_unique(array_filter(array_map(static function ($row) {
            return (int) (is_array($row) ? ($row['id'] ?? 0) : $row);
        }, $installments))));
        $paymentsByInstallment = [];
        $legalFactsByContract = [];
        $contractIds = array_values(array_unique(array_filter(array_map(static function ($row) {
            return (int) (is_array($row) ? ($row['contract_id'] ?? 0) : 0);
        }, $installments))));
        if ($contractIds) {
            $contractPlaceholders = implode(',', array_fill(0, count($contractIds), '?'));
            $legalFacts = Model::fetchAll(
                "SELECT contract_id,
                        MIN(legal_referred_at) AS legal_referred_at,
                        SUM(CASE WHEN legal_referred_at IS NULL AND status IN ('referred', 'external_submission_confirmed') THEN 1 ELSE 0 END) AS inconsistent_referral_count
                   FROM legal_cases
                  WHERE contract_id IN ({$contractPlaceholders})
                  GROUP BY contract_id",
                $contractIds
            );
            foreach ($legalFacts as $fact) {
                $legalFactsByContract[(int) $fact['contract_id']] = $fact;
            }
        }
        if ($ids) {
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $payments = Model::fetchAll(
                "SELECT * FROM payments WHERE installment_id IN ({$placeholders}) AND status = 'paid' AND COALESCE(is_corrected, 0) = 0 ORDER BY installment_id ASC, COALESCE(payment_date, paid_at, created_at) ASC, id ASC",
                $ids
            );
            foreach ($payments as $payment) {
                $paymentsByInstallment[(int) $payment['installment_id']][] = $payment;
            }
        }
        $states = [];
        foreach ($installments as $installment) {
            $legalFact = $legalFactsByContract[(int) ($installment['contract_id'] ?? 0)] ?? [];
            if (empty($installment['legal_started_at']) && !empty($legalFact['legal_referred_at'])) {
                $installment['legal_started_at'] = $legalFact['legal_referred_at'];
            }
            $installment['inconsistent_legal_referral_count'] = (int) ($legalFact['inconsistent_referral_count'] ?? 0);
            $states[(int) ($installment['id'] ?? 0)] = self::state($installment, $paymentsByInstallment[(int) ($installment['id'] ?? 0)] ?? [], $settings, $asOf);
        }
        return $states;
    }

    public static function stateForInstallment($installmentId, $asOf = null)
    {
        $installment = Installment::findRaw((int) $installmentId);
        if (!$installment) {
            throw new InvalidArgumentException('قسط پیدا نشد.');
        }
        return self::state($installment, null, null, $asOf);
    }

    public static function paymentAllocation(array $payment, $remainingPrincipal, $normalPenalty, $legalPenalty)
    {
        $hasExplicit = array_key_exists('principal_applied', $payment) && $payment['principal_applied'] !== null;
        if ($hasExplicit) {
            return [
                'principal_applied' => min(max(0, normalize_money($remainingPrincipal)), normalize_money($payment['principal_applied'] ?? 0)),
                'normal_penalty_applied' => min(max(0, normalize_money($normalPenalty)), normalize_money($payment['normal_penalty_applied'] ?? 0)),
                'legal_penalty_applied' => min(max(0, normalize_money($legalPenalty)), normalize_money($payment['legal_penalty_applied'] ?? 0)),
                'reward_applied' => normalize_money($payment['reward_applied'] ?? $payment['calculated_reward'] ?? 0),
            ];
        }

        $amount = normalize_money($payment['amount'] ?? 0);
        $before = max(0, normalize_money($remainingPrincipal));
        if (array_key_exists('remaining_after_payment', $payment) && $payment['remaining_after_payment'] !== null) {
            $principal = min($before, max(0, $before - normalize_money($payment['remaining_after_payment'])));
        } else {
            $principal = min($before, $amount);
        }
        $reward = min($principal, normalize_money($payment['calculated_reward'] ?? 0));
        $penaltyBudget = max(0, $amount + $reward - $principal);
        $legal = min(max(0, normalize_money($legalPenalty)), $penaltyBudget);
        $penaltyBudget -= $legal;
        $normal = min(max(0, normalize_money($normalPenalty)), $penaltyBudget);
        return [
            'principal_applied' => $principal,
            'normal_penalty_applied' => $normal,
            'legal_penalty_applied' => $legal,
            'reward_applied' => $reward,
        ];
    }

    private static function closedState($id, $contractId, array $installment, $base, $asOf, $status)
    {
        return [
            'installment_id' => $id, 'contract_id' => $contractId, 'status' => $status, 'financial_status' => $status,
            'financial_priority' => 90, 'due_date' => self::date($installment['due_date'] ?? $asOf),
            'original_principal' => $base, 'base_amount' => $base, 'effective_paid_principal' => normalize_money($installment['paid_amount'] ?? 0),
            'paid_amount' => normalize_money($installment['paid_amount'] ?? 0), 'remaining_principal' => 0, 'remaining_amount' => 0,
            'normal_penalty' => 0, 'legal_penalty' => 0, 'total_penalty' => 0, 'penalty' => 0,
            'normal_penalty_accrued' => 0, 'legal_penalty_accrued' => 0, 'effective_penalty_payable' => 0,
            'projected_legal_penalty' => 0, 'projected_legal_penalty_available' => false, 'penalty_mode' => 'normal',
            'eligible_reward' => 0, 'reward' => 0, 'reward_applied' => 0, 'effective_settlement_date' => null,
            'final_payable' => 0, 'payable' => 0, 'payment_allowed' => false, 'future_penalty' => 0,
            'calculated_at' => $asOf, 'calculation_version' => self::CALCULATION_VERSION,
            'calculation_status' => 'not_applicable', 'calculation_warnings' => [],
        ];
    }

    private static function accrueTo(&$normal, &$legal, $principal, $cursor, $target, $dueDate, $graceEnd, $normalRate, $legalRate, $legalStart)
    {
        if ($principal <= 0 || $target <= $cursor || $target <= $graceEnd) {
            return;
        }
        // After grace, delay is charged from the original due date. Payments
        // made during grace have already reduced $principal before this point.
        $from = $cursor;
        if ($cursor === $dueDate && $target > $graceEnd) {
            $from = $dueDate;
        }
        if ($target <= $from) {
            return;
        }
        if (!$legalStart || $target <= $legalStart) {
            $normal += MoneyMath::rateForDays($principal, $normalRate, self::daysBetween($from, $target));
            return;
        }
        if ($from >= $legalStart) {
            $legal += MoneyMath::rateForDays($principal, $legalRate, self::daysBetween($from, $target));
            return;
        }
        $normal += MoneyMath::rateForDays($principal, $normalRate, self::daysBetween($from, $legalStart));
        $legal += MoneyMath::rateForDays($principal, $legalRate, self::daysBetween($legalStart, $target));
    }

    private static function eligibleReward(array $installment, $remainingPrincipal, array $settings, $asOf)
    {
        if ($remainingPrincipal <= 0 || $asOf >= self::date($installment['due_date'] ?? $asOf)) {
            return 0;
        }
        $days = max(1, self::daysBetween($asOf, self::date($installment['due_date'])));
        return max(0, MoneyMath::rateForDays($remainingPrincipal, MoneyMath::rateUnits($settings['monthly_reward_rate'] ?? 0), $days)
            + normalize_money($installment['manual_reward_adjustment'] ?? 0));
    }

    private static function openStatus(array $installment, $remaining, $dueDate, $asOf)
    {
        if ($remaining < normalize_money($installment['base_amount'] ?? 0)) {
            return 'partial';
        }
        return $asOf > $dueDate ? 'overdue' : 'pending';
    }

    private static function financialStatus(array $installment, $remaining, $dueDate, $asOf, $graceEnd, $legalStartedAt)
    {
        if (($installment['status'] ?? '') === 'cancelled') return 'cancelled';
        if ($remaining <= 0) return 'paid';
        if ($dueDate < $asOf && $legalStartedAt && $legalStartedAt <= $asOf) return 'legal_overdue';
        if ($dueDate < $asOf && $asOf <= $graceEnd) return 'grace';
        if ($dueDate < $asOf) return 'overdue';
        if ($remaining < normalize_money($installment['base_amount'] ?? 0)) return 'partial';
        return 'upcoming';
    }

    private static function priority(array $installment, $remaining, $dueDate, $asOf, $graceEnd, $legalStartedAt, $isPaid)
    {
        if (($installment['status'] ?? '') === 'cancelled') return 90;
        if ($isPaid) return 80;
        if ($dueDate < $asOf && $legalStartedAt && $legalStartedAt <= $asOf) return 0;
        if ($dueDate < $asOf && $asOf > $graceEnd) return 1;
        if ($dueDate === $asOf) return 2;
        if ($dueDate < $asOf) return 3;
        if ($remaining < normalize_money($installment['base_amount'] ?? 0)) return 4;
        return 5;
    }

    private static function legalStartedAt(array $installment)
    {
        $value = substr((string) ($installment['legal_started_at'] ?? ''), 0, 10);
        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) ? $value : null;
    }

    private static function storedSettlementDate(array $installment, $isPaid)
    {
        if (!$isPaid) return null;
        $value = substr((string) ($installment['effective_settlement_at'] ?? $installment['last_payment_date'] ?? ''), 0, 10);
        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) ? $value : null;
    }

    private static function date($value)
    {
        $value = substr(trim((string) $value), 0, 10);
        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) ? $value : date('Y-m-d');
    }

    private static function addDays($date, $days)
    {
        $dateTime = new DateTime($date);
        if ((int) $days > 0) $dateTime->modify('+' . (int) $days . ' day');
        return $dateTime->format('Y-m-d');
    }

    private static function daysBetween($from, $to)
    {
        return max(0, (int) ((strtotime($to) - strtotime($from)) / 86400));
    }
}
