<?php

namespace Proma\Plugins\Accounting\Services;

class CommissionCalculationService
{
    public static function preview($sellerId, array $amounts)
    {
        return self::calculate($amounts, self::ruleFor((int) $sellerId));
    }

    public static function forSale(array $sale)
    {
        $contractId = (int) ($sale['contract_id'] ?? 0);
        $installments = \Model::fetch(
            "SELECT COALESCE(SUM(base_amount), 0) AS total, COALESCE(SUM(CASE WHEN status = 'paid' OR paid_amount > 0 THEN paid_amount ELSE 0 END), 0) AS paid FROM installments WHERE contract_id = ? AND status <> 'cancelled'",
            [$contractId]
        ) ?: [];
        $payments = \Model::fetch(
            "SELECT COALESCE(SUM(amount), 0) AS total FROM payments WHERE contract_id = ? AND status = 'paid' AND COALESCE(is_corrected, 0) = 0",
            [$contractId]
        ) ?: [];
        $financed = Money::integer($sale['financed_amount'] ?? 0);
        $totalInstallments = Money::integer($installments['total'] ?? 0);

        return self::calculate([
            'principal_amount' => $sale['principal_amount'] ?? 0,
            'down_payment_amount' => $sale['down_payment_amount'] ?? 0,
            'financed_amount' => $financed,
            'profit_amount' => max(0, $totalInstallments - $financed),
            'collected_amount' => $payments['total'] ?? ($installments['paid'] ?? 0),
        ], self::ruleFor((int) ($sale['seller_user_id'] ?? 0)));
    }

    public static function calculate(array $amounts, array $rule)
    {
        $basisType = self::basisType($rule['calculation_basis'] ?? 'financed_amount');
        $basis = Money::integer($amounts[$basisType] ?? 0);
        $type = ($rule['commission_type'] ?? '') === 'fixed' ? 'fixed' : 'percentage';
        $value = trim((string) ($rule['commission_value'] ?? '0'));
        $raw = $type === 'percentage'
            ? Money::percentage($basis, $value)
            : Money::integer($value);

        $minimum = !empty($rule['minimum_enabled']) ? Money::integer($rule['minimum_amount'] ?? 0) : null;
        $maximum = !empty($rule['maximum_enabled']) ? Money::integer($rule['maximum_amount'] ?? 0) : null;
        $afterMinimum = $minimum !== null ? max($raw, $minimum) : $raw;
        $afterMaximum = $maximum !== null ? min($afterMinimum, $maximum) : $afterMinimum;
        $final = self::round($afterMaximum, $rule['rounding_method'] ?? 'none', $rule['rounding_unit'] ?? 1000);

        return [
            'basis_type' => $basisType,
            'basis_amount' => $basis,
            'commission_type' => $type,
            'commission_value' => $value,
            'raw_commission' => $raw,
            'minimum_amount' => $minimum,
            'minimum_adjustment' => $afterMinimum - $raw,
            'maximum_amount' => $maximum,
            'maximum_adjustment' => $afterMaximum - $afterMinimum,
            'rounding_method' => self::roundingMethod($rule['rounding_method'] ?? 'none'),
            'rounding_unit' => Money::integer($rule['rounding_unit'] ?? 1000),
            'rounding_adjustment' => $final - $afterMaximum,
            'final_commission' => $final,
            'rule_source' => (string) ($rule['rule_source'] ?? 'default'),
            'rule_id' => !empty($rule['id']) ? (int) $rule['id'] : null,
            'rule_name' => (string) ($rule['name'] ?? 'قانون پیش‌فرض'),
            'calculation_timing' => (string) ($rule['calculation_timing'] ?? 'at_contract_creation'),
            'requires_approval' => !empty($rule['requires_approval']),
        ];
    }

    public static function ruleFor($sellerId)
    {
        $sellerId = (int) $sellerId;
        if ($sellerId > 0) {
            $rule = \Model::fetch(
                "SELECT * FROM plugin_accounting_commission_rules
                 WHERE is_active = 1 AND (user_id = ? OR user_id IS NULL)
                 AND (effective_from IS NULL OR effective_from <= CURDATE())
                 AND (effective_to IS NULL OR effective_to >= CURDATE())
                 ORDER BY CASE WHEN user_id = ? THEN 0 ELSE 1 END, priority DESC, id ASC LIMIT 1",
                [$sellerId, $sellerId]
            );
            if ($rule) {
                $settings = AccountingRepository::settings();
                $rule['minimum_enabled'] = $rule['minimum_amount'] !== null && $rule['minimum_amount'] !== '';
                $rule['maximum_enabled'] = $rule['maximum_amount'] !== null && $rule['maximum_amount'] !== '';
                $rule['rounding_method'] = self::roundingMethod($settings['rounding_method'] ?? 'none');
                $rule['rounding_unit'] = $settings['rounding_unit'] ?? 1000;
                $rule['rule_source'] = !empty($rule['user_id']) ? 'seller' : 'default_rule';
                return $rule;
            }
        }

        $settings = AccountingRepository::settings();
        return [
            'id' => null,
            'name' => 'قانون پیش‌فرض سامانه',
            'commission_type' => in_array($settings['default_commission_type'] ?? '', ['fixed', 'percentage'], true) ? $settings['default_commission_type'] : 'percentage',
            'commission_value' => $settings['default_commission_value'] ?? '0',
            'calculation_basis' => self::basisType($settings['default_calculation_basis'] ?? 'financed_amount'),
            'minimum_enabled' => ($settings['minimum_commission_enabled'] ?? '0') === '1',
            'minimum_amount' => $settings['minimum_commission'] ?? '0',
            'maximum_enabled' => ($settings['maximum_commission_enabled'] ?? '0') === '1',
            'maximum_amount' => $settings['maximum_commission'] ?? '0',
            'rounding_method' => self::roundingMethod($settings['rounding_method'] ?? 'none'),
            'rounding_unit' => $settings['rounding_unit'] ?? '1000',
            'calculation_timing' => $settings['default_calculation_timing'] ?? 'at_contract_creation',
            'requires_approval' => ($settings['require_commission_approval'] ?? '0') === '1',
            'rule_source' => 'default',
        ];
    }

    public static function round($amount, $method, $unit)
    {
        $amount = max(0, Money::integer($amount));
        $method = self::roundingMethod($method);
        $unit = max(1, Money::integer($unit));
        if ($method === 'none') {
            return $amount;
        }
        if ($method === 'down') {
            return intdiv($amount, $unit) * $unit;
        }
        if ($method === 'up') {
            return intdiv($amount + $unit - 1, $unit) * $unit;
        }
        return intdiv($amount + intdiv($unit, 2), $unit) * $unit;
    }

    public static function basisType($value)
    {
        return in_array($value, ['principal_amount', 'financed_amount', 'profit_amount', 'collected_amount'], true)
            ? $value
            : 'financed_amount';
    }

    public static function roundingMethod($value)
    {
        return in_array($value, ['none', 'nearest', 'down', 'up'], true) ? $value : 'none';
    }
}
