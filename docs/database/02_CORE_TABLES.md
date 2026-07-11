# 02 — Core Tables

مستند جدول‌های Core، زیرساختی، سیستمی و پایه دیتابیس در پروژه **Proma Pay**

---

## فهرست مطالب

- [هدف فایل](#هدف-فایل)
- [تعریف Core Tables](#تعریف-core-tables)
- [اصل مهم](#اصل-مهم)
- [لیست جدول‌های Core](#لیست-جدولهای-core)
- [جدول migrations](#جدول-migrations)
- [جدول migration_batches](#جدول-migration_batches)
- [جدول app_versions](#جدول-app_versions)
- [جدول system_modules](#جدول-system_modules)
- [جدول feature_flags](#جدول-feature_flags)
- [جدول maintenance_modes](#جدول-maintenance_modes)
- [جدول jobs](#جدول-jobs)
- [جدول failed_jobs](#جدول-failed_jobs)
- [جدول cron_locks](#جدول-cron_locks)
- [جدول number_sequences](#جدول-number_sequences)
- [جدول app_installations](#جدول-app_installations)
- [جدول system_health_checks](#جدول-system_health_checks)
- [رابطه جدول‌های Core با سایر Domainها](#رابطه-جدولهای-core-با-سایر-domainها)
- [قوانین امنیتی Core Tables](#قوانین-امنیتی-core-tables)
- [قوانین Performance](#قوانین-performance)
- [قوانین Migration برای Core Tables](#قوانین-migration-برای-core-tables)
- [Seedهای ضروری Core](#seedهای-ضروری-core)
- [چک‌لیست پیاده‌سازی](#چکلیست-پیادهسازی)
- [Definition of Done](#definition-of-done)

---

## هدف فایل

هدف این فایل این است که جدول‌های پایه و زیرساختی سیستم **Proma Pay** مشخص شوند.

جدول‌های Core مستقیماً مربوط به مشتری، قرارداد، قسط یا پرداخت نیستند؛ اما برای اجرای صحیح کل سیستم ضروری هستند.

این فایل برای Codex مشخص می‌کند که:

- جدول‌های پایه سیستم کدام هستند.
- هر جدول چه مسئولیتی دارد.
- چه ستون‌هایی باید داشته باشد.
- چه Indexهایی لازم است.
- چه داده‌هایی باید Seed شوند.
- کدام جدول‌ها حساس هستند.
- چه قوانینی برای Migration، Job، Maintenance و Version وجود دارد.

---

## تعریف Core Tables

Core Tables جدول‌هایی هستند که برای کارکرد عمومی سیستم استفاده می‌شوند.

این جدول‌ها معمولاً مربوط به موارد زیر هستند:

- Migrationها
- نسخه سیستم
- ماژول‌های فعال
- Feature Flagها
- Maintenance Mode
- Job Queue ساده
- Lock برای Cron Jobها
- تولید شماره‌های رسمی
- وضعیت سلامت سیستم
- اطلاعات نصب سیستم

Core Tables نباید منطق تجاری فروش اقساطی را داخل خودشان نگهداری کنند.

مثلاً اطلاعات قرارداد نباید در جدول Core ذخیره شود.  
اطلاعات قرارداد باید در جدول‌های Contracts Domain ذخیره شود.

---

## اصل مهم

اصل مهم در Core Tables:

> جدول‌های Core باید کم‌حجم، پایدار، امن و قابل اتکا باشند.

این جدول‌ها نباید تبدیل به محل ذخیره همه چیز شوند.

اشتباه رایج:

```text
ذخیره همه تنظیمات، وضعیت‌ها، لاگ‌ها، داده‌های موقت، گزارش‌ها و اطلاعات مالی داخل یک جدول عمومی مثل system_data
```

این کار ممنوع است.

هر نوع داده باید جای مشخص خودش را داشته باشد.

---

## لیست جدول‌های Core

جدول‌های پیشنهادی Core:

| جدول | کاربرد |
|---|---|
| `migrations` | ثبت Migrationهای اجراشده |
| `migration_batches` | گروه‌بندی اجرای Migrationها |
| `app_versions` | ثبت نسخه‌های نصب‌شده سیستم |
| `system_modules` | مدیریت ماژول‌های داخلی سیستم |
| `feature_flags` | فعال یا غیرفعال کردن قابلیت‌ها |
| `maintenance_modes` | مدیریت حالت تعمیر و نگهداری |
| `jobs` | صف ساده Jobهای داخلی |
| `failed_jobs` | ثبت Jobهای شکست‌خورده |
| `cron_locks` | جلوگیری از اجرای همزمان Cronها |
| `number_sequences` | تولید شماره رسمی قرارداد، پرداخت و غیره |
| `app_installations` | اطلاعات نصب سیستم |
| `system_health_checks` | وضعیت سلامت سرویس‌های اصلی |

---

## جدول migrations

### هدف جدول

جدول `migrations` برای ثبت Migrationهای اجراشده استفاده می‌شود.

این جدول کمک می‌کند سیستم بداند کدام تغییرات دیتابیس قبلاً اجرا شده‌اند و نباید دوباره اجرا شوند.

---

### نام جدول

```text
migrations
```

---

### ستون‌های پیشنهادی

| ستون | نوع | توضیح |
|---|---|---|
| `id` | BIGINT UNSIGNED AUTO_INCREMENT | شناسه داخلی |
| `migration` | VARCHAR(191) | نام Migration |
| `batch` | INT UNSIGNED | شماره Batch |
| `type` | VARCHAR(50) | نوع Migration |
| `source` | VARCHAR(100) NULL | منبع Migration |
| `executed_at` | DATETIME | زمان اجرا |
| `execution_time_ms` | INT UNSIGNED NULL | مدت اجرا به میلی‌ثانیه |
| `checksum` | VARCHAR(128) NULL | Hash فایل Migration |
| `created_at` | DATETIME NULL | زمان ثبت |

---

### مقدارهای پیشنهادی type

```text
core
plugin
system
manual
```

---

### قوانین

- هر Migration فقط یک بار اجرا شود.
- نام Migration باید Unique باشد.
- Migration پلاگین باید از Core قابل تشخیص باشد.
- اجرای Migration باید زمان اجرا را ثبت کند.
- اگر checksum تغییر کرد، سیستم باید هشدار دهد.
- Migrationهای اجراشده نباید بی‌دلیل حذف شوند.

---

### Indexهای پیشنهادی

```text
uniq_migrations_migration
idx_migrations_batch
idx_migrations_type
idx_migrations_source
idx_migrations_executed_at
```

---

### نمونه ساختار SQL

```sql
CREATE TABLE migrations (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    migration VARCHAR(191) NOT NULL,
    batch INT UNSIGNED NOT NULL DEFAULT 1,
    type VARCHAR(50) NOT NULL DEFAULT 'core',
    source VARCHAR(100) NULL,
    executed_at DATETIME NOT NULL,
    execution_time_ms INT UNSIGNED NULL,
    checksum VARCHAR(128) NULL,
    created_at DATETIME NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uniq_migrations_migration (migration),
    KEY idx_migrations_batch (batch),
    KEY idx_migrations_type (type),
    KEY idx_migrations_source (source),
    KEY idx_migrations_executed_at (executed_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

---

## جدول migration_batches

### هدف جدول

جدول `migration_batches` برای گروه‌بندی اجرای Migrationها استفاده می‌شود.

هر بار که چند Migration با هم اجرا می‌شوند، یک Batch ساخته می‌شود.

---

### نام جدول

```text
migration_batches
```

---

### ستون‌های پیشنهادی

| ستون | نوع | توضیح |
|---|---|---|
| `id` | BIGINT UNSIGNED AUTO_INCREMENT | شناسه داخلی |
| `batch_number` | INT UNSIGNED | شماره Batch |
| `type` | VARCHAR(50) | نوع Batch |
| `source` | VARCHAR(100) NULL | منبع |
| `status` | VARCHAR(50) | وضعیت |
| `started_at` | DATETIME NULL | زمان شروع |
| `completed_at` | DATETIME NULL | زمان پایان |
| `failed_at` | DATETIME NULL | زمان شکست |
| `error_message` | TEXT NULL | پیام خطا |
| `created_by` | BIGINT UNSIGNED NULL | اجراکننده |
| `created_at` | DATETIME NULL | زمان ایجاد |

---

### وضعیت‌های پیشنهادی

```text
pending
running
completed
failed
rolled_back
```

---

### قوانین

- هر اجرای گروهی Migration باید Batch داشته باشد.
- اگر Batch شکست خورد، وضعیت باید `failed` شود.
- اجرای Migrationهای حساس باید در Audit Log هم ثبت شود.
- Migration پلاگین باید source مشخص داشته باشد.
- Rollback در صورت پشتیبانی باید وضعیت را `rolled_back` کند.

---

### Indexهای پیشنهادی

```text
uniq_migration_batches_batch_number
idx_migration_batches_status
idx_migration_batches_type
idx_migration_batches_source
idx_migration_batches_started_at
```

---

## جدول app_versions

### هدف جدول

جدول `app_versions` برای ثبت نسخه‌های نصب‌شده یا بروزرسانی‌شده سیستم استفاده می‌شود.

این جدول برای Update، Rollback، Debug و بررسی سلامت نسخه ضروری است.

---

### نام جدول

```text
app_versions
```

---

### ستون‌های پیشنهادی

| ستون | نوع | توضیح |
|---|---|---|
| `id` | BIGINT UNSIGNED AUTO_INCREMENT | شناسه |
| `version` | VARCHAR(50) | نسخه سیستم |
| `previous_version` | VARCHAR(50) NULL | نسخه قبلی |
| `release_channel` | VARCHAR(50) | کانال انتشار |
| `installed_at` | DATETIME | زمان نصب |
| `installed_by` | BIGINT UNSIGNED NULL | نصب‌کننده |
| `update_id` | BIGINT UNSIGNED NULL | ارتباط با Update |
| `status` | VARCHAR(50) | وضعیت |
| `notes` | TEXT NULL | توضیحات |
| `metadata` | JSON NULL | داده تکمیلی |
| `created_at` | DATETIME NULL | زمان ایجاد |

---

### release_channelهای پیشنهادی

```text
stable
beta
dev
manual
```

---

### وضعیت‌های پیشنهادی

```text
installed
active
failed
rolled_back
```

---

### قوانین

- نسخه فعال سیستم باید قابل تشخیص باشد.
- هر Update موفق باید یک رکورد در این جدول ثبت کند.
- Rollback باید نسخه قبلی را مشخص کند.
- نسخه سیستم نباید فقط در فایل ذخیره شود.
- تغییر نسخه باید Audit Log داشته باشد.

---

### Indexهای پیشنهادی

```text
idx_app_versions_version
idx_app_versions_status
idx_app_versions_installed_at
idx_app_versions_update_id
```

---

## جدول system_modules

### هدف جدول

جدول `system_modules` برای مدیریت ماژول‌های داخلی سیستم استفاده می‌شود.

ماژول‌های داخلی با پلاگین فرق دارند.

ماژول داخلی بخشی از Core یا ساختار اصلی سیستم است؛ مثل:

- customers
- contracts
- installments
- payments
- legal
- reports
- plugins
- backups

---

### نام جدول

```text
system_modules
```

---

### ستون‌های پیشنهادی

| ستون | نوع | توضیح |
|---|---|---|
| `id` | BIGINT UNSIGNED AUTO_INCREMENT | شناسه |
| `module_key` | VARCHAR(100) | کلید یکتا |
| `name` | VARCHAR(191) | نام نمایشی |
| `description` | TEXT NULL | توضیح |
| `version` | VARCHAR(50) NULL | نسخه ماژول |
| `is_core` | TINYINT(1) | آیا Core است؟ |
| `is_enabled` | TINYINT(1) | فعال بودن |
| `sort_order` | INT UNSIGNED | ترتیب نمایش |
| `status` | VARCHAR(50) | وضعیت |
| `created_at` | DATETIME NULL | زمان ایجاد |
| `updated_at` | DATETIME NULL | زمان بروزرسانی |

---

### قوانین

- module_key باید Unique باشد.
- ماژول‌های حیاتی نباید از UI غیرفعال شوند.
- غیرفعال کردن ماژول داخلی حساس باید ممنوع یا محدود باشد.
- پلاگین‌ها در جدول `plugins` مدیریت می‌شوند، نه این جدول.
- این جدول برای نمایش منوی مدیریتی و کنترل قابلیت‌های داخلی کاربرد دارد.

---

### نمونه module_key

```text
customers
contracts
installments
payments
legal
reports
files
notifications
plugins
backup_update
```

---

### Indexهای پیشنهادی

```text
uniq_system_modules_module_key
idx_system_modules_is_enabled
idx_system_modules_is_core
idx_system_modules_status
idx_system_modules_sort_order
```

---

## جدول feature_flags

### هدف جدول

جدول `feature_flags` برای فعال یا غیرفعال کردن قابلیت‌های مشخص استفاده می‌شود.

Feature Flag کمک می‌کند قابلیت‌ها بدون تغییر کد یا قبل از نهایی شدن، کنترل شوند.

---

### نام جدول

```text
feature_flags
```

---

### ستون‌های پیشنهادی

| ستون | نوع | توضیح |
|---|---|---|
| `id` | BIGINT UNSIGNED AUTO_INCREMENT | شناسه |
| `flag_key` | VARCHAR(150) | کلید یکتا |
| `name` | VARCHAR(191) | نام نمایشی |
| `description` | TEXT NULL | توضیح |
| `is_enabled` | TINYINT(1) | فعال یا غیرفعال |
| `scope` | VARCHAR(50) | محدوده اثر |
| `module_key` | VARCHAR(100) NULL | ماژول مرتبط |
| `enabled_for_roles` | JSON NULL | نقش‌های مجاز |
| `enabled_for_users` | JSON NULL | کاربران مجاز |
| `starts_at` | DATETIME NULL | شروع فعال‌سازی |
| `ends_at` | DATETIME NULL | پایان فعال‌سازی |
| `created_by` | BIGINT UNSIGNED NULL | ایجادکننده |
| `updated_by` | BIGINT UNSIGNED NULL | ویرایش‌کننده |
| `created_at` | DATETIME NULL | زمان ایجاد |
| `updated_at` | DATETIME NULL | زمان بروزرسانی |

---

### scopeهای پیشنهادی

```text
global
role
user
module
internal
```

---

### نمونه flag_key

```text
reports.advanced_export
payments.card_to_card_review
plugins.upload_enabled
legal.referral_enabled
backup.auto_backup_enabled
```

---

### قوانین

- Feature Flag نباید جایگزین Permission شود.
- Permission همچنان باید سمت سرور بررسی شود.
- تغییر Feature Flag حساس باید Audit Log داشته باشد.
- قابلیت‌های مالی یا حقوقی نباید فقط با Frontend کنترل شوند.
- flag_key باید Unique باشد.

---

### Indexهای پیشنهادی

```text
uniq_feature_flags_flag_key
idx_feature_flags_is_enabled
idx_feature_flags_scope
idx_feature_flags_module_key
idx_feature_flags_starts_at
idx_feature_flags_ends_at
```

---

## جدول maintenance_modes

### هدف جدول

جدول `maintenance_modes` برای مدیریت حالت تعمیر و نگهداری سیستم استفاده می‌شود.

هنگام Update، Restore، Migration حساس یا عملیات خطرناک، سیستم می‌تواند وارد Maintenance Mode شود.

---

### نام جدول

```text
maintenance_modes
```

---

### ستون‌های پیشنهادی

| ستون | نوع | توضیح |
|---|---|---|
| `id` | BIGINT UNSIGNED AUTO_INCREMENT | شناسه |
| `is_active` | TINYINT(1) | فعال بودن |
| `reason` | TEXT NULL | دلیل |
| `message` | TEXT NULL | پیام نمایشی |
| `allowed_ips` | JSON NULL | IPهای مجاز |
| `allowed_user_ids` | JSON NULL | کاربران مجاز |
| `started_at` | DATETIME NULL | زمان شروع |
| `ended_at` | DATETIME NULL | زمان پایان |
| `started_by` | BIGINT UNSIGNED NULL | فعال‌کننده |
| `ended_by` | BIGINT UNSIGNED NULL | غیرفعال‌کننده |
| `status` | VARCHAR(50) | وضعیت |
| `metadata` | JSON NULL | داده تکمیلی |
| `created_at` | DATETIME NULL | زمان ایجاد |
| `updated_at` | DATETIME NULL | زمان بروزرسانی |

---

### وضعیت‌های پیشنهادی

```text
active
inactive
scheduled
expired
```

---

### قوانین

- فقط یک Maintenance Mode فعال باید وجود داشته باشد.
- فعال‌سازی Maintenance Mode باید Audit Log داشته باشد.
- غیرفعال‌سازی Maintenance Mode باید Audit Log داشته باشد.
- هنگام Update و Restore حساس، Maintenance Mode پیشنهاد یا الزام شود.
- کاربران مجاز مشخص می‌توانند در زمان Maintenance وارد شوند.
- مشتریان نباید در زمان Maintenance به عملیات حساس دسترسی داشته باشند.

---

### Indexهای پیشنهادی

```text
idx_maintenance_modes_is_active
idx_maintenance_modes_status
idx_maintenance_modes_started_at
idx_maintenance_modes_ended_at
```

---

## جدول jobs

### هدف جدول

جدول `jobs` برای صف ساده Jobهای داخلی استفاده می‌شود.

با توجه به سازگاری با هاست اشتراکی، سیستم نباید حتماً به Queue Server خارجی وابسته باشد.

این جدول می‌تواند Jobهای سبک و قابل کنترل را نگهداری کند.

---

### نام جدول

```text
jobs
```

---

### ستون‌های پیشنهادی

| ستون | نوع | توضیح |
|---|---|---|
| `id` | BIGINT UNSIGNED AUTO_INCREMENT | شناسه |
| `job_key` | VARCHAR(191) | کلید Job |
| `queue` | VARCHAR(100) | نام Queue |
| `payload` | LONGTEXT | داده Job |
| `attempts` | INT UNSIGNED | تعداد تلاش |
| `max_attempts` | INT UNSIGNED | حداکثر تلاش |
| `status` | VARCHAR(50) | وضعیت |
| `available_at` | DATETIME NULL | زمان قابل اجرا شدن |
| `reserved_at` | DATETIME NULL | زمان رزرو |
| `reserved_by` | VARCHAR(100) NULL | شناسه Worker |
| `started_at` | DATETIME NULL | زمان شروع |
| `completed_at` | DATETIME NULL | زمان پایان |
| `failed_at` | DATETIME NULL | زمان شکست |
| `error_message` | TEXT NULL | خطا |
| `created_at` | DATETIME NULL | زمان ایجاد |
| `updated_at` | DATETIME NULL | زمان بروزرسانی |

---

### وضعیت‌های پیشنهادی

```text
pending
reserved
running
completed
failed
cancelled
expired
```

---

### نمونه Jobها

```text
send_notification
generate_report_export
create_backup
cleanup_expired_exports
sync_plugin_data
send_overdue_reminder
```

---

### قوانین

- Jobهای سنگین باید محدود شوند.
- Job نباید باعث Timeout طولانی شود.
- هر Job باید max_attempts داشته باشد.
- Job شکست‌خورده باید در `failed_jobs` ثبت شود.
- Payload نباید اطلاعات حساس خام غیرضروری داشته باشد.
- Jobهای مالی حساس باید Idempotent باشند.

---

### Indexهای پیشنهادی

```text
idx_jobs_queue_status
idx_jobs_available_at
idx_jobs_reserved_at
idx_jobs_status
idx_jobs_job_key
```

---

## جدول failed_jobs

### هدف جدول

جدول `failed_jobs` برای ثبت Jobهایی است که بعد از تلاش مجدد هم شکست خورده‌اند.

---

### نام جدول

```text
failed_jobs
```

---

### ستون‌های پیشنهادی

| ستون | نوع | توضیح |
|---|---|---|
| `id` | BIGINT UNSIGNED AUTO_INCREMENT | شناسه |
| `job_id` | BIGINT UNSIGNED NULL | Job اصلی |
| `job_key` | VARCHAR(191) | کلید Job |
| `queue` | VARCHAR(100) | نام Queue |
| `payload` | LONGTEXT NULL | داده Job |
| `exception_message` | TEXT NULL | پیام خطا |
| `exception_trace` | LONGTEXT NULL | Trace خطا |
| `failed_at` | DATETIME | زمان شکست |
| `resolved_at` | DATETIME NULL | زمان حل مشکل |
| `resolved_by` | BIGINT UNSIGNED NULL | حل‌کننده |
| `status` | VARCHAR(50) | وضعیت |
| `created_at` | DATETIME NULL | زمان ایجاد |

---

### وضعیت‌های پیشنهادی

```text
open
resolved
ignored
retried
```

---

### قوانین

- Trace کامل فقط برای Super Admin قابل مشاهده باشد.
- اطلاعات حساس نباید داخل exception_trace خام نمایش داده شود.
- Job شکست‌خورده حساس باید Notification ایجاد کند.
- Retry دستی باید Audit Log داشته باشد.
- پاکسازی failed_jobs قدیمی باید طبق Settings انجام شود.

---

### Indexهای پیشنهادی

```text
idx_failed_jobs_job_id
idx_failed_jobs_job_key
idx_failed_jobs_queue
idx_failed_jobs_status
idx_failed_jobs_failed_at
```

---

## جدول cron_locks

### هدف جدول

جدول `cron_locks` برای جلوگیری از اجرای همزمان Cron Jobها استفاده می‌شود.

در هاست اشتراکی ممکن است Cron چند بار همزمان اجرا شود. این جدول جلوی اجرای تکراری و خطرناک را می‌گیرد.

---

### نام جدول

```text
cron_locks
```

---

### ستون‌های پیشنهادی

| ستون | نوع | توضیح |
|---|---|---|
| `id` | BIGINT UNSIGNED AUTO_INCREMENT | شناسه |
| `lock_key` | VARCHAR(191) | کلید Lock |
| `owner` | VARCHAR(191) NULL | مالک Lock |
| `locked_at` | DATETIME | زمان قفل شدن |
| `expires_at` | DATETIME | زمان انقضا |
| `released_at` | DATETIME NULL | زمان آزادسازی |
| `status` | VARCHAR(50) | وضعیت |
| `metadata` | JSON NULL | داده تکمیلی |
| `created_at` | DATETIME NULL | زمان ایجاد |
| `updated_at` | DATETIME NULL | زمان بروزرسانی |

---

### وضعیت‌های پیشنهادی

```text
locked
released
expired
failed
```

---

### نمونه lock_key

```text
backup.daily
reports.cleanup_exports
notifications.send_pending
installments.mark_overdue
jobs.worker
```

---

### قوانین

- lock_key باید Unique باشد.
- Lock باید expires_at داشته باشد.
- اگر Cron وسط کار قطع شد، Lock نباید برای همیشه باقی بماند.
- عملیات حساس مثل Backup و Update باید Lock داشته باشند.
- آزادسازی Lock باید Log شود.

---

### Indexهای پیشنهادی

```text
uniq_cron_locks_lock_key
idx_cron_locks_status
idx_cron_locks_expires_at
idx_cron_locks_locked_at
```

---

## جدول number_sequences

### هدف جدول

جدول `number_sequences` برای تولید شماره‌های رسمی و قابل نمایش استفاده می‌شود.

مثلاً:

- شماره قرارداد
- شماره پرداخت
- شماره پرونده حقوقی
- شماره خروجی گزارش
- شماره بکاپ

شناسه داخلی `id` نباید به عنوان شماره رسمی استفاده شود.

---

### نام جدول

```text
number_sequences
```

---

### ستون‌های پیشنهادی

| ستون | نوع | توضیح |
|---|---|---|
| `id` | BIGINT UNSIGNED AUTO_INCREMENT | شناسه |
| `sequence_key` | VARCHAR(100) | کلید Sequence |
| `prefix` | VARCHAR(50) NULL | پیشوند |
| `suffix` | VARCHAR(50) NULL | پسوند |
| `current_number` | BIGINT UNSIGNED | عدد فعلی |
| `padding_length` | INT UNSIGNED | تعداد رقم |
| `reset_policy` | VARCHAR(50) | سیاست Reset |
| `last_reset_at` | DATETIME NULL | آخرین Reset |
| `is_active` | TINYINT(1) | فعال بودن |
| `created_at` | DATETIME NULL | زمان ایجاد |
| `updated_at` | DATETIME NULL | زمان بروزرسانی |

---

### reset_policyهای پیشنهادی

```text
never
daily
monthly
yearly
manual
```

---

### نمونه sequence_key

```text
contract_number
payment_number
legal_case_number
backup_number
report_export_number
receipt_number
```

---

### قوانین

- sequence_key باید Unique باشد.
- تولید شماره رسمی باید Transaction-safe باشد.
- دو رکورد نباید شماره رسمی تکراری بگیرند.
- فرمت شماره رسمی باید قابل تنظیم باشد.
- Reset باید با احتیاط انجام شود.
- تغییر current_number باید Audit Log داشته باشد.

---

### نمونه خروجی شماره رسمی

```text
CON-000001
PAY-000001
LEG-000001
BKP-000001
EXP-000001
```

---

### Indexهای پیشنهادی

```text
uniq_number_sequences_sequence_key
idx_number_sequences_is_active
idx_number_sequences_reset_policy
```

---

## جدول app_installations

### هدف جدول

جدول `app_installations` برای نگهداری اطلاعات نصب سیستم استفاده می‌شود.

این جدول برای تشخیص نصب اولیه، اطلاعات محیط و وضعیت راه‌اندازی استفاده می‌شود.

---

### نام جدول

```text
app_installations
```

---

### ستون‌های پیشنهادی

| ستون | نوع | توضیح |
|---|---|---|
| `id` | BIGINT UNSIGNED AUTO_INCREMENT | شناسه |
| `installation_key` | VARCHAR(191) | کلید نصب |
| `installed_version` | VARCHAR(50) | نسخه نصب‌شده |
| `installed_at` | DATETIME | زمان نصب |
| `installed_by` | BIGINT UNSIGNED NULL | نصب‌کننده |
| `environment` | VARCHAR(50) | محیط |
| `base_url` | VARCHAR(255) NULL | آدرس اصلی |
| `php_version` | VARCHAR(50) NULL | نسخه PHP |
| `database_driver` | VARCHAR(50) NULL | نوع دیتابیس |
| `database_version` | VARCHAR(100) NULL | نسخه دیتابیس |
| `timezone` | VARCHAR(100) NULL | Timezone |
| `status` | VARCHAR(50) | وضعیت |
| `metadata` | JSON NULL | داده تکمیلی |
| `created_at` | DATETIME NULL | زمان ایجاد |
| `updated_at` | DATETIME NULL | زمان بروزرسانی |

---

### environmentهای پیشنهادی

```text
production
staging
local
development
```

---

### وضعیت‌های پیشنهادی

```text
installed
incomplete
maintenance
broken
```

---

### قوانین

- installation_key باید Unique باشد.
- این جدول نباید اطلاعات محرمانه دیتابیس را ذخیره کند.
- base_url می‌تواند برای PWA، Electron و لینک‌سازی استفاده شود.
- وضعیت نصب باید در Health Check بررسی شود.

---

### Indexهای پیشنهادی

```text
uniq_app_installations_installation_key
idx_app_installations_environment
idx_app_installations_status
idx_app_installations_installed_at
```

---

## جدول system_health_checks

### هدف جدول

جدول `system_health_checks` برای ثبت وضعیت سلامت بخش‌های اصلی سیستم استفاده می‌شود.

این جدول برای صفحه System Health یا Dashboard مدیریتی کاربرد دارد.

---

### نام جدول

```text
system_health_checks
```

---

### ستون‌های پیشنهادی

| ستون | نوع | توضیح |
|---|---|---|
| `id` | BIGINT UNSIGNED AUTO_INCREMENT | شناسه |
| `check_key` | VARCHAR(150) | کلید بررسی |
| `name` | VARCHAR(191) | نام نمایشی |
| `status` | VARCHAR(50) | وضعیت |
| `message` | TEXT NULL | پیام |
| `checked_at` | DATETIME | زمان بررسی |
| `duration_ms` | INT UNSIGNED NULL | مدت بررسی |
| `severity` | VARCHAR(50) | شدت |
| `metadata` | JSON NULL | داده تکمیلی |
| `created_at` | DATETIME NULL | زمان ایجاد |

---

### وضعیت‌های پیشنهادی

```text
ok
warning
failed
unknown
```

---

### severityهای پیشنهادی

```text
low
medium
high
critical
```

---

### نمونه check_key

```text
database.connection
storage.private_writable
storage.public_writable
backup.last_success
cron.last_run
queue.pending_jobs
php.version
app.version
```

---

### قوانین

- Health Check نباید اطلاعات حساس را نمایش دهد.
- وضعیت critical باید برای Super Admin قابل مشاهده باشد.
- بررسی‌های سنگین نباید در هر Request اجرا شوند.
- Health Check می‌تواند با Cron بروزرسانی شود.
- خطاهای Health Check مهم باید Notification ایجاد کنند.

---

### Indexهای پیشنهادی

```text
idx_system_health_checks_check_key
idx_system_health_checks_status
idx_system_health_checks_severity
idx_system_health_checks_checked_at
```

---

## رابطه جدول‌های Core با سایر Domainها

Core Tables پایه اجرای سایر Domainها هستند.

نمونه ارتباط‌ها:

| Core Table | ارتباط |
|---|---|
| `number_sequences` | تولید شماره قرارداد، پرداخت، پرونده حقوقی و خروجی گزارش |
| `jobs` | ارسال اعلان، ساخت گزارش، بکاپ، پاکسازی فایل‌های منقضی |
| `cron_locks` | جلوگیری از اجرای همزمان بکاپ، یادآوری‌ها و پردازش اقساط |
| `feature_flags` | کنترل قابلیت‌های جدید بدون تغییر کد |
| `maintenance_modes` | استفاده در Update، Restore و Migration حساس |
| `app_versions` | ارتباط با Backup & Update |
| `migrations` | اجرای Migrationهای Core و Plugin |
| `system_health_checks` | نمایش وضعیت کلی سیستم |

---

## قوانین امنیتی Core Tables

قوانین الزامی:

- دسترسی مستقیم کاربر عادی به Core Tables ممنوع است.
- تغییر Core Tables فقط از Serviceهای رسمی انجام شود.
- عملیات حساس باید Audit Log داشته باشد.
- تغییر نسخه سیستم باید Audit Log داشته باشد.
- تغییر Feature Flag حساس باید Audit Log داشته باشد.
- فعال‌سازی Maintenance Mode باید Audit Log داشته باشد.
- خطاهای امنیتی مرتبط با Core باید Security Log داشته باشند.
- اطلاعات حساس نباید در payload خام Jobها ذخیره شود.
- مسیرهای واقعی فایل و اطلاعات اتصال نباید در Health Check عمومی نمایش داده شوند.

---

## قوانین Performance

قوانین:

- جدول `jobs` باید Index مناسب برای Queue داشته باشد.
- Jobهای completed قدیمی باید طبق Policy پاکسازی یا Archive شوند.
- جدول `system_health_checks` نباید بیش از حد بزرگ شود.
- جدول `cron_locks` باید Lockهای expired را پاکسازی کند.
- جدول `failed_jobs` باید قابلیت پاکسازی داشته باشد.
- Queryهای Core باید سبک باشند.
- از ذخیره فایل یا داده حجیم داخل Core Tables خودداری شود.
- Payloadهای بزرگ باید فقط در صورت نیاز ذخیره شوند.

---

## قوانین Migration برای Core Tables

قوانین:

- جدول‌های `migrations` و `migration_batches` باید در ابتدای نصب ساخته شوند.
- Migrationهای Core باید قبل از Domainهای دیگر اجرا شوند.
- Migrationهای Core نباید به پلاگین وابسته باشند.
- تغییر ساختار Core Tables باید با احتیاط انجام شود.
- Migration مخرب روی Core Tables باید Backup داشته باشد.
- هر Migration باید قابل تشخیص، قابل اجرا و قابل ردیابی باشد.

---

## Seedهای ضروری Core

Seedهای پیشنهادی:

### system_modules

```text
customers
contracts
installments
payments
legal
reports
files
notifications
calendar
chat
plugins
backup_update
settings
```

### number_sequences

```text
contract_number
payment_number
legal_case_number
backup_number
report_export_number
```

### feature_flags

```text
reports.advanced_export
plugins.upload_enabled
backup.auto_backup_enabled
payments.card_to_card_review
legal.referral_enabled
```

### app_versions

```text
1.0.0
```

---

## چک‌لیست پیاده‌سازی

قبل از پیاده‌سازی Core Tables بررسی شود:

- [ ] جدول `migrations` وجود دارد.
- [ ] جدول `migration_batches` وجود دارد.
- [ ] جدول `app_versions` وجود دارد.
- [ ] جدول `system_modules` وجود دارد.
- [ ] جدول `feature_flags` وجود دارد.
- [ ] جدول `maintenance_modes` وجود دارد.
- [ ] جدول `jobs` وجود دارد.
- [ ] جدول `failed_jobs` وجود دارد.
- [ ] جدول `cron_locks` وجود دارد.
- [ ] جدول `number_sequences` وجود دارد.
- [ ] جدول `app_installations` وجود دارد.
- [ ] جدول `system_health_checks` وجود دارد.
- [ ] Indexهای ضروری تعریف شده‌اند.
- [ ] Seedهای اولیه آماده هستند.
- [ ] عملیات حساس Audit Log دارند.
- [ ] داده حساس در Core Tables ذخیره نشده است.
- [ ] Core Tables به پلاگین خاص وابسته نیستند.

---

## Definition of Done

Core Tables زمانی کامل هستند که:

- سیستم بتواند نصب اولیه را تشخیص دهد.
- Migrationها قابل اجرا و قابل ردیابی باشند.
- نسخه سیستم در دیتابیس ثبت شود.
- ماژول‌های داخلی قابل مدیریت باشند.
- Feature Flagها قابل کنترل باشند.
- Maintenance Mode قابل فعال و غیرفعال شدن باشد.
- Jobهای ساده قابل صف‌بندی و اجرا باشند.
- Jobهای شکست‌خورده قابل مشاهده و Retry باشند.
- Cron Lock از اجرای همزمان عملیات حساس جلوگیری کند.
- شماره‌های رسمی بدون تکرار تولید شوند.
- Health Check وضعیت کلی سیستم را نشان دهد.
- همه جدول‌های Core با نام‌گذاری استاندارد ساخته شده باشند.
- Indexهای ضروری وجود داشته باشند.
- عملیات حساس Audit و Security مناسب داشته باشند.
- Core بدون هیچ پلاگین اختیاری قابل اجرا باشد.

---

## پایان فایل