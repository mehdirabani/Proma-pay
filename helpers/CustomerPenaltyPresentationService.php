<?php

/**
 * Single presentation policy for customer-facing penalty comparison data.
 * Financial services consume the canonical state directly and never this DTO.
 */
final class CustomerPenaltyPresentationService
{
    public const DEFAULT_MESSAGE = 'جریمه حقوقی نمایش‌داده‌شده صرفاً برآورد مقایسه‌ای است و تا ثبت ارجاع رسمی، به مبلغ قابل پرداخت شما اضافه نمی‌شود.';

    public static function forState(array $state, ?array $settings = null): array
    {
        $settings = $settings ?? Settings::allKeyed();
        $normal = normalize_money($state['normal_penalty_accrued'] ?? $state['normal_penalty'] ?? 0);
        $legal = normalize_money($state['legal_penalty_accrued'] ?? $state['legal_penalty'] ?? 0);
        $effective = normalize_money($state['effective_penalty_payable'] ?? $state['total_penalty'] ?? $normal + $legal);
        $projected = normalize_money($state['projected_legal_penalty'] ?? 0);
        $actualReferral = !empty($state['canonical_legal_referral_at']);
        $enabled = !empty($settings['show_projected_legal_penalty_to_customer']);
        $validRate = MoneyMath::rateUnits($settings['legal_monthly_penalty_rate'] ?? 0) > 0;
        $showProjected = $enabled && $validRate && !$actualReferral
            && !empty($state['projected_legal_penalty_available']) && $projected > 0;

        return [
            'normal_penalty_accrued' => $normal,
            'legal_penalty_accrued' => $legal,
            'effective_penalty_payable' => $effective,
            'projected_legal_penalty' => $projected,
            'show_projected_legal_penalty' => $showProjected,
            'projected_legal_penalty_customer_message' => trim((string) ($settings['projected_legal_penalty_customer_message'] ?? '')) ?: self::DEFAULT_MESSAGE,
            'penalty_presentation_status' => $actualReferral ? 'actual_legal' : ($showProjected ? 'projected_comparison' : 'actual_normal'),
        ];
    }
}
