# 03 — Implementation Order

ترتیب اجرایی پیاده‌سازی پروژه **Proma Pay / پروما** برای Codex

---

## هدف این فایل

این فایل مشخص می‌کند Codex باید پروژه را با چه ترتیبی پیاده‌سازی کند.

هدف این است که Codex:

- از بخش‌های پایه شروع کند.
- زود وارد Featureهای مالی و حقوقی نشود.
- قبل از پرداخت، قرارداد، اقساط و پرونده حقوقی، زیرساخت امنیتی را بسازد.
- قبل از فایل، گزارش، پلاگین و بکاپ، Permission و Scope را آماده کند.
- هر مرحله را کوچک، قابل تست و قابل برگشت جلو ببرد.
- از خواندن بی‌هدف همه مستندات جلوگیری کند.

---

## اصل اصلی اجرای پروژه

اصل اصلی:

> اول زیرساخت امن، بعد Featureهای مالی، بعد Featureهای حساس، بعد امکانات جانبی.

Codex نباید از همان ابتدا وارد ساخت کل سیستم شود.

روش درست:

```text
یک مرحله را انتخاب کن.
فقط مستندات همان مرحله را بخوان.
همان مرحله را کامل و قابل تست پیاده‌سازی کن.
بعد برو مرحله بعدی.
```

روش اشتباه:

```text
همه مستندات را بخوان.
همه جدول‌ها را یک‌جا بساز.
همه Featureها را همزمان شروع کن.
بعد تلاش کن خطاها را اصلاح کنی.
```

---

## ترتیب کلی پیاده‌سازی

ترتیب پیشنهادی و استاندارد پروژه:

```text
00. Project Inspection
01. Core Bootstrap
02. Database Foundation
03. Security Foundation
04. Users / Roles / Permissions
05. Customers
06. Contracts
07. Installments
08. Payments
09. Financial Logs
10. Files
11. Notifications / Events / Jobs
12. Calendar / Reminders / Tasks
13. Legal
14. Reports / Exports
15. Backup & Update
16. Plugins
17. PWA / Electron Support
18. Final Hardening
```

---

## قانون مهم ترتیب

Codex نباید این ترتیب‌ها را بشکند:

```text
Payments قبل از Installments ساخته نشود.
Installments قبل از Contracts ساخته نشود.
Contracts قبل از Customers ساخته نشود.
Features حساس قبل از Users/Roles/Permissions ساخته نشوند.
Files قبل از Private Storage و Permission ساخته نشود.
Reports قبل از Scope و Export Security ساخته نشود.
Plugins قبل از Files, Settings, Backup و Security ساخته نشود.
Backup/Restore قبل از Files و Audit/Security ساخته نشود.
```

---

# 00 — Project Inspection

## هدف

قبل از هر کدنویسی، Codex باید پروژه فعلی را بررسی کند.

## کارهایی که باید انجام شود

```text
ساختار Root پروژه را بررسی کن.
فولدرهای موجود را شناسایی کن.
public/index.php را پیدا کن، اگر وجود دارد.
configها را بررسی کن.
routes را بررسی کن.
ساختار app را بررسی کن.
فولدر database/migrations را بررسی کن.
فولدر storage را بررسی کن.
assets و fonts را بررسی کن.
docs/codex را بخوان.
```

## کارهایی که نباید انجام شود

```text
هیچ فایل جدیدی نساز.
هیچ ساختاری را تغییر نده.
هیچ Package جدیدی نصب نکن.
هیچ Migration اجرا نکن.
هیچ Feature را شروع نکن.
```

## خروجی مورد انتظار

Codex باید برای خودش مشخص کند:

```text
Current structure:
Detected entrypoint:
Detected routing:
Detected database layer:
Detected auth:
Detected storage:
Missing foundations:
Next safe step:
```

---

# 01 — Core Bootstrap

## هدف

ساخت هسته اولیه پروژه.

این مرحله پایه اجرای کل سیستم است.

## مستندات مرتبط

```text
docs/codex/00_CODEX_START_HERE.md
docs/codex/01_PROJECT_MAP.md
docs/codex/02_NON_NEGOTIABLE_RULES.md
docs/database/02_CORE_TABLES.md
```

## موارد قابل پیاده‌سازی

```text
public/index.php
bootstrap/app.php
config/app.php
config/database.php
Router ساده
Base Controller
Base Service
Base Repository
View Renderer
Response Helper
Request Helper
Env Loader ساده
Error Handler پایه
```

## قوانین

- پروژه نباید به Framework سنگین وابسته شود.
- Bootstrap باید ساده و قابل فهم باشد.
- خطاهای فنی نباید به کاربر نهایی نمایش داده شوند.
- پروژه باید با PHP 7.4+ سازگار باشد.
- پروژه باید Shared Hosting compatible بماند.

## Acceptance

```text
صفحه اصلی بدون خطای PHP باز شود.
Router ساده کار کند.
Config قابل خواندن باشد.
Error Handler فعال باشد.
ساختار پروژه قابل توسعه باشد.
```

---

# 02 — Database Foundation

## هدف

ساخت زیرساخت دیتابیس و Migrationها.

## مستندات مرتبط

```text
docs/database/00_DATABASE_OVERVIEW.md
docs/database/01_NAMING_CONVENTIONS.md
docs/database/02_CORE_TABLES.md
docs/database/19_INDEXES_AND_PERFORMANCE.md
docs/database/20_MIGRATION_RULES.md
```

## موارد قابل پیاده‌سازی

```text
PDO Connection
Database Config
Migration Runner
migrations table
Seed Runner
Database Helperهای محدود
Core Tables
System Settings پایه
Numbering Service پایه
```

## قوانین

- همه Queryها باید PDO Prepared Statement باشند.
- Migration باید idempotent باشد.
- قبل از ساخت جدول، وجود جدول بررسی شود.
- قبل از افزودن Index، وجود Index بررسی شود.
- Migration مخرب ممنوع است.
- Charset باید utf8mb4 باشد.
- Collation باید utf8mb4_unicode_ci باشد.

## Acceptance

```text
اتصال دیتابیس کار کند.
جدول migrations ساخته شود.
Migration Runner بتواند migration اجراشده را ثبت کند.
Migration تکراری دوباره اجرا نشود.
Core Tables بدون خطا ساخته شوند.
```

---

# 03 — Security Foundation

## هدف

ساخت پایه‌های امنیتی قبل از هر Feature حساس.

## مستندات مرتبط

```text
docs/codex/02_NON_NEGOTIABLE_RULES.md
docs/database/04_USERS_ROLES_PERMISSIONS_TABLES.md
docs/database/18_LOGS_AUDIT_SECURITY_TABLES.md
```

## موارد قابل پیاده‌سازی

```text
Session Manager
Authentication Middleware
CSRF Token Service
Permission Checker
Scope Checker پایه
Password Hashing
Login Logs
Security Logs پایه
Audit Logs پایه
Validation پایه
```

## قوانین

- هیچ عملیات state-changing بدون CSRF نباشد.
- هیچ صفحه داخلی بدون Auth نباشد.
- هیچ عملیات حساس بدون Permission نباشد.
- داده‌های مشتری، قرارداد، پرداخت و پرونده باید Scope داشته باشند.
- Password خام ذخیره نشود.
- Session ID خام در Log ذخیره نشود.

## Acceptance

```text
Login پایه کار کند.
Logout کار کند.
CSRF برای فرم تستی کار کند.
Permission denied درست مدیریت شود.
Security Log برای تلاش غیرمجاز ثبت شود.
Audit Log پایه قابل ثبت باشد.
```

---

# 04 — Users / Roles / Permissions

## هدف

ساخت کامل مدیریت کاربران، نقش‌ها، Permissionها و Scope.

## مستندات مرتبط

```text
docs/database/04_USERS_ROLES_PERMISSIONS_TABLES.md
docs/database/18_LOGS_AUDIT_SECURITY_TABLES.md
docs/codex/02_NON_NEGOTIABLE_RULES.md
```

## موارد قابل پیاده‌سازی

```text
Users CRUD داخلی
Roles
Permissions
Role Permissions
User Roles
Permission Assignment
Scope Rules
User Status
Password Change
Login Security
```

## قوانین

- تغییر Role و Permission باید Audit Log داشته باشد.
- تلاش تغییر Role بدون Permission باید Security Log داشته باشد.
- کاربر غیرفعال نباید وارد شود.
- Password باید Hash شود.
- Permissionها باید قابل Seed و idempotent باشند.

## Acceptance

```text
Admin بتواند کاربر بسازد.
Admin بتواند نقش بسازد.
Admin بتواند Permission اختصاص دهد.
Permission Check در Route حساس کار کند.
Scope پایه قابل اعمال باشد.
```

---

# 05 — Customers

## هدف

ساخت مدیریت مشتریان.

## مستندات مرتبط

```text
docs/workflows/01_CUSTOMER_ONBOARDING.md
docs/database/03_CUSTOMERS_TABLES.md
docs/database/04_USERS_ROLES_PERMISSIONS_TABLES.md
docs/database/18_LOGS_AUDIT_SECURITY_TABLES.md
```

## موارد قابل پیاده‌سازی

```text
Customer Create
Customer Edit
Customer List
Customer Detail
Customer Documents Metadata
Customer Status
Customer Timeline پایه
Customer Search
```

## قوانین

- اطلاعات هویتی حساس است.
- کد ملی و موبایل باید Validation داشته باشند.
- عملیات مشتری باید Permission داشته باشد.
- مشاهده مشتری باید Scope داشته باشد.
- حذف مشتری باید Soft Delete باشد.
- تغییر اطلاعات حساس باید Audit Log داشته باشد.

## Acceptance

```text
ثبت مشتری با Validation انجام شود.
لیست مشتریان Pagination داشته باشد.
جستجوی مشتری با Query امن انجام شود.
دسترسی بدون Permission رد شود.
تغییر اطلاعات حساس Audit شود.
```

---

# 06 — Contracts

## هدف

ساخت قراردادهای فروش اقساطی.

## مستندات مرتبط

```text
docs/workflows/02_CONTRACT_CREATION.md
docs/database/05_CONTRACTS_TABLES.md
docs/database/06_INSTALLMENTS_TABLES.md
docs/database/08_FINANCIAL_TABLES.md
docs/database/18_LOGS_AUDIT_SECURITY_TABLES.md
```

## موارد قابل پیاده‌سازی

```text
Contract Create
Contract Draft
Contract Submit
Contract Detail
Contract Status
Contract Numbering
Contract Items
Contract Summary
Contract Audit
```

## قوانین

- ساخت قرارداد باید Transaction داشته باشد.
- قرارداد بدون مشتری معتبر ساخته نشود.
- مبلغ‌ها باید DECIMAL باشند.
- محاسبات قرارداد سمت سرور انجام شود.
- تغییر مبلغ قرارداد رسمی باید Audit و Financial Log داشته باشد.
- قرارداد حذف فیزیکی نشود.

## Acceptance

```text
قرارداد با شماره رسمی ساخته شود.
قرارداد به مشتری وصل شود.
محاسبات مبلغ سمت سرور انجام شود.
ساخت قرارداد داخل Transaction باشد.
خطا باعث Rollback شود.
Audit Log ثبت شود.
```

---

# 07 — Installments

## هدف

ساخت اقساط قرارداد و مدیریت سررسیدها.

## مستندات مرتبط

```text
docs/database/06_INSTALLMENTS_TABLES.md
docs/workflows/02_CONTRACT_CREATION.md
docs/workflows/05_OVERDUE_FOLLOWUP.md
docs/database/18_LOGS_AUDIT_SECURITY_TABLES.md
```

## موارد قابل پیاده‌سازی

```text
Installment Schedule Generation
Installment List
Installment Detail
Due Date Management
Overdue Detection
Installment Status History
Payment Promise پایه
Follow-up پایه
```

## قوانین

- قسط باید به قرارداد معتبر وصل باشد.
- paid_amount فقط با Payment approved تغییر کند.
- تغییر مبلغ قسط رسمی حساس است.
- تشخیص معوقه باید سمت سرور باشد.
- تغییر وضعیت قسط باید History داشته باشد.

## Acceptance

```text
اقساط از قرارداد ساخته شوند.
جمع اقساط با قرارداد سازگار باشد.
اقساط سررسیدشده قابل تشخیص باشند.
قسط بدون پرداخت approved تسویه نشود.
Status History ثبت شود.
```

---

# 08 — Payments

## هدف

ساخت پرداخت‌ها، رسیدها، کارت‌به‌کارت و تأیید پرداخت.

## مستندات مرتبط

```text
docs/workflows/03_INSTALLMENT_PAYMENT.md
docs/workflows/04_CARD_TO_CARD_REVIEW.md
docs/database/07_PAYMENTS_TABLES.md
docs/database/06_INSTALLMENTS_TABLES.md
docs/database/08_FINANCIAL_TABLES.md
docs/database/13_FILES_TABLES.md
docs/database/18_LOGS_AUDIT_SECURITY_TABLES.md
```

## موارد قابل پیاده‌سازی

```text
Payment Create
Payment Receipt Upload
Card-to-Card Review
Payment Approval
Payment Rejection
Payment Allocation
Payment Status History
Gateway Transaction پایه
Refund پایه
```

## قوانین

- پرداخت pending_review نباید روی بدهی اثر بگذارد.
- فقط approved روی بدهی اثر می‌گذارد.
- Approval باید Transaction داشته باشد.
- Payment approved باید Financial Log داشته باشد.
- Receipt باید Private File باشد.
- Rejection باید reason داشته باشد.
- Callback درگاه به‌تنهایی کافی نیست.

## Acceptance

```text
رسید پرداخت قابل ثبت باشد.
رسید بدون بررسی approved نشود.
Approval بدهی را درست کاهش دهد.
Rejected بدهی را تغییر ندهد.
Financial Log ثبت شود.
Audit Log ثبت شود.
خطا باعث Rollback شود.
```

---

# 09 — Financial Logs

## هدف

تکمیل ردیابی مالی، Ledger، Settlement و Adjustment.

## مستندات مرتبط

```text
docs/database/08_FINANCIAL_TABLES.md
docs/database/07_PAYMENTS_TABLES.md
docs/database/18_LOGS_AUDIT_SECURITY_TABLES.md
```

## موارد قابل پیاده‌سازی

```text
Financial Log Service
Ledger Entries
Manual Adjustment
Settlement Records
Financial Snapshots
Calculation Logs
```

## قوانین

- تغییر مالی بدون Financial Log ممنوع است.
- Adjustment دستی باید reason و Permission داشته باشد.
- Settlement باید Transaction داشته باشد.
- Financial Logs نباید حذف شوند.
- مبلغ‌ها فقط DECIMAL باشند.

## Acceptance

```text
هر Payment approved لاگ مالی بسازد.
Adjustment بدون reason رد شود.
Settlement لاگ مالی بسازد.
Financial Log قابل مشاهده برای نقش مجاز باشد.
```

---

# 10 — Files

## هدف

ساخت مدیریت فایل‌ها و Private Storage.

## مستندات مرتبط

```text
docs/database/13_FILES_TABLES.md
docs/database/18_LOGS_AUDIT_SECURITY_TABLES.md
docs/codex/02_NON_NEGOTIABLE_RULES.md
```

## موارد قابل پیاده‌سازی

```text
Private Storage
File Upload
File Metadata
File Links
Secure Download Controller
Download Tokens
File Access Logs
File Validation
File Security Scan پایه
```

## قوانین

- فایل حساس در public ذخیره نشود.
- مسیر واقعی فایل نمایش داده نشود.
- فایل واقعی داخل DB ذخیره نشود.
- دانلود فایل حساس Permission و Scope داشته باشد.
- Download Token خام ذخیره نشود.
- فایل blocked دانلود نشود.

## Acceptance

```text
فایل در Private Storage ذخیره شود.
Metadata در DB ثبت شود.
دانلود بدون Permission رد شود.
دانلود مجاز از Controller امن انجام شود.
File Access Log ثبت شود.
```

---

# 11 — Notifications / Events / Jobs

## هدف

ساخت Event System، Notification و Jobهای پایه.

## مستندات مرتبط

```text
docs/database/11_NOTIFICATIONS_TABLES.md
docs/database/18_LOGS_AUDIT_SECURITY_TABLES.md
docs/workflows/08_CALENDAR_AND_REMINDERS.md
docs/workflows/09_CHAT_AND_BOT_MESSAGES.md
```

## موارد قابل پیاده‌سازی

```text
Event Dispatcher
Notification Service
Notification Templates
Internal Notifications
Delivery Logs
Job Runner ساده
Cron-compatible Jobs
Retry پایه
```

## قوانین

- Notification منبع حقیقت نیست.
- Notification نباید عملیات مالی انجام دهد.
- ارسال خارجی باید Delivery Log داشته باشد.
- Secretهای Provider خام ذخیره نشوند.
- Jobها نباید Transaction طولانی ایجاد کنند.

## Acceptance

```text
Event ساده Dispatch شود.
Notification داخلی ساخته شود.
Delivery Log ثبت شود.
Job ساده قابل اجرا باشد.
Retry خطای ارسال قابل کنترل باشد.
```

---

# 12 — Calendar / Reminders / Tasks

## هدف

ساخت تقویم، یادآوری‌ها، Deadlineها و Taskها.

## مستندات مرتبط

```text
docs/workflows/08_CALENDAR_AND_REMINDERS.md
docs/database/12_CALENDAR_TABLES.md
docs/database/11_NOTIFICATIONS_TABLES.md
docs/database/18_LOGS_AUDIT_SECURITY_TABLES.md
```

## موارد قابل پیاده‌سازی

```text
Calendar Events
Reminders
Tasks
Task Comments
Recurring Rules پایه
Deadline Tracking
Reminder Notification
```

## قوانین

- Calendar منبع حقیقت مالی نیست.
- Reminder نباید پرداخت را approved کند.
- Task تکمیل‌شده نباید بدون Service رسمی داده مالی را تغییر دهد.
- رویداد حقوقی باید Permission داشته باشد.
- Reminder خارجی باید Notification Log داشته باشد.

## Acceptance

```text
رویداد تقویم ساخته شود.
Reminder در زمان مناسب قابل ارسال باشد.
Task به کاربر اختصاص داده شود.
Task overdue قابل تشخیص باشد.
رویداد حساس بدون Permission نمایش داده نشود.
```

---

# 13 — Legal

## هدف

ساخت پرونده حقوقی و فرایند ارجاع.

## مستندات مرتبط

```text
docs/workflows/06_LEGAL_REFERRAL.md
docs/database/09_LEGAL_TABLES.md
docs/database/08_FINANCIAL_TABLES.md
docs/database/13_FILES_TABLES.md
docs/database/18_LOGS_AUDIT_SECURITY_TABLES.md
```

## موارد قابل پیاده‌سازی

```text
Legal Case Create
Legal Referral
Legal Financial Snapshot
Legal Claims
Legal Actions
Legal Documents
Legal Deadlines
Legal Status History
```

## قوانین

- پرونده حقوقی باید از Snapshot معتبر ساخته شود.
- مدارک حقوقی Private هستند.
- تغییر وضعیت پرونده History می‌خواهد.
- claim_amount باید قابل ردیابی باشد.
- Legal Referral باید Audit Log داشته باشد.

## Acceptance

```text
پرونده از قرارداد معوق ساخته شود.
Snapshot مالی ثبت شود.
مدارک حقوقی Private باشند.
Status History ثبت شود.
دسترسی بدون Permission رد شود.
```

---

# 14 — Reports / Exports

## هدف

ساخت گزارش‌ها و خروجی‌های امن.

## مستندات مرتبط

```text
docs/workflows/12_REPORT_EXPORT.md
docs/database/15_REPORTS_TABLES.md
docs/database/13_FILES_TABLES.md
docs/database/18_LOGS_AUDIT_SECURITY_TABLES.md
docs/database/19_INDEXES_AND_PERFORMANCE.md
```

## موارد قابل پیاده‌سازی

```text
Reports List
Report Filters
Report Execution
Report Export
Export Files
Report Access Logs
Dashboard Widgets پایه
```

## قوانین

- گزارش حساس Permission و Scope دارد.
- Export حساس Private است.
- Export باید Expiration داشته باشد.
- Export بزرگ باید Job شود.
- Export مالی/حقوقی Audit Log دارد.

## Acceptance

```text
گزارش با Scope درست اجرا شود.
Export فایل Private بسازد.
دانلود Export بدون Permission رد شود.
Access Log و Audit Log ثبت شود.
گزارش بزرگ بدون LIMIT اجرا نشود.
```

---

# 15 — Backup & Update

## هدف

ساخت بکاپ، Restore، Update و Maintenance.

## مستندات مرتبط

```text
docs/workflows/10_BACKUP_AND_UPDATE.md
docs/database/16_BACKUP_UPDATE_TABLES.md
docs/database/13_FILES_TABLES.md
docs/database/18_LOGS_AUDIT_SECURITY_TABLES.md
docs/database/20_MIGRATION_RULES.md
```

## موارد قابل پیاده‌سازی

```text
Manual Backup
Backup Files
Backup Schedules
Restore Logs
Update Packages
Update Installation Logs
Maintenance Mode
System Versions
```

## قوانین

- Backup فایل حساس است و Private می‌ماند.
- Restore بدون Permission ممنوع است.
- Restore واقعی باید reason و pre_restore_backup داشته باشد.
- Update حساس بدون pre_update_backup ممنوع است.
- Maintenance نباید اطلاعات حساس نمایش دهد.

## Acceptance

```text
Backup ساخته و در Private Storage ثبت شود.
دانلود Backup Permission ویژه بخواهد.
Restore بدون Permission رد شود.
Update Log مرحله‌ای داشته باشد.
Maintenance Mode قابل فعال و غیرفعال باشد.
```

---

# 16 — Plugins

## هدف

ساخت زیرساخت پلاگین‌ها.

## مستندات مرتبط

```text
docs/workflows/11_PLUGIN_INSTALLATION.md
docs/database/17_PLUGINS_TABLES.md
docs/database/13_FILES_TABLES.md
docs/database/14_SETTINGS_TABLES.md
docs/database/16_BACKUP_UPDATE_TABLES.md
docs/database/18_LOGS_AUDIT_SECURITY_TABLES.md
docs/database/20_MIGRATION_RULES.md
```

## موارد قابل پیاده‌سازی

```text
Plugin Registry
Plugin Package Upload
Plugin Security Scan
Plugin Installation
Plugin Enable / Disable
Plugin Settings
Plugin Permissions
Plugin Routes
Plugin Hooks
Plugin Migrations
```

## قوانین

- پلاگین بدون Security Scan نصب نشود.
- ZIP دارای `../` یا `.env` باید Block شود.
- Route پلاگین Auth، CSRF و Permission دارد.
- Secret پلاگین در Secure Settings است.
- Migration پلاگین ثبت می‌شود.
- پلاگین نباید Core را دور بزند.

## Acceptance

```text
بسته پلاگین Upload شود.
Security Scan اجرا شود.
بسته خطرناک Block شود.
پلاگین نصب‌شده Route و Hook کنترل‌شده داشته باشد.
فعال‌سازی و غیرفعال‌سازی Audit شود.
```

---

# 17 — PWA / Electron Support

## هدف

آماده‌سازی پشتیبانی PWA و Electron بدون پیچیده کردن Core.

## مستندات مرتبط

```text
docs/specifications/
docs/codex/02_NON_NEGOTIABLE_RULES.md
```

## موارد قابل پیاده‌سازی

```text
manifest.json
service-worker.js پایه
offline fallback محدود
Electron config پایه
Desktop build notes
Asset caching محدود
```

## قوانین

- PWA نباید داده حساس را ناامن Cache کند.
- فایل‌های Private نباید Cache عمومی شوند.
- Token و Secret نباید در LocalStorage ذخیره شوند.
- Electron نباید Auth و Permission را دور بزند.

## Acceptance

```text
Manifest معتبر باشد.
Service Worker فایل حساس Cache نکند.
Electron فقط Wrapper امن باشد.
نسخه Desktop با Backend همان Permissionها را رعایت کند.
```

---

# 18 — Final Hardening

## هدف

بازبینی نهایی امنیت، Performance، UI و تست‌های حداقلی.

## مستندات مرتبط

```text
docs/codex/02_NON_NEGOTIABLE_RULES.md
docs/database/18_LOGS_AUDIT_SECURITY_TABLES.md
docs/database/19_INDEXES_AND_PERFORMANCE.md
docs/database/20_MIGRATION_RULES.md
```

## موارد قابل انجام

```text
Security Review
Permission Review
Scope Review
CSRF Review
Financial Flow Review
File Storage Review
Migration Review
Performance Review
Error Handling Review
RTL UI Review
Shared Hosting Review
```

## Acceptance

```text
هیچ Route حساس بدون Auth نباشد.
هیچ فرم حساس بدون CSRF نباشد.
هیچ Query خام خطرناک وجود نداشته باشد.
هیچ فایل حساس Public نباشد.
هیچ Secret خام در Log یا Response نباشد.
پرداخت pending اثر مالی نداشته باشد.
Featureهای مالی Transaction داشته باشند.
Audit/Security/Financial Logs درست ثبت شوند.
```

---

## الگوی اجرای هر مرحله

برای هر مرحله، Codex باید این الگو را رعایت کند:

```text
1. هدف مرحله را بخوان.
2. مستندات مرتبط همان مرحله را بخوان.
3. فایل‌های موجود پروژه را بررسی کن.
4. تغییرات لازم را کوچک و مرحله‌ای انجام بده.
5. Migration لازم را بساز.
6. Permission و CSRF و Scope را اضافه کن.
7. Logهای لازم را اضافه کن.
8. تست حداقلی انجام بده.
9. خلاصه تغییرات را گزارش کن.
10. مرحله بعد را بدون درخواست شروع نکن.
```

---

## قوانین توقف

Codex باید در این شرایط متوقف شود و حدس نزند:

```text
ابهام در اثر مالی
ابهام در وضعیت پرداخت
ابهام در محاسبه بدهی
ابهام در پرونده حقوقی
ابهام در Permission
ابهام در Scope
ابهام در Migration مخرب
ابهام در Public یا Private بودن فایل
ابهام در Secret یا Token
ابهام در نصب پلاگین
```

---

## Definition of Done

ترتیب پیاده‌سازی زمانی درست رعایت شده که:

```text
Core قبل از Featureها ساخته شده باشد.
Database Foundation قبل از Migrationهای Domain ساخته شده باشد.
Security Foundation قبل از Featureهای حساس ساخته شده باشد.
Users/Roles/Permissions قبل از Customers و Contracts ساخته شده باشد.
Customers قبل از Contracts ساخته شده باشد.
Contracts قبل از Installments ساخته شده باشد.
Installments قبل از Payments ساخته شده باشد.
Payments قبل از Financial Completion ساخته شده باشد.
Files قبل از Legal Documents و Report Exports آماده شده باشد.
Reports بعد از Permission و Scope ساخته شده باشد.
Backup/Update بعد از Files و Logs ساخته شده باشد.
Plugins بعد از Security، Files، Settings و Backup ساخته شده باشد.
هر مرحله قابل تست و قابل بررسی باشد.
```

---

## پایان فایل

Codex باید این فایل را به‌عنوان مسیر اجرایی پروژه در نظر بگیرد.

قانون نهایی:

> مرحله‌ای بساز، مرحله‌ای تست کن، و هیچ Feature حساس را قبل از زیرساخت امنیتی و دیتابیسی لازم شروع نکن.
````
