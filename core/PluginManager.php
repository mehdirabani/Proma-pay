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
            try {
                $manager->loadProvider($plugin);
            } catch (Throwable $e) {
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
        foreach (glob($root . DIRECTORY_SEPARATOR . '*', GLOB_ONLYDIR) ?: [] as $dir) {
            if (basename($dir) === 'tmp' || strpos(basename($dir), '.') === 0) {
                continue;
            }
            try {
                $manifest = PluginManifest::read($dir);
                $registered = PluginRegistry::find($manifest['id']);
                $manifest['path'] = str_replace('\\', '/', $dir);
                $manifest['status'] = $registered['status'] ?? 'discovered';
                $manifest['last_error'] = $registered['last_error'] ?? null;
                $items[] = $manifest;
            } catch (Throwable $e) {
                $items[] = [
                    'id' => basename($dir),
                    'name' => basename($dir),
                    'version' => '-',
                    'status' => 'failed',
                    'last_error' => $e->getMessage(),
                    'path' => str_replace('\\', '/', $dir),
                ];
            }
        }
        return $items;
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
            http_response_code(403);
            $controller = new Controller();
            $controller->render('errors/403', ['title' => 'دسترسی غیرمجاز'], 'app');
            exit;
        }
    }

    public static function rootPath()
    {
        return dirname(__DIR__) . DIRECTORY_SEPARATOR . 'plugins';
    }

    public function install($pluginId, $userId = null)
    {
        $manifest = $this->manifestById($pluginId);
        $this->assertRequirements($manifest);
        $existing = PluginRegistry::find($manifest['id']);
        if ($existing && in_array($existing['status'], ['installed', 'active', 'inactive'], true)) {
            throw new InvalidArgumentException('این افزونه قبلاً نصب شده است.');
        }
        $this->ensureRegistryTables();
        PluginRegistry::upsert($manifest, $manifest['_root'], $userId);
        try {
            $this->runMigrations($manifest);
            $provider = $this->providerFor($manifest);
            $provider->install($this, $manifest);
            $this->registerManifestPermissions($manifest);
            $this->audit('plugin', 'installed', $manifest['id'], $userId, ['version' => $manifest['version']]);
        } catch (Throwable $e) {
            PluginRegistry::setStatus($manifest['id'], 'failed', $e->getMessage());
            throw $e;
        }
    }

    public function activate($pluginId, $userId = null)
    {
        $registered = PluginRegistry::find($pluginId);
        if (!$registered || !in_array($registered['status'], ['installed', 'inactive'], true)) {
            throw new InvalidArgumentException('افزونه باید ابتدا نصب و سالم باشد.');
        }
        $manifest = $this->manifestFromRegistered($registered);
        $this->assertRequirements($manifest);
        $provider = $this->loadProvider($registered, false);
        if (method_exists($provider, 'activate')) {
            $provider->activate($this, $manifest);
        }
        PluginRegistry::setStatus($manifest['id'], 'active');
        $this->audit('plugin', 'activated', $manifest['id'], $userId);
    }

    public function deactivate($pluginId, $userId = null)
    {
        $registered = PluginRegistry::find($pluginId);
        if (!$registered || ($registered['status'] ?? '') !== 'active') {
            throw new InvalidArgumentException('افزونه فعال نیست.');
        }
        $manifest = $this->manifestFromRegistered($registered);
        $provider = $this->loadProvider($registered, false);
        if (method_exists($provider, 'deactivate')) {
            $provider->deactivate($this, $manifest);
        }
        PluginRegistry::setStatus($manifest['id'], 'inactive');
        $this->audit('plugin', 'deactivated', $manifest['id'], $userId);
    }

    public function uninstall($pluginId, $userId = null, $purge = false, $confirmation = '')
    {
        $registered = PluginRegistry::find($pluginId);
        if (!$registered) {
            throw new InvalidArgumentException('افزونه نصب نشده است.');
        }
        if ($purge && trim((string) $confirmation) !== 'حذف کامل اطلاعات افزونه') {
            throw new InvalidArgumentException('تایید حذف کامل افزونه صحیح نیست.');
        }
        $manifest = $this->manifestFromRegistered($registered);
        $provider = $this->loadProvider($registered, false);
        if (method_exists($provider, 'uninstall')) {
            $provider->uninstall($this, $manifest, $purge);
        }
        PluginRegistry::setStatus($manifest['id'], 'uninstalled');
        if ($purge) {
            $this->removePluginFiles($registered['path']);
        }
        $this->audit('plugin', $purge ? 'purged' : 'uninstalled', $manifest['id'], $userId);
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
            return ['ok' => false, 'message' => $e->getMessage()];
        }
    }

    public function upload(array $file, $userId = null)
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
        $destinationName = $pluginRoot === $temp ? $manifest['id'] : basename($pluginRoot);
        $destination = self::rootPath() . DIRECTORY_SEPARATOR . $destinationName;
        if (is_dir($destination) || file_exists($destination)) {
            $this->removePluginFiles($temp);
            throw new InvalidArgumentException('پوشه افزونه از قبل وجود دارد. ابتدا نسخه فعلی را بررسی کنید.');
        }
        if (!is_dir(self::rootPath()) && !mkdir(self::rootPath(), 0755, true)) {
            $this->removePluginFiles($temp);
            throw new RuntimeException('ساخت پوشه افزونه انجام نشد.');
        }
        if (!rename($pluginRoot, $destination)) {
            $this->removePluginFiles($temp);
            throw new RuntimeException('انتقال افزونه به پوشه plugins انجام نشد.');
        }
        $this->removePluginFiles($temp);
        $this->audit('plugin', 'uploaded', $manifest['id'], $userId, ['version' => $manifest['version']]);
        return $manifest;
    }

    public function registerRoute($pluginId, $path, $handler, array $options = [])
    {
        $path = trim((string) $path, '/');
        if ($path === '' || strpos($path, '..') !== false || !is_string($handler) || strpos($handler, '@') === false) {
            throw new InvalidArgumentException('Route افزونه معتبر نیست.');
        }
        foreach ($this->routes as $route) {
            if (($route['path'] ?? '') === $path) {
                throw new InvalidArgumentException('تعارض route افزونه شناسایی شد.');
            }
        }
        $this->routes[] = [
            'plugin_id' => (string) $pluginId,
            'path' => $path,
            'handler' => $handler,
            'options' => $options,
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
        ];
    }

    public function menus()
    {
        return $this->menus;
    }

    public static function fire($event, array $payload = [], $critical = false)
    {
        self::boot();
        return PluginHooks::dispatch($event, $payload, $critical);
    }

    public function dispatchRoute($route)
    {
        self::boot();
        $route = trim((string) $route, '/');
        foreach ($this->routes as $item) {
            $parameters = $this->matchRoute($item['path'], $route);
            if ($parameters === false) {
                continue;
            }
            $options = $item['options'];
            if (($options['method'] ?? '') !== '' && strtoupper((string) $options['method']) !== strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'))) {
                http_response_code(405);
                return true;
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
            call_user_func_array([$controller, $method], $parameters);
            return true;
        }
        return false;
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
            throw new RuntimeException('نسخه Proma Pay برای این افزونه کافی نیست.');
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
                Model::begin();
                foreach ($this->splitSql($sql) as $statement) {
                    Model::db()->exec($statement);
                }
                PluginRegistry::recordMigration($manifest['id'], $name, hash('sha256', $sql), 'success', (int) round((microtime(true) - $started) * 1000));
                Model::commit();
            } catch (Throwable $e) {
                Model::rollBack();
                try {
                    PluginRegistry::recordMigration($manifest['id'], $name, hash('sha256', $sql), 'failed', (int) round((microtime(true) - $started) * 1000), $e->getMessage());
                } catch (Throwable $ignored) {
                }
                throw new RuntimeException('اجرای migration افزونه ' . $name . ' ناموفق بود: ' . $e->getMessage(), 0, $e);
            }
        }
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

    protected function removePluginFiles($path)
    {
        $path = rtrim((string) $path, '/\\');
        if ($path === '' || !is_dir($path)) {
            return;
        }
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($iterator as $item) {
            if ($item->isDir()) {
                @rmdir($item->getPathname());
            } else {
                @unlink($item->getPathname());
            }
        }
        @rmdir($path);
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
