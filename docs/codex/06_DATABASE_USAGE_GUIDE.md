# 06 — Database Usage Guide

راهنمای استفاده صحیح از مستندات دیتابیس پروژه **Proma Pay / پروما** برای Codex

---

## هدف این فایل

این فایل به Codex می‌گوید هنگام کار با دیتابیس پروژه، دقیقاً چطور از مستندات `docs/database/` استفاده کند.

هدف این است که Codex:

- همه فایل‌های دیتابیس را بی‌هدف نخواند.
- فقط فایل‌های مرتبط با Task فعلی را بررسی کند.
- جدول‌ها، ستون‌ها، Indexها و Migrationها را طبق استاندارد بسازد.
- در عملیات مالی، حقوقی، پرداخت و فایل‌ها خطای خطرناک نکند.
- Migration مخرب یا ناسازگار نسازد.
- Queryهای ناامن یا کند تولید نکند.

---

## قانون اصلی

Codex نباید برای هر Task کل فولدر `docs/database/` را بخواند.

روش درست:

```text
1. Domain مربوط به Task را تشخیص بده.
2. فایل عمومی دیتابیس را در صورت نیاز بخوان.
3. فایل دیتابیس همان Domain را بخوان.
4. اگر Migration لازم است، فایل Migration Rules را بخوان.
5. اگر Query یا گزارش سنگین است، فایل Indexes & Performance را بخوان.
6. فقط بعد از آن کدنویسی کن.
```

---

## فایل‌های عمومی دیتابیس

این فایل‌ها مرجع عمومی هستند:

```text
docs/database/00_DATABASE_OVERVIEW.md
docs/database/01_NAMING_CONVENTIONS.md
docs/database/19_INDEXES_AND_PERFORMANCE.md
docs/database/20_MIGRATION_RULES.md
```

کاربرد هرکدام:

| فایل | کاربرد |
|---|---|
| `00_DATABASE_OVERVIEW.md` | شناخت کلی دیتابیس |
| `01_NAMING_CONVENTIONS.md` | قوانین نام‌گذاری جدول‌ها و ستون‌ها |
| `19_INDEXES_AND_PERFORMANCE.md` | Index، Performance، Pagination و Query Optimization |
| `20_MIGRATION_RULES.md` | قوانین Migration، Backup، Rollback و Seed |

---

## فایل‌های دیتابیس بر اساس Domain

| Domain | فایل مرتبط |
|---|---|
| Core | `02_CORE_TABLES.md` |
| Customers | `03_CUSTOMERS_TABLES.md` |
| Users / Roles / Permissions | `04_USERS_ROLES_PERMISSIONS_TABLES.md` |
| Contracts | `05_CONTRACTS_TABLES.md` |
| Installments | `06_INSTALLMENTS_TABLES.md` |
| Payments | `07_PAYMENTS_TABLES.md` |
| Financial | `08_FINANCIAL_TABLES.md` |
| Legal | `09_LEGAL_TABLES.md` |
| Chat | `10_CHAT_TABLES.md` |
| Notifications | `11_NOTIFICATIONS_TABLES.md` |
| Calendar | `12_CALENDAR_TABLES.md` |
| Files | `13_FILES_TABLES.md` |
| Settings | `14_SETTINGS_TABLES.md` |
| Reports | `15_REPORTS_TABLES.md` |
| Backup & Update | `16_BACKUP_UPDATE_TABLES.md` |
| Plugins | `17_PLUGINS_TABLES.md` |
| Logs / Audit / Security | `18_LOGS_AUDIT_SECURITY_TABLES.md` |

---

## استانداردهای قطعی دیتابیس

Codex باید همیشه این استانداردها را رعایت کند:

```text
Database: MySQL / MariaDB
Charset: utf8mb4
Collation: utf8mb4_unicode_ci
Primary Key: id BIGINT UNSIGNED AUTO_INCREMENT
Money: DECIMAL(15,2)
Table names: plural snake_case
Column names: snake_case
Foreign keys: *_id
Datetime columns: *_at
Date columns: *_date
Boolean columns: is_ / has_ / can_ / should_ / requires_
```

---

## قوانین مبلغ‌ها

مبالغ مالی باید همیشه با `DECIMAL(15,2)` ذخیره شوند.

مجاز:

```sql
amount DECIMAL(15,2) NOT NULL DEFAULT 0.00
```

ممنوع:

```sql
amount FLOAT
amount DOUBLE
```

قانون:

```text
هیچ مبلغ مالی نباید با FLOAT یا DOUBLE ذخیره یا محاسبه شود.
```

---

## قوانین شماره‌های رسمی

شماره‌های رسمی باید Unique باشند.

نمونه‌ها:

```text
customer_number
contract_number
installment_number
payment_number
legal_case_number
file_number
report_number
backup_number
```

قوانین:

- شماره رسمی نباید Primary Key باشد.
- شماره رسمی باید Unique Index داشته باشد.
- شماره رسمی باید توسط Service مرکزی تولید شود.
- شماره رسمی نباید با ورودی خام کاربر ساخته شود.

---

## قوانین Foreign Key

هر ارتباط مهم باید ستون `_id` داشته باشد.

نمونه:

```text
customer_id
contract_id
installment_id
payment_id
legal_case_id
file_id
user_id
```

قوانین:

- Foreign Keyهای پرتکرار باید Index داشته باشند.
- اگر Constraint فیزیکی استفاده نشود، Index همچنان الزامی است.
- Queryهای Scope معمولاً به همین ستون‌ها وابسته‌اند.
- داده حساس بدون Scope Check نباید خوانده شود.

---

## قوانین Soft Delete

برای داده‌های اصلی، حذف فیزیکی پیش‌فرض ممنوع است.

جدول‌هایی که معمولاً Soft Delete دارند:

```text
customers
contracts
installments
payments
files
legal_cases
reports
settings
plugins
```

ستون‌های استاندارد:

```text
deleted_at
deleted_by
delete_reason
```

قوانین:

- Queryهای عادی باید `deleted_at IS NULL` داشته باشند.
- جدول Soft Delete باید Index روی `deleted_at` داشته باشد.
- حذف فیزیکی داده مالی، حقوقی و لاگ‌ها ممنوع است.

---

## قوانین Query

همه Queryها باید با PDO Prepared Statements اجرا شوند.

مجاز:

```php
$stmt = $pdo->prepare(
    'SELECT id, full_name FROM customers WHERE id = :id AND deleted_at IS NULL'
);

$stmt->execute([
    'id' => $customerId,
]);
```

ممنوع:

```php
$sql = "SELECT * FROM customers WHERE id = " . $_GET['id'];
```

قوانین:

```text
SQL خام با ورودی کاربر ممنوع است.
Sort column باید whitelist شود.
Filterها باید whitelist شوند.
Queryهای لیستی باید LIMIT داشته باشند.
جدول‌های حجیم باید Pagination داشته باشند.
SELECT * در لیست‌های بزرگ ممنوع است.
```

---

## قوانین Migration

هر تغییر ساختار دیتابیس باید از Migration عبور کند.

قوانین:

```text
Migration باید idempotent باشد.
Migration باید در جدول migrations ثبت شود.
قبل از ساخت جدول، وجود جدول بررسی شود.
قبل از افزودن ستون، وجود ستون بررسی شود.
قبل از افزودن Index، وجود Index بررسی شود.
Migration مخرب بدون Backup ممنوع است.
Seedها باید idempotent باشند.
Secret خام نباید Seed شود.
```

مرجع اصلی:

```text
docs/database/20_MIGRATION_RULES.md
```

---

## قوانین Index

Codex نباید بی‌دلیل Index بسازد.

قانون:

```text
هر Index باید برای یک Query واقعی ساخته شود.
```

ستون‌هایی که معمولاً Index می‌خواهند:

```text
customer_id
contract_id
installment_id
payment_id
legal_case_id
user_id
status
created_at
due_date
paid_at
deleted_at
```

قوانین:

- Foreign Keyهای پرتکرار باید Index داشته باشند.
- شماره‌های رسمی باید Unique Index داشته باشند.
- جدول‌های Log باید Index زمانی داشته باشند.
- Queryهای گزارش باید Index مناسب داشته باشند.
- Index زیاد باعث کندی Insert و Update می‌شود.

مرجع اصلی:

```text
docs/database/19_INDEXES_AND_PERFORMANCE.md
```

---

## استفاده از فایل دیتابیس برای Task مشتری

اگر Task مربوط به مشتری است، Codex باید بخواند:

```text
docs/database/03_CUSTOMERS_TABLES.md
docs/database/04_USERS_ROLES_PERMISSIONS_TABLES.md
docs/database/18_LOGS_AUDIT_SECURITY_TABLES.md
```

در صورت نیاز به Migration:

```text
docs/database/20_MIGRATION_RULES.md
```

قوانین مهم:

```text
اطلاعات هویتی حساس است.
مشاهده و ویرایش مشتری Permission و Scope می‌خواهد.
تغییر اطلاعات حساس باید Audit Log داشته باشد.
```

---

## استفاده از فایل دیتابیس برای Task قرارداد

اگر Task مربوط به قرارداد است، Codex باید بخواند:

```text
docs/database/05_CONTRACTS_TABLES.md
docs/database/06_INSTALLMENTS_TABLES.md
docs/database/08_FINANCIAL_TABLES.md
docs/database/18_LOGS_AUDIT_SECURITY_TABLES.md
```

قوانین مهم:

```text
ساخت قرارداد باید Transaction داشته باشد.
قرارداد بدون مشتری معتبر ساخته نشود.
ساخت اقساط باید همراه قرارداد انجام شود.
تغییر مبلغ قرارداد رسمی باید Audit و Financial Log داشته باشد.
```

---

## استفاده از فایل دیتابیس برای Task اقساط

اگر Task مربوط به اقساط است، Codex باید بخواند:

```text
docs/database/06_INSTALLMENTS_TABLES.md
docs/database/07_PAYMENTS_TABLES.md
docs/database/08_FINANCIAL_TABLES.md
docs/database/18_LOGS_AUDIT_SECURITY_TABLES.md
```

قوانین مهم:

```text
paid_amount فقط با Payment approved تغییر می‌کند.
قسط معوق بر اساس due_date و status تشخیص داده می‌شود.
تغییر وضعیت قسط باید History داشته باشد.
```

---

## استفاده از فایل دیتابیس برای Task پرداخت

اگر Task مربوط به پرداخت است، Codex باید بخواند:

```text
docs/database/07_PAYMENTS_TABLES.md
docs/database/06_INSTALLMENTS_TABLES.md
docs/database/08_FINANCIAL_TABLES.md
docs/database/13_FILES_TABLES.md
docs/database/18_LOGS_AUDIT_SECURITY_TABLES.md
```

قانون بسیار مهم:

```text
فقط Payment approved روی بدهی اثر می‌گذارد.
```

این وضعیت‌ها نباید اثر مالی داشته باشند:

```text
initiated
waiting
pending_review
failed
rejected
cancelled
expired
```

---

## استفاده از فایل دیتابیس برای Task مالی

اگر Task مربوط به Financial Domain است، Codex باید بخواند:

```text
docs/database/08_FINANCIAL_TABLES.md
docs/database/07_PAYMENTS_TABLES.md
docs/database/18_LOGS_AUDIT_SECURITY_TABLES.md
```

قوانین مهم:

```text
هیچ تغییر مالی بدون Financial Log انجام نشود.
Financial Log نباید حذف یا ویرایش شود.
Adjustment دستی باید reason و Permission داشته باشد.
Settlement باید Transaction داشته باشد.
```

---

## استفاده از فایل دیتابیس برای Task حقوقی

اگر Task مربوط به پرونده حقوقی است، Codex باید بخواند:

```text
docs/database/09_LEGAL_TABLES.md
docs/database/08_FINANCIAL_TABLES.md
docs/database/13_FILES_TABLES.md
docs/database/18_LOGS_AUDIT_SECURITY_TABLES.md
```

قوانین مهم:

```text
پرونده حقوقی باید از Snapshot مالی معتبر ساخته شود.
مدارک حقوقی باید Private باشند.
تغییر وضعیت پرونده باید History داشته باشد.
ارجاع حقوقی باید Audit Log داشته باشد.
```

---

## استفاده از فایل دیتابیس برای Task فایل

اگر Task مربوط به فایل است، Codex باید بخواند:

```text
docs/database/13_FILES_TABLES.md
docs/database/18_LOGS_AUDIT_SECURITY_TABLES.md
```

قوانین مهم:

```text
فایل واقعی داخل دیتابیس ذخیره نمی‌شود.
فایل حساس باید Private Storage داشته باشد.
مسیر واقعی فایل نباید به کاربر نمایش داده شود.
دانلود فایل حساس باید Permission و Scope داشته باشد.
```

---

## استفاده از فایل دیتابیس برای Task گزارش

اگر Task مربوط به گزارش یا Export است، Codex باید بخواند:

```text
docs/database/15_REPORTS_TABLES.md
docs/database/13_FILES_TABLES.md
docs/database/18_LOGS_AUDIT_SECURITY_TABLES.md
docs/database/19_INDEXES_AND_PERFORMANCE.md
```

قوانین مهم:

```text
گزارش حساس Permission و Scope می‌خواهد.
Export حساس باید Private Storage داشته باشد.
Export باید Audit Log و Expiration داشته باشد.
گزارش بزرگ باید Job شود.
```

---

## استفاده از فایل دیتابیس برای Task تنظیمات

اگر Task مربوط به Settings است، Codex باید بخواند:

```text
docs/database/14_SETTINGS_TABLES.md
docs/database/18_LOGS_AUDIT_SECURITY_TABLES.md
```

قوانین مهم:

```text
Secret خام در settings ذخیره نشود.
تنظیمات حساس باید Audit Log داشته باشند.
مقدار حساس در UI باید Mask شود.
```

---

## استفاده از فایل دیتابیس برای Backup و Update

اگر Task مربوط به Backup، Restore یا Update است، Codex باید بخواند:

```text
docs/database/16_BACKUP_UPDATE_TABLES.md
docs/database/13_FILES_TABLES.md
docs/database/18_LOGS_AUDIT_SECURITY_TABLES.md
docs/database/20_MIGRATION_RULES.md
```

قوانین مهم:

```text
Backup فایل حساس است.
Restore بدون Permission و reason ممنوع است.
Update حساس بدون pre_update_backup ممنوع است.
```

---

## استفاده از فایل دیتابیس برای پلاگین

اگر Task مربوط به پلاگین است، Codex باید بخواند:

```text
docs/database/17_PLUGINS_TABLES.md
docs/database/13_FILES_TABLES.md
docs/database/14_SETTINGS_TABLES.md
docs/database/16_BACKUP_UPDATE_TABLES.md
docs/database/18_LOGS_AUDIT_SECURITY_TABLES.md
docs/database/20_MIGRATION_RULES.md
```

قوانین مهم:

```text
پلاگین بدون Security Scan نصب نشود.
Migration پلاگین باید ثبت شود.
پلاگین نباید Core Permission، CSRF، Audit یا Financial Log را دور بزند.
```

---

## استفاده از فایل دیتابیس برای Logs

اگر Task مربوط به Audit، Security یا Logs است، Codex باید بخواند:

```text
docs/database/18_LOGS_AUDIT_SECURITY_TABLES.md
docs/database/19_INDEXES_AND_PERFORMANCE.md
```

قوانین مهم:

```text
Audit Log نباید حذف یا ویرایش شود.
Security Log نباید حذف یا ویرایش شود.
Financial Log نباید حذف یا ویرایش شود.
Secret خام نباید در Log ذخیره شود.
```

---

## قوانین داده‌های حساس

داده‌های زیر حساس هستند:

```text
کد ملی
شماره موبایل
مدارک هویتی
قرارداد
رسید پرداخت
چک
سفته
مدارک حقوقی
گزارش مالی
Backup
API Key
Token
Password
Private file path
```

قوانین:

```text
داده حساس نباید بی‌Scope خوانده شود.
داده حساس نباید در Log خام ذخیره شود.
داده حساس نباید در Response عمومی نمایش داده شود.
داده حساس باید با Permission کنترل شود.
```

---

## قوانین Transaction دیتابیس

Transaction الزامی است برای:

```text
Contract Creation
Installment Generation
Payment Approval
Payment Allocation
Refund
Settlement
Manual Financial Adjustment
Legal Referral
Restore
Update
Plugin Installation
Sensitive Migration
```

قانون:

```text
اگر عملیات چند جدول حساس را تغییر می‌دهد، Transaction الزامی است.
```

---

## قوانین گزارش‌گیری دیتابیس

برای گزارش‌ها:

```text
بازه زمانی لازم است.
Scope لازم است.
Permission لازم است.
Pagination یا Job لازم است.
Export حساس باید Private باشد.
Query باید Index مناسب داشته باشد.
```

ممنوع:

```text
گزارش کل دیتابیس بدون Scope
Export مستقیم فایل حساس در public
Query بدون LIMIT روی جدول حجیم
```

---

## قوانین جلوگیری از خطای Codex

Codex نباید:

```text
جدول یا ستون جدید بدون بررسی مستند Domain بسازد.
نام ستون دلخواه و خارج از استاندارد انتخاب کند.
مبلغ را FLOAT یا DOUBLE بگذارد.
پرداخت pending را روی بدهی اثر دهد.
Migration مخرب بدون Backup بسازد.
Indexهای بی‌دلیل و زیاد بسازد.
SELECT * در لیست‌های بزرگ استفاده کند.
فایل حساس را public کند.
Secret خام را در settings یا logs ذخیره کند.
```

---

## چک‌لیست قبل از ساخت Migration

قبل از ساخت Migration، Codex باید بررسی کند:

```text
آیا فایل دیتابیس Domain مربوطه خوانده شده؟
آیا نام جدول طبق استاندارد است؟
آیا ستون‌ها طبق Naming Convention هستند؟
آیا ستون‌های مالی DECIMAL هستند؟
آیا Soft Delete لازم است؟
آیا Actor Fields لازم است؟
آیا Indexهای ضروری تعریف شده‌اند؟
آیا Unique Index لازم است؟
آیا Migration idempotent است؟
آیا Backup لازم است؟
آیا Seed امن و idempotent است؟
```

---

## چک‌لیست قبل از نوشتن Query

قبل از نوشتن Query، Codex باید بررسی کند:

```text
آیا Prepared Statement استفاده شده؟
آیا ورودی کاربر امن است؟
آیا Scope رعایت شده؟
آیا Permission در لایه مناسب بررسی شده؟
آیا deleted_at IS NULL لازم است؟
آیا LIMIT لازم است؟
آیا Pagination لازم است؟
آیا Index مناسب وجود دارد؟
آیا SELECT * قابل حذف است؟
```

---

## قانون نهایی

Codex باید دیتابیس را منبع حقیقت سیستم بداند، اما فقط از طریق Serviceها و Repositoryهای امن با آن کار کند.

```text
No raw user SQL.
No unsafe migration.
No financial change without log.
No sensitive data without scope.
No public sensitive file.
```

---

## پایان فایل
````
