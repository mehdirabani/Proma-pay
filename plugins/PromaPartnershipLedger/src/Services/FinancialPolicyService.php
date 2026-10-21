<?php

namespace Proma\Plugins\PartnershipLedger\Services;

use Proma\Plugins\PartnershipLedger\Domain\InstallmentPricingCalculator;
use Proma\Plugins\PartnershipLedger\Domain\Money;

final class FinancialPolicyService
{
    public static function quoteFromInput(array $input)
    {
        $rate = Money::basisPoints($input['monthly_rate_pct'] ?? '');
        return InstallmentPricingCalculator::quote(
            Money::fromToman($input['base_price_toman'] ?? ''),
            Money::fromToman($input['down_payment_toman'] ?? '0'),
            $rate,
            $input['months'] ?? '',
            $input['method'] ?? '',
            $input['basis'] ?? ''
        );
    }

    public static function validatePolicyInput(array $input, array $activePartners)
    {
        $name = trim((string) ($input['policy_name'] ?? ''));
        $rawEffectiveOn = \to_english_digits(trim((string) ($input['effective_on'] ?? '')));
        $effectiveOn = '';
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/D', $rawEffectiveOn)) {
            $effectiveOn = $rawEffectiveOn;
        } elseif (preg_match('/^(\d{4})[\/-](\d{1,2})[\/-](\d{1,2})$/D', $rawEffectiveOn, $dateParts)) {
            $jalaliYear = (int) $dateParts[1];
            $jalaliMonth = (int) $dateParts[2];
            $jalaliDay = (int) $dateParts[3];
            $monthLength = $jalaliMonth >= 1 && $jalaliMonth <= 6 ? 31 : ($jalaliMonth >= 7 && $jalaliMonth <= 11 ? 30 : 0);
            if ($jalaliMonth === 12) {
                [$lastGy, $lastGm, $lastGd] = \jalali_to_gregorian($jalaliYear, 12, 30);
                [$lastJy, $lastJm, $lastJd] = \gregorian_to_jalali($lastGy, $lastGm, $lastGd);
                $monthLength = $lastJy === $jalaliYear && $lastJm === 12 && $lastJd === 30 ? 30 : 29;
            }
            if ($jalaliYear >= 1 && $jalaliMonth >= 1 && $jalaliMonth <= 12 && $jalaliDay >= 1 && $jalaliDay <= $monthLength) {
                $effectiveOn = \parse_jalali_date($rawEffectiveOn) ?: '';
            }
        }
        if ($name === '' || strlen($name) > 100 || $effectiveOn === '') {
            throw new \InvalidArgumentException('نام سیاست و تاریخ مؤثر معتبر را وارد کنید.');
        }
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $effectiveOn);
        if (!$date || $date->format('Y-m-d') !== $effectiveOn) {
            throw new \InvalidArgumentException('تاریخ مؤثر معتبر نیست.');
        }
        $rate = Money::basisPoints($input['monthly_rate_pct'] ?? '');
        $months = filter_var($input['months'] ?? '', FILTER_VALIDATE_INT);
        if ($months === false || $months < 1 || $months > 120) {
            throw new \InvalidArgumentException('تعداد ماه باید بین ۱ تا ۱۲۰ باشد.');
        }
        $method = trim((string) ($input['method'] ?? ''));
        $basis = trim((string) ($input['basis'] ?? ''));
        $settlement = trim((string) ($input['settlement_policy'] ?? ''));
        if (!in_array($method, ['simple', 'compound'], true)
            || !in_array($basis, ['full_base_price', 'funded_amount'], true)
            || !in_array($settlement, ['instant', 'fixed_deadline', 'collection_based', 'custom_milestones'], true)) {
            throw new \InvalidArgumentException('روش سود، مبنا و سیاست تسویه را مشخص کنید.');
        }
        $baseShares = self::parseShares($input['base_profit_shares'] ?? [], $activePartners, true);
        $financingShares = self::parseShares($input['financing_profit_shares'] ?? [], $activePartners, true);
        $reinvestment = self::parseShares($input['reinvestment_pct'] ?? [], $activePartners, false);
        if (count($reinvestment) !== count($activePartners)) {
            throw new \InvalidArgumentException('درصد بازسرمایه‌گذاری هر شریک را مشخص کنید؛ برای صفر مقدار ۰ وارد کنید.');
        }
        return [
            'name' => $name,
            'effective_on' => $effectiveOn,
            'monthly_rate_bp' => $rate,
            'months' => $months,
            'method' => $method,
            'basis' => $basis,
            'settlement_policy' => $settlement,
            'base_profit_shares' => $baseShares,
            'financing_profit_shares' => $financingShares,
            'reinvestment_bp' => $reinvestment,
        ];
    }

    public static function save(array $input, $actorId)
    {
        $partners = OwnershipService::activePartners();
        $policy = self::validatePolicyInput($input, $partners);
        $json = json_encode($policy, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        $hash = hash('sha256', $json);
        $started = !\Model::db()->inTransaction();
        if ($started) { \Model::begin(); }
        try {
            $row = \Model::fetch('SELECT COALESCE(MAX(version), 0) AS version FROM plugin_inventory_financial_policy_versions WHERE policy_name = ?', [$policy['name']]);
            $version = (int) ($row['version'] ?? 0) + 1;
            \Model::execute(
                'INSERT INTO plugin_inventory_financial_policy_versions (policy_name, version, effective_on, policy_json, policy_hash, created_by, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())',
                [$policy['name'], $version, $policy['effective_on'], $json, $hash, $actorId ? (int) $actorId : null]
            );
            $id = (int) \Model::lastInsertId();
            AuditService::record('financial_policy_version_created', 'financial_policy', $id, $actorId, [], ['name' => $policy['name'], 'version' => $version, 'hash' => $hash]);
            if ($started) { \Model::commit(); }
            return ['id' => $id, 'version' => $version, 'hash' => $hash];
        } catch (\Throwable $error) {
            if ($started) { \Model::rollBack(); }
            throw $error;
        }
    }

    public static function versions()
    {
        $rows = \Model::fetchAll('SELECT id, policy_name, version, effective_on, policy_json, policy_hash, created_at FROM plugin_inventory_financial_policy_versions ORDER BY policy_name ASC, version DESC LIMIT 100');
        foreach ($rows as &$row) {
            $row['policy'] = json_decode((string) $row['policy_json'], true) ?: [];
        }
        unset($row);
        return $rows;
    }

    private static function parseShares($submitted, array $partners, $mustTotal)
    {
        if (!is_array($submitted)) {
            throw new \InvalidArgumentException('درصدهای تخصیص معتبر نیستند.');
        }
        $active = [];
        foreach ($partners as $partner) {
            $active[(int) $partner['id']] = (string) $partner['full_name'];
        }
        $shares = [];
        foreach ($submitted as $rawId => $rawPercent) {
            $partnerId = filter_var($rawId, FILTER_VALIDATE_INT);
            if (!$partnerId || !isset($active[(int) $partnerId])) {
                throw new \InvalidArgumentException('یکی از شرکای انتخاب‌شده فعال نیست.');
            }
            if (trim((string) $rawPercent) === '') {
                if ($mustTotal) { $shares[(int) $partnerId] = 0; }
                continue;
            }
            $shares[(int) $partnerId] = Money::basisPoints($rawPercent);
        }
        if (!$shares) {
            if ($mustTotal) { throw new \InvalidArgumentException('درصد سهم هر شریک را وارد کنید.'); }
            return [];
        }
        if ($mustTotal && array_sum($shares) !== 10000) {
            throw new \InvalidArgumentException('جمع درصد تخصیص سود باید دقیقاً ۱۰۰٪ باشد.');
        }
        ksort($shares, SORT_NUMERIC);
        return $shares;
    }
}
