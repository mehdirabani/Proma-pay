پوشه:

`docs/database/`

نام فایل:

`03_CUSTOMERS_TABLES.md`

مسیر کامل فایل:

`docs/database/03_CUSTOMERS_TABLES.md`

````markdown
# 03 — Customers Tables

مستند جدول‌های مشتریان، اطلاعات هویتی، مدارک، یادداشت‌ها، آدرس‌ها، اعتبارسنجی و داده‌های وابسته به Customer Domain در پروژه **Proma Pay**

---

## فهرست مطالب

- [هدف فایل](#هدف-فایل)
- [تعریف Customer Domain در دیتابیس](#تعریف-customer-domain-در-دیتابیس)
- [اصل مهم](#اصل-مهم)
- [لیست جدول‌های Customers](#لیست-جدولهای-customers)
- [جدول customers](#جدول-customers)
- [جدول customer_documents](#جدول-customer_documents)
- [جدول customer_notes](#جدول-customer_notes)
- [جدول customer_addresses](#جدول-customer_addresses)
- [جدول customer_contacts](#جدول-customer_contacts)
- [جدول customer_credit_profiles](#جدول-customer_credit_profiles)
- [جدول customer_verifications](#جدول-customer_verifications)
- [جدول customer_status_histories](#جدول-customer_status_histories)
- [رابطه مشتریان با سایر Domainها](#رابطه-مشتریان-با-سایر-domainها)
- [قوانین اطلاعات حساس مشتری](#قوانین-اطلاعات-حساس-مشتری)
- [قوانین Soft Delete](#قوانین-soft-delete)
- [قوانین Index و Performance](#قوانین-index-و-performance)
- [قوانین Validation](#قوانین-validation)
- [قوانین Audit و Security Log](#قوانین-audit-و-security-log)
- [Seedهای پیشنهادی](#seedهای-پیشنهادی)
- [چک‌لیست پیاده‌سازی](#چکلیست-پیادهسازی)
- [Definition of Done](#definition-of-done)

---

## هدف فایل

هدف این فایل این است که ساختار جدول‌های مربوط به مشتریان در پروژه **Proma Pay** مشخص شود.

Customer Domain یکی از مهم‌ترین بخش‌های سیستم است؛ چون قرارداد، قسط، پرداخت، ضمانت، پیگیری، پرونده حقوقی و گزارش‌ها همگی به مشتری وابسته هستند.

این فایل برای Codex مشخص می‌کند که:

- اطلاعات اصلی مشتری کجا ذخیره شود.
- مدارک مشتری چگونه نگهداری شود.
- یادداشت‌های داخلی مشتری چگونه ثبت شود.
- آدرس‌ها و راه‌های ارتباطی مشتری چگونه مدیریت شود.
- وضعیت اعتباری مشتری چگونه ثبت شود.
- احراز هویت مشتری چگونه ذخیره شود.
- داده حساس مشتری چگونه محافظت شود.
- چه Indexهایی برای جستجو و گزارش لازم است.
- چه عملیات‌هایی نیاز به Audit Log یا Security Log دارند.

---

## تعریف Customer Domain در دیتابیس

Customer Domain شامل اطلاعات مربوط به اشخاصی است که از سیستم استفاده می‌کنند یا برای آن‌ها قرارداد اقساطی ثبت می‌شود.

مشتری می‌تواند:

- خریدار نقدی باشد.
- خریدار اقساطی باشد.
- دارای قرارداد فعال باشد.
- دارای قسط معوق باشد.
- دارای پرونده حقوقی باشد.
- دارای مدارک احراز هویت باشد.
- دارای وضعیت اعتباری مشخص باشد.
- دارای یادداشت داخلی باشد.
- دارای آدرس و اطلاعات تماس تکمیلی باشد.

---

## اصل مهم

اصل مهم در Customer Tables:

> اطلاعات مشتری حساس است و نباید بدون Permission، Scope و Audit مناسب نمایش داده یا خروجی گرفته شود.

به خصوص موارد زیر حساس محسوب می‌شوند:

- کد ملی
- شماره موبایل
- تصویر کارت ملی
- مدارک شغلی
- فیش حقوقی
- آدرس
- اطلاعات ضامن یا مخاطب اضطراری
- یادداشت داخلی
- وضعیت اعتباری
- سوابق حقوقی
- مدارک قرارداد

---

## لیست جدول‌های Customers

جدول‌های پیشنهادی Customer Domain:

| جدول | کاربرد |
|---|---|
| `customers` | اطلاعات اصلی مشتری |
| `customer_documents` | مدارک و فایل‌های مشتری |
| `customer_notes` | یادداشت‌های داخلی مشتری |
| `customer_addresses` | آدرس‌های مشتری |
| `customer_contacts` | راه‌های ارتباطی و مخاطبین مرتبط |
| `customer_credit_profiles` | وضعیت اعتباری مشتری |
| `customer_verifications` | سوابق احراز هویت مشتری |
| `customer_status_histories` | تاریخچه تغییر وضعیت مشتری |

---

## جدول customers

### هدف جدول

جدول `customers` اطلاعات اصلی و پایه مشتری را نگهداری می‌کند.

این جدول منبع اصلی شناسایی مشتری در سیستم است.

---

### نام جدول

```text
customers
```

---

### ستون‌های پیشنهادی

| ستون | نوع | توضیح |
|---|---|---|
| `id` | BIGINT UNSIGNED AUTO_INCREMENT | شناسه داخلی |
| `customer_number` | VARCHAR(50) | شماره رسمی مشتری |
| `first_name` | VARCHAR(100) | نام |
| `last_name` | VARCHAR(100) | نام خانوادگی |
| `full_name` | VARCHAR(191) NULL | نام کامل برای جستجوی سریع |
| `father_name` | VARCHAR(100) NULL | نام پدر |
| `national_code` | VARCHAR(20) NULL | کد ملی |
| `birth_date` | DATE NULL | تاریخ تولد میلادی |
| `gender` | VARCHAR(20) NULL | جنسیت |
| `phone` | VARCHAR(30) | شماره موبایل اصلی |
| `secondary_phone` | VARCHAR(30) NULL | شماره موبایل دوم |
| `email` | VARCHAR(191) NULL | ایمیل |
| `customer_type` | VARCHAR(50) | نوع مشتری |
| `verification_status` | VARCHAR(50) | وضعیت احراز هویت |
| `credit_status` | VARCHAR(50) | وضعیت اعتباری |
| `status` | VARCHAR(50) | وضعیت کلی مشتری |
| `is_blacklisted` | TINYINT(1) | آیا مشتری در لیست سیاه است؟ |
| `blacklist_reason` | TEXT NULL | دلیل لیست سیاه |
| `registered_by` | BIGINT UNSIGNED NULL | کاربر ثبت‌کننده |
| `assigned_user_id` | BIGINT UNSIGNED NULL | کاربر مسئول مشتری |
| `last_activity_at` | DATETIME NULL | آخرین فعالیت |
| `metadata` | JSON NULL | اطلاعات تکمیلی |
| `created_at` | DATETIME NULL | زمان ایجاد |
| `updated_at` | DATETIME NULL | زمان بروزرسانی |
| `deleted_at` | DATETIME NULL | زمان حذف نرم |
| `created_by` | BIGINT UNSIGNED NULL | ایجادکننده |
| `updated_by` | BIGINT UNSIGNED NULL | ویرایش‌کننده |
| `deleted_by` | BIGINT UNSIGNED NULL | حذف‌کننده |

---

### customer_typeهای پیشنهادی

```text
normal
installment
retired
employee
business
vip
```

---

### verification_statusهای پیشنهادی

```text
not_verified
pending
verified
rejected
expired
```

---

### credit_statusهای پیشنهادی

```text
unknown
good
medium
risky
blocked
blacklisted
```

---

### statusهای پیشنهادی

```text
active
inactive
blocked
blacklisted
deleted
```

---

### قوانین

- `phone` باید برای مشتری اصلی الزامی باشد.
- `customer_number` باید Unique باشد.
- `national_code` در صورت ثبت باید معتبر و کنترل‌شده باشد.
- `national_code` می‌تواند Unique باشد، اما باید با Policy پروژه هماهنگ شود.
- مشتری دارای قرارداد فعال نباید حذف فیزیکی شود.
- مشتری دارای سابقه مالی نباید از دیتابیس پاک شود.
- تغییر کد ملی، شماره موبایل، وضعیت اعتباری یا Blacklist باید Audit Log داشته باشد.
- اطلاعات مشتری فقط با Permission و Scope مجاز نمایش داده شود.
- مشتری حذف‌شده با `deleted_at` از Queryهای عادی حذف شود.

---

### Indexهای پیشنهادی

```text
uniq_customers_customer_number
idx_customers_phone
idx_customers_national_code
idx_customers_full_name
idx_customers_status
idx_customers_verification_status
idx_customers_credit_status
idx_customers_assigned_user_id
idx_customers_created_at
idx_customers_deleted_at
```

---

### نمونه ساختار SQL

```sql
CREATE TABLE customers (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    customer_number VARCHAR(50) NOT NULL,
    first_name VARCHAR(100) NOT NULL,
    last_name VARCHAR(100) NOT NULL,
    full_name VARCHAR(191) NULL,
    father_name VARCHAR(100) NULL,
    national_code VARCHAR(20) NULL,
    birth_date DATE NULL,
    gender VARCHAR(20) NULL,
    phone VARCHAR(30) NOT NULL,
    secondary_phone VARCHAR(30) NULL,
    email VARCHAR(191) NULL,
    customer_type VARCHAR(50) NOT NULL DEFAULT 'normal',
    verification_status VARCHAR(50) NOT NULL DEFAULT 'not_verified',
    credit_status VARCHAR(50) NOT NULL DEFAULT 'unknown',
    status VARCHAR(50) NOT NULL DEFAULT 'active',
    is_blacklisted TINYINT(1) NOT NULL DEFAULT 0,
    blacklist_reason TEXT NULL,
    registered_by BIGINT UNSIGNED NULL,
    assigned_user_id BIGINT UNSIGNED NULL,
    last_activity_at DATETIME NULL,
    metadata JSON NULL,
    created_at DATETIME NULL,
    updated_at DATETIME NULL,
    deleted_at DATETIME NULL,
    created_by BIGINT UNSIGNED NULL,
    updated_by BIGINT UNSIGNED NULL,
    deleted_by BIGINT UNSIGNED NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uniq_customers_customer_number (customer_number),
    KEY idx_customers_phone (phone),
    KEY idx_customers_national_code (national_code),
    KEY idx_customers_full_name (full_name),
    KEY idx_customers_status (status),
    KEY idx_customers_verification_status (verification_status),
    KEY idx_customers_credit_status (credit_status),
    KEY idx_customers_assigned_user_id (assigned_user_id),
    KEY idx_customers_created_at (created_at),
    KEY idx_customers_deleted_at (deleted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

---

## جدول customer_documents

### هدف جدول

جدول `customer_documents` برای ثبت مدارک مشتری استفاده می‌شود.

فایل واقعی مدرک نباید داخل این جدول ذخیره شود.  
فایل باید در Files Domain و Private Storage ذخیره شود و این جدول فقط ارتباط مشتری با فایل و نوع مدرک را نگهداری کند.

---

### نام جدول

```text
customer_documents
```

---

### ستون‌های پیشنهادی

| ستون | نوع | توضیح |
|---|---|---|
| `id` | BIGINT UNSIGNED AUTO_INCREMENT | شناسه |
| `customer_id` | BIGINT UNSIGNED | مشتری |
| `file_id` | BIGINT UNSIGNED | فایل ثبت‌شده در Files Domain |
| `document_type` | VARCHAR(50) | نوع مدرک |
| `document_number` | VARCHAR(100) NULL | شماره مدرک در صورت نیاز |
| `title` | VARCHAR(191) NULL | عنوان نمایشی |
| `description` | TEXT NULL | توضیحات |
| `status` | VARCHAR(50) | وضعیت مدرک |
| `reviewed_by` | BIGINT UNSIGNED NULL | بررسی‌کننده |
| `reviewed_at` | DATETIME NULL | زمان بررسی |
| `rejection_reason` | TEXT NULL | دلیل رد |
| `expires_at` | DATETIME NULL | تاریخ انقضا |
| `metadata` | JSON NULL | داده تکمیلی |
| `created_at` | DATETIME NULL | زمان ایجاد |
| `updated_at` | DATETIME NULL | زمان بروزرسانی |
| `deleted_at` | DATETIME NULL | زمان حذف نرم |
| `created_by` | BIGINT UNSIGNED NULL | ایجادکننده |
| `updated_by` | BIGINT UNSIGNED NULL | ویرایش‌کننده |
| `deleted_by` | BIGINT UNSIGNED NULL | حذف‌کننده |

---

### document_typeهای پیشنهادی

```text
national_card_front
national_card_back
birth_certificate
payslip
employment_order
retirement_certificate
bank_statement
check_image
promissory_note
contract_attachment
other
```

---

### statusهای پیشنهادی

```text
pending
approved
rejected
expired
deleted
```

---

### قوانین

- هر مدرک باید به یک customer_id وصل باشد.
- هر مدرک باید file_id معتبر داشته باشد.
- فایل مدرک باید Private باشد.
- مشاهده مدرک باید Permission داشته باشد.
- دانلود مدرک باید Audit Log داشته باشد.
- رد یا تأیید مدرک باید reviewed_by و reviewed_at داشته باشد.
- مدارک ردشده نباید برای احراز هویت معتبر محسوب شوند.
- مدارک منقضی‌شده باید در Verification اثر داشته باشند.
- حذف مدرک باید Soft Delete باشد.

---

### Indexهای پیشنهادی

```text
idx_customer_documents_customer_id
idx_customer_documents_file_id
idx_customer_documents_document_type
idx_customer_documents_status
idx_customer_documents_reviewed_by
idx_customer_documents_expires_at
idx_customer_documents_created_at
```

---

## جدول customer_notes

### هدف جدول

جدول `customer_notes` برای ثبت یادداشت‌های داخلی درباره مشتری استفاده می‌شود.

یادداشت‌ها می‌توانند برای پیگیری، اعتبارسنجی، فروش، پشتیبانی یا مسائل حقوقی استفاده شوند.

---

### نام جدول

```text
customer_notes
```

---

### ستون‌های پیشنهادی

| ستون | نوع | توضیح |
|---|---|---|
| `id` | BIGINT UNSIGNED AUTO_INCREMENT | شناسه |
| `customer_id` | BIGINT UNSIGNED | مشتری |
| `note_type` | VARCHAR(50) | نوع یادداشت |
| `title` | VARCHAR(191) NULL | عنوان |
| `body` | TEXT | متن یادداشت |
| `visibility` | VARCHAR(50) | سطح نمایش |
| `priority` | VARCHAR(50) | اولویت |
| `is_pinned` | TINYINT(1) | سنجاق شده |
| `related_type` | VARCHAR(100) NULL | نوع موجودیت مرتبط |
| `related_id` | BIGINT UNSIGNED NULL | شناسه موجودیت مرتبط |
| `created_by` | BIGINT UNSIGNED NULL | نویسنده |
| `updated_by` | BIGINT UNSIGNED NULL | ویرایش‌کننده |
| `deleted_by` | BIGINT UNSIGNED NULL | حذف‌کننده |
| `created_at` | DATETIME NULL | زمان ایجاد |
| `updated_at` | DATETIME NULL | زمان بروزرسانی |
| `deleted_at` | DATETIME NULL | زمان حذف نرم |

---

### note_typeهای پیشنهادی

```text
general
sales
support
credit
legal
payment_followup
risk
internal
```

---

### visibilityهای پیشنهادی

```text
internal
manager_only
legal_only
accounting_only
```

---

### priorityهای پیشنهادی

```text
low
normal
high
critical
```

---

### قوانین

- یادداشت مشتری برای Customer قابل نمایش نیست.
- یادداشت‌های داخلی باید Permission داشته باشند.
- یادداشت حقوقی فقط برای نقش مجاز نمایش داده شود.
- یادداشت‌های critical می‌توانند در صفحه مشتری Badge ایجاد کنند.
- حذف یادداشت باید Soft Delete باشد.
- ویرایش یا حذف یادداشت حساس باید Audit Log داشته باشد.
- اگر یادداشت به قرارداد، قسط یا پرونده حقوقی مربوط است، related_type و related_id ثبت شود.

---

### Indexهای پیشنهادی

```text
idx_customer_notes_customer_id
idx_customer_notes_note_type
idx_customer_notes_visibility
idx_customer_notes_priority
idx_customer_notes_is_pinned
idx_customer_notes_related
idx_customer_notes_created_by
idx_customer_notes_created_at
```

---

## جدول customer_addresses

### هدف جدول

جدول `customer_addresses` برای ذخیره آدرس‌های مشتری استفاده می‌شود.

مشتری ممکن است چند آدرس داشته باشد:

- محل سکونت
- محل کار
- آدرس ارسال کالا
- آدرس حقوقی یا ثبتی

---

### نام جدول

```text
customer_addresses
```

---

### ستون‌های پیشنهادی

| ستون | نوع | توضیح |
|---|---|---|
| `id` | BIGINT UNSIGNED AUTO_INCREMENT | شناسه |
| `customer_id` | BIGINT UNSIGNED | مشتری |
| `address_type` | VARCHAR(50) | نوع آدرس |
| `province` | VARCHAR(100) NULL | استان |
| `city` | VARCHAR(100) NULL | شهر |
| `district` | VARCHAR(100) NULL | منطقه |
| `postal_code` | VARCHAR(20) NULL | کد پستی |
| `address_line` | TEXT | متن کامل آدرس |
| `latitude` | DECIMAL(10,7) NULL | عرض جغرافیایی |
| `longitude` | DECIMAL(10,7) NULL | طول جغرافیایی |
| `is_default` | TINYINT(1) | آدرس پیش‌فرض |
| `status` | VARCHAR(50) | وضعیت |
| `verified_at` | DATETIME NULL | زمان تأیید |
| `verified_by` | BIGINT UNSIGNED NULL | تأییدکننده |
| `created_at` | DATETIME NULL | زمان ایجاد |
| `updated_at` | DATETIME NULL | زمان بروزرسانی |
| `deleted_at` | DATETIME NULL | زمان حذف نرم |
| `created_by` | BIGINT UNSIGNED NULL | ایجادکننده |
| `updated_by` | BIGINT UNSIGNED NULL | ویرایش‌کننده |
| `deleted_by` | BIGINT UNSIGNED NULL | حذف‌کننده |

---

### address_typeهای پیشنهادی

```text
home
work
shipping
legal
other
```

---

### statusهای پیشنهادی

```text
active
inactive
verified
rejected
deleted
```

---

### قوانین

- هر آدرس باید customer_id داشته باشد.
- هر مشتری فقط یک آدرس پیش‌فرض فعال داشته باشد.
- آدرس مشتری حساس است و باید Permission داشته باشد.
- آدرس نباید در Export عمومی بدون Permission بیاید.
- تغییر آدرس مشتری دارای قرارداد فعال باید Audit Log داشته باشد.
- حذف آدرس باید Soft Delete باشد.

---

### Indexهای پیشنهادی

```text
idx_customer_addresses_customer_id
idx_customer_addresses_address_type
idx_customer_addresses_city
idx_customer_addresses_postal_code
idx_customer_addresses_is_default
idx_customer_addresses_status
idx_customer_addresses_deleted_at
```

---

## جدول customer_contacts

### هدف جدول

جدول `customer_contacts` برای نگهداری راه‌های ارتباطی تکمیلی یا مخاطبین مرتبط با مشتری استفاده می‌شود.

این جدول می‌تواند برای شماره‌های اضافه، مخاطب اضطراری، معرف یا شخص مرتبط استفاده شود.

---

### نام جدول

```text
customer_contacts
```

---

### ستون‌های پیشنهادی

| ستون | نوع | توضیح |
|---|---|---|
| `id` | BIGINT UNSIGNED AUTO_INCREMENT | شناسه |
| `customer_id` | BIGINT UNSIGNED | مشتری |
| `contact_type` | VARCHAR(50) | نوع ارتباط |
| `relationship` | VARCHAR(100) NULL | نسبت با مشتری |
| `name` | VARCHAR(191) NULL | نام شخص |
| `phone` | VARCHAR(30) NULL | شماره تماس |
| `email` | VARCHAR(191) NULL | ایمیل |
| `description` | TEXT NULL | توضیحات |
| `is_primary` | TINYINT(1) | مخاطب اصلی |
| `is_verified` | TINYINT(1) | تأیید شده |
| `verified_at` | DATETIME NULL | زمان تأیید |
| `status` | VARCHAR(50) | وضعیت |
| `created_at` | DATETIME NULL | زمان ایجاد |
| `updated_at` | DATETIME NULL | زمان بروزرسانی |
| `deleted_at` | DATETIME NULL | زمان حذف نرم |
| `created_by` | BIGINT UNSIGNED NULL | ایجادکننده |
| `updated_by` | BIGINT UNSIGNED NULL | ویرایش‌کننده |
| `deleted_by` | BIGINT UNSIGNED NULL | حذف‌کننده |

---

### contact_typeهای پیشنهادی

```text
secondary_phone
emergency_contact
reference
work_contact
family
other
```

---

### statusهای پیشنهادی

```text
active
inactive
invalid
deleted
```

---

### قوانین

- مخاطب اضطراری نباید با ضامن رسمی اشتباه گرفته شود.
- ضامن رسمی باید در Contract Domain ثبت شود.
- شماره‌های تماس باید Scope و Permission داشته باشند.
- استفاده از مخاطبین برای پیگیری باید طبق قوانین داخلی و حقوقی کنترل شود.
- حذف مخاطب باید Soft Delete باشد.
- تغییر مخاطب مهم باید Audit Log داشته باشد.

---

### Indexهای پیشنهادی

```text
idx_customer_contacts_customer_id
idx_customer_contacts_contact_type
idx_customer_contacts_phone
idx_customer_contacts_is_primary
idx_customer_contacts_is_verified
idx_customer_contacts_status
```

---

## جدول customer_credit_profiles

### هدف جدول

جدول `customer_credit_profiles` برای نگهداری وضعیت اعتباری مشتری استفاده می‌شود.

این جدول برای تصمیم‌گیری درباره فروش اقساطی، سقف اعتبار، ریسک مشتری و شرایط قرارداد کاربرد دارد.

---

### نام جدول

```text
customer_credit_profiles
```

---

### ستون‌های پیشنهادی

| ستون | نوع | توضیح |
|---|---|---|
| `id` | BIGINT UNSIGNED AUTO_INCREMENT | شناسه |
| `customer_id` | BIGINT UNSIGNED | مشتری |
| `credit_score` | INT NULL | امتیاز اعتباری داخلی |
| `credit_level` | VARCHAR(50) | سطح اعتباری |
| `risk_level` | VARCHAR(50) | سطح ریسک |
| `max_credit_amount` | DECIMAL(15,2) NULL | سقف اعتبار |
| `allowed_installment_months` | INT UNSIGNED NULL | حداکثر ماه‌های مجاز |
| `requires_guarantee` | TINYINT(1) | نیاز به ضمانت |
| `requires_down_payment` | TINYINT(1) | نیاز به پیش‌پرداخت |
| `down_payment_percent` | DECIMAL(5,2) NULL | درصد پیش‌پرداخت پیشنهادی |
| `last_reviewed_at` | DATETIME NULL | آخرین بررسی |
| `reviewed_by` | BIGINT UNSIGNED NULL | بررسی‌کننده |
| `review_note` | TEXT NULL | توضیح بررسی |
| `status` | VARCHAR(50) | وضعیت |
| `metadata` | JSON NULL | داده تکمیلی |
| `created_at` | DATETIME NULL | زمان ایجاد |
| `updated_at` | DATETIME NULL | زمان بروزرسانی |

---

### credit_levelهای پیشنهادی

```text
unknown
bronze
silver
gold
vip
blocked
```

---

### risk_levelهای پیشنهادی

```text
unknown
low
medium
high
critical
```

---

### statusهای پیشنهادی

```text
active
pending_review
approved
rejected
blocked
expired
```

---

### قوانین

- هر مشتری بهتر است فقط یک Credit Profile فعال داشته باشد.
- تغییر سقف اعتبار باید Audit Log داشته باشد.
- تغییر سطح ریسک باید Audit Log داشته باشد.
- تصمیم نهایی فروش اقساطی نباید فقط از Frontend بیاید.
- سقف اعتبار باید با Policy اقساط هماهنگ باشد.
- اطلاعات اعتباری برای مشتری نباید بدون طراحی مشخص نمایش داده شود.
- Credit Profile نباید جایگزین محاسبات قرارداد شود.
- مبلغ‌ها باید DECIMAL باشند.

---

### Indexهای پیشنهادی

```text
uniq_customer_credit_profiles_customer_id
idx_customer_credit_profiles_credit_level
idx_customer_credit_profiles_risk_level
idx_customer_credit_profiles_status
idx_customer_credit_profiles_reviewed_by
idx_customer_credit_profiles_last_reviewed_at
```

---

## جدول customer_verifications

### هدف جدول

جدول `customer_verifications` برای ثبت فرآیندها و سوابق احراز هویت مشتری استفاده می‌شود.

هر بار که مدارک مشتری بررسی می‌شود یا وضعیت احراز هویت تغییر می‌کند، می‌توان یک رکورد در این جدول ثبت کرد.

---

### نام جدول

```text
customer_verifications
```

---

### ستون‌های پیشنهادی

| ستون | نوع | توضیح |
|---|---|---|
| `id` | BIGINT UNSIGNED AUTO_INCREMENT | شناسه |
| `customer_id` | BIGINT UNSIGNED | مشتری |
| `verification_type` | VARCHAR(50) | نوع احراز |
| `status` | VARCHAR(50) | وضعیت |
| `requested_at` | DATETIME NULL | زمان درخواست |
| `reviewed_at` | DATETIME NULL | زمان بررسی |
| `reviewed_by` | BIGINT UNSIGNED NULL | بررسی‌کننده |
| `approved_at` | DATETIME NULL | زمان تأیید |
| `rejected_at` | DATETIME NULL | زمان رد |
| `rejection_reason` | TEXT NULL | دلیل رد |
| `expires_at` | DATETIME NULL | زمان انقضا |
| `metadata` | JSON NULL | داده تکمیلی |
| `created_at` | DATETIME NULL | زمان ایجاد |
| `updated_at` | DATETIME NULL | زمان بروزرسانی |

---

### verification_typeهای پیشنهادی

```text
identity
phone
documents
employment
retirement
manual
```

---

### statusهای پیشنهادی

```text
pending
approved
rejected
expired
cancelled
```

---

### قوانین

- احراز هویت approved می‌تواند وضعیت `verification_status` مشتری را تغییر دهد.
- رد احراز هویت باید دلیل داشته باشد.
- احراز هویت دستی باید reviewed_by داشته باشد.
- احراز هویت مدارک باید به customer_documents وابسته باشد.
- تغییر وضعیت احراز هویت باید Audit Log داشته باشد.
- مدارک منقضی‌شده باید باعث بررسی مجدد شوند.

---

### Indexهای پیشنهادی

```text
idx_customer_verifications_customer_id
idx_customer_verifications_verification_type
idx_customer_verifications_status
idx_customer_verifications_reviewed_by
idx_customer_verifications_requested_at
idx_customer_verifications_expires_at
```

---

## جدول customer_status_histories

### هدف جدول

جدول `customer_status_histories` برای ثبت تاریخچه تغییر وضعیت مشتری استفاده می‌شود.

این جدول کمک می‌کند تغییرات وضعیت مشتری قابل ردیابی باشد.

---

### نام جدول

```text
customer_status_histories
```

---

### ستون‌های پیشنهادی

| ستون | نوع | توضیح |
|---|---|---|
| `id` | BIGINT UNSIGNED AUTO_INCREMENT | شناسه |
| `customer_id` | BIGINT UNSIGNED | مشتری |
| `old_status` | VARCHAR(50) NULL | وضعیت قبلی |
| `new_status` | VARCHAR(50) | وضعیت جدید |
| `old_credit_status` | VARCHAR(50) NULL | وضعیت اعتباری قبلی |
| `new_credit_status` | VARCHAR(50) NULL | وضعیت اعتباری جدید |
| `reason` | TEXT NULL | دلیل تغییر |
| `changed_by` | BIGINT UNSIGNED NULL | تغییر دهنده |
| `changed_at` | DATETIME | زمان تغییر |
| `metadata` | JSON NULL | داده تکمیلی |

---

### قوانین

- هر تغییر مهم در status یا credit_status باید ثبت شود.
- Blacklist شدن مشتری باید حتماً در این جدول ثبت شود.
- خروج از Blacklist هم باید ثبت شود.
- reason برای تغییرات حساس الزامی باشد.
- این جدول نباید Soft Delete شود.
- فقط کاربران مجاز بتوانند این تاریخچه را ببینند.

---

### Indexهای پیشنهادی

```text
idx_customer_status_histories_customer_id
idx_customer_status_histories_new_status
idx_customer_status_histories_new_credit_status
idx_customer_status_histories_changed_by
idx_customer_status_histories_changed_at
```

---

## رابطه مشتریان با سایر Domainها

مشتری با بخش‌های زیادی از سیستم ارتباط دارد.

| Domain | رابطه |
|---|---|
| Contracts | هر قرارداد متعلق به یک مشتری است |
| Installments | اقساط از طریق قرارداد به مشتری وصل می‌شوند |
| Payments | پرداخت‌ها از طریق قرارداد یا قسط به مشتری مرتبط می‌شوند |
| Legal | پرونده حقوقی برای مشتری و قرارداد ساخته می‌شود |
| Files | مدارک مشتری در Files Domain ذخیره می‌شوند |
| Chat | گفتگوها می‌توانند به مشتری مرتبط باشند |
| Calendar | یادآوری‌ها و پیگیری‌ها می‌توانند برای مشتری باشند |
| Reports | گزارش مشتری، قرارداد، اقساط و معوقات از مشتری استفاده می‌کند |
| Audit | تغییرات حساس مشتری ثبت می‌شود |
| Security | تلاش غیرمجاز برای مشاهده اطلاعات مشتری ثبت می‌شود |

---

## قوانین اطلاعات حساس مشتری

اطلاعات زیر حساس هستند:

- کد ملی
- تاریخ تولد
- شماره موبایل
- آدرس
- مدارک هویتی
- مدارک شغلی
- فیش حقوقی
- اطلاعات مخاطب اضطراری
- وضعیت اعتباری
- یادداشت داخلی
- سوابق حقوقی

قوانین:

- نمایش اطلاعات حساس باید Permission داشته باشد.
- Export اطلاعات حساس باید Audit Log داشته باشد.
- دانلود مدارک مشتری باید Audit Log داشته باشد.
- مشتری نباید اطلاعات مشتری دیگر را ببیند.
- اپراتور نباید خارج از Scope اطلاعات مشتری ببیند.
- اطلاعات حساس نباید در Error Message خام نمایش داده شود.
- اطلاعات حساس نباید در نام فایل خروجی گزارش ذخیره شود.

---

## قوانین Soft Delete

جدول‌های زیر باید Soft Delete داشته باشند:

- `customers`
- `customer_documents`
- `customer_notes`
- `customer_addresses`
- `customer_contacts`

قوانین:

- مشتری دارای قرارداد فعال نباید حذف شود.
- مشتری دارای تاریخچه مالی نباید حذف فیزیکی شود.
- حذف مشتری باید فقط Soft Delete باشد.
- حذف مدرک باید فایل را طبق Files Policy مدیریت کند.
- حذف یادداشت حساس باید Audit Log داشته باشد.
- Queryهای عادی نباید رکوردهای deleted را نشان دهند.

---

## قوانین Index و Performance

قوانین:

- جستجوی مشتری با شماره موبایل باید سریع باشد.
- جستجوی مشتری با نام کامل باید قابل قبول باشد.
- فیلتر بر اساس وضعیت اعتباری باید Index داشته باشد.
- فیلتر مشتریان حذف‌نشده باید بهینه باشد.
- گزارش مشتریان باید Pagination داشته باشد.
- Export مشتریان باید محدود و Permission-based باشد.
- Index زیاد و بی‌دلیل نباید ساخته شود.

Indexهای مهم:

```text
customers.phone
customers.national_code
customers.full_name
customers.status
customers.credit_status
customers.assigned_user_id
customer_documents.customer_id
customer_notes.customer_id
customer_addresses.customer_id
customer_contacts.customer_id
customer_credit_profiles.customer_id
```

---

## قوانین Validation

### customers

- first_name الزامی است.
- last_name الزامی است.
- phone الزامی است.
- phone باید فرمت معتبر داشته باشد.
- national_code در صورت ثبت باید معتبر باشد.
- email در صورت ثبت باید معتبر باشد.
- status باید از مقدارهای مجاز باشد.
- credit_status باید از مقدارهای مجاز باشد.

### customer_documents

- customer_id الزامی است.
- file_id الزامی است.
- document_type الزامی است.
- status باید از مقدارهای مجاز باشد.
- rejected باید rejection_reason داشته باشد.

### customer_notes

- customer_id الزامی است.
- body الزامی است.
- visibility باید معتبر باشد.
- note_type باید معتبر باشد.

### customer_addresses

- customer_id الزامی است.
- address_line الزامی است.
- address_type باید معتبر باشد.
- فقط یک آدرس پیش‌فرض فعال مجاز است.

### customer_credit_profiles

- customer_id الزامی است.
- max_credit_amount باید DECIMAL و غیرمنفی باشد.
- down_payment_percent باید بین 0 تا 100 باشد.
- risk_level باید معتبر باشد.

---

## قوانین Audit و Security Log

### Audit Log الزامی برای:

- ایجاد مشتری جدید توسط کاربر داخلی
- ویرایش شماره موبایل
- ویرایش کد ملی
- تغییر وضعیت مشتری
- تغییر وضعیت اعتباری
- Blacklist کردن مشتری
- خروج مشتری از Blacklist
- تأیید یا رد مدارک
- دانلود مدرک مشتری
- تغییر سقف اعتبار
- حذف نرم مشتری
- حذف یادداشت حساس
- Export اطلاعات مشتریان

### Security Log الزامی برای:

- تلاش مشاهده مشتری بدون Permission
- تلاش مشاهده مشتری خارج از Scope
- تلاش دانلود مدرک مشتری بدون Permission
- تلاش Export اطلاعات حساس بدون Permission
- تلاش تغییر وضعیت مشتری بدون Permission
- CSRF نامعتبر در عملیات مشتری
- دستکاری customer_id در درخواست‌ها

---

## Seedهای پیشنهادی

Customer Domain معمولاً داده تستی در Production نباید Seed کند.

اما مقدارهای Enum یا تنظیمات پایه می‌توانند Seed شوند.

### customer_type

```text
normal
installment
retired
employee
business
vip
```

### verification_status

```text
not_verified
pending
verified
rejected
expired
```

### credit_status

```text
unknown
good
medium
risky
blocked
blacklisted
```

### document_type

```text
national_card_front
national_card_back
birth_certificate
payslip
employment_order
retirement_certificate
bank_statement
check_image
promissory_note
contract_attachment
other
```

---

## چک‌لیست پیاده‌سازی

قبل از پیاده‌سازی Customer Tables بررسی شود:

- [ ] جدول `customers` ساخته شده است.
- [ ] `customer_number` یکتا است.
- [ ] `phone` Index دارد.
- [ ] `national_code` کنترل و Index شده است.
- [ ] Soft Delete برای مشتری فعال است.
- [ ] جدول `customer_documents` وجود دارد.
- [ ] فایل مدارک از طریق Files Domain مدیریت می‌شود.
- [ ] مدارک مشتری در Private Storage هستند.
- [ ] جدول `customer_notes` وجود دارد.
- [ ] یادداشت داخلی برای مشتری نمایش داده نمی‌شود.
- [ ] جدول `customer_addresses` وجود دارد.
- [ ] جدول `customer_contacts` وجود دارد.
- [ ] جدول `customer_credit_profiles` وجود دارد.
- [ ] جدول `customer_verifications` وجود دارد.
- [ ] جدول `customer_status_histories` وجود دارد.
- [ ] تغییرات حساس Audit Log دارند.
- [ ] تلاش‌های غیرمجاز Security Log دارند.
- [ ] Queryهای Customer Scope را رعایت می‌کنند.
- [ ] Export اطلاعات مشتری Permission-based است.

---

## Definition of Done

Customer Tables زمانی کامل هستند که:

- مشتری با اطلاعات اصلی قابل ثبت باشد.
- مشتری شماره رسمی یکتا داشته باشد.
- مشتری با شماره موبایل، نام و کد ملی قابل جستجو باشد.
- مدارک مشتری در Private Storage و Files Domain مدیریت شوند.
- مدارک مشتری قابل تأیید، رد و منقضی شدن باشند.
- یادداشت داخلی مشتری قابل ثبت و کنترل با Permission باشد.
- آدرس‌ها و مخاطبین مشتری قابل مدیریت باشند.
- وضعیت اعتباری مشتری قابل ثبت و ردیابی باشد.
- احراز هویت مشتری قابل ثبت و پیگیری باشد.
- تغییر وضعیت مشتری در تاریخچه ثبت شود.
- حذف مشتری و داده‌های وابسته به صورت Soft Delete انجام شود.
- اطلاعات حساس مشتری بدون Permission نمایش یا Export نشود.
- همه عملیات حساس Audit Log داشته باشند.
- تلاش‌های غیرمجاز Security Log داشته باشند.
- جدول‌ها با MySQL/MariaDB و PHP 7.4+ سازگار باشند.
- Codex بتواند از روی این مستندات Migrationهای Customer Domain را بسازد.

---

## پایان فایل
````
