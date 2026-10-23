<?php

declare(strict_types=1);

/** Strict, versioned commission and separate referral-reward policy documents. */
final class MerchantCommissionPolicy
{
    private const TYPES = [
        'fixed_per_sale', 'cash_price_percent', 'financed_principal_percent',
        'installments_collected_percent', 'tiered_cash_volume_percent',
    ];
    private const RECOGNITION = ['contract_activated', 'settlement_paid', 'installment_collected'];
    private const PAYABLE = ['on_recognition', 'after_refund_window', 'manual_approval'];
    private const REVERSAL = ['proportional', 'full_refund_only', 'manual_approval'];

    public static function resolve(array $policy): array
    {
        $enabled = $policy['enabled'] ?? false;
        if (!in_array($enabled, [true, false], true)) {
            throw new InvalidArgumentException('merchant_commission_enabled_invalid');
        }
        $commissionRules = self::rules($policy['commission_rules'] ?? [], false);
        $rewardRules = self::rules($policy['reward_rules'] ?? [], true);
        if ($enabled && !$commissionRules && !$rewardRules) {
            throw new InvalidArgumentException('merchant_commission_rules_required');
        }
        return [
            'enabled' => $enabled,
            'currency_unit' => 'toman',
            'commission_rules' => $commissionRules,
            'reward_rules' => $rewardRules,
        ];
    }

    private static function rules($rules, bool $rewardRules): array
    {
        if (!is_array($rules)) {
            throw new InvalidArgumentException('merchant_commission_rules_invalid');
        }
        $result = [];
        $seen = [];
        foreach ($rules as $rule) {
            if (!is_array($rule)) {
                throw new InvalidArgumentException('merchant_commission_rule_invalid');
            }
            $code = $rule['rule_code'] ?? null;
            if (!is_string($code) || !preg_match('/^[a-z][a-z0-9_.-]{0,59}$/D', $code) || isset($seen[$code])) {
                throw new InvalidArgumentException('merchant_commission_rule_code_invalid');
            }
            $seen[$code] = true;
            $type = $rule['type'] ?? null;
            $allowedTypes = $rewardRules
                ? ['fixed_per_referral', 'cash_price_percent', 'tiered_cash_volume_percent']
                : self::TYPES;
            if (!is_string($type) || !in_array($type, $allowedTypes, true)) {
                throw new InvalidArgumentException('merchant_commission_rule_type_invalid');
            }
            $payer = $rule['payer'] ?? null;
            $payeeType = $rule['payee_type'] ?? null;
            $payeeTypes = $rewardRules ? ['merchant', 'user', 'referral_member'] : ['merchant', 'user'];
            if (!in_array($payer, ['platform', 'merchant'], true)
                || !in_array($payeeType, $payeeTypes, true)) {
                throw new InvalidArgumentException('merchant_commission_parties_invalid');
            }
            $payeeUserId = $rule['payee_user_id'] ?? null;
            if (($payeeType === 'user' && (!is_int($payeeUserId) || $payeeUserId < 1))
                || (in_array($payeeType, ['merchant', 'referral_member'], true) && $payeeUserId !== null)) {
                throw new InvalidArgumentException('merchant_commission_payee_invalid');
            }
            $recognition = $rule['recognition_event'] ?? null;
            $payable = $rule['payable_rule'] ?? null;
            $reversal = $rule['reversal_policy'] ?? null;
            if (!in_array($recognition, self::RECOGNITION, true)
                || !in_array($payable, self::PAYABLE, true)
                || !in_array($reversal, self::REVERSAL, true)) {
                throw new InvalidArgumentException('merchant_commission_lifecycle_invalid');
            }
            if (($type === 'installments_collected_percent' && $recognition !== 'installment_collected')
                || ($type !== 'installments_collected_percent' && !$rewardRules && $recognition === 'installment_collected')
                || ($rewardRules && $type === 'fixed_per_referral' && !in_array($recognition, ['contract_activated','installment_collected'], true))
                || ($rewardRules && $type !== 'fixed_per_referral' && $recognition === 'installment_collected')
                || ($payeeType === 'referral_member' && (!$rewardRules || $type !== 'fixed_per_referral'))) {
                throw new InvalidArgumentException('merchant_commission_rule_event_mismatch');
            }
            $refundWindow = $rule['refund_window_days'] ?? 0;
            if (!is_int($refundWindow) || $refundWindow < 0 || $refundWindow > 3650
                || ($payable === 'after_refund_window' && $refundWindow < 1)) {
                throw new InvalidArgumentException('merchant_commission_refund_window_invalid');
            }
            $normalized = [
                'rule_code' => $code,
                'type' => $type,
                'payer' => $payer,
                'payee_type' => $payeeType,
                'payee_user_id' => $payeeUserId,
                'recognition_event' => $recognition,
                'payable_rule' => $payable,
                'reversal_policy' => $reversal,
                'refund_window_days' => $refundWindow,
            ];
            if ($type === 'fixed_per_sale' || $type === 'fixed_per_referral') {
                $amount = $rule['fixed_amount_toman'] ?? null;
                if (!is_int($amount) || $amount < 1) {
                    throw new InvalidArgumentException('merchant_commission_fixed_amount_invalid');
                }
                $normalized['fixed_amount_toman'] = $amount;
            } elseif ($type === 'tiered_cash_volume_percent') {
                $normalized['tiers'] = self::tiers($rule['tiers'] ?? null);
            } else {
                $rate = $rule['rate_bps'] ?? null;
                if (!is_int($rate) || $rate < 1 || $rate > 10000) {
                    throw new InvalidArgumentException('merchant_commission_rate_invalid');
                }
                $normalized['rate_bps'] = $rate;
            }
            $result[] = $normalized;
        }
        return $result;
    }

    private static function tiers($tiers): array
    {
        if (!is_array($tiers) || !$tiers) {
            throw new InvalidArgumentException('merchant_commission_tiers_invalid');
        }
        $result = [];
        $previous = -1;
        foreach ($tiers as $tier) {
            if (!is_array($tier)
                || !is_int($tier['minimum_monthly_cash_volume_toman'] ?? null)
                || $tier['minimum_monthly_cash_volume_toman'] < 0
                || $tier['minimum_monthly_cash_volume_toman'] <= $previous
                || !is_int($tier['rate_bps'] ?? null)
                || $tier['rate_bps'] < 0 || $tier['rate_bps'] > 10000) {
                throw new InvalidArgumentException('merchant_commission_tiers_invalid');
            }
            $previous = $tier['minimum_monthly_cash_volume_toman'];
            $result[] = [
                'minimum_monthly_cash_volume_toman' => $previous,
                'rate_bps' => $tier['rate_bps'],
            ];
        }
        if ($result[0]['minimum_monthly_cash_volume_toman'] !== 0) {
            throw new InvalidArgumentException('merchant_commission_tiers_invalid');
        }
        return $result;
    }
}
