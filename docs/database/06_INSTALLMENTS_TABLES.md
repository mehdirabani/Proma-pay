# 06 — Installments Tables

مستند جدول‌های اقساط، برنامه پرداخت، وضعیت اقساط، پیگیری‌ها، وعده پرداخت، دیرکرد، Snapshot و داده‌های وابسته به Installment Domain در پروژه **Proma Pay**

---

## فهرست مطالب

- [هدف فایل](#هدف-فایل)
- [تعریف Installment Domain در دیتابیس](#تعریف-installment-domain-در-دیتابیس)
- [اصل مهم](#اصل-مهم)
- [لیست جدول‌های Installments](#لیست-جدولهای-installments)
- [جدول installments](#جدول-installments)
- [جدول installment_schedules](#جدول-installment_schedules)
- [جدول installment_status_histories](#جدول-installment_status_histories)
- [جدول installment_followups](#جدول-installment_followups)
- [جدول payment_promises](#جدول-payment_promises)
- [جدول installment_penalties](#جدول-installment_penalties)
- [جدول installment_snapshots](#جدول-installment_snapshots)
- [رابطه اقساط با سایر Domainها](#رابطه-اقساط-با-سایر-domainها)
- [قوانین مالی اقساط](#قوانین-مالی-اقساط)
- [قوانین وضعیت اقساط](#قوانین-وضعیت-اقساط)
- [قوانین دیرکرد](#قوانین-دیرکرد)
- [قوانین پیگیری اقساط](#قوانین-پیگیری-اقساط)
- [قوانین وعده پرداخت](#قوانین-وعده-پرداخت)
- [قوانین Soft Delete](#قوانین-soft-delete)
- [قوانین Index و Performance](#قوانین-index-و-performance)
- [قوانین Validation](#قوانین-validation)
- [قوانین Audit و Financial Log](#قوانین-audit-و-financial-log)
- [Seedهای پیشنهادی](#seedهای-پیشنهادی)
- [چک‌لیست پیاده‌سازی](#چکلیست-پیادهسازی)
- [Definition of Done](#definition-of-done)

---

## هدف فایل

هدف این فایل این است که ساختار جدول‌های مربوط به اقساط در پروژه **Proma Pay** مشخص شود.

Installment Domain یکی از مهم‌ترین بخش‌های سیستم است؛ چون مدیریت فروش اقساطی، تشخیص معوقات، ثبت پرداخت، محاسبه مانده، پیگیری مشتری، وعده پرداخت، ارجاع حقوقی و گزارش‌های مالی به آن وابسته است.

این فایل برای Codex مشخص می‌کند که:

- هر قسط چگونه ذخیره شود.
- تاریخ سررسید اقساط چگونه مدیریت شود.
- وضعیت هر قسط چگونه تغییر کند.
- پرداخت‌های تأییدشده چگونه روی قسط اثر بگذارند.
- اقساط معوق چگونه شناسایی شوند.
- پیگیری‌های اپراتور چگونه ثبت شوند.
- وعده پرداخت مشتری چگونه نگهداری شود.
- دیرکرد یا جریمه چگونه ثبت شود.
- Snapshot اقساط در لحظه‌های مهم چگونه ذخیره شود.
- چه عملیات‌هایی نیاز به Audit، Financial Log یا Security Log دارند.

---

## تعریف Installment Domain در دیتابیس

Installment Domain مسئول نگهداری اقساط مربوط به قراردادهای اقساطی است.

هر قرارداد اقساطی می‌تواند چند قسط داشته باشد.

هر قسط می‌تواند وضعیت‌های مختلفی داشته باشد:

- در انتظار پرداخت
- پرداخت جزئی
- پرداخت کامل
- معوق
- حقوقی‌شده
- لغوشده
- تسویه‌شده

هر قسط باید بتواند:

- مبلغ اصلی داشته باشد.
- مبلغ پرداخت‌شده داشته باشد.
- مانده داشته باشد.
- تاریخ سررسید داشته باشد.
- تعداد روز تأخیر داشته باشد.
- پرداخت‌های مرتبط داشته باشد.
- پیگیری‌های مرتبط داشته باشد.
- وعده پرداخت داشته باشد.
- Snapshot داشته باشد.

---

## اصل مهم

اصل مهم در Installment Tables:

> مبلغ قسط، مانده قسط، وضعیت قسط و دیرکرد باید همیشه از سمت سرور و بر اساس داده معتبر محاسبه یا بروزرسانی شود.

Frontend نباید منبع حقیقت مبلغ یا وضعیت قسط باشد.

موارد حساس:

- تغییر مبلغ قسط
- تغییر تاریخ سررسید
- ثبت پرداخت روی قسط
- تغییر وضعیت به paid
- تغییر وضعیت به overdue
- ارجاع قسط به حقوقی
- حذف یا لغو قسط
- ثبت جریمه یا تخفیف
- تسویه قسط

---

## لیست جدول‌های Installments

جدول‌های پیشنهادی Installment Domain:

| جدول | کاربرد |
|---|---|
| `installments` | اطلاعات اصلی هر قسط |
| `installment_schedules` | برنامه زمان‌بندی تولید اقساط |
| `installment_status_histories` | تاریخچه تغییر وضعیت اقساط |
| `installment_followups` | پیگیری‌های اپراتور برای اقساط |
| `payment_promises` | وعده‌های پرداخت مشتری |
| `installment_penalties` | جریمه‌ها یا دیرکردهای ثبت‌شده برای قسط |
| `installment_snapshots` | Snapshotهای مالی و وضعیتی اقساط |

---

## جدول installments

### هدف جدول

جدول `installments` اطلاعات اصلی هر قسط را نگهداری می‌کند.

این جدول منبع اصلی وضعیت هر قسط، مبلغ قسط، تاریخ سررسید، مبلغ پرداخت‌شده و مانده قسط است.

---

### نام جدول

```text
installments
```

---

### ستون‌های پیشنهادی

| ستون | نوع | توضیح |
|---|---|---|
| `id` | BIGINT UNSIGNED AUTO_INCREMENT | شناسه داخلی |
| `installment_number` | VARCHAR(50) | شماره رسمی قسط |
| `contract_id` | BIGINT UNSIGNED | قرارداد |
| `customer_id` | BIGINT UNSIGNED | مشتری |
| `sequence_number` | INT UNSIGNED | شماره ترتیب قسط در قرارداد |
| `title` | VARCHAR(191) NULL | عنوان نمایشی قسط |
| `due_date` | DATE | تاریخ سررسید |
| `original_due_date` | DATE NULL | تاریخ سررسید اولیه |
| `installment_amount` | DECIMAL(15,2) | مبلغ اصلی قسط |
| `paid_amount` | DECIMAL(15,2) | مبلغ پرداخت‌شده |
| `remaining_amount` | DECIMAL(15,2) | مبلغ باقی‌مانده |
| `penalty_amount` | DECIMAL(15,2) | مبلغ جریمه |
| `discount_amount` | DECIMAL(15,2) | مبلغ تخفیف |
| `settlement_amount` | DECIMAL(15,2) NULL | مبلغ تسویه قسط |
| `status` | VARCHAR(50) | وضعیت قسط |
| `payment_status` | VARCHAR(50) | وضعیت پرداخت |
| `overdue_days` | INT UNSIGNED | تعداد روز تأخیر |
| `is_overdue` | TINYINT(1) | آیا قسط معوق است؟ |
| `is_locked` | TINYINT(1) | قفل بودن برای تغییرات حساس |
| `paid_at` | DATETIME NULL | زمان پرداخت کامل |
| `partially_paid_at` | DATETIME NULL | زمان اولین پرداخت جزئی |
| `overdue_at` | DATETIME NULL | زمان معوق شدن |
| `legal_referred_at` | DATETIME NULL | زمان ارجاع به حقوقی |
| `settled_at` | DATETIME NULL | زمان تسویه |
| `cancelled_at` | DATETIME NULL | زمان لغو |
| `cancelled_by` | BIGINT UNSIGNED NULL | لغوکننده |
| `cancellation_reason` | TEXT NULL | دلیل لغو |
| `assigned_user_id` | BIGINT UNSIGNED NULL | اپراتور یا مسئول پیگیری |
| `last_followup_at` | DATETIME NULL | آخرین پیگیری |
| `next_followup_at` | DATETIME NULL | پیگیری بعدی |
| `metadata` | JSON NULL | داده تکمیلی |
| `created_at` | DATETIME NULL | زمان ایجاد |
| `updated_at` | DATETIME NULL | زمان بروزرسانی |
| `deleted_at` | DATETIME NULL | زمان حذف نرم |
| `created_by` | BIGINT UNSIGNED NULL | ایجادکننده |
| `updated_by` | BIGINT UNSIGNED NULL | ویرایش‌کننده |
| `deleted_by` | BIGINT UNSIGNED NULL | حذف‌کننده |

---

### statusهای پیشنهادی

```text
pending
partially_paid
paid
overdue
legal
settled
cancelled
deleted
```

---

### payment_statusهای پیشنهادی

```text
unpaid
partial
paid
overpaid
refunded
```

---

### قوانین

- هر قسط باید contract_id معتبر داشته باشد.
- هر قسط باید customer_id معتبر داشته باشد.
- sequence_number باید در هر قرارداد یکتا باشد.
- installment_number باید Unique باشد.
- مبلغ‌ها باید با DECIMAL ذخیره شوند.
- استفاده از FLOAT و DOUBLE برای مبلغ ممنوع است.
- paid_amount نباید از مجموع پرداخت‌های approved جدا و بی‌دلیل تغییر کند.
- remaining_amount باید سمت سرور محاسبه شود.
- قسط paid نباید بدون Permission ویژه تغییر مالی کند.
- قسط legal باید محدودیت ویرایش داشته باشد.
- تغییر due_date بعد از فعال شدن قرارداد باید Audit Log داشته باشد.
- تغییر مبلغ قسط باید Financial Log داشته باشد.
- حذف قسط دارای پرداخت ممنوع یا فقط Soft Delete باشد.

---

### Indexهای پیشنهادی

```text
uniq_installments_installment_number
uniq_installments_contract_sequence
idx_installments_contract_id
idx_installments_customer_id
idx_installments_due_date
idx_installments_status
idx_installments_payment_status
idx_installments_is_overdue
idx_installments_assigned_user_id
idx_installments_last_followup_at
idx_installments_next_followup_at
idx_installments_created_at
idx_installments_deleted_at
```

---

### نمونه ساختار SQL

```sql
CREATE TABLE installments (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    installment_number VARCHAR(50) NOT NULL,
    contract_id BIGINT UNSIGNED NOT NULL,
    customer_id BIGINT UNSIGNED NOT NULL,
    sequence_number INT UNSIGNED NOT NULL,
    title VARCHAR(191) NULL,
    due_date DATE NOT NULL,
    original_due_date DATE NULL,
    installment_amount DECIMAL(15,2) NOT NULL DEFAULT 0,
    paid_amount DECIMAL(15,2) NOT NULL DEFAULT 0,
    remaining_amount DECIMAL(15,2) NOT NULL DEFAULT 0,
    penalty_amount DECIMAL(15,2) NOT NULL DEFAULT 0,
    discount_amount DECIMAL(15,2) NOT NULL DEFAULT 0,
    settlement_amount DECIMAL(15,2) NULL,
    status VARCHAR(50) NOT NULL DEFAULT 'pending',
    payment_status VARCHAR(50) NOT NULL DEFAULT 'unpaid',
    overdue_days INT UNSIGNED NOT NULL DEFAULT 0,
    is_overdue TINYINT(1) NOT NULL DEFAULT 0,
    is_locked TINYINT(1) NOT NULL DEFAULT 0,
    paid_at DATETIME NULL,
    partially_paid_at DATETIME NULL,
    overdue_at DATETIME NULL,
    legal_referred_at DATETIME NULL,
    settled_at DATETIME NULL,
    cancelled_at DATETIME NULL,
    cancelled_by BIGINT UNSIGNED NULL,
    cancellation_reason TEXT NULL,
    assigned_user_id BIGINT UNSIGNED NULL,
    last_followup_at DATETIME NULL,
    next_followup_at DATETIME NULL,
    metadata JSON NULL,
    created_at DATETIME NULL,
    updated_at DATETIME NULL,
    deleted_at DATETIME NULL,
    created_by BIGINT UNSIGNED NULL,
    updated_by BIGINT UNSIGNED NULL,
    deleted_by BIGINT UNSIGNED NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uniq_installments_installment_number (installment_number),
    UNIQUE KEY uniq_installments_contract_sequence (contract_id, sequence_number),
    KEY idx_installments_contract_id (contract_id),
    KEY idx_installments_customer_id (customer_id),
    KEY idx_installments_due_date (due_date),
    KEY idx_installments_status (status),
    KEY idx_installments_payment_status (payment_status),
    KEY idx_installments_is_overdue (is_overdue),
    KEY idx_installments_assigned_user_id (assigned_user_id),
    KEY idx_installments_last_followup_at (last_followup_at),
    KEY idx_installments_next_followup_at (next_followup_at),
    KEY idx_installments_created_at (created_at),
    KEY idx_installments_deleted_at (deleted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

---

## جدول installment_schedules

### هدف جدول

جدول `installment_schedules` برای نگهداری الگوی تولید اقساط یک قرارداد استفاده می‌شود.

این جدول نشان می‌دهد اقساط بر اساس چه برنامه‌ای تولید شده‌اند.

---

### نام جدول

```text
installment_schedules
```

---

### ستون‌های پیشنهادی

| ستون | نوع | توضیح |
|---|---|---|
| `id` | BIGINT UNSIGNED AUTO_INCREMENT | شناسه |
| `contract_id` | BIGINT UNSIGNED | قرارداد |
| `customer_id` | BIGINT UNSIGNED | مشتری |
| `schedule_type` | VARCHAR(50) | نوع برنامه |
| `start_date` | DATE | تاریخ شروع |
| `first_due_date` | DATE | تاریخ اولین سررسید |
| `installment_count` | INT UNSIGNED | تعداد اقساط |
| `interval_type` | VARCHAR(50) | نوع فاصله زمانی |
| `interval_value` | INT UNSIGNED | مقدار فاصله |
| `base_installment_amount` | DECIMAL(15,2) | مبلغ پایه هر قسط |
| `total_scheduled_amount` | DECIMAL(15,2) | مجموع مبلغ برنامه |
| `rounding_policy` | VARCHAR(50) | سیاست گرد کردن |
| `status` | VARCHAR(50) | وضعیت برنامه |
| `generated_at` | DATETIME NULL | زمان تولید اقساط |
| `generated_by` | BIGINT UNSIGNED NULL | تولیدکننده |
| `metadata` | JSON NULL | داده تکمیلی |
| `created_at` | DATETIME NULL | زمان ایجاد |
| `updated_at` | DATETIME NULL | زمان بروزرسانی |

---

### schedule_typeهای پیشنهادی

```text
fixed_monthly
custom
manual
recalculated
```

---

### interval_typeهای پیشنهادی

```text
days
weeks
months
custom
```

---

### rounding_policyهای پیشنهادی

```text
none
last_installment_adjustment
first_installment_adjustment
spread_difference
```

---

### statusهای پیشنهادی

```text
draft
generated
active
cancelled
replaced
```

---

### قوانین

- هر قرارداد اقساطی باید یک برنامه تولید اقساط داشته باشد.
- اگر اقساط دستی ساخته شوند، schedule_type می‌تواند manual باشد.
- تغییر برنامه بعد از فعال شدن قرارداد باید Audit و Financial Log داشته باشد.
- اگر برنامه دوباره محاسبه شود، برنامه قبلی باید replaced شود.
- مجموع برنامه باید با financed_amount قرارداد سازگار باشد.
- اختلاف گرد کردن باید طبق rounding_policy کنترل شود.

---

### Indexهای پیشنهادی

```text
idx_installment_schedules_contract_id
idx_installment_schedules_customer_id
idx_installment_schedules_schedule_type
idx_installment_schedules_status
idx_installment_schedules_generated_at
```

---

## جدول installment_status_histories

### هدف جدول

جدول `installment_status_histories` تاریخچه تغییر وضعیت اقساط را ذخیره می‌کند.

این جدول برای ردیابی مالی، پشتیبانی، پیگیری معوقات و پرونده حقوقی ضروری است.

---

### نام جدول

```text
installment_status_histories
```

---

### ستون‌های پیشنهادی

| ستون | نوع | توضیح |
|---|---|---|
| `id` | BIGINT UNSIGNED AUTO_INCREMENT | شناسه |
| `installment_id` | BIGINT UNSIGNED | قسط |
| `contract_id` | BIGINT UNSIGNED | قرارداد |
| `customer_id` | BIGINT UNSIGNED | مشتری |
| `old_status` | VARCHAR(50) NULL | وضعیت قبلی |
| `new_status` | VARCHAR(50) | وضعیت جدید |
| `old_payment_status` | VARCHAR(50) NULL | وضعیت پرداخت قبلی |
| `new_payment_status` | VARCHAR(50) NULL | وضعیت پرداخت جدید |
| `old_remaining_amount` | DECIMAL(15,2) NULL | مانده قبلی |
| `new_remaining_amount` | DECIMAL(15,2) NULL | مانده جدید |
| `reason` | TEXT NULL | دلیل تغییر |
| `changed_by` | BIGINT UNSIGNED NULL | تغییر دهنده |
| `changed_at` | DATETIME | زمان تغییر |
| `metadata` | JSON NULL | داده تکمیلی |

---

### قوانین

- هر تغییر مهم در status قسط باید ثبت شود.
- تغییر به paid باید ثبت شود.
- تغییر به overdue باید ثبت شود.
- تغییر به legal باید ثبت شود.
- تغییر به cancelled باید reason داشته باشد.
- این جدول نباید Soft Delete شود.
- تاریخچه وضعیت باید فقط برای نقش مجاز نمایش داده شود.

---

### Indexهای پیشنهادی

```text
idx_installment_status_histories_installment_id
idx_installment_status_histories_contract_id
idx_installment_status_histories_customer_id
idx_installment_status_histories_new_status
idx_installment_status_histories_new_payment_status
idx_installment_status_histories_changed_by
idx_installment_status_histories_changed_at
```

---

## جدول installment_followups

### هدف جدول

جدول `installment_followups` برای ثبت پیگیری‌های اپراتور، حسابدار یا واحد وصول مطالبات درباره اقساط استفاده می‌شود.

این جدول برای مدیریت معوقات و گزارش عملکرد اپراتورها ضروری است.

---

### نام جدول

```text
installment_followups
```

---

### ستون‌های پیشنهادی

| ستون | نوع | توضیح |
|---|---|---|
| `id` | BIGINT UNSIGNED AUTO_INCREMENT | شناسه |
| `installment_id` | BIGINT UNSIGNED | قسط |
| `contract_id` | BIGINT UNSIGNED | قرارداد |
| `customer_id` | BIGINT UNSIGNED | مشتری |
| `followup_type` | VARCHAR(50) | نوع پیگیری |
| `channel` | VARCHAR(50) | کانال ارتباط |
| `result` | VARCHAR(50) | نتیجه پیگیری |
| `summary` | VARCHAR(255) NULL | خلاصه |
| `description` | TEXT NULL | توضیح کامل |
| `next_action` | VARCHAR(100) NULL | اقدام بعدی |
| `next_followup_at` | DATETIME NULL | زمان پیگیری بعدی |
| `payment_promise_id` | BIGINT UNSIGNED NULL | وعده پرداخت مرتبط |
| `assigned_user_id` | BIGINT UNSIGNED NULL | کاربر مسئول |
| `created_by` | BIGINT UNSIGNED NULL | ثبت‌کننده |
| `created_at` | DATETIME NULL | زمان ثبت |
| `updated_at` | DATETIME NULL | زمان بروزرسانی |
| `deleted_at` | DATETIME NULL | زمان حذف نرم |
| `deleted_by` | BIGINT UNSIGNED NULL | حذف‌کننده |

---

### followup_typeهای پیشنهادی

```text
reminder
overdue_followup
payment_request
legal_warning
settlement_negotiation
manual_note
```

---

### channelهای پیشنهادی

```text
phone
sms
whatsapp
telegram
in_person
system
other
```

---

### resultهای پیشنهادی

```text
successful
no_answer
wrong_number
promise_received
refused_to_pay
needs_followup
sent_to_legal
resolved
```

---

### قوانین

- هر پیگیری باید به installment_id وصل باشد.
- پیگیری معوقه باید contract_id و customer_id هم داشته باشد.
- یادداشت پیگیری برای مشتری نباید بدون طراحی مشخص نمایش داده شود.
- حذف پیگیری باید Soft Delete باشد.
- پیگیری حقوقی یا حساس باید Audit Log داشته باشد.
- ثبت followup باید last_followup_at قسط را بروزرسانی کند.
- اگر next_followup_at وجود دارد، Calendar/Reminder می‌تواند ساخته شود.

---

### Indexهای پیشنهادی

```text
idx_installment_followups_installment_id
idx_installment_followups_contract_id
idx_installment_followups_customer_id
idx_installment_followups_followup_type
idx_installment_followups_channel
idx_installment_followups_result
idx_installment_followups_assigned_user_id
idx_installment_followups_next_followup_at
idx_installment_followups_created_by
idx_installment_followups_created_at
```

---

## جدول payment_promises

### هدف جدول

جدول `payment_promises` برای ثبت وعده‌های پرداخت مشتری استفاده می‌شود.

وقتی مشتری اعلام می‌کند در تاریخ مشخصی مبلغی را پرداخت می‌کند، یک Promise ثبت می‌شود.

---

### نام جدول

```text
payment_promises
```

---

### ستون‌های پیشنهادی

| ستون | نوع | توضیح |
|---|---|---|
| `id` | BIGINT UNSIGNED AUTO_INCREMENT | شناسه |
| `promise_number` | VARCHAR(50) | شماره رسمی وعده |
| `installment_id` | BIGINT UNSIGNED | قسط |
| `contract_id` | BIGINT UNSIGNED | قرارداد |
| `customer_id` | BIGINT UNSIGNED | مشتری |
| `promised_amount` | DECIMAL(15,2) | مبلغ وعده داده‌شده |
| `promise_date` | DATE | تاریخ وعده پرداخت |
| `promise_channel` | VARCHAR(50) | کانال دریافت وعده |
| `status` | VARCHAR(50) | وضعیت وعده |
| `fulfilled_amount` | DECIMAL(15,2) | مبلغ محقق‌شده |
| `fulfilled_at` | DATETIME NULL | زمان تحقق وعده |
| `failed_at` | DATETIME NULL | زمان شکست وعده |
| `failure_reason` | TEXT NULL | دلیل شکست |
| `created_by` | BIGINT UNSIGNED NULL | ثبت‌کننده |
| `approved_by` | BIGINT UNSIGNED NULL | تأییدکننده در صورت نیاز |
| `approved_at` | DATETIME NULL | زمان تأیید |
| `description` | TEXT NULL | توضیحات |
| `metadata` | JSON NULL | داده تکمیلی |
| `created_at` | DATETIME NULL | زمان ایجاد |
| `updated_at` | DATETIME NULL | زمان بروزرسانی |
| `deleted_at` | DATETIME NULL | زمان حذف نرم |
| `deleted_by` | BIGINT UNSIGNED NULL | حذف‌کننده |

---

### promise_channelهای پیشنهادی

```text
phone
sms
whatsapp
telegram
in_person
system
other
```

---

### statusهای پیشنهادی

```text
pending
active
fulfilled
partially_fulfilled
failed
cancelled
expired
deleted
```

---

### قوانین

- هر وعده باید به installment_id وصل باشد.
- promise_number باید Unique باشد.
- promised_amount باید DECIMAL و مثبت باشد.
- promise_date نباید خالی باشد.
- وعده فعال باید در گزارش معوقات نمایش داده شود.
- پرداخت approved می‌تواند وعده را fulfilled یا partially_fulfilled کند.
- اگر تاریخ وعده گذشت و پرداخت انجام نشد، وعده باید failed یا expired شود.
- تغییر وعده پرداخت باید Audit Log داشته باشد.
- حذف وعده باید Soft Delete باشد.

---

### Indexهای پیشنهادی

```text
uniq_payment_promises_promise_number
idx_payment_promises_installment_id
idx_payment_promises_contract_id
idx_payment_promises_customer_id
idx_payment_promises_promise_date
idx_payment_promises_status
idx_payment_promises_created_by
idx_payment_promises_created_at
```

---

## جدول installment_penalties

### هدف جدول

جدول `installment_penalties` برای ثبت جریمه‌ها، دیرکردها یا تعدیلات مرتبط با تأخیر قسط استفاده می‌شود.

این جدول برای شفافیت مالی و Audit بسیار مهم است.

---

### نام جدول

```text
installment_penalties
```

---

### ستون‌های پیشنهادی

| ستون | نوع | توضیح |
|---|---|---|
| `id` | BIGINT UNSIGNED AUTO_INCREMENT | شناسه |
| `installment_id` | BIGINT UNSIGNED | قسط |
| `contract_id` | BIGINT UNSIGNED | قرارداد |
| `customer_id` | BIGINT UNSIGNED | مشتری |
| `penalty_type` | VARCHAR(50) | نوع جریمه |
| `calculation_type` | VARCHAR(50) | نوع محاسبه |
| `base_amount` | DECIMAL(15,2) | مبلغ مبنا |
| `penalty_amount` | DECIMAL(15,2) | مبلغ جریمه |
| `discount_amount` | DECIMAL(15,2) | تخفیف روی جریمه |
| `final_penalty_amount` | DECIMAL(15,2) | مبلغ نهایی جریمه |
| `days_overdue` | INT UNSIGNED | تعداد روز تأخیر |
| `calculated_at` | DATETIME NULL | زمان محاسبه |
| `applied_at` | DATETIME NULL | زمان اعمال |
| `applied_by` | BIGINT UNSIGNED NULL | اعمال‌کننده |
| `waived_at` | DATETIME NULL | زمان بخشودگی |
| `waived_by` | BIGINT UNSIGNED NULL | بخشنده |
| `waiver_reason` | TEXT NULL | دلیل بخشودگی |
| `status` | VARCHAR(50) | وضعیت |
| `metadata` | JSON NULL | داده تکمیلی |
| `created_at` | DATETIME NULL | زمان ایجاد |
| `updated_at` | DATETIME NULL | زمان بروزرسانی |

---

### penalty_typeهای پیشنهادی

```text
late_payment
manual
legal
settlement_adjustment
other
```

---

### calculation_typeهای پیشنهادی

```text
fixed
daily
percentage
manual
none
```

---

### statusهای پیشنهادی

```text
calculated
applied
waived
cancelled
adjusted
```

---

### قوانین

- هر جریمه باید به installment_id وصل باشد.
- مبلغ جریمه باید با DECIMAL ذخیره شود.
- جریمه نباید فقط در Frontend محاسبه شود.
- اعمال جریمه باید Financial Log داشته باشد.
- بخشودگی جریمه باید Audit و Financial Log داشته باشد.
- فرمول داخلی جریمه نباید برای نقش غیرمجاز نمایش داده شود.
- در صورت عدم نیاز به جریمه، این جدول می‌تواند فقط برای ثبت Manual Adjustment استفاده شود.

---

### Indexهای پیشنهادی

```text
idx_installment_penalties_installment_id
idx_installment_penalties_contract_id
idx_installment_penalties_customer_id
idx_installment_penalties_penalty_type
idx_installment_penalties_status
idx_installment_penalties_calculated_at
idx_installment_penalties_applied_at
```

---

## جدول installment_snapshots

### هدف جدول

جدول `installment_snapshots` وضعیت مالی و وضعیتی قسط را در لحظه‌های مهم ذخیره می‌کند.

Snapshot برای اختلافات مالی، گزارش‌های دقیق، ارجاع حقوقی و تسویه ضروری است.

---

### نام جدول

```text
installment_snapshots
```

---

### ستون‌های پیشنهادی

| ستون | نوع | توضیح |
|---|---|---|
| `id` | BIGINT UNSIGNED AUTO_INCREMENT | شناسه |
| `installment_id` | BIGINT UNSIGNED | قسط |
| `contract_id` | BIGINT UNSIGNED | قرارداد |
| `customer_id` | BIGINT UNSIGNED | مشتری |
| `snapshot_type` | VARCHAR(50) | نوع Snapshot |
| `installment_amount` | DECIMAL(15,2) | مبلغ قسط |
| `paid_amount` | DECIMAL(15,2) | مبلغ پرداخت‌شده |
| `remaining_amount` | DECIMAL(15,2) | مانده |
| `penalty_amount` | DECIMAL(15,2) | جریمه |
| `discount_amount` | DECIMAL(15,2) | تخفیف |
| `due_date` | DATE | تاریخ سررسید |
| `overdue_days` | INT UNSIGNED | تعداد روز تأخیر |
| `status` | VARCHAR(50) | وضعیت قسط |
| `payment_status` | VARCHAR(50) | وضعیت پرداخت |
| `snapshot_data` | JSON NULL | داده کامل Snapshot |
| `created_by` | BIGINT UNSIGNED NULL | ایجادکننده |
| `created_at` | DATETIME | زمان ایجاد |

---

### snapshot_typeهای پیشنهادی

```text
created
payment_applied
partial_payment
paid
overdue_detected
penalty_applied
promise_created
legal_referral
settlement
cancelled
manual
```

---

### قوانین

- قسط هنگام ایجاد می‌تواند Snapshot داشته باشد.
- پرداخت روی قسط باید Snapshot ایجاد کند.
- معوق شدن قسط باید Snapshot داشته باشد.
- ارجاع به حقوقی باید Snapshot داشته باشد.
- تسویه باید Snapshot داشته باشد.
- Snapshot نباید ویرایش شود.
- Snapshot نباید Soft Delete شود.
- Snapshot باید فقط برای نقش مجاز قابل مشاهده باشد.

---

### Indexهای پیشنهادی

```text
idx_installment_snapshots_installment_id
idx_installment_snapshots_contract_id
idx_installment_snapshots_customer_id
idx_installment_snapshots_snapshot_type
idx_installment_snapshots_status
idx_installment_snapshots_payment_status
idx_installment_snapshots_created_at
```

---

## رابطه اقساط با سایر Domainها

اقساط با بخش‌های زیادی از سیستم ارتباط دارد.

| Domain | رابطه |
|---|---|
| Customers | هر قسط متعلق به مشتری قرارداد است |
| Contracts | هر قسط متعلق به یک قرارداد است |
| Payments | پرداخت‌های approved روی اقساط اعمال می‌شوند |
| Financial | تغییرات مبلغ، جریمه، تخفیف و تسویه در Financial Log ثبت می‌شود |
| Legal | اقساط معوق می‌توانند وارد پرونده حقوقی شوند |
| Calendar | یادآوری سررسید و پیگیری اقساط از این Domain استفاده می‌کند |
| Notifications | یادآوری پرداخت، اخطار معوقه و وعده پرداخت ارسال می‌شود |
| Chat | پیام‌های مربوط به قسط می‌توانند ثبت شوند |
| Reports | گزارش اقساط، معوقات، پرداخت‌ها و عملکرد اپراتور از این Domain استفاده می‌کند |
| Audit | تغییرات حساس اقساط ثبت می‌شود |
| Security | تلاش غیرمجاز برای مشاهده یا تغییر اقساط ثبت می‌شود |

---

## قوانین مالی اقساط

قوانین:

- همه مبلغ‌ها باید DECIMAL باشند.
- مبلغ قسط باید سمت سرور محاسبه شود.
- paid_amount فقط بر اساس پرداخت‌های approved یا عملیات مالی معتبر بروزرسانی شود.
- remaining_amount باید از installment_amount، paid_amount، penalty_amount و discount_amount محاسبه یا کنترل شود.
- پرداخت pending_review نباید در paid_amount قطعی لحاظ شود.
- پرداخت rejected نباید روی مبلغ قسط اثر بگذارد.
- تغییر دستی مبلغ قسط باید Financial Log داشته باشد.
- تسویه قسط باید Snapshot و Financial Log داشته باشد.
- قسط paid نباید دوباره paid شود.
- overpaid باید کنترل شود.

---

## قوانین وضعیت اقساط

Transitionهای پیشنهادی:

```text
pending -> partially_paid
pending -> paid
pending -> overdue
partially_paid -> paid
partially_paid -> overdue
overdue -> partially_paid
overdue -> paid
overdue -> legal
legal -> paid
legal -> settled
pending -> cancelled
```

قوانین:

- Transition نامعتبر باید رد شود.
- تغییر status باید در history ثبت شود.
- قسط paid نباید بدون Permission ویژه به وضعیت قبلی برگردد.
- قسط legal باید محدودیت ویرایش داشته باشد.
- قسط cancelled باید cancellation_reason داشته باشد.
- paid_at فقط هنگام پرداخت کامل تنظیم شود.
- overdue_at فقط هنگام معوق شدن تنظیم شود.

---

## قوانین دیرکرد

قوانین:

- قسطی که due_date آن گذشته و paid نشده، معوق محسوب می‌شود.
- overdue_days باید سمت سرور محاسبه شود.
- معوق شدن قسط باید با Cron یا Job قابل تشخیص باشد.
- قسط معوق باید is_overdue = 1 داشته باشد.
- قسط پرداخت‌شده نباید معوق بماند.
- جریمه دیرکرد فقط در صورت فعال بودن Policy ثبت شود.
- جریمه دیرکرد باید Financial Log داشته باشد.
- فرمول دیرکرد نباید برای نقش غیرمجاز نمایش داده شود.

---

## قوانین پیگیری اقساط

قوانین:

- هر پیگیری باید کاربر ثبت‌کننده داشته باشد.
- پیگیری باید نتیجه مشخص داشته باشد.
- پیگیری حساس نباید برای مشتری نمایش داده شود.
- پیگیری‌های معوقات باید در گزارش عملکرد اپراتور استفاده شوند.
- next_followup_at می‌تواند باعث ساخت Reminder شود.
- پیگیری حقوقی باید Permission خاص داشته باشد.
- حذف پیگیری باید Audit Log داشته باشد.

---

## قوانین وعده پرداخت

قوانین:

- وعده پرداخت باید مبلغ و تاریخ داشته باشد.
- وعده پرداخت باید به قسط وصل باشد.
- وعده پرداخت فعال باید در گزارش معوقات مشخص باشد.
- پرداخت approved می‌تواند وعده را fulfilled کند.
- اگر تاریخ وعده بگذرد و پرداخت کافی ثبت نشود، وعده failed یا expired شود.
- وعده پرداخت شکست‌خورده می‌تواند سطح ریسک مشتری را تغییر دهد.
- تغییر یا حذف وعده پرداخت باید Audit Log داشته باشد.

---

## قوانین Soft Delete

جدول‌های زیر باید Soft Delete داشته باشند:

- `installments`
- `installment_followups`
- `payment_promises`

قوانین:

- قسط دارای پرداخت نباید فیزیکی حذف شود.
- قسط قرارداد active نباید حذف شود، مگر با عملیات اصلاحی مجاز.
- حذف قسط باید Audit و Financial Log داشته باشد.
- history و snapshots نباید Soft Delete شوند.
- جریمه اعمال‌شده نباید فیزیکی حذف شود؛ باید cancelled یا waived شود.

---

## قوانین Index و Performance

قوانین:

- گزارش معوقات باید سریع باشد.
- فیلتر اقساط بر اساس due_date، status و customer_id باید Index داشته باشد.
- جستجوی اقساط قرارداد باید سریع باشد.
- صفحه‌بندی برای گزارش اقساط الزامی است.
- Job تشخیص معوقات باید Query بهینه داشته باشد.
- Index زیاد و بی‌دلیل ممنوع است.
- Export اقساط باید محدود و Permission-based باشد.

Indexهای مهم:

```text
installments.contract_id
installments.customer_id
installments.due_date
installments.status
installments.is_overdue
installments.assigned_user_id
installment_followups.installment_id
payment_promises.installment_id
payment_promises.promise_date
installment_penalties.installment_id
installment_snapshots.installment_id
```

---

## قوانین Validation

### installments

- contract_id الزامی است.
- customer_id الزامی است.
- sequence_number الزامی و در قرارداد یکتا است.
- due_date الزامی است.
- installment_amount باید غیرمنفی باشد.
- paid_amount نباید منفی باشد.
- remaining_amount نباید منفی باشد، مگر Policy خاص وجود داشته باشد.
- status باید معتبر باشد.
- payment_status باید معتبر باشد.

### installment_schedules

- contract_id الزامی است.
- installment_count باید بیشتر از صفر باشد.
- first_due_date الزامی است.
- base_installment_amount باید غیرمنفی باشد.
- interval_type باید معتبر باشد.
- total_scheduled_amount باید با قرارداد سازگار باشد.

### installment_followups

- installment_id الزامی است.
- followup_type الزامی است.
- channel باید معتبر باشد.
- result باید معتبر باشد.
- created_by الزامی است.
- next_followup_at اگر وجود دارد باید معتبر باشد.

### payment_promises

- installment_id الزامی است.
- promised_amount باید بیشتر از صفر باشد.
- promise_date الزامی است.
- status باید معتبر باشد.
- fulfilled_amount نباید منفی باشد.

### installment_penalties

- installment_id الزامی است.
- penalty_amount باید غیرمنفی باشد.
- final_penalty_amount باید غیرمنفی باشد.
- days_overdue باید غیرمنفی باشد.
- status باید معتبر باشد.

---

## قوانین Audit و Financial Log

### Audit Log الزامی برای:

- ایجاد اقساط قرارداد
- تغییر تاریخ سررسید
- تغییر وضعیت قسط
- لغو قسط
- ارجاع قسط به حقوقی
- ثبت پیگیری حساس
- حذف پیگیری
- ثبت وعده پرداخت
- تغییر وعده پرداخت
- حذف وعده پرداخت
- Export گزارش اقساط حساس
- مشاهده یا تغییر اقساط خارج از Scope مجاز

### Financial Log الزامی برای:

- تغییر مبلغ قسط
- ثبت پرداخت روی قسط
- تغییر paid_amount
- تغییر remaining_amount
- اعمال جریمه
- بخشودگی جریمه
- اعمال تخفیف
- تسویه قسط
- لغو قسط دارای مبلغ
- اصلاح دستی مانده قسط

### Security Log الزامی برای:

- تلاش مشاهده قسط بدون Permission
- تلاش مشاهده قسط خارج از Scope
- تلاش تغییر مبلغ از Frontend
- تلاش تغییر status بدون Permission
- تلاش حذف قسط بدون Permission
- تلاش Export اقساط خارج از Scope
- CSRF نامعتبر
- دستکاری installment_id در درخواست‌ها

---

## Seedهای پیشنهادی

### installment status

```text
pending
partially_paid
paid
overdue
legal
settled
cancelled
deleted
```

### payment_status

```text
unpaid
partial
paid
overpaid
refunded
```

### followup_type

```text
reminder
overdue_followup
payment_request
legal_warning
settlement_negotiation
manual_note
```

### followup result

```text
successful
no_answer
wrong_number
promise_received
refused_to_pay
needs_followup
sent_to_legal
resolved
```

### payment promise status

```text
pending
active
fulfilled
partially_fulfilled
failed
cancelled
expired
deleted
```

### penalty status

```text
calculated
applied
waived
cancelled
adjusted
```

---

## چک‌لیست پیاده‌سازی

قبل از پیاده‌سازی Installment Tables بررسی شود:

- [ ] جدول `installments` ساخته شده است.
- [ ] `installment_number` یکتا است.
- [ ] ترکیب `contract_id` و `sequence_number` یکتا است.
- [ ] همه مبلغ‌ها DECIMAL هستند.
- [ ] قسط به contract_id و customer_id وصل است.
- [ ] جدول `installment_schedules` وجود دارد.
- [ ] جدول `installment_status_histories` وجود دارد.
- [ ] جدول `installment_followups` وجود دارد.
- [ ] جدول `payment_promises` وجود دارد.
- [ ] جدول `installment_penalties` وجود دارد.
- [ ] جدول `installment_snapshots` وجود دارد.
- [ ] تغییرات مالی Financial Log دارند.
- [ ] تغییرات حساس Audit Log دارند.
- [ ] تلاش‌های غیرمجاز Security Log دارند.
- [ ] Queryهای اقساط Scope را رعایت می‌کنند.
- [ ] گزارش معوقات Index مناسب دارد.
- [ ] Export اقساط Permission-based است.

---

## Definition of Done

Installment Tables زمانی کامل هستند که:

- اقساط قرارداد به صورت دقیق قابل تولید و ذخیره باشند.
- هر قسط شماره رسمی یکتا داشته باشد.
- هر قسط به قرارداد و مشتری وصل باشد.
- مبلغ قسط، پرداخت‌شده، مانده، جریمه و تخفیف قابل نگهداری باشد.
- وضعیت قسط قابل تغییر و تاریخچه آن قابل ردیابی باشد.
- معوق شدن قسط قابل تشخیص و ثبت باشد.
- پیگیری‌های اقساط قابل ثبت و گزارش‌گیری باشند.
- وعده‌های پرداخت قابل ثبت، تحقق، شکست و انقضا باشند.
- جریمه یا دیرکرد قابل ثبت، اعمال، بخشودگی و Audit باشد.
- Snapshotهای مهم قسط ذخیره شوند.
- پرداخت pending_review روی مبلغ قطعی اثر نگذارد.
- پرداخت approved بتواند قسط را partially_paid یا paid کند.
- قسط دارای پرداخت فیزیکی حذف نشود.
- اطلاعات اقساط بدون Permission نمایش یا Export نشود.
- تغییرات مالی Financial Log داشته باشند.
- عملیات حساس Audit Log داشته باشند.
- تلاش‌های غیرمجاز Security Log داشته باشند.
- جدول‌ها با MySQL/MariaDB و PHP 7.4+ سازگار باشند.
- Codex بتواند از روی این مستندات Migrationهای Installment Domain را بسازد.

---

## پایان فایل
````
