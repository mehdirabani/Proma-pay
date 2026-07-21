<?php

final class PluginStatus
{
    const DISCOVERED = 'discovered';
    const UPLOADED = 'uploaded';
    const VALIDATING = 'validating';
    const INSTALLED = 'installed';
    const ACTIVE = 'active';
    const INACTIVE = 'inactive';
    const ACTIVATING = 'activating';
    const DEACTIVATING = 'deactivating';
    const UNINSTALLING = 'uninstalling';
    const ERROR = 'error';
    const RECOVERY_REQUIRED = 'recovery_required';

    // Kept as aliases for packages created before the unified lifecycle.
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
            self::VALIDATING,
            self::INSTALLED,
            self::ACTIVE,
            self::INACTIVE,
            self::ACTIVATING,
            self::DEACTIVATING,
            self::INSTALLING,
            self::UPDATING,
            self::UNINSTALLING,
            self::ERROR,
            self::RECOVERY_REQUIRED,
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
        if (in_array($status, [self::FAILED, self::INSTALLATION_FAILED, self::UPDATE_FAILED, self::MIGRATION_FAILED, self::HEALTH_FAILED], true)) {
            return self::ERROR;
        }
        if ($status === self::REPAIR_REQUIRED) {
            return self::RECOVERY_REQUIRED;
        }
        return in_array($status, self::all(), true) ? $status : self::DISCOVERED;
    }

    public static function label($status)
    {
        $labels = [
            self::DISCOVERED => 'شناسایی‌شده',
            self::UPLOADED => 'آماده نصب',
            self::VALIDATING => 'در حال اعتبارسنجی',
            self::INSTALLED => 'نصب‌شده',
            self::ACTIVE => 'فعال',
            self::INACTIVE => 'غیرفعال',
            self::ACTIVATING => 'در حال فعال‌سازی',
            self::DEACTIVATING => 'در حال غیرفعال‌سازی',
            self::INSTALLING => 'در حال نصب',
            self::UPDATING => 'در حال بروزرسانی',
            self::UNINSTALLING => 'در حال حذف',
            self::ERROR => 'دارای خطا',
            self::RECOVERY_REQUIRED => 'نیازمند بازیابی',
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
        return (bool) $hasFiles && in_array(self::normalize($status), [self::DISCOVERED, self::UPLOADED, self::REMOVED, self::ERROR, self::RECOVERY_REQUIRED], true);
    }
}
