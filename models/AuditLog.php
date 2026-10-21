<?php

class AuditLog extends Model
{
    public static function record($eventType, $eventAction, $relatedType, $relatedId, array $data = [])
    {
        $actorId = !empty($data['actor_user_id']) ? (int) $data['actor_user_id'] : null;
        $auditNumber = 'AUD-' . date('YmdHis') . '-' . strtoupper(bin2hex(random_bytes(3)));
        $metadata = [
            'actor_type' => $data['actor_type'] ?? 'user',
            'event_result' => $data['event_result'] ?? 'success',
            'severity' => $data['severity'] ?? 'high',
            'description' => trim((string) ($data['description'] ?? '')) ?: null,
            'customer_id' => !empty($data['customer_id']) ? (int) $data['customer_id'] : null,
            'contract_id' => !empty($data['contract_id']) ? (int) $data['contract_id'] : null,
            'installment_id' => !empty($data['installment_id']) ? (int) $data['installment_id'] : null,
            'old_values' => $data['old_values'] ?? null,
            'new_values' => $data['new_values'] ?? null,
        ];

        self::execute(
            'INSERT INTO audit_logs
             (audit_number, event_type, event_action, event_result, severity, actor_type, actor_user_id,
              related_type, related_id, customer_id, contract_id, installment_id, old_values, new_values,
              description, ip_address, user_agent, request_method, request_path, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())',
            [
                $auditNumber,
                trim((string) $eventType),
                trim((string) $eventAction),
                $metadata['event_result'],
                $metadata['severity'],
                $metadata['actor_type'],
                $actorId,
                trim((string) $relatedType),
                (int) $relatedId,
                $metadata['customer_id'],
                $metadata['contract_id'],
                $metadata['installment_id'],
                self::jsonValue($metadata['old_values']),
                self::jsonValue($metadata['new_values']),
                $metadata['description'],
                $_SERVER['REMOTE_ADDR'] ?? null,
                $_SERVER['HTTP_USER_AGENT'] ?? null,
                $_SERVER['REQUEST_METHOD'] ?? null,
                isset($_SERVER['REQUEST_URI']) ? substr((string) $_SERVER['REQUEST_URI'], 0, 255) : null,
            ]
        );
        return $auditNumber;
    }

    protected static function jsonValue($value)
    {
        if ($value === null) {
            return null;
        }
        return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
