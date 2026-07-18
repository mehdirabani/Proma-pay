<?php

final class PluginStatus
{
    const DISCOVERED = 'discovered';
    const UPLOADED = 'uploaded';
    const INSTALLED = 'installed';
    const ACTIVE = 'active';
    const INACTIVE = 'inactive';
    const INSTALLING = 'installing';
    const UPDATING = 'updating';
    const INSTALLATION_FAILED = 'installation_failed';
    const UPDATE_FAILED = 'update_failed';
    const MIGRATION_FAILED = 'migration_failed';
    const HEALTH_FAILED = 'health_failed';
    const REPAIR_REQUIRED = 'repair_required';
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
            self::INSTALLING,
            self::UPDATING,
            self::INSTALLATION_FAILED,
            self::UPDATE_FAILED,
            self::MIGRATION_FAILED,
            self::HEALTH_FAILED,
            self::REPAIR_REQUIRED,
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
            self::INSTALLING => 'در حال نصب',
            self::UPDATING => 'در حال بروزرسانی',
            self::INSTALLATION_FAILED => 'نصب ناموفق',
            self::UPDATE_FAILED => 'بروزرسانی ناموفق',
            self::MIGRATION_FAILED => 'خطای migration',
            self::HEALTH_FAILED => 'سلامت ناموفق',
            self::REPAIR_REQUIRED => 'نیازمند تعمیر',
            self::FAILED => 'دارای خطا',
            self::UPDATE_AVAILABLE => 'بروزرسانی موجود',
            self::REMOVED => 'حذف‌شده',
        ];
        $status = self::normalize($status);
        return $labels[$status];
    }

    public static function canInstall($status, $hasFiles)
    {
        return (bool) $hasFiles && in_array(self::normalize($status), [self::DISCOVERED, self::UPLOADED, self::REMOVED, self::FAILED, self::INSTALLATION_FAILED, self::UPDATE_FAILED, self::MIGRATION_FAILED, self::HEALTH_FAILED, self::REPAIR_REQUIRED], true);
    }
}
