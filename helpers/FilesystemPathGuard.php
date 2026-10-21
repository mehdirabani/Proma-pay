<?php

final class FilesystemPathGuard
{
    /** Compare resolved paths with native case semantics and a directory boundary. */
    public static function contains(string $root, string $path): bool
    {
        $root = rtrim(str_replace('\\', '/', $root), '/');
        $path = rtrim(str_replace('\\', '/', $path), '/');
        if ($root === '') $root = '/';
        $windows = DIRECTORY_SEPARATOR === '\\' || preg_match('#^(?:[a-z]:/|//)#i', $root);
        if ($windows) { $root = strtolower($root); $path = strtolower($path); }
        return $path === $root || str_starts_with($path, rtrim($root, '/') . '/');
    }

    public static function requireParent(string $root, string $directory, string $label): void
    {
        $existing = $directory;
        while (!is_dir($existing)) {
            $parent = dirname($existing);
            if ($parent === $existing) throw new RuntimeException('پوشه مقصد پیدا نشد: ' . $label);
            $existing = $parent;
        }
        $resolved = realpath($existing);
        if ($resolved === false || !self::contains($root, $resolved)) {
            throw new RuntimeException('مسیر مقصد از پوشه مجاز خارج است: ' . $label);
        }
        if (!is_dir($directory) && !@mkdir($directory, 0755, true) && !is_dir($directory)) {
            throw new RuntimeException('ساخت پوشه مقصد انجام نشد: ' . $label . '؛ مجوز نوشتن و فضای دیسک را بررسی کنید.');
        }
        clearstatcache(true, $directory);
        $resolved = realpath($directory);
        if ($resolved === false || !self::contains($root, $resolved)) throw new RuntimeException('مسیر مقصد از پوشه مجاز خارج است: ' . $label);
    }
}
