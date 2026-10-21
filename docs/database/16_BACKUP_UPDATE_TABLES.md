# 16 — Backup & Update Tables

مستند جدول‌های بکاپ، بازیابی، آپدیت سیستم، بسته‌های بروزرسانی، لاگ اجرای عملیات، وضعیت نسخه‌ها و داده‌های وابسته به Backup & Update Domain در پروژه **Proma Pay**

---

## فهرست مطالب

- [هدف فایل](#هدف-فایل)
- [تعریف Backup & Update Domain در دیتابیس](#تعریف-backup--update-domain-در-دیتابیس)
- [اصل مهم](#اصل-مهم)
- [لیست جدول‌های Backup و Update](#لیست-جدولهای-backup-و-update)
- [جدول backups](#جدول-backups)
- [جدول backup_files](#جدول-backup_files)
- [جدول backup_schedules](#جدول-backup_schedules)
- [جدول backup_restore_logs](#جدول-backup_restore_logs)
- [جدول update_packages](#جدول-update_packages)
- [جدول update_installations](#جدول-update_installations)
- [جدول update_steps](#جدول-update_steps)
- [جدول system_versions](#جدول-system_versions)
- [جدول maintenance_logs](#جدول-maintenance_logs)
- [رابطه Backup & Update Domain با سایر Domainها](#رابطه-backup--update-domain-با-سایر-domainها)
- [قوانین بکاپ](#قوانین-بکاپ)
- [قوانین فایل بکاپ](#قوانین-فایل-بکاپ)
- [قوانین Restore](#قوانین-restore)
- [قوانین Update](#قوانین-update)
- [قوانین Maintenance Mode](#قوانین-maintenance-mode)
- [قوانین Soft Delete](#قوانین-soft-delete)
- [قوانین Index و Performance](#قوانین-index-و-performance)
- [قوانین Validation](#قوانین-validation)
- [قوانین Audit و Security Log](#قوانین-audit-و-security-log)
- [Seedهای پیشنهادی](#seedهای-پیشنهادی)
- [چک‌لیست پیاده‌سازی](#چکلیست-پیادهسازی)
- [Definition of Done](#definition-of-done)

---

## هدف فایل

هدف این فایل این است که ساختار جدول‌های مربوط به بکاپ، بازیابی و بروزرسانی سیستم در پروژه **Proma Pay** مشخص شود.

Backup & Update Domain برای محافظت از داده‌ها، کنترل نسخه سیستم، مدیریت بسته‌های بروزرسانی و ثبت دقیق عملیات حساس استفاده می‌شود.

این فایل برای Codex مشخص می‌کند که:

- بکاپ‌ها چگونه ثبت شوند.
- فایل‌های بکاپ چگونه در Files Domain نگهداری شوند.
- بکاپ‌های زمان‌بندی‌شده چگونه اجرا شوند.
- Restore چگونه Log شود.
- بسته‌های Update چگونه ثبت و بررسی شوند.
- نصب Update چگونه مرحله‌به‌مرحله ذخیره شود.
- نسخه فعلی سیستم چگونه نگهداری شود.
- Maintenance Mode چگونه ثبت شود.
- چه عملیات‌هایی نیاز به Audit Log و Security Log دارند.

---

## تعریف Backup & Update Domain در دیتابیس

Backup & Update Domain مسئول مدیریت عملیات زیر است:

- ساخت بکاپ دیتابیس
- ساخت بکاپ فایل‌ها
- ساخت بکاپ کامل سیستم
- ذخیره Metadata فایل بکاپ
- زمان‌بندی بکاپ خودکار
- Restore کردن بکاپ
- ثبت لاگ Restore
- آپلود بسته Update
- بررسی امنیتی بسته Update
- نصب Update
- اجرای Migrationهای Update
- ثبت نسخه سیستم
- فعال یا غیرفعال کردن Maintenance Mode

---

## اصل مهم

اصل مهم در Backup & Update Tables:

> هیچ عملیات Restore یا Update نباید بدون بکاپ معتبر، Permission ویژه، Audit Log و Security Log انجام شود.

Restore و Update جزو حساس‌ترین عملیات‌های سیستم هستند؛ چون می‌توانند:

- داده مالی را تغییر دهند.
- ساختار دیتابیس را تغییر دهند.
- فایل‌های سیستم را تغییر دهند.
- پلاگین‌ها را ناسازگار کنند.
- داده‌های مشتریان و قراردادها را در معرض خطر قرار دهند.
- باعث از دست رفتن اطلاعات شوند.

قانون طلایی:

> قبل از هر Update یا Restore حساس، سیستم باید وضعیت فعلی را Snapshot/Backup کند و عملیات را مرحله‌به‌مرحله Log کند.

---

## لیست جدول‌های Backup و Update

جدول‌های پیشنهادی Backup & Update Domain:

| جدول | کاربرد |
|---|---|
| `backups` | اطلاعات اصلی بکاپ‌ها |
| `backup_files` | فایل‌های مربوط به هر بکاپ |
| `backup_schedules` | زمان‌بندی بکاپ‌های خودکار |
| `backup_restore_logs` | لاگ عملیات Restore |
| `update_packages` | بسته‌های بروزرسانی |
| `update_installations` | نصب بروزرسانی‌ها |
| `update_steps` | مراحل نصب Update |
| `system_versions` | نسخه‌های سیستم |
| `maintenance_logs` | لاگ فعال‌سازی Maintenance Mode |

---

## جدول backups

### هدف جدول

جدول `backups` اطلاعات اصلی هر بکاپ را نگهداری می‌کند.

هر بکاپ می‌تواند دیتابیس، فایل‌ها یا کل سیستم را شامل شود.

---

### نام جدول

```text
backups
```

---

### ستون‌های پیشنهادی

| ستون | نوع | توضیح |
|---|---|---|
| `id` | BIGINT UNSIGNED AUTO_INCREMENT | شناسه داخلی |
| `backup_number` | VARCHAR(50) | شماره رسمی بکاپ |
| `backup_type` | VARCHAR(50) | نوع بکاپ |
| `backup_scope` | VARCHAR(50) | محدوده بکاپ |
| `status` | VARCHAR(50) | وضعیت بکاپ |
| `trigger_type` | VARCHAR(50) | نوع شروع عملیات |
| `reason` | TEXT NULL | دلیل ساخت بکاپ |
| `started_at` | DATETIME NULL | زمان شروع |
| `completed_at` | DATETIME NULL | زمان تکمیل |
| `failed_at` | DATETIME NULL | زمان شکست |
| `duration_seconds` | INT UNSIGNED NULL | مدت اجرا |
| `database_included` | TINYINT(1) | شامل دیتابیس |
| `files_included` | TINYINT(1) | شامل فایل‌ها |
| `plugins_included` | TINYINT(1) | شامل پلاگین‌ها |
| `storage_disk_key` | VARCHAR(100) NULL | محل ذخیره‌سازی |
| `total_size_bytes` | BIGINT UNSIGNED NULL | حجم کل |
| `files_count` | INT UNSIGNED NULL | تعداد فایل‌ها |
| `checksum_sha256` | VARCHAR(128) NULL | هش بکاپ |
| `is_encrypted` | TINYINT(1) | رمزنگاری‌شده |
| `is_restorable` | TINYINT(1) | قابل Restore بودن |
| `created_by` | BIGINT UNSIGNED NULL | ایجادکننده |
| `error_message` | TEXT NULL | پیام خطا |
| `metadata` | JSON NULL | داده تکمیلی |
| `created_at` | DATETIME NULL | زمان ایجاد |
| `updated_at` | DATETIME NULL | زمان بروزرسانی |
| `deleted_at` | DATETIME NULL | زمان حذف نرم |
| `deleted_by` | BIGINT UNSIGNED NULL | حذف‌کننده |
| `delete_reason` | TEXT NULL | دلیل حذف |

---

### backup_typeهای پیشنهادی

```text
database
files
full
settings
plugins
manual
pre_update
pre_restore
```

---

### backup_scopeهای پیشنهادی

```text
full_system
database_only
files_only
selected_tables
selected_files
settings_only
plugins_only
```

---

### statusهای پیشنهادی

```text
queued
running
completed
failed
cancelled
expired
deleted
```

---

### trigger_typeهای پیشنهادی

```text
manual
scheduled
pre_update
pre_restore
system
plugin
```

---

### قوانین

- `backup_number` باید Unique باشد.
- هر Backup باید backup_type داشته باشد.
- بکاپ completed باید حداقل یک backup_file داشته باشد.
- بکاپ failed باید error_message داشته باشد.
- بکاپ pre_update قبل از نصب Update الزامی است.
- بکاپ pre_restore قبل از Restore الزامی است.
- فایل بکاپ باید در Files Domain و Private Storage ذخیره شود.
- بکاپ نباید Public باشد.
- حذف بکاپ باید Soft Delete باشد.
- دانلود بکاپ باید Permission ویژه و Audit Log داشته باشد.
- بکاپ‌های قدیمی می‌توانند طبق Retention Policy منقضی شوند.

---

### Indexهای پیشنهادی

```text
uniq_backups_backup_number
idx_backups_backup_type
idx_backups_backup_scope
idx_backups_status
idx_backups_trigger_type
idx_backups_started_at
idx_backups_completed_at
idx_backups_failed_at
idx_backups_storage_disk_key
idx_backups_created_by
idx_backups_created_at
idx_backups_deleted_at
```

---

### نمونه ساختار SQL

```sql
CREATE TABLE backups (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    backup_number VARCHAR(50) NOT NULL,
    backup_type VARCHAR(50) NOT NULL,
    backup_scope VARCHAR(50) NOT NULL DEFAULT 'full_system',
    status VARCHAR(50) NOT NULL DEFAULT 'queued',
    trigger_type VARCHAR(50) NOT NULL DEFAULT 'manual',
    reason TEXT NULL,
    started_at DATETIME NULL,
    completed_at DATETIME NULL,
    failed_at DATETIME NULL,
    duration_seconds INT UNSIGNED NULL,
    database_included TINYINT(1) NOT NULL DEFAULT 0,
    files_included TINYINT(1) NOT NULL DEFAULT 0,
    plugins_included TINYINT(1) NOT NULL DEFAULT 0,
    storage_disk_key VARCHAR(100) NULL,
    total_size_bytes BIGINT UNSIGNED NULL,
    files_count INT UNSIGNED NULL,
    checksum_sha256 VARCHAR(128) NULL,
    is_encrypted TINYINT(1) NOT NULL DEFAULT 0,
    is_restorable TINYINT(1) NOT NULL DEFAULT 0,
    created_by BIGINT UNSIGNED NULL,
    error_message TEXT NULL,
    metadata JSON NULL,
    created_at DATETIME NULL,
    updated_at DATETIME NULL,
    deleted_at DATETIME NULL,
    deleted_by BIGINT UNSIGNED NULL,
    delete_reason TEXT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uniq_backups_backup_number (backup_number),
    KEY idx_backups_backup_type (backup_type),
    KEY idx_backups_backup_scope (backup_scope),
    KEY idx_backups_status (status),
    KEY idx_backups_trigger_type (trigger_type),
    KEY idx_backups_started_at (started_at),
    KEY idx_backups_completed_at (completed_at),
    KEY idx_backups_failed_at (failed_at),
    KEY idx_backups_storage_disk_key (storage_disk_key),
    KEY idx_backups_created_by (created_by),
    KEY idx_backups_created_at (created_at),
    KEY idx_backups_deleted_at (deleted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

---

## جدول backup_files

### هدف جدول

جدول `backup_files` فایل‌های مربوط به هر بکاپ را نگهداری می‌کند.

یک بکاپ می‌تواند چند فایل داشته باشد:

- فایل دیتابیس
- فایل فایل‌های آپلودی
- فایل تنظیمات
- فایل پلاگین‌ها
- فایل Manifest

---

### نام جدول

```text
backup_files
```

---

### ستون‌های پیشنهادی

| ستون | نوع | توضیح |
|---|---|---|
| `id` | BIGINT UNSIGNED AUTO_INCREMENT | شناسه |
| `backup_id` | BIGINT UNSIGNED | بکاپ |
| `file_id` | BIGINT UNSIGNED | فایل در Files Domain |
| `file_role` | VARCHAR(50) | نقش فایل |
| `file_format` | VARCHAR(50) | فرمت فایل |
| `size_bytes` | BIGINT UNSIGNED | حجم فایل |
| `checksum_sha256` | VARCHAR(128) NULL | هش فایل |
| `is_encrypted` | TINYINT(1) | رمزنگاری‌شده |
| `is_required_for_restore` | TINYINT(1) | برای Restore الزامی |
| `status` | VARCHAR(50) | وضعیت فایل |
| `created_at` | DATETIME NULL | زمان ایجاد |

---

### file_roleهای پیشنهادی

```text
database_dump
uploaded_files_archive
system_files_archive
plugins_archive
settings_export
manifest
log_file
other
```

---

### file_formatهای پیشنهادی

```text
sql
zip
tar
json
txt
gz
other
```

---

### statusهای پیشنهادی

```text
active
missing
corrupted
deleted
archived
```

---

### قوانین

- هر backup_file باید backup_id داشته باشد.
- هر backup_file باید file_id معتبر داشته باشد.
- فایل بکاپ باید Private باشد.
- فایل manifest برای بکاپ کامل پیشنهاد می‌شود.
- فایل corrupted نباید برای Restore استفاده شود.
- Restore فقط با فایل‌های معتبر و is_required_for_restore انجام شود.
- checksum باید برای تشخیص خرابی فایل استفاده شود.

---

### Indexهای پیشنهادی

```text
idx_backup_files_backup_id
idx_backup_files_file_id
idx_backup_files_file_role
idx_backup_files_file_format
idx_backup_files_is_encrypted
idx_backup_files_is_required_for_restore
idx_backup_files_status
```

---

## جدول backup_schedules

### هدف جدول

جدول `backup_schedules` زمان‌بندی بکاپ‌های خودکار را نگهداری می‌کند.

---

### نام جدول

```text
backup_schedules
```

---

### ستون‌های پیشنهادی

| ستون | نوع | توضیح |
|---|---|---|
| `id` | BIGINT UNSIGNED AUTO_INCREMENT | شناسه |
| `schedule_number` | VARCHAR(50) | شماره رسمی زمان‌بندی |
| `name` | VARCHAR(191) | نام زمان‌بندی |
| `backup_type` | VARCHAR(50) | نوع بکاپ |
| `backup_scope` | VARCHAR(50) | محدوده بکاپ |
| `frequency` | VARCHAR(50) | فرکانس |
| `interval_value` | INT UNSIGNED | فاصله اجرا |
| `run_at_time` | TIME NULL | ساعت اجرا |
| `day_of_week` | INT UNSIGNED NULL | روز هفته |
| `day_of_month` | INT UNSIGNED NULL | روز ماه |
| `timezone` | VARCHAR(100) NULL | منطقه زمانی |
| `retention_days` | INT UNSIGNED NULL | مدت نگهداری |
| `max_backups_to_keep` | INT UNSIGNED NULL | حداکثر تعداد نگهداری |
| `storage_disk_key` | VARCHAR(100) NULL | محل ذخیره |
| `is_active` | TINYINT(1) | فعال بودن |
| `last_run_at` | DATETIME NULL | آخرین اجرا |
| `next_run_at` | DATETIME NULL | اجرای بعدی |
| `created_by` | BIGINT UNSIGNED NULL | ایجادکننده |
| `updated_by` | BIGINT UNSIGNED NULL | ویرایش‌کننده |
| `created_at` | DATETIME NULL | زمان ایجاد |
| `updated_at` | DATETIME NULL | زمان بروزرسانی |
| `deleted_at` | DATETIME NULL | زمان حذف نرم |
| `deleted_by` | BIGINT UNSIGNED NULL | حذف‌کننده |

---

### frequencyهای پیشنهادی

```text
hourly
daily
weekly
monthly
custom
```

---

### قوانین

- `schedule_number` باید Unique باشد.
- Schedule فعال باید next_run_at داشته باشد.
- interval_value باید بیشتر از صفر باشد.
- retention_days باید مثبت باشد.
- Schedule غیرفعال نباید بکاپ جدید بسازد.
- تغییر Schedule بکاپ باید Audit Log داشته باشد.
- بکاپ زمان‌بندی‌شده باید در صورت شکست Notification ایجاد کند.
- حذف Schedule باید Soft Delete باشد.

---

### Indexهای پیشنهادی

```text
uniq_backup_schedules_schedule_number
idx_backup_schedules_backup_type
idx_backup_schedules_backup_scope
idx_backup_schedules_frequency
idx_backup_schedules_is_active
idx_backup_schedules_last_run_at
idx_backup_schedules_next_run_at
idx_backup_schedules_storage_disk_key
idx_backup_schedules_created_by
idx_backup_schedules_deleted_at
```

---

## جدول backup_restore_logs

### هدف جدول

جدول `backup_restore_logs` لاگ عملیات Restore را ذخیره می‌کند.

Restore یکی از حساس‌ترین عملیات‌های سیستم است و باید دقیق ثبت شود.

---

### نام جدول

```text
backup_restore_logs
```

---

### ستون‌های پیشنهادی

| ستون | نوع | توضیح |
|---|---|---|
| `id` | BIGINT UNSIGNED AUTO_INCREMENT | شناسه |
| `restore_number` | VARCHAR(50) | شماره رسمی Restore |
| `backup_id` | BIGINT UNSIGNED | بکاپ Restore شده |
| `pre_restore_backup_id` | BIGINT UNSIGNED NULL | بکاپ قبل از Restore |
| `restore_type` | VARCHAR(50) | نوع Restore |
| `status` | VARCHAR(50) | وضعیت Restore |
| `reason` | TEXT | دلیل Restore |
| `requested_by` | BIGINT UNSIGNED NULL | درخواست‌دهنده |
| `approved_by` | BIGINT UNSIGNED NULL | تأییدکننده |
| `approved_at` | DATETIME NULL | زمان تأیید |
| `started_at` | DATETIME NULL | زمان شروع |
| `completed_at` | DATETIME NULL | زمان تکمیل |
| `failed_at` | DATETIME NULL | زمان شکست |
| `duration_seconds` | INT UNSIGNED NULL | مدت اجرا |
| `affected_tables` | JSON NULL | جدول‌های تحت تأثیر |
| `affected_files` | JSON NULL | فایل‌های تحت تأثیر |
| `error_message` | TEXT NULL | پیام خطا |
| `metadata` | JSON NULL | داده تکمیلی |
| `created_at` | DATETIME NULL | زمان ایجاد |
| `updated_at` | DATETIME NULL | زمان بروزرسانی |

---

### restore_typeهای پیشنهادی

```text
full_restore
database_restore
files_restore
settings_restore
partial_restore
test_restore
```

---

### statusهای پیشنهادی

```text
requested
approved
running
completed
failed
cancelled
blocked
```

---

### قوانین

- `restore_number` باید Unique باشد.
- Restore باید backup_id معتبر داشته باشد.
- Restore واقعی باید reason داشته باشد.
- Restore حساس باید Approval داشته باشد.
- قبل از Restore باید pre_restore_backup ساخته شود.
- Restore باید Maintenance Mode را فعال کند، مگر نوع test_restore باشد.
- Restore باید Audit و Security Log داشته باشد.
- Restore failed باید error_message داشته باشد.
- Restore completed باید Notification مدیریتی ایجاد کند.
- Restore نباید بدون Permission ویژه اجرا شود.

---

### Indexهای پیشنهادی

```text
uniq_backup_restore_logs_restore_number
idx_backup_restore_logs_backup_id
idx_backup_restore_logs_pre_restore_backup_id
idx_backup_restore_logs_restore_type
idx_backup_restore_logs_status
idx_backup_restore_logs_requested_by
idx_backup_restore_logs_approved_by
idx_backup_restore_logs_started_at
idx_backup_restore_logs_completed_at
idx_backup_restore_logs_failed_at
```

---

## جدول update_packages

### هدف جدول

جدول `update_packages` بسته‌های بروزرسانی سیستم را نگهداری می‌کند.

بسته Update می‌تواند شامل کد، Migration، فایل، تنظیمات یا تغییرات سیستمی باشد.

---

### نام جدول

```text
update_packages
```

---

### ستون‌های پیشنهادی

| ستون | نوع | توضیح |
|---|---|---|
| `id` | BIGINT UNSIGNED AUTO_INCREMENT | شناسه |
| `package_number` | VARCHAR(50) | شماره رسمی بسته |
| `package_key` | VARCHAR(150) | کلید بسته |
| `version` | VARCHAR(50) | نسخه بسته |
| `name` | VARCHAR(191) | نام بسته |
| `description` | TEXT NULL | توضیح |
| `file_id` | BIGINT UNSIGNED | فایل بسته در Files Domain |
| `package_type` | VARCHAR(50) | نوع بسته |
| `source` | VARCHAR(50) | منبع بسته |
| `status` | VARCHAR(50) | وضعیت بسته |
| `checksum_sha256` | VARCHAR(128) NULL | هش بسته |
| `requires_backup` | TINYINT(1) | نیاز به بکاپ |
| `requires_maintenance` | TINYINT(1) | نیاز به Maintenance |
| `min_system_version` | VARCHAR(50) NULL | حداقل نسخه سیستم |
| `max_system_version` | VARCHAR(50) NULL | حداکثر نسخه سیستم |
| `target_version` | VARCHAR(50) NULL | نسخه مقصد |
| `manifest_data` | JSON NULL | Manifest بسته |
| `validation_status` | VARCHAR(50) | وضعیت اعتبارسنجی |
| `validated_by` | BIGINT UNSIGNED NULL | اعتبارسنجی توسط |
| `validated_at` | DATETIME NULL | زمان اعتبارسنجی |
| `validation_error` | TEXT NULL | خطای اعتبارسنجی |
| `uploaded_by` | BIGINT UNSIGNED NULL | آپلودکننده |
| `uploaded_at` | DATETIME NULL | زمان آپلود |
| `metadata` | JSON NULL | داده تکمیلی |
| `created_at` | DATETIME NULL | زمان ایجاد |
| `updated_at` | DATETIME NULL | زمان بروزرسانی |

---

### package_typeهای پیشنهادی

```text
core_update
security_update
database_migration
hotfix
patch
feature_update
manual_package
```

---

### sourceهای پیشنهادی

```text
manual_upload
official
system
developer
plugin
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
- `package_key` و version باید ترکیب یکتا داشته باشند.
- هر بسته باید file_id معتبر داشته باشد.
- فایل بسته باید Private باشد.
- بسته Update باید قبل از نصب Validate شود.
- بسته invalid یا blocked نباید نصب شود.
- بسته دارای مسیرهای خطرناک مثل `../` باید Block شود.
- بسته نباید فایل‌های غیرمجاز یا Executable خطرناک داشته باشد.
- بسته نیازمند backup باید قبل از نصب Backup ایجاد کند.
- نصب بسته باید Audit و Security Log داشته باشد.

---

### Indexهای پیشنهادی

```text
uniq_update_packages_package_number
uniq_update_packages_key_version
idx_update_packages_package_key
idx_update_packages_version
idx_update_packages_file_id
idx_update_packages_package_type
idx_update_packages_source
idx_update_packages_status
idx_update_packages_validation_status
idx_update_packages_requires_backup
idx_update_packages_target_version
idx_update_packages_uploaded_by
idx_update_packages_uploaded_at
```

---

## جدول update_installations

### هدف جدول

جدول `update_installations` عملیات نصب Update را نگهداری می‌کند.

هر نصب Update باید مرحله‌به‌مرحله قابل ردیابی باشد.

---

### نام جدول

```text
update_installations
```

---

### ستون‌های پیشنهادی

| ستون | نوع | توضیح |
|---|---|---|
| `id` | BIGINT UNSIGNED AUTO_INCREMENT | شناسه |
| `installation_number` | VARCHAR(50) | شماره رسمی نصب |
| `package_id` | BIGINT UNSIGNED | بسته Update |
| `from_version` | VARCHAR(50) NULL | نسخه قبل |
| `to_version` | VARCHAR(50) NULL | نسخه بعد |
| `status` | VARCHAR(50) | وضعیت نصب |
| `pre_update_backup_id` | BIGINT UNSIGNED NULL | بکاپ قبل از Update |
| `started_by` | BIGINT UNSIGNED NULL | شروع‌کننده |
| `approved_by` | BIGINT UNSIGNED NULL | تأییدکننده |
| `approved_at` | DATETIME NULL | زمان تأیید |
| `started_at` | DATETIME NULL | زمان شروع |
| `completed_at` | DATETIME NULL | زمان تکمیل |
| `failed_at` | DATETIME NULL | زمان شکست |
| `duration_seconds` | INT UNSIGNED NULL | مدت اجرا |
| `maintenance_enabled` | TINYINT(1) | آیا Maintenance فعال شد؟ |
| `rollback_available` | TINYINT(1) | امکان Rollback |
| `rolled_back_at` | DATETIME NULL | زمان Rollback |
| `rolled_back_by` | BIGINT UNSIGNED NULL | انجام‌دهنده Rollback |
| `rollback_reason` | TEXT NULL | دلیل Rollback |
| `error_message` | TEXT NULL | پیام خطا |
| `metadata` | JSON NULL | داده تکمیلی |
| `created_at` | DATETIME NULL | زمان ایجاد |
| `updated_at` | DATETIME NULL | زمان بروزرسانی |

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
- هر Installation باید package_id داشته باشد.
- بسته باید validated باشد.
- اگر package.requires_backup فعال است، pre_update_backup_id الزامی است.
- اگر package.requires_maintenance فعال است، Maintenance باید فعال شود.
- Update باید مرحله‌به‌مرحله در update_steps ثبت شود.
- Update failed باید error_message داشته باشد.
- Update completed باید system_versions را بروزرسانی کند.
- Rollback باید reason داشته باشد.
- نصب Update باید Audit و Security Log داشته باشد.

---

### Indexهای پیشنهادی

```text
uniq_update_installations_installation_number
idx_update_installations_package_id
idx_update_installations_status
idx_update_installations_pre_update_backup_id
idx_update_installations_started_by
idx_update_installations_approved_by
idx_update_installations_started_at
idx_update_installations_completed_at
idx_update_installations_failed_at
idx_update_installations_rolled_back_at
```

---

## جدول update_steps

### هدف جدول

جدول `update_steps` مراحل اجرای یک نصب Update را ذخیره می‌کند.

این جدول برای Debug، نمایش پیشرفت و بررسی خطا ضروری است.

---

### نام جدول

```text
update_steps
```

---

### ستون‌های پیشنهادی

| ستون | نوع | توضیح |
|---|---|---|
| `id` | BIGINT UNSIGNED AUTO_INCREMENT | شناسه |
| `installation_id` | BIGINT UNSIGNED | نصب Update |
| `step_number` | INT UNSIGNED | شماره مرحله |
| `step_key` | VARCHAR(100) | کلید مرحله |
| `step_name` | VARCHAR(191) | نام مرحله |
| `step_type` | VARCHAR(50) | نوع مرحله |
| `status` | VARCHAR(50) | وضعیت مرحله |
| `started_at` | DATETIME NULL | زمان شروع |
| `completed_at` | DATETIME NULL | زمان تکمیل |
| `failed_at` | DATETIME NULL | زمان شکست |
| `duration_seconds` | INT UNSIGNED NULL | مدت اجرا |
| `output_message` | TEXT NULL | خروجی |
| `error_message` | TEXT NULL | پیام خطا |
| `metadata` | JSON NULL | داده تکمیلی |
| `created_at` | DATETIME NULL | زمان ایجاد |
| `updated_at` | DATETIME NULL | زمان بروزرسانی |

---

### step_typeهای پیشنهادی

```text
validation
backup
maintenance_on
extract_package
copy_files
database_migration
clear_cache
post_update_check
maintenance_off
rollback
cleanup
```

---

### statusهای پیشنهادی

```text
pending
running
completed
failed
skipped
cancelled
```

---

### قوانین

- هر Step باید installation_id داشته باشد.
- step_number برای هر installation_id باید یکتا باشد.
- failed step باید error_message داشته باشد.
- اجرای Stepها باید به ترتیب کنترل شود.
- database_migration step باید با Migration Rules هماهنگ باشد.
- Stepهای حساس باید در صورت نیاز Audit یا System Log داشته باشند.

---

### Indexهای پیشنهادی

```text
idx_update_steps_installation_id
idx_update_steps_step_number
idx_update_steps_step_key
idx_update_steps_step_type
idx_update_steps_status
idx_update_steps_started_at
idx_update_steps_completed_at
idx_update_steps_failed_at
```

---

## جدول system_versions

### هدف جدول

جدول `system_versions` نسخه‌های سیستم را نگهداری می‌کند.

این جدول مشخص می‌کند سیستم از چه نسخه‌ای به چه نسخه‌ای منتقل شده است.

---

### نام جدول

```text
system_versions
```

---

### ستون‌های پیشنهادی

| ستون | نوع | توضیح |
|---|---|---|
| `id` | BIGINT UNSIGNED AUTO_INCREMENT | شناسه |
| `version` | VARCHAR(50) | نسخه |
| `version_name` | VARCHAR(191) NULL | نام نسخه |
| `release_type` | VARCHAR(50) | نوع انتشار |
| `installation_id` | BIGINT UNSIGNED NULL | نصب مرتبط |
| `package_id` | BIGINT UNSIGNED NULL | بسته مرتبط |
| `is_current` | TINYINT(1) | نسخه فعلی |
| `installed_at` | DATETIME NULL | زمان نصب |
| `installed_by` | BIGINT UNSIGNED NULL | نصب‌کننده |
| `changelog` | TEXT NULL | تغییرات |
| `metadata` | JSON NULL | داده تکمیلی |
| `created_at` | DATETIME NULL | زمان ایجاد |

---

### release_typeهای پیشنهادی

```text
initial
patch
minor
major
security
hotfix
rollback
```

---

### قوانین

- version باید یکتا باشد.
- فقط یک نسخه باید is_current = 1 باشد.
- Update completed باید نسخه جدید را current کند.
- Rollback باید نسخه current را اصلاح کند.
- تغییر نسخه باید Audit Log داشته باشد.
- system_versions نباید Soft Delete شود.

---

### Indexهای پیشنهادی

```text
uniq_system_versions_version
idx_system_versions_release_type
idx_system_versions_installation_id
idx_system_versions_package_id
idx_system_versions_is_current
idx_system_versions_installed_at
idx_system_versions_installed_by
```

---

## جدول maintenance_logs

### هدف جدول

جدول `maintenance_logs` فعال یا غیرفعال شدن Maintenance Mode را نگهداری می‌کند.

Maintenance Mode در عملیات حساس مثل Update و Restore استفاده می‌شود.

---

### نام جدول

```text
maintenance_logs
```

---

### ستون‌های پیشنهادی

| ستون | نوع | توضیح |
|---|---|---|
| `id` | BIGINT UNSIGNED AUTO_INCREMENT | شناسه |
| `maintenance_number` | VARCHAR(50) | شماره رسمی Maintenance |
| `mode_type` | VARCHAR(50) | نوع Maintenance |
| `status` | VARCHAR(50) | وضعیت |
| `reason` | TEXT NULL | دلیل |
| `related_type` | VARCHAR(100) NULL | موجودیت مرتبط |
| `related_id` | BIGINT UNSIGNED NULL | شناسه موجودیت |
| `started_by` | BIGINT UNSIGNED NULL | شروع‌کننده |
| `started_at` | DATETIME NULL | زمان شروع |
| `ended_by` | BIGINT UNSIGNED NULL | پایان‌دهنده |
| `ended_at` | DATETIME NULL | زمان پایان |
| `allowed_user_ids` | JSON NULL | کاربران مجاز هنگام Maintenance |
| `message` | TEXT NULL | پیام نمایشی |
| `metadata` | JSON NULL | داده تکمیلی |
| `created_at` | DATETIME NULL | زمان ایجاد |
| `updated_at` | DATETIME NULL | زمان بروزرسانی |

---

### mode_typeهای پیشنهادی

```text
manual
backup
restore
update
migration
emergency
system
```

---

### statusهای پیشنهادی

```text
active
ended
failed
cancelled
```

---

### قوانین

- `maintenance_number` باید Unique باشد.
- Maintenance فعال باید started_at داشته باشد.
- Maintenance ended باید ended_at داشته باشد.
- Maintenance برای Update یا Restore باید related_type و related_id داشته باشد.
- فعال‌سازی Maintenance باید Audit و Security Log داشته باشد.
- پیام Maintenance نباید اطلاعات حساس نمایش دهد.
- فقط کاربران مجاز باید در Maintenance امکان ورود داشته باشند.
- Maintenance نباید بدون دلیل طولانی فعال بماند.

---

### Indexهای پیشنهادی

```text
uniq_maintenance_logs_maintenance_number
idx_maintenance_logs_mode_type
idx_maintenance_logs_status
idx_maintenance_logs_related
idx_maintenance_logs_started_by
idx_maintenance_logs_started_at
idx_maintenance_logs_ended_at
```

---

## رابطه Backup & Update Domain با سایر Domainها

Backup & Update Domain با بخش‌های زیادی از سیستم ارتباط دارد.

| Domain | رابطه |
|---|---|
| Files | فایل‌های بکاپ و بسته‌های Update در Files Domain ذخیره می‌شوند |
| Settings | سیاست‌های بکاپ، مسیر ذخیره، نیاز به بکاپ قبل از Update |
| Security | تلاش‌های غیرمجاز برای Restore و Update ثبت می‌شوند |
| Audit | تمام عملیات حساس ثبت می‌شوند |
| Notifications | شکست یا موفقیت بکاپ و Update اعلان ایجاد می‌کند |
| Reports | گزارش بکاپ‌ها و Updateها |
| Plugins | نصب پلاگین می‌تواند قبل از اجرا بکاپ بخواهد |
| Database Migrations | Update می‌تواند Migration اجرا کند |
| Users | فقط کاربران مجاز عملیات Backup/Restore/Update انجام می‌دهند |

---

## قوانین بکاپ

قوانین:

- بکاپ باید شماره رسمی یکتا داشته باشد.
- بکاپ باید نوع و محدوده مشخص داشته باشد.
- بکاپ کامل باید شامل دیتابیس و فایل‌های ضروری باشد.
- بکاپ قبل از Update و Restore باید الزامی باشد.
- بکاپ failed باید error_message داشته باشد.
- بکاپ completed باید فایل معتبر داشته باشد.
- بکاپ باید در Private Storage ذخیره شود.
- بکاپ نباید Public شود.
- بکاپ باید طبق Retention Policy نگهداری یا منقضی شود.
- دانلود بکاپ باید Permission ویژه داشته باشد.

---

## قوانین فایل بکاپ

قوانین:

- فایل بکاپ باید در Files Domain ثبت شود.
- فایل بکاپ باید Private باشد.
- فایل بکاپ باید در دسته `backup` ذخیره شود.
- فایل بکاپ بهتر است checksum داشته باشد.
- فایل بکاپ corrupted نباید Restore شود.
- فایل بکاپ deleted نباید دانلود یا Restore شود.
- فایل بکاپ می‌تواند encrypted باشد.
- فایل بکاپ نباید از مسیر Public Web قابل دانلود باشد.

---

## قوانین Restore

قوانین:

- Restore فقط با Permission ویژه مجاز است.
- Restore باید reason داشته باشد.
- Restore حساس باید Approval داشته باشد.
- قبل از Restore باید pre_restore_backup ساخته شود.
- Restore باید Maintenance Mode را فعال کند، مگر test_restore باشد.
- Restore باید مرحله‌به‌مرحله Log شود.
- Restore failed باید error_message داشته باشد.
- Restore completed باید Notification مدیریتی ایجاد کند.
- Restore باید Audit و Security Log داشته باشد.
- Restore نباید بدون بررسی checksum اجرا شود.

---

## قوانین Update

قوانین:

- بسته Update باید قبل از نصب Validate شود.
- بسته invalid یا blocked نباید نصب شود.
- بسته Update باید file_id معتبر داشته باشد.
- بسته Update باید Private باشد.
- اگر بسته requires_backup دارد، pre_update_backup الزامی است.
- اگر بسته requires_maintenance دارد، Maintenance Mode الزامی است.
- Update باید update_installation ایجاد کند.
- مراحل Update باید در update_steps ثبت شوند.
- Update completed باید system_versions را بروزرسانی کند.
- Update failed باید error_message داشته باشد.
- Rollback باید reason داشته باشد.
- Update باید Audit و Security Log داشته باشد.

---

## قوانین Maintenance Mode

قوانین:

- Maintenance Mode باید قابل Log باشد.
- فعال‌سازی Maintenance باید دلیل داشته باشد.
- Maintenance نباید Secret یا اطلاعات حساس نمایش دهد.
- هنگام Maintenance فقط کاربران مجاز وارد شوند.
- پایان Maintenance باید ended_at ثبت کند.
- Maintenance طولانی باید هشدار ایجاد کند.
- Maintenance برای Update و Restore باید related_type و related_id داشته باشد.

---

## قوانین Soft Delete

جدول‌های زیر باید Soft Delete داشته باشند:

- `backups`
- `backup_schedules`

قوانین:

- بکاپ deleted نباید قابل Restore یا دانلود باشد.
- حذف بکاپ باید Audit Log داشته باشد.
- فایل بکاپ باید از دسترسی خارج شود.
- backup_restore_logs نباید حذف شوند.
- update_packages نباید حذف فیزیکی شوند؛ status آن‌ها archived یا blocked شود.
- update_installations، update_steps و system_versions نباید Soft Delete شوند.
- maintenance_logs نباید حذف شوند.

---

## قوانین Index و Performance

قوانین:

- لیست بکاپ‌ها باید با status و completed_at سریع فیلتر شود.
- Scheduleهای فعال باید با next_run_at سریع پیدا شوند.
- Updateهای نصب‌شده باید با version سریع بررسی شوند.
- Logهای Restore و Update ممکن است زیاد شوند و باید Index مناسب داشته باشند.
- فایل‌های بکاپ بزرگ هستند؛ دیتابیس فقط Metadata نگهداری کند.
- Queryها باید Pagination داشته باشند.
- Retention Job باید بکاپ‌های منقضی‌شده را سریع پیدا کند.

Indexهای مهم:

```text
backups.backup_number
backups.status
backups.backup_type
backups.completed_at
backup_schedules.next_run_at
backup_schedules.is_active
backup_restore_logs.restore_number
update_packages.package_key
update_packages.version
update_installations.status
system_versions.version
system_versions.is_current
maintenance_logs.status
```

---

## قوانین Validation

### backups

- backup_number الزامی و یکتا است.
- backup_type معتبر باشد.
- backup_scope معتبر باشد.
- status معتبر باشد.
- completed backup باید completed_at داشته باشد.
- failed backup باید error_message داشته باشد.

### backup_files

- backup_id الزامی است.
- file_id الزامی است.
- file_role معتبر باشد.
- size_bytes باید غیرمنفی باشد.
- فایل required_for_restore نباید corrupted باشد.

### backup_schedules

- schedule_number الزامی و یکتا است.
- backup_type معتبر باشد.
- frequency معتبر باشد.
- interval_value باید بیشتر از صفر باشد.
- active schedule باید next_run_at داشته باشد.

### backup_restore_logs

- restore_number الزامی و یکتا است.
- backup_id الزامی است.
- restore_type معتبر باشد.
- reason الزامی است.
- completed restore باید completed_at داشته باشد.
- failed restore باید error_message داشته باشد.

### update_packages

- package_number الزامی و یکتا است.
- package_key الزامی است.
- version الزامی است.
- file_id الزامی است.
- package_type معتبر باشد.
- validation_status معتبر باشد.
- invalid package باید validation_error داشته باشد.

### update_installations

- installation_number الزامی و یکتا است.
- package_id الزامی است.
- status معتبر باشد.
- اگر requires_backup فعال است، pre_update_backup_id الزامی است.
- failed installation باید error_message داشته باشد.

### update_steps

- installation_id الزامی است.
- step_number الزامی است.
- step_key الزامی است.
- step_type معتبر باشد.
- status معتبر باشد.
- failed step باید error_message داشته باشد.

### system_versions

- version الزامی و یکتا است.
- فقط یک رکورد باید is_current = 1 باشد.
- release_type معتبر باشد.

### maintenance_logs

- maintenance_number الزامی و یکتا است.
- mode_type معتبر باشد.
- status معتبر باشد.
- active maintenance باید started_at داشته باشد.
- ended maintenance باید ended_at داشته باشد.

---

## قوانین Audit و Security Log

### Audit Log الزامی برای:

- ایجاد بکاپ دستی
- حذف بکاپ
- دانلود بکاپ
- تغییر Schedule بکاپ
- Restore بکاپ
- تأیید Restore
- شروع Restore
- پایان Restore
- آپلود بسته Update
- اعتبارسنجی بسته Update
- نصب Update
- Rollback Update
- تغییر نسخه سیستم
- فعال کردن Maintenance Mode
- غیرفعال کردن Maintenance Mode
- تغییر تنظیمات Backup و Update

### Security Log الزامی برای:

- تلاش دانلود بکاپ بدون Permission
- تلاش Restore بدون Permission
- تلاش نصب Update بدون Permission
- تلاش آپلود بسته Update خطرناک
- تلاش نصب بسته invalid یا blocked
- تلاش دستکاری backup_id یا package_id
- تلاش دسترسی مستقیم به فایل بکاپ
- CSRF نامعتبر در عملیات Backup، Restore یا Update
- تلاش تغییر Maintenance Mode بدون Permission
- تلاش مشاهده فایل بکاپ خارج از Scope

---

## Seedهای پیشنهادی

### backup_type

```text
database
files
full
settings
plugins
manual
pre_update
pre_restore
```

### backup_status

```text
queued
running
completed
failed
cancelled
expired
deleted
```

### restore_type

```text
full_restore
database_restore
files_restore
settings_restore
partial_restore
test_restore
```

### update_package_type

```text
core_update
security_update
database_migration
hotfix
patch
feature_update
manual_package
```

### update_status

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

### maintenance_mode_type

```text
manual
backup
restore
update
migration
emergency
system
```

---

## چک‌لیست پیاده‌سازی

قبل از پیاده‌سازی Backup & Update Tables بررسی شود:

- [ ] جدول `backups` ساخته شده است.
- [ ] `backup_number` یکتا است.
- [ ] بکاپ‌ها در Private Storage ذخیره می‌شوند.
- [ ] جدول `backup_files` ساخته شده است.
- [ ] فایل‌های بکاپ به Files Domain وصل هستند.
- [ ] جدول `backup_schedules` ساخته شده است.
- [ ] Scheduleهای فعال next_run_at دارند.
- [ ] جدول `backup_restore_logs` ساخته شده است.
- [ ] Restore قبل از اجرا pre_restore_backup می‌سازد.
- [ ] جدول `update_packages` ساخته شده است.
- [ ] بسته Update قبل از نصب Validate می‌شود.
- [ ] جدول `update_installations` ساخته شده است.
- [ ] Update قبل از نصب pre_update_backup می‌سازد.
- [ ] جدول `update_steps` ساخته شده است.
- [ ] مراحل Update به صورت دقیق ثبت می‌شوند.
- [ ] جدول `system_versions` ساخته شده است.
- [ ] فقط یک نسخه current وجود دارد.
- [ ] جدول `maintenance_logs` ساخته شده است.
- [ ] Maintenance Mode قابل Log و کنترل است.
- [ ] عملیات حساس Audit Log دارند.
- [ ] تلاش‌های غیرمجاز Security Log دارند.

---

## Definition of Done

Backup & Update Tables زمانی کامل هستند که:

- بکاپ با شماره رسمی یکتا قابل ثبت باشد.
- بکاپ بتواند دیتابیس، فایل‌ها یا کل سیستم را پوشش دهد.
- فایل‌های بکاپ در Files Domain و Private Storage ذخیره شوند.
- Schedule بکاپ خودکار قابل تعریف باشد.
- Restore بکاپ با reason، approval، pre_restore_backup و log کامل انجام شود.
- بسته Update قابل آپلود، Validate و Block باشد.
- نصب Update مرحله‌به‌مرحله قابل ردیابی باشد.
- قبل از Update حساس، pre_update_backup ساخته شود.
- نسخه سیستم در system_versions قابل مدیریت باشد.
- فقط یک نسخه current وجود داشته باشد.
- Maintenance Mode قابل فعال‌سازی، پایان و Log باشد.
- دانلود بکاپ و نصب Update بدون Permission ممکن نباشد.
- عملیات حساس Audit Log داشته باشند.
- تلاش‌های غیرمجاز Security Log داشته باشند.
- جدول‌ها با MySQL/MariaDB و PHP 7.4+ سازگار باشند.
- Codex بتواند از روی این مستندات Migrationهای Backup & Update Domain را بسازد.

---

## پایان فایل
````
