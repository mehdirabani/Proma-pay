<?php

class PluginManager
{
    protected static $instance;
    protected static $booted = false;
    protected $providers = [];
    protected $routes = [];
    protected $menus = [];

    public static function instance()
    {
        if (!self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public static function boot()
    {
        $manager = self::instance();
        if (self::$booted) {
            return $manager;
        }
        self::$booted = true;
        foreach (PluginRegistry::all() as $plugin) {
            if (($plugin['status'] ?? '') !== 'active') {
                continue;
            }
            $pluginStartedAt = microtime(true);
            try {
                $manager->loadProvider($plugin);
                if (class_exists('RequestTelemetry', false)) {
                    RequestTelemetry::recordPlugin($plugin['plugin_id'] ?? 'unknown');
                    RequestTelemetry::recordSpan('plugin.boot', $pluginStartedAt, ['plugin' => $plugin['plugin_id'] ?? 'unknown']);
                }
            } catch (Throwable $e) {
                if (class_exists('RequestTelemetry', false)) {
                    RequestTelemetry::recordPlugin($plugin['plugin_id'] ?? 'unknown');
                    RequestTelemetry::recordSpan('plugin.boot_failed', $pluginStartedAt, ['plugin' => $plugin['plugin_id'] ?? 'unknown']);
                }
                try {
                    PluginRegistry::setStatus($plugin['plugin_id'], 'failed', $e->getMessage());
                    PluginRegistry::logRuntimeError('plugin_boot:' . $plugin['plugin_id'], $e);
                } catch (Throwable $ignored) {
                }
            }
        }
        return $manager;
    }

    public static function discover()
    {
        $root = self::rootPath();
        if (!is_dir($root)) {
            return [];
        }
        $items = [];
        $seen = [];
        $registryReady = PluginRegistry::tableExists();
        foreach (glob($root . DIRECTORY_SEPARATOR . '*', GLOB_ONLYDIR) ?: [] as $dir) {
            if (basename($dir) === 'tmp' || strpos(basename($dir), '.') === 0) {
                continue;
            }
            try {
                $manifest = PluginManifest::read($dir);
                $seen[$manifest['id']] = true;
                if ($registryReady) {
                    PluginRegistry::reconcileFilesystem($manifest, $dir, 'discovered');
                }
                $registered = $registryReady ? PluginRegistry::find($manifest['id']) : null;
                $manifest['technical_path'] = str_replace('\\', '/', $dir);
                $manifest['path'] = self::displayPath($dir);
                $manifest['status'] = PluginRegistry::normalizeStatus($registered['status'] ?? 'discovered');
                $manifest['installed_version'] = (string) ($registered['version'] ?? $manifest['version']);
                $registeredManifest = $registered ? json_decode((string) ($registered['manifest_json'] ?? ''), true) : [];
                $manifest['has_staged_update'] = is_array($registeredManifest) && !empty($registeredManifest['_update_previous_version']);
                $manifest['last_error'] = $registered['last_error'] ?? null;
                $manifest['has_files'] = true;
                $items[] = $manifest;
            } catch (Throwable $e) {
                $items[] = [
                    'id' => basename($dir),
                    'name' => basename($dir),
                    'version' => '-',
                    'status' => 'failed',
                    'last_error' => $e->getMessage(),
                    'path' => self::displayPath($dir),
                    'technical_path' => str_replace('\\', '/', $dir),
                    'has_files' => false,
                ];
            }
        }
        if ($registryReady) {
            foreach (PluginRegistry::allIncludingRemoved() as $registered) {
                $pluginId = trim((string) ($registered['plugin_id'] ?? ''));
                if ($pluginId === '' || isset($seen[$pluginId]) || !empty($registered['deleted_at'])) {
                    continue;
                }
                $missingStatus = PluginRegistry::markMissing($pluginId);
                $items[] = [
                    'id' => $pluginId,
                    'name' => $registered['name'] ?? $pluginId,
                    'version' => $registered['version'] ?? '-',
                    'status' => $missingStatus,
                    'last_error' => 'فایل‌های افزونه در پوشه runtime پیدا نشد.',
                    'path' => self::displayPath($registered['path'] ?? $pluginId),
                    'technical_path' => str_replace('\\', '/', (string) ($registered['path'] ?? '')),
                    'has_files' => false,
                ];
            }
        }
        return $items;
    }

    public static function displayPath($path)
    {
        $name = basename(str_replace('\\', '/', rtrim((string) $path, '/\\')));
        $name = preg_replace('/[^A-Za-z0-9._-]/', '', $name);
        return 'plugins/' . ($name !== '' ? $name : 'unknown');
    }

    public static function isActive($pluginId)
    {
        $plugin = PluginRegistry::find($pluginId);
        return $plugin && ($plugin['status'] ?? '') === 'active';
    }

    public static function can($permission)
    {
        if (!Auth::check()) {
            return false;
        }
        $permission = trim((string) $permission);
        if (Auth::role() === 'admin') {
            return true;
        }
        if (strpos($permission, 'plugin.') !== 0) {
            return false;
        }
        $parts = explode('.', $permission, 3);
        return count($parts) === 3 && PluginRegistry::roleCan($parts[1], $permission, Auth::role());
    }

    public static function requirePermission($permission)
    {
        if (!self::can($permission)) {
            ErrorHandler::abort(403);
        }
    }

    public static function rootPath()
    {
        return dirname(__DIR__) . DIRECTORY_SEPARATOR . 'plugins';
    }

    public function install($pluginId, $userId = null)
    {
        $lockName = $this->acquireLifecycleLock($pluginId, 'install');
        try {
        $manifest = $this->manifestById($pluginId);
        $existing = PluginRegistry::find($manifest['id']);
        if ($existing && in_array(PluginRegistry::normalizeStatus($existing['status']), [PluginStatus::INSTALLED, PluginStatus::ACTIVE, PluginStatus::INACTIVE, PluginStatus::VALIDATING, PluginStatus::UPDATING, PluginStatus::ACTIVATING, PluginStatus::DEACTIVATING, PluginStatus::UNINSTALLING], true)) {
            throw new InvalidArgumentException('این افزونه قبلاً نصب شده است.');
        }
        $this->ensureRegistryTables();
        PluginRegistry::upsert($manifest, $manifest['_root'], $userId, PluginStatus::VALIDATING);
        try {
            $this->assertRequirements($manifest);
            $this->runMigrations($manifest);
            $provider = $this->providerFor($manifest);
            $provider->install($this, $manifest);
            $this->registerManifestPermissions($manifest);
            $this->assertProviderHealth($provider, $manifest);
            PluginRegistry::updateManifest($manifest);
            PluginRegistry::setStatus($manifest['id'], PluginStatus::INSTALLED);
            $this->audit('plugin', 'installed', $manifest['id'], $userId, ['version' => $manifest['version']]);
        } catch (Throwable $e) {
            PluginRegistry::setStatus($manifest['id'], PluginStatus::ERROR, $e->getMessage());
            throw $e;
        }
        } finally {
            $this->releaseLifecycleLock($lockName);
        }
    }

    public function activate($pluginId, $userId = null)
    {
        $lockName = $this->acquireLifecycleLock($pluginId, 'activate');
        try {
        $registered = PluginRegistry::find($pluginId);
        if (!$registered || !in_array($registered['status'], ['installed', 'inactive'], true)) {
            throw new InvalidArgumentException('فعال‌سازی افزونه ممکن نیست. نصب یا بروزرسانی افزونه به‌طور کامل انجام نشده است. ابتدا عملیات تعمیر افزونه را اجرا کنید.');
        }
        $manifest = $this->manifestFromRegistered($registered);
        $this->assertRequirements($manifest);
        PluginRegistry::setStatus($manifest['id'], PluginStatus::ACTIVATING);
        try {
            $provider = $this->loadProvider($registered, false);
            $this->assertMigrationsCurrent($manifest);
            $this->assertProviderHealth($provider, $manifest);
            if (method_exists($provider, 'activate')) {
                $provider->activate($this, $manifest);
            }
            $this->assertProviderHealth($provider, $manifest);
            PluginRegistry::setStatus($manifest['id'], PluginStatus::ACTIVE);
        } catch (Throwable $e) {
            PluginRegistry::setStatus($manifest['id'], PluginStatus::ERROR, $e->getMessage());
            throw $e;
        }
        $this->audit('plugin', 'activated', $manifest['id'], $userId);
        } finally {
            $this->releaseLifecycleLock($lockName);
        }
    }

    public function deactivate($pluginId, $userId = null)
    {
        $lockName = $this->acquireLifecycleLock($pluginId, 'deactivate');
        try {
        $registered = PluginRegistry::find($pluginId);
        if (!$registered || ($registered['status'] ?? '') !== 'active') {
            throw new InvalidArgumentException('افزونه فعال نیست.');
        }
        $manifest = $this->manifestFromRegistered($registered);
        PluginRegistry::setStatus($manifest['id'], PluginStatus::DEACTIVATING);
        try {
            $provider = $this->loadProvider($registered, false);
            if (method_exists($provider, 'deactivate')) {
                $provider->deactivate($this, $manifest);
            }
            PluginRegistry::setStatus($manifest['id'], PluginStatus::INACTIVE);
        } catch (Throwable $e) {
            PluginRegistry::setStatus($manifest['id'], PluginStatus::ACTIVE, $e->getMessage());
            throw $e;
        }
        $this->audit('plugin', 'deactivated', $manifest['id'], $userId);
        } finally {
            $this->releaseLifecycleLock($lockName);
        }
    }

    public function uninstall($pluginId, $userId = null, $purge = false, $confirmation = '')
    {
        $lockName = $this->acquireLifecycleLock($pluginId, $purge ? 'purge' : 'uninstall');
        try {
        $registered = PluginRegistry::find($pluginId);
        if (!$registered) {
            throw new InvalidArgumentException('افزونه نصب نشده است.');
        }
        if ($purge && trim((string) $confirmation) !== 'حذف کامل اطلاعات افزونه') {
            throw new InvalidArgumentException('تایید حذف کامل افزونه صحیح نیست.');
        }
        if ($purge && Auth::role() !== 'admin') {
            throw new InvalidArgumentException('حذف کامل داده‌های افزونه فقط برای مدیر ارشد مجاز است.');
        }
        $manifest = $this->manifestFromRegistered($registered);
        PluginRegistry::setStatus($manifest['id'], PluginStatus::UNINSTALLING);
        try {
            $provider = $this->loadProvider($registered, false);
            if (($registered['status'] ?? '') === PluginStatus::ACTIVE && method_exists($provider, 'deactivate')) {
                $provider->deactivate($this, $manifest);
            }
            if (method_exists($provider, 'uninstall')) {
                $provider->uninstall($this, $manifest, $purge);
            }
            $this->removePluginFiles($registered['path']);
            PluginRegistry::setStatus($manifest['id'], PluginStatus::REMOVED);
        } catch (Throwable $e) {
            PluginRegistry::setStatus($manifest['id'], PluginStatus::RECOVERY_REQUIRED, $e->getMessage());
            throw $e;
        }
        $this->audit('plugin', $purge ? 'purged' : 'uninstalled', $manifest['id'], $userId);
        } finally {
            $this->releaseLifecycleLock($lockName);
        }
    }

    public function update($pluginId, $userId = null)
    {
        $lockName = $this->acquireLifecycleLock($pluginId, 'update');
        try {
        $registered = PluginRegistry::find($pluginId);
        if (!$registered) {
            throw new InvalidArgumentException('افزونه نصب نشده است.');
        }
        $storedManifest = json_decode((string) ($registered['manifest_json'] ?? ''), true);
        $storedManifest = is_array($storedManifest) ? $storedManifest : [];
        $manifest = PluginManifest::read($registered['path']);
        $current = trim((string) ($registered['version'] ?? '0.0.0'));
        if (version_compare($manifest['version'], $current, '<=')) {
            throw new InvalidArgumentException('نسخه جدیدتری برای این افزونه پیدا نشد.');
        }
        $resumeStatus = PluginRegistry::normalizeStatus($storedManifest['_update_previous_status'] ?? $registered['status'] ?? 'inactive');
        if (!in_array($resumeStatus, ['active', 'inactive', 'installed'], true)) {
            $resumeStatus = 'inactive';
        }
        $cleanupWarning = '';
        try {
            PluginRegistry::setStatus($pluginId, PluginStatus::UPDATING);
            $this->assertRequirements($manifest);
            $provider = $this->loadProvider($registered, false);
            $this->runMigrations($manifest);
            if (method_exists($provider, 'update')) {
                $provider->update($this, $manifest);
            }
            if ($resumeStatus === 'active' && !empty($storedManifest['_update_previous_version']) && method_exists($provider, 'activate')) {
                $provider->activate($this, $manifest);
            }
            $this->assertMigrationsCurrent($manifest);
            $this->assertProviderHealth($provider, $manifest);
            PluginRegistry::updateManifest($manifest);
            PluginRegistry::setStatus($manifest['id'], $resumeStatus);
            $backupPath = trim((string) ($storedManifest['_update_backup_path'] ?? ''));
            if ($backupPath !== '' && is_dir($backupPath)) {
                try {
                    $this->removePluginFiles($backupPath);
                } catch (Throwable $cleanupError) {
                    $cleanupWarning = $cleanupError->getMessage();
                }
            }
            $this->audit('plugin', 'updated', $manifest['id'], $userId, ['from' => $current, 'to' => $manifest['version'], 'status' => $resumeStatus, 'cleanup_warning' => $cleanupWarning ?: null]);
        } catch (Throwable $e) {
            $this->restoreFailedUpdate($pluginId, $registered, $storedManifest, $e);
            throw $e;
        }
        return ['from' => $current, 'to' => $manifest['version'], 'status' => $resumeStatus, 'cleanup_warning' => $cleanupWarning];
        } finally {
            $this->releaseLifecycleLock($lockName);
        }
    }

    public function stageUpdate($pluginId, array $file, $userId = null)
    {
        return $this->upload($file, $userId, $pluginId);
    }

    public function deleteFromHost($pluginId, $userId = null)
    {
        $registered = PluginRegistry::find($pluginId);
        if (!$registered) {
            throw new InvalidArgumentException('افزونه برای حذف از هاست پیدا نشد.');
        }
        $pluginId = trim((string) $pluginId);
        $status = PluginRegistry::normalizeStatus($registered['status'] ?? 'discovered');
        $path = $this->assertManagedPluginPath($registered['path'] ?? '');
        $storedManifest = json_decode((string) ($registered['manifest_json'] ?? ''), true);
        $storedManifest = is_array($storedManifest) ? $storedManifest : [];
        try {
            $manifest = PluginManifest::read($path);
        } catch (Throwable $e) {
            $manifest = ['id' => $pluginId, 'name' => $registered['name'] ?? $pluginId, 'version' => $registered['version'] ?? '0.0.0'];
        }

        if (isset($manifest['_root']) && in_array($status, ['active', 'installed', 'inactive'], true)) {
            $provider = $this->loadProvider($registered, false);
            if ($status === 'active' && method_exists($provider, 'deactivate')) {
                $provider->deactivate($this, $manifest);
            }
            if (method_exists($provider, 'uninstall')) {
                $provider->uninstall($this, $manifest, false);
            }
        }

        $backupPath = trim((string) ($storedManifest['_update_backup_path'] ?? ''));
        if ($backupPath !== '' && is_dir($backupPath)) {
            $this->removePluginFiles($backupPath);
        }
        PluginRegistry::setStatus($pluginId, 'removed');
        $this->removePluginFiles($path);
        PluginRegistry::softDelete($pluginId);
        $this->audit('plugin', 'deleted_from_host', $pluginId, $userId, ['version' => $registered['version'] ?? null, 'data_preserved' => true]);
        return ['id' => $pluginId, 'name' => $manifest['name'] ?? $pluginId, 'version' => $registered['version'] ?? null];
    }

    public function healthCheck($pluginId)
    {
        $registered = PluginRegistry::find($pluginId);
        if (!$registered) {
            return ['ok' => false, 'message' => 'افزونه ثبت نشده است.'];
        }
        try {
            $manifest = $this->manifestFromRegistered($registered);
            $this->assertRequirements($manifest);
            $provider = $this->providerFor($manifest);
            $result = method_exists($provider, 'healthCheck') ? $provider->healthCheck($this, $manifest) : true;
            return ['ok' => $result !== false, 'message' => $result === false ? 'بررسی provider ناموفق بود.' : 'افزونه سالم است.'];
        } catch (Throwable $e) {
            ErrorHandler::log('plugin_health:' . (string) $pluginId, $e, 500);
            return ['ok' => false, 'message' => 'بررسی سلامت افزونه انجام نشد. شناسه پیگیری: ' . ErrorHandler::requestId()];
        }
    }

    public function upload(array $file, $userId = null, $updatePluginId = null)
    {
        if (!class_exists('ZipArchive')) {
            throw new RuntimeException('افزونه ZIP در PHP فعال نیست.');
        }
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            throw new InvalidArgumentException('بارگذاری بسته افزونه انجام نشد.');
        }
        if ((int) ($file['size'] ?? 0) <= 0 || (int) $file['size'] > 20 * 1024 * 1024) {
            throw new InvalidArgumentException('حجم بسته افزونه باید کمتر از ۲۰ مگابایت باشد.');
        }
        $zip = new ZipArchive();
        if ($zip->open($file['tmp_name']) !== true) {
            throw new InvalidArgumentException('فایل ZIP افزونه قابل باز شدن نیست.');
        }
        $temp = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'cache' . DIRECTORY_SEPARATOR . 'plugin-upload-' . bin2hex(random_bytes(8));
        if (!mkdir($temp, 0700, true) && !is_dir($temp)) {
            $zip->close();
            throw new RuntimeException('ساخت پوشه موقت افزونه انجام نشد.');
        }
        try {
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $stat = $zip->statIndex($i);
                $name = str_replace('\\', '/', (string) ($stat['name'] ?? ''));
                $normalized = ltrim($name, '/');
                if ($normalized === '' || strpos($normalized, '../') !== false || strpos($normalized, '..\\') !== false || preg_match('#^[A-Za-z]:/#', $normalized)) {
                    throw new InvalidArgumentException('مسیر ناامن در بسته افزونه شناسایی شد.');
                }
                if (preg_match('/(^|\/)(\.env|config\/database\.php)$/i', $normalized) || preg_match('/\.(phar|cgi|exe|bat|cmd|sh)$/i', $normalized)) {
                    throw new InvalidArgumentException('فایل خطرناک در بسته افزونه شناسایی شد.');
                }
                $external = (int) ($stat['external_attributes'] ?? 0);
                if ((($external >> 16) & 0xF000) === 0xA000) {
                    throw new InvalidArgumentException('لینک نمادین در بسته افزونه مجاز نیست.');
                }
            }
            if (!$zip->extractTo($temp)) {
                throw new RuntimeException('استخراج بسته افزونه انجام نشد.');
            }
        } finally {
            $zip->close();
        }

        $roots = [];
        foreach (glob($temp . DIRECTORY_SEPARATOR . '*', GLOB_ONLYDIR) ?: [] as $dir) {
            $roots[] = $dir;
        }
        $pluginRoot = null;
        if (is_file($temp . DIRECTORY_SEPARATOR . 'plugin.json')) {
            $pluginRoot = $temp;
        } elseif (count($roots) === 1 && is_file($roots[0] . DIRECTORY_SEPARATOR . 'plugin.json')) {
            $pluginRoot = $roots[0];
        }
        if (!$pluginRoot) {
            $this->removePluginFiles($temp);
            throw new InvalidArgumentException('ساختار ZIP باید plugin.json را در ریشه افزونه داشته باشد.');
        }
        $manifest = PluginManifest::read($pluginRoot);
        $this->ensureRegistryTables();
        if ($updatePluginId !== null) {
            return $this->stageExtractedUpdate($updatePluginId, $manifest, $pluginRoot, $temp, $userId);
        }
        $destinationName = $pluginRoot === $temp ? $manifest['id'] : basename($pluginRoot);
        $destination = self::rootPath() . DIRECTORY_SEPARATOR . $destinationName;
        $replacementBackup = null;
        if (is_dir($destination) || file_exists($destination)) {
            $registered = PluginRegistry::findAny($manifest['id']);
            $status = PluginRegistry::normalizeStatus($registered['status'] ?? 'discovered');
            if (in_array($status, ['installed', 'active', 'inactive', 'update_available'], true)) {
                $this->removePluginFiles($temp);
                throw new InvalidArgumentException('نسخه نصب‌شده یا فعال افزونه را از مسیر بروزرسانی ارتقا دهید.');
            }
            try {
                $destinationManifest = PluginManifest::read($destination);
                if (($destinationManifest['id'] ?? '') !== $manifest['id']) {
                    throw new InvalidArgumentException('پوشه مقصد متعلق به افزونه دیگری است.');
                }
            } catch (Throwable $e) {
                $this->removePluginFiles($temp);
                throw new InvalidArgumentException('پوشه مقصد موجود است و جایگزینی امن آن ممکن نیست: ' . $e->getMessage());
            }
            $replacementBackup = dirname($destination) . DIRECTORY_SEPARATOR . '.replace-' . basename($destination) . '-' . bin2hex(random_bytes(5));
            if (!rename($destination, $replacementBackup)) {
                $this->removePluginFiles($temp);
                throw new RuntimeException('آماده‌سازی نسخه قبلی افزونه برای جایگزینی انجام نشد.');
            }
        }
        if (!is_dir(self::rootPath()) && !mkdir(self::rootPath(), 0755, true)) {
            $this->removePluginFiles($temp);
            throw new RuntimeException('ساخت پوشه افزونه انجام نشد.');
        }
        if (!rename($pluginRoot, $destination)) {
            if ($replacementBackup && is_dir($replacementBackup)) {
                @rename($replacementBackup, $destination);
            }
            $this->removePluginFiles($temp);
            throw new RuntimeException('انتقال افزونه به پوشه plugins انجام نشد.');
        }
        if ($replacementBackup && is_dir($replacementBackup)) {
            $this->removePluginFiles($replacementBackup);
        }
        $this->removePluginFiles($temp);
        $manifest = PluginManifest::read($destination);
        PluginRegistry::reconcileFilesystem($manifest, $destination, 'uploaded', $userId);
        $this->audit('plugin', 'uploaded', $manifest['id'], $userId, ['version' => $manifest['version']]);
        return $manifest;
    }

    protected function stageExtractedUpdate($pluginId, array $manifest, $pluginRoot, $temp, $userId = null)
    {
        $registered = PluginRegistry::find($pluginId);
        if (!$registered) {
            $this->removePluginFiles($temp);
            throw new InvalidArgumentException('افزونه نصب‌شده برای بروزرسانی پیدا نشد.');
        }
        $status = PluginRegistry::normalizeStatus($registered['status'] ?? 'discovered');
        if (!in_array($status, ['installed', 'active', 'inactive'], true)) {
            $this->removePluginFiles($temp);
            throw new InvalidArgumentException('ابتدا بروزرسانی آماده قبلی را نصب یا وضعیت افزونه را اصلاح کنید.');
        }
        if (($manifest['id'] ?? '') !== trim((string) $pluginId)) {
            $this->removePluginFiles($temp);
            throw new InvalidArgumentException('شناسه بسته بروزرسانی با افزونه انتخاب‌شده یکسان نیست.');
        }
        $current = trim((string) ($registered['version'] ?? '0.0.0'));
        if (version_compare((string) $manifest['version'], $current, '<=')) {
            $this->removePluginFiles($temp);
            throw new InvalidArgumentException('نسخه بسته باید از نسخه نصب‌شده جدیدتر باشد.');
        }
        try {
            $this->assertRequirements($manifest);
            $destination = $this->assertManagedPluginPath($registered['path'] ?? '');
        } catch (Throwable $e) {
            $this->removePluginFiles($temp);
            throw $e;
        }
        $oldProvider = null;
        $oldManifest = null;
        $deactivated = false;
        if ($status === 'active') {
            try {
                $oldManifest = $this->manifestFromRegistered($registered);
                $oldProvider = $this->loadProvider($registered, false);
                if (method_exists($oldProvider, 'deactivate')) {
                    $oldProvider->deactivate($this, $oldManifest);
                    $deactivated = true;
                }
            } catch (Throwable $e) {
                $this->removePluginFiles($temp);
                throw $e;
            }
        }
        $backup = self::rootPath() . DIRECTORY_SEPARATOR . '.update-backup-' . preg_replace('/[^a-z0-9_-]/i', '-', (string) $pluginId) . '-' . bin2hex(random_bytes(5));
        if (!rename($destination, $backup)) {
            if ($deactivated && $oldProvider && method_exists($oldProvider, 'activate')) {
                try {
                    $oldProvider->activate($this, $oldManifest);
                } catch (Throwable $ignored) {
                }
            }
            $this->removePluginFiles($temp);
            throw new RuntimeException('ساخت نسخه ایمنی فایل‌های فعلی افزونه انجام نشد.');
        }
        $candidateMoved = false;
        try {
            if (!rename($pluginRoot, $destination)) {
                throw new RuntimeException('انتقال نسخه جدید افزونه به مسیر runtime انجام نشد.');
            }
            $candidateMoved = true;
            $candidate = PluginManifest::read($destination);
            PluginRegistry::markUpdateAvailable($pluginId, $candidate, $destination, $status, $backup);
            $this->audit('plugin', 'update_staged', $pluginId, $userId, ['from' => $current, 'to' => $candidate['version'], 'previous_status' => $status]);
            $this->removePluginFiles($temp);
            return ['id' => $pluginId, 'name' => $candidate['name'], 'from' => $current, 'to' => $candidate['version']];
        } catch (Throwable $e) {
            if ($candidateMoved && is_dir($destination)) {
                $this->removePluginFiles($destination);
            }
            if (is_dir($backup)) {
                @rename($backup, $destination);
            }
            if ($deactivated && $oldProvider && method_exists($oldProvider, 'activate')) {
                try {
                    $oldProvider->activate($this, $oldManifest);
                } catch (Throwable $ignored) {
                }
            }
            if (is_dir($temp)) {
                $this->removePluginFiles($temp);
            }
            throw $e;
        }
    }

    public function rescan($userId = null)
    {
        $plugins = self::discover();
        $this->audit('plugin', 'rescanned', 'runtime', $userId, ['count' => count($plugins)]);
        return $plugins;
    }

    public function registerRoute($pluginId, $path, $handler, array $options = [])
    {
        $path = trim((string) $path, '/');
        $method = strtoupper(trim((string) ($options['method'] ?? 'GET')));
        if ($path === '' || strpos($path, '..') !== false || !is_string($handler) || strpos($handler, '@') === false) {
            throw new InvalidArgumentException('Route افزونه معتبر نیست.');
        }
        foreach ($this->routes as $route) {
            $registeredMethod = strtoupper(trim((string) ($route['options']['method'] ?? 'GET')));
            if (($route['path'] ?? '') === $path && $registeredMethod === $method) {
                throw new InvalidArgumentException('تعارض route افزونه شناسایی شد.');
            }
        }
        $this->routes[] = [
            'plugin_id' => (string) $pluginId,
            'path' => $path,
            'handler' => $handler,
            'options' => array_merge($options, ['method' => $method]),
        ];
    }

    public function registerMenu($pluginId, array $item)
    {
        if (empty($item['route']) || empty($item['label'])) {
            return;
        }
        $this->menus[] = [
            'plugin_id' => (string) $pluginId,
            'route' => trim((string) $item['route'], '/'),
            'label' => trim((string) $item['label']),
            'icon' => trim((string) ($item['icon'] ?? 'box')),
            'permission' => trim((string) ($item['permission'] ?? '')),
            'group_label' => trim((string) ($item['group_label'] ?? '')),
            'group_icon' => trim((string) ($item['group_icon'] ?? '')),
        ];
    }

    public function registerHook($pluginId, $hook, callable $listener, $priority = 10)
    {
        $hook = trim((string) $hook);
        if ($hook === '' || strpos($hook, '..') !== false) {
            throw new InvalidArgumentException('نام hook افزونه معتبر نیست.');
        }
        PluginHooks::listen($hook, $listener, $priority);
    }

    public function menus()
    {
        return $this->menus;
    }

    public function assetsForRoute($route)
    {
        self::boot();
        $route = trim((string) $route, '/');
        $pluginId = '';
        foreach ($this->routes as $item) {
            if ($this->matchRoute($item['path'], $route) !== false) {
                $pluginId = (string) ($item['plugin_id'] ?? '');
                break;
            }
        }
        if ($pluginId === '') {
            return [];
        }
        $registered = PluginRegistry::find($pluginId);
        if (!$registered || ($registered['status'] ?? '') !== 'active' || empty($registered['path'])) {
            return [];
        }
        $manifest = $this->manifestFromRegistered($registered);
        $base = self::displayPath($registered['path']);
        $assets = [];
        foreach ($manifest['assets'] ?? [] as $asset) {
            $asset = trim(str_replace('\\', '/', (string) $asset), '/');
            if ($asset === '' || strpos($asset, '..') !== false || !preg_match('/^[A-Za-z0-9._\/-]+$/', $asset)) {
                continue;
            }
            $extension = strtolower((string) pathinfo($asset, PATHINFO_EXTENSION));
            if (!in_array($extension, ['css', 'js'], true)) {
                continue;
            }
            $fullPath = rtrim((string) $registered['path'], '/\\') . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $asset);
            if (!is_file($fullPath)) {
                continue;
            }
            $assets[] = ['type' => $extension, 'path' => $base . '/' . $asset];
        }
        return $assets;
    }

    public static function viewFile($pluginId, $view)
    {
        $plugin = PluginRegistry::find($pluginId);
        if (!$plugin || empty($plugin['path'])) {
            throw new InvalidArgumentException('افزونه برای نمایش view پیدا نشد.');
        }
        $view = trim(str_replace('\\', '/', (string) $view), '/');
        if ($view === '' || strpos($view, '..') !== false || !preg_match('/^[a-zA-Z0-9_\/-]+$/', $view)) {
            throw new InvalidArgumentException('مسیر view افزونه معتبر نیست.');
        }
        $path = rtrim($plugin['path'], '/\\') . DIRECTORY_SEPARATOR . 'views' . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $view) . '.php';
        if (!is_file($path)) {
            throw new InvalidArgumentException('view افزونه پیدا نشد.');
        }
        return $path;
    }

    public static function fire($event, array $payload = [], $critical = false)
    {
        self::boot();
        return PluginHooks::dispatch($event, $payload, $critical);
    }

    public static function filter($hook, $value, array $context = [])
    {
        self::boot();
        return PluginHooks::filter($hook, $value, $context);
    }

    public function dispatchRoute($route)
    {
        self::boot();
        $route = trim((string) $route, '/');
        $requestMethod = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
        $matched = [];
        foreach ($this->routes as $item) {
            $parameters = $this->matchRoute($item['path'], $route);
            if ($parameters === false) {
                continue;
            }
            $matched[] = [$item, $parameters];
        }
        if (!$matched) {
            return false;
        }
        usort($matched, static function ($left, $right) use ($route) {
            $leftPath = trim((string) ($left[0]['path'] ?? ''), '/');
            $rightPath = trim((string) ($right[0]['path'] ?? ''), '/');
            $leftExact = $leftPath === $route ? 1 : 0;
            $rightExact = $rightPath === $route ? 1 : 0;
            if ($leftExact !== $rightExact) {
                return $rightExact <=> $leftExact;
            }
            $leftParams = substr_count($leftPath, '{');
            $rightParams = substr_count($rightPath, '{');
            if ($leftParams !== $rightParams) {
                return $leftParams <=> $rightParams;
            }
            return strlen($rightPath) <=> strlen($leftPath);
        });
        if (trim((string) ($matched[0][0]['path'] ?? ''), '/') === $route) {
            $matched = array_values(array_filter($matched, static function ($match) use ($route) {
                return trim((string) ($match[0]['path'] ?? ''), '/') === $route;
            }));
        }
        foreach ($matched as $match) {
            [$item, $parameters] = $match;
            $options = $item['options'];
            if (($options['method'] ?? '') !== '' && strtoupper((string) $options['method']) !== $requestMethod) {
                continue;
            }
            if (($options['auth'] ?? true) && !Auth::check()) {
                Auth::requireLogin();
            }
            if (!empty($options['permission'])) {
                self::requirePermission($options['permission']);
            }
            [$class, $method] = explode('@', $item['handler'], 2);
            if (!class_exists($class)) {
                throw new RuntimeException('کنترلر افزونه بارگذاری نشد.');
            }
            $controller = new $class();
            if (!method_exists($controller, $method)) {
                throw new RuntimeException('متد route افزونه پیدا نشد.');
            }
            $routeStartedAt = microtime(true);
            call_user_func_array([$controller, $method], $parameters);
            if (class_exists('RequestTelemetry', false)) {
                RequestTelemetry::recordSpan('plugin.route', $routeStartedAt, ['handler' => $class . '@' . $method]);
            }
            return true;
        }
        $allowed = [];
        foreach ($matched as $match) {
            $method = strtoupper((string) ($match[0]['options']['method'] ?? ''));
            if ($method !== '') {
                $allowed[$method] = true;
            }
        }
        if (!$allowed && $requestMethod !== '') {
            $allowed[$requestMethod] = true;
        }
        $allow = implode(', ', array_keys($allowed));
        ErrorHandler::respond(
            405,
            'این آدرس از روش استفاده‌شده پشتیبانی نمی‌کند. صفحه را دوباره باز کرده و عملیات را از مسیر اصلی انجام دهید.',
            [],
            $allow !== '' ? ['Allow' => $allow] : []
        );
        return true;
    }

    protected function matchRoute($pattern, $route)
    {
        $pattern = trim((string) $pattern, '/');
        if ($pattern === $route) {
            return [];
        }
        $regex = preg_replace_callback('/\{([a-zA-Z0-9_]+)\}/', static function () {
            return '([^/]+)';
        }, $pattern);
        $matches = [];
        if (!preg_match('#^' . $regex . '$#', $route, $matches)) {
            return false;
        }
        array_shift($matches);
        return array_map('urldecode', $matches);
    }

    protected function manifestById($pluginId)
    {
        foreach (self::discover() as $manifest) {
            if (($manifest['id'] ?? '') === (string) $pluginId && !empty($manifest['_root'])) {
                return $manifest;
            }
        }
        throw new InvalidArgumentException('افزونه پیدا نشد یا Manifest آن معتبر نیست.');
    }

    protected function manifestFromRegistered(array $registered)
    {
        $manifest = json_decode((string) ($registered['manifest_json'] ?? ''), true);
        if (!is_array($manifest)) {
            $manifest = PluginManifest::read($registered['path']);
        } else {
            $manifest = PluginManifest::validate($manifest, $registered['path']);
        }
        return $manifest;
    }

    protected function assertRequirements(array $manifest)
    {
        $requiredPhp = preg_replace('/[^0-9.]/', '', (string) ($manifest['requires_php'] ?? '0'));
        if ($requiredPhp !== '' && version_compare(PHP_VERSION, $requiredPhp, '<')) {
            throw new RuntimeException('نسخه PHP سرور برای این افزونه کافی نیست.');
        }
        $requiredCore = preg_replace('/[^0-9.]/', '', (string) ($manifest['requires_core'] ?? '0'));
        if ($requiredCore !== '' && version_compare(app_version(), $requiredCore, '<')) {
            throw new RuntimeException('نسخه Proma Pay برای این افزونه کافی نیست. نسخه نصب‌شده: ' . app_version_display() . '؛ حداقل لازم: V' . $requiredCore . '.');
        }
        $requiredApi = trim((string) ($manifest['plugin_api_version'] ?? '0'));
        if ($requiredApi !== '' && version_compare(plugin_api_version(), $requiredApi, '<')) {
            throw new RuntimeException('نسخه API افزونه‌ها سازگار نیست.');
        }
    }

    protected function loadProvider(array $registered, $boot = true)
    {
        $manifest = $this->manifestFromRegistered($registered);
        $provider = $this->providerFor($manifest);
        $providerKey = $manifest['id'];
        if (!isset($this->providers[$providerKey])) {
            $instance = new $provider();
            if (!$instance instanceof PluginServiceProviderInterface) {
                throw new RuntimeException('Provider افزونه قرارداد PluginServiceProviderInterface را پیاده نکرده است.');
            }
            $this->providers[$providerKey] = $instance;
            $instance->register($this, $manifest);
        }
        $instance = $this->providers[$providerKey];
        if ($boot) {
            $instance->boot($this, $manifest);
        }
        return $instance;
    }

    protected function providerFor(array $manifest)
    {
        $file = PluginManifest::providerFile($manifest);
        require_once $file;
        $class = (string) $manifest['provider'];
        if (!class_exists($class)) {
            throw new RuntimeException('کلاس provider افزونه در فایل تعریف نشده است.');
        }
        return new $class();
    }

    protected function runMigrations(array $manifest)
    {
        $files = [];
        foreach ($manifest['migrations'] as $migration) {
            $path = self::safePluginPath($manifest['_root'], $migration);
            if (is_file($path)) {
                $files[] = $path;
            }
        }
        if (!$files && is_dir($manifest['_root'] . DIRECTORY_SEPARATOR . 'migrations')) {
            $files = glob($manifest['_root'] . DIRECTORY_SEPARATOR . 'migrations' . DIRECTORY_SEPARATOR . '*.sql') ?: [];
        }
        sort($files, SORT_NATURAL);
        foreach ($files as $file) {
            $name = basename($file);
            if (PluginRegistry::migrationDone($manifest['id'], $name)) {
                continue;
            }
            $sql = (string) file_get_contents($file);
            $started = microtime(true);
            try {
                foreach ($this->splitSql($sql) as $statement) {
                    $this->executeMigrationStatement($statement);
                }
                PluginRegistry::recordMigration($manifest['id'], $name, hash('sha256', $sql), 'success', (int) round((microtime(true) - $started) * 1000));
            } catch (Throwable $e) {
                try {
                    PluginRegistry::recordMigration($manifest['id'], $name, hash('sha256', $sql), 'failed', (int) round((microtime(true) - $started) * 1000), $e->getMessage());
                } catch (Throwable $ignored) {
                }
                PluginRegistry::setStatus($manifest['id'], PluginStatus::ERROR, $e->getMessage());
                throw new RuntimeException('اجرای migration افزونه ' . $name . ' ناموفق بود: ' . $e->getMessage(), 0, $e);
            }
        }
    }

    protected function executeMigrationStatement($sql)
    {
        $statement = Model::db()->prepare((string) $sql);
        try {
            $statement->execute();
            do {
                if ($statement->columnCount() > 0) {
                    $statement->fetchAll(PDO::FETCH_ASSOC);
                }
            } while ($statement->nextRowset());
        } finally {
            $statement->closeCursor();
        }
    }

    protected function assertMigrationsCurrent(array $manifest)
    {
        foreach ($manifest['migrations'] ?? [] as $migration) {
            $name = basename(str_replace('\\', '/', (string) $migration));
            if ($name === '') {
                continue;
            }
            if (!PluginRegistry::migrationDone($manifest['id'], $name)) {
                throw new RuntimeException('migration افزونه کامل نیست: ' . $name);
            }
        }
    }

    protected function assertProviderHealth($provider, array $manifest)
    {
        if (method_exists($provider, 'healthCheck') && $provider->healthCheck($this, $manifest) === false) {
            PluginRegistry::setStatus($manifest['id'], PluginStatus::HEALTH_FAILED, 'بررسی سلامت افزونه ناموفق بود.');
            throw new RuntimeException('بررسی سلامت افزونه ناموفق بود.');
        }
    }

    protected function restoreFailedUpdate($pluginId, array $registered, array $storedManifest, Throwable $error)
    {
        $backupPath = trim((string) ($storedManifest['_update_backup_path'] ?? ''));
        $candidatePath = trim((string) ($registered['path'] ?? ''));
        $previousStatus = PluginRegistry::normalizeStatus($storedManifest['_update_previous_status'] ?? 'inactive');
        if (!in_array($previousStatus, [PluginStatus::ACTIVE, PluginStatus::INACTIVE, PluginStatus::INSTALLED], true)) {
            $previousStatus = PluginStatus::INACTIVE;
        }

        if ($backupPath !== '' && is_dir($backupPath) && $candidatePath !== '') {
            try {
                if (is_dir($candidatePath) && realpath($candidatePath) !== realpath($backupPath)) {
                    $this->removePluginFiles($candidatePath);
                }
                if (!is_dir($candidatePath) && !rename($backupPath, $candidatePath)) {
                    throw new RuntimeException('بازگردانی پوشه قبلی افزونه انجام نشد.');
                }
                $restoredManifest = PluginManifest::read($candidatePath);
                PluginRegistry::updateManifest($restoredManifest);
                PluginRegistry::setStatus($pluginId, $previousStatus, 'بروزرسانی کامل نشد و نسخه قبلی افزونه بازیابی شد.');
                $this->audit('plugin', 'update_rolled_back', $pluginId, null, ['status' => $previousStatus]);
                return;
            } catch (Throwable $rollbackError) {
                ErrorHandler::log('plugin_update_rollback:' . (string) $pluginId, $rollbackError, 500);
            }
        }

        PluginRegistry::setStatus($pluginId, PluginStatus::REPAIR_REQUIRED, $error->getMessage());
    }

    protected function registerManifestPermissions(array $manifest)
    {
        foreach ($manifest['permissions'] as $permission) {
            if (is_string($permission)) {
                PluginRegistry::savePermission($manifest['id'], $permission, $permission);
                continue;
            }
            if (is_array($permission) && !empty($permission['key'])) {
                PluginRegistry::savePermission($manifest['id'], $permission['key'], $permission['label'] ?? $permission['key']);
            }
        }
    }

    protected function ensureRegistryTables()
    {
        if (!PluginRegistry::all() && !PluginRegistry::tableExists()) {
            throw new RuntimeException('migration هسته افزونه‌ها اجرا نشده است.');
        }
    }

    protected function audit($type, $action, $pluginId, $userId = null, array $newValues = [])
    {
        try {
            AuditLog::record($type, $action, 'plugin', 0, [
                'actor_user_id' => $userId,
                'description' => 'عملیات افزونه ' . $pluginId,
                'new_values' => $newValues + ['plugin_id' => $pluginId],
            ]);
        } catch (Throwable $e) {
        }
    }

    protected function acquireLifecycleLock($pluginId, $operation)
    {
        $pluginId = preg_replace('/[^a-z0-9._-]/i', '', (string) $pluginId);
        if ($pluginId === '') {
            throw new InvalidArgumentException('شناسه افزونه معتبر نیست.');
        }
        $lockName = 'proma_plugin_' . substr(hash('sha256', $pluginId), 0, 32);
        $row = Model::fetch('SELECT GET_LOCK(?, 10) AS acquired', [$lockName]);
        if ((int) ($row['acquired'] ?? 0) !== 1) {
            throw new RuntimeException('عملیات دیگری روی این افزونه در حال اجرا است. چند لحظه بعد دوباره تلاش کنید.');
        }
        $this->audit('plugin', 'lifecycle_lock_acquired', $pluginId, Auth::id(), ['operation' => (string) $operation]);
        return $lockName;
    }

    protected function releaseLifecycleLock($lockName)
    {
        if (!$lockName) {
            return;
        }
        try {
            Model::fetch('SELECT RELEASE_LOCK(?) AS released', [(string) $lockName]);
        } catch (Throwable $e) {
            ErrorHandler::log('plugin_lifecycle_unlock', $e, 500);
        }
    }

    protected function removePluginFiles($path)
    {
        $path = rtrim((string) $path, '/\\');
        if ($path === '' || !is_dir($path)) {
            return;
        }
        if (is_link($path)) {
            throw new RuntimeException('حذف لینک نمادین به عنوان پوشه افزونه مجاز نیست.');
        }
        $realPath = realpath($path);
        $pluginRoot = realpath(self::rootPath());
        $cacheRoot = realpath(dirname(__DIR__) . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'cache');
        $allowed = false;
        foreach ([$pluginRoot, $cacheRoot] as $allowedRoot) {
            if ($allowedRoot && $realPath && $realPath !== $allowedRoot && strpos($realPath, rtrim($allowedRoot, '/\\') . DIRECTORY_SEPARATOR) === 0) {
                $allowed = true;
                break;
            }
        }
        if (!$allowed) {
            throw new RuntimeException('مسیر حذف خارج از محدوده امن افزونه‌ها است.');
        }
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($iterator as $item) {
            if ($item->isLink() || $item->isFile()) {
                if (!unlink($item->getPathname())) {
                    throw new RuntimeException('حذف یکی از فایل‌های افزونه انجام نشد.');
                }
            } else {
                if (!rmdir($item->getPathname())) {
                    throw new RuntimeException('حذف یکی از پوشه‌های افزونه انجام نشد.');
                }
            }
        }
        if (!rmdir($path)) {
            throw new RuntimeException('حذف پوشه اصلی افزونه انجام نشد.');
        }
    }

    protected function assertManagedPluginPath($path)
    {
        $root = realpath(self::rootPath());
        $resolved = realpath(rtrim((string) $path, '/\\'));
        if (!$root || !$resolved || !is_dir($resolved) || is_link($resolved)) {
            throw new RuntimeException('مسیر فایل‌های افزونه روی هاست معتبر نیست.');
        }
        $parent = realpath(dirname($resolved));
        if (!$parent || strcasecmp(rtrim($parent, '/\\'), rtrim($root, '/\\')) !== 0) {
            throw new RuntimeException('مسیر افزونه خارج از پوشه مدیریت‌شده plugins است.');
        }
        return $resolved;
    }

    protected static function safePluginPath($root, $relative)
    {
        $relative = str_replace('\\', '/', trim((string) $relative));
        if ($relative === '' || strpos($relative, '..') !== false || strpos($relative, '/') === 0) {
            throw new InvalidArgumentException('مسیر migration افزونه امن نیست.');
        }
        return rtrim($root, '/\\') . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);
    }

    protected function splitSql($sql)
    {
        $parts = preg_split('/;\s*(?:\r?\n|$)/', (string) $sql);
        return array_values(array_filter(array_map('trim', $parts), static function ($item) {
            return $item !== '' && strpos(ltrim($item), '--') !== 0;
        }));
    }
}
