<?php

class UploadHelper
{
    public const IMAGE_EXTENSIONS = ['jpg', 'jpeg', 'png', 'webp'];
    public const LOGO_EXTENSIONS = ['jpg', 'jpeg', 'png', 'webp', 'svg'];
    public const FAVICON_EXTENSIONS = ['ico', 'jpg', 'jpeg', 'png', 'webp', 'svg'];
    public const DOCUMENT_EXTENSIONS = ['jpg', 'jpeg', 'png', 'webp', 'pdf'];
    public const MAX_IMAGE_SIZE = 1048576;
    public const MAX_DOCUMENT_SIZE = 5242880;

    public static function storeImage(array $upload, $subdir, array $extensions = self::IMAGE_EXTENSIONS)
    {
        return self::storeSecureFile($upload, $subdir, $extensions, self::MAX_IMAGE_SIZE);
    }

    public static function storeSecureFile(array $upload, $subdir, array $extensions = self::DOCUMENT_EXTENSIONS, $maxSize = self::MAX_DOCUMENT_SIZE)
    {
        if (empty($upload['tmp_name']) || (int) ($upload['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return null;
        }
        if ((int) ($upload['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK || !is_uploaded_file($upload['tmp_name'])) {
            throw new InvalidArgumentException('فایل به‌درستی بارگذاری نشد.');
        }
        if ((int) ($upload['size'] ?? 0) > (int) $maxSize) {
            throw new InvalidArgumentException('حجم فایل از محدوده مجاز بیشتر است.');
        }

        $extension = strtolower(pathinfo($upload['name'] ?? '', PATHINFO_EXTENSION));
        if (!in_array($extension, $extensions, true)) {
            throw new InvalidArgumentException('فرمت فایل مجاز نیست.');
        }

        $mime = self::detectMime($upload['tmp_name']);
        $allowedMimes = [
            'ico' => ['image/x-icon', 'image/vnd.microsoft.icon', 'application/octet-stream'],
            'jpg' => ['image/jpeg'],
            'jpeg' => ['image/jpeg'],
            'png' => ['image/png'],
            'webp' => ['image/webp'],
            'pdf' => ['application/pdf'],
            'svg' => ['image/svg+xml', 'text/plain'],
        ];
        if (!in_array($mime, $allowedMimes[$extension] ?? [], true)) {
            throw new InvalidArgumentException('نوع واقعی فایل معتبر نیست.');
        }

        $base = self::secureBaseDir();
        $safeSubdir = trim(preg_replace('/[^a-z0-9_\-\/]/i', '', str_replace('\\', '/', (string) $subdir)), '/');
        $dir = $base . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $safeSubdir) . DIRECTORY_SEPARATOR . date('Y') . DIRECTORY_SEPARATOR . date('m');
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        $fileName = bin2hex(random_bytes(16)) . '.' . $extension;
        $destination = $dir . DIRECTORY_SEPARATOR . $fileName;
        if (!move_uploaded_file($upload['tmp_name'], $destination)) {
            throw new RuntimeException('ذخیره فایل انجام نشد.');
        }
        return 'storage/secure_uploads/' . $safeSubdir . '/' . date('Y') . '/' . date('m') . '/' . $fileName;
    }

    public static function storePublicImage(array $upload, $subdir, array $extensions = self::LOGO_EXTENSIONS)
    {
        if (empty($upload['tmp_name']) || (int) ($upload['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return null;
        }
        if ((int) ($upload['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK || !is_uploaded_file($upload['tmp_name'])) {
            throw new InvalidArgumentException('فایل به‌درستی بارگذاری نشد.');
        }
        if ((int) ($upload['size'] ?? 0) > self::MAX_IMAGE_SIZE) {
            throw new InvalidArgumentException('حجم هر تصویر باید حداکثر ۱ مگابایت باشد.');
        }
        $extension = strtolower(pathinfo($upload['name'] ?? '', PATHINFO_EXTENSION));
        if (!in_array($extension, $extensions, true)) {
            throw new InvalidArgumentException('فرمت فایل مجاز نیست.');
        }
        $mime = self::detectMime($upload['tmp_name']);
        $allowedMimes = [
            'jpg' => ['image/jpeg'],
            'jpeg' => ['image/jpeg'],
            'png' => ['image/png'],
            'webp' => ['image/webp'],
            'svg' => ['image/svg+xml', 'text/plain'],
        ];
        if (!in_array($mime, $allowedMimes[$extension] ?? [], true)) {
            throw new InvalidArgumentException('نوع واقعی فایل معتبر نیست.');
        }
        $safeSubdir = trim(preg_replace('/[^a-z0-9_\-\/]/i', '', str_replace('\\', '/', (string) $subdir)), '/');
        $dir = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $safeSubdir);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        $fileName = bin2hex(random_bytes(16)) . '.' . $extension;
        $destination = $dir . DIRECTORY_SEPARATOR . $fileName;
        if (!move_uploaded_file($upload['tmp_name'], $destination)) {
            throw new RuntimeException('ذخیره فایل انجام نشد.');
        }
        return 'storage/uploads/' . $safeSubdir . '/' . $fileName;
    }

    public static function absolutePath($relativePath)
    {
        $relativePath = str_replace(['..', '\\'], ['', '/'], (string) $relativePath);
        $root = realpath(dirname(__DIR__));
        $path = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, ltrim($relativePath, '/'));
        $secureRoot = realpath(self::secureBaseDir());
        $dir = realpath(dirname($path));
        if (!$secureRoot || !$dir || strpos($dir, $secureRoot) !== 0) {
            return null;
        }
        return is_file($path) ? $path : null;
    }

    public static function deleteRelative($relativePath)
    {
        $path = self::absolutePath($relativePath);
        if ($path && is_file($path)) {
            @unlink($path);
        }
    }

    protected static function secureBaseDir()
    {
        $dir = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'secure_uploads';
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        $htaccess = $dir . DIRECTORY_SEPARATOR . '.htaccess';
        if (!is_file($htaccess)) {
            file_put_contents($htaccess, "Require all denied\nDeny from all\n");
        }
        return $dir;
    }

    protected static function detectMime($path)
    {
        if (function_exists('finfo_open')) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            if ($finfo) {
                $mime = finfo_file($finfo, $path);
                finfo_close($finfo);
                return $mime ?: '';
            }
        }
        return mime_content_type($path) ?: '';
    }
}
