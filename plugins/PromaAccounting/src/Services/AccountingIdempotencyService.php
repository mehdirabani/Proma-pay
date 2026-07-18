<?php

namespace Proma\Plugins\Accounting\Services;

/** Coordinates a financial request with its immutable ledger result. */
class AccountingIdempotencyService
{
    public static function begin(string $requestUuid, string $requestHash, int $userId, int $accountUserId, string $operationType): ?int
    {
        $created = \Model::execute(
            'INSERT INTO accounting_requests (request_uuid, plugin_id, user_id, account_user_id, operation_type, request_hash, status, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
             ON DUPLICATE KEY UPDATE request_uuid = request_uuid',
            [$requestUuid, 'proma-accounting', $userId ?: null, $accountUserId, $operationType, $requestHash, 'processing']
        );
        if ($created === 1) {
            return null;
        }

        $request = \Model::fetch('SELECT * FROM accounting_requests WHERE request_uuid = ? LIMIT 1 FOR UPDATE', [$requestUuid]);
        if (!$request) {
            throw new \RuntimeException('ACCOUNTING_STATE_UNCERTAIN: نتیجه درخواست مالی قابل تشخیص نیست. برای جلوگیری از ثبت تکراری، ابتدا گردش حساب شخص را بررسی کنید.');
        }
        if (!hash_equals((string) $request['request_hash'], $requestHash)) {
            throw new \RuntimeException('این درخواست مالی قبلاً با اطلاعات دیگری استفاده شده است.');
        }
        if (($request['status'] ?? '') === 'completed' && !empty($request['ledger_entry_id'])) {
            return (int) $request['ledger_entry_id'];
        }
        throw new \RuntimeException('ACCOUNTING_STATE_UNCERTAIN: وضعیت عملیات مالی در حال بررسی است. برای جلوگیری از ثبت تکراری، ابتدا گردش حساب شخص را بررسی کنید.');
    }

    public static function complete(string $requestUuid, int $ledgerEntryId): void
    {
        $updated = \Model::execute(
            "UPDATE accounting_requests SET status = 'completed', ledger_entry_id = ?, response_code = 303, completed_at = NOW() WHERE request_uuid = ? AND status = 'processing'",
            [$ledgerEntryId, $requestUuid]
        );
        if ($updated !== 1) {
            throw new \RuntimeException('ACCOUNTING_STATE_UNCERTAIN: وضعیت نهایی درخواست مالی ثبت نشد.');
        }
    }
}
