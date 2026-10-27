<?php

/**
 * Routes a single-installment financial change into the contract-level,
 * maker-checker amendment workflow. No request is applied in this service.
 */
final class InstallmentChangeService
{
    public static function change($installmentId, array $input, $actorId, $expectedContractId = null): array
    {
        $installmentId = (int) $installmentId;
        $actorId = (int) $actorId;
        $reason = trim((string) ($input['reason'] ?? ''));
        if ($installmentId <= 0 || $actorId <= 0 || mb_strlen($reason, 'UTF-8') < 3) {
            throw new InvalidArgumentException('علت تغییر قسط را کامل وارد کنید.');
        }
        if (($input['difference_mode'] ?? 'manual_schedule') !== 'manual_schedule') {
            throw new InvalidArgumentException('انتقال خودکار اختلاف به اقساط دیگر در اصلاحیه پشتیبانی نمی‌شود؛ برنامه کامل را صریح وارد کنید.');
        }
        $before = self::lockedSnapshot($installmentId, $expectedContractId);
        self::assertVersion($before, (string) ($input['version_token'] ?? ''));
        $dueDate = parse_jalali_date($input['due_date'] ?? '') ?: trim((string) ($input['due_date'] ?? ''));
        $amount = normalize_money($input['base_amount'] ?? 0);
        if (!self::validDate($dueDate) || $dueDate < date('Y-m-d') || $amount <= 0) {
            throw new InvalidArgumentException('برای اصلاحیه آینده‌نگر، مبلغ مثبت و تاریخ سررسید آینده وارد کنید.');
        }
        $result = ContractAmendmentService::requestInstallmentChange($installmentId, ['due_date' => $dueDate, 'base_amount' => $amount], $actorId, $reason, false);
        return $result + ['mode' => 'approval_required'];
    }

    public static function void($installmentId, array $input, $actorId, $expectedContractId = null): array
    {
        $installmentId = (int) $installmentId;
        $actorId = (int) $actorId;
        $reason = trim((string) ($input['reason'] ?? ''));
        if (empty($input['confirm_void']) || mb_strlen($reason, 'UTF-8') < 3) {
            throw new InvalidArgumentException('علت ابطال و تأیید پیامدهای آن الزامی است.');
        }
        $before = self::lockedSnapshot($installmentId, $expectedContractId);
        self::assertVersion($before, (string) ($input['version_token'] ?? ''));
        $expected = 'ابطال قسط شماره ' . to_persian_digits($before['installment_number']);
        if (trim((string) ($input['typed_confirmation'] ?? '')) !== $expected) {
            throw new InvalidArgumentException('عبارت تأیید ابطال دقیق وارد نشده است.');
        }
        $result = ContractAmendmentService::requestInstallmentChange($installmentId, ['action' => 'void'], $actorId, $reason, true);
        return $result + ['mode' => 'approval_required'];
    }

    public static function versionToken(array $installment): string
    {
        return hash('sha256', implode('|', [
            (int) ($installment['id'] ?? 0), (string) ($installment['updated_at'] ?? ''),
            (string) ($installment['due_date'] ?? ''), normalize_money($installment['base_amount'] ?? 0),
            normalize_money($installment['paid_amount'] ?? 0), (string) ($installment['status'] ?? ''),
        ]));
    }

    private static function lockedSnapshot(int $installmentId, $expectedContractId): array
    {
        $sql = 'SELECT i.* FROM installments i WHERE i.id = ?';
        $params = [$installmentId];
        if ($expectedContractId !== null) {
            $sql .= ' AND i.contract_id = ?';
            $params[] = (int) $expectedContractId;
        }
        $row = Model::fetch($sql, $params);
        if (!$row) throw new InvalidArgumentException('قسط متعلق به این قرارداد پیدا نشد.');
        if (($row['status'] ?? '') === 'cancelled') throw new InvalidArgumentException('قسط ابطال‌شده قابل تغییر نیست.');
        return $row;
    }

    private static function assertVersion(array $before, string $submitted): void
    {
        if ($submitted === '' || !hash_equals(self::versionToken($before), $submitted)) {
            throw new InvalidArgumentException('قسط پس از باز شدن فرم تغییر کرده است. صفحه را تازه‌سازی و دوباره بررسی کنید.');
        }
    }

    private static function validDate(string $date): bool
    {
        $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
        return $parsed && $parsed->format('Y-m-d') === $date;
    }
}
