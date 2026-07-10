# 19 — Indexes & Performance

مستند قوانین Indexگذاری، Performance، Query Optimization، Pagination، Archive، گزارش‌گیری، لاگ‌های حجیم و بهینه‌سازی دیتابیس در پروژه **Proma Pay**

---

## فهرست مطالب

- [19 — Indexes \& Performance](#19--indexes--performance)
  - [فهرست مطالب](#فهرست-مطالب)
  - [هدف فایل](#هدف-فایل)
  - [اصل مهم](#اصل-مهم)
  - [تعریف Performance در Proma Pay](#تعریف-performance-در-proma-pay)
  - [قوانین عمومی Indexگذاری](#قوانین-عمومی-indexگذاری)
  - [قوانین نام‌گذاری Indexها](#قوانین-نامگذاری-indexها)
    - [الگوی Unique Index](#الگوی-unique-index)
    - [الگوی Index ساده](#الگوی-index-ساده)
    - [الگوی Composite Index](#الگوی-composite-index)
    - [قوانین](#قوانین)
  - [قوانین Primary Key](#قوانین-primary-key)
  - [قوانین Foreign Key Index](#قوانین-foreign-key-index)
  - [قوانین Unique Index](#قوانین-unique-index)
  - [قوانین Composite Index](#قوانین-composite-index)
  - [قوانین Index روی Status](#قوانین-index-روی-status)
  - [قوانین Index روی Date و Time](#قوانین-index-روی-date-و-time)
  - [قوانین Index روی Soft Delete](#قوانین-index-روی-soft-delete)
  - [قوانین Index روی JSON](#قوانین-index-روی-json)
  - [قوانین Fulltext Search](#قوانین-fulltext-search)
  - [قوانین Pagination](#قوانین-pagination)
  - [قوانین Queryهای گزارش‌گیری](#قوانین-queryهای-گزارشگیری)
  - [قوانین جدول‌های حجیم](#قوانین-جدولهای-حجیم)
  - [قوانین Archive و Retention](#قوانین-archive-و-retention)
  - [قوانین Transaction و Lock](#قوانین-transaction-و-lock)
  - [قوانین N+1 Query](#قوانین-n1-query)
  - [قوانین SELECT](#قوانین-select)
  - [قوانین COUNT](#قوانین-count)
  - [قوانین ORDER BY](#قوانین-order-by)
  - [قوانین Search و Filter](#قوانین-search-و-filter)
  - [Indexهای ضروری بر اساس Domain](#indexهای-ضروری-بر-اساس-domain)
    - [Core Tables](#core-tables)
    - [Customers](#customers)
    - [Contracts](#contracts)
    - [Installments](#installments)
    - [Payments](#payments)
    - [Financial](#financial)
    - [Legal](#legal)
    - [Chat](#chat)
    - [Notifications](#notifications)
    - [Calendar](#calendar)
    - [Files](#files)
    - [Reports](#reports)
    - [Backup \& Update](#backup--update)
    - [Plugins](#plugins)
    - [Logs, Audit \& Security](#logs-audit--security)
  - [Queryهای ممنوع یا پرریسک](#queryهای-ممنوع-یا-پرریسک)
    - [SELECT بدون LIMIT روی جدول بزرگ](#select-بدون-limit-روی-جدول-بزرگ)
    - [LIKE با Wildcard اول روی جدول بزرگ](#like-با-wildcard-اول-روی-جدول-بزرگ)
    - [ORDER BY بدون Index](#order-by-بدون-index)
    - [Query بدون Scope](#query-بدون-scope)
    - [SELECT ستون‌های سنگین در لیست](#select-ستونهای-سنگین-در-لیست)
  - [قوانین Performance برای Shared Hosting](#قوانین-performance-برای-shared-hosting)
  - [قوانین EXPLAIN و Slow Query](#قوانین-explain-و-slow-query)
  - [قوانین Migration مربوط به Index](#قوانین-migration-مربوط-به-index)
  - [چک‌لیست Performance](#چکلیست-performance)
  - [Definition of Done](#definition-of-done)
  - [پایان فایل](#پایان-فایل)

---

## هدف فایل

هدف این فایل این است که قوانین Indexگذاری و بهینه‌سازی دیتابیس در پروژه **Proma Pay** مشخص شود.

این فایل برای Codex مشخص می‌کند که:

- چه ستون‌هایی باید Index داشته باشند.
- چه Indexهایی ضروری هستند.
- چه Indexهایی خطرناک یا بی‌فایده هستند.
- Queryها چگونه باید نوشته شوند.
- گزارش‌ها چگونه بدون فشار زیاد اجرا شوند.
- جدول‌های حجیم چگونه مدیریت شوند.
- Pagination چگونه انجام شود.
- Soft Delete چگونه روی Performance اثر می‌گذارد.
- لاگ‌ها و خروجی‌ها چگونه Archive شوند.
- در محیط Shared Hosting چه محدودیت‌هایی باید رعایت شود.

---

## اصل مهم

اصل مهم در Performance:

> Index برای سریع‌تر کردن Query ساخته می‌شود، نه برای زیبایی دیتابیس.

هر Index هزینه دارد:

- INSERT کندتر می‌شود.
- UPDATE کندتر می‌شود.
- DELETE یا Soft Delete کندتر می‌شود.
- حجم دیتابیس بیشتر می‌شود.
- Migration سنگین‌تر می‌شود.
- Backup بزرگ‌تر می‌شود.

بنابراین:

> هر Index باید دلیل مشخص داشته باشد و با Query واقعی توجیه شود.

---

## تعریف Performance در Proma Pay

در Proma Pay، Performance یعنی سیستم بتواند عملیات زیر را سریع و پایدار انجام دهد:

- نمایش لیست مشتریان
- نمایش قراردادهای فعال
- نمایش اقساط سررسیدشده
- نمایش اقساط معوق
- ثبت پرداخت
- تأیید رسید کارت‌به‌کارت
- گزارش مالی
- گزارش معوقات
- مشاهده پرونده حقوقی
- دانلود فایل‌های مجاز
- ارسال اعلان‌ها
- اجرای Jobهای بکاپ
- نمایش داشبورد مدیریتی
- جستجوی قرارداد یا مشتری
- ثبت Audit و Security Log بدون کندی شدید

---

## قوانین عمومی Indexگذاری

قوانین عمومی:

- همه `PRIMARY KEY`ها باید `BIGINT UNSIGNED AUTO_INCREMENT` باشند.
- همه Foreign Keyهای پرتکرار باید Index داشته باشند.
- ستون‌های شماره رسمی مثل `contract_number` و `payment_number` باید Unique Index داشته باشند.
- ستون‌های `status` که زیاد فیلتر می‌شوند باید Index داشته باشند.
- ستون‌های تاریخ مثل `created_at`, `due_date`, `paid_at`, `referred_at` باید در Queryهای پرتکرار Index داشته باشند.
- ستون‌های `deleted_at` در جدول‌های Soft Delete باید Index داشته باشند.
- ستون‌های `customer_id`, `contract_id`, `installment_id`, `payment_id`, `legal_case_id` در جدول‌های مرتبط باید Index داشته باشند.
- روی ستون‌هایی که Cardinality خیلی پایین دارند، Index تنها معمولاً کافی نیست.
- برای Queryهای پرتکرار، Composite Index بهتر از چند Index جداگانه است.
- Indexهای تکراری ممنوع هستند.
- Indexهای بدون مصرف باید حذف یا ساخته نشوند.
- قبل از ساخت Index سنگین روی جدول پرحجم باید Backup و Migration Plan وجود داشته باشد.

---

## قوانین نام‌گذاری Indexها

نام Index باید قابل فهم و استاندارد باشد.

### الگوی Unique Index

```text
uniq_{table}_{column}
```

مثال:

```text
uniq_contracts_contract_number
uniq_payments_payment_number
uniq_users_email
```

### الگوی Index ساده

```text
idx_{table}_{column}
```

مثال:

```text
idx_contracts_customer_id
idx_installments_due_date
idx_payments_status
```

### الگوی Composite Index

```text
idx_{table}_{column1}_{column2}
```

مثال:

```text
idx_installments_contract_status
idx_payments_contract_status
idx_audit_logs_related_created
```

### قوانین

- نام Index باید کوتاه ولی واضح باشد.
- نام Index نباید تصادفی یا مبهم باشد.
- نام Index باید در Migrationها ثابت بماند.
- قبل از ساخت Index جدید، وجود Index مشابه بررسی شود.

---

## قوانین Primary Key

همه جدول‌ها باید Primary Key داشته باشند.

الگوی استاندارد:

```sql
id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY
```

قوانین:

- Primary Key نباید Business Key باشد.
- شماره رسمی مثل `contract_number` نباید Primary Key باشد.
- Primary Key نباید قابل تغییر باشد.
- Foreign Keyها باید به `id` اشاره کنند.
- `UUID` برای Primary Key پیش‌فرض توصیه نمی‌شود، مگر در سناریوی خاص.
- برای سیستم Shared Hosting، `BIGINT UNSIGNED AUTO_INCREMENT` ساده‌ترین و پایدارترین انتخاب است.

---

## قوانین Foreign Key Index

هر ستون Foreign Key باید Index داشته باشد.

ستون‌های رایج:

```text
user_id
customer_id
contract_id
installment_id
payment_id
legal_case_id
file_id
notification_id
report_id
backup_id
plugin_id
created_by
updated_by
deleted_by
approved_by
rejected_by
assigned_user_id
```

قوانین:

- حتی اگر Constraint فیزیکی Foreign Key تعریف نشود، Index لازم است.
- در جدول‌های پرتراکنش، FK Constraint فیزیکی باید با احتیاط استفاده شود.
- در Shared Hosting، گاهی FK Constraint سنگین می‌شود؛ اما Index همچنان ضروری است.
- Queryهای Scope معمولاً به `customer_id` یا `contract_id` وابسته‌اند، پس این ستون‌ها باید Index داشته باشند.

---

## قوانین Unique Index

Unique Index برای جلوگیری از داده تکراری استفاده می‌شود.

موارد الزامی:

```text
users.email
users.mobile
customers.customer_number
contracts.contract_number
installments.installment_number
payments.payment_number
legal_cases.legal_case_number
files.file_number
reports.report_key
settings.setting_key
plugins.plugin_id
```

قوانین:

- شماره‌های رسمی باید Unique باشند.
- `email` و `mobile` در صورت یکتا بودن باید Unique باشند.
- ترکیب‌های یکتا باید Composite Unique داشته باشند.
- Unique Index باید با قوانین Soft Delete هماهنگ باشد.
- اگر رکورد Soft Delete شده ولی مقدار Unique نباید دوباره استفاده شود، Unique ساده کافی است.
- اگر مقدار Unique بعد از Soft Delete قابل استفاده مجدد است، طراحی جداگانه لازم است.

---

## قوانین Composite Index

Composite Index برای Queryهایی استفاده می‌شود که چند شرط ثابت دارند.

مثال Query:

```sql
SELECT *
FROM installments
WHERE contract_id = ?
  AND status = ?
  AND deleted_at IS NULL
ORDER BY due_date ASC;
```

Index مناسب:

```text
idx_installments_contract_status_due_deleted
```

ساختار پیشنهادی:

```sql
KEY idx_installments_contract_status_due_deleted (
    contract_id,
    status,
    due_date,
    deleted_at
)
```

قوانین:

- ترتیب ستون‌ها مهم است.
- ستون‌های Equality اول بیایند.
- ستون‌های Range مثل تاریخ بعد از Equality بیایند.
- ستون Sort در صورت امکان داخل Index باشد.
- ستون `deleted_at` معمولاً در انتهای Composite Index قرار می‌گیرد، مگر Query اصلی اول Soft Delete را فیلتر کند.
- Composite Index نباید خیلی طولانی شود.
- Index پنج یا شش ستونه فقط در صورت نیاز واقعی ساخته شود.
- چند Index مشابه با Prefix یکسان نسازید.

---

## قوانین Index روی Status

ستون `status` در بسیاری از جدول‌ها وجود دارد.

Index ساده روی status وقتی مفید است که:

- جدول بزرگ باشد.
- status در Queryهای پرتکرار استفاده شود.
- status همراه با تاریخ یا مالک فیلتر شود.

Index ساده:

```sql
KEY idx_payments_status (status)
```

Composite بهتر:

```sql
KEY idx_payments_status_paid_at (status, paid_at)
```

قوانین:

- status به تنهایی Cardinality کمی دارد.
- status تنها برای جدول کوچک معمولاً لازم نیست.
- برای داشبوردها معمولاً Composite لازم است.
- مثال‌های خوب:

```text
installments.status + due_date
payments.status + received_at
legal_cases.status + referred_at
notifications.status + scheduled_at
job_logs.status + created_at
```

---

## قوانین Index روی Date و Time

ستون‌های تاریخ برای گزارش‌گیری و فیلترهای زمانی بسیار مهم هستند.

ستون‌های رایج:

```text
created_at
updated_at
deleted_at
due_date
paid_at
received_at
approved_at
referred_at
closed_at
sent_at
scheduled_at
started_at
completed_at
failed_at
expires_at
```

قوانین:

- جدول‌های گزارش‌پذیر باید Index زمانی داشته باشند.
- Queryهای گزارش باید بازه زمانی داشته باشند.
- Query بدون بازه زمانی روی جدول بزرگ ممنوع است.
- برای داشبوردها، تاریخ معمولاً همراه status Index می‌شود.
- برای Logها، `created_at` ضروری است.
- برای Jobها، `next_run_at` و `status` ضروری است.

مثال:

```sql
KEY idx_audit_logs_created_at (created_at)
```

مثال Composite:

```sql
KEY idx_notifications_status_scheduled_at (status, scheduled_at)
```

---

## قوانین Index روی Soft Delete

جدول‌های دارای Soft Delete معمولاً ستون زیر دارند:

```text
deleted_at
```

قوانین:

- جدول Soft Delete باید Index روی `deleted_at` داشته باشد.
- Queryهای عادی باید همیشه `deleted_at IS NULL` داشته باشند.
- اگر Query اصلی با customer_id انجام می‌شود، Composite بهتر است.

مثال:

```sql
KEY idx_customers_deleted_at (deleted_at)
```

Composite:

```sql
KEY idx_contracts_customer_deleted (customer_id, deleted_at)
```

قانون مهم:

> فراموش کردن `deleted_at IS NULL` باعث نمایش داده حذف‌شده و کندی Query می‌شود.

---

## قوانین Index روی JSON

در MySQL/MariaDB، Index مستقیم روی JSON معمولاً ساده و قابل اتکا نیست.

قوانین:

- داده‌هایی که زیاد فیلتر می‌شوند نباید فقط داخل JSON باشند.
- فیلدهای گزارش‌پذیر باید ستون واقعی داشته باشند.
- JSON برای metadata و داده تکمیلی است.
- اگر لازم است روی مقدار JSON فیلتر شود، باید ستون جداگانه ساخته شود.
- JSONهای بزرگ نباید در لیست‌ها Select شوند.
- JSON نباید جایگزین طراحی درست جدول شود.

مثال بد:

```text
metadata.customer_id
```

مثال خوب:

```text
customer_id
metadata
```

---

## قوانین Fulltext Search

Fulltext فقط برای جستجوی متنی استفاده می‌شود، نه فیلترهای معمولی.

موارد مناسب:

- جستجو در توضیحات
- جستجو در یادداشت‌ها
- جستجو در پیام‌ها
- جستجو در عنوان‌ها
- جستجو در متن گزارش‌ها

قوانین:

- Fulltext روی جدول‌های حساس باید Permission-based باشد.
- Fulltext روی داده حقوقی و مالی با احتیاط استفاده شود.
- Fulltext نباید اطلاعات داخلی را برای مشتری قابل جستجو کند.
- برای فارسی، کیفیت Fulltext ممکن است محدود باشد.
- برای شروع پروژه، Search ساده و Indexهای معمولی کافی است.
- Fulltext در نسخه‌های بعدی قابل اضافه شدن است.

---

## قوانین Pagination

Pagination برای همه لیست‌های بزرگ الزامی است.

قانون:

> هیچ لیست بزرگی نباید بدون LIMIT اجرا شود.

الگوی ساده:

```sql
SELECT *
FROM payments
WHERE contract_id = ?
ORDER BY created_at DESC
LIMIT 20 OFFSET 0;
```

برای داده‌های خیلی بزرگ، Keyset Pagination بهتر است:

```sql
SELECT *
FROM audit_logs
WHERE created_at < ?
ORDER BY created_at DESC
LIMIT 50;
```

قوانین:

- برای جدول‌های بزرگ، OFFSET زیاد کند است.
- برای Logها Keyset Pagination پیشنهاد می‌شود.
- لیست‌های مدیریتی باید Limit پیش‌فرض داشته باشند.
- حداکثر Limit باید کنترل شود.
- کاربر نباید بتواند `limit=100000` ارسال کند.
- Export باید از Pagination داخلی یا Job استفاده کند.

---

## قوانین Queryهای گزارش‌گیری

گزارش‌ها می‌توانند سنگین باشند.

قوانین:

- گزارش باید فیلتر تاریخ داشته باشد.
- گزارش باید Scope کاربر را رعایت کند.
- گزارش‌های سنگین باید Queue شوند.
- گزارش بدون Limit ممنوع است، مگر Export کنترل‌شده باشد.
- Export بزرگ باید به فایل تبدیل شود.
- گزارش مالی باید از جدول‌های Snapshot یا Ledger در صورت امکان استفاده کند.
- گزارش معوقات باید از Index روی `due_date` و `status` استفاده کند.
- گزارش حقوقی باید از Index روی `legal_cases.status` و `referred_at` استفاده کند.
- گزارش Log باید حتماً بازه زمانی داشته باشد.

---

## قوانین جدول‌های حجیم

جدول‌هایی که احتمالاً بزرگ می‌شوند:

```text
audit_logs
security_logs
activity_logs
system_logs
login_logs
permission_check_logs
data_change_logs
export_logs
error_logs
job_logs
financial_logs
chat_messages
external_message_logs
notification_delivery_logs
report_execution_logs
file_access_logs
```

قوانین:

- این جدول‌ها باید Index زمانی داشته باشند.
- Queryها باید Pagination داشته باشند.
- پاکسازی یا Archive Policy لازم است.
- JSONهای سنگین باید کنترل شوند.
- Export مستقیم از این جدول‌ها باید محدود باشد.
- نمایش UI باید فقط ستون‌های لازم را بخواند.
- گزارش‌های سنگین باید async باشند.

---

## قوانین Archive و Retention

برای جدول‌های حجیم باید سیاست نگهداری وجود داشته باشد.

نمونه سیاست:

| جدول | سیاست پیشنهادی |
|---|---|
| `audit_logs` | نگهداری بلندمدت یا Archive رسمی |
| `security_logs` | نگهداری بلندمدت یا Archive رسمی |
| `financial_logs` | غیرقابل حذف، Archive رسمی |
| `activity_logs` | قابل Archive |
| `system_logs` | قابل Cleanup |
| `error_logs` | قابل Archive |
| `job_logs` | قابل Cleanup |
| `notification_delivery_logs` | قابل Archive |
| `external_message_logs` | قابل Archive |
| `file_access_logs` | برای فایل حساس بلندمدت |

قوانین:

- Archive نباید Audit و Security مهم را نابود کند.
- Archive باید Audit Log داشته باشد.
- پاکسازی نباید بدون Backup انجام شود.
- Retention Policy باید در Settings تعریف شود.
- داده‌های حقوقی و مالی باید طولانی‌تر نگهداری شوند.

---

## قوانین Transaction و Lock

عملیات حساس باید Transaction داشته باشند:

- ایجاد قرارداد
- ایجاد اقساط
- تأیید پرداخت
- تخصیص پرداخت
- Refund
- Settlement
- Adjustment
- Legal Referral
- Restore
- Update
- Plugin Installation
- Migration حساس

قوانین Performance در Transaction:

- Transaction باید کوتاه باشد.
- داخل Transaction نباید عملیات سنگین فایل انجام شود.
- داخل Transaction نباید ارسال SMS انجام شود.
- داخل Transaction نباید Export انجام شود.
- ابتدا داده لازم Validate شود، سپس Transaction شروع شود.
- عملیات خارجی بعد از Commit با Job انجام شود.
- Row Lock باید محدود باشد.
- Query داخل Transaction باید Index داشته باشد.
- Transaction باز رها نشود.

---

## قوانین N+1 Query

N+1 یعنی برای هر رکورد یک Query جدا اجرا شود.

مثال بد:

```text
لیست 100 قرارداد را بگیر
برای هر قرارداد یک Query برای مشتری بزن
برای هر قرارداد یک Query برای اقساط بزن
برای هر قرارداد یک Query برای پرداخت‌ها بزن
```

قوانین:

- برای لیست‌ها از Join کنترل‌شده یا Batch Query استفاده شود.
- اطلاعات شمارشی با Query گروهی گرفته شود.
- در صفحه جزئیات، Queryهای مرتبط محدود باشند.
- در گزارش‌ها N+1 ممنوع است.
- در Timeline و Dashboard، داده‌ها باید Batch خوانده شوند.

---

## قوانین SELECT

قوانین:

- `SELECT *` در Queryهای لیستی ممنوع است.
- فقط ستون‌های لازم Select شوند.
- ستون‌های JSON بزرگ در لیست‌ها Select نشوند.
- ستون‌های TEXT بزرگ در لیست‌ها Select نشوند.
- فایل مسیر واقعی، Secret و Metadata سنگین در لیست‌ها Select نشوند.
- Detail Page می‌تواند ستون‌های کامل‌تر بخواند.
- Export باید ستون‌ها را کنترل‌شده انتخاب کند.

مثال بد:

```sql
SELECT *
FROM files
WHERE customer_id = ?;
```

مثال بهتر:

```sql
SELECT id, file_number, original_name, file_category, status, uploaded_at
FROM files
WHERE owner_customer_id = ?
  AND deleted_at IS NULL
ORDER BY uploaded_at DESC
LIMIT 20;
```

---

## قوانین COUNT

`COUNT(*)` روی جدول‌های بزرگ می‌تواند سنگین شود.

قوانین:

- COUNT باید با فیلتر Index شده باشد.
- COUNT برای داشبورد باید Cache یا Summary داشته باشد.
- COUNT روی Logهای بزرگ بدون تاریخ ممنوع است.
- برای Pagination لازم نیست همیشه Total دقیق محاسبه شود.
- در جدول‌های بزرگ می‌توان approximate count یا “Load more” استفاده کرد.

مثال بد:

```sql
SELECT COUNT(*)
FROM audit_logs;
```

مثال بهتر:

```sql
SELECT COUNT(*)
FROM audit_logs
WHERE created_at >= ?
  AND created_at < ?;
```

---

## قوانین ORDER BY

قوانین:

- ستون ORDER BY باید تا حد امکان Index داشته باشد.
- ORDER BY روی ستون بدون Index در جدول بزرگ ممنوع است.
- ORDER BY روی expressionهای محاسباتی سنگین ممنوع است.
- ORDER BY همزمان با WHERE باید با Composite Index هماهنگ باشد.

مثال:

```sql
WHERE contract_id = ?
ORDER BY due_date ASC
```

Index مناسب:

```sql
KEY idx_installments_contract_due_date (contract_id, due_date)
```

---

## قوانین Search و Filter

قوانین:

- جستجو با `%keyword%` روی جدول بزرگ کند است.
- برای جستجوی شماره رسمی از Exact Match یا Prefix استفاده شود.
- برای شماره موبایل، کد ملی و شماره قرارداد Index لازم است.
- فیلترهای ورودی باید whitelist شوند.
- کاربر نباید بتواند نام ستون دلخواه برای Sort ارسال کند.
- Sort و Filter باید فقط روی ستون‌های مجاز انجام شود.
- Search روی داده حساس باید Permission داشته باشد.

مثال خوب:

```sql
WHERE contract_number = ?
```

مثال قابل قبول:

```sql
WHERE contract_number LIKE 'CTR-%'
```

مثال پرریسک:

```sql
WHERE description LIKE '%something%'
```

---

## Indexهای ضروری بر اساس Domain

### Core Tables

```text
users.id
users.email
users.mobile
users.status
users.deleted_at

branches.id
branches.status

system_jobs.status
system_jobs.next_run_at
```

---

### Customers

Indexهای ضروری:

```text
customers.customer_number
customers.mobile
customers.national_code
customers.status
customers.deleted_at
customers.created_at
```

Compositeهای پیشنهادی:

```text
idx_customers_status_deleted
idx_customers_mobile_deleted
idx_customers_national_code_deleted
```

---

### Contracts

Indexهای ضروری:

```text
contracts.contract_number
contracts.customer_id
contracts.status
contracts.contract_status
contracts.legal_status
contracts.created_at
contracts.deleted_at
```

Compositeهای پیشنهادی:

```text
idx_contracts_customer_status
idx_contracts_customer_deleted
idx_contracts_status_created
idx_contracts_legal_status_created
```

---

### Installments

Indexهای ضروری:

```text
installments.installment_number
installments.customer_id
installments.contract_id
installments.status
installments.due_date
installments.paid_at
installments.deleted_at
```

Compositeهای پیشنهادی:

```text
idx_installments_contract_status
idx_installments_customer_status
idx_installments_status_due_date
idx_installments_contract_due_date
idx_installments_customer_due_date
idx_installments_contract_status_due_deleted
```

---

### Payments

Indexهای ضروری:

```text
payments.payment_number
payments.customer_id
payments.contract_id
payments.installment_id
payments.status
payments.payment_method
payments.review_status
payments.paid_at
payments.received_at
payments.approved_at
payments.deleted_at
```

Compositeهای پیشنهادی:

```text
idx_payments_contract_status
idx_payments_customer_status
idx_payments_status_received
idx_payments_method_status
idx_payments_review_status_received
```

---

### Financial

Indexهای ضروری:

```text
financial_logs.log_number
financial_logs.event_type
financial_logs.related_type
financial_logs.related_id
financial_logs.customer_id
financial_logs.contract_id
financial_logs.payment_id
financial_logs.occurred_at
```

Compositeهای پیشنهادی:

```text
idx_financial_logs_related
idx_financial_logs_contract_event
idx_financial_logs_customer_occurred
idx_financial_logs_event_occurred
```

---

### Legal

Indexهای ضروری:

```text
legal_cases.legal_case_number
legal_cases.customer_id
legal_cases.contract_id
legal_cases.status
legal_cases.legal_stage
legal_cases.assigned_lawyer_id
legal_cases.referred_at
legal_cases.deleted_at
```

Compositeهای پیشنهادی:

```text
idx_legal_cases_status_referred
idx_legal_cases_lawyer_status
idx_legal_cases_contract_status
```

---

### Chat

Indexهای ضروری:

```text
chat_threads.thread_number
chat_threads.customer_id
chat_threads.contract_id
chat_threads.status
chat_threads.last_message_at
chat_messages.thread_id
chat_messages.created_at
chat_messages.sender_user_id
chat_messages.sender_customer_id
chat_messages.deleted_at
```

Compositeهای پیشنهادی:

```text
idx_chat_threads_customer_last_message
idx_chat_threads_status_last_message
idx_chat_messages_thread_created
```

---

### Notifications

Indexهای ضروری:

```text
notifications.notification_number
notifications.notification_type
notifications.status
notifications.customer_id
notifications.scheduled_at
notification_recipients.user_id
notification_recipients.customer_id
notification_recipients.is_read
notification_delivery_logs.status
notification_delivery_logs.next_retry_at
```

Compositeهای پیشنهادی:

```text
idx_notification_recipients_user_read
idx_notification_recipients_customer_read
idx_notifications_status_scheduled
idx_delivery_logs_status_retry
```

---

### Calendar

Indexهای ضروری:

```text
calendar_events.event_number
calendar_events.start_at
calendar_events.status
calendar_events.assigned_user_id
calendar_tasks.task_number
calendar_tasks.status
calendar_tasks.due_at
calendar_tasks.assigned_user_id
calendar_reminders.status
calendar_reminders.remind_at
```

Compositeهای پیشنهادی:

```text
idx_calendar_events_user_start
idx_calendar_events_status_start
idx_calendar_tasks_user_status_due
idx_calendar_reminders_status_remind
```

---

### Files

Indexهای ضروری:

```text
files.file_number
files.file_category
files.status
files.scan_status
files.owner_customer_id
files.uploaded_by
files.uploaded_at
files.deleted_at
file_links.file_id
file_links.related_type
file_links.related_id
file_access_logs.file_id
file_access_logs.created_at
file_download_tokens.token_hash
file_download_tokens.expires_at
```

Compositeهای پیشنهادی:

```text
idx_file_links_related
idx_files_category_status
idx_files_owner_uploaded
idx_file_access_logs_file_created
```

---

### Reports

Indexهای ضروری:

```text
reports.report_key
reports.domain_key
reports.status
report_exports.report_id
report_exports.requested_by
report_exports.status
report_exports.expires_at
report_schedules.status
report_schedules.next_run_at
report_execution_logs.report_id
report_execution_logs.started_at
```

Compositeهای پیشنهادی:

```text
idx_report_exports_user_status
idx_report_schedules_status_next_run
idx_report_execution_report_started
```

---

### Backup & Update

Indexهای ضروری:

```text
backups.backup_number
backups.status
backups.backup_type
backups.completed_at
backup_schedules.is_active
backup_schedules.next_run_at
update_packages.package_key
update_packages.version
update_installations.status
system_versions.version
system_versions.is_current
maintenance_logs.status
```

Compositeهای پیشنهادی:

```text
idx_backups_status_completed
idx_backup_schedules_active_next_run
idx_update_packages_key_version
```

---

### Plugins

Indexهای ضروری:

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

Compositeهای پیشنهادی:

```text
idx_plugins_status_enabled
idx_plugin_hooks_event_active
idx_plugin_routes_plugin_active
idx_plugin_logs_plugin_created
```

---

### Logs, Audit & Security

Indexهای ضروری:

```text
audit_logs.audit_number
audit_logs.event_type
audit_logs.actor_user_id
audit_logs.related_type
audit_logs.related_id
audit_logs.created_at

security_logs.security_number
security_logs.event_type
security_logs.threat_level
security_logs.status
security_logs.ip_address
security_logs.created_at

login_logs.user_id
login_logs.customer_id
login_logs.ip_address
login_logs.logged_at

error_logs.error_level
error_logs.error_type
error_logs.created_at

job_logs.job_type
job_logs.status
job_logs.created_at
```

Compositeهای پیشنهادی:

```text
idx_audit_logs_related_created
idx_audit_logs_user_created
idx_security_logs_ip_created
idx_security_logs_threat_created
idx_login_logs_user_logged
idx_job_logs_status_created
```

---

## Queryهای ممنوع یا پرریسک

### SELECT بدون LIMIT روی جدول بزرگ

ممنوع:

```sql
SELECT *
FROM audit_logs;
```

---

### LIKE با Wildcard اول روی جدول بزرگ

پرریسک:

```sql
SELECT *
FROM customers
WHERE full_name LIKE '%علی%';
```

---

### ORDER BY بدون Index

پرریسک:

```sql
SELECT *
FROM payments
ORDER BY metadata->'$.something';
```

---

### Query بدون Scope

ممنوع در پنل کاربر محدود:

```sql
SELECT *
FROM contracts
WHERE status = 'active';
```

باید Scope داشته باشد:

```sql
SELECT *
FROM contracts
WHERE status = 'active'
  AND branch_id = ?
  AND deleted_at IS NULL
LIMIT 20;
```

---

### SELECT ستون‌های سنگین در لیست

بد:

```sql
SELECT metadata, description, body
FROM chat_messages
WHERE thread_id = ?;
```

بهتر:

```sql
SELECT id, sender_type, message_type, status, created_at
FROM chat_messages
WHERE thread_id = ?
ORDER BY created_at DESC
LIMIT 50;
```

---

## قوانین Performance برای Shared Hosting

چون پروژه باید با هاست اشتراکی هم سازگار باشد:

- Queryهای سنگین باید محدود شوند.
- Exportهای بزرگ باید Job شوند.
- فایل‌های بزرگ نباید داخل PHP Memory کامل خوانده شوند.
- Backup باید مرحله‌ای اجرا شود.
- Import و Export باید Chunk شوند.
- Migrationهای سنگین باید با احتیاط اجرا شوند.
- ساخت Index روی جدول بزرگ باید زمان مناسب انجام شود.
- Cron/Jobها نباید همزمان زیاد اجرا شوند.
- Dashboard نباید در هر Load گزارش سنگین اجرا کند.
- Cache برای Settings و Dashboard Summary استفاده شود.
- لاگ‌های حجیم باید Archive شوند.

---

## قوانین EXPLAIN و Slow Query

برای Queryهای مهم باید `EXPLAIN` بررسی شود.

مواردی که باید بررسی شوند:

- آیا Index استفاده شده؟
- آیا Full Table Scan رخ داده؟
- آیا filesort رخ داده؟
- آیا temporary table ساخته شده؟
- تعداد rows تخمینی چقدر است؟
- آیا Composite Index مناسب است؟
- آیا ترتیب ستون‌های Index درست است؟

قوانین:

- Queryهای Dashboard باید EXPLAIN شوند.
- Queryهای گزارش مالی باید EXPLAIN شوند.
- Queryهای لیست اقساط معوق باید EXPLAIN شوند.
- Queryهای پرداخت‌ها باید EXPLAIN شوند.
- Queryهای Log روی جدول بزرگ باید EXPLAIN شوند.
- Slow Queryها باید در system_logs یا error_logs قابل ثبت باشند.

---

## قوانین Migration مربوط به Index

قوانین:

- ساخت Index جدید باید در Migration جداگانه باشد، اگر جدول بزرگ است.
- حذف Index باید با احتیاط انجام شود.
- قبل از حذف Index باید بررسی شود Queryها از آن استفاده نمی‌کنند.
- نام Index باید ثابت و استاندارد باشد.
- Migration باید idempotent باشد.
- اگر Index وجود دارد، دوباره ساخته نشود.
- ساخت Unique Index باید قبلش داده‌های تکراری را بررسی کند.
- ساخت Index روی ستون طولانی VARCHAR باید با طول مناسب انجام شود، اگر لازم است.
- ساخت Index روی جدول حجیم باید با Backup و Maintenance Plan انجام شود.

مثال بررسی وجود Index:

```text
Before adding index:
- Check information_schema.statistics
- If index exists, skip
- If not exists, create
```

---

## چک‌لیست Performance

قبل از نهایی شدن Migrationها بررسی شود:

- [ ] همه جدول‌ها Primary Key دارند.
- [ ] همه شماره‌های رسمی Unique Index دارند.
- [ ] Foreign Keyهای پرتکرار Index دارند.
- [ ] ستون‌های status پرتکرار Index دارند.
- [ ] ستون‌های date پرتکرار Index دارند.
- [ ] جدول‌های Soft Delete روی deleted_at Index دارند.
- [ ] Queryهای لیستی LIMIT دارند.
- [ ] Queryهای بزرگ Pagination دارند.
- [ ] گزارش‌های بزرگ بازه زمانی دارند.
- [ ] Exportهای بزرگ Job می‌شوند.
- [ ] Queryهای Dashboard سنگین نیستند.
- [ ] جدول‌های Log Index زمانی دارند.
- [ ] Archive Policy برای Logها وجود دارد.
- [ ] JSON برای فیلدهای پرتکرار استفاده نشده است.
- [ ] SELECT * در لیست‌های اصلی استفاده نشده است.
- [ ] Queryهای حساس Scope دارند.
- [ ] Queryهای مهم با EXPLAIN بررسی شده‌اند.
- [ ] Indexهای تکراری وجود ندارند.
- [ ] Indexهای Composite با Queryهای واقعی هماهنگ هستند.
- [ ] Migrationهای Index روی جدول بزرگ امن هستند.
- [ ] Shared Hosting محدودیت‌ها را رعایت می‌کند.

---

## Definition of Done

Indexes & Performance زمانی کامل است که:

- همه جدول‌های اصلی Indexهای ضروری داشته باشند.
- شماره‌های رسمی Unique باشند.
- Foreign Keyهای پرتکرار Index داشته باشند.
- Queryهای لیستی سریع و محدود باشند.
- Queryهای گزارش‌گیری Scope و بازه زمانی داشته باشند.
- جدول‌های حجیم قابل Archive یا Cleanup باشند.
- Logها باعث کندی جدی سیستم نشوند.
- Dashboard بدون Queryهای سنگین Load شود.
- Exportهای بزرگ به صورت Job اجرا شوند.
- Soft Delete در Queryها و Indexها لحاظ شده باشد.
- JSON فقط برای metadata استفاده شود، نه فیلترهای اصلی.
- Transactionها کوتاه و ایمن باشند.
- N+1 Query در لیست‌ها و گزارش‌ها وجود نداشته باشد.
- Queryهای حساس با EXPLAIN بررسی شده باشند.
- Migrationهای Index ایمن، idempotent و قابل اجرا باشند.
- پروژه با MySQL/MariaDB و PHP 7.4+ در Shared Hosting سازگار باشد.
- Codex بتواند بر اساس این مستندات Indexهای Database را به‌صورت امن و استاندارد بسازد.

---

## پایان فایل
````
