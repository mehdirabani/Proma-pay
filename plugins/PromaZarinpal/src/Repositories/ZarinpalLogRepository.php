<?php

namespace Proma\Plugins\Zarinpal\Repositories;

class ZarinpalLogRepository
{
    public function record($event, $message, $transactionId = null, array $context = [], $level = 'info', $providerCode = null)
    {
        $context = $this->sanitize($context);
        \Model::execute(
            'INSERT INTO proma_zarinpal_logs (transaction_id, event_type, level, provider_code, message, context_json, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())',
            [
                $transactionId ? (int) $transactionId : null,
                substr(preg_replace('/[^a-z0-9._-]/i', '', (string) $event), 0, 80),
                in_array($level, ['info', 'warning', 'error'], true) ? $level : 'info',
                $providerCode === null ? null : substr((string) $providerCode, 0, 40),
                substr(trim((string) $message), 0, 255),
                $context ? json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null,
            ]
        );
    }

    private function sanitize($value, $key = '')
    {
        foreach (['merchant', 'password', 'secret', 'session', 'token', 'national', 'address', 'card_pan'] as $blocked) {
            if ($key !== '' && stripos($key, $blocked) !== false) {
                return '[redacted]';
            }
        }
        if (is_array($value)) {
            $result = [];
            foreach ($value as $childKey => $childValue) {
                $result[$childKey] = $this->sanitize($childValue, (string) $childKey);
            }
            return $result;
        }
        if (is_scalar($value) || $value === null) {
            return $value;
        }
        return '[unsupported]';
    }
}
