<?php

/**
 * Read-only operator debt scenarios derived from the canonical contract
 * financial summary.  Projected legal penalty remains a comparison value and
 * is never copied into the real payable amount.
 */
final class OperatorDebtScenarioService
{
    public static function cards(array $summary, bool $actualReferral): array
    {
        $principal = normalize_money($summary['remaining_principal'] ?? 0);
        $normal = normalize_money($summary['normal_late_penalty_total'] ?? 0);
        $actualLegal = normalize_money($summary['legal_late_penalty_total'] ?? 0);
        $projectedLegal = normalize_money($summary['projected_legal_penalty_total'] ?? 0);
        $legalForScenario = $actualReferral ? $actualLegal : $projectedLegal;
        $costs = normalize_money($summary['approved_outstanding_legal_costs'] ?? $summary['legal_costs_total'] ?? 0);
        $legalLabel = $actualReferral ? 'جریمه حقوقی اعمال‌شده' : 'جریمه حقوقی احتمالی';
        $legalState = $actualReferral ? 'واقعی' : 'احتمالی';

        return [
            self::card('اصل بدهی باقی‌مانده', $principal, 'واقعی', true),
            self::card('جریمه عادی', $normal, 'واقعی', true),
            self::card($legalLabel, $legalForScenario, $legalState, $actualReferral),
            self::card('مجموع هزینه‌های حقوقی تأییدشده', $costs, 'واقعی', true),
            self::card('اصل بدهی + جریمه عادی', $principal + $normal, 'قابل پرداخت', true),
            self::card('اصل بدهی + جریمه حقوقی', $principal + $legalForScenario, $actualReferral ? 'قابل پرداخت' : 'غیرقابل پرداخت', $actualReferral),
            self::card('اصل بدهی + عادی + هزینه‌های حقوقی', $principal + $normal + $costs, 'قابل پرداخت', true),
            self::card('اصل بدهی + حقوقی + هزینه‌های حقوقی', $principal + $legalForScenario + $costs, $actualReferral ? 'قابل پرداخت' : 'غیرقابل پرداخت', $actualReferral),
            self::card('مبلغ واقعی قابل پرداخت امروز', normalize_money($summary['final_collectable_amount'] ?? 0), 'قابل پرداخت', true, true),
        ];
    }

    private static function card(string $label, int $amount, string $status, bool $payable, bool $emphasis = false): array
    {
        return [
            'label' => $label,
            'amount' => max(0, $amount),
            'status' => $status,
            'is_payable' => $payable,
            'emphasis' => $emphasis,
        ];
    }
}
