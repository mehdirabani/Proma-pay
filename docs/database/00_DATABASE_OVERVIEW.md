# 00 — Database Overview

مستند نمای کلی دیتابیس، استاندارد جدول‌ها، نام‌گذاری، Migrationها و قوانین ذخیره‌سازی داده در پروژه **Proma Pay**

---

## فهرست مطالب

- [00 — Database Overview](#00--database-overview)
  - [فهرست مطالب](#فهرست-مطالب)
  - [هدف این پوشه](#هدف-این-پوشه)
  - [اصل مهم دیتابیس](#اصل-مهم-دیتابیس)
  - [تکنولوژی دیتابیس](#تکنولوژی-دیتابیس)
  - [لیست فایل‌های پیشنهادی این پوشه](#لیست-فایلهای-پیشنهادی-این-پوشه)
  - [قوانین عمومی طراحی دیتابیس](#قوانین-عمومی-طراحی-دیتابیس)
  - [اصول نام‌گذاری جدول‌ها](#اصول-نامگذاری-جدولها)
  - [اصول نام‌گذاری ستون‌ها](#اصول-نامگذاری-ستونها)
  - [ستون‌های استاندارد مشترک](#ستونهای-استاندارد-مشترک)
  - [قوانین Primary Key](#قوانین-primary-key)
  - [قوانین Foreign Key](#قوانین-foreign-key)
    - [قوانین حذف رابطه](#قوانین-حذف-رابطه)
  - [قوانین Status](#قوانین-status)
  - [قوانین تاریخ و زمان](#قوانین-تاریخ-و-زمان)
  - [قوانین مبلغ و محاسبات مالی](#قوانین-مبلغ-و-محاسبات-مالی)
  - [قوانین Soft Delete](#قوانین-soft-delete)
  - [قوانین Metadata](#قوانین-metadata)
  - [قوانین Audit و Log](#قوانین-audit-و-log)
  - [قوانین اطلاعات حساس](#قوانین-اطلاعات-حساس)
  - [قوانین Index](#قوانین-index)
  - [قوانین Migration](#قوانین-migration)
  - [Migrationهای ممنوع یا خطرناک](#migrationهای-ممنوع-یا-خطرناک)
  - [قوانین Seed Data](#قوانین-seed-data)
  - [قوانین Transaction](#قوانین-transaction)
  - [قوانین Performance](#قوانین-performance)
  - [قوانین پلاگین‌ها در دیتابیس](#قوانین-پلاگینها-در-دیتابیس)
  - [جدول‌های اصلی سیستم](#جدولهای-اصلی-سیستم)
  - [ذخیره فایل در دیتابیس](#ذخیره-فایل-در-دیتابیس)
  - [ستون‌های شماره رسمی](#ستونهای-شماره-رسمی)
  - [قوانین داده‌های مشتری](#قوانین-دادههای-مشتری)
  - [قوانین داده‌های مالی](#قوانین-دادههای-مالی)
  - [قوانین گزارش‌گیری](#قوانین-گزارشگیری)
  - [چک‌لیست طراحی جدول جدید](#چکلیست-طراحی-جدول-جدید)
  - [Definition of Done](#definition-of-done)
  - [پایان فایل](#پایان-فایل)

---

## هدف این پوشه

پوشه `docs/database` برای مستندسازی ساختار دیتابیس پروژه **Proma Pay** استفاده می‌شود.

این بخش باید مشخص کند:

- چه جدول‌هایی در سیستم وجود دارند.
- هر جدول چه مسئولیتی دارد.
- رابطه جدول‌ها با یکدیگر چیست.
- نام‌گذاری جدول‌ها و ستون‌ها چگونه است.
- Migrationها چگونه نوشته می‌شوند.
- Indexها چگونه تعریف می‌شوند.
- اطلاعات حساس چگونه ذخیره می‌شوند.
- Soft Delete، Audit، Log و Metadata چگونه مدیریت می‌شوند.
- Codex هنگام ساخت دیتابیس چه اصولی را باید رعایت کند.

---

## اصل مهم دیتابیس

دیتابیس Proma Pay باید برای یک سیستم واقعی فروش اقساطی طراحی شود؛ نه یک پروژه تمرینی ساده.

بنابراین دیتابیس باید:

- قابل توسعه باشد.
- قابل نگهداری باشد.
- امن باشد.
- داده مالی را دقیق نگهداری کند.
- داده حساس مشتری را محافظت کند.
- آماده گزارش‌گیری باشد.
- آماده توسعه پلاگینی باشد.
- با هاست اشتراکی سازگار باشد.
- با MySQL و MariaDB کار کند.
- با PHP 7.4+ سازگار باشد.

---

## تکنولوژی دیتابیس

دیتابیس اصلی سیستم:

```text
MySQL / MariaDB
```

روش اتصال:

```text
PDO Prepared Statements
```

قوانین:

- استفاده از Query خام بدون Prepared Statement ممنوع است.
- Queryها باید قابل خواندن و قابل نگهداری باشند.
- هیچ داده ورودی نباید مستقیم داخل SQL قرار بگیرد.
- Migrationها باید با MySQL/MariaDB سازگار باشند.
- از قابلیت‌های خیلی خاص نسخه‌های جدید MySQL نباید طوری استفاده شود که هاست اشتراکی را دچار مشکل کند.
- سیستم باید با `utf8mb4` کار کند.
- Collation پیشنهادی `utf8mb4_unicode_ci` است.

---

## لیست فایل‌های پیشنهادی این پوشه

ساختار پیشنهادی پوشه:

```text
docs/database/
├── 00_DATABASE_OVERVIEW.md
├── 01_NAMING_CONVENTIONS.md
├── 02_CORE_TABLES.md
├── 03_CUSTOMERS_TABLES.md
├── 04_USERS_ROLES_PERMISSIONS_TABLES.md
├── 05_CONTRACTS_TABLES.md
├── 06_INSTALLMENTS_TABLES.md
├── 07_PAYMENTS_TABLES.md
├── 08_FINANCIAL_TABLES.md
├── 09_LEGAL_TABLES.md
├── 10_CHAT_TABLES.md
├── 11_NOTIFICATIONS_TABLES.md
├── 12_CALENDAR_TABLES.md
├── 13_FILES_TABLES.md
├── 14_SETTINGS_TABLES.md
├── 15_REPORTS_TABLES.md
├── 16_BACKUP_UPDATE_TABLES.md
├── 17_PLUGINS_TABLES.md
├── 18_LOGS_AUDIT_SECURITY_TABLES.md
├── 19_INDEXES_AND_PERFORMANCE.md
└── 20_MIGRATION_RULES.md
```

---

## قوانین عمومی طراحی دیتابیس

قوانین اصلی:

- هر جدول باید فقط یک مسئولیت مشخص داشته باشد.
- جدول‌ها نباید چند مفهوم نامرتبط را با هم نگهداری کنند.
- اطلاعات مالی باید دقیق و قابل ردیابی باشند.
- اطلاعات حساس باید محدود، امن و قابل Audit باشند.
- تغییرات حساس باید در Audit Log ثبت شوند.
- داده‌های حذف‌شدنی مهم باید Soft Delete شوند.
- جدول‌های گزارش‌گیری نباید منبع حقیقت باشند، مگر Snapshot رسمی باشند.
- جدول‌ها باید برای Queryهای رایج Index مناسب داشته باشند.
- جدول‌ها باید از ابتدا برای توسعه آینده آماده باشند.
- رابطه‌ها باید واضح و قابل فهم باشند.

---

## اصول نام‌گذاری جدول‌ها

قوانین عمومی:

- نام جدول‌ها انگلیسی باشد.
- نام جدول‌ها `snake_case` باشد.
- نام جدول‌ها جمع باشد.
- نام جدول‌ها واضح و قابل فهم باشد.
- از نام‌های مبهم مثل `data`, `info`, `items` بدون Context استفاده نشود.
- جدول‌های پلاگین باید Prefix جدا داشته باشند.

نمونه صحیح:

```text
customers
contracts
contract_items
installments
payments
payment_receipts
legal_cases
chat_threads
chat_messages
calendar_events
notifications
files
settings
plugins
audit_logs
security_logs
```

نمونه اشتباه:

```text
tbl_customer
customerData
info
data_table
cst
paytbl
```

---

## اصول نام‌گذاری ستون‌ها

قوانین عمومی:

- نام ستون‌ها انگلیسی باشد.
- نام ستون‌ها `snake_case` باشد.
- ستون‌های Foreign Key با `_id` تمام شوند.
- ستون‌های تاریخ با `_at` تمام شوند.
- ستون‌های Boolean با `is_` یا `has_` شروع شوند.
- ستون‌های وضعیت با `status` نام‌گذاری شوند.
- ستون‌های مبلغ با `_amount` تمام شوند.
- ستون‌های تعداد با `_count` تمام شوند.
- ستون‌های توضیح با `description` یا `note` مشخص شوند.
- ستون‌های Metadata با `metadata` نام‌گذاری شوند.

نمونه صحیح:

```text
customer_id
contract_id
installment_id
payment_amount
remaining_amount
paid_amount
status
created_at
updated_at
deleted_at
created_by
metadata
```

نمونه اشتباه:

```text
cid
cID
date
money
stat
desc1
extra
```

---

## ستون‌های استاندارد مشترک

بیشتر جدول‌های اصلی باید ستون‌های زیر را داشته باشند:

| ستون | نوع پیشنهادی | توضیح |
|---|---|---|
| `id` | BIGINT UNSIGNED AUTO_INCREMENT | شناسه اصلی |
| `created_at` | DATETIME NULL | زمان ایجاد |
| `updated_at` | DATETIME NULL | زمان آخرین بروزرسانی |
| `deleted_at` | DATETIME NULL | زمان حذف نرم |
| `created_by` | BIGINT UNSIGNED NULL | کاربر ایجادکننده |
| `updated_by` | BIGINT UNSIGNED NULL | کاربر ویرایش‌کننده |
| `deleted_by` | BIGINT UNSIGNED NULL | کاربر حذف‌کننده |
| `metadata` | JSON یا LONGTEXT NULL | داده‌های تکمیلی |

قانون:

همه جدول‌ها لازم نیست همه این ستون‌ها را داشته باشند، اما جدول‌های اصلی، حساس و عملیاتی باید این ساختار را داشته باشند.

---

## قوانین Primary Key

قوانین:

- هر جدول باید Primary Key داشته باشد.
- نام Primary Key به صورت پیش‌فرض `id` باشد.
- نوع پیشنهادی `BIGINT UNSIGNED AUTO_INCREMENT` است.
- از String به عنوان Primary Key اصلی استفاده نشود، مگر در موارد خاص.
- برای شناسه‌های نمایشی از ستون جدا مثل `contract_number` استفاده شود.
- شناسه داخلی دیتابیس نباید الزاماً در UI به عنوان شماره رسمی نمایش داده شود.

نمونه:

```sql
id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY
```

---

## قوانین Foreign Key

قوانین:

- Foreign Keyها باید با `_id` تمام شوند.
- رابطه‌ها باید در نام ستون مشخص باشند.
- اگر محدودیت واقعی Foreign Key در هاست مشکل ایجاد کند، حداقل Index و منطق کنترل در Service الزامی است.
- حذف داده والد نباید باعث حذف ناخواسته داده مالی شود.
- برای داده‌های حساس، حذف Cascade باید با احتیاط استفاده شود.

نمونه:

```text
customer_id
contract_id
installment_id
payment_id
legal_case_id
assigned_user_id
```

### قوانین حذف رابطه

برای داده‌های مالی:

```text
ON DELETE CASCADE
```

نباید بدون بررسی استفاده شود.

برای داده‌های مالی، حقوقی، قرارداد، پرداخت و اقساط، معمولاً بهتر است:

```text
ON DELETE RESTRICT
```

یا کنترل نرم‌افزاری با Soft Delete استفاده شود.

---

## قوانین Status

ستون `status` باید مقدارهای مشخص و محدود داشته باشد.

نمونه وضعیت قرارداد:

```text
draft
active
completed
cancelled
legal
```

نمونه وضعیت قسط:

```text
pending
partially_paid
paid
overdue
legal
cancelled
```

نمونه وضعیت پرداخت:

```text
initiated
waiting
pending_review
approved
failed
rejected
cancelled
expired
```

قوانین:

- مقدار status نباید آزاد و بی‌قاعده باشد.
- هر status باید در Domain مربوطه مستند شده باشد.
- تغییر statusهای حساس باید Audit Log داشته باشد.
- تغییر status مالی باید Financial Log داشته باشد.

---

## قوانین تاریخ و زمان

قوانین:

- همه زمان‌های سیستمی در دیتابیس با میلادی و فرمت `DATETIME` ذخیره شوند.
- نمایش تاریخ در UI می‌تواند شمسی باشد.
- تاریخ شمسی نباید به عنوان منبع اصلی ذخیره شود.
- ستون‌هایی که زمان رویداد را نشان می‌دهند با `_at` تمام شوند.
- ستون‌هایی که فقط تاریخ دارند می‌توانند با `_date` تمام شوند.
- Timezone سیستم باید در Settings مشخص باشد.

نمونه ستون‌ها:

```text
created_at
updated_at
deleted_at
paid_at
approved_at
rejected_at
started_at
completed_at
failed_at
due_date
contract_date
```

---

## قوانین مبلغ و محاسبات مالی

اطلاعات مالی باید دقیق ذخیره شود.

قوانین:

- برای مبلغ از `DECIMAL` استفاده شود.
- استفاده از `FLOAT` و `DOUBLE` برای مبلغ ممنوع است.
- نوع پیشنهادی برای مبلغ:

```sql
DECIMAL(15,2)
```

- مبلغ‌ها باید با واحد اصلی سیستم ذخیره شوند.
- واحد پول در Settings مشخص شود.
- محاسبات مالی نباید فقط در Frontend انجام شود.
- تغییرات مالی باید Financial Log داشته باشند.
- مبلغ‌های Snapshot باید در زمان عملیات حساس ذخیره شوند.

نمونه ستون‌های مالی:

```text
total_amount
paid_amount
remaining_amount
installment_amount
penalty_amount
discount_amount
settlement_amount
debt_amount_at_referral
```

---

## قوانین Soft Delete

برای داده‌های مهم، حذف فیزیکی مستقیم ممنوع است.

جدول‌های زیر باید Soft Delete داشته باشند:

- customers
- contracts
- installments
- payments
- legal_cases
- files
- chat_threads
- chat_messages
- guarantees
- guarantors
- plugins
- reports_exports

ستون‌های پیشنهادی:

```text
deleted_at
deleted_by
delete_reason
```

قوانین:

- حذف داده مالی باید فقط Soft Delete باشد.
- حذف فیزیکی فقط برای فایل‌های موقت یا خروجی‌های منقضی‌شده مجاز است.
- حذف داده حساس باید Audit Log داشته باشد.
- Queryهای عادی نباید داده‌های deleted را نمایش دهند.

---

## قوانین Metadata

ستون `metadata` برای داده‌های تکمیلی و غیرثابت استفاده می‌شود.

کاربردها:

- ذخیره داده‌های افزونه‌ای
- ذخیره تنظیمات فرعی
- ذخیره Snapshotهای سبک
- ذخیره پاسخ سرویس خارجی
- ذخیره اطلاعات کم‌استفاده

قوانین:

- Metadata نباید جایگزین ستون‌های اصلی شود.
- داده‌ای که زیاد فیلتر یا جستجو می‌شود نباید فقط داخل metadata باشد.
- اطلاعات حساس داخل metadata باید با احتیاط ذخیره شود.
- اگر MySQL/MariaDB از JSON به خوبی پشتیبانی نکرد، می‌توان از LONGTEXT با JSON encoded استفاده کرد.
- ساختار metadata باید تا حد امکان مستند باشد.

---

## قوانین Audit و Log

دیتابیس باید چند نوع Log را پشتیبانی کند:

| نوع Log | کاربرد |
|---|---|
| `audit_logs` | تغییرات حساس و مدیریتی |
| `security_logs` | تلاش‌های غیرمجاز و امنیتی |
| `financial_logs` | تغییرات مالی |
| `system_logs` | خطاها و عملیات سیستمی |
| `activity_logs` | فعالیت‌های عمومی |

قوانین:

- تغییرات حساس باید Audit Log داشته باشند.
- تغییرات مالی باید Financial Log داشته باشند.
- تلاش غیرمجاز باید Security Log داشته باشد.
- Logها نباید اطلاعات حساس خام را بی‌دلیل ذخیره کنند.
- Logها باید قابل فیلتر و گزارش‌گیری باشند.
- Logها نباید باعث کندی شدید سیستم شوند.

---

## قوانین اطلاعات حساس

اطلاعات حساس شامل موارد زیر است:

- کد ملی
- مدارک هویتی
- فایل قرارداد
- فایل حقوقی
- اطلاعات ضمانت
- اطلاعات ضامن
- رسید پرداخت
- کد رهگیری پرداخت
- کلیدهای API
- اطلاعات درگاه
- فایل بکاپ
- تنظیمات حساس پلاگین‌ها

قوانین:

- اطلاعات حساس باید فقط برای نقش مجاز قابل مشاهده باشد.
- فایل‌های حساس باید در Private Storage باشند.
- مسیر واقعی فایل حساس نباید در UI نمایش داده شود.
- تنظیمات حساس باید encrypted یا حداقل masked شوند.
- مشاهده یا دانلود اطلاعات حساس باید Audit Log داشته باشد.
- داده حساس نباید در Error Message خام نمایش داده شود.
- داده حساس نباید در نام فایل خروجی گزارش ذخیره شود.

---

## قوانین Index

Indexها برای عملکرد سیستم ضروری هستند.

ستون‌هایی که معمولاً Index لازم دارند:

- Foreign Keyها
- status
- created_at
- due_date
- paid_at
- customer_id
- contract_id
- installment_id
- payment_id
- assigned_user_id
- phone
- national_code در صورت نیاز و با احتیاط
- contract_number
- payment_number
- legal_case_number

قوانین:

- هر Foreign Key باید Index داشته باشد.
- ستون‌های فیلتر گزارش باید Index مناسب داشته باشند.
- Index زیاد و بی‌دلیل ممنوع است.
- برای گزارش‌های سنگین باید Index ترکیبی طراحی شود.
- Indexها باید در فایل `19_INDEXES_AND_PERFORMANCE.md` مستند شوند.

---

## قوانین Migration

Migrationها باید قابل اجرا، قابل تکرار و امن باشند.

قوانین:

- هر تغییر ساختار دیتابیس باید Migration داشته باشد.
- Migration باید Idempotent باشد.
- Migration اجراشده نباید دوباره اجرا شود.
- Migration نباید داده مالی را بدون Backup تغییر دهد.
- Migrationهای مخرب باید ممنوع یا نیازمند تأیید ویژه باشند.
- نام Migration باید واضح باشد.
- Migration باید در جدول `migrations` ثبت شود.
- Migration پلاگین‌ها باید جدا از Migration Core قابل تشخیص باشند.

نمونه نام Migration:

```text
2026_01_01_000001_create_customers_table
2026_01_01_000002_create_contracts_table
2026_01_01_000003_create_installments_table
```

---

## Migrationهای ممنوع یا خطرناک

موارد زیر ممنوع یا بسیار حساس هستند:

```sql
DROP DATABASE
TRUNCATE
DELETE بدون WHERE
UPDATE بدون WHERE روی جدول حساس
DROP TABLE بدون Backup و تأیید
ALTER TABLE مخرب بدون برنامه مهاجرت داده
```

قانون:

اگر Migration باعث حذف یا تغییر داده مالی، حقوقی، پرداخت یا قرارداد می‌شود، باید قبل از اجرا Backup و Audit داشته باشد.

---

## قوانین Seed Data

Seed Data برای داده‌های اولیه استفاده می‌شود.

نمونه داده‌های اولیه:

- نقش‌ها
- Permissionها
- تنظیمات پایه
- وضعیت‌های سیستمی
- قالب‌های Notification
- تنظیمات پیش‌فرض گزارش
- تنظیمات پیش‌فرض بکاپ

قوانین:

- Seed نباید داده تستی واقعی در نسخه Production بسازد.
- Seed باید قابل تکرار باشد.
- Seed نباید Roleها و Permissionهای موجود را خراب کند.
- Seed باید با نسخه سیستم هماهنگ باشد.
- اطلاعات حساس واقعی نباید داخل Seed باشد.

---

## قوانین Transaction

عملیات حساس باید داخل Transaction انجام شوند.

نمونه عملیات نیازمند Transaction:

- ایجاد قرارداد و اقساط
- ثبت پرداخت و اعمال روی قسط
- تأیید رسید کارت‌به‌کارت
- تسویه قرارداد
- ارجاع حقوقی
- Restore بخشی از دیتابیس
- اجرای Migration حساس
- نصب پلاگین همراه با Migration

قوانین:

- اگر عملیات وسط کار شکست خورد، داده نیمه‌کاره نباید باقی بماند.
- Eventهای حساس بهتر است بعد از Commit اجرا شوند.
- Logهای ضروری خطا باید حتی در شکست عملیات ثبت شوند.
- Transaction نباید خیلی طولانی و سنگین شود.

---

## قوانین Performance

دیتابیس باید برای هاست اشتراکی بهینه باشد.

قوانین:

- Queryهای گزارش باید صفحه‌بندی شوند.
- Exportهای بزرگ باید محدود یا Job شوند.
- Queryهای N+1 ممنوع است.
- ستون‌های فیلترپذیر باید Index داشته باشند.
- جدول‌های Log باید قابلیت پاکسازی یا Archive داشته باشند.
- فایل‌های بزرگ نباید داخل دیتابیس ذخیره شوند.
- فقط مسیر و Metadata فایل در دیتابیس ذخیره شود.
- Cache برای گزارش‌های سنگین در آینده قابل اضافه شدن باشد.

---

## قوانین پلاگین‌ها در دیتابیس

پلاگین‌ها نباید ساختار Core را بی‌قاعده تغییر دهند.

قوانین:

- جدول‌های پلاگین باید Prefix مشخص داشته باشند.
- الگوی پیشنهادی:

```text
plugin_{plugin_id}_{table_name}
```

نمونه:

```text
plugin_sms_provider_logs
plugin_ai_assistant_requests
plugin_accounting_sync_records
```

- پلاگین نباید جدول‌های Core را بدون Contract رسمی تغییر دهد.
- Migration پلاگین باید جدا ثبت شود.
- حذف پلاگین نباید داده Core را خراب کند.
- تنظیمات پلاگین باید در namespace جدا ذخیره شود.

---

## جدول‌های اصلی سیستم

لیست سطح بالا:

| گروه | جدول‌های اصلی |
|---|---|
| Core | settings, migrations, jobs |
| Users | users, roles, permissions, role_permissions, user_roles |
| Customers | customers, customer_documents, customer_notes |
| Contracts | contracts, contract_items, contract_guarantees, contract_guarantors, contract_documents |
| Installments | installments, installment_followups, payment_promises |
| Payments | payments, payment_receipts, payment_gateway_logs |
| Financial | financial_logs, financial_adjustments, settlement_records |
| Legal | legal_cases, legal_case_installments, legal_actions, legal_documents |
| Chat | chat_threads, chat_messages, chat_attachments, chat_participants |
| Notifications | notifications, notification_templates, notification_channels |
| Calendar | calendar_events, reminders |
| Files | files, file_access_logs |
| Reports | report_exports, report_logs |
| Backup & Update | backups, backup_logs, updates, update_logs |
| Plugins | plugins, plugin_settings, plugin_permissions, plugin_migrations |
| Logs | audit_logs, security_logs, system_logs, activity_logs |

---

## ذخیره فایل در دیتابیس

فایل واقعی نباید داخل دیتابیس ذخیره شود.

در دیتابیس فقط موارد زیر ذخیره می‌شود:

- نام فایل
- نام اصلی فایل
- مسیر داخلی امن
- نوع فایل
- MIME Type
- حجم فایل
- مالک فایل
- نوع ارتباط فایل
- وضعیت فایل
- Hash یا checksum در صورت نیاز
- تاریخ ایجاد و حذف

قانون:

فایل‌های حساس باید در Private Storage باشند و فقط از Controller امن دانلود شوند.

---

## ستون‌های شماره رسمی

برای موجودیت‌های مهم باید شماره رسمی جدا از `id` وجود داشته باشد.

نمونه:

| جدول | ستون شماره رسمی |
|---|---|
| contracts | contract_number |
| payments | payment_number |
| backups | backup_number |
| legal_cases | legal_case_number |
| report_exports | export_number |
| invoices در آینده | invoice_number |

قوانین:

- شماره رسمی باید یکتا باشد.
- شماره رسمی باید برای نمایش به کاربر استفاده شود.
- id داخلی نباید به عنوان شماره رسمی استفاده شود.
- ساخت شماره رسمی باید در Service مرکزی انجام شود.

---

## قوانین داده‌های مشتری

اطلاعات مشتری حساس است.

قوانین:

- شماره موبایل باید Index داشته باشد.
- کد ملی در صورت استفاده باید Unique یا کنترل‌شده باشد.
- کد ملی فقط برای نقش مجاز نمایش داده شود.
- مدارک هویتی نباید Public باشند.
- حذف مشتری دارای قرارداد فعال ممنوع است.
- مشتری دارای تاریخچه مالی نباید فیزیکی حذف شود.
- تغییر اطلاعات مهم مشتری باید Audit Log داشته باشد.

---

## قوانین داده‌های مالی

داده مالی باید غیرقابل ابهام باشد.

قوانین:

- پرداخت approved نباید بدون Audit تغییر کند.
- پرداخت rejected نباید در مانده حساب لحاظ شود.
- پرداخت pending_review نباید قطعی محسوب شود.
- تغییر مبلغ قسط باید Financial Log داشته باشد.
- تسویه کامل باید Snapshot داشته باشد.
- مبلغ بدهی هنگام ارجاع حقوقی باید Snapshot شود.
- Frontend منبع حقیقت مبلغ نیست.

---

## قوانین گزارش‌گیری

گزارش‌ها باید از داده معتبر خوانده شوند.

قوانین:

- Query گزارش باید Scope کاربر را رعایت کند.
- گزارش مشتری نباید داده دیگران را نمایش دهد.
- گزارش مالی باید Permission داشته باشد.
- ستون‌های حساس باید Permission جدا داشته باشند.
- Export گزارش حساس باید Audit Log داشته باشد.
- گزارش‌های سنگین باید Pagination داشته باشند.

---

## چک‌لیست طراحی جدول جدید

قبل از ایجاد هر جدول جدید بررسی شود:

- [ ] نام جدول واضح و جمع است.
- [ ] جدول فقط یک مسئولیت دارد.
- [ ] Primary Key دارد.
- [ ] Foreign Keyها با `_id` نام‌گذاری شده‌اند.
- [ ] ستون‌های تاریخ استاندارد هستند.
- [ ] ستون status در صورت نیاز تعریف شده است.
- [ ] Soft Delete در صورت حساس بودن وجود دارد.
- [ ] Indexهای ضروری مشخص شده‌اند.
- [ ] داده حساس مشخص شده است.
- [ ] Audit یا Financial Log در صورت نیاز مشخص شده است.
- [ ] Metadata فقط برای داده‌های تکمیلی استفاده شده است.
- [ ] جدول با گزارش‌گیری آینده سازگار است.
- [ ] Migration امن و قابل تکرار دارد.

---

## Definition of Done

بخش دیتابیس زمانی درست طراحی شده است که:

- همه جدول‌های اصلی مستند باشند.
- نام‌گذاری جدول‌ها و ستون‌ها یکدست باشد.
- داده‌های مالی با DECIMAL ذخیره شوند.
- اطلاعات حساس محافظت شوند.
- Soft Delete برای داده‌های مهم رعایت شود.
- Migrationها امن و قابل اجرا باشند.
- Indexهای ضروری تعریف شده باشند.
- Queryهای گزارش‌گیری قابل اجرا و بهینه باشند.
- Log، Audit، Security و Financial Log پشتیبانی شوند.
- پلاگین‌ها بتوانند بدون آسیب به Core جدول‌های خود را اضافه کنند.
- Codex بتواند از روی این مستندات ساختار دیتابیس را بسازد.

---

## پایان فایل