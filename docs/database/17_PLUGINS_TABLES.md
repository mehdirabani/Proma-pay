# 17 — Plugins Tables

مستند جدول‌های پلاگین‌ها، بسته‌های پلاگین، نصب، فعال‌سازی، تنظیمات، Permissionها، Routeها، Hookها، Migrationها، اسکن امنیتی و داده‌های وابسته به Plugins Domain در پروژه **Proma Pay**

---

## فهرست مطالب

- [هدف فایل](#هدف-فایل)
- [تعریف Plugins Domain در دیتابیس](#تعریف-plugins-domain-در-دیتابیس)
- [اصل مهم](#اصل-مهم)
- [لیست جدول‌های Plugins](#لیست-جدولهای-plugins)
- [جدول plugins](#جدول-plugins)
- [جدول plugin_packages](#جدول-plugin_packages)
- [جدول plugin_installations](#جدول-plugin_installations)
- [جدول plugin_status_histories](#جدول-plugin_status_histories)
- [جدول plugin_settings](#جدول-plugin_settings)
- [جدول plugin_permissions](#جدول-plugin_permissions)
- [جدول plugin_routes](#جدول-plugin_routes)
- [جدول plugin_hooks](#جدول-plugin_hooks)
- [جدول plugin_migrations](#جدول-plugin_migrations)
- [جدول plugin_security_scans](#جدول-plugin_security_scans)
- [جدول plugin_logs](#جدول-plugin_logs)
- [رابطه Plugins Domain با سایر Domainها](#رابطه-plugins-domain-با-سایر-domainها)
- [قوانین نصب پلاگین](#قوانین-نصب-پلاگین)
- [قوانین فعال‌سازی و غیرفعال‌سازی](#قوانین-فعالسازی-و-غیرفعالسازی)
- [قوانین امنیت پلاگین](#قوانین-امنیت-پلاگین)
- [قوانین تنظیمات پلاگین](#قوانین-تنظیمات-پلاگین)
- [قوانین Permission پلاگین](#قوانین-permission-پلاگین)
- [قوانین Migration پلاگین](#قوانین-migration-پلاگین)
- [قوانین Soft Delete](#قوانین-soft-delete)
- [قوانین Index و Performance](#قوانین-index-و-performance)
- [قوانین Validation](#قوانین-validation)
- [قوانین Audit و Security Log](#قوانین-audit-و-security-log)
- [Seedهای پیشنهادی](#seedهای-پیشنهادی)
- [چک‌لیست پیاده‌سازی](#چکلیست-پیادهسازی)
- [Definition of Done](#definition-of-done)

---

## هدف فایل

هدف این فایل این است که ساختار جدول‌های مربوط به پلاگین‌ها در پروژه **Proma Pay** مشخص شود.

Plugins Domain برای توسعه‌پذیر کردن سیستم استفاده می‌شود؛ اما چون پلاگین‌ها می‌توانند به بخش‌های حساس سیستم دسترسی داشته باشند، باید با کنترل امنیتی شدید طراحی شوند.

این فایل برای Codex مشخص می‌کند که:

- پلاگین‌ها چگونه ثبت شوند.
- بسته ZIP پلاگین چگونه نگهداری شود.
- نصب، فعال‌سازی، غیرفعال‌سازی و حذف پلاگین چگونه Log شود.
- تنظیمات پلاگین چگونه ذخیره شوند.
- Secretهای پلاگین چگونه امن نگهداری شوند.
- Permissionهای پلاگین چگونه تعریف شوند.
- Routeها و Hookهای پلاگین چگونه ثبت شوند.
- Migrationهای پلاگین چگونه اجرا شوند.
- اسکن امنیتی پلاگین چگونه ذخیره شود.
- پلاگین چگونه با Backup، Files، Settings، Security و Audit ارتباط داشته باشد.

---

## تعریف Plugins Domain در دیتابیس

Plugins Domain مسئول مدیریت افزونه‌هایی است که امکانات جانبی به سیستم اضافه می‌کنند.

نمونه پلاگین‌ها:

- درگاه پرداخت جدید
- Provider پیامک
- اتصال Telegram
- اتصال WhatsApp
- Export سفارشی
- گزارش اختصاصی
- کانال Notification
- ابزار مالی کمکی
- اتصال به حسابداری
- ابزار Import
- ابزار Automation داخلی

پلاگین نباید Core سیستم را ناامن کند.

---

## اصل مهم

اصل مهم در Plugins Tables:

> پلاگین اختیاری است؛ اما امنیت سیستم اختیاری نیست.

پلاگین نباید بتواند:

- Permission را دور بزند.
- CSRF را دور بزند.
- مستقیم مبلغ مالی را تغییر دهد.
- مستقیم فایل حساس را Public کند.
- مستقیم SQL خام خطرناک اجرا کند.
- بدون ثبت Audit عملیات حساس انجام دهد.
- بدون ثبت Financial Log تغییر مالی ایجاد کند.
- بدون Backup نصب یا بروزرسانی حساس انجام دهد.
- Secret را خام در دیتابیس یا Log ذخیره کند.

قانون طلایی:

> پلاگین فقط از طریق API، Service، Permission و Eventهای رسمی سیستم مجاز به تعامل با Core است.

---

## لیست جدول‌های Plugins

جدول‌های پیشنهادی Plugins Domain:

| جدول | کاربرد |
|---|---|
| `plugins` | اطلاعات اصلی پلاگین نصب‌شده |
| `plugin_packages` | بسته‌های آپلودشده پلاگین |
| `plugin_installations` | عملیات نصب، بروزرسانی یا حذف پلاگین |
| `plugin_status_histories` | تاریخچه تغییر وضعیت پلاگین |
| `plugin_settings` | تنظیمات پلاگین |
| `plugin_permissions` | Permissionهای تعریف‌شده توسط پلاگین |
| `plugin_routes` | Routeهای ثبت‌شده توسط پلاگین |
| `plugin_hooks` | Hookها و Event Listenerهای پلاگین |
| `plugin_migrations` | Migrationهای اجراشده پلاگین |
| `plugin_security_scans` | نتیجه اسکن امنیتی پلاگین |
| `plugin_logs` | لاگ‌های داخلی پلاگین |

---

## جدول plugins

### هدف جدول

جدول `plugins` اطلاعات اصلی پلاگین‌های نصب‌شده را نگهداری می‌کند.

---

### نام جدول

```text
plugins
```

---

### ستون‌های پیشنهادی

| ستون | نوع | توضیح |
|---|---|---|
| `id` | BIGINT UNSIGNED AUTO_INCREMENT | شناسه داخلی |
| `plugin_number` | VARCHAR(50) | شماره رسمی پلاگین |
| `plugin_id` | VARCHAR(100) | شناسه یکتای پلاگین |
| `name` | VARCHAR(191) | نام پلاگین |
| `description` | TEXT NULL | توضیح |
| `version` | VARCHAR(50) | نسخه فعلی |
| `type` | VARCHAR(50) | نوع پلاگین |
| `author` | VARCHAR(191) NULL | سازنده |
| `author_url` | VARCHAR(255) NULL | آدرس سازنده |
| `status` | VARCHAR(50) | وضعیت پلاگین |
| `is_core` | TINYINT(1) | پلاگین سیستمی |
| `is_enabled` | TINYINT(1) | فعال بودن |
| `is_verified` | TINYINT(1) | تأییدشده بودن |
| `requires_backup` | TINYINT(1) | نیاز به بکاپ |
| `min_proma_version` | VARCHAR(50) NULL | حداقل نسخه سیستم |
| `max_proma_version` | VARCHAR(50) NULL | حداکثر نسخه سیستم |
| `min_php_version` | VARCHAR(50) NULL | حداقل نسخه PHP |
| `installed_package_id` | BIGINT UNSIGNED NULL | بسته نصب‌شده |
| `installed_at` | DATETIME NULL | زمان نصب |
| `installed_by` | BIGINT UNSIGNED NULL | نصب‌کننده |
| `enabled_at` | DATETIME NULL | زمان فعال‌سازی |
| `enabled_by` | BIGINT UNSIGNED NULL | فعال‌کننده |
| `disabled_at` | DATETIME NULL | زمان غیرفعال‌سازی |
| `disabled_by` | BIGINT UNSIGNED NULL | غیرفعال‌کننده |
| `disable_reason` | TEXT NULL | دلیل غیرفعال‌سازی |
| `metadata` | JSON NULL | داده تکمیلی |
| `created_at` | DATETIME NULL | زمان ایجاد |
| `updated_at` | DATETIME NULL | زمان بروزرسانی |
| `deleted_at` | DATETIME NULL | زمان حذف نرم |
| `deleted_by` | BIGINT UNSIGNED NULL | حذف‌کننده |
| `delete_reason` | TEXT NULL | دلیل حذف |

---

### typeهای پیشنهادی

```text
payment_gateway
notification_channel
report
export
integration
automation
ui_extension
security
financial_tool
other
```

---

### statusهای پیشنهادی

```text
uploaded
validated
installed
enabled
disabled
update_available
updating
failed
uninstalled
blocked
deleted
```

---

### قوانین

- `plugin_number` باید Unique باشد.
- `plugin_id` باید Unique باشد.
- plugin_id باید فقط شامل حروف کوچک انگلیسی، عدد، خط تیره یا آندرلاین باشد.
- پلاگین blocked نباید فعال شود.
- پلاگین failed نباید Route یا Hook فعال داشته باشد.
- پلاگین core نباید از UI عادی حذف شود.
- فعال‌سازی پلاگین باید Audit Log داشته باشد.
- غیرفعال‌سازی پلاگین باید Audit Log داشته باشد.
- اگر پلاگین requires_backup دارد، نصب یا بروزرسانی آن باید Backup ایجاد کند.
- پلاگین نباید بدون Security Scan نصب شود.
- پلاگین نباید Secret خام در metadata ذخیره کند.

---

### Indexهای پیشنهادی

```text
uniq_plugins_plugin_number
uniq_plugins_plugin_id
idx_plugins_type
idx_plugins_status
idx_plugins_is_core
idx_plugins_is_enabled
idx_plugins_is_verified
idx_plugins_requires_backup
idx_plugins_version
idx_plugins_installed_package_id
idx_plugins_installed_at
idx_plugins_deleted_at
```

---

### نمونه ساختار SQL

```sql
CREATE TABLE plugins (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    plugin_number VARCHAR(50) NOT NULL,
    plugin_id VARCHAR(100) NOT NULL,
    name VARCHAR(191) NOT NULL,
    description TEXT NULL,
    version VARCHAR(50) NOT NULL,
    type VARCHAR(50) NOT NULL DEFAULT 'other',
    author VARCHAR(191) NULL,
    author_url VARCHAR(255) NULL,
    status VARCHAR(50) NOT NULL DEFAULT 'uploaded',
    is_core TINYINT(1) NOT NULL DEFAULT 0,
    is_enabled TINYINT(1) NOT NULL DEFAULT 0,
    is_verified TINYINT(1) NOT NULL DEFAULT 0,
    requires_backup TINYINT(1) NOT NULL DEFAULT 1,
    min_proma_version VARCHAR(50) NULL,
    max_proma_version VARCHAR(50) NULL,
    min_php_version VARCHAR(50) NULL,
    installed_package_id BIGINT UNSIGNED NULL,
    installed_at DATETIME NULL,
    installed_by BIGINT UNSIGNED NULL,
    enabled_at DATETIME NULL,
    enabled_by BIGINT UNSIGNED NULL,
    disabled_at DATETIME NULL,
    disabled_by BIGINT UNSIGNED NULL,
    disable_reason TEXT NULL,
    metadata JSON NULL,
    created_at DATETIME NULL,
    updated_at DATETIME NULL,
    deleted_at DATETIME NULL,
    deleted_by BIGINT UNSIGNED NULL,
    delete_reason TEXT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uniq_plugins_plugin_number (plugin_number),
    UNIQUE KEY uniq_plugins_plugin_id (plugin_id),
    KEY idx_plugins_type (type),
    KEY idx_plugins_status (status),
    KEY idx_plugins_is_core (is_core),
    KEY idx_plugins_is_enabled (is_enabled),
    KEY idx_plugins_is_verified (is_verified),
    KEY idx_plugins_requires_backup (requires_backup),
    KEY idx_plugins_version (version),
    KEY idx_plugins_installed_package_id (installed_package_id),
    KEY idx_plugins_installed_at (installed_at),
    KEY idx_plugins_deleted_at (deleted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

---

## جدول plugin_packages

### هدف جدول

جدول `plugin_packages` بسته‌های آپلودشده پلاگین را نگهداری می‌کند.

بسته پلاگین معمولاً فایل ZIP است و فایل واقعی باید در Files Domain و Private Storage ذخیره شود.

---

### نام جدول

```text
plugin_packages
```

---

### ستون‌های پیشنهادی

| ستون | نوع | توضیح |
|---|---|---|
| `id` | BIGINT UNSIGNED AUTO_INCREMENT | شناسه |
| `package_number` | VARCHAR(50) | شماره رسمی بسته |
| `plugin_id` | VARCHAR(100) | شناسه پلاگین |
| `version` | VARCHAR(50) | نسخه بسته |
| `file_id` | BIGINT UNSIGNED | فایل ZIP در Files Domain |
| `package_name` | VARCHAR(191) NULL | نام بسته |
| `package_type` | VARCHAR(50) | نوع بسته |
| `status` | VARCHAR(50) | وضعیت بسته |
| `checksum_sha256` | VARCHAR(128) NULL | هش بسته |
| `manifest_data` | JSON NULL | داده plugin.json |
| `validation_status` | VARCHAR(50) | وضعیت اعتبارسنجی |
| `validated_at` | DATETIME NULL | زمان اعتبارسنجی |
| `validated_by` | BIGINT UNSIGNED NULL | اعتبارسنجی‌کننده |
| `validation_error` | TEXT NULL | خطای اعتبارسنجی |
| `uploaded_by` | BIGINT UNSIGNED NULL | آپلودکننده |
| `uploaded_at` | DATETIME NULL | زمان آپلود |
| `metadata` | JSON NULL | داده تکمیلی |
| `created_at` | DATETIME NULL | زمان ایجاد |
| `updated_at` | DATETIME NULL | زمان بروزرسانی |

---

### package_typeهای پیشنهادی

```text
install
update
patch
hotfix
rollback
manual
```

---

### statusهای پیشنهادی

```text
uploaded
validating
validated
invalid
ready
installed
failed
blocked
archived
```

---

### validation_statusهای پیشنهادی

```text
pending
passed
failed
blocked
```

---

### قوانین

- `package_number` باید Unique باشد.
- ترکیب plugin_id و version بهتر است Unique باشد.
- هر بسته باید file_id معتبر داشته باشد.
- فایل بسته باید Private باشد.
- بسته پلاگین باید قبل از نصب Validate شود.
- بسته invalid یا blocked نباید نصب شود.
- فایل ZIP نباید مسیر `../` داشته باشد.
- فایل ZIP نباید مسیر Absolute داشته باشد.
- فایل ZIP نباید `.env` داشته باشد.
- فایل ZIP نباید فایل اجرایی خطرناک داشته باشد.
- plugin_id داخل Manifest باید با فولدر پلاگین سازگار باشد.
- manifest_data نباید Secret خام داشته باشد.

---

### Indexهای پیشنهادی

```text
uniq_plugin_packages_package_number
idx_plugin_packages_plugin_id
idx_plugin_packages_version
idx_plugin_packages_file_id
idx_plugin_packages_package_type
idx_plugin_packages_status
idx_plugin_packages_validation_status
idx_plugin_packages_uploaded_by
idx_plugin_packages_uploaded_at
```

---

## جدول plugin_installations

### هدف جدول

جدول `plugin_installations` عملیات نصب، بروزرسانی، حذف یا Rollback پلاگین را نگهداری می‌کند.

---

### نام جدول

```text
plugin_installations
```

---

### ستون‌های پیشنهادی

| ستون | نوع | توضیح |
|---|---|---|
| `id` | BIGINT UNSIGNED AUTO_INCREMENT | شناسه |
| `installation_number` | VARCHAR(50) | شماره رسمی عملیات |
| `plugin_id` | VARCHAR(100) | شناسه پلاگین |
| `package_id` | BIGINT UNSIGNED NULL | بسته پلاگین |
| `operation_type` | VARCHAR(50) | نوع عملیات |
| `from_version` | VARCHAR(50) NULL | نسخه قبلی |
| `to_version` | VARCHAR(50) NULL | نسخه جدید |
| `status` | VARCHAR(50) | وضعیت عملیات |
| `requires_backup` | TINYINT(1) | نیاز به بکاپ |
| `backup_id` | BIGINT UNSIGNED NULL | بکاپ مرتبط |
| `started_by` | BIGINT UNSIGNED NULL | شروع‌کننده |
| `approved_by` | BIGINT UNSIGNED NULL | تأییدکننده |
| `approved_at` | DATETIME NULL | زمان تأیید |
| `started_at` | DATETIME NULL | زمان شروع |
| `completed_at` | DATETIME NULL | زمان تکمیل |
| `failed_at` | DATETIME NULL | زمان شکست |
| `duration_seconds` | INT UNSIGNED NULL | مدت اجرا |
| `rollback_available` | TINYINT(1) | امکان Rollback |
| `rolled_back_at` | DATETIME NULL | زمان Rollback |
| `rolled_back_by` | BIGINT UNSIGNED NULL | انجام‌دهنده Rollback |
| `rollback_reason` | TEXT NULL | دلیل Rollback |
| `error_message` | TEXT NULL | پیام خطا |
| `metadata` | JSON NULL | داده تکمیلی |
| `created_at` | DATETIME NULL | زمان ایجاد |
| `updated_at` | DATETIME NULL | زمان بروزرسانی |

---

### operation_typeهای پیشنهادی

```text
install
update
enable
disable
uninstall
rollback
repair
```

---

### statusهای پیشنهادی

```text
pending
approved
running
completed
failed
cancelled
rolled_back
blocked
```

---

### قوانین

- `installation_number` باید Unique باشد.
- نصب یا بروزرسانی باید package_id داشته باشد.
- بسته باید validation_status = passed داشته باشد.
- اگر requires_backup فعال است، backup_id الزامی است.
- نصب پلاگین باید داخل روند کنترل‌شده انجام شود.
- عملیات failed باید error_message داشته باشد.
- عملیات completed باید plugin status را بروزرسانی کند.
- عملیات uninstall نباید اطلاعات مالی یا حقوقی را بدون Policy حذف کند.
- Rollback باید reason داشته باشد.
- نصب، بروزرسانی و حذف باید Audit و Security Log داشته باشند.

---

### Indexهای پیشنهادی

```text
uniq_plugin_installations_installation_number
idx_plugin_installations_plugin_id
idx_plugin_installations_package_id
idx_plugin_installations_operation_type
idx_plugin_installations_status
idx_plugin_installations_backup_id
idx_plugin_installations_started_by
idx_plugin_installations_started_at
idx_plugin_installations_completed_at
idx_plugin_installations_failed_at
```

---

## جدول plugin_status_histories

### هدف جدول

جدول `plugin_status_histories` تاریخچه تغییر وضعیت پلاگین را نگهداری می‌کند.

---

### نام جدول

```text
plugin_status_histories
```

---

### ستون‌های پیشنهادی

| ستون | نوع | توضیح |
|---|---|---|
| `id` | BIGINT UNSIGNED AUTO_INCREMENT | شناسه |
| `plugin_id` | VARCHAR(100) | شناسه پلاگین |
| `old_status` | VARCHAR(50) NULL | وضعیت قبلی |
| `new_status` | VARCHAR(50) | وضعیت جدید |
| `old_version` | VARCHAR(50) NULL | نسخه قبلی |
| `new_version` | VARCHAR(50) NULL | نسخه جدید |
| `reason` | TEXT NULL | دلیل تغییر |
| `changed_by` | BIGINT UNSIGNED NULL | تغییر‌دهنده |
| `changed_at` | DATETIME | زمان تغییر |
| `metadata` | JSON NULL | داده تکمیلی |

---

### قوانین

- هر تغییر وضعیت مهم پلاگین باید ثبت شود.
- فعال‌سازی باید ثبت شود.
- غیرفعال‌سازی باید reason داشته باشد.
- blocked شدن پلاگین باید reason داشته باشد.
- بروزرسانی نسخه باید ثبت شود.
- این جدول نباید Soft Delete شود.

---

### Indexهای پیشنهادی

```text
idx_plugin_status_histories_plugin_id
idx_plugin_status_histories_new_status
idx_plugin_status_histories_old_version
idx_plugin_status_histories_new_version
idx_plugin_status_histories_changed_by
idx_plugin_status_histories_changed_at
```

---

## جدول plugin_settings

### هدف جدول

جدول `plugin_settings` تنظیمات پلاگین‌ها را نگهداری می‌کند.

برای Secretهای پلاگین، مقدار خام نباید در این جدول ذخیره شود؛ باید از Secure Settings استفاده شود.

---

### نام جدول

```text
plugin_settings
```

---

### ستون‌های پیشنهادی

| ستون | نوع | توضیح |
|---|---|---|
| `id` | BIGINT UNSIGNED AUTO_INCREMENT | شناسه |
| `plugin_id` | VARCHAR(100) | شناسه پلاگین |
| `setting_key` | VARCHAR(150) | کلید تنظیم |
| `name` | VARCHAR(191) | نام نمایشی |
| `description` | TEXT NULL | توضیح |
| `value` | LONGTEXT NULL | مقدار غیرحساس |
| `value_type` | VARCHAR(50) | نوع مقدار |
| `default_value` | LONGTEXT NULL | مقدار پیش‌فرض |
| `is_sensitive` | TINYINT(1) | حساس بودن |
| `secure_setting_key` | VARCHAR(150) NULL | کلید Secure Setting |
| `is_required` | TINYINT(1) | الزامی بودن |
| `is_readonly` | TINYINT(1) | فقط خواندنی |
| `validation_rules` | JSON NULL | قوانین اعتبارسنجی |
| `sort_order` | INT UNSIGNED | ترتیب نمایش |
| `status` | VARCHAR(50) | وضعیت |
| `created_at` | DATETIME NULL | زمان ایجاد |
| `updated_at` | DATETIME NULL | زمان بروزرسانی |
| `updated_by` | BIGINT UNSIGNED NULL | ویرایش‌کننده |

---

### value_typeهای پیشنهادی

```text
string
text
integer
decimal
boolean
json
array
url
email
secure_reference
```

---

### statusهای پیشنهادی

```text
active
inactive
deprecated
readonly
```

---

### قوانین

- ترکیب plugin_id و setting_key باید Unique باشد.
- setting_key باید با namespace پلاگین سازگار باشد.
- مقدار حساس نباید خام در value ذخیره شود.
- اگر is_sensitive فعال است، secure_setting_key باید وجود داشته باشد.
- Secretهای پلاگین باید در secure_settings ذخیره شوند.
- تغییر تنظیم حساس پلاگین باید Audit و Security Log داشته باشد.
- پلاگین نباید تنظیمات Core را مستقیم تغییر دهد، مگر از API رسمی.
- تنظیمات پلاگین حذف‌شده باید inactive یا deprecated شوند.

---

### Indexهای پیشنهادی

```text
uniq_plugin_settings_plugin_key
idx_plugin_settings_plugin_id
idx_plugin_settings_setting_key
idx_plugin_settings_value_type
idx_plugin_settings_is_sensitive
idx_plugin_settings_secure_setting_key
idx_plugin_settings_status
idx_plugin_settings_updated_by
```

---

## جدول plugin_permissions

### هدف جدول

جدول `plugin_permissions` Permissionهای تعریف‌شده توسط پلاگین را نگهداری می‌کند.

---

### نام جدول

```text
plugin_permissions
```

---

### ستون‌های پیشنهادی

| ستون | نوع | توضیح |
|---|---|---|
| `id` | BIGINT UNSIGNED AUTO_INCREMENT | شناسه |
| `plugin_id` | VARCHAR(100) | شناسه پلاگین |
| `permission_key` | VARCHAR(150) | کلید Permission |
| `name` | VARCHAR(191) | نام نمایشی |
| `description` | TEXT NULL | توضیح |
| `permission_group` | VARCHAR(100) NULL | گروه Permission |
| `is_sensitive` | TINYINT(1) | حساس بودن |
| `is_assignable` | TINYINT(1) | قابل اختصاص به نقش |
| `status` | VARCHAR(50) | وضعیت |
| `created_at` | DATETIME NULL | زمان ایجاد |
| `updated_at` | DATETIME NULL | زمان بروزرسانی |

---

### نمونه Permission Key

```text
plugin.sms_provider.view
plugin.sms_provider.manage
plugin.payment_gateway.view
plugin.payment_gateway.manage
plugin.custom_report.export
```

---

### قوانین

- `permission_key` باید Unique باشد.
- Permission پلاگین باید با `plugin.{plugin_id}.` شروع شود.
- Permission حساس باید is_sensitive = 1 داشته باشد.
- Permission پلاگین نباید هم‌نام Permissionهای Core باشد.
- حذف پلاگین نباید Permissionهای استفاده‌شده در Audit را فیزیکی حذف کند.
- Permission غیرفعال نباید برای دسترسی جدید استفاده شود.
- Permissionهای پلاگین باید با Users/Roles/Permissions Domain هماهنگ شوند.

---

### Indexهای پیشنهادی

```text
uniq_plugin_permissions_permission_key
idx_plugin_permissions_plugin_id
idx_plugin_permissions_permission_group
idx_plugin_permissions_is_sensitive
idx_plugin_permissions_is_assignable
idx_plugin_permissions_status
```

---

## جدول plugin_routes

### هدف جدول

جدول `plugin_routes` مسیرهای ثبت‌شده توسط پلاگین را نگهداری می‌کند.

Routeهای پلاگین باید محدود، کنترل‌شده و Permission-based باشند.

---

### نام جدول

```text
plugin_routes
```

---

### ستون‌های پیشنهادی

| ستون | نوع | توضیح |
|---|---|---|
| `id` | BIGINT UNSIGNED AUTO_INCREMENT | شناسه |
| `plugin_id` | VARCHAR(100) | شناسه پلاگین |
| `route_key` | VARCHAR(150) | کلید Route |
| `http_method` | VARCHAR(20) | متد HTTP |
| `route_path` | VARCHAR(255) | مسیر Route |
| `handler` | VARCHAR(255) | Handler داخلی |
| `permission_key` | VARCHAR(150) NULL | Permission لازم |
| `requires_auth` | TINYINT(1) | نیاز به ورود |
| `requires_csrf` | TINYINT(1) | نیاز به CSRF |
| `is_api` | TINYINT(1) | API بودن |
| `is_active` | TINYINT(1) | فعال بودن |
| `metadata` | JSON NULL | داده تکمیلی |
| `created_at` | DATETIME NULL | زمان ایجاد |
| `updated_at` | DATETIME NULL | زمان بروزرسانی |

---

### قوانین

- `route_key` باید Unique باشد.
- Route پلاگین باید با `/plugins/{plugin_id}/` شروع شود.
- Route حساس باید permission_key داشته باشد.
- Route POST/PUT/PATCH/DELETE باید CSRF داشته باشد، مگر API رسمی با Token معتبر باشد.
- Route پلاگین نباید مسیر Core را Override کند.
- Route غیرفعال نباید Register شود.
- فعال‌سازی Route حساس باید Audit Log داشته باشد.
- Route نباید بدون Auth به داده حساس دسترسی دهد.

---

### Indexهای پیشنهادی

```text
uniq_plugin_routes_route_key
idx_plugin_routes_plugin_id
idx_plugin_routes_http_method
idx_plugin_routes_route_path
idx_plugin_routes_permission_key
idx_plugin_routes_requires_auth
idx_plugin_routes_requires_csrf
idx_plugin_routes_is_api
idx_plugin_routes_is_active
```

---

## جدول plugin_hooks

### هدف جدول

جدول `plugin_hooks` Hookها یا Event Listenerهای ثبت‌شده توسط پلاگین را نگهداری می‌کند.

پلاگین می‌تواند به Eventهای سیستم گوش بدهد، اما فقط در محدوده مجاز.

---

### نام جدول

```text
plugin_hooks
```

---

### ستون‌های پیشنهادی

| ستون | نوع | توضیح |
|---|---|---|
| `id` | BIGINT UNSIGNED AUTO_INCREMENT | شناسه |
| `plugin_id` | VARCHAR(100) | شناسه پلاگین |
| `hook_key` | VARCHAR(150) | کلید Hook |
| `event_name` | VARCHAR(150) | نام Event |
| `listener_class` | VARCHAR(255) | Listener |
| `priority` | INT | اولویت اجرا |
| `is_active` | TINYINT(1) | فعال بودن |
| `requires_permission` | TINYINT(1) | نیاز به Permission |
| `permission_key` | VARCHAR(150) NULL | Permission لازم |
| `run_async` | TINYINT(1) | اجرای غیرهمزمان |
| `metadata` | JSON NULL | داده تکمیلی |
| `created_at` | DATETIME NULL | زمان ایجاد |
| `updated_at` | DATETIME NULL | زمان بروزرسانی |

---

### Eventهای نمونه

```text
PaymentApproved
PaymentRejected
InstallmentOverdue
ContractCreated
LegalCaseCreated
NotificationCreated
BackupCompleted
BackupFailed
```

---

### قوانین

- `hook_key` باید Unique باشد.
- Hook باید plugin_id معتبر داشته باشد.
- event_name باید از Eventهای مجاز باشد.
- Hook نباید Eventهای مالی را بدون Service رسمی تغییر دهد.
- Hook مربوط به Payment باید Permission و Audit/Financial Log را رعایت کند.
- Hook فعال فقط برای پلاگین enabled اجرا شود.
- Hook failed باید در plugin_logs ثبت شود.
- Hookهای سنگین باید run_async باشند.
- Hook نباید Exception کنترل‌نشده باعث شکست Core Workflow شود.

---

### Indexهای پیشنهادی

```text
uniq_plugin_hooks_hook_key
idx_plugin_hooks_plugin_id
idx_plugin_hooks_event_name
idx_plugin_hooks_priority
idx_plugin_hooks_is_active
idx_plugin_hooks_requires_permission
idx_plugin_hooks_permission_key
idx_plugin_hooks_run_async
```

---

## جدول plugin_migrations

### هدف جدول

جدول `plugin_migrations` Migrationهای اجراشده توسط پلاگین را نگهداری می‌کند.

---

### نام جدول

```text
plugin_migrations
```

---

### ستون‌های پیشنهادی

| ستون | نوع | توضیح |
|---|---|---|
| `id` | BIGINT UNSIGNED AUTO_INCREMENT | شناسه |
| `plugin_id` | VARCHAR(100) | شناسه پلاگین |
| `migration_name` | VARCHAR(191) | نام Migration |
| `migration_batch` | INT UNSIGNED | Batch |
| `status` | VARCHAR(50) | وضعیت |
| `executed_at` | DATETIME NULL | زمان اجرا |
| `rolled_back_at` | DATETIME NULL | زمان Rollback |
| `checksum_sha256` | VARCHAR(128) NULL | هش فایل Migration |
| `error_message` | TEXT NULL | پیام خطا |
| `metadata` | JSON NULL | داده تکمیلی |
| `created_at` | DATETIME NULL | زمان ایجاد |

---

### statusهای پیشنهادی

```text
pending
executed
failed
rolled_back
skipped
blocked
```

---

### قوانین

- ترکیب plugin_id و migration_name باید Unique باشد.
- Migration پلاگین باید قبل از اجرا Validate شود.
- Migration destructive باید Backup بخواهد.
- Migration failed باید error_message داشته باشد.
- Migration نباید جدول‌های Core را بدون API/Migration Policy تغییر خطرناک دهد.
- Migration پلاگین باید با Migration Rules اصلی هماهنگ باشد.
- Rollback فقط اگر امن و تعریف‌شده باشد مجاز است.
- Migration اجراشده نباید بی‌ردپا حذف شود.

---

### Indexهای پیشنهادی

```text
uniq_plugin_migrations_plugin_name
idx_plugin_migrations_plugin_id
idx_plugin_migrations_migration_batch
idx_plugin_migrations_status
idx_plugin_migrations_executed_at
idx_plugin_migrations_rolled_back_at
```

---

## جدول plugin_security_scans

### هدف جدول

جدول `plugin_security_scans` نتیجه اسکن امنیتی پلاگین یا بسته پلاگین را ذخیره می‌کند.

---

### نام جدول

```text
plugin_security_scans
```

---

### ستون‌های پیشنهادی

| ستون | نوع | توضیح |
|---|---|---|
| `id` | BIGINT UNSIGNED AUTO_INCREMENT | شناسه |
| `plugin_id` | VARCHAR(100) NULL | شناسه پلاگین |
| `package_id` | BIGINT UNSIGNED NULL | بسته پلاگین |
| `scan_type` | VARCHAR(50) | نوع اسکن |
| `status` | VARCHAR(50) | وضعیت اسکن |
| `risk_level` | VARCHAR(50) | سطح ریسک |
| `result_message` | TEXT NULL | پیام نتیجه |
| `issues_count` | INT UNSIGNED | تعداد مشکلات |
| `blocked_reason` | TEXT NULL | دلیل Block |
| `scanned_by` | BIGINT UNSIGNED NULL | اسکن‌کننده |
| `scanned_at` | DATETIME NULL | زمان اسکن |
| `metadata` | JSON NULL | داده تکمیلی |
| `created_at` | DATETIME NULL | زمان ایجاد |

---

### scan_typeهای پیشنهادی

```text
zip_structure
manifest_check
path_traversal_check
dangerous_files_check
php_syntax_check
permission_check
migration_check
manual_review
```

---

### statusهای پیشنهادی

```text
pending
passed
failed
suspicious
blocked
approved_manually
```

---

### risk_levelهای پیشنهادی

```text
low
medium
high
critical
unknown
```

---

### قوانین

- اسکن باید package_id یا plugin_id داشته باشد.
- بسته blocked نباید نصب شود.
- risk_level critical باید پلاگین را blocked کند.
- اسکن Path Traversal الزامی است.
- اسکن فایل‌های خطرناک الزامی است.
- اسکن Manifest الزامی است.
- اسکن دستی باید scanned_by داشته باشد.
- نتیجه اسکن باید در نصب پلاگین لحاظ شود.
- اسکن امنیتی نباید حذف شود.

---

### Indexهای پیشنهادی

```text
idx_plugin_security_scans_plugin_id
idx_plugin_security_scans_package_id
idx_plugin_security_scans_scan_type
idx_plugin_security_scans_status
idx_plugin_security_scans_risk_level
idx_plugin_security_scans_scanned_by
idx_plugin_security_scans_scanned_at
```

---

## جدول plugin_logs

### هدف جدول

جدول `plugin_logs` لاگ‌های داخلی پلاگین را ذخیره می‌کند.

این جدول برای Debug، خطاها، هشدارها و ردیابی عملکرد پلاگین استفاده می‌شود.

---

### نام جدول

```text
plugin_logs
```

---

### ستون‌های پیشنهادی

| ستون | نوع | توضیح |
|---|---|---|
| `id` | BIGINT UNSIGNED AUTO_INCREMENT | شناسه |
| `plugin_id` | VARCHAR(100) | شناسه پلاگین |
| `log_level` | VARCHAR(50) | سطح لاگ |
| `log_type` | VARCHAR(50) | نوع لاگ |
| `message` | TEXT | پیام |
| `context` | JSON NULL | Context غیرحساس |
| `related_type` | VARCHAR(100) NULL | موجودیت مرتبط |
| `related_id` | BIGINT UNSIGNED NULL | شناسه موجودیت |
| `user_id` | BIGINT UNSIGNED NULL | کاربر مرتبط |
| `created_at` | DATETIME NULL | زمان ثبت |

---

### log_levelهای پیشنهادی

```text
debug
info
notice
warning
error
critical
```

---

### log_typeهای پیشنهادی

```text
runtime
hook
route
migration
setting
api
security
system
other
```

---

### قوانین

- لاگ پلاگین نباید Secret خام ذخیره کند.
- context نباید داده محرمانه غیرضروری داشته باشد.
- خطاهای Hook باید ثبت شوند.
- خطاهای Route پلاگین باید ثبت شوند.
- خطاهای امنیتی باید علاوه بر plugin_logs در Security Log هم ثبت شوند.
- لاگ‌های قدیمی می‌توانند طبق Archive Policy پاکسازی شوند.
- log_level critical باید Notification مدیریتی ایجاد کند.

---

### Indexهای پیشنهادی

```text
idx_plugin_logs_plugin_id
idx_plugin_logs_log_level
idx_plugin_logs_log_type
idx_plugin_logs_related
idx_plugin_logs_user_id
idx_plugin_logs_created_at
```

---

## رابطه Plugins Domain با سایر Domainها

Plugins Domain با بخش‌های مختلف سیستم ارتباط دارد.

| Domain | رابطه |
|---|---|
| Files | بسته ZIP پلاگین در Files Domain ذخیره می‌شود |
| Settings | تنظیمات و Secretهای پلاگین در Settings/Secure Settings ذخیره می‌شوند |
| Users/Roles/Permissions | Permissionهای پلاگین به نقش‌ها وصل می‌شوند |
| Backup & Update | نصب یا بروزرسانی پلاگین می‌تواند Backup بخواهد |
| Notifications | پلاگین می‌تواند کانال Notification اضافه کند |
| Payments | پلاگین می‌تواند درگاه پرداخت اضافه کند |
| Reports | پلاگین می‌تواند گزارش یا Export اضافه کند |
| Audit | نصب، حذف، فعال‌سازی و تغییرات حساس پلاگین Audit می‌شوند |
| Security | آپلود بسته خطرناک، دسترسی غیرمجاز و Route ناامن ثبت می‌شود |
| Database Migrations | پلاگین می‌تواند Migrationهای محدود و کنترل‌شده داشته باشد |

---

## قوانین نصب پلاگین

قوانین:

- بسته پلاگین باید در Files Domain و Private Storage ذخیره شود.
- بسته پلاگین باید Validate شود.
- بسته پلاگین باید Security Scan شود.
- بسته invalid یا blocked نباید نصب شود.
- نصب پلاگین باید Audit Log داشته باشد.
- نصب پلاگین باید Security Log داشته باشد.
- اگر پلاگین requires_backup دارد، قبل از نصب باید Backup ساخته شود.
- نصب باید در plugin_installations ثبت شود.
- Migrationهای پلاگین باید ثبت و کنترل شوند.
- پلاگین نصب‌شده باید status معتبر داشته باشد.

---

## قوانین فعال‌سازی و غیرفعال‌سازی

قوانین:

- فقط پلاگین installed یا disabled می‌تواند enabled شود.
- پلاگین blocked نباید enabled شود.
- پلاگین failed نباید enabled شود.
- فعال‌سازی باید Permission ویژه داشته باشد.
- غیرفعال‌سازی پلاگین حساس باید reason داشته باشد.
- Routeها و Hookهای پلاگین فقط وقتی فعال شوند که پلاگین enabled باشد.
- غیرفعال‌سازی نباید داده‌های پلاگین را حذف کند.
- Uninstall باید با احتیاط و Policy مشخص انجام شود.

---

## قوانین امنیت پلاگین

قوانین:

- پلاگین نباید مسیر `../` در ZIP داشته باشد.
- پلاگین نباید فایل `.env` داشته باشد.
- پلاگین نباید فایل اجرایی خطرناک داشته باشد.
- پلاگین نباید فایل خارج از فولدر خود نصب کند.
- plugin_id باید با نام فولدر و Manifest سازگار باشد.
- Route پلاگین باید Auth و CSRF مناسب داشته باشد.
- Permission پلاگین باید namespace مشخص داشته باشد.
- Secret پلاگین نباید خام ذخیره شود.
- پلاگین نباید جدول‌های Core را مستقیم و خطرناک تغییر دهد.
- پلاگین نباید بدون Service رسمی عملیات مالی انجام دهد.
- پلاگین نباید بدون Audit عملیات حساس انجام دهد.

---

## قوانین تنظیمات پلاگین

قوانین:

- تنظیمات پلاگین باید namespace داشته باشند.
- الگوی پیشنهادی:

```text
plugin.{plugin_id}.{setting_key}
```

- تنظیمات حساس باید در secure_settings ذخیره شوند.
- مقدار خام Secret نباید در plugin_settings ذخیره شود.
- مقدار Secret نباید در plugin_logs ذخیره شود.
- تغییر تنظیمات حساس باید Audit و Security Log داشته باشد.
- تنظیمات پلاگین حذف‌شده باید inactive یا deprecated شوند.
- پلاگین نباید تنظیمات Core را مستقیم تغییر دهد.

---

## قوانین Permission پلاگین

قوانین:

- Permission پلاگین باید با الگوی زیر شروع شود:

```text
plugin.{plugin_id}.
```

- Permission پلاگین نباید با Permission Core تداخل داشته باشد.
- Route حساس باید permission_key داشته باشد.
- Hook حساس باید permission_key یا کنترل Service داشته باشد.
- Permission پلاگین باید قابل اختصاص به Role باشد.
- حذف پلاگین نباید تاریخچه Permissionها را از Audit پاک کند.
- Permission غیرفعال نباید دسترسی جدید ایجاد کند.

---

## قوانین Migration پلاگین

قوانین:

- Migration پلاگین باید ثبت شود.
- Migration باید idempotent باشد.
- Migration مخرب باید Backup بخواهد.
- Migration نباید جدول Core را بدون قرارداد مشخص تغییر دهد.
- Migration failed باید error_message داشته باشد.
- Rollback فقط اگر امن باشد مجاز است.
- Migration اجراشده نباید فیزیکی حذف شود.
- نام جدول‌های پلاگین باید Prefix داشته باشند.

الگوی پیشنهادی نام جدول پلاگین:

```text
plugin_{plugin_id}_{table_name}
```

---

## قوانین Soft Delete

جدول‌های زیر باید Soft Delete داشته باشند:

- `plugins`

جدول‌های زیر معمولاً Soft Delete ندارند و با status کنترل می‌شوند:

- `plugin_packages`
- `plugin_installations`
- `plugin_status_histories`
- `plugin_migrations`
- `plugin_security_scans`
- `plugin_logs`

قوانین:

- پلاگین حذف‌شده باید deleted_at داشته باشد.
- پلاگین حذف‌شده نباید Route یا Hook فعال داشته باشد.
- حذف پلاگین نباید Audit، Log و Migration History را پاک کند.
- بسته پلاگین بهتر است archived یا blocked شود، نه حذف فیزیکی.
- Secretهای پلاگین باید inactive یا revoked شوند.
- داده‌های مالی یا حقوقی تولیدشده توسط پلاگین نباید با حذف پلاگین از بین بروند.

---

## قوانین Index و Performance

قوانین:

- پیدا کردن پلاگین با plugin_id باید سریع باشد.
- بررسی پلاگین‌های enabled باید سریع باشد.
- Routeهای پلاگین باید با plugin_id و route_path سریع پیدا شوند.
- Hookهای فعال باید با event_name سریع پیدا شوند.
- plugin_logs ممکن است بزرگ شود و باید Archive Policy داشته باشد.
- plugin_security_scans باید برای بررسی نصب سریع قابل فیلتر باشد.
- Queryها باید Pagination داشته باشند.

Indexهای مهم:

```text
plugins.plugin_id
plugins.status
plugins.is_enabled
plugin_packages.plugin_id
plugin_packages.version
plugin_routes.route_path
plugin_hooks.event_name
plugin_migrations.plugin_id
plugin_logs.plugin_id
plugin_logs.created_at
```

---

## قوانین Validation

### plugins

- plugin_number الزامی و یکتا است.
- plugin_id الزامی و یکتا است.
- name الزامی است.
- version الزامی است.
- type معتبر باشد.
- status معتبر باشد.
- plugin_id باید فرمت امن داشته باشد.
- پلاگین enabled باید installed_at داشته باشد.

### plugin_packages

- package_number الزامی و یکتا است.
- plugin_id الزامی است.
- version الزامی است.
- file_id الزامی است.
- validation_status معتبر باشد.
- invalid package باید validation_error داشته باشد.

### plugin_installations

- installation_number الزامی و یکتا است.
- plugin_id الزامی است.
- operation_type معتبر باشد.
- status معتبر باشد.
- نصب و آپدیت باید package_id داشته باشند.
- failed installation باید error_message داشته باشد.

### plugin_settings

- plugin_id الزامی است.
- setting_key الزامی است.
- ترکیب plugin_id و setting_key باید یکتا باشد.
- is_sensitive باید secure_setting_key داشته باشد.
- value_type معتبر باشد.

### plugin_permissions

- permission_key الزامی و یکتا است.
- permission_key باید با `plugin.{plugin_id}.` شروع شود.
- name الزامی است.
- status معتبر باشد.

### plugin_routes

- route_key الزامی و یکتا است.
- plugin_id الزامی است.
- http_method معتبر باشد.
- route_path باید با `/plugins/{plugin_id}/` شروع شود.
- Route حساس باید permission_key داشته باشد.

### plugin_hooks

- hook_key الزامی و یکتا است.
- plugin_id الزامی است.
- event_name الزامی است.
- listener_class الزامی است.
- priority باید عددی باشد.

### plugin_migrations

- plugin_id الزامی است.
- migration_name الزامی است.
- ترکیب plugin_id و migration_name باید یکتا باشد.
- status معتبر باشد.
- failed migration باید error_message داشته باشد.

### plugin_security_scans

- package_id یا plugin_id الزامی است.
- scan_type معتبر باشد.
- status معتبر باشد.
- risk_level معتبر باشد.
- blocked status باید blocked_reason داشته باشد.

---

## قوانین Audit و Security Log

### Audit Log الزامی برای:

- آپلود بسته پلاگین
- Validate کردن بسته پلاگین
- نصب پلاگین
- بروزرسانی پلاگین
- فعال‌سازی پلاگین
- غیرفعال‌سازی پلاگین
- حذف یا Uninstall پلاگین
- اجرای Migration پلاگین
- Rollback پلاگین
- تغییر تنظیمات پلاگین
- تغییر Secret پلاگین
- ثبت Route حساس پلاگین
- ثبت Hook حساس پلاگین
- تغییر Permissionهای پلاگین

### Security Log الزامی برای:

- تلاش آپلود پلاگین خطرناک
- تشخیص مسیر `../` در ZIP
- تشخیص فایل `.env` در بسته
- تشخیص فایل اجرایی خطرناک
- تلاش نصب پلاگین invalid یا blocked
- تلاش فعال‌سازی پلاگین بدون Permission
- تلاش اجرای Route پلاگین بدون Permission
- تلاش دسترسی پلاگین به داده خارج از Scope
- تلاش ذخیره Secret خام در Log
- CSRF نامعتبر در عملیات پلاگین
- تلاش تغییر Migration یا فایل پلاگین بدون Permission

---

## Seedهای پیشنهادی

### plugin_type

```text
payment_gateway
notification_channel
report
export
integration
automation
ui_extension
security
financial_tool
other
```

### plugin_status

```text
uploaded
validated
installed
enabled
disabled
update_available
updating
failed
uninstalled
blocked
deleted
```

### package_type

```text
install
update
patch
hotfix
rollback
manual
```

### operation_type

```text
install
update
enable
disable
uninstall
rollback
repair
```

### scan_type

```text
zip_structure
manifest_check
path_traversal_check
dangerous_files_check
php_syntax_check
permission_check
migration_check
manual_review
```

### log_level

```text
debug
info
notice
warning
error
critical
```

---

## چک‌لیست پیاده‌سازی

قبل از پیاده‌سازی Plugins Tables بررسی شود:

- [ ] جدول `plugins` ساخته شده است.
- [ ] `plugin_number` یکتا است.
- [ ] `plugin_id` یکتا و امن است.
- [ ] جدول `plugin_packages` ساخته شده است.
- [ ] بسته پلاگین در Files Domain و Private Storage ذخیره می‌شود.
- [ ] بسته پلاگین قبل از نصب Validate می‌شود.
- [ ] جدول `plugin_installations` ساخته شده است.
- [ ] نصب و بروزرسانی پلاگین Log می‌شود.
- [ ] جدول `plugin_status_histories` ساخته شده است.
- [ ] تغییرات وضعیت پلاگین ثبت می‌شوند.
- [ ] جدول `plugin_settings` ساخته شده است.
- [ ] Secretهای پلاگین خام ذخیره نمی‌شوند.
- [ ] جدول `plugin_permissions` ساخته شده است.
- [ ] Permissionهای پلاگین namespace دارند.
- [ ] جدول `plugin_routes` ساخته شده است.
- [ ] Routeهای پلاگین Auth، CSRF و Permission دارند.
- [ ] جدول `plugin_hooks` ساخته شده است.
- [ ] Hookهای پلاگین فقط در محدوده مجاز اجرا می‌شوند.
- [ ] جدول `plugin_migrations` ساخته شده است.
- [ ] Migrationهای پلاگین ثبت و کنترل می‌شوند.
- [ ] جدول `plugin_security_scans` ساخته شده است.
- [ ] بسته‌های خطرناک Block می‌شوند.
- [ ] جدول `plugin_logs` ساخته شده است.
- [ ] عملیات حساس Audit Log دارند.
- [ ] تلاش‌های غیرمجاز Security Log دارند.

---

## Definition of Done

Plugins Tables زمانی کامل هستند که:

- پلاگین با شناسه یکتا و امن قابل ثبت باشد.
- بسته پلاگین در Files Domain و Private Storage ذخیره شود.
- بسته پلاگین قبل از نصب Validate و Security Scan شود.
- بسته invalid یا blocked نصب نشود.
- نصب، بروزرسانی، فعال‌سازی، غیرفعال‌سازی و حذف پلاگین قابل ردیابی باشد.
- وضعیت پلاگین تاریخچه داشته باشد.
- تنظیمات پلاگین قابل ذخیره باشد.
- Secretهای پلاگین فقط به صورت امن و رمزنگاری‌شده نگهداری شوند.
- Permissionهای پلاگین namespace استاندارد داشته باشند.
- Routeهای پلاگین Auth، CSRF و Permission را رعایت کنند.
- Hookهای پلاگین کنترل‌شده و قابل Log باشند.
- Migrationهای پلاگین ثبت، کنترل و قابل بررسی باشند.
- اسکن امنیتی پلاگین قابل ذخیره و بررسی باشد.
- پلاگین نتواند Core Security، Permission، Financial Log یا Audit را دور بزند.
- عملیات حساس Audit Log داشته باشند.
- تلاش‌های غیرمجاز Security Log داشته باشند.
- جدول‌ها با MySQL/MariaDB و PHP 7.4+ سازگار باشند.
- Codex بتواند از روی این مستندات Migrationهای Plugins Domain را بسازد.

---

## پایان فایل
````
