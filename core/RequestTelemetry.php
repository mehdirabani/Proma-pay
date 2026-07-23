<?php

class RequestTelemetry
{
    protected static $startedAt;
    protected static $finished = false;
    protected static $route = '';
    protected static $status;
    protected static $queries = 0;
    protected static $queryDurationMs = 0.0;
    protected static $slowestQuery = null;
    protected static $queryErrors = 0;
    protected static $spans = [];
    protected static $externalCalls = [];
    protected static $plugins = [];
    protected static $errors = [];

    public static function boot()
    {
        if (self::$startedAt !== null) {
            return;
        }

        self::$startedAt = microtime(true);
        self::$route = self::sanitizeRoute($_GET['route'] ?? '');
        register_shutdown_function([self::class, 'finish']);
    }

    public static function setRoute($route)
    {
        self::$route = self::sanitizeRoute($route);
    }

    public static function setStatus($status)
    {
        self::$status = max(100, min(599, (int) $status));
    }

    public static function recordQuery($sql, $durationMs, Throwable $error = null)
    {
        $durationMs = max(0.0, (float) $durationMs);
        self::$queries++;
        self::$queryDurationMs += $durationMs;
        if ($error) {
            self::$queryErrors++;
        }

        if (self::$slowestQuery === null || $durationMs > self::$slowestQuery['duration_ms']) {
            self::$slowestQuery = [
                'duration_ms' => round($durationMs, 2),
                'fingerprint' => self::queryFingerprint($sql),
            ];
        }
    }

    public static function recordSpan($name, $startedAt, array $context = [])
    {
        self::$spans[] = [
            'name' => self::safeLabel($name),
            'duration_ms' => round(max(0, (microtime(true) - (float) $startedAt) * 1000), 2),
            'context' => self::safeContext($context),
        ];
    }

    public static function recordExternal($service, $durationMs, $status = 0, $ok = false)
    {
        self::$externalCalls[] = [
            'service' => self::safeLabel($service),
            'duration_ms' => round(max(0, (float) $durationMs), 2),
            'status' => (int) $status,
            'ok' => (bool) $ok,
        ];
    }

    public static function recordPlugin($pluginId)
    {
        $pluginId = self::safeLabel($pluginId);
        if ($pluginId !== '') {
            self::$plugins[$pluginId] = true;
        }
    }

    public static function recordError($kind, $status = 500)
    {
        self::$errors[] = [
            'kind' => self::safeLabel($kind),
            'status' => (int) $status,
        ];
    }

    public static function finish()
    {
        if (self::$finished || self::$startedAt === null || PHP_SAPI === 'cli') {
            return;
        }
        self::$finished = true;

        $durationMs = round((microtime(true) - self::$startedAt) * 1000, 2);
        $status = self::$status ?: (int) http_response_code();
        if ($status < 100) {
            $status = 200;
        }
        $warningMs = self::envFloat('PROMA_REQUEST_WARNING_MS', 2000);
        $slowMs = self::envFloat('PROMA_REQUEST_SLOW_MS', 5000);
        $criticalMs = self::envFloat('PROMA_REQUEST_CRITICAL_MS', 10000);
        $severity = $durationMs >= $criticalMs ? 'critical' : ($durationMs >= $slowMs ? 'slow' : ($durationMs >= $warningMs ? 'warning' : 'normal'));
        if ($severity === 'normal' && $status < 400 && !self::sampleNormalRequest()) {
            return;
        }

        $record = [
            'timestamp' => date(DATE_ATOM),
            'request_id' => class_exists('ErrorHandler', false) ? ErrorHandler::requestId() : null,
            'version' => function_exists('app_version') ? app_version() : null,
            'method' => strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')),
            'route' => self::$route,
            'status' => $status,
            'severity' => $severity,
            'duration_ms' => $durationMs,
            'client_ip' => self::maskedIp($_SERVER['REMOTE_ADDR'] ?? ''),
            'role' => isset($_SESSION['role']) ? self::safeLabel($_SESSION['role']) : null,
            'user_id' => isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null,
            'queries' => [
                'count' => self::$queries,
                'duration_ms' => round(self::$queryDurationMs, 2),
                'errors' => self::$queryErrors,
                'slowest' => self::$slowestQuery,
            ],
            'spans' => array_slice(self::$spans, -20),
            'external_calls' => array_slice(self::$externalCalls, -10),
            'plugins' => array_keys(self::$plugins),
            'errors' => array_slice(self::$errors, -10),
            'memory_peak_bytes' => memory_get_peak_usage(true),
            'pid' => function_exists('getmypid') ? getmypid() : null,
        ];

        self::write($record, $severity !== 'normal' || $status >= 400);
    }

    protected static function write(array $record, $important)
    {
        $root = dirname(__DIR__) . '/storage/logs';
        if (!is_dir($root) && !mkdir($root, 0750, true) && !is_dir($root)) {
            error_log('[PromaPay][telemetry] storage/logs is unavailable.');
            return;
        }
        if (!is_writable($root)) {
            error_log('[PromaPay][telemetry] storage/logs is not writable.');
            return;
        }

        $path = $root . '/request-' . date('Y-m-d') . '.jsonl';
        $maxBytes = max(1048576, (int) self::envFloat('PROMA_REQUEST_LOG_MAX_BYTES', 8388608));
        if (is_file($path)) {
            $currentBytes = (int) filesize($path);
            if ((!$important && $currentBytes >= $maxBytes) || $currentBytes >= ($maxBytes * 2)) {
                return;
            }
        }

        $json = json_encode($record, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (!is_string($json)) {
            error_log('[PromaPay][telemetry] JSON encoding failed.');
            return;
        }
        $handle = fopen($path, 'ab');
        if ($handle === false) {
            error_log('[PromaPay][telemetry] request log could not be opened.');
            return;
        }
        if (flock($handle, LOCK_EX | LOCK_NB)) {
            fwrite($handle, $json . PHP_EOL);
            flock($handle, LOCK_UN);
        }
        fclose($handle);
    }

    protected static function sampleNormalRequest()
    {
        $rate = max(0.0, min(1.0, self::envFloat('PROMA_REQUEST_SAMPLE_RATE', 0.05)));
        if ($rate <= 0) {
            return false;
        }
        if ($rate >= 1) {
            return true;
        }
        try {
            return random_int(1, 10000) <= (int) round($rate * 10000);
        } catch (Throwable $e) {
            return mt_rand(1, 10000) <= (int) round($rate * 10000);
        }
    }

    protected static function queryFingerprint($sql)
    {
        $sql = preg_replace("/'(?:''|[^'])*'/", '?', (string) $sql);
        $sql = preg_replace('/\b\d+(?:\.\d+)?\b/', '?', (string) $sql);
        $sql = preg_replace('/\s+/', ' ', trim((string) $sql));
        return substr((string) $sql, 0, 240);
    }

    protected static function sanitizeRoute($route)
    {
        $route = trim((string) $route, '/');
        if ($route === '') {
            return 'default';
        }
        return substr(preg_replace('/[^a-zA-Z0-9_\-\/{}]/', '', $route), 0, 180);
    }

    protected static function safeLabel($value)
    {
        return substr(preg_replace('/[^a-zA-Z0-9_.:\-\/]/', '', (string) $value), 0, 120);
    }

    protected static function safeContext(array $context)
    {
        $safe = [];
        foreach ($context as $key => $value) {
            if (is_scalar($value) || $value === null) {
                $safe[self::safeLabel($key)] = is_string($value) ? self::safeLabel($value) : $value;
            }
        }
        return $safe;
    }

    protected static function maskedIp($ip)
    {
        $ip = trim((string) $ip);
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            $parts = explode('.', $ip);
            return $parts[0] . '.' . $parts[1] . '.' . $parts[2] . '.0';
        }
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            $packed = inet_pton($ip);
            return $packed === false ? null : inet_ntop(substr($packed, 0, 8) . str_repeat("\0", 8));
        }
        return null;
    }

    protected static function envFloat($name, $default)
    {
        $value = getenv($name);
        return is_numeric($value) ? (float) $value : (float) $default;
    }
}
