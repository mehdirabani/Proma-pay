<?php

namespace Proma\Plugins\Accounting\Services;

/** Creates one idempotent salary ledger period when an enabled rule is due. */
final class SalaryPayrollService
{
    public static function processDue(int $limit = 100): array
    {
        $limit = max(1, min(250, $limit));
        $today = date('Y-m-d');
        $jalali = \to_english_digits(\jdate($today));
        if (!preg_match('#^(\d{4})/(\d{2})/(\d{2})$#', $jalali, $parts)) {
            return ['checked' => 0, 'posted' => 0, 'failed' => 0];
        }
        $year = (int) $parts[1];
        $month = (int) $parts[2];
        $dayToday = (int) $parts[3];
        $rules = \Model::fetchAll(
            "SELECT r.* FROM user_salary_rules r
             JOIN users u ON u.id = r.user_id AND u.status = 'active'
             WHERE r.status = 'active' AND r.automatic_posting_enabled = 1
               AND r.start_date <= ? AND (r.end_date IS NULL OR r.end_date >= ?)
             ORDER BY r.id ASC LIMIT {$limit}",
            [$today, $today]
        );
        $result = ['checked' => count($rules), 'posted' => 0, 'failed' => 0];
        foreach ($rules as $rule) {
            try {
                $configuredDay = max(1, min(31, (int) ($rule['payment_day_jalali'] ?? 1)));
                $lastDay = self::daysInJalaliMonth($year, $month);
                if ($configuredDay > $lastDay && ($rule['invalid_day_policy'] ?? 'last_day') !== 'last_day') continue;
                $dueDay = min($configuredDay, $lastDay);
                if ($dayToday < $dueDay) continue;
                if (self::postPeriod($rule, $year, $month)) $result['posted']++;
            } catch (\Throwable $e) {
                $result['failed']++;
                if (class_exists('\\PluginRegistry')) \PluginRegistry::logRuntimeError('accounting.salary_payroll', $e);
            }
        }
        return $result;
    }

    private static function postPeriod(array $rule, int $year, int $month): bool
    {
        \Model::begin();
        try {
            $periodExists = \Model::execute(
                "INSERT IGNORE INTO user_salary_periods (salary_rule_id, user_id, salary_year, salary_month, amount_toman, status, created_at, updated_at)
                 VALUES (?, ?, ?, ?, ?, 'pending', NOW(), NOW())",
                [(int) $rule['id'], (int) $rule['user_id'], $year, $month, Money::decimal($rule['amount_toman'] ?? 0)]
            );
            if ((int) $periodExists !== 1) {
                \Model::commit();
                return false;
            }
            $periodId = (int) \Model::lastInsertId();
            $amount = Money::integer($rule['amount_toman'] ?? 0);
            if ($amount <= 0) throw new \InvalidArgumentException('مبلغ حقوق ثابت معتبر نیست.');
            $label = sprintf('حقوق ثابت %04d/%02d · %s', $year, $month, (string) $rule['title']);
            $entryId = LedgerService::post(
                (int) $rule['user_id'], 'fixed_salary', 'increase', $amount, $label, 0,
                'salary_period', $periodId,
                ['salary_rule_id' => (int) $rule['id'], 'salary_year' => $year, 'salary_month' => $month],
                'salary-period:' . (int) $rule['id'] . ':' . $year . '-' . str_pad((string) $month, 2, '0', STR_PAD_LEFT)
            );
            \Model::execute(
                "UPDATE user_salary_periods SET status = 'posted', ledger_entry_id = ?, posted_at = NOW(), updated_at = NOW() WHERE id = ? AND status = 'pending'",
                [$entryId, $periodId]
            );
            \Model::commit();
            return true;
        } catch (\Throwable $e) {
            \Model::rollBack();
            throw $e;
        }
    }

    private static function daysInJalaliMonth(int $year, int $month): int
    {
        if ($month >= 1 && $month <= 6) return 31;
        if ($month >= 7 && $month <= 11) return 30;
        $nextYear = $month === 12 ? $year + 1 : $year;
        $nextMonth = $month === 12 ? 1 : $month + 1;
        $nextMonthStart = \jalali_to_gregorian($nextYear, $nextMonth, 1);
        $lastDate = (new \DateTimeImmutable(sprintf('%04d-%02d-%02d', $nextMonthStart[0], $nextMonthStart[1], $nextMonthStart[2])))->modify('-1 day')->format('Y-m-d');
        $converted = \to_english_digits(\jdate($lastDate));
        return (int) substr($converted, -2);
    }
}
