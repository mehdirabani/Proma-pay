<?php

class SystemHealthController extends Controller
{
    public function index()
    {
        Auth::requireRole('admin');
        Auth::releaseSessionLock();

        $database = $this->databaseStatus();
        $storageRoot = dirname(__DIR__) . '/storage';
        $plugins = PluginRegistry::tableExists() ? PluginRegistry::all() : [];
        $telemetry = $this->recentTelemetry();

        $this->render('system-health/index', [
            'title' => 'سلامت سامانه',
            'checkedAt' => date('Y-m-d H:i:s'),
            'database' => $database,
            'storage' => [
                'ok' => is_dir($storageRoot) && is_readable($storageRoot) && is_writable($storageRoot),
                'readable' => is_readable($storageRoot),
                'writable' => is_writable($storageRoot),
            ],
            'plugins' => array_map(static function ($plugin) {
                return [
                    'id' => (string) ($plugin['plugin_id'] ?? ''),
                    'name' => (string) ($plugin['name'] ?? $plugin['plugin_id'] ?? ''),
                    'version' => (string) ($plugin['version'] ?? '-'),
                    'status' => PluginRegistry::normalizeStatus($plugin['status'] ?? 'discovered'),
                    'last_error' => !empty($plugin['last_error']) ? 'خطای ثبت‌شده؛ جزئیات در لاگ فنی محافظت‌شده است.' : null,
                ];
            }, $plugins),
            'runtime' => [
                'core_version' => app_version_display(),
                'php_version' => PHP_VERSION,
                'sapi' => PHP_SAPI,
                'server' => $this->serverLabel(),
                'memory_limit' => (string) ini_get('memory_limit'),
                'max_execution_time' => (string) ini_get('max_execution_time'),
            ],
            'queue' => $this->queueStatus(),
            'telemetry' => $telemetry,
        ]);
    }

    protected function databaseStatus()
    {
        $startedAt = microtime(true);
        try {
            $row = Model::fetch('SELECT VERSION() AS version');
            return [
                'ok' => true,
                'version' => preg_replace('/[^a-zA-Z0-9.\-]/', '', (string) ($row['version'] ?? 'unknown')),
                'response_ms' => round((microtime(true) - $startedAt) * 1000, 2),
            ];
        } catch (Throwable $e) {
            ErrorHandler::log('system_health_database', $e, 503);
            return ['ok' => false, 'version' => '-', 'response_ms' => round((microtime(true) - $startedAt) * 1000, 2)];
        }
    }

    protected function queueStatus()
    {
        try {
            $row = Model::fetch(
                "SELECT
                    COALESCE(SUM(CASE WHEN status IN ('pending','failed') THEN 1 ELSE 0 END), 0) AS pending,
                    COALESCE(SUM(CASE WHEN status = 'dead' THEN 1 ELSE 0 END), 0) AS dead,
                    MAX(CASE WHEN status = 'processed' THEN processed_at ELSE NULL END) AS last_processed_at
                 FROM system_outbox"
            ) ?: [];
            return [
                'available' => true,
                'pending' => (int) ($row['pending'] ?? 0),
                'dead' => (int) ($row['dead'] ?? 0),
                'last_processed_at' => $row['last_processed_at'] ?? null,
            ];
        } catch (Throwable $e) {
            return ['available' => false, 'pending' => 0, 'dead' => 0, 'last_processed_at' => null];
        }
    }

    protected function recentTelemetry()
    {
        $path = dirname(__DIR__) . '/storage/logs/request-' . date('Y-m-d') . '.jsonl';
        if (!is_file($path) || !is_readable($path)) {
            return ['available' => false, 'slow' => [], 'errors' => [], 'external_failures' => 0];
        }

        $handle = fopen($path, 'rb');
        if ($handle === false) {
            return ['available' => false, 'slow' => [], 'errors' => [], 'external_failures' => 0];
        }
        try {
            $size = filesize($path);
            if ($size > 1048576) {
                fseek($handle, -1048576, SEEK_END);
                fgets($handle);
            }
            $contents = stream_get_contents($handle);
        } finally {
            fclose($handle);
        }

        $slow = [];
        $errors = [];
        $externalFailures = 0;
        foreach (array_slice(preg_split('/\R/', trim((string) $contents)) ?: [], -500) as $line) {
            $row = json_decode($line, true);
            if (!is_array($row)) {
                continue;
            }
            $safe = [
                'request_id' => (string) ($row['request_id'] ?? ''),
                'route' => (string) ($row['route'] ?? ''),
                'status' => (int) ($row['status'] ?? 0),
                'duration_ms' => (float) ($row['duration_ms'] ?? 0),
                'timestamp' => (string) ($row['timestamp'] ?? ''),
            ];
            if (in_array($row['severity'] ?? '', ['warning', 'slow', 'critical'], true)) {
                $slow[] = $safe;
            }
            if ($safe['status'] >= 400 || !empty($row['errors'])) {
                $errors[] = $safe;
            }
            foreach (($row['external_calls'] ?? []) as $call) {
                if (empty($call['ok'])) {
                    $externalFailures++;
                }
            }
        }

        return [
            'available' => true,
            'slow' => array_slice($slow, -10),
            'errors' => array_slice($errors, -10),
            'external_failures' => $externalFailures,
        ];
    }

    protected function serverLabel()
    {
        $raw = trim((string) ($_SERVER['SERVER_SOFTWARE'] ?? 'unknown'));
        $first = explode(' ', $raw)[0] ?? 'unknown';
        return substr(preg_replace('/[^a-zA-Z0-9.\-\/]/', '', $first), 0, 80);
    }
}
