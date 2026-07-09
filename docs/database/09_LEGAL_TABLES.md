# 09 — Legal Tables

مستند جدول‌های پرونده‌های حقوقی، اقساط ارجاع‌شده، اقدامات حقوقی، مدارک حقوقی، هزینه‌ها، مهلت‌ها، تاریخچه وضعیت و داده‌های وابسته به Legal Domain در پروژه **Proma Pay**

---

## فهرست مطالب

- [هدف فایل](#هدف-فایل)
- [تعریف Legal Domain در دیتابیس](#تعریف-legal-domain-در-دیتابیس)
- [اصل مهم](#اصل-مهم)
- [لیست جدول‌های Legal](#لیست-جدولهای-legal)
- [جدول legal_cases](#جدول-legal_cases)
- [جدول legal_case_installments](#جدول-legal_case_installments)
- [جدول legal_claims](#جدول-legal_claims)
- [جدول legal_actions](#جدول-legal_actions)
- [جدول legal_documents](#جدول-legal_documents)
- [جدول legal_deadlines](#جدول-legal_deadlines)
- [جدول legal_costs](#جدول-legal_costs)
- [جدول legal_status_histories](#جدول-legal_status_histories)
- [جدول legal_notes](#جدول-legal_notes)
- [رابطه Legal Domain با سایر Domainها](#رابطه-legal-domain-با-سایر-domainها)
- [قوانین Snapshot حقوقی](#قوانین-snapshot-حقوقی)
- [قوانین مبلغ و بدهی حقوقی](#قوانین-مبلغ-و-بدهی-حقوقی)
- [قوانین مدارک حقوقی](#قوانین-مدارک-حقوقی)
- [قوانین وضعیت پرونده حقوقی](#قوانین-وضعیت-پرونده-حقوقی)
- [قوانین مهلت‌های حقوقی](#قوانین-مهلتهای-حقوقی)
- [قوانین Soft Delete](#قوانین-soft-delete)
- [قوانین Index و Performance](#قوانین-index-و-performance)
- [قوانین Validation](#قوانین-validation)
- [قوانین Audit و Security Log](#قوانین-audit-و-security-log)
- [Seedهای پیشنهادی](#seedهای-پیشنهادی)
- [چک‌لیست پیاده‌سازی](#چکلیست-پیادهسازی)
- [Definition of Done](#definition-of-done)

---

## هدف فایل

هدف این فایل این است که ساختار جدول‌های مربوط به پرونده‌های حقوقی در پروژه **Proma Pay** مشخص شود.

Legal Domain زمانی استفاده می‌شود که مشتری در پرداخت اقساط، تسویه قرارداد یا انجام تعهدات خود دچار تأخیر جدی شده و پرونده برای پیگیری حقوقی، وصول ضمانت، مطالبه وجه یا اقدامات قانونی داخلی آماده می‌شود.

این فایل برای Codex مشخص می‌کند که:

- پرونده حقوقی چگونه ذخیره شود.
- اقساط معوق چگونه به پرونده حقوقی وصل شوند.
- مبلغ بدهی هنگام ارجاع چگونه Snapshot شود.
- اقدامات حقوقی چگونه ثبت شوند.
- مدارک حقوقی چگونه در Files Domain نگهداری شوند.
- مهلت‌ها، جلسات و یادآوری‌های حقوقی چگونه ذخیره شوند.
- هزینه‌های حقوقی چگونه ثبت شوند.
- تغییر وضعیت پرونده چگونه قابل ردیابی باشد.
- چه عملیات‌هایی نیاز به Audit Log، Financial Log یا Security Log دارند.

---

## تعریف Legal Domain در دیتابیس

Legal Domain مسئول نگهداری اطلاعات مربوط به پرونده‌های حقوقی و پیگیری‌های رسمی یا نیمه‌رسمی قراردادهای مشکل‌دار است.

یک پرونده حقوقی می‌تواند شامل موارد زیر باشد:

- مشتری
- قرارداد
- اقساط معوق
- مبلغ بدهی هنگام ارجاع
- جریمه یا دیرکرد ثبت‌شده
- ضمانت‌های قابل استفاده
- چک یا سفته
- مدارک قرارداد
- اقدامات حقوقی انجام‌شده
- وکیل یا مسئول پرونده
- وضعیت پرونده
- مهلت‌ها و جلسات
- هزینه‌های حقوقی
- نتیجه پرونده

---

## اصل مهم

اصل مهم در Legal Tables:

> پرونده حقوقی باید بر اساس Snapshot دقیق مالی و مدارک معتبر ساخته شود، نه بر اساس محاسبات لحظه‌ای و غیرقابل ردیابی.

وقتی یک قرارداد یا قسط وارد مسیر حقوقی می‌شود، سیستم باید وضعیت مالی آن لحظه را ثبت کند.

موارد حساس:

- مبلغ بدهی هنگام ارجاع
- تعداد اقساط معوق
- مبلغ جریمه
- مدارک قرارداد
- مدارک ضمانت
- اطلاعات ضامن
- اقدامات حقوقی
- نتیجه پرونده
- فایل‌های دادخواست، اظهارنامه، رأی یا ابلاغیه
- یادداشت داخلی وکیل یا مدیر

---

## لیست جدول‌های Legal

جدول‌های پیشنهادی Legal Domain:

| جدول | کاربرد |
|---|---|
| `legal_cases` | اطلاعات اصلی پرونده حقوقی |
| `legal_case_installments` | اقساط مرتبط با پرونده حقوقی |
| `legal_claims` | خواسته‌ها یا مطالبات پرونده |
| `legal_actions` | اقدامات حقوقی انجام‌شده |
| `legal_documents` | مدارک و فایل‌های حقوقی |
| `legal_deadlines` | مهلت‌ها، جلسات و یادآوری‌های حقوقی |
| `legal_costs` | هزینه‌های حقوقی |
| `legal_status_histories` | تاریخچه تغییر وضعیت پرونده |
| `legal_notes` | یادداشت‌های داخلی حقوقی |

---

## جدول legal_cases

### هدف جدول

جدول `legal_cases` اطلاعات اصلی پرونده حقوقی را نگهداری می‌کند.

این جدول منبع اصلی وضعیت حقوقی قرارداد و مشتری است.

---

### نام جدول

```text
legal_cases
```

---

### ستون‌های پیشنهادی

| ستون | نوع | توضیح |
|---|---|---|
| `id` | BIGINT UNSIGNED AUTO_INCREMENT | شناسه داخلی |
| `legal_case_number` | VARCHAR(50) | شماره رسمی پرونده |
| `customer_id` | BIGINT UNSIGNED | مشتری |
| `contract_id` | BIGINT UNSIGNED | قرارداد |
| `case_type` | VARCHAR(50) | نوع پرونده |
| `case_priority` | VARCHAR(50) | اولویت پرونده |
| `status` | VARCHAR(50) | وضعیت پرونده |
| `legal_stage` | VARCHAR(50) | مرحله حقوقی |
| `referral_reason` | TEXT | دلیل ارجاع |
| `referred_by` | BIGINT UNSIGNED NULL | ارجاع‌دهنده |
| `referred_at` | DATETIME | زمان ارجاع |
| `assigned_lawyer_id` | BIGINT UNSIGNED NULL | وکیل یا مسئول حقوقی |
| `assigned_at` | DATETIME NULL | زمان ارجاع به مسئول |
| `debt_amount_at_referral` | DECIMAL(15,2) | بدهی هنگام ارجاع |
| `paid_amount_at_referral` | DECIMAL(15,2) | مبلغ پرداخت‌شده هنگام ارجاع |
| `remaining_amount_at_referral` | DECIMAL(15,2) | مانده هنگام ارجاع |
| `penalty_amount_at_referral` | DECIMAL(15,2) | جریمه هنگام ارجاع |
| `overdue_installments_count` | INT UNSIGNED | تعداد اقساط معوق |
| `first_overdue_date` | DATE NULL | اولین تاریخ معوقه |
| `last_overdue_date` | DATE NULL | آخرین تاریخ معوقه |
| `claim_amount` | DECIMAL(15,2) NULL | مبلغ خواسته یا مطالبه |
| `settlement_amount` | DECIMAL(15,2) NULL | مبلغ توافق یا تسویه |
| `closed_at` | DATETIME NULL | زمان بسته شدن پرونده |
| `closed_by` | BIGINT UNSIGNED NULL | بستن پرونده توسط |
| `close_reason` | TEXT NULL | دلیل بسته شدن |
| `result` | VARCHAR(50) NULL | نتیجه پرونده |
| `financial_snapshot_id` | BIGINT UNSIGNED NULL | Snapshot مالی |
| `metadata` | JSON NULL | داده تکمیلی |
| `created_at` | DATETIME NULL | زمان ایجاد |
| `updated_at` | DATETIME NULL | زمان بروزرسانی |
| `deleted_at` | DATETIME NULL | زمان حذف نرم |
| `created_by` | BIGINT UNSIGNED NULL | ایجادکننده |
| `updated_by` | BIGINT UNSIGNED NULL | ویرایش‌کننده |
| `deleted_by` | BIGINT UNSIGNED NULL | حذف‌کننده |

---

### case_typeهای پیشنهادی

```text
installment_default
payment_default
guarantee_collection
device_return
contract_claim
settlement_dispute
manual
other
```

---

### case_priorityهای پیشنهادی

```text
low
normal
high
critical
```

---

### statusهای پیشنهادی

```text
draft
referred
under_review
in_progress
waiting_customer
waiting_court
settlement_negotiation
settled
closed
cancelled
deleted
```

---

### legal_stageهای پیشنهادی

```text
internal_followup
warning_sent
notice_prepared
notice_sent
petition_prepared
filed
court_process
judgment_received
execution
closed
```

---

### resultهای پیشنهادی

```text
recovered
settled
cancelled
unrecoverable
rejected
won
lost
closed_without_action
```

---

### قوانین

- `legal_case_number` باید Unique باشد.
- هر پرونده باید customer_id و contract_id داشته باشد.
- پرونده باید دلیل ارجاع داشته باشد.
- هنگام ارجاع باید Snapshot مالی ثبت شود.
- مبلغ‌های هنگام ارجاع باید با DECIMAL ذخیره شوند.
- پرونده حقوقی نباید فقط با محاسبه زنده مانده قرارداد ساخته شود.
- تغییر وضعیت پرونده باید در `legal_status_histories` ثبت شود.
- بستن پرونده باید close_reason داشته باشد.
- پرونده closed نباید بدون Permission ویژه تغییر کند.
- اطلاعات پرونده حقوقی فقط برای نقش مجاز قابل مشاهده باشد.
- حذف پرونده باید Soft Delete باشد.
- پرونده دارای اقدام حقوقی یا مدارک نباید فیزیکی حذف شود.

---

### Indexهای پیشنهادی

```text
uniq_legal_cases_legal_case_number
idx_legal_cases_customer_id
idx_legal_cases_contract_id
idx_legal_cases_case_type
idx_legal_cases_status
idx_legal_cases_legal_stage
idx_legal_cases_case_priority
idx_legal_cases_assigned_lawyer_id
idx_legal_cases_referred_at
idx_legal_cases_closed_at
idx_legal_cases_deleted_at
```

---

### نمونه ساختار SQL

```sql
CREATE TABLE legal_cases (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    legal_case_number VARCHAR(50) NOT NULL,
    customer_id BIGINT UNSIGNED NOT NULL,
    contract_id BIGINT UNSIGNED NOT NULL,
    case_type VARCHAR(50) NOT NULL DEFAULT 'installment_default',
    case_priority VARCHAR(50) NOT NULL DEFAULT 'normal',
    status VARCHAR(50) NOT NULL DEFAULT 'draft',
    legal_stage VARCHAR(50) NOT NULL DEFAULT 'internal_followup',
    referral_reason TEXT NOT NULL,
    referred_by BIGINT UNSIGNED NULL,
    referred_at DATETIME NOT NULL,
    assigned_lawyer_id BIGINT UNSIGNED NULL,
    assigned_at DATETIME NULL,
    debt_amount_at_referral DECIMAL(15,2) NOT NULL DEFAULT 0,
    paid_amount_at_referral DECIMAL(15,2) NOT NULL DEFAULT 0,
    remaining_amount_at_referral DECIMAL(15,2) NOT NULL DEFAULT 0,
    penalty_amount_at_referral DECIMAL(15,2) NOT NULL DEFAULT 0,
    overdue_installments_count INT UNSIGNED NOT NULL DEFAULT 0,
    first_overdue_date DATE NULL,
    last_overdue_date DATE NULL,
    claim_amount DECIMAL(15,2) NULL,
    settlement_amount DECIMAL(15,2) NULL,
    closed_at DATETIME NULL,
    closed_by BIGINT UNSIGNED NULL,
    close_reason TEXT NULL,
    result VARCHAR(50) NULL,
    financial_snapshot_id BIGINT UNSIGNED NULL,
    metadata JSON NULL,
    created_at DATETIME NULL,
    updated_at DATETIME NULL,
    deleted_at DATETIME NULL,
    created_by BIGINT UNSIGNED NULL,
    updated_by BIGINT UNSIGNED NULL,
    deleted_by BIGINT UNSIGNED NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uniq_legal_cases_legal_case_number (legal_case_number),
    KEY idx_legal_cases_customer_id (customer_id),
    KEY idx_legal_cases_contract_id (contract_id),
    KEY idx_legal_cases_case_type (case_type),
    KEY idx_legal_cases_status (status),
    KEY idx_legal_cases_legal_stage (legal_stage),
    KEY idx_legal_cases_case_priority (case_priority),
    KEY idx_legal_cases_assigned_lawyer_id (assigned_lawyer_id),
    KEY idx_legal_cases_referred_at (referred_at),
    KEY idx_legal_cases_closed_at (closed_at),
    KEY idx_legal_cases_deleted_at (deleted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

---

## جدول legal_case_installments

### هدف جدول

جدول `legal_case_installments` اقساط مرتبط با یک پرونده حقوقی را نگهداری می‌کند.

ممکن است فقط بخشی از اقساط قرارداد وارد پرونده حقوقی شوند.

---

### نام جدول

```text
legal_case_installments
```

---

### ستون‌های پیشنهادی

| ستون | نوع | توضیح |
|---|---|---|
| `id` | BIGINT UNSIGNED AUTO_INCREMENT | شناسه |
| `legal_case_id` | BIGINT UNSIGNED | پرونده حقوقی |
| `customer_id` | BIGINT UNSIGNED | مشتری |
| `contract_id` | BIGINT UNSIGNED | قرارداد |
| `installment_id` | BIGINT UNSIGNED | قسط |
| `sequence_number` | INT UNSIGNED NULL | شماره قسط |
| `due_date` | DATE | تاریخ سررسید |
| `installment_amount_at_referral` | DECIMAL(15,2) | مبلغ قسط هنگام ارجاع |
| `paid_amount_at_referral` | DECIMAL(15,2) | پرداخت‌شده هنگام ارجاع |
| `remaining_amount_at_referral` | DECIMAL(15,2) | مانده هنگام ارجاع |
| `penalty_amount_at_referral` | DECIMAL(15,2) | جریمه هنگام ارجاع |
| `overdue_days_at_referral` | INT UNSIGNED | روزهای تأخیر هنگام ارجاع |
| `status_at_referral` | VARCHAR(50) | وضعیت قسط هنگام ارجاع |
| `current_status` | VARCHAR(50) NULL | وضعیت فعلی در پرونده |
| `included_in_claim` | TINYINT(1) | آیا در خواسته لحاظ شده؟ |
| `created_at` | DATETIME NULL | زمان ایجاد |

---

### current_statusهای پیشنهادی

```text
included
excluded
paid_after_referral
settled
cancelled
```

---

### قوانین

- هر رکورد باید legal_case_id داشته باشد.
- هر رکورد باید installment_id معتبر داشته باشد.
- مبلغ‌های هنگام ارجاع Snapshot هستند و نباید بعداً تغییر کنند.
- اگر قسط بعد از ارجاع پرداخت شد، current_status باید بروزرسانی شود.
- اقساط included_in_claim در مبلغ خواسته لحاظ می‌شوند.
- این جدول نباید Soft Delete شود، مگر با سیاست اصلاح رسمی.
- تغییر included_in_claim باید Audit Log داشته باشد.

---

### Indexهای پیشنهادی

```text
idx_legal_case_installments_legal_case_id
idx_legal_case_installments_customer_id
idx_legal_case_installments_contract_id
idx_legal_case_installments_installment_id
idx_legal_case_installments_due_date
idx_legal_case_installments_current_status
idx_legal_case_installments_included_in_claim
```

---

## جدول legal_claims

### هدف جدول

جدول `legal_claims` خواسته‌ها یا مطالبات مطرح‌شده در پرونده حقوقی را نگهداری می‌کند.

یک پرونده می‌تواند چند خواسته داشته باشد.

مثال:

- مطالبه وجه
- مطالبه خسارت تأخیر
- مطالبه هزینه دادرسی
- وصول ضمانت
- درخواست بازگشت کالا
- اجرای تعهد قراردادی

---

### نام جدول

```text
legal_claims
```

---

### ستون‌های پیشنهادی

| ستون | نوع | توضیح |
|---|---|---|
| `id` | BIGINT UNSIGNED AUTO_INCREMENT | شناسه |
| `legal_case_id` | BIGINT UNSIGNED | پرونده حقوقی |
| `customer_id` | BIGINT UNSIGNED | مشتری |
| `contract_id` | BIGINT UNSIGNED | قرارداد |
| `claim_type` | VARCHAR(50) | نوع خواسته |
| `title` | VARCHAR(191) | عنوان خواسته |
| `description` | TEXT NULL | توضیحات |
| `claim_amount` | DECIMAL(15,2) NULL | مبلغ خواسته |
| `status` | VARCHAR(50) | وضعیت |
| `approved_by` | BIGINT UNSIGNED NULL | تأییدکننده |
| `approved_at` | DATETIME NULL | زمان تأیید |
| `metadata` | JSON NULL | داده تکمیلی |
| `created_at` | DATETIME NULL | زمان ایجاد |
| `updated_at` | DATETIME NULL | زمان بروزرسانی |

---

### claim_typeهای پیشنهادی

```text
debt_collection
late_penalty
legal_costs
guarantee_collection
device_return
contract_obligation
settlement_claim
other
```

---

### statusهای پیشنهادی

```text
draft
approved
filed
accepted
rejected
cancelled
closed
```

---

### قوانین

- هر Claim باید legal_case_id داشته باشد.
- claim_amount در صورت مالی بودن باید DECIMAL باشد.
- Claim حساس باید approval داشته باشد.
- تغییر claim_amount باید Audit و Financial Log داشته باشد.
- Claim filed نباید بدون Permission ویژه تغییر کند.
- Claim باید در گزارش پرونده حقوقی قابل مشاهده باشد.

---

### Indexهای پیشنهادی

```text
idx_legal_claims_legal_case_id
idx_legal_claims_customer_id
idx_legal_claims_contract_id
idx_legal_claims_claim_type
idx_legal_claims_status
idx_legal_claims_approved_by
```

---

## جدول legal_actions

### هدف جدول

جدول `legal_actions` اقدامات انجام‌شده در پرونده حقوقی را ثبت می‌کند.

اقدام حقوقی می‌تواند داخلی یا رسمی باشد.

مثال:

- تماس اخطار حقوقی
- ارسال پیام هشدار
- تنظیم اظهارنامه
- ارسال اظهارنامه
- تنظیم دادخواست
- ثبت دادخواست
- جلسه دادگاه
- دریافت رأی
- اجرای حکم
- مذاکره تسویه

---

### نام جدول

```text
legal_actions
```

---

### ستون‌های پیشنهادی

| ستون | نوع | توضیح |
|---|---|---|
| `id` | BIGINT UNSIGNED AUTO_INCREMENT | شناسه |
| `legal_case_id` | BIGINT UNSIGNED | پرونده حقوقی |
| `customer_id` | BIGINT UNSIGNED | مشتری |
| `contract_id` | BIGINT UNSIGNED | قرارداد |
| `action_type` | VARCHAR(50) | نوع اقدام |
| `action_channel` | VARCHAR(50) | کانال اقدام |
| `title` | VARCHAR(191) | عنوان اقدام |
| `description` | TEXT NULL | توضیح |
| `action_result` | VARCHAR(50) | نتیجه اقدام |
| `action_at` | DATETIME | زمان اقدام |
| `performed_by` | BIGINT UNSIGNED NULL | انجام‌دهنده |
| `next_action` | VARCHAR(100) NULL | اقدام بعدی |
| `next_action_at` | DATETIME NULL | زمان اقدام بعدی |
| `related_file_id` | BIGINT UNSIGNED NULL | فایل مرتبط |
| `metadata` | JSON NULL | داده تکمیلی |
| `created_at` | DATETIME NULL | زمان ایجاد |
| `updated_at` | DATETIME NULL | زمان بروزرسانی |
| `deleted_at` | DATETIME NULL | زمان حذف نرم |
| `deleted_by` | BIGINT UNSIGNED NULL | حذف‌کننده |

---

### action_typeهای پیشنهادی

```text
internal_review
legal_warning
customer_call
sms_warning
notice_prepared
notice_sent
petition_prepared
petition_filed
court_session
judgment_received
execution_requested
settlement_negotiation
guarantee_action
case_closed
other
```

---

### action_channelهای پیشنهادی

```text
internal
phone
sms
whatsapp
telegram
in_person
court
lawyer
system
other
```

---

### action_resultهای پیشنهادی

```text
pending
successful
failed
no_response
customer_promised
settlement_reached
sent_to_next_stage
cancelled
```

---

### قوانین

- هر اقدام باید legal_case_id داشته باشد.
- اقدام باید action_type و action_at داشته باشد.
- اقدام رسمی باید performed_by داشته باشد.
- اقدام دارای فایل باید related_file_id معتبر داشته باشد.
- اقدامات حساس فقط برای نقش مجاز نمایش داده شوند.
- حذف اقدام باید Soft Delete و Audit Log داشته باشد.
- اگر next_action_at وجود دارد، Calendar/Reminder باید بتواند از آن استفاده کند.
- اقدام حقوقی می‌تواند وضعیت پرونده را تغییر دهد.

---

### Indexهای پیشنهادی

```text
idx_legal_actions_legal_case_id
idx_legal_actions_customer_id
idx_legal_actions_contract_id
idx_legal_actions_action_type
idx_legal_actions_action_channel
idx_legal_actions_action_result
idx_legal_actions_action_at
idx_legal_actions_performed_by
idx_legal_actions_next_action_at
idx_legal_actions_deleted_at
```

---

## جدول legal_documents

### هدف جدول

جدول `legal_documents` مدارک و فایل‌های مرتبط با پرونده حقوقی را نگهداری می‌کند.

فایل واقعی باید در Files Domain و Private Storage ذخیره شود.

---

### نام جدول

```text
legal_documents
```

---

### ستون‌های پیشنهادی

| ستون | نوع | توضیح |
|---|---|---|
| `id` | BIGINT UNSIGNED AUTO_INCREMENT | شناسه |
| `legal_case_id` | BIGINT UNSIGNED | پرونده حقوقی |
| `customer_id` | BIGINT UNSIGNED | مشتری |
| `contract_id` | BIGINT UNSIGNED | قرارداد |
| `file_id` | BIGINT UNSIGNED | فایل |
| `document_type` | VARCHAR(50) | نوع سند |
| `title` | VARCHAR(191) NULL | عنوان |
| `description` | TEXT NULL | توضیح |
| `status` | VARCHAR(50) | وضعیت سند |
| `is_sensitive` | TINYINT(1) | حساس بودن |
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

### document_typeهای پیشنهادی

```text
signed_contract
installment_report
payment_report
debt_snapshot
guarantee_document
check_image
promissory_note
notice
petition
court_notice
judgment
execution_document
settlement_agreement
lawyer_note
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

- هر سند باید legal_case_id داشته باشد.
- هر سند باید file_id معتبر داشته باشد.
- فایل حقوقی باید Private باشد.
- دانلود سند حقوقی باید Audit Log داشته باشد.
- فایل‌های حقوقی بسیار حساس هستند و نباید در Public Storage قرار بگیرند.
- سند rejected باید rejection_reason داشته باشد.
- حذف سند باید Soft Delete باشد.
- فایل‌های رسمی پرونده بعد از ثبت نباید بدون Audit جایگزین شوند.

---

### Indexهای پیشنهادی

```text
idx_legal_documents_legal_case_id
idx_legal_documents_customer_id
idx_legal_documents_contract_id
idx_legal_documents_file_id
idx_legal_documents_document_type
idx_legal_documents_status
idx_legal_documents_is_sensitive
idx_legal_documents_uploaded_by
idx_legal_documents_uploaded_at
idx_legal_documents_deleted_at
```

---

## جدول legal_deadlines

### هدف جدول

جدول `legal_deadlines` مهلت‌ها، جلسات، یادآوری‌ها و تاریخ‌های مهم پرونده حقوقی را ذخیره می‌کند.

مثال:

- مهلت ارسال اظهارنامه
- مهلت ثبت دادخواست
- جلسه دادگاه
- مهلت پیگیری وکیل
- مهلت اجرای حکم
- تاریخ مذاکره تسویه

---

### نام جدول

```text
legal_deadlines
```

---

### ستون‌های پیشنهادی

| ستون | نوع | توضیح |
|---|---|---|
| `id` | BIGINT UNSIGNED AUTO_INCREMENT | شناسه |
| `legal_case_id` | BIGINT UNSIGNED | پرونده حقوقی |
| `customer_id` | BIGINT UNSIGNED | مشتری |
| `contract_id` | BIGINT UNSIGNED | قرارداد |
| `deadline_type` | VARCHAR(50) | نوع مهلت |
| `title` | VARCHAR(191) | عنوان |
| `description` | TEXT NULL | توضیح |
| `due_at` | DATETIME | زمان سررسید |
| `remind_at` | DATETIME NULL | زمان یادآوری |
| `assigned_user_id` | BIGINT UNSIGNED NULL | مسئول |
| `status` | VARCHAR(50) | وضعیت |
| `completed_at` | DATETIME NULL | زمان تکمیل |
| `completed_by` | BIGINT UNSIGNED NULL | تکمیل‌کننده |
| `cancelled_at` | DATETIME NULL | زمان لغو |
| `cancelled_by` | BIGINT UNSIGNED NULL | لغوکننده |
| `cancellation_reason` | TEXT NULL | دلیل لغو |
| `calendar_event_id` | BIGINT UNSIGNED NULL | رویداد تقویم مرتبط |
| `metadata` | JSON NULL | داده تکمیلی |
| `created_at` | DATETIME NULL | زمان ایجاد |
| `updated_at` | DATETIME NULL | زمان بروزرسانی |

---

### deadline_typeهای پیشنهادی

```text
followup
notice_deadline
petition_deadline
court_session
document_preparation
execution_followup
settlement_meeting
other
```

---

### statusهای پیشنهادی

```text
pending
completed
overdue
cancelled
rescheduled
```

---

### قوانین

- هر Deadline باید legal_case_id داشته باشد.
- due_at الزامی است.
- Deadline حساس باید assigned_user_id داشته باشد.
- Deadline می‌تواند Calendar Event بسازد.
- مهلت overdue باید در Dashboard حقوقی نمایش داده شود.
- تغییر Deadline مهم باید Audit Log داشته باشد.
- لغو Deadline باید cancellation_reason داشته باشد.

---

### Indexهای پیشنهادی

```text
idx_legal_deadlines_legal_case_id
idx_legal_deadlines_customer_id
idx_legal_deadlines_contract_id
idx_legal_deadlines_deadline_type
idx_legal_deadlines_due_at
idx_legal_deadlines_remind_at
idx_legal_deadlines_assigned_user_id
idx_legal_deadlines_status
idx_legal_deadlines_calendar_event_id
```

---

## جدول legal_costs

### هدف جدول

جدول `legal_costs` هزینه‌های مربوط به پرونده حقوقی را ثبت می‌کند.

هزینه‌ها ممکن است داخلی یا قابل مطالبه باشند.

مثال:

- هزینه دادرسی
- هزینه وکیل
- هزینه ارسال اظهارنامه
- هزینه کارشناسی
- هزینه اجرای حکم
- هزینه رفت‌وآمد یا پیگیری

---

### نام جدول

```text
legal_costs
```

---

### ستون‌های پیشنهادی

| ستون | نوع | توضیح |
|---|---|---|
| `id` | BIGINT UNSIGNED AUTO_INCREMENT | شناسه |
| `legal_case_id` | BIGINT UNSIGNED | پرونده حقوقی |
| `customer_id` | BIGINT UNSIGNED | مشتری |
| `contract_id` | BIGINT UNSIGNED | قرارداد |
| `cost_type` | VARCHAR(50) | نوع هزینه |
| `amount` | DECIMAL(15,2) | مبلغ |
| `is_claimable` | TINYINT(1) | قابل مطالبه بودن |
| `paid_by` | VARCHAR(50) | پرداخت‌کننده |
| `payment_id` | BIGINT UNSIGNED NULL | پرداخت مرتبط |
| `file_id` | BIGINT UNSIGNED NULL | رسید یا سند هزینه |
| `status` | VARCHAR(50) | وضعیت |
| `description` | TEXT NULL | توضیح |
| `created_by` | BIGINT UNSIGNED NULL | ثبت‌کننده |
| `created_at` | DATETIME NULL | زمان ثبت |
| `approved_by` | BIGINT UNSIGNED NULL | تأییدکننده |
| `approved_at` | DATETIME NULL | زمان تأیید |
| `metadata` | JSON NULL | داده تکمیلی |

---

### cost_typeهای پیشنهادی

```text
court_fee
lawyer_fee
notice_fee
expert_fee
execution_fee
transportation
administrative
other
```

---

### paid_byهای پیشنهادی

```text
store
customer
lawyer
other
```

---

### statusهای پیشنهادی

```text
draft
approved
paid
rejected
cancelled
```

---

### قوانین

- هر هزینه باید legal_case_id داشته باشد.
- مبلغ باید DECIMAL باشد.
- هزینه قابل مطالبه می‌تواند در legal_claims لحاظ شود.
- هزینه approved باید Audit Log داشته باشد.
- هزینه paid در صورت اثر مالی باید Financial Log داشته باشد.
- رسید هزینه باید در Files Domain ذخیره شود.
- تغییر هزینه پرونده closed باید محدود باشد.

---

### Indexهای پیشنهادی

```text
idx_legal_costs_legal_case_id
idx_legal_costs_customer_id
idx_legal_costs_contract_id
idx_legal_costs_cost_type
idx_legal_costs_is_claimable
idx_legal_costs_paid_by
idx_legal_costs_payment_id
idx_legal_costs_status
idx_legal_costs_created_at
```

---

## جدول legal_status_histories

### هدف جدول

جدول `legal_status_histories` تاریخچه تغییر وضعیت پرونده حقوقی را ذخیره می‌کند.

این جدول برای ردیابی مسیر پرونده، گزارش حقوقی و Audit ضروری است.

---

### نام جدول

```text
legal_status_histories
```

---

### ستون‌های پیشنهادی

| ستون | نوع | توضیح |
|---|---|---|
| `id` | BIGINT UNSIGNED AUTO_INCREMENT | شناسه |
| `legal_case_id` | BIGINT UNSIGNED | پرونده حقوقی |
| `customer_id` | BIGINT UNSIGNED | مشتری |
| `contract_id` | BIGINT UNSIGNED | قرارداد |
| `old_status` | VARCHAR(50) NULL | وضعیت قبلی |
| `new_status` | VARCHAR(50) | وضعیت جدید |
| `old_legal_stage` | VARCHAR(50) NULL | مرحله قبلی |
| `new_legal_stage` | VARCHAR(50) NULL | مرحله جدید |
| `old_result` | VARCHAR(50) NULL | نتیجه قبلی |
| `new_result` | VARCHAR(50) NULL | نتیجه جدید |
| `reason` | TEXT NULL | دلیل تغییر |
| `changed_by` | BIGINT UNSIGNED NULL | تغییر دهنده |
| `changed_at` | DATETIME | زمان تغییر |
| `metadata` | JSON NULL | داده تکمیلی |

---

### قوانین

- هر تغییر status پرونده باید ثبت شود.
- تغییر legal_stage باید ثبت شود.
- بستن پرونده باید reason داشته باشد.
- تغییر result باید ثبت شود.
- این جدول نباید Soft Delete شود.
- مشاهده تاریخچه پرونده باید Permission حقوقی داشته باشد.

---

### Indexهای پیشنهادی

```text
idx_legal_status_histories_legal_case_id
idx_legal_status_histories_customer_id
idx_legal_status_histories_contract_id
idx_legal_status_histories_new_status
idx_legal_status_histories_new_legal_stage
idx_legal_status_histories_new_result
idx_legal_status_histories_changed_by
idx_legal_status_histories_changed_at
```

---

## جدول legal_notes

### هدف جدول

جدول `legal_notes` یادداشت‌های داخلی مربوط به پرونده حقوقی را نگهداری می‌کند.

این یادداشت‌ها برای وکیل، مدیر یا اپراتور حقوقی قابل استفاده هستند و نباید برای مشتری نمایش داده شوند، مگر در طراحی خاص.

---

### نام جدول

```text
legal_notes
```

---

### ستون‌های پیشنهادی

| ستون | نوع | توضیح |
|---|---|---|
| `id` | BIGINT UNSIGNED AUTO_INCREMENT | شناسه |
| `legal_case_id` | BIGINT UNSIGNED | پرونده حقوقی |
| `customer_id` | BIGINT UNSIGNED | مشتری |
| `contract_id` | BIGINT UNSIGNED | قرارداد |
| `note_type` | VARCHAR(50) | نوع یادداشت |
| `visibility` | VARCHAR(50) | سطح نمایش |
| `priority` | VARCHAR(50) | اولویت |
| `title` | VARCHAR(191) NULL | عنوان |
| `body` | TEXT | متن یادداشت |
| `is_pinned` | TINYINT(1) | سنجاق‌شده |
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
lawyer_note
manager_note
risk_note
settlement_note
court_note
internal
```

---

### visibilityهای پیشنهادی

```text
legal_only
manager_only
super_admin_only
internal
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

- هر یادداشت باید legal_case_id داشته باشد.
- یادداشت حقوقی برای مشتری نمایش داده نشود.
- یادداشت وکیل یا مدیر باید Permission داشته باشد.
- حذف یادداشت باید Soft Delete باشد.
- ویرایش یا حذف یادداشت حساس باید Audit Log داشته باشد.
- یادداشت critical می‌تواند در صفحه پرونده Badge ایجاد کند.

---

### Indexهای پیشنهادی

```text
idx_legal_notes_legal_case_id
idx_legal_notes_customer_id
idx_legal_notes_contract_id
idx_legal_notes_note_type
idx_legal_notes_visibility
idx_legal_notes_priority
idx_legal_notes_is_pinned
idx_legal_notes_created_by
idx_legal_notes_created_at
idx_legal_notes_deleted_at
```

---

## رابطه Legal Domain با سایر Domainها

Legal Domain با بخش‌های زیادی از سیستم ارتباط دارد.

| Domain | رابطه |
|---|---|
| Customers | هر پرونده حقوقی متعلق به یک مشتری است |
| Contracts | هر پرونده معمولاً برای یک قرارداد ساخته می‌شود |
| Installments | اقساط معوق به پرونده حقوقی وصل می‌شوند |
| Payments | پرداخت بعد از ارجاع می‌تواند پرونده را تغییر دهد |
| Financial | Snapshot بدهی و هزینه‌ها در Financial Domain ثبت می‌شود |
| Files | مدارک حقوقی در Files Domain و Private Storage ذخیره می‌شوند |
| Calendar | مهلت‌ها و جلسات حقوقی به Calendar وصل می‌شوند |
| Notifications | هشدار، مهلت و تغییر وضعیت می‌تواند Notification ایجاد کند |
| Reports | گزارش پرونده‌های حقوقی از این Domain استفاده می‌کند |
| Audit | همه تغییرات حساس حقوقی ثبت می‌شود |
| Security | تلاش غیرمجاز برای مشاهده پرونده حقوقی ثبت می‌شود |

---

## قوانین Snapshot حقوقی

هنگام ارجاع قرارداد یا قسط به حقوقی باید Snapshot ثبت شود.

Snapshot باید شامل موارد زیر باشد:

- مبلغ کل قرارداد
- مبلغ پرداخت‌شده
- مانده بدهی
- جریمه یا دیرکرد
- تعداد اقساط معوق
- تاریخ اولین معوقه
- تاریخ آخرین معوقه
- وضعیت قرارداد
- وضعیت اقساط
- ضمانت‌های موجود
- مدارک مهم

قوانین:

- Snapshot نباید بعداً ویرایش شود.
- Snapshot باید به legal_case وصل باشد.
- Snapshot می‌تواند در Financial Domain ذخیره شود.
- Legal Case باید financial_snapshot_id داشته باشد.
- محاسبه زنده نباید جایگزین Snapshot حقوقی شود.

---

## قوانین مبلغ و بدهی حقوقی

قوانین:

- همه مبلغ‌ها باید DECIMAL باشند.
- مبلغ بدهی هنگام ارجاع باید Snapshot شود.
- مبلغ خواسته باید از داده معتبر و قابل ردیابی ساخته شود.
- هزینه حقوقی قابل مطالبه باید جدا از اصل بدهی ثبت شود.
- پرداخت بعد از ارجاع باید روی وضعیت پرونده اثر قابل ردیابی داشته باشد.
- تغییر مبلغ خواسته باید Audit و در صورت اثر مالی Financial Log داشته باشد.
- فرمول داخلی محاسبه دیرکرد نباید برای نقش غیرمجاز نمایش داده شود.

---

## قوانین مدارک حقوقی

قوانین:

- مدارک حقوقی باید در Files Domain ذخیره شوند.
- فایل حقوقی باید Private باشد.
- دانلود مدارک حقوقی باید Audit Log داشته باشد.
- فایل‌های رسمی نباید بدون Audit جایگزین شوند.
- مدارک ردشده باید rejection_reason داشته باشند.
- مدارک حذف‌شده باید Soft Delete شوند.
- مسیر واقعی فایل نباید در UI نمایش داده شود.
- مشتری نباید به یادداشت‌ها و مدارک داخلی حقوقی دسترسی داشته باشد.

---

## قوانین وضعیت پرونده حقوقی

Transitionهای پیشنهادی:

```text
draft -> referred
referred -> under_review
under_review -> in_progress
in_progress -> waiting_customer
in_progress -> waiting_court
in_progress -> settlement_negotiation
settlement_negotiation -> settled
in_progress -> closed
waiting_court -> closed
settled -> closed
draft -> cancelled
referred -> cancelled
```

قوانین:

- Transition نامعتبر باید رد شود.
- هر تغییر status باید history داشته باشد.
- بستن پرونده باید close_reason داشته باشد.
- پرونده closed نباید بدون Permission ویژه تغییر کند.
- پرونده settled باید با Settlement Domain هماهنگ باشد.
- پرونده cancelled باید دلیل داشته باشد.
- پرونده حقوقی‌شده باید روی contract.legal_status اثر بگذارد.

---

## قوانین مهلت‌های حقوقی

قوانین:

- هر Deadline باید due_at داشته باشد.
- مهلت overdue باید قابل گزارش باشد.
- Deadline می‌تواند Calendar Event و Notification ایجاد کند.
- تغییر زمان جلسه یا مهلت باید Audit Log داشته باشد.
- لغو Deadline باید cancellation_reason داشته باشد.
- مسئول Deadline باید مشخص باشد، مگر نوع سیستمی باشد.
- Deadlineهای حقوقی حساس فقط برای نقش مجاز نمایش داده شوند.

---

## قوانین Soft Delete

جدول‌های زیر باید Soft Delete داشته باشند:

- `legal_cases`
- `legal_actions`
- `legal_documents`
- `legal_notes`

قوانین:

- پرونده دارای مدارک یا اقدامات رسمی نباید فیزیکی حذف شود.
- حذف پرونده باید فقط Soft Delete باشد.
- history و snapshots نباید حذف شوند.
- حذف سند حقوقی باید Audit Log داشته باشد.
- حذف یادداشت حقوقی باید Audit Log داشته باشد.
- پرونده deleted نباید در Queryهای عادی نمایش داده شود.

---

## قوانین Index و Performance

قوانین:

- جستجوی پرونده با legal_case_number باید سریع باشد.
- فیلتر پرونده‌ها بر اساس status، stage، lawyer و date باید سریع باشد.
- گزارش پرونده‌های حقوقی باید Pagination داشته باشد.
- Dashboard حقوقی باید Deadlineهای نزدیک را سریع پیدا کند.
- Export پرونده‌های حقوقی باید محدود و Permission-based باشد.
- Index زیاد و بی‌دلیل ممنوع است.

Indexهای مهم:

```text
legal_cases.legal_case_number
legal_cases.customer_id
legal_cases.contract_id
legal_cases.status
legal_cases.legal_stage
legal_cases.assigned_lawyer_id
legal_case_installments.legal_case_id
legal_actions.legal_case_id
legal_documents.legal_case_id
legal_deadlines.due_at
legal_costs.legal_case_id
legal_status_histories.legal_case_id
```

---

## قوانین Validation

### legal_cases

- legal_case_number الزامی و یکتا است.
- customer_id الزامی است.
- contract_id الزامی است.
- referral_reason الزامی است.
- referred_at الزامی است.
- debt_amount_at_referral باید غیرمنفی باشد.
- status باید معتبر باشد.
- legal_stage باید معتبر باشد.
- closed status باید close_reason داشته باشد.

### legal_case_installments

- legal_case_id الزامی است.
- installment_id الزامی است.
- due_date الزامی است.
- amountها باید غیرمنفی باشند.
- overdue_days_at_referral باید غیرمنفی باشد.

### legal_claims

- legal_case_id الزامی است.
- claim_type الزامی است.
- title الزامی است.
- claim_amount در صورت ثبت باید غیرمنفی باشد.
- status باید معتبر باشد.

### legal_actions

- legal_case_id الزامی است.
- action_type الزامی است.
- action_at الزامی است.
- title الزامی است.
- action_result باید معتبر باشد.

### legal_documents

- legal_case_id الزامی است.
- file_id الزامی است.
- document_type الزامی است.
- status باید معتبر باشد.
- rejected باید rejection_reason داشته باشد.

### legal_deadlines

- legal_case_id الزامی است.
- deadline_type الزامی است.
- title الزامی است.
- due_at الزامی است.
- status باید معتبر باشد.

### legal_costs

- legal_case_id الزامی است.
- cost_type الزامی است.
- amount باید غیرمنفی باشد.
- status باید معتبر باشد.

---

## قوانین Audit و Security Log

### Audit Log الزامی برای:

- ایجاد پرونده حقوقی
- ارجاع قرارداد به حقوقی
- تغییر وضعیت پرونده
- تغییر مرحله حقوقی
- بستن پرونده
- لغو پرونده
- تغییر مبلغ خواسته
- افزودن یا حذف Claim
- ثبت اقدام حقوقی رسمی
- حذف اقدام حقوقی
- آپلود سند حقوقی
- دانلود سند حقوقی
- حذف سند حقوقی
- ثبت یا تغییر هزینه حقوقی
- تغییر Deadline مهم
- حذف یا ویرایش یادداشت حقوقی
- Export گزارش پرونده‌های حقوقی

### Financial Log الزامی برای:

- ثبت هزینه حقوقی paid
- تغییر مبلغ claim در صورت اثر مالی
- تسویه پرونده حقوقی
- اعمال هزینه قابل مطالبه
- وصول ضمانت
- پرداخت بعد از ارجاع حقوقی
- تغییر بدهی با Adjustment حقوقی

### Security Log الزامی برای:

- تلاش مشاهده پرونده حقوقی بدون Permission
- تلاش مشاهده پرونده خارج از Scope
- تلاش دانلود سند حقوقی بدون Permission
- تلاش تغییر وضعیت پرونده بدون Permission
- تلاش تغییر مبلغ حقوقی از Frontend
- تلاش حذف پرونده بدون Permission
- CSRF نامعتبر
- دستکاری legal_case_id در درخواست‌ها
- تلاش Export پرونده‌های خارج از Scope

---

## Seedهای پیشنهادی

### case_type

```text
installment_default
payment_default
guarantee_collection
device_return
contract_claim
settlement_dispute
manual
other
```

### status

```text
draft
referred
under_review
in_progress
waiting_customer
waiting_court
settlement_negotiation
settled
closed
cancelled
deleted
```

### legal_stage

```text
internal_followup
warning_sent
notice_prepared
notice_sent
petition_prepared
filed
court_process
judgment_received
execution
closed
```

### claim_type

```text
debt_collection
late_penalty
legal_costs
guarantee_collection
device_return
contract_obligation
settlement_claim
other
```

### action_type

```text
internal_review
legal_warning
customer_call
sms_warning
notice_prepared
notice_sent
petition_prepared
petition_filed
court_session
judgment_received
execution_requested
settlement_negotiation
guarantee_action
case_closed
other
```

### document_type

```text
signed_contract
installment_report
payment_report
debt_snapshot
guarantee_document
check_image
promissory_note
notice
petition
court_notice
judgment
execution_document
settlement_agreement
lawyer_note
other
```

---

## چک‌لیست پیاده‌سازی

قبل از پیاده‌سازی Legal Tables بررسی شود:

- [ ] جدول `legal_cases` ساخته شده است.
- [ ] `legal_case_number` یکتا است.
- [ ] پرونده به customer_id و contract_id وصل است.
- [ ] مبلغ بدهی هنگام ارجاع Snapshot می‌شود.
- [ ] جدول `legal_case_installments` وجود دارد.
- [ ] اقساط حقوقی‌شده Snapshot مبلغی دارند.
- [ ] جدول `legal_claims` وجود دارد.
- [ ] جدول `legal_actions` وجود دارد.
- [ ] جدول `legal_documents` وجود دارد.
- [ ] فایل‌های حقوقی در Files Domain و Private Storage ذخیره می‌شوند.
- [ ] جدول `legal_deadlines` وجود دارد.
- [ ] مهلت‌ها می‌توانند Calendar Event بسازند.
- [ ] جدول `legal_costs` وجود دارد.
- [ ] جدول `legal_status_histories` وجود دارد.
- [ ] جدول `legal_notes` وجود دارد.
- [ ] تغییرات حساس Audit Log دارند.
- [ ] عملیات مالی حقوقی Financial Log دارند.
- [ ] تلاش‌های غیرمجاز Security Log دارند.
- [ ] Queryهای حقوقی Scope را رعایت می‌کنند.
- [ ] Export پرونده‌های حقوقی Permission-based است.

---

## Definition of Done

Legal Tables زمانی کامل هستند که:

- پرونده حقوقی با شماره رسمی یکتا قابل ثبت باشد.
- پرونده به مشتری و قرارداد وصل باشد.
- پرونده هنگام ارجاع Snapshot مالی معتبر داشته باشد.
- اقساط مرتبط با پرونده حقوقی قابل ثبت باشند.
- خواسته‌ها و مطالبات پرونده قابل ثبت باشند.
- اقدامات حقوقی قابل ثبت و پیگیری باشند.
- مدارک حقوقی در Private Storage و Files Domain مدیریت شوند.
- مهلت‌ها و جلسات حقوقی قابل ثبت و یادآوری باشند.
- هزینه‌های حقوقی قابل ثبت و گزارش‌گیری باشند.
- تاریخچه وضعیت پرونده قابل ردیابی باشد.
- یادداشت‌های حقوقی داخلی قابل ثبت و کنترل با Permission باشند.
- پرونده closed بدون Permission ویژه تغییر نکند.
- مدارک و اطلاعات حقوقی بدون Permission نمایش یا Export نشوند.
- تغییرات حساس Audit Log داشته باشند.
- عملیات مالی مرتبط Financial Log داشته باشند.
- تلاش‌های غیرمجاز Security Log داشته باشند.
- جدول‌ها با MySQL/MariaDB و PHP 7.4+ سازگار باشند.
- Codex بتواند از روی این مستندات Migrationهای Legal Domain را بسازد.

---

## پایان فایل
````
