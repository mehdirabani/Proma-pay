<?php

final class PluginReconciliationService
{
    public static function scan()
    {
        $report = [];
        foreach (PluginManager::discover() as $plugin) {
            $id = trim((string) ($plugin['id'] ?? ''));
            $status = PluginStatus::normalize($plugin['status'] ?? PluginStatus::DISCOVERED);
            $issues = [];
            $hasFiles = !empty($plugin['has_files']);

            if (!$hasFiles) {
                $issues[] = 'missing_files';
            }
            if ($hasFiles && empty($plugin['technical_path'])) {
                $issues[] = 'missing_runtime_path';
            }
            if ($hasFiles && version_compare((string) ($plugin['version'] ?? '0.0.0'), (string) ($plugin['installed_version'] ?? '0.0.0'), '<')) {
                $issues[] = 'source_older_than_registry';
            }
            if (in_array($status, [PluginStatus::ERROR, PluginStatus::RECOVERY_REQUIRED], true)) {
                $issues[] = 'recoverable_state';
            }

            $report[$id] = [
                'plugin_id' => $id,
                'status' => $status,
                'has_files' => $hasFiles,
                'issues' => array_values(array_unique($issues)),
                'healthy' => !$issues,
                'recommended_action' => self::recommendedAction($status, $hasFiles, $issues),
            ];
        }
        return $report;
    }

    public static function repair($pluginId, $userId = null)
    {
        $pluginId = trim((string) $pluginId);
        $candidate = null;
        foreach (PluginManager::discover() as $plugin) {
            if (($plugin['id'] ?? '') === $pluginId) {
                $candidate = $plugin;
                break;
            }
        }
        if (!$candidate || empty($candidate['has_files']) || empty($candidate['technical_path'])) {
            throw new RuntimeException('فایل معتبر افزونه پیدا نشد. ابتدا ZIP افزونه را دوباره بارگذاری کنید.');
        }

        $manifest = PluginManifest::read($candidate['technical_path']);
        $registered = PluginRegistry::findAny($pluginId);
        $status = PluginStatus::normalize($registered['status'] ?? PluginStatus::DISCOVERED);
        if (!in_array($status, [PluginStatus::ERROR, PluginStatus::RECOVERY_REQUIRED, PluginStatus::REMOVED, PluginStatus::DISCOVERED, PluginStatus::UPLOADED], true)) {
            throw new InvalidArgumentException('این افزونه در وضعیت قابل تعمیر خودکار نیست.');
        }

        PluginRegistry::reconcileFilesystem($manifest, $candidate['technical_path'], PluginStatus::UPLOADED, $userId);
        PluginRegistry::setStatus($pluginId, PluginStatus::UPLOADED);
        AuditLog::record('plugin', 'reconciled', 'plugin', 0, [
            'actor_user_id' => $userId,
            'description' => 'رجیستری و فایل‌های افزونه آشتی داده شد.',
            'new_values' => ['plugin_id' => $pluginId, 'status' => PluginStatus::UPLOADED],
        ]);
        return ['plugin_id' => $pluginId, 'status' => PluginStatus::UPLOADED];
    }

    public static function clearStale($pluginId, $userId = null)
    {
        $record = PluginRegistry::findAny($pluginId);
        if (!$record) {
            return false;
        }
        $status = PluginStatus::normalize($record['status'] ?? PluginStatus::DISCOVERED);
        $path = trim((string) ($record['path'] ?? ''));
        if (is_dir($path) || !in_array($status, [PluginStatus::ERROR, PluginStatus::RECOVERY_REQUIRED, PluginStatus::REMOVED], true)) {
            throw new InvalidArgumentException('فقط رکورد خطادار یا حذف‌شده‌ای که فایل runtime ندارد قابل پاک‌سازی است.');
        }
        PluginRegistry::softDelete($pluginId);
        AuditLog::record('plugin', 'stale_registry_cleared', 'plugin', 0, [
            'actor_user_id' => $userId,
            'description' => 'رکورد stale افزونه از فهرست عملیاتی حذف شد.',
            'new_values' => ['plugin_id' => $pluginId],
        ]);
        return true;
    }

    protected static function recommendedAction($status, $hasFiles, array $issues)
    {
        if (!$hasFiles) {
            return in_array($status, [PluginStatus::ERROR, PluginStatus::RECOVERY_REQUIRED, PluginStatus::REMOVED], true)
                ? 'reupload_or_clear'
                : 'reupload';
        }
        if (in_array('recoverable_state', $issues, true)) {
            return 'repair';
        }
        if ($status === PluginStatus::UPDATE_AVAILABLE) {
            return 'update';
        }
        if (PluginStatus::canInstall($status, true)) {
            return 'install';
        }
        return 'none';
    }
}
