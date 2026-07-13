# 05 — Contracts Tables

مستند جدول‌های قراردادها، آیتم‌های قرارداد، ضمانت‌ها، ضامن‌ها، مدارک قرارداد، وضعیت‌ها و Snapshotهای قراردادی در پروژه **Proma Pay**

---

## فهرست مطالب

- [هدف فایل](#هدف-فایل)
- [تعریف Contract Domain در دیتابیس](#تعریف-contract-domain-در-دیتابیس)
- [اصل مهم](#اصل-مهم)
- [لیست جدول‌های Contracts](#لیست-جدولهای-contracts)
- [جدول contracts](#جدول-contracts)
- [جدول contract_items](#جدول-contract_items)
- [جدول contract_guarantees](#جدول-contract_guarantees)
- [جدول contract_guarantors](#جدول-contract_guarantors)
- [جدول contract_documents](#جدول-contract_documents)
- [جدول contract_status_histories](#جدول-contract_status_histories)
- [جدول contract_terms](#جدول-contract_terms)
- [جدول contract_signatures](#جدول-contract_signatures)
- [جدول contract_snapshots](#جدول-contract_snapshots)
- [رابطه قرارداد با سایر Domainها](#رابطه-قرارداد-با-سایر-domainها)
- [قوانین مالی قرارداد](#قوانین-مالی-قرارداد)
- [قوانین ضمانت قرارداد](#قوانین-ضمانت-قرارداد)
- [قوانین وضعیت قرارداد](#قوانین-وضعیت-قرارداد)
- [قوانین Soft Delete](#قوانین-soft-delete)
- [قوانین Index و Performance](#قوانین-index-و-performance)
- [قوانین Validation](#قوانین-validation)
- [قوانین Audit و Security Log](#قوانین-audit-و-security-log)
- [Seedهای پیشنهادی](#seedهای-پیشنهادی)
- [چک‌لیست پیاده‌سازی](#چکلیست-پیادهسازی)
- [Definition of Done](#definition-of-done)

---

## هدف فایل

هدف این فایل این است که ساختار جدول‌های مربوط به قراردادهای فروش اقساطی در پروژه **Proma Pay** مشخص شود.

Contract Domain قلب سیستم Proma Pay است؛ چون اقساط، پرداخت‌ها، معوقات، تسویه، پیگیری‌ها، ضمانت‌ها و پرونده‌های حقوقی همگی به قرارداد وابسته هستند.

این فایل برای Codex مشخص می‌کند که:

- قرارداد اصلی چگونه ذخیره شود.
- کالاها یا خدمات داخل قرارداد چگونه ثبت شوند.
- ضمانت‌ها چگونه مدیریت شوند.
- ضامن‌ها چگونه ثبت شوند.
- مدارک قرارداد کجا نگهداری شوند.
- تغییر وضعیت قرارداد چگونه قابل ردیابی باشد.
- Snapshot مالی قرارداد چگونه ذخیره شود.
- قرارداد چگونه به اقساط، پرداخت‌ها، مشتری، فایل‌ها و پرونده حقوقی وصل شود.
- چه عملیات‌هایی نیاز به Audit Log یا Financial Log دارند.

---

## تعریف Contract Domain در دیتابیس

Contract Domain مسئول نگهداری قراردادهای فروش اقساطی یا توافق‌های مالی بین فروشگاه و مشتری است.

هر قرارداد می‌تواند شامل موارد زیر باشد:

- مشتری
- شماره رسمی قرارداد
- مبلغ کل
- پیش‌پرداخت
- مبلغ باقی‌مانده
- تعداد اقساط
- تاریخ شروع
- تاریخ پایان
- کالا یا کالاهای فروخته‌شده
- شرایط پرداخت
- ضمانت‌ها
- ضامن‌ها
- مدارک قرارداد
- وضعیت حقوقی
- وضعیت تسویه
- تاریخچه تغییر وضعیت
- Snapshot مالی در لحظه‌های مهم

---

## اصل مهم

اصل مهم در Contract Tables:

> قرارداد یک داده مالی و حقوقی حساس است و نباید بدون Audit، Permission و Snapshot مناسب تغییر کند.

به خصوص موارد زیر باید با دقت کنترل شوند:

- مبلغ قرارداد
- مبلغ پیش‌پرداخت
- تعداد اقساط
- تاریخ سررسیدها
- وضعیت قرارداد
- ضمانت‌ها
- ضامن‌ها
- مدارک قرارداد
- ارجاع به حقوقی
- تسویه قرارداد
- فسخ یا لغو قرارداد

---

## لیست جدول‌های Contracts

جدول‌های پیشنهادی Contract Domain:

| جدول | کاربرد |
|---|---|
| `contracts` | اطلاعات اصلی قرارداد |
| `contract_items` | کالاها یا خدمات داخل قرارداد |
| `contract_guarantees` | ضمانت‌های دریافت‌شده برای قرارداد |
| `contract_guarantors` | ضامن‌ها یا افراد تضمین‌کننده |
| `contract_documents` | فایل‌ها و مدارک قرارداد |
| `contract_status_histories` | تاریخچه تغییر وضعیت قرارداد |
| `contract_terms` | بندها و شرایط قرارداد |
| `contract_signatures` | امضاها یا تأییدهای قرارداد |
| `contract_snapshots` | Snapshotهای مالی و حقوقی قرارداد |

---

## جدول contracts

### هدف جدول

جدول `contracts` اطلاعات اصلی قرارداد را نگهداری می‌کند.

این جدول منبع اصلی وضعیت، مبلغ و ارتباط قرارداد با مشتری است.

---

### نام جدول

```text
contracts
```

---

### ستون‌های پیشنهادی

| ستون | نوع | توضیح |
|---|---|---|
| `id` | BIGINT UNSIGNED AUTO_INCREMENT | شناسه داخلی |
| `contract_number` | VARCHAR(50) | شماره رسمی قرارداد |
| `customer_id` | BIGINT UNSIGNED | مشتری |
| `contract_type` | VARCHAR(50) | نوع قرارداد |
| `contract_date` | DATE | تاریخ قرارداد |
| `start_date` | DATE NULL | تاریخ شروع |
| `end_date` | DATE NULL | تاریخ پایان |
| `total_amount` | DECIMAL(15,2) | مبلغ کل قرارداد |
| `down_payment_amount` | DECIMAL(15,2) | مبلغ پیش‌پرداخت |
| `financed_amount` | DECIMAL(15,2) | مبلغ تأمین‌شده یا اقساطی |
| `installment_count` | INT UNSIGNED | تعداد اقساط |
| `installment_amount` | DECIMAL(15,2) NULL | مبلغ قسط پایه |
| `paid_amount` | DECIMAL(15,2) | مبلغ پرداخت‌شده |
| `remaining_amount` | DECIMAL(15,2) | مبلغ باقی‌مانده |
| `penalty_amount` | DECIMAL(15,2) | مبلغ جریمه یا دیرکرد ثبت‌شده |
| `discount_amount` | DECIMAL(15,2) | تخفیف |
| `settlement_amount` | DECIMAL(15,2) NULL | مبلغ تسویه |
| `profit_amount` | DECIMAL(15,2) NULL | سود محاسبه‌شده در صورت نیاز داخلی |
| `status` | VARCHAR(50) | وضعیت قرارداد |
| `payment_status` | VARCHAR(50) | وضعیت پرداخت قرارداد |
| `legal_status` | VARCHAR(50) | وضعیت حقوقی |
| `settlement_status` | VARCHAR(50) | وضعیت تسویه |
| `guarantee_status` | VARCHAR(50) | وضعیت ضمانت |
| `assigned_user_id` | BIGINT UNSIGNED NULL | کاربر مسئول |
| `approved_by` | BIGINT UNSIGNED NULL | تأییدکننده |
| `approved_at` | DATETIME NULL | زمان تأیید |
| `cancelled_by` | BIGINT UNSIGNED NULL | لغوکننده |
| `cancelled_at` | DATETIME NULL | زمان لغو |
| `cancellation_reason` | TEXT NULL | دلیل لغو |
| `settled_by` | BIGINT UNSIGNED NULL | تسویه‌کننده |
| `settled_at` | DATETIME NULL | زمان تسویه |
| `metadata` | JSON NULL | داده تکمیلی |
| `created_at` | DATETIME NULL | زمان ایجاد |
| `updated_at` | DATETIME NULL | زمان بروزرسانی |
| `deleted_at` | DATETIME NULL | زمان حذف نرم |
| `created_by` | BIGINT UNSIGNED NULL | ایجادکننده |
| `updated_by` | BIGINT UNSIGNED NULL | ویرایش‌کننده |
| `deleted_by` | BIGINT UNSIGNED NULL | حذف‌کننده |

---

### contract_typeهای پیشنهادی

```text
installment_sale
cash_sale
loan
trust
manual
other
```

---

### statusهای پیشنهادی

```text
draft
pending_approval
active
completed
cancelled
settled
legal
defaulted
deleted
```

---

### payment_statusهای پیشنهادی

```text
unpaid
partially_paid
paid
overdue
settled
refunded
```

---

### legal_statusهای پیشنهادی

```text
none
warning
referred
in_progress
closed
```

---

### settlement_statusهای پیشنهادی

```text
not_settled
pending
settled
cancelled
```

---

### guarantee_statusهای پیشنهادی

```text
not_required
pending
received
incomplete
returned
used
```

---

### قوانین

- `contract_number` باید Unique باشد.
- هر قرارداد باید `customer_id` معتبر داشته باشد.
- مبلغ‌ها باید با `DECIMAL` ذخیره شوند.
- استفاده از `FLOAT` و `DOUBLE` برای مبلغ ممنوع است.
- قرارداد active نباید بدون دلیل لغو شود.
- تغییر مبلغ قرارداد بعد از فعال شدن باید محدود و دارای Audit و Financial Log باشد.
- قرارداد دارای پرداخت یا قسط نباید فیزیکی حذف شود.
- تغییر وضعیت قرارداد باید در `contract_status_histories` ثبت شود.
- تسویه قرارداد باید Snapshot داشته باشد.
- ارجاع به حقوقی باید Snapshot بدهی داشته باشد.
- قرارداد نباید به پلاگین خاص وابسته باشد.

---

### Indexهای پیشنهادی

```text
uniq_contracts_contract_number
idx_contracts_customer_id
idx_contracts_contract_date
idx_contracts_status
idx_contracts_payment_status
idx_contracts_legal_status
idx_contracts_settlement_status
idx_contracts_guarantee_status
idx_contracts_assigned_user_id
idx_contracts_created_at
idx_contracts_deleted_at
```

---

### نمونه ساختار SQL

```sql
CREATE TABLE contracts (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    contract_number VARCHAR(50) NOT NULL,
    customer_id BIGINT UNSIGNED NOT NULL,
    contract_type VARCHAR(50) NOT NULL DEFAULT 'installment_sale',
    contract_date DATE NOT NULL,
    start_date DATE NULL,
    end_date DATE NULL,
    total_amount DECIMAL(15,2) NOT NULL DEFAULT 0,
    down_payment_amount DECIMAL(15,2) NOT NULL DEFAULT 0,
    financed_amount DECIMAL(15,2) NOT NULL DEFAULT 0,
    installment_count INT UNSIGNED NOT NULL DEFAULT 0,
    installment_amount DECIMAL(15,2) NULL,
    paid_amount DECIMAL(15,2) NOT NULL DEFAULT 0,
    remaining_amount DECIMAL(15,2) NOT NULL DEFAULT 0,
    penalty_amount DECIMAL(15,2) NOT NULL DEFAULT 0,
    discount_amount DECIMAL(15,2) NOT NULL DEFAULT 0,
    settlement_amount DECIMAL(15,2) NULL,
    profit_amount DECIMAL(15,2) NULL,
    status VARCHAR(50) NOT NULL DEFAULT 'draft',
    payment_status VARCHAR(50) NOT NULL DEFAULT 'unpaid',
    legal_status VARCHAR(50) NOT NULL DEFAULT 'none',
    settlement_status VARCHAR(50) NOT NULL DEFAULT 'not_settled',
    guarantee_status VARCHAR(50) NOT NULL DEFAULT 'pending',
    assigned_user_id BIGINT UNSIGNED NULL,
    approved_by BIGINT UNSIGNED NULL,
    approved_at DATETIME NULL,
    cancelled_by BIGINT UNSIGNED NULL,
    cancelled_at DATETIME NULL,
    cancellation_reason TEXT NULL,
    settled_by BIGINT UNSIGNED NULL,
    settled_at DATETIME NULL,
    metadata JSON NULL,
    created_at DATETIME NULL,
    updated_at DATETIME NULL,
    deleted_at DATETIME NULL,
    created_by BIGINT UNSIGNED NULL,
    updated_by BIGINT UNSIGNED NULL,
    deleted_by BIGINT UNSIGNED NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uniq_contracts_contract_number (contract_number),
    KEY idx_contracts_customer_id (customer_id),
    KEY idx_contracts_contract_date (contract_date),
    KEY idx_contracts_status (status),
    KEY idx_contracts_payment_status (payment_status),
    KEY idx_contracts_legal_status (legal_status),
    KEY idx_contracts_settlement_status (settlement_status),
    KEY idx_contracts_guarantee_status (guarantee_status),
    KEY idx_contracts_assigned_user_id (assigned_user_id),
    KEY idx_contracts_created_at (created_at),
    KEY idx_contracts_deleted_at (deleted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

---

## جدول contract_items

### هدف جدول

جدول `contract_items` کالاها یا خدمات داخل قرارداد را نگهداری می‌کند.

در فروش موبایل اقساطی، هر قرارداد معمولاً شامل یک گوشی یا چند کالا و لوازم جانبی است.

---

### نام جدول

```text
contract_items
```

---

### ستون‌های پیشنهادی

| ستون | نوع | توضیح |
|---|---|---|
| `id` | BIGINT UNSIGNED AUTO_INCREMENT | شناسه |
| `contract_id` | BIGINT UNSIGNED | قرارداد |
| `item_type` | VARCHAR(50) | نوع آیتم |
| `product_name` | VARCHAR(191) | نام کالا |
| `brand` | VARCHAR(100) NULL | برند |
| `model` | VARCHAR(100) NULL | مدل |
| `imei` | VARCHAR(100) NULL | IMEI یا شناسه دستگاه |
| `serial_number` | VARCHAR(100) NULL | شماره سریال |
| `warranty_title` | VARCHAR(191) NULL | عنوان گارانتی |
| `color` | VARCHAR(50) NULL | رنگ |
| `storage` | VARCHAR(50) NULL | حافظه |
| `ram` | VARCHAR(50) NULL | رم |
| `quantity` | INT UNSIGNED | تعداد |
| `unit_price` | DECIMAL(15,2) | قیمت واحد |
| `discount_amount` | DECIMAL(15,2) | تخفیف آیتم |
| `total_amount` | DECIMAL(15,2) | مبلغ کل آیتم |
| `description` | TEXT NULL | توضیح |
| `metadata` | JSON NULL | داده تکمیلی |
| `created_at` | DATETIME NULL | زمان ایجاد |
| `updated_at` | DATETIME NULL | زمان بروزرسانی |
| `deleted_at` | DATETIME NULL | زمان حذف نرم |

---

### item_typeهای پیشنهادی

```text
mobile
accessory
service
insurance
other
```

---

### قوانین

- هر آیتم باید به یک contract_id وصل باشد.
- مبلغ آیتم باید با DECIMAL ذخیره شود.
- اگر کالا گوشی است، IMEI یا serial_number در صورت وجود ثبت شود.
- تغییر آیتم بعد از فعال شدن قرارداد باید Audit و Financial Log داشته باشد.
- حذف آیتم قرارداد فعال باید محدود شود.
- total_amount باید سمت سرور محاسبه شود.
- Frontend نباید منبع حقیقت مبلغ آیتم باشد.

---

### Indexهای پیشنهادی

```text
idx_contract_items_contract_id
idx_contract_items_item_type
idx_contract_items_brand
idx_contract_items_model
idx_contract_items_imei
idx_contract_items_serial_number
idx_contract_items_deleted_at
```

---

## جدول contract_guarantees

### هدف جدول

جدول `contract_guarantees` ضمانت‌های دریافت‌شده برای قرارداد را نگهداری می‌کند.

ضمانت می‌تواند شامل چک، سفته، تعهدنامه، مدارک یا ضمانت شخصی باشد.

---

### نام جدول

```text
contract_guarantees
```

---

### ستون‌های پیشنهادی

| ستون | نوع | توضیح |
|---|---|---|
| `id` | BIGINT UNSIGNED AUTO_INCREMENT | شناسه |
| `contract_id` | BIGINT UNSIGNED | قرارداد |
| `customer_id` | BIGINT UNSIGNED | مشتری |
| `guarantee_type` | VARCHAR(50) | نوع ضمانت |
| `guarantee_number` | VARCHAR(100) NULL | شماره ضمانت |
| `bank_name` | VARCHAR(100) NULL | نام بانک برای چک |
| `branch_name` | VARCHAR(100) NULL | شعبه |
| `amount` | DECIMAL(15,2) NULL | مبلغ ضمانت |
| `due_date` | DATE NULL | تاریخ سررسید ضمانت |
| `received_at` | DATETIME NULL | زمان دریافت |
| `received_by` | BIGINT UNSIGNED NULL | دریافت‌کننده |
| `returned_at` | DATETIME NULL | زمان برگشت ضمانت |
| `returned_by` | BIGINT UNSIGNED NULL | برگشت‌دهنده |
| `used_at` | DATETIME NULL | زمان استفاده یا وصول |
| `used_by` | BIGINT UNSIGNED NULL | استفاده‌کننده |
| `status` | VARCHAR(50) | وضعیت ضمانت |
| `description` | TEXT NULL | توضیح |
| `metadata` | JSON NULL | داده تکمیلی |
| `created_at` | DATETIME NULL | زمان ایجاد |
| `updated_at` | DATETIME NULL | زمان بروزرسانی |
| `deleted_at` | DATETIME NULL | زمان حذف نرم |
| `created_by` | BIGINT UNSIGNED NULL | ایجادکننده |
| `updated_by` | BIGINT UNSIGNED NULL | ویرایش‌کننده |
| `deleted_by` | BIGINT UNSIGNED NULL | حذف‌کننده |

---

### guarantee_typeهای پیشنهادی

```text
check
promissory_note
cash_deposit
document
guarantor
device_retention
other
```

---

### statusهای پیشنهادی

```text
pending
received
verified
incomplete
returned
used
cancelled
deleted
```

---

### قوانین

- هر ضمانت باید به contract_id وصل باشد.
- ضمانت چک باید اطلاعات شماره و بانک داشته باشد.
- مبلغ ضمانت در صورت ثبت باید DECIMAL باشد.
- برگشت ضمانت باید Audit Log داشته باشد.
- استفاده یا وصول ضمانت باید Audit و Financial Log داشته باشد.
- حذف ضمانت قرارداد فعال باید محدود باشد.
- ضمانت‌های حساس نباید برای نقش غیرمجاز نمایش داده شوند.
- فایل تصویر ضمانت باید در Files Domain و Private Storage باشد.

---

### Indexهای پیشنهادی

```text
idx_contract_guarantees_contract_id
idx_contract_guarantees_customer_id
idx_contract_guarantees_guarantee_type
idx_contract_guarantees_guarantee_number
idx_contract_guarantees_status
idx_contract_guarantees_due_date
idx_contract_guarantees_received_at
idx_contract_guarantees_deleted_at
```

---

## جدول contract_guarantors

### هدف جدول

جدول `contract_guarantors` اطلاعات ضامن‌های مرتبط با قرارداد را نگهداری می‌کند.

ضامن رسمی با مخاطب اضطراری مشتری فرق دارد.

---

### نام جدول

```text
contract_guarantors
```

---

### ستون‌های پیشنهادی

| ستون | نوع | توضیح |
|---|---|---|
| `id` | BIGINT UNSIGNED AUTO_INCREMENT | شناسه |
| `contract_id` | BIGINT UNSIGNED | قرارداد |
| `customer_id` | BIGINT UNSIGNED | مشتری اصلی |
| `full_name` | VARCHAR(191) | نام کامل ضامن |
| `national_code` | VARCHAR(20) NULL | کد ملی ضامن |
| `phone` | VARCHAR(30) NULL | شماره تماس |
| `relationship` | VARCHAR(100) NULL | نسبت با مشتری |
| `job_title` | VARCHAR(191) NULL | شغل |
| `workplace` | VARCHAR(191) NULL | محل کار |
| `address` | TEXT NULL | آدرس |
| `guarantee_level` | VARCHAR(50) | سطح ضمانت |
| `status` | VARCHAR(50) | وضعیت |
| `verified_at` | DATETIME NULL | زمان تأیید |
| `verified_by` | BIGINT UNSIGNED NULL | تأییدکننده |
| `rejection_reason` | TEXT NULL | دلیل رد |
| `metadata` | JSON NULL | داده تکمیلی |
| `created_at` | DATETIME NULL | زمان ایجاد |
| `updated_at` | DATETIME NULL | زمان بروزرسانی |
| `deleted_at` | DATETIME NULL | زمان حذف نرم |
| `created_by` | BIGINT UNSIGNED NULL | ایجادکننده |
| `updated_by` | BIGINT UNSIGNED NULL | ویرایش‌کننده |
| `deleted_by` | BIGINT UNSIGNED NULL | حذف‌کننده |

---

### guarantee_levelهای پیشنهادی

```text
primary
secondary
supporting
```

---

### statusهای پیشنهادی

```text
pending
verified
rejected
inactive
deleted
```

---

### قوانین

- ضامن باید به contract_id وصل باشد.
- اطلاعات ضامن حساس است.
- نمایش اطلاعات ضامن باید Permission داشته باشد.
- Export اطلاعات ضامن باید Audit Log داشته باشد.
- تأیید یا رد ضامن باید reviewed/verified_by داشته باشد.
- حذف ضامن قرارداد active باید محدود باشد.
- ضامن نباید با Customer Contact یکی فرض شود.

---

### Indexهای پیشنهادی

```text
idx_contract_guarantors_contract_id
idx_contract_guarantors_customer_id
idx_contract_guarantors_national_code
idx_contract_guarantors_phone
idx_contract_guarantors_status
idx_contract_guarantors_verified_by
idx_contract_guarantors_deleted_at
```

---

## جدول contract_documents

### هدف جدول

جدول `contract_documents` ارتباط قرارداد با فایل‌ها و مدارک قرارداد را نگهداری می‌کند.

فایل واقعی باید در Files Domain ذخیره شود.

---

### نام جدول

```text
contract_documents
```

---

### ستون‌های پیشنهادی

| ستون | نوع | توضیح |
|---|---|---|
| `id` | BIGINT UNSIGNED AUTO_INCREMENT | شناسه |
| `contract_id` | BIGINT UNSIGNED | قرارداد |
| `customer_id` | BIGINT UNSIGNED | مشتری |
| `file_id` | BIGINT UNSIGNED | فایل |
| `document_type` | VARCHAR(50) | نوع مدرک |
| `title` | VARCHAR(191) NULL | عنوان |
| `description` | TEXT NULL | توضیح |
| `status` | VARCHAR(50) | وضعیت |
| `uploaded_by` | BIGINT UNSIGNED NULL | آپلودکننده |
| `uploaded_at` | DATETIME NULL | زمان آپلود |
| `reviewed_by` | BIGINT UNSIGNED NULL | بررسی‌کننده |
| `reviewed_at` | DATETIME NULL | زمان بررسی |
| `rejection_reason` | TEXT NULL | دلیل رد |
| `metadata` | JSON NULL | داده تکمیلی |
| `created_at` | DATETIME NULL | زمان ایجاد |
| `updated_at` | DATETIME NULL | زمان بروزرسانی |
| `deleted_at` | DATETIME NULL | زمان حذف نرم |

---

### document_typeهای پیشنهادی

```text
signed_contract
contract_pdf
guarantee_check
promissory_note
customer_document
device_delivery_receipt
settlement_receipt
legal_attachment
other
```

---

### statusهای پیشنهادی

```text
pending
approved
rejected
archived
deleted
```

---

### قوانین

- هر document باید file_id معتبر داشته باشد.
- فایل قرارداد باید Private باشد.
- دانلود قرارداد باید Audit Log داشته باشد.
- قرارداد امضاشده نباید بدون Audit جایگزین شود.
- فایل‌های حقوقی متصل به قرارداد باید Permission حقوقی داشته باشند.
- حذف مدرک قرارداد باید Soft Delete باشد.

---

### Indexهای پیشنهادی

```text
idx_contract_documents_contract_id
idx_contract_documents_customer_id
idx_contract_documents_file_id
idx_contract_documents_document_type
idx_contract_documents_status
idx_contract_documents_uploaded_by
idx_contract_documents_reviewed_by
idx_contract_documents_created_at
```

---

## جدول contract_status_histories

### هدف جدول

جدول `contract_status_histories` تاریخچه تغییر وضعیت قرارداد را ذخیره می‌کند.

این جدول برای ردیابی روند قرارداد، اختلافات، حسابداری و پرونده حقوقی بسیار مهم است.

---

### نام جدول

```text
contract_status_histories
```

---

### ستون‌های پیشنهادی

| ستون | نوع | توضیح |
|---|---|---|
| `id` | BIGINT UNSIGNED AUTO_INCREMENT | شناسه |
| `contract_id` | BIGINT UNSIGNED | قرارداد |
| `customer_id` | BIGINT UNSIGNED | مشتری |
| `old_status` | VARCHAR(50) NULL | وضعیت قبلی |
| `new_status` | VARCHAR(50) | وضعیت جدید |
| `old_payment_status` | VARCHAR(50) NULL | وضعیت پرداخت قبلی |
| `new_payment_status` | VARCHAR(50) NULL | وضعیت پرداخت جدید |
| `old_legal_status` | VARCHAR(50) NULL | وضعیت حقوقی قبلی |
| `new_legal_status` | VARCHAR(50) NULL | وضعیت حقوقی جدید |
| `reason` | TEXT NULL | دلیل تغییر |
| `changed_by` | BIGINT UNSIGNED NULL | تغییر دهنده |
| `changed_at` | DATETIME | زمان تغییر |
| `metadata` | JSON NULL | داده تکمیلی |

---

### قوانین

- هر تغییر مهم در status قرارداد باید ثبت شود.
- ارجاع به حقوقی باید در این جدول ثبت شود.
- تسویه باید در این جدول ثبت شود.
- لغو قرارداد باید reason داشته باشد.
- این جدول نباید Soft Delete شود.
- مشاهده تاریخچه وضعیت قرارداد باید Permission داشته باشد.

---

### Indexهای پیشنهادی

```text
idx_contract_status_histories_contract_id
idx_contract_status_histories_customer_id
idx_contract_status_histories_new_status
idx_contract_status_histories_new_payment_status
idx_contract_status_histories_new_legal_status
idx_contract_status_histories_changed_by
idx_contract_status_histories_changed_at
```

---

## جدول contract_terms

### هدف جدول

جدول `contract_terms` بندها و شرایط قراردادی را نگهداری می‌کند.

این جدول کمک می‌کند بندهای مهم قرارداد به صورت ساختاریافته ذخیره شوند.

---

### نام جدول

```text
contract_terms
```

---

### ستون‌های پیشنهادی

| ستون | نوع | توضیح |
|---|---|---|
| `id` | BIGINT UNSIGNED AUTO_INCREMENT | شناسه |
| `contract_id` | BIGINT UNSIGNED | قرارداد |
| `term_key` | VARCHAR(100) | کلید بند |
| `title` | VARCHAR(191) | عنوان بند |
| `body` | TEXT | متن بند |
| `sort_order` | INT UNSIGNED | ترتیب نمایش |
| `is_required` | TINYINT(1) | الزامی بودن |
| `is_accepted` | TINYINT(1) | پذیرفته شده |
| `accepted_at` | DATETIME NULL | زمان پذیرش |
| `accepted_by` | BIGINT UNSIGNED NULL | پذیرنده |
| `metadata` | JSON NULL | داده تکمیلی |
| `created_at` | DATETIME NULL | زمان ایجاد |
| `updated_at` | DATETIME NULL | زمان بروزرسانی |

---

### نمونه term_key

```text
installment_payment_terms
late_payment_penalty
guarantee_collection_right
device_return_right
ownership_until_full_payment
legal_referral_terms
settlement_terms
```

---

### قوانین

- بندهای مهم قرارداد باید Snapshot شوند.
- تغییر بند بعد از فعال شدن قرارداد باید محدود باشد.
- پذیرش بندها باید قابل ردیابی باشد.
- بندهای حقوقی حساس باید Audit Log داشته باشند.
- متن نهایی قرارداد باید قابل بازسازی از Snapshot باشد.

---

### Indexهای پیشنهادی

```text
idx_contract_terms_contract_id
idx_contract_terms_term_key
idx_contract_terms_sort_order
idx_contract_terms_is_required
idx_contract_terms_is_accepted
```

---

## جدول contract_signatures

### هدف جدول

جدول `contract_signatures` امضاها یا تأییدهای قرارداد را ذخیره می‌کند.

امضا می‌تواند دستی، دیجیتال، سیستمی یا تأیید داخلی باشد.

---

### نام جدول

```text
contract_signatures
```

---

### ستون‌های پیشنهادی

| ستون | نوع | توضیح |
|---|---|---|
| `id` | BIGINT UNSIGNED AUTO_INCREMENT | شناسه |
| `contract_id` | BIGINT UNSIGNED | قرارداد |
| `customer_id` | BIGINT UNSIGNED | مشتری |
| `signature_type` | VARCHAR(50) | نوع امضا |
| `signer_type` | VARCHAR(50) | نوع امضاکننده |
| `signer_name` | VARCHAR(191) NULL | نام امضاکننده |
| `signer_user_id` | BIGINT UNSIGNED NULL | کاربر امضاکننده |
| `file_id` | BIGINT UNSIGNED NULL | فایل امضا یا قرارداد امضاشده |
| `signed_at` | DATETIME NULL | زمان امضا |
| `ip_address` | VARCHAR(45) NULL | IP |
| `user_agent` | TEXT NULL | User Agent |
| `status` | VARCHAR(50) | وضعیت |
| `metadata` | JSON NULL | داده تکمیلی |
| `created_at` | DATETIME NULL | زمان ایجاد |

---

### signature_typeهای پیشنهادی

```text
manual
digital
system_approval
customer_acceptance
admin_approval
```

---

### signer_typeهای پیشنهادی

```text
customer
admin
operator
lawyer
system
guarantor
```

---

### statusهای پیشنهادی

```text
pending
signed
rejected
cancelled
expired
```

---

### قوانین

- قرارداد فعال باید امضا یا تأیید لازم را داشته باشد.
- فایل امضا باید در Files Domain ذخیره شود.
- امضای دیجیتال باید Metadata کافی داشته باشد.
- تغییر یا حذف امضا باید Audit Log داشته باشد.
- امضای مشتری نباید بدون Permission و دلیل حذف شود.

---

### Indexهای پیشنهادی

```text
idx_contract_signatures_contract_id
idx_contract_signatures_customer_id
idx_contract_signatures_signature_type
idx_contract_signatures_signer_type
idx_contract_signatures_signer_user_id
idx_contract_signatures_status
idx_contract_signatures_signed_at
```

---

## جدول contract_snapshots

### هدف جدول

جدول `contract_snapshots` وضعیت مالی، قراردادی و حقوقی قرارداد را در لحظه‌های مهم ذخیره می‌کند.

Snapshot برای جلوگیری از اختلاف محاسبات آینده ضروری است.

---

### نام جدول

```text
contract_snapshots
```

---

### ستون‌های پیشنهادی

| ستون | نوع | توضیح |
|---|---|---|
| `id` | BIGINT UNSIGNED AUTO_INCREMENT | شناسه |
| `contract_id` | BIGINT UNSIGNED | قرارداد |
| `customer_id` | BIGINT UNSIGNED | مشتری |
| `snapshot_type` | VARCHAR(50) | نوع Snapshot |
| `total_amount` | DECIMAL(15,2) | مبلغ کل |
| `paid_amount` | DECIMAL(15,2) | مبلغ پرداخت‌شده |
| `remaining_amount` | DECIMAL(15,2) | مبلغ باقی‌مانده |
| `penalty_amount` | DECIMAL(15,2) | جریمه |
| `discount_amount` | DECIMAL(15,2) | تخفیف |
| `installment_count` | INT UNSIGNED NULL | تعداد اقساط |
| `overdue_count` | INT UNSIGNED NULL | تعداد معوقات |
| `status` | VARCHAR(50) | وضعیت قرارداد در آن لحظه |
| `payment_status` | VARCHAR(50) | وضعیت پرداخت در آن لحظه |
| `legal_status` | VARCHAR(50) | وضعیت حقوقی در آن لحظه |
| `snapshot_data` | JSON NULL | داده کامل Snapshot |
| `created_by` | BIGINT UNSIGNED NULL | ایجادکننده |
| `created_at` | DATETIME | زمان ایجاد |

---

### snapshot_typeهای پیشنهادی

```text
created
activated
payment_applied
overdue_detected
legal_referral
settlement
cancelled
manual
```

---

### قوانین

- ارجاع حقوقی باید Snapshot داشته باشد.
- تسویه باید Snapshot داشته باشد.
- لغو قرارداد باید Snapshot داشته باشد.
- Snapshot نباید بعداً ویرایش شود.
- Snapshot منبع مهم برای گزارش و اختلافات حقوقی است.
- Snapshot نباید جایگزین جدول اصلی شود، بلکه وضعیت لحظه‌ای را ثبت می‌کند.

---

### Indexهای پیشنهادی

```text
idx_contract_snapshots_contract_id
idx_contract_snapshots_customer_id
idx_contract_snapshots_snapshot_type
idx_contract_snapshots_status
idx_contract_snapshots_payment_status
idx_contract_snapshots_legal_status
idx_contract_snapshots_created_at
```

---

## رابطه قرارداد با سایر Domainها

قرارداد با بخش‌های زیادی از سیستم ارتباط دارد.

| Domain | رابطه |
|---|---|
| Customers | هر قرارداد متعلق به یک مشتری است |
| Installments | اقساط بر اساس قرارداد ساخته می‌شوند |
| Payments | پرداخت‌ها به قرارداد و اقساط وصل می‌شوند |
| Financial | تغییرات مالی قرارداد در Financial Log ثبت می‌شوند |
| Legal | پرونده حقوقی از قرارداد ایجاد می‌شود |
| Files | مدارک و قرارداد امضاشده در Files ذخیره می‌شوند |
| Calendar | یادآوری اقساط و پیگیری‌ها به قرارداد وصل می‌شوند |
| Chat | گفتگوهای مرتبط با قرارداد قابل ثبت هستند |
| Reports | گزارش قراردادها، اقساط، معوقات و تسویه از قرارداد استفاده می‌کند |
| Audit | تغییرات حساس قرارداد ثبت می‌شود |
| Security | تلاش غیرمجاز برای مشاهده یا تغییر قرارداد ثبت می‌شود |

---

## قوانین مالی قرارداد

قوانین:

- همه مبلغ‌ها باید با DECIMAL ذخیره شوند.
- Frontend نباید منبع حقیقت مبلغ باشد.
- مبلغ کل قرارداد باید از آیتم‌ها و شرایط محاسبه شود.
- پیش‌پرداخت باید در پرداخت‌ها نیز قابل ردیابی باشد.
- financed_amount باید سمت سرور محاسبه شود.
- paid_amount و remaining_amount باید از پرداخت‌های approved محاسبه یا با Snapshot معتبر نگهداری شوند.
- پرداخت pending_review نباید در paid_amount قطعی لحاظ شود.
- تغییر مبلغ قرارداد active باید Financial Log داشته باشد.
- تسویه قرارداد باید Snapshot و Settlement Record داشته باشد.
- فرمول داخلی سود نباید برای نقش غیرمجاز نمایش داده شود.

---

## قوانین ضمانت قرارداد

قوانین:

- ضمانت‌های قرارداد باید قابل ثبت و ردیابی باشند.
- ضمانت‌ها می‌توانند چک، سفته، ضامن یا نگهداری مالکیت کالا باشند.
- ضمانت دریافتی باید status مشخص داشته باشد.
- برگشت ضمانت بعد از تسویه باید Audit Log داشته باشد.
- استفاده از ضمانت باید Financial Log و Audit Log داشته باشد.
- اطلاعات چک، سفته و ضامن حساس است.
- نمایش و Export ضمانت‌ها باید Permission داشته باشد.
- ضمانت قرارداد حقوقی‌شده نباید بدون Permission تغییر کند.

---

## قوانین وضعیت قرارداد

تغییر وضعیت قرارداد باید کنترل‌شده باشد.

### Transitionهای پیشنهادی

```text
draft -> pending_approval
pending_approval -> active
active -> completed
active -> settled
active -> legal
active -> defaulted
active -> cancelled
legal -> settled
legal -> closed
```

### قوانین

- قرارداد draft قابل ویرایش است.
- قرارداد active فقط با Permission ویژه قابل تغییر مالی است.
- قرارداد settled نباید پرداخت جدید عادی بگیرد، مگر با Permission اصلاحی.
- قرارداد legal باید محدودیت ویرایش داشته باشد.
- قرارداد cancelled باید cancellation_reason داشته باشد.
- هر تغییر وضعیت باید در history ثبت شود.
- Transition نامعتبر باید رد شود.

---

## قوانین Soft Delete

جدول‌های زیر باید Soft Delete داشته باشند:

- `contracts`
- `contract_items`
- `contract_guarantees`
- `contract_guarantors`
- `contract_documents`

قوانین:

- قرارداد دارای پرداخت یا قسط نباید فیزیکی حذف شود.
- قرارداد active نباید حذف شود، مگر اول cancel شود.
- حذف قرارداد باید Audit Log داشته باشد.
- حذف آیتم قرارداد active باید Financial Log داشته باشد.
- حذف مدارک قرارداد باید فقط Soft Delete باشد.
- Snapshot و history نباید حذف نرم شوند.

---

## قوانین Index و Performance

قوانین:

- جستجوی قرارداد با contract_number باید سریع باشد.
- فیلتر قراردادهای مشتری باید سریع باشد.
- گزارش قراردادها باید بر اساس status، date و customer فیلترپذیر باشد.
- گزارش معوقات از قرارداد و اقساط استفاده می‌کند و باید Index مناسب داشته باشد.
- قراردادهای deleted در Queryهای عادی نمایش داده نشوند.
- Index زیاد و بی‌دلیل ممنوع است.

Indexهای مهم:

```text
contracts.contract_number
contracts.customer_id
contracts.status
contracts.payment_status
contracts.legal_status
contracts.contract_date
contract_items.contract_id
contract_guarantees.contract_id
contract_guarantors.contract_id
contract_documents.contract_id
contract_snapshots.contract_id
contract_status_histories.contract_id
```

---

## قوانین Validation

### contracts

- customer_id الزامی است.
- contract_number الزامی و یکتا است.
- contract_date الزامی است.
- total_amount باید غیرمنفی باشد.
- down_payment_amount نباید بیشتر از total_amount باشد.
- financed_amount باید غیرمنفی باشد.
- installment_count برای قرارداد اقساطی باید بیشتر از صفر باشد.
- status باید معتبر باشد.
- payment_status باید معتبر باشد.
- legal_status باید معتبر باشد.

### contract_items

- contract_id الزامی است.
- product_name الزامی است.
- quantity باید بیشتر از صفر باشد.
- unit_price باید غیرمنفی باشد.
- total_amount باید سمت سرور محاسبه شود.
- IMEI در صورت ثبت باید فرمت قابل قبول داشته باشد.

### contract_guarantees

- contract_id الزامی است.
- guarantee_type الزامی است.
- amount در صورت ثبت باید غیرمنفی باشد.
- guarantee_number برای چک یا سفته می‌تواند الزامی شود.
- status باید معتبر باشد.

### contract_guarantors

- contract_id الزامی است.
- full_name الزامی است.
- phone یا national_code در صورت Policy الزامی باشد.
- status باید معتبر باشد.

### contract_documents

- contract_id الزامی است.
- file_id الزامی است.
- document_type الزامی است.
- status باید معتبر باشد.

---

## قوانین Audit و Security Log

### Audit Log الزامی برای:

- ایجاد قرارداد
- فعال‌سازی قرارداد
- تغییر مبلغ قرارداد
- تغییر تعداد اقساط
- تغییر تاریخ‌های قرارداد
- لغو قرارداد
- تسویه قرارداد
- ارجاع قرارداد به حقوقی
- تغییر ضمانت
- ثبت یا حذف ضامن
- دانلود قرارداد امضاشده
- حذف نرم قرارداد
- تغییر بندهای قرارداد
- تغییر امضا یا تأیید قرارداد
- Export قراردادهای حساس

### Financial Log الزامی برای:

- تغییر مبلغ کل قرارداد
- تغییر پیش‌پرداخت
- تغییر مبلغ اقساطی
- تغییر مبلغ قسط
- اعمال پرداخت روی قرارداد
- تسویه قرارداد
- ثبت تخفیف
- ثبت جریمه
- استفاده از ضمانت مالی

### Security Log الزامی برای:

- تلاش مشاهده قرارداد بدون Permission
- تلاش مشاهده قرارداد خارج از Scope
- تلاش تغییر قرارداد بدون Permission
- تلاش دانلود مدرک قرارداد بدون Permission
- تلاش تغییر مبلغ از Frontend
- CSRF نامعتبر
- دستکاری contract_id در درخواست‌ها
- تلاش Export قراردادهای خارج از Scope

---

## Seedهای پیشنهادی

### contract_type

```text
installment_sale
cash_sale
loan
trust
manual
other
```

### status

```text
draft
pending_approval
active
completed
cancelled
settled
legal
defaulted
deleted
```

### payment_status

```text
unpaid
partially_paid
paid
overdue
settled
refunded
```

### legal_status

```text
none
warning
referred
in_progress
closed
```

### guarantee_type

```text
check
promissory_note
cash_deposit
document
guarantor
device_retention
other
```

### document_type

```text
signed_contract
contract_pdf
guarantee_check
promissory_note
customer_document
device_delivery_receipt
settlement_receipt
legal_attachment
other
```

---

## چک‌لیست پیاده‌سازی

قبل از پیاده‌سازی Contract Tables بررسی شود:

- [ ] جدول `contracts` ساخته شده است.
- [ ] `contract_number` یکتا است.
- [ ] همه مبلغ‌ها DECIMAL هستند.
- [ ] قرارداد به customer_id وصل است.
- [ ] وضعیت‌های قرارداد مستند و کنترل‌شده هستند.
- [ ] جدول `contract_items` وجود دارد.
- [ ] آیتم‌های قرارداد مبلغ سمت سرور دارند.
- [ ] جدول `contract_guarantees` وجود دارد.
- [ ] جدول `contract_guarantors` وجود دارد.
- [ ] جدول `contract_documents` وجود دارد.
- [ ] فایل‌های قرارداد در Files Domain ذخیره می‌شوند.
- [ ] جدول `contract_status_histories` وجود دارد.
- [ ] جدول `contract_terms` وجود دارد.
- [ ] جدول `contract_signatures` وجود دارد.
- [ ] جدول `contract_snapshots` وجود دارد.
- [ ] تغییرات حساس Audit Log دارند.
- [ ] تغییرات مالی Financial Log دارند.
- [ ] تلاش‌های غیرمجاز Security Log دارند.
- [ ] Queryهای قرارداد Scope را رعایت می‌کنند.
- [ ] Export قرارداد Permission-based است.

---

## Definition of Done

Contract Tables زمانی کامل هستند که:

- قرارداد با شماره رسمی یکتا قابل ثبت باشد.
- قرارداد به مشتری وصل باشد.
- قرارداد بتواند کالا یا آیتم‌های مختلف داشته باشد.
- مبلغ کل، پیش‌پرداخت، مبلغ اقساطی، پرداخت‌شده و مانده قابل نگهداری باشد.
- قرارداد بتواند ضمانت و ضامن داشته باشد.
- مدارک قرارداد در Files Domain و Private Storage مدیریت شوند.
- تغییر وضعیت قرارداد در تاریخچه ثبت شود.
- بندهای قرارداد قابل ذخیره و Snapshot باشند.
- امضا یا تأیید قرارداد قابل ثبت باشد.
- Snapshotهای مهم مثل ایجاد، ارجاع حقوقی و تسویه ذخیره شوند.
- قرارداد active بدون Audit قابل تغییر مالی نباشد.
- قرارداد دارای پرداخت یا قسط فیزیکی حذف نشود.
- اطلاعات حساس قرارداد بدون Permission نمایش یا Export نشود.
- تغییرات مالی Financial Log داشته باشند.
- عملیات حساس Audit Log داشته باشند.
- تلاش‌های غیرمجاز Security Log داشته باشند.
- جدول‌ها با MySQL/MariaDB و PHP 7.4+ سازگار باشند.
- Codex بتواند از روی این مستندات Migrationهای Contract Domain را بسازد.

---

## پایان فایل
````
