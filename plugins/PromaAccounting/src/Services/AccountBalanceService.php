<?php

namespace Proma\Plugins\Accounting\Services;

/**
 * Read-only, role-agnostic account summary for authenticated dashboards.
 * A positive ledger balance means the organisation owes the user (creditor);
 * a negative balance means the user owes the organisation (debtor).
 */
class AccountBalanceService
{
    public static function snapshot($userId): array
    {
        $userId = (int) $userId;
        if ($userId <= 0) {
            return self::emptySnapshot();
        }

        $account = \Model::fetch(
            "SELECT a.account_number, a.current_balance, a.currency, a.status
             FROM accounting_user_accounts a
             JOIN users u ON u.id = a.user_id
             WHERE a.user_id = ?
             LIMIT 1",
            [$userId]
        ) ?: [];
        $numeric = Money::signedInteger($account['current_balance'] ?? 0);
        $balance = Money::signedDecimal($numeric);
        $position = $numeric > 0 ? 'creditor' : ($numeric < 0 ? 'debtor' : 'settled');

        return [
            'user_id' => $userId,
            'account_number' => (string) ($account['account_number'] ?? ''),
            'balance' => $balance,
            'absolute_balance' => Money::decimal(abs($numeric)),
            'currency' => (string) ($account['currency'] ?? 'IRR'),
            'status' => (string) ($account['status'] ?? 'active'),
            'position' => $position,
            'label' => ['creditor' => 'بستانکار از مجموعه', 'debtor' => 'بدهکار به مجموعه', 'settled' => 'تسویه‌شده'][$position],
            'tone' => ['creditor' => 'success', 'debtor' => 'danger', 'settled' => 'neutral'][$position],
        ];
    }

    public static function emptySnapshot(): array
    {
        return [
            'user_id' => 0, 'account_number' => '', 'balance' => '0', 'absolute_balance' => '0',
            'currency' => 'IRR', 'status' => 'unavailable', 'position' => 'settled',
            'label' => 'تسویه‌شده', 'tone' => 'neutral',
        ];
    }
}
