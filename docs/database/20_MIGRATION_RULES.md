# 20 — Migration Rules

مستند قوانین Migration، ساخت جدول‌ها، تغییر ساختار دیتابیس، Seedها، Rollback، Backup، نسخه‌بندی و اجرای امن تغییرات دیتابیس در پروژه **Proma Pay**

---

## فهرست مطالب

- [20 — Migration Rules](#20--migration-rules)
  - [فهرست مطالب](#فهرست-مطالب)
  - [هدف فایل](#هدف-فایل)
  - [تعریف Migration در Proma Pay](#تعریف-migration-در-proma-pay)
  - [اصل مهم](#اصل-مهم)
  - [قوانین عمومی Migration](#قوانین-عمومی-migration)
  - [قوانین نام‌گذاری Migrationها](#قوانین-نامگذاری-migrationها)
    - [الگوی پیشنهادی](#الگوی-پیشنهادی)
    - [قوانین](#قوانین)
  - [ساختار پیشنهادی فایل Migration](#ساختار-پیشنهادی-فایل-migration)
  - [جدول migrations](#جدول-migrations)
    - [هدف جدول](#هدف-جدول)
    - [نام جدول](#نام-جدول)
    - [ستون‌های پیشنهادی](#ستونهای-پیشنهادی)
    - [statusهای پیشنهادی](#statusهای-پیشنهادی)
    - [قوانین](#قوانین-1)
    - [نمونه ساختار SQL](#نمونه-ساختار-sql)
  - [قوانین ساخت جدول جدید](#قوانین-ساخت-جدول-جدید)
  - [قوانین تغییر جدول موجود](#قوانین-تغییر-جدول-موجود)
  - [قوانین حذف جدول یا ستون](#قوانین-حذف-جدول-یا-ستون)
  - [قوانین Index در Migration](#قوانین-index-در-migration)
  - [قوانین Seed در Migration](#قوانین-seed-در-migration)
  - [قوانین داده‌های حساس در Migration](#قوانین-دادههای-حساس-در-migration)
  - [قوانین Transaction در Migration](#قوانین-transaction-در-migration)
  - [قوانین Backup قبل از Migration](#قوانین-backup-قبل-از-migration)
  - [قوانین Rollback](#قوانین-rollback)
  - [قوانین Migrationهای مالی](#قوانین-migrationهای-مالی)
  - [قوانین Migrationهای حقوقی](#قوانین-migrationهای-حقوقی)
  - [قوانین Migrationهای فایل](#قوانین-migrationهای-فایل)
  - [قوانین Migrationهای پلاگین](#قوانین-migrationهای-پلاگین)
  - [قوانین اجرای Migration در Shared Hosting](#قوانین-اجرای-migration-در-shared-hosting)
  - [قوانین Log و Audit برای Migration](#قوانین-log-و-audit-برای-migration)
  - [قوانین تست Migration](#قوانین-تست-migration)
  - [قوانین ترتیب اجرای Migrationها](#قوانین-ترتیب-اجرای-migrationها)
  - [Queryهای ممنوع در Migration](#queryهای-ممنوع-در-migration)
    - [حذف فیزیکی بی‌Backup](#حذف-فیزیکی-بیbackup)
    - [تغییر مالی بدون Log](#تغییر-مالی-بدون-log)
    - [Public کردن فایل حساس](#public-کردن-فایل-حساس)
    - [اجرای Update بدون WHERE](#اجرای-update-بدون-where)
    - [حذف داده Log حساس](#حذف-داده-log-حساس)
  - [چک‌لیست قبل از اجرای Migration](#چکلیست-قبل-از-اجرای-migration)
  - [چک‌لیست بعد از اجرای Migration](#چکلیست-بعد-از-اجرای-migration)
  - [Definition of Done](#definition-of-done)
  - [پایان فایل](#پایان-فایل)

---

## هدف فایل

هدف این فایل این است که قوانین اجرای Migrationهای دیتابیس در پروژه **Proma Pay** مشخص شود.

Migrationها مسئول ساخت و تغییر ساختار دیتابیس هستند و اگر اشتباه طراحی شوند می‌توانند باعث از بین رفتن داده‌های مالی، حقوقی، مشتریان، قراردادها، پرداخت‌ها و فایل‌های حساس شوند.

این فایل برای Codex مشخص می‌کند که:

- Migrationها چگونه نام‌گذاری شوند.
- Migrationها چگونه اجرا شوند.
- چه تغییراتی امن هستند.
- چه تغییراتی پرریسک هستند.
- چه زمانی Backup الزامی است.
- Rollback چگونه مدیریت شود.
- Seedها چگونه وارد شوند.
- تغییرات مالی و حقوقی چگونه کنترل شوند.
- Indexها چگونه ساخته شوند.
- Migration پلاگین‌ها چگونه اجرا شود.
- در Shared Hosting چه محدودیت‌هایی باید رعایت شود.
- اجرای Migration چگونه Log و Audit شود.

---

## تعریف Migration در Proma Pay

Migration یعنی تغییر کنترل‌شده ساختار دیتابیس.

Migration می‌تواند شامل موارد زیر باشد:

- ساخت جدول جدید
- افزودن ستون جدید
- تغییر نوع ستون
- افزودن Index
- افزودن Unique Index
- افزودن Seed اولیه
- ساخت جدول‌های پلاگین
- اجرای تغییرات نسخه جدید سیستم
- اصلاح ساختار داده
- اضافه کردن ستون‌های Audit
- اضافه کردن ستون‌های Soft Delete
- ساخت جدول‌های Log
- آماده‌سازی دیتابیس برای Feature جدید

Migration نباید بی‌ردپا، دستی و بدون ثبت انجام شود.

---

## اصل مهم

اصل مهم در Migration:

> هیچ تغییر ساختاری روی دیتابیس نباید بدون ثبت، کنترل، Backup و قابلیت بررسی انجام شود.

Migration باید:

- قابل تکرار امن باشد.
- قابل ردیابی باشد.
- قابل تست باشد.
- تا حد امکان idempotent باشد.
- بدون حذف ناگهانی داده اجرا شود.
- قبل از عملیات خطرناک Backup داشته باشد.
- نتیجه اجرای آن در جدول migrations ثبت شود.
- خطای آن قابل بررسی باشد.
- در صورت حساس بودن Audit Log داشته باشد.

قانون طلایی:

> Migration نباید داده مالی، حقوقی یا حساس را بدون Snapshot، Backup و Audit تغییر دهد.

---

## قوانین عمومی Migration

قوانین عمومی:

- هر Migration باید نام یکتا داشته باشد.
- هر Migration باید نسخه یا timestamp داشته باشد.
- هر Migration باید در جدول `migrations` ثبت شود.
- Migration نباید دوباره اجرا شود، اگر قبلاً با موفقیت اجرا شده است.
- Migration باید قبل از اجرا بررسی کند که جدول، ستون یا Index وجود دارد یا نه.
- Migration باید تا حد امکان idempotent باشد.
- Migration نباید داده حساس خام تولید یا ذخیره کند.
- Migration نباید Secret در دیتابیس Seed کند.
- Migration نباید فایل واقعی را داخل دیتابیس ذخیره کند.
- Migration نباید مسیر Public برای فایل حساس ایجاد کند.
- Migration سنگین باید با Backup و Maintenance Plan انجام شود.
- Migration نباید عملیات خارجی مثل SMS، Email یا API Call انجام دهد.
- Migration نباید محاسبات مالی قطعی را بدون Financial Log انجام دهد.
- Migration نباید وضعیت حقوقی پرونده را بدون Audit تغییر دهد.

---

## قوانین نام‌گذاری Migrationها

نام Migration باید قابل فهم، مرتب و قابل ردیابی باشد.

### الگوی پیشنهادی

```text
YYYY_MM_DD_HHMMSS_action_description.php
```

مثال:

```text
2026_01_10_120000_create_customers_tables.php
2026_01_10_121000_create_contracts_tables.php
2026_01_10_122000_create_installments_tables.php
2026_01_10_123000_add_indexes_to_payments_table.php
2026_01_10_124000_seed_default_permissions.php
```

### قوانین

- نام فایل باید انگلیسی باشد.
- نام فایل باید snake_case باشد.
- نام فایل باید با timestamp شروع شود.
- نام فایل باید هدف Migration را مشخص کند.
- Migrationهای ساخت جدول با `create_` شروع شوند.
- Migrationهای تغییر جدول با `alter_` یا `add_` شروع شوند.
- Migrationهای Seed با `seed_` شروع شوند.
- Migrationهای پلاگین با `plugin_{plugin_id}_` مشخص شوند.
- نام Migration نباید مبهم باشد.

---

## ساختار پیشنهادی فایل Migration

ساختار کلی Migration باید ساده، قابل تست و سازگار با PHP 7.4+ باشد.

نمونه ساختار پیشنهادی:

```php
<?php

return [
    'name' => '2026_01_10_120000_create_customers_tables',
    'description' => 'Create customers domain tables',
    'up' => function (PDO $pdo): void {
        // Create or alter tables here.
    },
    'down' => function (PDO $pdo): void {
        // Safe rollback here if possible.
    },
];
```

قوانین:

- Migration باید `up` داشته باشد.
- Migration بهتر است `down` داشته باشد.
- اگر Rollback امن نیست، باید صراحتاً مشخص شود.
- Migration باید از PDO Prepared Statements استفاده کند، اگر Query داده‌ای دارد.
- Migration نباید از ورودی خام کاربر استفاده کند.
- Migration نباید به UI وابسته باشد.
- Migration نباید به Session کاربر وابسته باشد.
- Migration باید مستقل و قابل اجرا در CLI یا Admin Panel باشد.

---

## جدول migrations

### هدف جدول

جدول `migrations` Migrationهای اجراشده را نگهداری می‌کند.

این جدول مشخص می‌کند چه Migrationهایی اجرا شده‌اند، چه زمانی اجرا شده‌اند و نتیجه اجرای آن‌ها چه بوده است.

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
| `migration_name` | VARCHAR(191) | نام Migration |
| `batch` | INT UNSIGNED | شماره Batch |
| `status` | VARCHAR(50) | وضعیت اجرا |
| `started_at` | DATETIME NULL | زمان شروع |
| `finished_at` | DATETIME NULL | زمان پایان |
| `execution_time_ms` | INT UNSIGNED NULL | مدت اجرا |
| `checksum_sha256` | VARCHAR(128) NULL | هش فایل Migration |
| `executed_by` | BIGINT UNSIGNED NULL | اجراکننده |
| `error_message` | TEXT NULL | پیام خطا |
| `metadata` | JSON NULL | داده تکمیلی |
| `created_at` | DATETIME NULL | زمان ثبت |

---

### statusهای پیشنهادی

```text
pending
running
completed
failed
rolled_back
skipped
blocked
```

---

### قوانین

- `migration_name` باید Unique باشد.
- Migration completed نباید دوباره اجرا شود.
- Migration failed باید error_message داشته باشد.
- Migration running نباید طولانی رها شود.
- Migration rolled_back باید ثبت شود.
- checksum برای تشخیص تغییر فایل Migration استفاده شود.
- تغییر فایل Migration اجراشده باید ممنوع یا هشداردهنده باشد.
- این جدول نباید Soft Delete شود.

---

### نمونه ساختار SQL

```sql
CREATE TABLE migrations (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    migration_name VARCHAR(191) NOT NULL,
    batch INT UNSIGNED NOT NULL DEFAULT 1,
    status VARCHAR(50) NOT NULL DEFAULT 'pending',
    started_at DATETIME NULL,
    finished_at DATETIME NULL,
    execution_time_ms INT UNSIGNED NULL,
    checksum_sha256 VARCHAR(128) NULL,
    executed_by BIGINT UNSIGNED NULL,
    error_message TEXT NULL,
    metadata JSON NULL,
    created_at DATETIME NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uniq_migrations_migration_name (migration_name),
    KEY idx_migrations_batch (batch),
    KEY idx_migrations_status (status),
    KEY idx_migrations_started_at (started_at),
    KEY idx_migrations_finished_at (finished_at),
    KEY idx_migrations_executed_by (executed_by)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

---

## قوانین ساخت جدول جدید

برای ساخت جدول جدید، قوانین زیر الزامی است:

- جدول باید نام انگلیسی، plural و snake_case داشته باشد.
- جدول باید `id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY` داشته باشد.
- جدول باید `created_at` و `updated_at` داشته باشد، مگر Log/History خاص باشد.
- جدول‌های قابل حذف باید Soft Delete داشته باشند.
- جدول‌های حساس باید actor fields داشته باشند.
- ستون‌های پولی باید `DECIMAL(15,2)` باشند.
- ستون‌های تاریخ و زمان باید طبق استاندارد `_at` یا `_date` نام‌گذاری شوند.
- ستون‌های Boolean باید با `is_`, `has_`, `can_`, `should_`, `requires_` شروع شوند.
- ستون‌های FK باید با `_id` تمام شوند.
- ستون‌های JSON فقط برای metadata یا config استفاده شوند.
- هر جدول باید Indexهای ضروری داشته باشد.
- Charset باید `utf8mb4` باشد.
- Collation باید `utf8mb4_unicode_ci` باشد.

نمونه:

```sql
CREATE TABLE example_entities (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    example_number VARCHAR(50) NOT NULL,
    customer_id BIGINT UNSIGNED NULL,
    status VARCHAR(50) NOT NULL DEFAULT 'active',
    amount DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    metadata JSON NULL,
    created_at DATETIME NULL,
    updated_at DATETIME NULL,
    deleted_at DATETIME NULL,
    created_by BIGINT UNSIGNED NULL,
    updated_by BIGINT UNSIGNED NULL,
    deleted_by BIGINT UNSIGNED NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uniq_example_entities_example_number (example_number),
    KEY idx_example_entities_customer_id (customer_id),
    KEY idx_example_entities_status (status),
    KEY idx_example_entities_deleted_at (deleted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

---

## قوانین تغییر جدول موجود

تغییر جدول موجود باید با احتیاط انجام شود.

تغییرات کم‌ریسک:

- افزودن ستون Nullable
- افزودن ستون با Default امن
- افزودن Index روی ستون موجود
- افزودن جدول جدید مرتبط
- افزودن Seed غیرحساس

تغییرات پرریسک:

- تغییر نوع ستون
- Rename ستون
- حذف ستون
- حذف جدول
- افزودن Not Null بدون Default
- افزودن Unique Index روی داده موجود
- تغییر ستون پولی
- تغییر ستون وضعیت
- تغییر جدول‌های مالی، حقوقی یا پرداخت

قوانین:

- قبل از تغییر پرریسک باید Backup گرفته شود.
- قبل از افزودن Unique Index باید داده تکراری بررسی شود.
- قبل از تغییر نوع ستون باید داده ناسازگار بررسی شود.
- تغییر جدول بزرگ باید در زمان مناسب انجام شود.
- تغییر جدول بزرگ باید با Maintenance Plan بررسی شود.
- تغییر ستون حساس باید Audit Log داشته باشد.
- تغییر مالی باید با Financial Log هماهنگ باشد.

---

## قوانین حذف جدول یا ستون

حذف جدول یا ستون بسیار پرریسک است.

قانون پیش‌فرض:

> حذف فیزیکی ستون یا جدول در Migration عادی ممنوع است، مگر با دلیل بسیار روشن، Backup و Approval.

راهکار بهتر:

- ستون را deprecated کنید.
- ستون را دیگر استفاده نکنید.
- جدول را archived کنید.
- داده را به جدول جدید منتقل کنید.
- بعد از چند نسخه و Backup رسمی حذف کنید.

قوانین حذف:

- حذف باید Migration جدا داشته باشد.
- حذف باید Backup قبل از اجرا داشته باشد.
- حذف باید Audit Log داشته باشد.
- حذف باید در Release Notes ثبت شود.
- حذف جدول مالی، حقوقی، پرداخت، فایل، لاگ و بکاپ ممنوع است، مگر Archive رسمی و تصمیم مدیریتی.
- حذف ستون دارای داده حساس باید با Retention Policy هماهنگ باشد.

---

## قوانین Index در Migration

قوانین:

- هر Index باید نام استاندارد داشته باشد.
- قبل از ساخت Index باید بررسی شود که وجود ندارد.
- Index تکراری ساخته نشود.
- ساخت Index روی جدول بزرگ باید در Migration جدا باشد.
- ساخت Unique Index باید قبلش duplicate check داشته باشد.
- حذف Index باید با بررسی Queryهای وابسته انجام شود.
- Indexهای Composite باید بر اساس Query واقعی ساخته شوند.
- Index روی JSON پیش‌فرض توصیه نمی‌شود.
- Index روی TEXT طولانی باید با احتیاط باشد.
- ساخت Index نباید باعث Timeout در Shared Hosting شود.

نمونه بررسی منطقی:

```text
Before adding index:
1. Check information_schema.statistics
2. If index exists, skip
3. If not exists, create index
4. Log result
```

---

## قوانین Seed در Migration

Seed یعنی وارد کردن داده‌های اولیه.

Seedهای مجاز:

- نقش‌های اولیه
- Permissionهای اولیه
- Setting Groups
- System Policies
- Notification Types
- Payment Methods
- Default Report Definitions
- Default Plugin Types
- Default Statuses

قوانین Seed:

- Seed باید idempotent باشد.
- اگر رکورد وجود دارد، دوباره ایجاد نشود.
- Seed نباید ID ثابت را فرض کند، مگر ضروری و کنترل‌شده.
- Seed نباید Secret خام ذخیره کند.
- Seed نباید Password پیش‌فرض ناامن بسازد.
- Seed نباید داده واقعی مشتری وارد کند.
- Seed نباید قرارداد یا پرداخت واقعی بسازد.
- Seed باید قابل اجرا چندباره بدون خرابی باشد.
- Seed حساس باید Audit یا System Log داشته باشد.

مثال الگو:

```text
If permission_key exists:
    skip
Else:
    insert permission
```

---

## قوانین داده‌های حساس در Migration

داده‌های زیر نباید خام در Migration ذخیره شوند:

```text
password
api_key
secret
token
private_key
webhook_secret
otp
session_id
customer_identity_document
contract_private_file_path
backup_file_path_public
```

قوانین:

- Secretها باید در `.env` یا secure_settings رمزنگاری‌شده مدیریت شوند.
- Password پیش‌فرض باید تصادفی یا در مرحله Setup ساخته شود.
- API Key نباید در فایل Migration باشد.
- Token نباید در Git ذخیره شود.
- مسیر فایل Private نباید Public شود.
- داده مشتری واقعی نباید در Migration باشد.
- داده مالی واقعی نباید در Migration باشد.

---

## قوانین Transaction در Migration

برخی Migrationها باید Transaction داشته باشند.

مناسب برای Transaction:

- Insert Seedها
- ساخت داده‌های وابسته
- تغییرات کوچک و امن
- ثبت migration log و داده مرتبط

نامناسب برای Transaction طولانی:

- ساخت Index روی جدول بزرگ
- تغییر ساختار سنگین
- Copy حجم زیاد داده
- پردازش فایل
- Backup
- Export
- عملیات طولانی

قوانین:

- Transaction باید کوتاه باشد.
- عملیات فایل داخل Transaction انجام نشود.
- API Call داخل Transaction انجام نشود.
- Migration سنگین نباید Transaction بلندمدت باز نگه دارد.
- در صورت خطا، Rollback انجام شود.
- اگر DB Engine اجازه Rollback برای DDL ندهد، باید برنامه Recovery وجود داشته باشد.

---

## قوانین Backup قبل از Migration

Backup قبل از Migration در موارد زیر الزامی است:

- تغییر جدول‌های مالی
- تغییر جدول‌های پرداخت
- تغییر جدول‌های قرارداد
- تغییر جدول‌های اقساط
- تغییر جدول‌های حقوقی
- تغییر جدول‌های فایل
- حذف ستون یا جدول
- تغییر نوع ستون
- افزودن Unique Index روی داده موجود
- Migration پلاگین حساس
- Update سیستم
- Restore
- تغییرات گسترده داده
- Migration روی جدول حجیم

قوانین:

- Backup باید در `backups` ثبت شود.
- فایل Backup باید در Files Domain و Private Storage باشد.
- Migration باید به backup_id مرتبط شود، اگر حساس است.
- Backup failed یعنی Migration حساس نباید اجرا شود.
- Backup باید قبل از شروع عملیات پرریسک انجام شود.
- بعد از Migration موفق، Notification مدیریتی می‌تواند ایجاد شود.

---

## قوانین Rollback

Rollback یعنی برگشت دادن Migration.

همه Migrationها Rollback امن ندارند.

Rollback امن:

- حذف جدول تازه ساخته‌شده و خالی
- حذف Index تازه اضافه‌شده
- حذف Seed غیرحساس تازه اضافه‌شده
- برگرداندن status یک Setting کم‌ریسک

Rollback پرریسک:

- حذف ستون دارای داده
- برگرداندن تغییر نوع ستون
- برگشت تغییرات مالی
- برگشت تغییرات حقوقی
- برگشت Migrationهای داده‌ای بزرگ
- Rollback روی فایل‌های Private
- Rollback پلاگین با داده تولیدشده

قوانین:

- اگر Rollback امن نیست، باید در Migration اعلام شود.
- Rollback حساس باید Backup داشته باشد.
- Rollback باید Log شود.
- Rollback مالی باید Financial Log داشته باشد، اگر اثر مالی دارد.
- Rollback حقوقی باید Audit Log داشته باشد.
- Rollback نباید داده جدید کاربران را بی‌دلیل حذف کند.
- Rollback Update باید با Backup & Update Domain هماهنگ باشد.

---

## قوانین Migrationهای مالی

Migration مالی بسیار حساس است.

شامل:

- جدول‌های financial
- payments
- installments
- contracts
- settlements
- adjustments
- ledgers
- penalties
- refunds

قوانین:

- ستون پولی فقط DECIMAL باشد.
- هیچ Migration نباید مبلغ پرداخت، قسط یا بدهی را بی‌ردپا تغییر دهد.
- تغییر داده مالی باید Financial Log داشته باشد.
- تغییر داده مالی باید Audit Log داشته باشد.
- قبل از Migration مالی Backup الزامی است.
- Snapshot قبل و بعد توصیه می‌شود.
- فرمول‌های مالی نباید در Migration پراکنده شوند.
- Recalculate مالی باید در Service رسمی انجام شود، نه SQL خام پراکنده.
- داده مالی قبلی نباید بدون نسخه یا دلیل overwrite شود.

---

## قوانین Migrationهای حقوقی

Migration حقوقی شامل داده‌های پرونده‌ها، مدارک، Claims و Deadlines است.

قوانین:

- قبل از Migration حقوقی Backup الزامی است.
- تغییر وضعیت پرونده حقوقی باید Audit Log داشته باشد.
- تغییر claim_amount باید Financial/Audit هماهنگ داشته باشد، اگر اثر مالی دارد.
- مدارک حقوقی نباید Public شوند.
- فایل‌های حقوقی باید در Files Domain و Private Storage باشند.
- یادداشت‌های حقوقی نباید برای نقش غیرمجاز قابل مشاهده شوند.
- Migration نباید پرونده را بدون دلیل close یا cancel کند.
- Snapshotهای ارجاع حقوقی نباید حذف یا overwrite شوند.

---

## قوانین Migrationهای فایل

Migration مربوط به Files Domain حساس است.

قوانین:

- فایل واقعی داخل دیتابیس ذخیره نشود.
- مسیر فایل Private نباید Public شود.
- تغییر storage_path باید با احتیاط انجام شود.
- قبل از Migration فایل Backup الزامی است، اگر مسیر یا visibility تغییر می‌کند.
- فایل‌های حساس باید Private بمانند.
- فایل‌های deleted، blocked یا quarantined نباید دوباره فعال شوند، مگر با دلیل و Audit.
- Migration نباید فایل را بدون بررسی Security Scan approved کند.
- تغییر visibility فایل حساس باید Audit و Security Log داشته باشد.

---

## قوانین Migrationهای پلاگین

Migrationهای پلاگین باید محدود و کنترل‌شده باشند.

قوانین:

- نام جدول پلاگین باید Prefix داشته باشد.

```text
plugin_{plugin_id}_{table_name}
```

- Migration پلاگین باید در `plugin_migrations` ثبت شود.
- Migration پلاگین باید idempotent باشد.
- Migration پلاگین نباید جدول‌های Core را خطرناک تغییر دهد.
- Migration پلاگین نباید Permission را دور بزند.
- Migration پلاگین حساس باید Backup بخواهد.
- Migration پلاگین failed باید نصب پلاگین را متوقف کند.
- Rollback پلاگین فقط اگر امن باشد اجرا شود.
- Migration پلاگین باید با Security Scan بسته هماهنگ باشد.

---

## قوانین اجرای Migration در Shared Hosting

چون پروژه باید Shared Hosting compatible باشد، Migrationها باید سبک و کنترل‌شده باشند.

قوانین:

- Migration نباید Timeout طولانی ایجاد کند.
- عملیات سنگین باید مرحله‌ای شود.
- ساخت Index روی جدول بزرگ باید جداگانه اجرا شود.
- Copy داده حجیم باید Chunk شود.
- Export یا Backup داخل Migration انجام نشود، فقط قبل از Migration انجام شود.
- Memory زیاد مصرف نشود.
- Migration باید قابل اجرا از Admin Panel یا CLI باشد.
- در Admin Panel باید Progress یا نتیجه نمایش داده شود.
- اگر Migration طولانی است، Maintenance Mode بررسی شود.
- Queryهای Migration باید بهینه باشند.

---

## قوانین Log و Audit برای Migration

Migrationها باید قابل ردیابی باشند.

حداقل Log:

- نام Migration
- وضعیت اجرا
- زمان شروع
- زمان پایان
- اجراکننده
- خطا
- batch
- checksum

برای Migration حساس Audit Log الزامی است:

- Migration مالی
- Migration حقوقی
- Migration فایل‌های حساس
- Migration Backup/Restore/Update
- Migration پلاگین
- Migration تغییر Permissionها
- Migration تغییر Settings حساس

Security Log الزامی است برای:

- تلاش اجرای Migration بدون Permission
- تلاش اجرای Migration دستکاری‌شده
- checksum mismatch
- تلاش اجرای Migration خطرناک بدون Backup
- CSRF نامعتبر در اجرای Migration از پنل
- تلاش Rollback بدون Permission

---

## قوانین تست Migration

قبل از اجرای Production:

- Migration باید روی دیتابیس تست اجرا شود.
- Migration باید روی دیتابیس دارای داده نمونه اجرا شود.
- Migration باید چندبار اجرا شود تا idempotent بودن بررسی شود.
- Rollback در صورت وجود باید تست شود.
- اجرای Migration باید خطاهای SQL را مدیریت کند.
- Indexهای ساخته‌شده باید با EXPLAIN بررسی شوند.
- Migrationهای مالی باید با داده تست مالی بررسی شوند.
- Migrationهای حقوقی باید با پرونده تست بررسی شوند.
- Migrationهای فایل باید Private Storage را حفظ کنند.
- Migrationهای پلاگین باید با Security Scan تست شوند.

---

## قوانین ترتیب اجرای Migrationها

ترتیب اجرا مهم است.

ترتیب پیشنهادی برای پروژه:

```text
1. Core Tables
2. Users / Roles / Permissions
3. Customers
4. Contracts
5. Installments
6. Payments
7. Financial
8. Legal
9. Chat
10. Notifications
11. Calendar
12. Files
13. Settings
14. Reports
15. Backup & Update
16. Plugins
17. Logs / Audit / Security
18. Indexهای تکمیلی
19. Seedهای نهایی
```

قوانین:

- جدول مرجع باید قبل از جدول وابسته ساخته شود.
- Permissionها باید قبل از Featureهای نیازمند Permission Seed شوند.
- Settings پایه باید قبل از استفاده Featureها وجود داشته باشند.
- Files Domain باید قبل از ذخیره فایل‌های رسمی آماده باشد.
- Logs بهتر است زودتر ساخته شود، اما حداقل قبل از عملیات حساس فعال باشد.
- Indexهای سنگین می‌توانند بعد از ساخت جدول‌ها در Migration جدا اجرا شوند.

---

## Queryهای ممنوع در Migration

### حذف فیزیکی بی‌Backup

ممنوع:

```sql
DROP TABLE payments;
```

ممنوع:

```sql
ALTER TABLE contracts DROP COLUMN total_amount;
```

---

### تغییر مالی بدون Log

ممنوع:

```sql
UPDATE installments
SET amount = amount + 100000;
```

---

### Public کردن فایل حساس

ممنوع:

```sql
UPDATE files
SET is_public = 1
WHERE file_category = 'legal_document';
```

---

### اجرای Update بدون WHERE

ممنوع:

```sql
UPDATE contracts
SET status = 'closed';
```

---

### حذف داده Log حساس

ممنوع:

```sql
DELETE FROM audit_logs;
```

ممنوع:

```sql
DELETE FROM financial_logs;
```

ممنوع:

```sql
DELETE FROM security_logs;
```

---

## چک‌لیست قبل از اجرای Migration

قبل از اجرای Migration بررسی شود:

- [ ] نام Migration استاندارد و یکتا است.
- [ ] Migration در محیط تست اجرا شده است.
- [ ] Migration idempotent است یا کنترل دوباره‌اجرا شدن دارد.
- [ ] Backup برای Migration حساس گرفته شده است.
- [ ] Migration روی داده مالی اثر غیرمجاز ندارد.
- [ ] Migration روی داده حقوقی اثر غیرمجاز ندارد.
- [ ] Migration فایل حساس را Public نمی‌کند.
- [ ] Migration Secret خام ذخیره نمی‌کند.
- [ ] Migration Permission را دور نمی‌زند.
- [ ] Queryهای پرریسک بررسی شده‌اند.
- [ ] Indexهای جدید بررسی شده‌اند.
- [ ] Unique Indexها duplicate check دارند.
- [ ] Rollback اگر وجود دارد تست شده است.
- [ ] Shared Hosting محدودیت‌ها رعایت شده‌اند.
- [ ] Migration نیازمند Maintenance Mode مشخص شده است.
- [ ] اجرای Migration Permission ویژه دارد.
- [ ] Audit و Security Log برای عملیات حساس آماده است.

---

## چک‌لیست بعد از اجرای Migration

بعد از اجرای Migration بررسی شود:

- [ ] رکورد Migration با status completed ثبت شده است.
- [ ] خطایی در error_logs ثبت نشده است.
- [ ] جدول‌ها یا ستون‌های جدید وجود دارند.
- [ ] Indexهای جدید ساخته شده‌اند.
- [ ] Seedها تکراری ایجاد نشده‌اند.
- [ ] داده‌های مالی سالم هستند.
- [ ] داده‌های حقوقی سالم هستند.
- [ ] فایل‌های حساس همچنان Private هستند.
- [ ] Permissionها درست کار می‌کنند.
- [ ] Queryهای مهم با EXPLAIN بررسی شده‌اند.
- [ ] Dashboard بدون خطا Load می‌شود.
- [ ] عملیات ثبت مشتری، قرارداد، قسط و پرداخت تست شده است.
- [ ] Audit Log برای Migration حساس ثبت شده است.
- [ ] Security Log خطای غیرمنتظره ندارد.
- [ ] Backup مربوطه قابل مشاهده و Private است.
- [ ] نسخه سیستم در صورت نیاز بروزرسانی شده است.

---

## Definition of Done

Migration Rules زمانی کامل هستند که:

- همه Migrationها نام استاندارد و یکتا داشته باشند.
- همه Migrationها در جدول `migrations` ثبت شوند.
- Migration completed دوباره اجرا نشود.
- Migrationها تا حد امکان idempotent باشند.
- ساخت جدول‌ها طبق استاندارد Database Naming انجام شود.
- تغییر جدول‌های موجود با Backup و بررسی ریسک انجام شود.
- حذف ستون یا جدول بدون Approval و Backup انجام نشود.
- Indexها استاندارد، ضروری و بدون تکرار باشند.
- Seedها امن، idempotent و بدون Secret خام باشند.
- Migrationهای مالی Financial Log و Audit لازم را رعایت کنند.
- Migrationهای حقوقی Audit و Privacy لازم را رعایت کنند.
- Migrationهای فایل Private Storage را حفظ کنند.
- Migrationهای پلاگین در `plugin_migrations` ثبت شوند.
- Rollback فقط در حالت امن اجرا شود.
- اجرای Migration در Shared Hosting باعث Timeout و فشار غیرقابل کنترل نشود.
- Migrationهای حساس Backup، Audit Log و Security Log داشته باشند.
- Codex بتواند بر اساس این مستندات Migrationهای دیتابیس Proma Pay را ایمن، قابل تست و قابل ردیابی بسازد.

---

## پایان فایل
````
