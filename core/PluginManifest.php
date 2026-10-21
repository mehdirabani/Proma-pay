<?php

class PluginManifest
{
    public static function read($pluginRoot)
    {
        $pluginRoot = self::safeRoot($pluginRoot);
        $path = $pluginRoot . DIRECTORY_SEPARATOR . 'plugin.json';
        if (!is_file($path)) {
            throw new InvalidArgumentException('فایل plugin.json افزونه پیدا نشد.');
        }
        $data = json_decode((string) file_get_contents($path), true);
        if (!is_array($data)) {
            throw new InvalidArgumentException('Manifest افزونه JSON معتبر نیست.');
        }
        return self::validate($data, $pluginRoot);
    }

    public static function validate(array $manifest, $pluginRoot = null)
    {
        foreach (['id', 'name', 'version', 'plugin_api_version', 'requires_core', 'requires_php', 'provider'] as $field) {
            if (!isset($manifest[$field]) || trim((string) $manifest[$field]) === '') {
                throw new InvalidArgumentException('فیلد ' . $field . ' در Manifest افزونه الزامی است.');
            }
        }
        $id = trim((string) $manifest['id']);
        if (!preg_match('/^[a-z][a-z0-9._-]{2,99}$/', $id)) {
            throw new InvalidArgumentException('شناسه افزونه فرمت امنی ندارد.');
        }
        if (!preg_match('/^\d+\.\d+\.\d+(?:[-+][0-9A-Za-z.-]+)?$/', trim((string) $manifest['version']))) {
            throw new InvalidArgumentException('نسخه افزونه باید semantic version باشد.');
        }
        $provider = trim((string) $manifest['provider']);
        if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]*(\\\\[A-Za-z_][A-Za-z0-9_]*)+$/', $provider)) {
            throw new InvalidArgumentException('کلاس provider افزونه معتبر نیست.');
        }
        foreach (['permissions', 'dependencies', 'routes', 'migrations', 'assets', 'settings'] as $listField) {
            if (isset($manifest[$listField]) && !is_array($manifest[$listField])) {
                throw new InvalidArgumentException('فیلد ' . $listField . ' باید آرایه باشد.');
            }
        }
        $manifest['id'] = $id;
        $manifest['name'] = trim((string) $manifest['name']);
        $manifest['version'] = trim((string) $manifest['version']);
        $manifest['permissions'] = array_values($manifest['permissions'] ?? []);
        $manifest['dependencies'] = array_values($manifest['dependencies'] ?? []);
        $manifest['routes'] = array_values($manifest['routes'] ?? []);
        $manifest['migrations'] = array_values($manifest['migrations'] ?? []);
        $manifest['assets'] = array_values($manifest['assets'] ?? []);
        $manifest['settings'] = array_values($manifest['settings'] ?? []);
        if ($pluginRoot !== null) {
            $manifest['_root'] = self::safeRoot($pluginRoot);
        }
        return $manifest;
    }

    public static function providerFile(array $manifest)
    {
        $root = self::safeRoot($manifest['_root'] ?? '');
        $shortName = substr(strrchr((string) $manifest['provider'], '\\'), 1);
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getFilename() === $shortName . '.php') {
                return $file->getPathname();
            }
        }
        throw new InvalidArgumentException('فایل provider افزونه پیدا نشد.');
    }

    protected static function safeRoot($root)
    {
        $root = rtrim((string) $root, '/\\');
        if ($root === '' || !is_dir($root)) {
            throw new InvalidArgumentException('مسیر افزونه معتبر نیست.');
        }
        return $root;
    }
}
