<?php

final class PluginStatus
{
    const DISCOVERED = 'discovered';
    const UPLOADED = 'uploaded';
    const INSTALLED = 'installed';
    const ACTIVE = 'active';
    const INACTIVE = 'inactive';
    const FAILED = 'failed';
    const UPDATE_AVAILABLE = 'update_available';
    const REMOVED = 'removed';

    public static function all()
    {
        return [
            self::DISCOVERED,
            self::UPLOADED,
            self::INSTALLED,
            self::ACTIVE,
            self::INACTIVE,
            self::FAILED,
            self::UPDATE_AVAILABLE,
            self::REMOVED,
        ];
    }

    public static function normalize($status)
    {
        $status = strtolower(trim((string) $status));
        if (in_array($status, ['deleted', 'uninstalled'], true)) {
            return self::REMOVED;
        }
        return in_array($status, self::all(), true) ? $status : self::DISCOVERED;
    }

    public static function label($status)
    {
        $labels = [
            self::DISCOVERED => 'شناسایی‌شده',
            self::UPLOADED => 'آماده نصب',
            self::INSTALLED => 'نصب‌شده',
            self::ACTIVE => 'فعال',
            self::INACTIVE => 'غیرفعال',
            self::FAILED => 'دارای خطا',
            self::UPDATE_AVAILABLE => 'بروزرسانی موجود',
            self::REMOVED => 'حذف‌شده',
        ];
        $status = self::normalize($status);
        return $labels[$status];
    }

    public static function canInstall($status, $hasFiles)
    {
        return (bool) $hasFiles && in_array(self::normalize($status), [self::DISCOVERED, self::UPLOADED, self::REMOVED, self::FAILED], true);
    }
}
