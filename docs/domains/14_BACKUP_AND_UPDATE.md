# 14 — Backup & Update Domain

مستند دامنه بکاپ، بازیابی، بروزرسانی و نگهداری نسخه در پروژه **Proma Pay**

---

## فهرست مطالب

- [14 — Backup \& Update Domain](#14--backup--update-domain)
  - [فهرست مطالب](#فهرست-مطالب)
  - [هدف این دامنه](#هدف-این-دامنه)
  - [مرز دامنه](#مرز-دامنه)
    - [این دامنه مسئول است برای:](#این-دامنه-مسئول-است-برای)
    - [این دامنه مسئول نیست برای:](#این-دامنه-مسئول-نیست-برای)
  - [تعریف Backup](#تعریف-backup)
  - [تعریف Restore](#تعریف-restore)
  - [تعریف Update](#تعریف-update)
  - [موجودیت‌های اصلی](#موجودیتهای-اصلی)
    - [backups](#backups)
    - [backup\_logs](#backup_logs)
    - [restore\_logs](#restore_logs)
    - [updates](#updates)
    - [update\_logs](#update_logs)
    - [migrations](#migrations)
  - [انواع بکاپ](#انواع-بکاپ)
  - [محتوای بکاپ](#محتوای-بکاپ)
    - [Database](#database)
    - [Private Files](#private-files)
    - [Public Files](#public-files)
    - [Configuration](#configuration)
  - [مواردی که نباید در بکاپ عمومی باشند](#مواردی-که-نباید-در-بکاپ-عمومی-باشند)
  - [قوانین امنیت بکاپ](#قوانین-امنیت-بکاپ)
  - [ZipArchive و Fallback](#ziparchive-و-fallback)
  - [Restore](#restore)
  - [هشدار Restore](#هشدار-restore)
  - [Update Package](#update-package)
  - [ساختار update.json](#ساختار-updatejson)
  - [Migrationها](#migrationها)
  - [Migrationهای ممنوع یا خطرناک](#migrationهای-ممنوع-یا-خطرناک)
  - [Rollback](#rollback)
  - [Maintenance Mode](#maintenance-mode)
  - [نسخه سیستم](#نسخه-سیستم)
  - [Workflowها](#workflowها)
    - [ایجاد بکاپ دستی](#ایجاد-بکاپ-دستی)
    - [بکاپ قبل از آپدیت](#بکاپ-قبل-از-آپدیت)
    - [Restore بکاپ](#restore-بکاپ)
    - [اجرای آپدیت](#اجرای-آپدیت)
    - [شکست آپدیت](#شکست-آپدیت)
  - [Permissionهای پیشنهادی](#permissionهای-پیشنهادی)
    - [Backup](#backup)
    - [Update](#update)
    - [Maintenance](#maintenance)
  - [دسترسی نقش‌ها](#دسترسی-نقشها)
    - [super\_admin](#super_admin)
    - [admin](#admin)
    - [accountant](#accountant)
    - [operator](#operator)
    - [lawyer](#lawyer)
    - [customer](#customer)
  - [Eventها](#eventها)
  - [Notificationها](#notificationها)
    - [BackupCompleted](#backupcompleted)
    - [BackupFailed](#backupfailed)
    - [UpdateCompleted](#updatecompleted)
    - [UpdateFailed](#updatefailed)
    - [RestoreCompleted](#restorecompleted)
  - [Logging و Audit](#logging-و-audit)
    - [Logهای عادی](#logهای-عادی)
    - [Audit Log](#audit-log)
    - [Security Log](#security-log)
  - [قوانین UI](#قوانین-ui)
  - [قوانین امنیتی](#قوانین-امنیتی)
  - [اطلاعات حساس](#اطلاعات-حساس)
  - [نکات دیتابیس](#نکات-دیتابیس)
  - [قوانین Validation](#قوانین-validation)
  - [قابلیت پلاگینی](#قابلیت-پلاگینی)
  - [اثر روی دامنه‌های دیگر](#اثر-روی-دامنههای-دیگر)
  - [چک‌لیست پیاده‌سازی](#چکلیست-پیادهسازی)
  - [چک‌لیست بازبینی](#چکلیست-بازبینی)
  - [Definition of Done](#definition-of-done)
  - [قابلیت‌های آینده](#قابلیتهای-آینده)
  - [پایان فایل](#پایان-فایل)

---

## هدف این دامنه

دامنه **Backup & Update** مسئول مدیریت بکاپ، بازیابی، بروزرسانی، Migration، Rollback، Maintenance Mode و نگهداری نسخه سیستم در Proma Pay است.

این دامنه باید مطمئن شود که قبل از هر عملیات حساس، مخصوصاً آپدیت و Restore، اطلاعات کاربران، مشتریان، قراردادها، اقساط، پرداخت‌ها، فایل‌ها و تنظیمات حفظ می‌شوند.

هدف اصلی این دامنه:

- جلوگیری از از دست رفتن داده‌ها
- امکان بازیابی سیستم
- اجرای امن آپدیت‌ها
- ثبت تاریخچه بکاپ‌ها و آپدیت‌ها
- جلوگیری از اجرای Migration خطرناک
- آماده‌سازی سیستم برای انتشار تجاری و آپدیت‌های آینده

---

## مرز دامنه

### این دامنه مسئول است برای:

- ایجاد بکاپ دستی
- ایجاد بکاپ خودکار
- مدیریت فایل‌های بکاپ
- بازیابی بکاپ
- اعتبارسنجی فایل بکاپ
- اجرای آپدیت Core
- اجرای آپدیت پلاگین‌ها
- بررسی update.json
- اجرای Migrationها
- ثبت نسخه سیستم
- فعال و غیرفعال کردن Maintenance Mode
- ثبت Log عملیات بکاپ و آپدیت
- Rollback در صورت امکان
- ارسال اعلان نتیجه بکاپ یا آپدیت

### این دامنه مسئول نیست برای:

- مدیریت مستقیم فایل‌های عمومی
- ذخیره Metadata عمومی فایل‌ها
- نصب پلاگین از صفر
- تولید گزارش‌های تحلیلی
- مدیریت کاربران
- مدیریت مشتریان
- اجرای منطق مالی
- تولید قرارداد

فایل‌های بکاپ و آپدیت از نظر ذخیره‌سازی باید با **Files Domain** هماهنگ باشند.

---

## تعریف Backup

Backup یعنی تهیه نسخه پشتیبان از داده‌ها و فایل‌های مهم سیستم، به شکلی که در صورت خرابی، حذف اشتباه، خطای آپدیت یا مشکل هاست بتوان سیستم را بازیابی کرد.

بکاپ باید بتواند شامل موارد زیر باشد:

- دیتابیس
- تنظیمات
- فایل‌های خصوصی
- فایل‌های عمومی ضروری
- قالب قراردادها
- تنظیمات پلاگین‌ها
- فایل‌های پلاگین‌ها در صورت نیاز
- Metadata فایل‌ها
- نسخه سیستم

---

## تعریف Restore

Restore یعنی بازیابی سیستم از یک فایل یا مجموعه فایل بکاپ.

Restore عملیات بسیار حساس است و می‌تواند داده‌های فعلی سیستم را تغییر دهد یا جایگزین کند.

بنابراین Restore باید فقط با Permission سطح بالا، تأیید چندمرحله‌ای و Audit Log انجام شود.

---

## تعریف Update

Update یعنی بروزرسانی فایل‌های سیستم، دیتابیس، Migrationها، تنظیمات یا پلاگین‌ها از یک نسخه به نسخه جدیدتر.

Update باید:

- امن باشد.
- قابل اعتبارسنجی باشد.
- قبل از اجرا Backup بگیرد.
- Migrationهای خطرناک را کنترل کند.
- در صورت خطا سیستم را در وضعیت مشخص نگه دارد.
- Log کامل داشته باشد.

---

## موجودیت‌های اصلی

### backups

جدول اصلی بکاپ‌ها.

| فیلد | توضیح |
|---|---|
| `id` | شناسه بکاپ |
| `backup_number` | شماره بکاپ |
| `backup_type` | نوع بکاپ |
| `status` | وضعیت |
| `file_id` | فایل بکاپ |
| `size` | حجم |
| `includes_database` | شامل دیتابیس |
| `includes_private_files` | شامل فایل‌های خصوصی |
| `includes_public_files` | شامل فایل‌های عمومی |
| `includes_plugins` | شامل پلاگین‌ها |
| `created_by` | ایجادکننده |
| `started_at` | زمان شروع |
| `completed_at` | زمان پایان |
| `failed_at` | زمان شکست |
| `error_message` | خطا |
| `metadata` | داده تکمیلی |
| `created_at` | تاریخ ایجاد |

---

### backup_logs

لاگ جزئیات عملیات بکاپ.

| فیلد | توضیح |
|---|---|
| `id` | شناسه |
| `backup_id` | بکاپ |
| `step` | مرحله |
| `status` | وضعیت مرحله |
| `message` | پیام |
| `created_at` | زمان ثبت |
| `metadata` | داده تکمیلی |

---

### restore_logs

لاگ عملیات Restore.

| فیلد | توضیح |
|---|---|
| `id` | شناسه |
| `backup_id` | بکاپ مورد استفاده |
| `status` | وضعیت |
| `started_by` | اجراکننده |
| `started_at` | شروع |
| `completed_at` | پایان |
| `failed_at` | شکست |
| `error_message` | خطا |
| `metadata` | داده تکمیلی |

---

### updates

جدول عملیات آپدیت.

| فیلد | توضیح |
|---|---|
| `id` | شناسه |
| `update_version` | نسخه مقصد |
| `from_version` | نسخه قبلی |
| `status` | وضعیت |
| `package_file_id` | فایل پکیج |
| `backup_id` | بکاپ قبل از آپدیت |
| `started_by` | اجراکننده |
| `started_at` | شروع |
| `completed_at` | پایان |
| `failed_at` | شکست |
| `error_message` | خطا |
| `metadata` | داده تکمیلی |

---

### update_logs

لاگ مراحل آپدیت.

| فیلد | توضیح |
|---|---|
| `id` | شناسه |
| `update_id` | آپدیت |
| `step` | مرحله |
| `status` | وضعیت |
| `message` | پیام |
| `created_at` | تاریخ |
| `metadata` | داده تکمیلی |

---

### migrations

جدول Migrationهای اجراشده.

| فیلد | توضیح |
|---|---|
| `id` | شناسه |
| `migration_name` | نام Migration |
| `batch` | شماره Batch |
| `source_type` | core یا plugin |
| `source_id` | شناسه منبع |
| `status` | وضعیت |
| `executed_at` | زمان اجرا |
| `error_message` | خطا |

---

## انواع بکاپ

انواع بکاپ پیشنهادی:

| نوع | توضیح |
|---|---|
| `manual` | بکاپ دستی توسط مدیر |
| `automatic` | بکاپ زمان‌بندی‌شده |
| `pre_update` | بکاپ قبل از آپدیت |
| `pre_restore` | بکاپ قبل از Restore |
| `pre_plugin_install` | بکاپ قبل از نصب پلاگین |
| `pre_plugin_update` | بکاپ قبل از آپدیت پلاگین |
| `emergency` | بکاپ اضطراری |

---

## محتوای بکاپ

بکاپ می‌تواند شامل بخش‌های زیر باشد:

### Database

شامل:

- مشتریان
- کاربران
- قراردادها
- اقساط
- پرداخت‌ها
- پرونده‌های حقوقی
- تنظیمات
- لاگ‌ها
- Metadata فایل‌ها
- تنظیمات پلاگین‌ها

### Private Files

شامل:

- مدارک هویتی
- رسیدهای پرداخت در صورت باقی بودن
- PDF قراردادها
- فایل‌های حقوقی
- فایل‌های خصوصی چت
- فایل‌های پلاگین‌ها

### Public Files

شامل:

- لوگو
- آواتارها
- فایل‌های عمومی لازم
- خروجی‌های ضروری در صورت نیاز

### Configuration

شامل:

- تنظیمات غیرحساس
- تنظیمات حساس به صورت encrypted
- نسخه سیستم
- وضعیت پلاگین‌ها

---

## مواردی که نباید در بکاپ عمومی باشند

در بکاپ قابل دانلود عمومی یا قابل ارسال به پشتیبانی نباید موارد حساس بدون هشدار وجود داشته باشد:

- اطلاعات واقعی مشتریان
- مدارک هویتی
- رسیدهای پرداخت
- فایل‌های حقوقی
- کلیدهای درگاه
- توکن‌های API
- اطلاعات دیتابیس
- Sessionها
- لاگ‌های امنیتی حساس

اگر بکاپ برای پشتیبانی ساخته می‌شود، باید نسخه Sanitized داشته باشد.

---

## قوانین امنیت بکاپ

بکاپ بسیار حساس است.

قوانین:

- فایل بکاپ باید در Private Storage ذخیره شود.
- فایل بکاپ نباید در Public باشد.
- دانلود بکاپ فقط برای super_admin یا نقش مجاز باشد.
- دانلود بکاپ باید Audit Log داشته باشد.
- حذف بکاپ باید Audit Log داشته باشد.
- بکاپ نباید داخل Release ZIP قرار بگیرد.
- نام فایل بکاپ نباید قابل حدس باشد.
- در صورت امکان، بکاپ رمزنگاری یا حداقل محافظت شود.
- بکاپ‌های قدیمی طبق Retention Policy پاک شوند.

---

## ZipArchive و Fallback

سیستم باید اگر ZipArchive فعال نبود، Fatal Error ندهد.

قوانین:

- اگر ZipArchive موجود است، بکاپ ZIP ساخته شود.
- اگر ZipArchive موجود نیست، سیستم باید fallback داشته باشد.
- Fallback می‌تواند شامل ایجاد فایل SQL و کپی ساختاریافته فایل‌ها باشد.
- اگر هیچ روش مناسبی ممکن نبود، پیام فارسی واضح نمایش داده شود.
- نبود ZipArchive نباید باعث خراب شدن کل سیستم شود.

---

## Restore

Restore عملیات بسیار حساس است.

قوانین:

- Restore فقط برای super_admin یا Permission بسیار حساس مجاز است.
- قبل از Restore باید بکاپ pre_restore گرفته شود.
- فایل بکاپ باید اعتبارسنجی شود.
- نسخه بکاپ باید بررسی شود.
- سازگاری دیتابیس باید بررسی شود.
- سیستم باید وارد Maintenance Mode شود.
- Restore باید مرحله به مرحله Log شود.
- بعد از Restore باید Cache پاک شود.
- بعد از Restore باید Migrationهای لازم بررسی شوند.

---

## هشدار Restore

قبل از Restore باید هشدار واضح نمایش داده شود:

```text
این عملیات ممکن است داده‌های فعلی سیستم را تغییر دهد یا جایگزین کند.
قبل از ادامه، از اطلاعات فعلی بکاپ گرفته می‌شود.
```

برای تأیید Restore، کاربر باید عبارت زیر را وارد کند:

```text
بازیابی را تایید می‌کنم
```

---

## Update Package

پکیج آپدیت باید ساختار مشخص داشته باشد.

ساختار پیشنهادی:

```text
update-v1.1.0.zip
├── update.json
├── files/
├── migrations/
├── scripts/
├── checksums.json
└── CHANGELOG.md
```

---

## ساختار update.json

فایل `update.json` باید داخل پکیج آپدیت باشد.

نمونه:

```json
{
  "name": "Proma Pay Update",
  "version": "1.1.0",
  "from_version": "1.0.0",
  "min_supported_version": "1.0.0",
  "type": "minor",
  "requires_backup": true,
  "min_php": "7.4",
  "created_at": "2026-01-01 12:00:00",
  "files": [],
  "migrations": [],
  "scripts": [],
  "checksum": "",
  "notes": "Bug fixes and improvements"
}
```

قوانین:

- version الزامی است.
- min_supported_version الزامی است.
- requires_backup باید true باشد، مگر دلیل مشخص وجود داشته باشد.
- min_php باید بررسی شود.
- فایل‌های خطرناک باید رد شوند.
- checksum در صورت وجود باید بررسی شود.

---

## Migrationها

Migrationها تغییرات ساختار دیتابیس هستند.

قوانین:

- Migration باید Idempotent باشد.
- چند بار اجرا شدن Migration نباید سیستم را خراب کند.
- Migration باید در جدول migrations ثبت شود.
- Migration ناموفق باید Log شود.
- Migration نباید بدون بررسی داده را حذف کند.
- Migrationهای خطرناک باید ممنوع یا نیازمند تأیید ویژه باشند.

---

## Migrationهای ممنوع یا خطرناک

موارد زیر ممنوع یا بسیار محدود هستند:

```sql
DROP DATABASE
TRUNCATE
DROP TABLE بدون کنترل
DELETE بدون WHERE
UPDATE بدون WHERE روی جدول حساس
ALTER TABLE مخرب بدون Backup
حذف ستون دارای داده بدون Migration ایمن
```

اگر حذف یا تغییر حساس لازم بود، باید:

- بکاپ اجباری باشد.
- Migration توضیح داشته باشد.
- داده قدیمی حفظ یا منتقل شود.
- Rollback یا Recovery Plan مشخص باشد.

---

## Rollback

Rollback یعنی تلاش برای بازگرداندن سیستم به وضعیت قبل از آپدیت.

قوانین:

- قبل از آپدیت باید بکاپ pre_update گرفته شود.
- اگر آپدیت شکست خورد، سیستم باید بتواند حداقل فایل‌ها یا دیتابیس را از بکاپ بازگرداند.
- اگر Rollback کامل ممکن نیست، باید پیام واضح نمایش داده شود.
- نتیجه Rollback باید Log شود.
- بعد از Rollback، Maintenance Mode باید مدیریت شود.

---

## Maintenance Mode

در عملیات حساس، سیستم باید وارد Maintenance Mode شود.

عملیات‌هایی که نیاز به Maintenance Mode دارند:

- Update Core
- Restore Backup
- Migration سنگین
- Repair دیتابیس
- نصب یا آپدیت پلاگین حساس

در Maintenance Mode:

- کاربران عادی نباید وارد سیستم شوند.
- مشتریان نباید عملیات پرداخت یا آپلود انجام دهند.
- مدیر مجاز باید وضعیت عملیات را ببیند.
- بعد از پایان عملیات، سیستم باید از Maintenance خارج شود.

---

## نسخه سیستم

سیستم باید نسخه فعلی خود را نگهداری کند.

محل‌های پیشنهادی:

- جدول settings
- جدول system_versions
- فایل version.php در صورت نیاز

کلید پیشنهادی:

```text
system.version
```

قوانین:

- هر Update باید نسخه را تغییر دهد.
- نسخه باید در Footer یا صفحه About قابل مشاهده باشد.
- نسخه باید در Release Report ثبت شود.
- پلاگین‌ها باید بتوانند min_proma_version را بررسی کنند.

---

## Workflowها

### ایجاد بکاپ دستی

1. کاربر وارد بخش Backup می‌شود.
2. Permission بررسی می‌شود.
3. نوع بکاپ انتخاب می‌شود.
4. سیستم فضای قابل استفاده را بررسی می‌کند.
5. عملیات Backup شروع می‌شود.
6. دیتابیس Export می‌شود.
7. فایل‌های انتخابی اضافه می‌شوند.
8. فایل بکاپ ساخته می‌شود.
9. Metadata در Files Domain ثبت می‌شود.
10. Backup Log ثبت می‌شود.
11. Notification نتیجه ارسال می‌شود.
12. Toast موفقیت نمایش داده می‌شود.

---

### بکاپ قبل از آپدیت

1. کاربر پکیج آپدیت را آپلود می‌کند.
2. پکیج اعتبارسنجی می‌شود.
3. سیستم requires_backup را بررسی می‌کند.
4. بکاپ pre_update ساخته می‌شود.
5. اگر بکاپ موفق بود، آپدیت ادامه پیدا می‌کند.
6. اگر بکاپ شکست خورد، آپدیت متوقف می‌شود.

---

### Restore بکاپ

1. کاربر فایل بکاپ را انتخاب می‌کند.
2. Permission سطح بالا بررسی می‌شود.
3. هشدار Restore نمایش داده می‌شود.
4. عبارت تأیید دریافت می‌شود.
5. بکاپ pre_restore گرفته می‌شود.
6. سیستم وارد Maintenance Mode می‌شود.
7. فایل بکاپ اعتبارسنجی می‌شود.
8. دیتابیس و فایل‌ها بازیابی می‌شوند.
9. Cache پاک می‌شود.
10. Restore Log ثبت می‌شود.
11. سیستم از Maintenance خارج می‌شود.
12. Notification نتیجه ارسال می‌شود.

---

### اجرای آپدیت

1. کاربر پکیج آپدیت را آپلود می‌کند.
2. فایل در Private Storage ذخیره می‌شود.
3. update.json بررسی می‌شود.
4. نسخه فعلی و نسخه مقصد بررسی می‌شود.
5. سازگاری PHP بررسی می‌شود.
6. Checksum بررسی می‌شود.
7. بکاپ pre_update گرفته می‌شود.
8. سیستم وارد Maintenance Mode می‌شود.
9. فایل‌ها به صورت امن جایگزین می‌شوند.
10. Migrationها اجرا می‌شوند.
11. اسکریپت‌های مجاز اجرا می‌شوند.
12. نسخه سیستم بروزرسانی می‌شود.
13. Cache پاک می‌شود.
14. سیستم از Maintenance خارج می‌شود.
15. Log و Notification ثبت می‌شود.

---

### شکست آپدیت

1. خطا ثبت می‌شود.
2. Update status برابر failed می‌شود.
3. اگر ممکن بود Rollback اجرا می‌شود.
4. اگر Rollback ممکن نبود، سیستم در Maintenance Mode باقی می‌ماند یا پیام راهنما می‌دهد.
5. Notification برای super_admin ارسال می‌شود.
6. Error Log و Update Log ثبت می‌شوند.

---

## Permissionهای پیشنهادی

### Backup

- `backup.view`
- `backup.create`
- `backup.download`
- `backup.delete`
- `backup.restore`
- `backup.view_logs`

### Update

- `update.view`
- `update.upload`
- `update.validate`
- `update.apply`
- `update.rollback`
- `update.view_logs`

### Maintenance

- `maintenance.view`
- `maintenance.enable`
- `maintenance.disable`

---

## دسترسی نقش‌ها

### super_admin

می‌تواند همه عملیات بکاپ، Restore، آپدیت و Maintenance را انجام دهد.

### admin

ممکن است بتواند بکاپ بگیرد، اما Restore و Update باید محدودتر باشد.

### accountant

نباید Restore یا Update انجام دهد.

### operator

نباید به Backup یا Update دسترسی داشته باشد.

### lawyer

نباید به Backup یا Update دسترسی داشته باشد.

### customer

هرگز نباید Backup یا Update را ببیند.

---

## Eventها

Eventهای اصلی این دامنه:

- `BackupStarted`
- `BackupCompleted`
- `BackupFailed`
- `BackupDeleted`
- `RestoreStarted`
- `RestoreCompleted`
- `RestoreFailed`
- `UpdatePackageUploaded`
- `UpdatePackageValidated`
- `UpdateStarted`
- `UpdateCompleted`
- `UpdateFailed`
- `RollbackStarted`
- `RollbackCompleted`
- `RollbackFailed`
- `MaintenanceModeEnabled`
- `MaintenanceModeDisabled`
- `MigrationExecuted`
- `MigrationFailed`

---

## Notificationها

### BackupCompleted

گیرنده:

- کاربر اجراکننده
- super_admin در صورت نیاز

پیام پیشنهادی:

> بکاپ با موفقیت ساخته شد.

---

### BackupFailed

گیرنده:

- super_admin

پیام پیشنهادی:

> ساخت بکاپ با خطا مواجه شد.

---

### UpdateCompleted

گیرنده:

- super_admin
- admin در صورت نیاز

پیام پیشنهادی:

> بروزرسانی سیستم با موفقیت انجام شد.

---

### UpdateFailed

گیرنده:

- super_admin

پیام پیشنهادی:

> بروزرسانی سیستم با خطا مواجه شد.

---

### RestoreCompleted

گیرنده:

- super_admin

پیام پیشنهادی:

> بازیابی بکاپ با موفقیت انجام شد.

---

## Logging و Audit

### Logهای عادی

موارد زیر باید Log داشته باشند:

- شروع بکاپ
- پایان بکاپ
- شکست بکاپ
- شروع آپدیت
- پایان آپدیت
- شکست آپدیت
- اجرای Migration
- ورود و خروج از Maintenance Mode

---

### Audit Log

موارد زیر باید Audit Log داشته باشند:

- دانلود بکاپ
- حذف بکاپ
- Restore بکاپ
- اجرای آپدیت
- Rollback
- غیرفعال کردن Maintenance Mode بعد از خطا
- تغییر تنظیمات Backup
- تغییر تنظیمات Update

---

### Security Log

موارد زیر باید Security Log داشته باشند:

- تلاش دسترسی به بکاپ بدون Permission
- تلاش دانلود بکاپ بدون Permission
- تلاش آپلود پکیج آپدیت نامعتبر
- تلاش اجرای Update بدون Permission
- تلاش Restore بدون Permission
- فایل ZIP مشکوک
- مسیر خطرناک داخل ZIP
- CSRF نامعتبر در Backup یا Update

---

## قوانین UI

صفحات Backup & Update باید شامل موارد زیر باشند:

- لیست بکاپ‌ها
- دکمه ساخت بکاپ
- وضعیت بکاپ
- حجم بکاپ
- تاریخ ایجاد
- ایجادکننده
- دکمه دانلود امن
- دکمه حذف
- دکمه Restore
- لیست آپدیت‌ها
- Upload پکیج آپدیت
- نمایش نتیجه اعتبارسنجی
- نمایش نسخه فعلی
- نمایش نسخه مقصد
- Progress مراحل آپدیت
- نمایش Log عملیات
- Maintenance Mode Banner

---

## قوانین امنیتی

موارد الزامی:

- CSRF برای همه عملیات
- Permission سمت سرور
- Private Storage برای بکاپ و آپدیت
- عدم دسترسی مستقیم به فایل بکاپ
- عدم اجرای فایل آپدیت قبل از اعتبارسنجی
- بررسی مسیرهای داخل ZIP
- جلوگیری از Path Traversal
- Backup اجباری قبل از Update
- Backup اجباری قبل از Restore
- Audit Log برای عملیات حساس
- Security Log برای تلاش غیرمجاز

---

## اطلاعات حساس

موارد زیر بسیار حساس هستند:

- فایل بکاپ
- فایل دیتابیس
- فایل‌های خصوصی داخل بکاپ
- پکیج آپدیت
- update.json
- لاگ‌های Restore
- لاگ‌های آپدیت
- خطاهای Migration
- اطلاعات نسخه و سازگاری در بعضی شرایط

---

## نکات دیتابیس

جدول‌های پیشنهادی:

- `backups`
- `backup_logs`
- `restore_logs`
- `updates`
- `update_logs`
- `migrations`

Indexهای پیشنهادی:

- `backups.backup_type`
- `backups.status`
- `backups.created_at`
- `backups.created_by`
- `updates.update_version`
- `updates.status`
- `updates.started_at`
- `migrations.migration_name`
- `migrations.source_type`
- `migrations.source_id`
- `migrations.status`

---

## قوانین Validation

- فایل بکاپ باید معتبر باشد.
- فایل آپدیت باید ZIP معتبر باشد.
- update.json الزامی است.
- نسخه آپدیت باید معتبر باشد.
- min_supported_version باید با نسخه فعلی سازگار باشد.
- min_php باید با سرور سازگار باشد.
- مسیرهای داخل ZIP نباید خطرناک باشند.
- Migrationها نباید مخرب باشند.
- Restore بدون تأیید متنی ممنوع است.
- Update بدون Backup موفق ممنوع است.

---

## قابلیت پلاگینی

Backup & Update Domain باید آماده توسعه پلاگینی باشد.

Extension Pointهای پیشنهادی:

- `backup_started`
- `backup_completed`
- `backup_failed`
- `restore_started`
- `restore_completed`
- `update_started`
- `update_completed`
- `update_failed`
- `backup_storage_drivers`
- `backup_before_archive`
- `backup_after_archive`
- `update_package_validators`
- `migration_runners`
- `rollback_handlers`

نمونه پلاگین‌های آینده:

- بکاپ روی فضای ابری
- بکاپ روی S3
- بکاپ خودکار زمان‌بندی‌شده پیشرفته
- بکاپ رمزنگاری‌شده
- آپدیت از سرور مرکزی
- License-Based Update
- Remote Update Repository
- Plugin Auto Update
- بکاپ افزایشی
- مانیتورینگ سلامت بکاپ

---

## اثر روی دامنه‌های دیگر

Backup & Update روی تمام دامنه‌ها اثر دارد:

- Customers
- Contracts
- Installments
- Payments
- Financial Calculations
- Legal
- Chat
- Calendar
- Files
- Settings
- Reports
- Plugins

مثال:

قبل از آپدیت Payments:

- باید از دیتابیس بکاپ گرفته شود.
- باید از تنظیمات پرداخت بکاپ گرفته شود.
- Migrationهای پرداخت بررسی شوند.
- اگر آپدیت شکست خورد، Rollback یا راهنمای بازیابی وجود داشته باشد.

---

## چک‌لیست پیاده‌سازی

قبل از پیاده‌سازی قابلیت مربوط به Backup & Update بررسی شود:

- [ ] بکاپ در Private Storage ذخیره می‌شود.
- [ ] بکاپ از Public قابل دسترسی نیست.
- [ ] دانلود بکاپ Permission دارد.
- [ ] دانلود بکاپ Audit Log دارد.
- [ ] Update بدون بکاپ موفق اجرا نمی‌شود.
- [ ] Restore بدون بکاپ pre_restore اجرا نمی‌شود.
- [ ] update.json Validate می‌شود.
- [ ] Migrationها امن و Idempotent هستند.
- [ ] Path Traversal در ZIP کنترل شده است.
- [ ] Maintenance Mode در عملیات حساس فعال می‌شود.
- [ ] شکست عملیات Log دارد.
- [ ] Notification نتیجه ارسال می‌شود.
- [ ] UI مطابق UI Constitution است.

---

## چک‌لیست بازبینی

قبل از Merge تغییرات این دامنه:

- [ ] نبود ZipArchive باعث Fatal Error نمی‌شود.
- [ ] بکاپ واقعی داخل Release ZIP قرار نمی‌گیرد.
- [ ] Restore هشدار و تأیید متنی دارد.
- [ ] Update Package قبل از اجرا اعتبارسنجی می‌شود.
- [ ] Migration خطرناک رد یا محدود می‌شود.
- [ ] فایل‌های بکاپ قابل دانلود مستقیم نیستند.
- [ ] فایل‌های آپدیت مستقیم اجرا نمی‌شوند.
- [ ] Maintenance Mode بعد از موفقیت خاموش می‌شود.
- [ ] در شکست Update وضعیت سیستم مشخص است.
- [ ] Log مراحل قابل مشاهده است.
- [ ] Responsive بودن صفحه بررسی شده است.

---

## Definition of Done

این دامنه زمانی کامل است که:

- بکاپ دستی قابل ایجاد باشد.
- بکاپ قبل از آپدیت اجباری باشد.
- Restore با هشدار، Permission و Audit انجام شود.
- Update Package اعتبارسنجی شود.
- Migrationها امن اجرا شوند.
- Maintenance Mode در عملیات حساس فعال شود.
- فایل‌های بکاپ و آپدیت در Private Storage باشند.
- خطاها Log و Notification داشته باشند.
- نبود ZipArchive سیستم را خراب نکند.
- Rollback یا راهنمای بازیابی وجود داشته باشد.
- سیستم آماده آپدیت‌های آینده و تجاری باشد.

---

## قابلیت‌های آینده

در نسخه‌های آینده این دامنه باید آماده موارد زیر باشد:

- Scheduled Backups
- Cloud Backups
- Encrypted Backups
- Incremental Backups
- Backup Health Check
- Remote Update Server
- One-Click Update
- Plugin Auto Update
- License-Based Updates
- Update Signature Verification
- Backup Diff Viewer
- Restore Preview
- Safe Mode Recovery
- Disaster Recovery Assistant
- CI/CD Release Integration

---

## پایان فایل