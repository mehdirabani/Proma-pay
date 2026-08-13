<?php

/**
 * Lightweight, database-free request budget for shared hosting.
 *
 * The guard runs before sessions, plugins and database access. It protects a
 * host from a broken browser extension, a refresh loop or an over-eager client
 * poller without adding a write query to every page request.
 */
class RequestFloodGuard
{
    public static function enforce(): void
    {
        if (PHP_SAPI === 'cli') {
            return;
        }

        $result = self::consume(self::clientIp(), self::route());
        if (!empty($result['allowed'])) {
            return;
        }

        $retryAfter = max(1, (int) ($result['retry_after'] ?? 1));
        if (class_exists('RequestTelemetry', false)) {
            RequestTelemetry::setStatus(429);
            RequestTelemetry::recordError('request_flood_guard', 429);
        }

        ErrorHandler::respond(
            429,
            'تعداد درخواست‌های این اتصال بیش از حد مجاز است. چند لحظه صبر کنید و دوباره تلاش کنید.',
            [],
            [
                'Retry-After' => (string) $retryAfter,
                'X-Proma-Rate-Limit' => (string) ($result['policy'] ?? 'global'),
            ]
        );
    }

    /**
     * Consumes one token. Public for deterministic QA; web requests use enforce().
     */
    public static function consume(string $ip, string $route = '', ?float $now = null): array
    {
        $ip = trim($ip);
        if ($ip === '') {
            return ['allowed' => true, 'retry_after' => 0, 'policy' => 'none'];
        }

        $now = $now ?? microtime(true);
        $policies = [self::globalPolicy()];
        $specificPolicy = self::routePolicy($route);
        if ($specificPolicy !== null) {
            $policies[] = $specificPolicy;
        }

        foreach ($policies as $policy) {
            $result = self::consumePolicy($ip, $policy, $now);
            if (empty($result['allowed'])) {
                return $result;
            }
        }

        $lastPolicy = end($policies);
        return ['allowed' => true, 'retry_after' => 0, 'policy' => (string) ($lastPolicy['key'] ?? 'global')];
    }

    protected static function consumePolicy(string $ip, array $policy, float $now): array
    {
        $directory = self::storageDirectory();
        if (!is_dir($directory) && !@mkdir($directory, 0750, true) && !is_dir($directory)) {
            // A permission issue must not take the whole shared-host site down.
            return ['allowed' => true, 'retry_after' => 0, 'policy' => $policy['key']];
        }

        $path = $directory . DIRECTORY_SEPARATOR . 'rfg-' . hash('sha256', $policy['key'] . '|' . $ip) . '.json';
        $handle = @fopen($path, 'c+');
        if ($handle === false) {
            return ['allowed' => true, 'retry_after' => 0, 'policy' => $policy['key']];
        }

        try {
            if (!flock($handle, LOCK_EX)) {
                return ['allowed' => true, 'retry_after' => 0, 'policy' => $policy['key']];
            }

            rewind($handle);
            $raw = stream_get_contents($handle);
            $state = is_string($raw) ? json_decode($raw, true) : null;
            if (!is_array($state)) {
                $state = [];
            }

            $capacity = max(1.0, (float) $policy['capacity']);
            $refill = max(0.001, (float) $policy['refill_per_second']);
            $updatedAt = (float) ($state['updated_at'] ?? $now);
            $elapsed = max(0.0, min(3600.0, $now - $updatedAt));
            $tokens = min($capacity, max(0.0, (float) ($state['tokens'] ?? $capacity)) + ($elapsed * $refill));
            $allowed = $tokens >= 1.0;
            $retryAfter = $allowed ? 0 : (int) ceil((1.0 - $tokens) / $refill);
            if ($allowed) {
                $tokens -= 1.0;
            }

            $nextState = json_encode([
                'tokens' => round($tokens, 5),
                'updated_at' => $now,
            ], JSON_UNESCAPED_SLASHES);
            if (is_string($nextState)) {
                ftruncate($handle, 0);
                rewind($handle);
                fwrite($handle, $nextState);
                fflush($handle);
            }

            return [
                'allowed' => $allowed,
                'retry_after' => $allowed ? 0 : max(1, $retryAfter),
                'policy' => $policy['key'],
            ];
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    }

    protected static function globalPolicy(): array
    {
        return [
            'key' => 'global',
            // 8 immediate requests, then 24 requests per minute. Static files
            // bypass PHP and are cached by .htaccess; this budget is only dynamic routes.
            'capacity' => self::envFloat('PROMA_REQUEST_RATE_CAPACITY', 8.0),
            'refill_per_second' => self::envFloat('PROMA_REQUEST_RATE_REFILL_PER_SECOND', 0.4),
        ];
    }

    protected static function routePolicy(string $route): ?array
    {
        $route = trim($route, '/');
        if ($route === 'chat/fetch') {
            return [
                'key' => 'chat-poll',
                'capacity' => self::envFloat('PROMA_CHAT_POLL_RATE_CAPACITY', 3.0),
                'refill_per_second' => self::envFloat('PROMA_CHAT_POLL_RATE_REFILL_PER_SECOND', 1.0 / 45.0),
            ];
        }
        if ($route === 'notifications/feed') {
            return [
                'key' => 'notification-poll',
                'capacity' => self::envFloat('PROMA_NOTIFICATION_POLL_RATE_CAPACITY', 2.0),
                'refill_per_second' => self::envFloat('PROMA_NOTIFICATION_POLL_RATE_REFILL_PER_SECOND', 1.0 / 90.0),
            ];
        }
        if ($route === 'contracts/preview') {
            return [
                'key' => 'contract-preview',
                // A healthy form emits one request after a user stops typing.
                // This separate bucket makes a client regression harmless on a shared host.
                'capacity' => self::envFloat('PROMA_CONTRACT_PREVIEW_RATE_CAPACITY', 3.0),
                'refill_per_second' => self::envFloat('PROMA_CONTRACT_PREVIEW_RATE_REFILL_PER_SECOND', 1.0 / 5.0),
            ];
        }
        if ($route === 'health/live' || $route === 'health/ready') {
            return [
                'key' => 'health',
                'capacity' => self::envFloat('PROMA_HEALTH_RATE_CAPACITY', 4.0),
                'refill_per_second' => self::envFloat('PROMA_HEALTH_RATE_REFILL_PER_SECOND', 1.0 / 30.0),
            ];
        }
        return null;
    }

    protected static function storageDirectory(): string
    {
        $configured = trim((string) getenv('PROMA_REQUEST_RATE_DIRECTORY'));
        return $configured !== '' ? rtrim($configured, '/\\') : dirname(__DIR__) . '/storage/cache/request-rate';
    }

    protected static function clientIp(): string
    {
        // REMOTE_ADDR is intentionally used instead of forwarded headers: on a
        // shared host those headers are user-controlled unless the host rewrites them.
        return substr(trim((string) ($_SERVER['REMOTE_ADDR'] ?? '')), 0, 64);
    }

    protected static function route(): string
    {
        return substr(trim((string) ($_GET['route'] ?? ''), '/'), 0, 180);
    }

    protected static function envFloat(string $key, float $fallback): float
    {
        $value = getenv($key);
        return $value !== false && is_numeric($value) ? max(0.001, (float) $value) : $fallback;
    }
}
