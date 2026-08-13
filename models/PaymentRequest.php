<?php

class PaymentRequest extends Model
{
    public static function hash(array $payload)
    {
        ksort($payload);
        return hash('sha256', json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    public static function normalizeUuid($uuid)
    {
        $uuid = trim((string) $uuid);
        if ($uuid === '' || strlen($uuid) > 64 || !preg_match('/^[A-Za-z0-9._:-]+$/', $uuid)) {
            return bin2hex(random_bytes(16));
        }
        return $uuid;
    }

    public static function beginRequest($uuid, $userId, $installmentId, $requestHash)
    {
        $uuid = self::normalizeUuid($uuid);
        $requestHash = trim((string) $requestHash);
        try {
            self::execute(
                'INSERT INTO payment_requests
                 (request_uuid, user_id, installment_id, request_hash, status, created_at)
                 VALUES (?, ?, ?, ?, ?, NOW())',
                [$uuid, $userId ? (int) $userId : null, (int) $installmentId, $requestHash, 'processing']
            );
            return ['status' => 'new', 'uuid' => $uuid, 'payment_id' => null];
        } catch (PDOException $e) {
            if ($e->getCode() !== '23000') {
                throw $e;
            }
            $existing = self::fetch('SELECT * FROM payment_requests WHERE request_uuid = ? LIMIT 1', [$uuid]);
            if (!$existing) {
                throw $e;
            }
            if (!hash_equals((string) $existing['request_hash'], $requestHash)) {
                throw new InvalidArgumentException('این درخواست پرداخت قبلاً با اطلاعات متفاوت ثبت شده است. صفحه را تازه‌سازی کنید.');
            }
            if (($existing['status'] ?? '') === 'completed' && !empty($existing['payment_id'])) {
                return ['status' => 'completed', 'uuid' => $uuid, 'payment_id' => (int) $existing['payment_id']];
            }
            throw new RuntimeException('این درخواست پرداخت در حال پردازش است. برای جلوگیری از پرداخت تکراری چند لحظه بعد وضعیت را بررسی کنید.');
        }
    }

    public static function complete($uuid, $paymentId, $responseCode = 'manual_paid')
    {
        self::execute(
            "UPDATE payment_requests
             SET status = 'completed', payment_id = ?, response_code = ?, completed_at = NOW(), updated_at = NOW()
             WHERE request_uuid = ?",
            [(int) $paymentId, substr((string) $responseCode, 0, 60), self::normalizeUuid($uuid)]
        );
    }

    public static function beginSettlement($uuid, $userId, $contractId, array $installmentIds, $quoteUuid, $requestHash)
    {
        $uuid = self::normalizeUuid($uuid);
        $ids = array_values(array_unique(array_filter(array_map('intval', $installmentIds))));
        sort($ids);
        try {
            self::execute(
                'INSERT INTO payment_requests (request_uuid, user_id, installment_id, contract_id, quote_uuid, selection_json, request_hash, status, created_at)
                 VALUES (?, ?, NULL, ?, ?, ?, ?, ?, NOW())',
                [$uuid, $userId ? (int) $userId : null, (int) $contractId, trim((string) $quoteUuid) ?: null, json_encode($ids), trim((string) $requestHash), 'processing']
            );
            return ['status' => 'new', 'uuid' => $uuid, 'payment_group_id' => null];
        } catch (PDOException $e) {
            if ($e->getCode() !== '23000') throw $e;
            $existing = self::fetch('SELECT * FROM payment_requests WHERE request_uuid = ? LIMIT 1', [$uuid]);
            if (!$existing) throw $e;
            if (!hash_equals((string) $existing['request_hash'], trim((string) $requestHash))) {
                throw new InvalidArgumentException('این شناسه پرداخت با مبلغ یا اقساط متفاوت استفاده شده است. صفحه را تازه‌سازی کنید.', 409);
            }
            if (($existing['status'] ?? '') === 'completed' && !empty($existing['payment_group_id'])) {
                return ['status' => 'completed', 'uuid' => $uuid, 'payment_group_id' => (int) $existing['payment_group_id']];
            }
            throw new RuntimeException('این درخواست پرداخت در حال پردازش است. برای جلوگیری از پرداخت تکراری چند لحظه بعد وضعیت را بررسی کنید.', 409);
        }
    }

    public static function completeSettlement($uuid, $paymentGroupId, $responseCode = 'settlement_paid')
    {
        self::execute(
            "UPDATE payment_requests SET status = 'completed', payment_group_id = ?, response_code = ?, completed_at = NOW(), updated_at = NOW()
             WHERE request_uuid = ?",
            [(int) $paymentGroupId, substr((string) $responseCode, 0, 60), self::normalizeUuid($uuid)]
        );
    }

    public static function fail($uuid, Throwable $e)
    {
        try {
            self::execute(
                "UPDATE payment_requests
                 SET status = 'failed', error_message = ?, updated_at = NOW()
                 WHERE request_uuid = ? AND status = 'processing'",
                [substr($e->getMessage(), 0, 2000), self::normalizeUuid($uuid)]
            );
        } catch (Throwable $ignored) {
        }
    }
}
