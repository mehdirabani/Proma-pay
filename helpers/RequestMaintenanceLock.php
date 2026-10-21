<?php

/** Keeps application requests out of a database/file restore in progress. */
final class RequestMaintenanceLock
{
    private static $handle;
    private static $exclusive = false;

    public static function enter()
    {
        if (self::$handle) return;
        $directory = dirname(__DIR__) . '/storage/.runtime-lock';
        if (!is_dir($directory) && !@mkdir($directory, 0700, true)) {
            throw new RuntimeException('ساخت قفل نگهداری سامانه انجام نشد.');
        }
        $handle = fopen($directory . '/restore.lock', 'c+');
        if (!$handle || !flock($handle, LOCK_SH | LOCK_NB)) {
            if ($handle) fclose($handle);
            throw new RuntimeException('بازیابی بکاپ در حال انجام است؛ چند لحظه بعد دوباره تلاش کنید.', 503);
        }
        self::$handle = $handle;
        register_shutdown_function([self::class, 'release']);
        if (is_file($directory . '/restore-pending.json')) {
            self::release();
            throw new RuntimeException('بازیابی قبلی کامل نشده است؛ بکاپ ایمنی باید بازیابی شود.', 503);
        }
    }

    public static function exclusive()
    {
        self::enter();
        if (self::$exclusive) throw new RuntimeException('بازیابی هم‌زمان مجاز نیست.');
        // Nonblocking promotion cannot deadlock with another restore request.
        if (!flock(self::$handle, LOCK_EX | LOCK_NB)) {
            flock(self::$handle, LOCK_SH);
            throw new RuntimeException('سامانه درخواست فعال دارد؛ پس از پایان آن بازیابی را دوباره اجرا کنید.', 409);
        }
        self::$exclusive = true;
    }

    public static function shared()
    {
        if (self::$handle) flock(self::$handle, LOCK_SH);
        self::$exclusive = false;
    }

    public static function markPending($backupName, $journalDirectory = null)
    {
        $path = dirname(__DIR__) . '/storage/.runtime-lock/restore-pending.json';
        if (file_put_contents($path, json_encode(['safety_backup' => basename($backupName), 'journal_directory' => $journalDirectory, 'started_at' => date('c')]), LOCK_EX) === false) {
            throw new RuntimeException('ثبت نشانگر ایمنی بازیابی انجام نشد.');
        }
    }

    public static function clearPending()
    {
        $path = dirname(__DIR__) . '/storage/.runtime-lock/restore-pending.json';
        if (is_file($path) && !unlink($path)) throw new RuntimeException('حذف نشانگر بازیابی انجام نشد.');
    }

    public static function release()
    {
        if (self::$handle) {
            flock(self::$handle, LOCK_UN);
            fclose(self::$handle);
        }
        self::$handle = null;
        self::$exclusive = false;
    }
}
