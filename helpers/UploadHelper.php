<?php

class UploadHelper
{
    public const IMAGE_EXTENSIONS = ['jpg', 'jpeg', 'png', 'webp'];
    public const LOGO_EXTENSIONS = ['jpg', 'jpeg', 'png', 'webp', 'svg'];
    public const FAVICON_EXTENSIONS = ['ico', 'jpg', 'jpeg', 'png', 'webp', 'svg'];
    public const DOCUMENT_EXTENSIONS = ['jpg', 'jpeg', 'png', 'webp', 'pdf'];
    public const MAX_IMAGE_SIZE = 10485760;
    public const MAX_DOCUMENT_SIZE = 10485760;
    public const MAX_AVATAR_SIZE = 5242880;

    public static function storeImage(array $upload, $subdir, array $extensions = self::IMAGE_EXTENSIONS, array $context = [])
    {
        return self::storeSecureFile($upload, $subdir, $extensions, self::MAX_IMAGE_SIZE, $context);
    }

    public static function storeAvatar(array $upload, $userId)
    {
        if (empty($upload['tmp_name']) || (int) ($upload['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return null;
        }
        self::validateAvatarImage($upload['tmp_name'], (int) ($upload['size'] ?? 0));
        return self::storeSecureFile($upload, 'avatars/' . (int) $userId, self::IMAGE_EXTENSIONS, self::MAX_AVATAR_SIZE, [
            'category' => 'avatar',
            'visibility' => 'private',
            'related_entity_type' => 'user',
            'related_entity_id' => (int) $userId,
            'relation_type' => 'avatar',
            'description' => 'تصویر آواتار حساب کاربری',
        ]);
    }

    public static function validateAvatarImage($path, $size = null)
    {
        if (!is_file($path)) {
            throw new InvalidArgumentException('فایل آواتار معتبر نیست.');
        }
        $size = $size === null ? filesize($path) : (int) $size;
        if ($size <= 0 || $size > self::MAX_AVATAR_SIZE) {
            throw new InvalidArgumentException('حجم آواتار باید حداکثر ۵ مگابایت باشد.');
        }
        $image = @getimagesize($path);
        if (!$image || empty($image[0]) || empty($image[1])) {
            throw new InvalidArgumentException('محتوای فایل آواتار یک تصویر معتبر نیست.');
        }
        $width = (int) $image[0];
        $height = (int) $image[1];
        $mime = (string) ($image['mime'] ?? '');
        if (!in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true)) {
            throw new InvalidArgumentException('آواتار فقط با فرمت JPEG، PNG یا WebP مجاز است.');
        }
        if ($width < 64 || $height < 64 || $width > 4096 || $height > 4096 || ($width * $height) > 16777216) {
            throw new InvalidArgumentException('ابعاد آواتار باید بین ۶۴ تا ۴۰۹۶ پیکسل باشد.');
        }
        return ['width' => $width, 'height' => $height, 'mime' => $mime];
    }

    public static function storeSecureFile(array $upload, $subdir, array $extensions = self::DOCUMENT_EXTENSIONS, $maxSize = self::MAX_DOCUMENT_SIZE, array $context = [])
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
        $relativePath = 'storage/secure_uploads/' . $safeSubdir . '/' . date('Y') . '/' . date('m') . '/' . $fileName;
        self::registerManagedFile($relativePath, $upload, array_merge(['category' => self::categoryFromSubdir($safeSubdir)], $context));
        return $relativePath;
    }

    public static function storePublicImage(array $upload, $subdir, array $extensions = self::LOGO_EXTENSIONS, array $context = [])
    {
        if (empty($upload['tmp_name']) || (int) ($upload['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return null;
        }
        if ((int) ($upload['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK || !is_uploaded_file($upload['tmp_name'])) {
            throw new InvalidArgumentException('فایل به‌درستی بارگذاری نشد.');
        }
        if ((int) ($upload['size'] ?? 0) > self::MAX_IMAGE_SIZE) {
            throw new InvalidArgumentException('حجم هر تصویر باید حداکثر ۱۰ مگابایت باشد.');
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
        $relativePath = 'storage/uploads/' . $safeSubdir . '/' . $fileName;
        self::registerManagedFile($relativePath, $upload, array_merge([
            'category' => self::categoryFromSubdir($safeSubdir),
            'visibility' => 'public',
        ], $context));
        return $relativePath;
    }

    public static function absolutePath($relativePath)
    {
        $relativePath = ltrim(str_replace(['..', '\\'], ['', '/'], (string) $relativePath), '/');
        $root = realpath(dirname(__DIR__));
        $path = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, ltrim($relativePath, '/'));
        $allowedRoot = strpos($relativePath, 'storage/uploads/') === 0
            ? $root . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'uploads'
            : self::secureBaseDir();
        $secureRoot = realpath($allowedRoot);
        $dir = realpath(dirname($path));
        if (!$secureRoot || !$dir || strpos($dir, $secureRoot) !== 0) {
            return null;
        }
        return is_file($path) ? $path : null;
    }

    public static function deleteRelative($relativePath)
    {
        if (class_exists('FileRecord') && FileRecord::archiveByPath($relativePath, 'بایگانی فایل در جریان عملیاتی سیستم')) {
            return true;
        }
        $path = self::absolutePath($relativePath);
        if ($path && is_file($path)) {
            if (!unlink($path)) {
                throw new RuntimeException('حذف فایل امن انجام نشد.');
            }
            return true;
        }
        return false;
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

    protected static function registerManagedFile($relativePath, array $upload, array $context)
    {
        if (!class_exists('FileRecord')) {
            return;
        }
        try {
            FileRecord::registerStored($relativePath, $upload, $context);
        } catch (Throwable $e) {
            ErrorHandler::log('upload_file_registry', $e, 500);
        }
    }

    protected static function categoryFromSubdir($subdir)
    {
        $first = explode('/', trim((string) $subdir, '/'))[0] ?? 'general';
        return preg_replace('/[^a-z0-9_-]/i', '_', $first) ?: 'general';
    }
}
