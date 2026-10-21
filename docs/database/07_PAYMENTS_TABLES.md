# 07 — Payments Tables

مستند جدول‌های پرداخت‌ها، رسیدها، کارت‌به‌کارت، درگاه پرداخت، تخصیص پرداخت به اقساط، بازپرداخت، تاریخچه وضعیت و داده‌های وابسته به Payment Domain در پروژه **Proma Pay**

---

## فهرست مطالب

- [هدف فایل](#هدف-فایل)
- [تعریف Payment Domain در دیتابیس](#تعریف-payment-domain-در-دیتابیس)
- [اصل مهم](#اصل-مهم)
- [لیست جدول‌های Payments](#لیست-جدولهای-payments)
- [جدول payments](#جدول-payments)
- [جدول payment_allocations](#جدول-payment_allocations)
- [جدول payment_receipts](#جدول-payment_receipts)
- [جدول card_to_card_reviews](#جدول-card_to_card_reviews)
- [جدول payment_gateway_transactions](#جدول-payment_gateway_transactions)
- [جدول payment_status_histories](#جدول-payment_status_histories)
- [جدول payment_refunds](#جدول-payment_refunds)
- [جدول payment_methods](#جدول-payment_methods)
- [رابطه پرداخت‌ها با سایر Domainها](#رابطه-پرداختها-با-سایر-domainها)
- [قوانین مالی پرداخت](#قوانین-مالی-پرداخت)
- [قوانین تخصیص پرداخت](#قوانین-تخصیص-پرداخت)
- [قوانین رسید کارت‌به‌کارت](#قوانین-رسید-کارتبهکارت)
- [قوانین درگاه پرداخت](#قوانین-درگاه-پرداخت)
- [قوانین وضعیت پرداخت](#قوانین-وضعیت-پرداخت)
- [قوانین بازپرداخت](#قوانین-بازپرداخت)
- [قوانین Soft Delete](#قوانین-soft-delete)
- [قوانین Index و Performance](#قوانین-index-و-performance)
- [قوانین Validation](#قوانین-validation)
- [قوانین Audit و Financial Log](#قوانین-audit-و-financial-log)
- [Seedهای پیشنهادی](#seedهای-پیشنهادی)
- [چک‌لیست پیاده‌سازی](#چکلیست-پیادهسازی)
- [Definition of Done](#definition-of-done)

---

## هدف فایل

هدف این فایل این است که ساختار جدول‌های مربوط به پرداخت‌ها در پروژه **Proma Pay** مشخص شود.

Payment Domain یکی از حساس‌ترین بخش‌های سیستم است؛ چون هر پرداخت می‌تواند وضعیت قرارداد، اقساط، مانده بدهی، تسویه، معوقات و پرونده حقوقی را تغییر دهد.

این فایل برای Codex مشخص می‌کند که:

- پرداخت‌ها چگونه ذخیره شوند.
- پرداخت کارت‌به‌کارت چگونه ثبت و بررسی شود.
- رسیدهای پرداخت چگونه در Files Domain نگهداری شوند.
- پرداخت درگاه چگونه ذخیره و Verify شود.
- پرداخت چگونه روی قرارداد و اقساط اعمال شود.
- پرداخت چگونه تأیید، رد، لغو یا برگشت داده شود.
- تغییر وضعیت پرداخت چگونه تاریخچه داشته باشد.
- عملیات مالی چه Financial Log و Audit Logهایی نیاز دارد.
- چه داده‌هایی حساس هستند و باید Permission داشته باشند.

---

## تعریف Payment Domain در دیتابیس

Payment Domain مسئول ثبت و مدیریت تمام پرداخت‌های مالی سیستم است.

پرداخت می‌تواند از مسیرهای مختلف انجام شود:

- پرداخت نقدی
- کارت‌به‌کارت
- درگاه بانکی
- پرداخت دستی توسط حسابدار
- کیف پول در آینده
- تهاتر یا اصلاح مالی
- بازپرداخت
- تسویه

پرداخت می‌تواند به موارد زیر مرتبط باشد:

- مشتری
- قرارداد
- قسط
- چند قسط
- پیش‌پرداخت قرارداد
- تسویه قرارداد
- جریمه
- تخفیف یا اصلاح مالی

---

## اصل مهم

اصل مهم در Payment Tables:

> پرداخت فقط زمانی باید روی مانده قرارداد یا قسط اثر بگذارد که وضعیت آن `approved` یا معادل قطعی آن باشد.

پرداخت‌های زیر نباید اثر قطعی مالی داشته باشند:

- `initiated`
- `waiting`
- `pending_review`
- `failed`
- `rejected`
- `cancelled`
- `expired`

قانون طلایی:

> رسید آپلودشده یا تراکنش درگاه ثبت‌شده به معنی پرداخت قطعی نیست؛ پرداخت قطعی فقط بعد از تأیید معتبر ثبت می‌شود.

---

## لیست جدول‌های Payments

جدول‌های پیشنهادی Payment Domain:

| جدول | کاربرد |
|---|---|
| `payments` | اطلاعات اصلی پرداخت |
| `payment_allocations` | تخصیص مبلغ پرداخت به قسط، قرارداد یا جریمه |
| `payment_receipts` | رسیدهای پرداخت و فایل‌های مرتبط |
| `card_to_card_reviews` | بررسی رسیدهای کارت‌به‌کارت |
| `payment_gateway_transactions` | تراکنش‌های درگاه پرداخت |
| `payment_status_histories` | تاریخچه تغییر وضعیت پرداخت |
| `payment_refunds` | برگشت وجه یا اصلاح پرداخت |
| `payment_methods` | روش‌های پرداخت قابل استفاده در سیستم |

---

## جدول payments

### هدف جدول

جدول `payments` اطلاعات اصلی هر پرداخت را نگهداری می‌کند.

این جدول منبع اصلی مبلغ پرداخت، وضعیت پرداخت، روش پرداخت و ارتباط آن با مشتری، قرارداد و اقساط است.

---

### نام جدول

```text
payments
```

---

### ستون‌های پیشنهادی

| ستون | نوع | توضیح |
|---|---|---|
| `id` | BIGINT UNSIGNED AUTO_INCREMENT | شناسه داخلی |
| `payment_number` | VARCHAR(50) | شماره رسمی پرداخت |
| `customer_id` | BIGINT UNSIGNED | مشتری |
| `contract_id` | BIGINT UNSIGNED NULL | قرارداد مرتبط |
| `installment_id` | BIGINT UNSIGNED NULL | قسط اصلی مرتبط در صورت وجود |
| `payment_method` | VARCHAR(50) | روش پرداخت |
| `payment_type` | VARCHAR(50) | نوع پرداخت |
| `amount` | DECIMAL(15,2) | مبلغ پرداخت |
| `allocated_amount` | DECIMAL(15,2) | مبلغ تخصیص‌یافته |
| `unallocated_amount` | DECIMAL(15,2) | مبلغ تخصیص‌نیافته |
| `currency` | VARCHAR(10) | واحد پول |
| `status` | VARCHAR(50) | وضعیت پرداخت |
| `review_status` | VARCHAR(50) NULL | وضعیت بررسی |
| `reference_number` | VARCHAR(191) NULL | کد پیگیری یا شماره مرجع |
| `tracking_code` | VARCHAR(191) NULL | کد رهگیری |
| `paid_at` | DATETIME NULL | زمان پرداخت اعلام‌شده |
| `received_at` | DATETIME NULL | زمان دریافت در سیستم |
| `approved_at` | DATETIME NULL | زمان تأیید |
| `approved_by` | BIGINT UNSIGNED NULL | تأییدکننده |
| `rejected_at` | DATETIME NULL | زمان رد |
| `rejected_by` | BIGINT UNSIGNED NULL | ردکننده |
| `rejection_reason` | TEXT NULL | دلیل رد |
| `cancelled_at` | DATETIME NULL | زمان لغو |
| `cancelled_by` | BIGINT UNSIGNED NULL | لغوکننده |
| `cancellation_reason` | TEXT NULL | دلیل لغو |
| `source` | VARCHAR(50) | منبع ثبت پرداخت |
| `description` | TEXT NULL | توضیح |
| `metadata` | JSON NULL | داده تکمیلی |
| `created_at` | DATETIME NULL | زمان ایجاد |
| `updated_at` | DATETIME NULL | زمان بروزرسانی |
| `deleted_at` | DATETIME NULL | زمان حذف نرم |
| `created_by` | BIGINT UNSIGNED NULL | ایجادکننده |
| `updated_by` | BIGINT UNSIGNED NULL | ویرایش‌کننده |
| `deleted_by` | BIGINT UNSIGNED NULL | حذف‌کننده |

---

### payment_methodهای پیشنهادی

```text
cash
card_to_card
bank_gateway
manual
wallet
refund_adjustment
settlement
other
```

---

### payment_typeهای پیشنهادی

```text
down_payment
installment_payment
partial_installment
full_settlement
penalty_payment
manual_adjustment
refund
other
```

---

### statusهای پیشنهادی

```text
initiated
waiting
pending_review
approved
failed
rejected
cancelled
expired
refunded
partially_refunded
deleted
```

---

### review_statusهای پیشنهادی

```text
not_required
pending
approved
rejected
needs_more_info
```

---

### sourceهای پیشنهادی

```text
admin
customer_panel
gateway
system
plugin
import
```

---

### قوانین

- `payment_number` باید Unique باشد.
- هر پرداخت باید customer_id داشته باشد.
- مبلغ پرداخت باید DECIMAL باشد.
- استفاده از FLOAT و DOUBLE برای مبلغ ممنوع است.
- پرداخت فقط در وضعیت `approved` روی قرارداد یا قسط اثر مالی قطعی بگذارد.
- پرداخت pending_review نباید مانده قسط را کاهش دهد.
- پرداخت rejected نباید اثر مالی داشته باشد.
- پرداخت approved نباید بدون Permission ویژه تغییر کند.
- تغییر مبلغ پرداخت approved باید ممنوع یا فقط با عملیات اصلاحی رسمی انجام شود.
- هر پرداخت approved باید Financial Log داشته باشد.
- هر تغییر وضعیت پرداخت باید در `payment_status_histories` ثبت شود.
- پرداخت حذف‌شده باید Soft Delete شود.
- پرداخت دارای اثر مالی نباید فیزیکی حذف شود.

---

### Indexهای پیشنهادی

```text
uniq_payments_payment_number
idx_payments_customer_id
idx_payments_contract_id
idx_payments_installment_id
idx_payments_payment_method
idx_payments_payment_type
idx_payments_status
idx_payments_review_status
idx_payments_reference_number
idx_payments_tracking_code
idx_payments_paid_at
idx_payments_approved_at
idx_payments_created_at
idx_payments_deleted_at
```

---

### نمونه ساختار SQL

```sql
CREATE TABLE payments (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    payment_number VARCHAR(50) NOT NULL,
    customer_id BIGINT UNSIGNED NOT NULL,
    contract_id BIGINT UNSIGNED NULL,
    installment_id BIGINT UNSIGNED NULL,
    payment_method VARCHAR(50) NOT NULL,
    payment_type VARCHAR(50) NOT NULL DEFAULT 'installment_payment',
    amount DECIMAL(15,2) NOT NULL DEFAULT 0,
    allocated_amount DECIMAL(15,2) NOT NULL DEFAULT 0,
    unallocated_amount DECIMAL(15,2) NOT NULL DEFAULT 0,
    currency VARCHAR(10) NOT NULL DEFAULT 'IRR',
    status VARCHAR(50) NOT NULL DEFAULT 'initiated',
    review_status VARCHAR(50) NULL,
    reference_number VARCHAR(191) NULL,
    tracking_code VARCHAR(191) NULL,
    paid_at DATETIME NULL,
    received_at DATETIME NULL,
    approved_at DATETIME NULL,
    approved_by BIGINT UNSIGNED NULL,
    rejected_at DATETIME NULL,
    rejected_by BIGINT UNSIGNED NULL,
    rejection_reason TEXT NULL,
    cancelled_at DATETIME NULL,
    cancelled_by BIGINT UNSIGNED NULL,
    cancellation_reason TEXT NULL,
    source VARCHAR(50) NOT NULL DEFAULT 'admin',
    description TEXT NULL,
    metadata JSON NULL,
    created_at DATETIME NULL,
    updated_at DATETIME NULL,
    deleted_at DATETIME NULL,
    created_by BIGINT UNSIGNED NULL,
    updated_by BIGINT UNSIGNED NULL,
    deleted_by BIGINT UNSIGNED NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uniq_payments_payment_number (payment_number),
    KEY idx_payments_customer_id (customer_id),
    KEY idx_payments_contract_id (contract_id),
    KEY idx_payments_installment_id (installment_id),
    KEY idx_payments_payment_method (payment_method),
    KEY idx_payments_payment_type (payment_type),
    KEY idx_payments_status (status),
    KEY idx_payments_review_status (review_status),
    KEY idx_payments_reference_number (reference_number),
    KEY idx_payments_tracking_code (tracking_code),
    KEY idx_payments_paid_at (paid_at),
    KEY idx_payments_approved_at (approved_at),
    KEY idx_payments_created_at (created_at),
    KEY idx_payments_deleted_at (deleted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

---

## جدول payment_allocations

### هدف جدول

جدول `payment_allocations` مشخص می‌کند یک پرداخت دقیقاً به چه چیزی تخصیص داده شده است.

یک پرداخت می‌تواند به یک یا چند مورد تخصیص داده شود:

- یک قسط
- چند قسط
- جریمه
- پیش‌پرداخت قرارداد
- تسویه قرارداد
- اصلاح مالی

---

### نام جدول

```text
payment_allocations
```

---

### ستون‌های پیشنهادی

| ستون | نوع | توضیح |
|---|---|---|
| `id` | BIGINT UNSIGNED AUTO_INCREMENT | شناسه |
| `payment_id` | BIGINT UNSIGNED | پرداخت |
| `customer_id` | BIGINT UNSIGNED | مشتری |
| `contract_id` | BIGINT UNSIGNED NULL | قرارداد |
| `installment_id` | BIGINT UNSIGNED NULL | قسط |
| `allocation_type` | VARCHAR(50) | نوع تخصیص |
| `amount` | DECIMAL(15,2) | مبلغ تخصیص |
| `status` | VARCHAR(50) | وضعیت تخصیص |
| `allocated_at` | DATETIME NULL | زمان تخصیص |
| `allocated_by` | BIGINT UNSIGNED NULL | تخصیص‌دهنده |
| `reversed_at` | DATETIME NULL | زمان برگشت تخصیص |
| `reversed_by` | BIGINT UNSIGNED NULL | برگشت‌دهنده |
| `reverse_reason` | TEXT NULL | دلیل برگشت |
| `metadata` | JSON NULL | داده تکمیلی |
| `created_at` | DATETIME NULL | زمان ایجاد |
| `updated_at` | DATETIME NULL | زمان بروزرسانی |

---

### allocation_typeهای پیشنهادی

```text
installment_principal
installment_penalty
contract_down_payment
contract_settlement
manual_adjustment
overpayment
refund
other
```

---

### statusهای پیشنهادی

```text
pending
applied
reversed
cancelled
```

---

### قوانین

- هر Allocation باید به payment_id وصل باشد.
- مجموع allocationهای applied نباید بیشتر از مبلغ payment approved باشد.
- Allocation فقط برای پرداخت approved اثر مالی قطعی داشته باشد.
- برگشت Allocation باید Financial Log داشته باشد.
- تخصیص به قسط باید paid_amount و remaining_amount قسط را بروزرسانی کند.
- تخصیص به قرارداد باید paid_amount و remaining_amount قرارداد را بروزرسانی کند.
- Allocation باید داخل Transaction انجام شود.
- Allocation نباید بدون Audit برای پرداخت approved تغییر کند.

---

### Indexهای پیشنهادی

```text
idx_payment_allocations_payment_id
idx_payment_allocations_customer_id
idx_payment_allocations_contract_id
idx_payment_allocations_installment_id
idx_payment_allocations_allocation_type
idx_payment_allocations_status
idx_payment_allocations_allocated_at
idx_payment_allocations_allocated_by
```

---

## جدول payment_receipts

### هدف جدول

جدول `payment_receipts` رسیدها و فایل‌های مرتبط با پرداخت را نگهداری می‌کند.

فایل واقعی رسید باید در Files Domain و Private Storage ذخیره شود.

---

### نام جدول

```text
payment_receipts
```

---

### ستون‌های پیشنهادی

| ستون | نوع | توضیح |
|---|---|---|
| `id` | BIGINT UNSIGNED AUTO_INCREMENT | شناسه |
| `payment_id` | BIGINT UNSIGNED | پرداخت |
| `customer_id` | BIGINT UNSIGNED | مشتری |
| `contract_id` | BIGINT UNSIGNED NULL | قرارداد |
| `file_id` | BIGINT UNSIGNED | فایل رسید |
| `receipt_type` | VARCHAR(50) | نوع رسید |
| `receipt_number` | VARCHAR(191) NULL | شماره رسید |
| `bank_name` | VARCHAR(100) NULL | نام بانک |
| `source_card_masked` | VARCHAR(50) NULL | شماره کارت مبدأ ماسک‌شده |
| `destination_card_masked` | VARCHAR(50) NULL | شماره کارت مقصد ماسک‌شده |
| `paid_at` | DATETIME NULL | زمان پرداخت روی رسید |
| `amount` | DECIMAL(15,2) NULL | مبلغ روی رسید |
| `status` | VARCHAR(50) | وضعیت رسید |
| `uploaded_by` | BIGINT UNSIGNED NULL | آپلودکننده |
| `uploaded_at` | DATETIME NULL | زمان آپلود |
| `reviewed_by` | BIGINT UNSIGNED NULL | بررسی‌کننده |
| `reviewed_at` | DATETIME NULL | زمان بررسی |
| `rejection_reason` | TEXT NULL | دلیل رد |
| `metadata` | JSON NULL | داده تکمیلی |
| `created_at` | DATETIME NULL | زمان ایجاد |
| `updated_at` | DATETIME NULL | زمان بروزرسانی |
| `deleted_at` | DATETIME NULL | زمان حذف نرم |
| `deleted_by` | BIGINT UNSIGNED NULL | حذف‌کننده |

---

### receipt_typeهای پیشنهادی

```text
card_to_card
bank_slip
gateway_receipt
cash_receipt
manual_receipt
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

- هر رسید باید payment_id داشته باشد.
- هر رسید باید file_id معتبر داشته باشد.
- فایل رسید باید Private باشد.
- دانلود رسید باید Permission و Audit Log داشته باشد.
- اطلاعات کارت باید Mask شود.
- شماره کامل کارت نباید بدون نیاز ذخیره شود.
- رسید به تنهایی پرداخت قطعی محسوب نمی‌شود.
- تأیید رسید باید وضعیت payment را هم طبق Workflow تغییر دهد.
- حذف رسید باید Soft Delete باشد.

---

### Indexهای پیشنهادی

```text
idx_payment_receipts_payment_id
idx_payment_receipts_customer_id
idx_payment_receipts_contract_id
idx_payment_receipts_file_id
idx_payment_receipts_receipt_type
idx_payment_receipts_status
idx_payment_receipts_uploaded_by
idx_payment_receipts_reviewed_by
idx_payment_receipts_uploaded_at
```

---

## جدول card_to_card_reviews

### هدف جدول

جدول `card_to_card_reviews` برای بررسی رسیدهای کارت‌به‌کارت استفاده می‌شود.

این جدول به حسابدار یا کاربر مجاز اجازه می‌دهد رسید را بررسی، تأیید یا رد کند.

---

### نام جدول

```text
card_to_card_reviews
```

---

### ستون‌های پیشنهادی

| ستون | نوع | توضیح |
|---|---|---|
| `id` | BIGINT UNSIGNED AUTO_INCREMENT | شناسه |
| `payment_id` | BIGINT UNSIGNED | پرداخت |
| `receipt_id` | BIGINT UNSIGNED NULL | رسید |
| `customer_id` | BIGINT UNSIGNED | مشتری |
| `contract_id` | BIGINT UNSIGNED NULL | قرارداد |
| `claimed_amount` | DECIMAL(15,2) | مبلغ ادعاشده |
| `verified_amount` | DECIMAL(15,2) NULL | مبلغ تأییدشده |
| `claimed_paid_at` | DATETIME NULL | زمان پرداخت اعلامی |
| `verified_paid_at` | DATETIME NULL | زمان پرداخت تأییدشده |
| `review_status` | VARCHAR(50) | وضعیت بررسی |
| `review_note` | TEXT NULL | توضیح بررسی |
| `reviewed_by` | BIGINT UNSIGNED NULL | بررسی‌کننده |
| `reviewed_at` | DATETIME NULL | زمان بررسی |
| `approved_at` | DATETIME NULL | زمان تأیید |
| `rejected_at` | DATETIME NULL | زمان رد |
| `rejection_reason` | TEXT NULL | دلیل رد |
| `duplicate_check_status` | VARCHAR(50) NULL | وضعیت بررسی تکراری بودن |
| `metadata` | JSON NULL | داده تکمیلی |
| `created_at` | DATETIME NULL | زمان ایجاد |
| `updated_at` | DATETIME NULL | زمان بروزرسانی |

---

### review_statusهای پیشنهادی

```text
pending
approved
rejected
needs_more_info
cancelled
```

---

### duplicate_check_statusهای پیشنهادی

```text
not_checked
unique
possible_duplicate
duplicate
```

---

### قوانین

- هر بررسی کارت‌به‌کارت باید payment_id داشته باشد.
- پرداخت کارت‌به‌کارت تا قبل از تأیید باید `pending_review` باشد.
- تأیید Review باید payment را approved کند.
- رد Review باید payment را rejected کند.
- verified_amount باید با مبلغ پرداخت نهایی سازگار باشد.
- تأیید یا رد رسید باید Audit و Financial Log مناسب داشته باشد.
- بررسی رسید تکراری باید با reference، مبلغ، تاریخ و تصویر قابل انجام باشد.
- رسید تکراری نباید دوباره approved شود.

---

### Indexهای پیشنهادی

```text
idx_card_to_card_reviews_payment_id
idx_card_to_card_reviews_receipt_id
idx_card_to_card_reviews_customer_id
idx_card_to_card_reviews_contract_id
idx_card_to_card_reviews_review_status
idx_card_to_card_reviews_reviewed_by
idx_card_to_card_reviews_duplicate_check_status
idx_card_to_card_reviews_created_at
```

---

## جدول payment_gateway_transactions

### هدف جدول

جدول `payment_gateway_transactions` تراکنش‌های درگاه پرداخت را ذخیره می‌کند.

این جدول داده‌های ارتباط با Gateway را نگهداری می‌کند و کمک می‌کند تراکنش‌ها Verify، Debug و Reconcile شوند.

---

### نام جدول

```text
payment_gateway_transactions
```

---

### ستون‌های پیشنهادی

| ستون | نوع | توضیح |
|---|---|---|
| `id` | BIGINT UNSIGNED AUTO_INCREMENT | شناسه |
| `payment_id` | BIGINT UNSIGNED | پرداخت |
| `customer_id` | BIGINT UNSIGNED | مشتری |
| `contract_id` | BIGINT UNSIGNED NULL | قرارداد |
| `gateway_key` | VARCHAR(100) | کلید درگاه |
| `gateway_name` | VARCHAR(191) NULL | نام درگاه |
| `authority` | VARCHAR(191) NULL | Authority یا شناسه شروع |
| `reference_id` | VARCHAR(191) NULL | کد مرجع |
| `tracking_code` | VARCHAR(191) NULL | کد رهگیری |
| `amount` | DECIMAL(15,2) | مبلغ |
| `status` | VARCHAR(50) | وضعیت تراکنش |
| `request_payload` | JSON NULL | داده درخواست |
| `callback_payload` | JSON NULL | داده Callback |
| `verify_payload` | JSON NULL | داده Verify |
| `error_code` | VARCHAR(100) NULL | کد خطا |
| `error_message` | TEXT NULL | پیام خطا |
| `requested_at` | DATETIME NULL | زمان درخواست |
| `callback_at` | DATETIME NULL | زمان Callback |
| `verified_at` | DATETIME NULL | زمان Verify |
| `failed_at` | DATETIME NULL | زمان شکست |
| `metadata` | JSON NULL | داده تکمیلی |
| `created_at` | DATETIME NULL | زمان ایجاد |
| `updated_at` | DATETIME NULL | زمان بروزرسانی |

---

### statusهای پیشنهادی

```text
initiated
redirected
callback_received
verified
failed
cancelled
expired
suspicious
```

---

### قوانین

- هر تراکنش درگاه باید payment_id داشته باشد.
- پرداخت درگاه فقط بعد از Verify موفق approved شود.
- Callback به تنهایی پرداخت قطعی نیست.
- Verify باید سمت سرور انجام شود.
- مبلغ Verify باید با مبلغ Payment برابر باشد.
- reference_id یا tracking_code در صورت موفقیت باید ثبت شود.
- خطای Gateway باید Log شود.
- داده حساس Gateway نباید خام و غیرضروری ذخیره شود.
- کلیدهای API در این جدول ذخیره نشوند.

---

### Indexهای پیشنهادی

```text
idx_payment_gateway_transactions_payment_id
idx_payment_gateway_transactions_customer_id
idx_payment_gateway_transactions_contract_id
idx_payment_gateway_transactions_gateway_key
idx_payment_gateway_transactions_authority
idx_payment_gateway_transactions_reference_id
idx_payment_gateway_transactions_tracking_code
idx_payment_gateway_transactions_status
idx_payment_gateway_transactions_requested_at
idx_payment_gateway_transactions_verified_at
```

---

## جدول payment_status_histories

### هدف جدول

جدول `payment_status_histories` تاریخچه تغییر وضعیت پرداخت را ذخیره می‌کند.

این جدول برای Audit، حسابداری، اختلافات مشتری و بررسی امنیتی ضروری است.

---

### نام جدول

```text
payment_status_histories
```

---

### ستون‌های پیشنهادی

| ستون | نوع | توضیح |
|---|---|---|
| `id` | BIGINT UNSIGNED AUTO_INCREMENT | شناسه |
| `payment_id` | BIGINT UNSIGNED | پرداخت |
| `customer_id` | BIGINT UNSIGNED | مشتری |
| `contract_id` | BIGINT UNSIGNED NULL | قرارداد |
| `old_status` | VARCHAR(50) NULL | وضعیت قبلی |
| `new_status` | VARCHAR(50) | وضعیت جدید |
| `old_review_status` | VARCHAR(50) NULL | وضعیت بررسی قبلی |
| `new_review_status` | VARCHAR(50) NULL | وضعیت بررسی جدید |
| `old_amount` | DECIMAL(15,2) NULL | مبلغ قبلی |
| `new_amount` | DECIMAL(15,2) NULL | مبلغ جدید |
| `reason` | TEXT NULL | دلیل تغییر |
| `changed_by` | BIGINT UNSIGNED NULL | تغییر دهنده |
| `changed_at` | DATETIME | زمان تغییر |
| `metadata` | JSON NULL | داده تکمیلی |

---

### قوانین

- هر تغییر مهم در payment.status باید ثبت شود.
- تغییر به approved باید ثبت شود.
- تغییر به rejected باید reason داشته باشد.
- تغییر مبلغ پرداخت باید ثبت شود.
- این جدول نباید Soft Delete شود.
- مشاهده تاریخچه پرداخت باید Permission داشته باشد.

---

### Indexهای پیشنهادی

```text
idx_payment_status_histories_payment_id
idx_payment_status_histories_customer_id
idx_payment_status_histories_contract_id
idx_payment_status_histories_new_status
idx_payment_status_histories_new_review_status
idx_payment_status_histories_changed_by
idx_payment_status_histories_changed_at
```

---

## جدول payment_refunds

### هدف جدول

جدول `payment_refunds` برای ثبت برگشت وجه یا اصلاح پرداخت استفاده می‌شود.

Refund می‌تواند کامل یا جزئی باشد.

---

### نام جدول

```text
payment_refunds
```

---

### ستون‌های پیشنهادی

| ستون | نوع | توضیح |
|---|---|---|
| `id` | BIGINT UNSIGNED AUTO_INCREMENT | شناسه |
| `refund_number` | VARCHAR(50) | شماره رسمی Refund |
| `payment_id` | BIGINT UNSIGNED | پرداخت اصلی |
| `customer_id` | BIGINT UNSIGNED | مشتری |
| `contract_id` | BIGINT UNSIGNED NULL | قرارداد |
| `amount` | DECIMAL(15,2) | مبلغ برگشت |
| `refund_method` | VARCHAR(50) | روش برگشت |
| `reason` | TEXT | دلیل برگشت |
| `status` | VARCHAR(50) | وضعیت |
| `requested_by` | BIGINT UNSIGNED NULL | درخواست‌دهنده |
| `requested_at` | DATETIME NULL | زمان درخواست |
| `approved_by` | BIGINT UNSIGNED NULL | تأییدکننده |
| `approved_at` | DATETIME NULL | زمان تأیید |
| `processed_by` | BIGINT UNSIGNED NULL | انجام‌دهنده |
| `processed_at` | DATETIME NULL | زمان انجام |
| `rejected_by` | BIGINT UNSIGNED NULL | ردکننده |
| `rejected_at` | DATETIME NULL | زمان رد |
| `rejection_reason` | TEXT NULL | دلیل رد |
| `reference_number` | VARCHAR(191) NULL | شماره مرجع برگشت |
| `metadata` | JSON NULL | داده تکمیلی |
| `created_at` | DATETIME NULL | زمان ایجاد |
| `updated_at` | DATETIME NULL | زمان بروزرسانی |

---

### refund_methodهای پیشنهادی

```text
cash
card_to_card
bank_transfer
gateway_refund
manual_adjustment
other
```

---

### statusهای پیشنهادی

```text
requested
approved
processed
rejected
cancelled
failed
```

---

### قوانین

- refund_number باید Unique باشد.
- Refund باید به payment_id وصل باشد.
- مجموع Refundهای processed نباید بیشتر از مبلغ پرداخت approved باشد.
- Refund باید Financial Log داشته باشد.
- Refund باید اثر مالی پرداخت را اصلاح کند.
- Refund پرداخت تخصیص‌یافته باید Allocationها را هم اصلاح کند.
- Refund باید داخل Transaction انجام شود.
- Refund نباید بدون دلیل ثبت شود.
- Refund باید Audit Log داشته باشد.

---

### Indexهای پیشنهادی

```text
uniq_payment_refunds_refund_number
idx_payment_refunds_payment_id
idx_payment_refunds_customer_id
idx_payment_refunds_contract_id
idx_payment_refunds_status
idx_payment_refunds_refund_method
idx_payment_refunds_requested_by
idx_payment_refunds_processed_at
idx_payment_refunds_created_at
```

---

## جدول payment_methods

### هدف جدول

جدول `payment_methods` روش‌های پرداخت فعال سیستم را نگهداری می‌کند.

این جدول برای مدیریت فعال یا غیرفعال بودن روش‌هایی مثل کارت‌به‌کارت، نقدی یا درگاه پرداخت استفاده می‌شود.

---

### نام جدول

```text
payment_methods
```

---

### ستون‌های پیشنهادی

| ستون | نوع | توضیح |
|---|---|---|
| `id` | BIGINT UNSIGNED AUTO_INCREMENT | شناسه |
| `method_key` | VARCHAR(100) | کلید روش پرداخت |
| `name` | VARCHAR(191) | نام نمایشی |
| `description` | TEXT NULL | توضیح |
| `is_active` | TINYINT(1) | فعال بودن |
| `requires_receipt` | TINYINT(1) | نیاز به رسید |
| `requires_review` | TINYINT(1) | نیاز به بررسی |
| `is_online` | TINYINT(1) | آنلاین بودن |
| `sort_order` | INT UNSIGNED | ترتیب نمایش |
| `settings` | JSON NULL | تنظیمات روش پرداخت |
| `created_at` | DATETIME NULL | زمان ایجاد |
| `updated_at` | DATETIME NULL | زمان بروزرسانی |

---

### method_keyهای پیشنهادی

```text
cash
card_to_card
bank_gateway
manual
wallet
```

---

### قوانین

- method_key باید Unique باشد.
- تغییر وضعیت روش پرداخت باید Audit Log داشته باشد.
- روش کارت‌به‌کارت معمولاً requires_receipt و requires_review دارد.
- روش درگاه پرداخت is_online دارد.
- تنظیمات حساس درگاه نباید داخل settings خام ذخیره شود.
- کلید API درگاه باید در Settings امن یا encrypted ذخیره شود.

---

### Indexهای پیشنهادی

```text
uniq_payment_methods_method_key
idx_payment_methods_is_active
idx_payment_methods_requires_receipt
idx_payment_methods_requires_review
idx_payment_methods_is_online
idx_payment_methods_sort_order
```

---

## رابطه پرداخت‌ها با سایر Domainها

Payment Domain با بخش‌های زیادی از سیستم ارتباط دارد.

| Domain | رابطه |
|---|---|
| Customers | هر پرداخت متعلق به یک مشتری است |
| Contracts | پرداخت می‌تواند روی قرارداد اعمال شود |
| Installments | پرداخت می‌تواند روی یک یا چند قسط تخصیص داده شود |
| Financial | پرداخت approved باعث Financial Log می‌شود |
| Files | رسیدهای پرداخت در Files Domain ذخیره می‌شوند |
| Reports | گزارش پرداخت‌ها و مالی از Payment استفاده می‌کند |
| Notifications | تأیید، رد یا دریافت پرداخت می‌تواند Notification ایجاد کند |
| Legal | پرداخت روی قرارداد حقوقی می‌تواند وضعیت پرونده را تغییر دهد |
| Audit | عملیات حساس پرداخت ثبت می‌شود |
| Security | تلاش غیرمجاز برای مشاهده یا تغییر پرداخت ثبت می‌شود |
| Plugins | درگاه‌های پرداخت می‌توانند از طریق پلاگین اضافه شوند |

---

## قوانین مالی پرداخت

قوانین:

- همه مبلغ‌ها باید DECIMAL باشند.
- پرداخت فقط بعد از approved اثر مالی داشته باشد.
- پرداخت approved باید Financial Log داشته باشد.
- پرداخت rejected نباید روی مانده اثر بگذارد.
- پرداخت pending_review نباید روی مانده اثر قطعی داشته باشد.
- تغییر مبلغ پرداخت approved ممنوع یا بسیار محدود باشد.
- اصلاح پرداخت باید با Refund یا Financial Adjustment انجام شود.
- پرداخت overpayment باید به شکل مشخص مدیریت شود.
- تسویه قرارداد باید payment_type مشخص داشته باشد.
- عملیات مالی پرداخت باید داخل Transaction انجام شود.

---

## قوانین تخصیص پرداخت

قوانین:

- پرداخت می‌تواند به یک یا چند قسط تخصیص داده شود.
- تخصیص باید بعد از تأیید پرداخت انجام شود.
- مجموع تخصیص‌ها نباید از مبلغ پرداخت بیشتر شود.
- Allocation باید وضعیت قسط و قرارداد را بروزرسانی کند.
- Allocation باید Snapshot قسط یا قرارداد ایجاد کند.
- برگشت Allocation باید Financial Log داشته باشد.
- Allocation نباید بدون Permission و Audit تغییر کند.

---

## قوانین رسید کارت‌به‌کارت

قوانین:

- رسید کارت‌به‌کارت باید فایل داشته باشد.
- فایل رسید باید در Private Storage ذخیره شود.
- کارت‌ها باید Mask شوند.
- مشاهده و دانلود رسید باید Permission داشته باشد.
- رسید باید توسط کاربر مجاز بررسی شود.
- رسید تکراری نباید دوباره تأیید شود.
- رد رسید باید دلیل داشته باشد.
- تأیید رسید باید payment را approved کند.
- رد رسید باید payment را rejected کند.
- تأیید یا رد رسید باید Audit Log داشته باشد.

---

## قوانین درگاه پرداخت

قوانین:

- پرداخت درگاه با initiated شروع می‌شود.
- Callback به تنهایی پرداخت قطعی نیست.
- Verify سمت سرور الزامی است.
- مبلغ Verify باید با مبلغ Payment یکی باشد.
- تراکنش failed نباید اثر مالی داشته باشد.
- تراکنش verified باید payment را approved کند.
- خطاهای Gateway باید Log شوند.
- اطلاعات محرمانه درگاه نباید در جدول تراکنش ذخیره شود.
- پلاگین درگاه نباید Core Payment Logic را دور بزند.

---

## قوانین وضعیت پرداخت

Transitionهای پیشنهادی:

```text
initiated -> waiting
waiting -> pending_review
pending_review -> approved
pending_review -> rejected
initiated -> failed
waiting -> failed
initiated -> expired
approved -> partially_refunded
approved -> refunded
approved -> cancelled فقط با اصلاح رسمی و محدود
```

قوانین:

- Transition نامعتبر باید رد شود.
- تغییر به approved باید Financial Log داشته باشد.
- تغییر به rejected باید rejection_reason داشته باشد.
- تغییر به cancelled باید cancellation_reason داشته باشد.
- پرداخت approved نباید عادی به pending یا rejected برگردد.
- اصلاح پرداخت approved باید با Refund یا Adjustment انجام شود.
- هر تغییر status باید history داشته باشد.

---

## قوانین بازپرداخت

قوانین:

- Refund فقط برای پرداخت approved مجاز است.
- Refund می‌تواند کامل یا جزئی باشد.
- مجموع Refundها نباید از مبلغ پرداخت بیشتر شود.
- Refund باید Allocationها را اصلاح کند.
- Refund باید Financial Log داشته باشد.
- Refund باید Audit Log داشته باشد.
- Refund باید reason داشته باشد.
- Refund processed باید وضعیت پرداخت را refunded یا partially_refunded کند.

---

## قوانین Soft Delete

جدول‌های زیر باید Soft Delete داشته باشند:

- `payments`
- `payment_receipts`

قوانین:

- پرداخت approved نباید فیزیکی حذف شود.
- پرداخت دارای Allocation نباید فیزیکی حذف شود.
- پرداخت rejected یا failed می‌تواند Soft Delete شود.
- رسید پرداخت باید Soft Delete شود.
- تاریخچه وضعیت پرداخت حذف نشود.
- Refund حذف نشود؛ وضعیت آن باید cancelled یا failed شود.

---

## قوانین Index و Performance

قوانین:

- جستجوی payment_number باید سریع باشد.
- گزارش پرداخت‌ها باید بر اساس تاریخ، وضعیت، مشتری و قرارداد سریع باشد.
- بررسی رسیدهای pending_review باید سریع باشد.
- تراکنش درگاه باید با authority، reference_id و tracking_code قابل جستجو باشد.
- گزارش مالی باید payment.status و approved_at را Index داشته باشد.
- Export پرداخت‌ها باید محدود و Permission-based باشد.
- Queryهای تخصیص پرداخت باید با payment_id و installment_id سریع باشند.

Indexهای مهم:

```text
payments.payment_number
payments.customer_id
payments.contract_id
payments.installment_id
payments.status
payments.payment_method
payments.approved_at
payment_allocations.payment_id
payment_allocations.installment_id
payment_receipts.payment_id
card_to_card_reviews.review_status
payment_gateway_transactions.authority
payment_gateway_transactions.reference_id
payment_status_histories.payment_id
payment_refunds.payment_id
```

---

## قوانین Validation

### payments

- customer_id الزامی است.
- amount باید بیشتر از صفر باشد.
- payment_method الزامی و معتبر باشد.
- payment_type باید معتبر باشد.
- status باید معتبر باشد.
- payment_number باید یکتا باشد.
- پرداخت قسط باید contract_id یا installment_id معتبر داشته باشد.
- پرداخت approved باید approved_by و approved_at داشته باشد.
- پرداخت rejected باید rejection_reason داشته باشد.

### payment_allocations

- payment_id الزامی است.
- amount باید بیشتر از صفر باشد.
- allocation_type باید معتبر باشد.
- تخصیص به قسط باید installment_id داشته باشد.
- مجموع Allocationها نباید از مبلغ پرداخت بیشتر شود.

### payment_receipts

- payment_id الزامی است.
- file_id الزامی است.
- receipt_type معتبر باشد.
- amount در صورت ثبت باید غیرمنفی باشد.
- فایل رسید باید Private باشد.

### card_to_card_reviews

- payment_id الزامی است.
- claimed_amount باید بیشتر از صفر باشد.
- review_status باید معتبر باشد.
- approved باید verified_amount و reviewed_by داشته باشد.
- rejected باید rejection_reason داشته باشد.

### payment_gateway_transactions

- payment_id الزامی است.
- gateway_key الزامی است.
- amount باید بیشتر از صفر باشد.
- status باید معتبر باشد.
- verified status باید reference_id یا tracking_code معتبر داشته باشد.

### payment_refunds

- payment_id الزامی است.
- refund_number یکتا باشد.
- amount باید بیشتر از صفر باشد.
- reason الزامی است.
- processed Refund باید processed_by و processed_at داشته باشد.

---

## قوانین Audit و Financial Log

### Audit Log الزامی برای:

- ایجاد پرداخت دستی
- تغییر مبلغ پرداخت
- تأیید پرداخت
- رد پرداخت
- لغو پرداخت
- حذف نرم پرداخت
- آپلود رسید
- دانلود رسید
- تأیید یا رد رسید کارت‌به‌کارت
- تخصیص پرداخت به اقساط
- برگشت Allocation
- ثبت Refund
- تأیید Refund
- انجام Refund
- تغییر روش پرداخت
- Export گزارش پرداخت‌ها

### Financial Log الزامی برای:

- approved شدن پرداخت
- اعمال پرداخت روی قسط
- اعمال پرداخت روی قرارداد
- پرداخت پیش‌پرداخت
- پرداخت تسویه
- اعمال جریمه پرداخت‌شده
- برگشت پرداخت
- Refund کامل یا جزئی
- اصلاح دستی پرداخت
- برگشت Allocation

### Security Log الزامی برای:

- تلاش مشاهده پرداخت بدون Permission
- تلاش مشاهده پرداخت خارج از Scope
- تلاش تأیید پرداخت بدون Permission
- تلاش رد پرداخت بدون Permission
- تلاش تغییر مبلغ پرداخت از Frontend
- تلاش دانلود رسید بدون Permission
- تلاش تأیید رسید تکراری
- CSRF نامعتبر
- دستکاری payment_id در درخواست‌ها
- تلاش Export پرداخت‌های خارج از Scope

---

## Seedهای پیشنهادی

### payment_method

```text
cash
card_to_card
bank_gateway
manual
wallet
refund_adjustment
settlement
other
```

### payment_type

```text
down_payment
installment_payment
partial_installment
full_settlement
penalty_payment
manual_adjustment
refund
other
```

### payment status

```text
initiated
waiting
pending_review
approved
failed
rejected
cancelled
expired
refunded
partially_refunded
deleted
```

### review_status

```text
not_required
pending
approved
rejected
needs_more_info
```

### receipt_type

```text
card_to_card
bank_slip
gateway_receipt
cash_receipt
manual_receipt
other
```

### refund_status

```text
requested
approved
processed
rejected
cancelled
failed
```

---

## چک‌لیست پیاده‌سازی

قبل از پیاده‌سازی Payment Tables بررسی شود:

- [ ] جدول `payments` ساخته شده است.
- [ ] `payment_number` یکتا است.
- [ ] همه مبلغ‌ها DECIMAL هستند.
- [ ] پرداخت به customer_id وصل است.
- [ ] پرداخت می‌تواند به contract_id و installment_id وصل شود.
- [ ] پرداخت pending_review اثر مالی قطعی ندارد.
- [ ] پرداخت approved Financial Log دارد.
- [ ] جدول `payment_allocations` وجود دارد.
- [ ] تخصیص پرداخت به اقساط داخل Transaction انجام می‌شود.
- [ ] جدول `payment_receipts` وجود دارد.
- [ ] فایل رسید در Files Domain و Private Storage ذخیره می‌شود.
- [ ] جدول `card_to_card_reviews` وجود دارد.
- [ ] تأیید رسید کارت‌به‌کارت payment را approved می‌کند.
- [ ] رد رسید کارت‌به‌کارت payment را rejected می‌کند.
- [ ] جدول `payment_gateway_transactions` وجود دارد.
- [ ] درگاه پرداخت فقط بعد از Verify موفق approved می‌شود.
- [ ] جدول `payment_status_histories` وجود دارد.
- [ ] جدول `payment_refunds` وجود دارد.
- [ ] جدول `payment_methods` وجود دارد.
- [ ] عملیات حساس Audit Log دارند.
- [ ] تلاش‌های غیرمجاز Security Log دارند.
- [ ] Queryهای پرداخت Scope را رعایت می‌کنند.
- [ ] Export پرداخت‌ها Permission-based است.

---

## Definition of Done

Payment Tables زمانی کامل هستند که:

- پرداخت با شماره رسمی یکتا قابل ثبت باشد.
- هر پرداخت به مشتری وصل باشد.
- پرداخت بتواند به قرارداد و قسط وصل شود.
- پرداخت فقط بعد از approved اثر مالی داشته باشد.
- پرداخت pending_review، failed و rejected اثر مالی نداشته باشند.
- پرداخت کارت‌به‌کارت رسید و Review داشته باشد.
- رسید پرداخت در Private Storage و Files Domain مدیریت شود.
- درگاه پرداخت تراکنش، Callback و Verify قابل ثبت داشته باشد.
- پرداخت بتواند به یک یا چند قسط تخصیص داده شود.
- تخصیص پرداخت وضعیت قسط و قرارداد را بروزرسانی کند.
- Refund کامل و جزئی قابل ثبت و پردازش باشد.
- تغییر وضعیت پرداخت در history ثبت شود.
- پرداخت approved بدون عملیات اصلاحی رسمی تغییر نکند.
- عملیات مالی Financial Log داشته باشند.
- عملیات حساس Audit Log داشته باشند.
- تلاش‌های غیرمجاز Security Log داشته باشند.
- اطلاعات رسید و کارت بدون Permission نمایش یا Export نشود.
- جدول‌ها با MySQL/MariaDB و PHP 7.4+ سازگار باشند.
- Codex بتواند از روی این مستندات Migrationهای Payment Domain را بسازد.

---

## پایان فایل
````
