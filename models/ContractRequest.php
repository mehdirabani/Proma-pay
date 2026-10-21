<?php

class ContractRequest extends Model
{
    public static function normalizeUuid($uuid)
    {
        $uuid = trim((string) $uuid);
        if ($uuid === '' || strlen($uuid) > 64 || !preg_match('/^[A-Za-z0-9._:-]+$/', $uuid)) {
            return bin2hex(random_bytes(24));
        }
        return $uuid;
    }

    public static function hash(array $payload)
    {
        unset($payload['_csrf'], $payload['contract_request_uuid']);
        $payload = self::sortRecursive($payload);
        return hash('sha256', json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    public static function beginRequest($uuid, $userId, $requestHash)
    {
        $uuid = self::normalizeUuid($uuid);
        $requestHash = trim((string) $requestHash);
        try {
            self::execute(
                'INSERT INTO contract_requests (request_uuid, user_id, request_hash, status, created_at)
                 VALUES (?, ?, ?, ?, NOW())',
                [$uuid, $userId ? (int) $userId : null, $requestHash, 'processing']
            );
            return ['status' => 'new', 'uuid' => $uuid, 'contract_id' => null];
        } catch (PDOException $e) {
            if ($e->getCode() !== '23000') {
                throw $e;
            }
            $existing = self::fetch('SELECT * FROM contract_requests WHERE request_uuid = ? LIMIT 1', [$uuid]);
            if (!$existing) {
                throw $e;
            }
            if (!hash_equals((string) $existing['request_hash'], $requestHash)) {
                throw new InvalidArgumentException('این درخواست قرارداد قبلاً با اطلاعات متفاوت ارسال شده است. صفحه را تازه‌سازی کنید.');
            }
            if (($existing['status'] ?? '') === 'completed' && !empty($existing['contract_id'])) {
                return ['status' => 'completed', 'uuid' => $uuid, 'contract_id' => (int) $existing['contract_id']];
            }
            if (($existing['status'] ?? '') === 'failed') {
                self::execute(
                    "UPDATE contract_requests
                     SET status = 'processing', error_message = NULL, updated_at = NOW()
                     WHERE id = ? AND status = 'failed'",
                    [(int) $existing['id']]
                );
                return ['status' => 'new', 'uuid' => $uuid, 'contract_id' => null];
            }
            throw new InvalidArgumentException('ثبت این قرارداد در حال پردازش است. چند لحظه بعد فهرست قراردادها را بررسی کنید.');
        }
    }

    public static function complete($uuid, $contractId)
    {
        return self::execute(
            "UPDATE contract_requests
             SET status = 'completed', contract_id = ?, completed_at = NOW(), updated_at = NOW()
             WHERE request_uuid = ? AND status = 'processing'",
            [(int) $contractId, self::normalizeUuid($uuid)]
        );
    }

    public static function fail($uuid, Throwable $exception)
    {
        try {
            self::execute(
                "UPDATE contract_requests
                 SET status = 'failed', error_message = ?, updated_at = NOW()
                 WHERE request_uuid = ? AND status = 'processing'",
                [mb_substr($exception->getMessage(), 0, 2000, 'UTF-8'), self::normalizeUuid($uuid)]
            );
        } catch (Throwable $ignored) {
        }
    }

    protected static function sortRecursive(array $value)
    {
        foreach ($value as $key => $item) {
            if (is_array($item)) {
                $value[$key] = self::sortRecursive($item);
            }
        }
        if ($value !== [] && array_keys($value) !== range(0, count($value) - 1)) {
            ksort($value);
        }
        return $value;
    }
}
