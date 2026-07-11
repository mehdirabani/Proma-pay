# 08 — Financial Tables

مستند جدول‌های مالی، لاگ‌های مالی، اصلاحات مالی، تسویه، Snapshotهای مالی، دفتر ثبت رویدادهای مالی و داده‌های وابسته به Financial Domain در پروژه **Proma Pay**

---

## فهرست مطالب

- [هدف فایل](#هدف-فایل)
- [تعریف Financial Domain در دیتابیس](#تعریف-financial-domain-در-دیتابیس)
- [اصل مهم](#اصل-مهم)
- [لیست جدول‌های Financial](#لیست-جدولهای-financial)
- [جدول financial_logs](#جدول-financial_logs)
- [جدول financial_adjustments](#جدول-financial_adjustments)
- [جدول settlement_records](#جدول-settlement_records)
- [جدول settlement_items](#جدول-settlement_items)
- [جدول financial_snapshots](#جدول-financial_snapshots)
- [جدول account_ledger_entries](#جدول-account_ledger_entries)
- [جدول financial_calculation_logs](#جدول-financial_calculation_logs)
- [رابطه Financial Domain با سایر Domainها](#رابطه-financial-domain-با-سایر-domainها)
- [قوانین مبلغ و دقت مالی](#قوانین-مبلغ-و-دقت-مالی)
- [قوانین Financial Log](#قوانین-financial-log)
- [قوانین Adjustment](#قوانین-adjustment)
- [قوانین Settlement](#قوانین-settlement)
- [قوانین Snapshot مالی](#قوانین-snapshot-مالی)
- [قوانین Ledger](#قوانین-ledger)
- [قوانین Audit و Security Log](#قوانین-audit-و-security-log)
- [قوانین Index و Performance](#قوانین-index-و-performance)
- [قوانین Validation](#قوانین-validation)
- [Seedهای پیشنهادی](#seedهای-پیشنهادی)
- [چک‌لیست پیاده‌سازی](#چکلیست-پیادهسازی)
- [Definition of Done](#definition-of-done)

---

## هدف فایل

هدف این فایل این است که ساختار جدول‌های مالی پروژه **Proma Pay** مشخص شود.

Financial Domain مسئول نگهداری ردپای مالی سیستم است؛ یعنی هر تغییری که روی مبلغ قرارداد، قسط، پرداخت، مانده، جریمه، تخفیف، تسویه یا اصلاح مالی اثر می‌گذارد باید قابل ردیابی باشد.

این فایل برای Codex مشخص می‌کند که:

- رویدادهای مالی چگونه ثبت شوند.
- اصلاحات مالی چگونه مدیریت شوند.
- تسویه قرارداد چگونه ذخیره شود.
- Snapshotهای مالی چگونه ساخته شوند.
- دفتر رویدادهای مالی چگونه قابل گزارش‌گیری باشد.
- محاسبات مالی چگونه Log شوند.
- چه عملیات‌هایی نیاز به Financial Log دارند.
- چه داده‌هایی حساس هستند.
- چه Indexهایی برای گزارش‌های مالی لازم است.

---

## تعریف Financial Domain در دیتابیس

Financial Domain بخش حسابداری کامل و رسمی نیست، اما نقش بسیار مهمی در ردیابی مالی سیستم دارد.

این Domain باید بتواند مشخص کند:

- چه مبلغی ایجاد شده است.
- چه مبلغی پرداخت شده است.
- چه مبلغی مانده است.
- چه مبلغی تخفیف خورده است.
- چه مبلغی جریمه شده است.
- چه مبلغی تسویه شده است.
- چه اصلاح مالی انجام شده است.
- چه کاربری تغییر مالی را انجام داده است.
- این تغییر مالی مربوط به کدام مشتری، قرارداد، قسط یا پرداخت است.
- قبل و بعد از تغییر، وضعیت مالی چه بوده است.

---

## اصل مهم

اصل مهم در Financial Tables:

> هیچ تغییر مالی نباید بدون ردپای قابل بررسی در سیستم انجام شود.

هر عملیات مالی حساس باید حداقل یکی از موارد زیر را داشته باشد:

- Financial Log
- Audit Log
- Snapshot
- Ledger Entry
- Reason
- Actor User
- Timestamp

قانون طلایی:

> Frontend منبع حقیقت مالی نیست. تمام مبلغ‌ها و تغییرات مالی باید سمت سرور محاسبه، اعتبارسنجی و ثبت شوند.

---

## لیست جدول‌های Financial

جدول‌های پیشنهادی Financial Domain:

| جدول | کاربرد |
|---|---|
| `financial_logs` | ثبت رویدادهای مالی مهم |
| `financial_adjustments` | اصلاحات مالی دستی یا سیستمی |
| `settlement_records` | تسویه قرارداد یا اقساط |
| `settlement_items` | آیتم‌های داخل تسویه |
| `financial_snapshots` | Snapshotهای مالی در لحظه‌های حساس |
| `account_ledger_entries` | دفتر رویدادهای مالی ساده |
| `financial_calculation_logs` | ثبت جزئیات محاسبات مالی مهم |

---

## جدول financial_logs

### هدف جدول

جدول `financial_logs` برای ثبت تمام رویدادهای مالی مهم استفاده می‌شود.

این جدول باید بتواند نشان دهد چه تغییر مالی، توسط چه کسی، روی چه موجودیتی و با چه مبلغی انجام شده است.

---

### نام جدول

```text
financial_logs
```

---

### ستون‌های پیشنهادی

| ستون | نوع | توضیح |
|---|---|---|
| `id` | BIGINT UNSIGNED AUTO_INCREMENT | شناسه داخلی |
| `log_number` | VARCHAR(50) | شماره رسمی لاگ مالی |
| `event_type` | VARCHAR(100) | نوع رویداد مالی |
| `related_type` | VARCHAR(100) | نوع موجودیت مرتبط |
| `related_id` | BIGINT UNSIGNED | شناسه موجودیت مرتبط |
| `customer_id` | BIGINT UNSIGNED NULL | مشتری |
| `contract_id` | BIGINT UNSIGNED NULL | قرارداد |
| `installment_id` | BIGINT UNSIGNED NULL | قسط |
| `payment_id` | BIGINT UNSIGNED NULL | پرداخت |
| `amount` | DECIMAL(15,2) | مبلغ اثر مالی |
| `old_amount` | DECIMAL(15,2) NULL | مبلغ قبلی |
| `new_amount` | DECIMAL(15,2) NULL | مبلغ جدید |
| `balance_before` | DECIMAL(15,2) NULL | مانده قبل |
| `balance_after` | DECIMAL(15,2) NULL | مانده بعد |
| `currency` | VARCHAR(10) | واحد پول |
| `direction` | VARCHAR(20) | جهت اثر مالی |
| `source` | VARCHAR(50) | منبع رویداد |
| `reason` | TEXT NULL | دلیل |
| `description` | TEXT NULL | توضیح |
| `actor_user_id` | BIGINT UNSIGNED NULL | کاربر انجام‌دهنده |
| `occurred_at` | DATETIME | زمان وقوع |
| `metadata` | JSON NULL | داده تکمیلی |
| `created_at` | DATETIME NULL | زمان ثبت |

---

### event_typeهای پیشنهادی

```text
contract_created
contract_amount_changed
installment_created
installment_amount_changed
payment_approved
payment_allocated
payment_reversed
payment_refunded
penalty_applied
penalty_waived
discount_applied
manual_adjustment
settlement_created
settlement_completed
legal_referral_snapshot
guarantee_used
```

---

### directionهای پیشنهادی

```text
increase
decrease
neutral
```

---

### sourceهای پیشنهادی

```text
system
admin
accountant
payment
contract
installment
settlement
legal
plugin
manual
```

---

### قوانین

- هر رویداد مالی مهم باید financial_log داشته باشد.
- `log_number` باید Unique باشد.
- مبلغ باید DECIMAL باشد.
- استفاده از FLOAT و DOUBLE ممنوع است.
- financial_log نباید ویرایش یا حذف شود.
- رویداد مالی باید related_type و related_id داشته باشد.
- اگر رویداد به قرارداد یا قسط مربوط است، contract_id و installment_id تا حد امکان ثبت شود.
- اگر رویداد به پرداخت مربوط است، payment_id ثبت شود.
- تغییر مالی دستی باید reason داشته باشد.
- لاگ مالی باید برای گزارش‌گیری قابل فیلتر باشد.

---

### Indexهای پیشنهادی

```text
uniq_financial_logs_log_number
idx_financial_logs_event_type
idx_financial_logs_related
idx_financial_logs_customer_id
idx_financial_logs_contract_id
idx_financial_logs_installment_id
idx_financial_logs_payment_id
idx_financial_logs_actor_user_id
idx_financial_logs_occurred_at
idx_financial_logs_source
```

---

### نمونه ساختار SQL

```sql
CREATE TABLE financial_logs (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    log_number VARCHAR(50) NOT NULL,
    event_type VARCHAR(100) NOT NULL,
    related_type VARCHAR(100) NOT NULL,
    related_id BIGINT UNSIGNED NOT NULL,
    customer_id BIGINT UNSIGNED NULL,
    contract_id BIGINT UNSIGNED NULL,
    installment_id BIGINT UNSIGNED NULL,
    payment_id BIGINT UNSIGNED NULL,
    amount DECIMAL(15,2) NOT NULL DEFAULT 0,
    old_amount DECIMAL(15,2) NULL,
    new_amount DECIMAL(15,2) NULL,
    balance_before DECIMAL(15,2) NULL,
    balance_after DECIMAL(15,2) NULL,
    currency VARCHAR(10) NOT NULL DEFAULT 'IRR',
    direction VARCHAR(20) NOT NULL DEFAULT 'neutral',
    source VARCHAR(50) NOT NULL DEFAULT 'system',
    reason TEXT NULL,
    description TEXT NULL,
    actor_user_id BIGINT UNSIGNED NULL,
    occurred_at DATETIME NOT NULL,
    metadata JSON NULL,
    created_at DATETIME NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uniq_financial_logs_log_number (log_number),
    KEY idx_financial_logs_event_type (event_type),
    KEY idx_financial_logs_related (related_type, related_id),
    KEY idx_financial_logs_customer_id (customer_id),
    KEY idx_financial_logs_contract_id (contract_id),
    KEY idx_financial_logs_installment_id (installment_id),
    KEY idx_financial_logs_payment_id (payment_id),
    KEY idx_financial_logs_actor_user_id (actor_user_id),
    KEY idx_financial_logs_occurred_at (occurred_at),
    KEY idx_financial_logs_source (source)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

---

## جدول financial_adjustments

### هدف جدول

جدول `financial_adjustments` برای ثبت اصلاحات مالی استفاده می‌شود.

Adjustment زمانی استفاده می‌شود که لازم باشد یک مبلغ به صورت رسمی و قابل ردیابی اصلاح شود.

مثال:

- اصلاح مانده قسط
- اصلاح مبلغ قرارداد
- بخشودگی جریمه
- اعمال تخفیف دستی
- اصلاح پرداخت اشتباه
- ثبت تعدیل حسابداری

---

### نام جدول

```text
financial_adjustments
```

---

### ستون‌های پیشنهادی

| ستون | نوع | توضیح |
|---|---|---|
| `id` | BIGINT UNSIGNED AUTO_INCREMENT | شناسه |
| `adjustment_number` | VARCHAR(50) | شماره رسمی اصلاح |
| `adjustment_type` | VARCHAR(100) | نوع اصلاح |
| `related_type` | VARCHAR(100) | نوع موجودیت مرتبط |
| `related_id` | BIGINT UNSIGNED | شناسه موجودیت مرتبط |
| `customer_id` | BIGINT UNSIGNED NULL | مشتری |
| `contract_id` | BIGINT UNSIGNED NULL | قرارداد |
| `installment_id` | BIGINT UNSIGNED NULL | قسط |
| `payment_id` | BIGINT UNSIGNED NULL | پرداخت |
| `amount` | DECIMAL(15,2) | مبلغ اصلاح |
| `direction` | VARCHAR(20) | جهت اصلاح |
| `old_value` | DECIMAL(15,2) NULL | مقدار قبلی |
| `new_value` | DECIMAL(15,2) NULL | مقدار جدید |
| `reason` | TEXT | دلیل اصلاح |
| `status` | VARCHAR(50) | وضعیت اصلاح |
| `requested_by` | BIGINT UNSIGNED NULL | درخواست‌دهنده |
| `requested_at` | DATETIME NULL | زمان درخواست |
| `approved_by` | BIGINT UNSIGNED NULL | تأییدکننده |
| `approved_at` | DATETIME NULL | زمان تأیید |
| `rejected_by` | BIGINT UNSIGNED NULL | ردکننده |
| `rejected_at` | DATETIME NULL | زمان رد |
| `rejection_reason` | TEXT NULL | دلیل رد |
| `applied_by` | BIGINT UNSIGNED NULL | اعمال‌کننده |
| `applied_at` | DATETIME NULL | زمان اعمال |
| `financial_log_id` | BIGINT UNSIGNED NULL | لاگ مالی مرتبط |
| `metadata` | JSON NULL | داده تکمیلی |
| `created_at` | DATETIME NULL | زمان ایجاد |
| `updated_at` | DATETIME NULL | زمان بروزرسانی |

---

### adjustment_typeهای پیشنهادی

```text
discount
penalty_waiver
penalty_addition
installment_amount_correction
contract_amount_correction
payment_correction
balance_correction
settlement_adjustment
manual_credit
manual_debit
other
```

---

### statusهای پیشنهادی

```text
draft
requested
approved
rejected
applied
cancelled
failed
```

---

### directionهای پیشنهادی

```text
increase
decrease
neutral
```

---

### قوانین

- `adjustment_number` باید Unique باشد.
- Adjustment باید دلیل داشته باشد.
- Adjustment حساس باید approval داشته باشد.
- Adjustment اعمال‌شده باید Financial Log داشته باشد.
- Adjustment اعمال‌شده نباید حذف شود.
- Adjustment روی پرداخت approved باید با احتیاط و Permission ویژه انجام شود.
- Adjustment باید داخل Transaction اعمال شود.
- Adjustment rejected باید rejection_reason داشته باشد.
- Adjustment applied باید applied_by و applied_at داشته باشد.

---

### Indexهای پیشنهادی

```text
uniq_financial_adjustments_adjustment_number
idx_financial_adjustments_adjustment_type
idx_financial_adjustments_related
idx_financial_adjustments_customer_id
idx_financial_adjustments_contract_id
idx_financial_adjustments_installment_id
idx_financial_adjustments_payment_id
idx_financial_adjustments_status
idx_financial_adjustments_requested_by
idx_financial_adjustments_approved_by
idx_financial_adjustments_applied_at
```

---

## جدول settlement_records

### هدف جدول

جدول `settlement_records` برای ثبت تسویه قرارداد یا اقساط استفاده می‌شود.

تسویه می‌تواند به معنی پرداخت کامل مانده، توافق جدید، بخشودگی، تخفیف یا بستن پرونده مالی باشد.

---

### نام جدول

```text
settlement_records
```

---

### ستون‌های پیشنهادی

| ستون | نوع | توضیح |
|---|---|---|
| `id` | BIGINT UNSIGNED AUTO_INCREMENT | شناسه |
| `settlement_number` | VARCHAR(50) | شماره رسمی تسویه |
| `customer_id` | BIGINT UNSIGNED | مشتری |
| `contract_id` | BIGINT UNSIGNED | قرارداد |
| `settlement_type` | VARCHAR(50) | نوع تسویه |
| `status` | VARCHAR(50) | وضعیت تسویه |
| `total_debt_amount` | DECIMAL(15,2) | بدهی کل هنگام تسویه |
| `paid_amount` | DECIMAL(15,2) | مبلغ پرداخت‌شده |
| `discount_amount` | DECIMAL(15,2) | مبلغ تخفیف |
| `penalty_amount` | DECIMAL(15,2) | مبلغ جریمه لحاظ‌شده |
| `waived_penalty_amount` | DECIMAL(15,2) | جریمه بخشوده‌شده |
| `final_settlement_amount` | DECIMAL(15,2) | مبلغ نهایی تسویه |
| `remaining_after_settlement` | DECIMAL(15,2) | مانده بعد از تسویه |
| `settlement_date` | DATE | تاریخ تسویه |
| `requested_by` | BIGINT UNSIGNED NULL | درخواست‌دهنده |
| `approved_by` | BIGINT UNSIGNED NULL | تأییدکننده |
| `approved_at` | DATETIME NULL | زمان تأیید |
| `completed_by` | BIGINT UNSIGNED NULL | تکمیل‌کننده |
| `completed_at` | DATETIME NULL | زمان تکمیل |
| `cancelled_by` | BIGINT UNSIGNED NULL | لغوکننده |
| `cancelled_at` | DATETIME NULL | زمان لغو |
| `cancellation_reason` | TEXT NULL | دلیل لغو |
| `description` | TEXT NULL | توضیح |
| `financial_log_id` | BIGINT UNSIGNED NULL | لاگ مالی مرتبط |
| `snapshot_id` | BIGINT UNSIGNED NULL | Snapshot مرتبط |
| `metadata` | JSON NULL | داده تکمیلی |
| `created_at` | DATETIME NULL | زمان ایجاد |
| `updated_at` | DATETIME NULL | زمان بروزرسانی |

---

### settlement_typeهای پیشنهادی

```text
full_payment
discounted_settlement
legal_settlement
manual_settlement
guarantee_settlement
installment_settlement
other
```

---

### statusهای پیشنهادی

```text
draft
pending_approval
approved
completed
cancelled
failed
```

---

### قوانین

- `settlement_number` باید Unique باشد.
- هر تسویه باید customer_id و contract_id داشته باشد.
- تسویه باید Snapshot مالی داشته باشد.
- تسویه completed باید Financial Log داشته باشد.
- تسویه completed باید وضعیت قرارداد و اقساط را بروزرسانی کند.
- تسویه با تخفیف یا بخشودگی باید approval داشته باشد.
- تسویه قرارداد حقوقی‌شده باید Permission ویژه داشته باشد.
- لغو تسویه باید reason داشته باشد.
- تسویه باید داخل Transaction انجام شود.

---

### Indexهای پیشنهادی

```text
uniq_settlement_records_settlement_number
idx_settlement_records_customer_id
idx_settlement_records_contract_id
idx_settlement_records_settlement_type
idx_settlement_records_status
idx_settlement_records_settlement_date
idx_settlement_records_approved_by
idx_settlement_records_completed_at
```

---

## جدول settlement_items

### هدف جدول

جدول `settlement_items` آیتم‌های داخل یک تسویه را نگهداری می‌کند.

یک تسویه ممکن است شامل چند قسط، جریمه، تخفیف، پرداخت یا اصلاح مالی باشد.

---

### نام جدول

```text
settlement_items
```

---

### ستون‌های پیشنهادی

| ستون | نوع | توضیح |
|---|---|---|
| `id` | BIGINT UNSIGNED AUTO_INCREMENT | شناسه |
| `settlement_id` | BIGINT UNSIGNED | تسویه |
| `customer_id` | BIGINT UNSIGNED | مشتری |
| `contract_id` | BIGINT UNSIGNED | قرارداد |
| `installment_id` | BIGINT UNSIGNED NULL | قسط |
| `payment_id` | BIGINT UNSIGNED NULL | پرداخت |
| `item_type` | VARCHAR(50) | نوع آیتم |
| `title` | VARCHAR(191) NULL | عنوان |
| `original_amount` | DECIMAL(15,2) | مبلغ اصلی |
| `discount_amount` | DECIMAL(15,2) | تخفیف |
| `penalty_amount` | DECIMAL(15,2) | جریمه |
| `final_amount` | DECIMAL(15,2) | مبلغ نهایی |
| `status` | VARCHAR(50) | وضعیت آیتم |
| `description` | TEXT NULL | توضیح |
| `metadata` | JSON NULL | داده تکمیلی |
| `created_at` | DATETIME NULL | زمان ایجاد |

---

### item_typeهای پیشنهادی

```text
installment
penalty
discount
payment
adjustment
guarantee
other
```

---

### statusهای پیشنهادی

```text
pending
included
excluded
applied
cancelled
```

---

### قوانین

- هر آیتم باید به settlement_id وصل باشد.
- اگر آیتم مربوط به قسط است، installment_id باید ثبت شود.
- اگر آیتم مربوط به پرداخت است، payment_id باید ثبت شود.
- مبلغ‌ها باید DECIMAL باشند.
- مجموع settlement_items باید با settlement_records سازگار باشد.
- آیتم‌های تسویه completed نباید تغییر کنند.
- تغییر آیتم تسویه approved یا completed باید Audit و Financial Log داشته باشد.

---

### Indexهای پیشنهادی

```text
idx_settlement_items_settlement_id
idx_settlement_items_customer_id
idx_settlement_items_contract_id
idx_settlement_items_installment_id
idx_settlement_items_payment_id
idx_settlement_items_item_type
idx_settlement_items_status
```

---

## جدول financial_snapshots

### هدف جدول

جدول `financial_snapshots` وضعیت مالی یک موجودیت را در یک لحظه مشخص ذخیره می‌کند.

Snapshot کمک می‌کند اگر بعداً داده‌ها تغییر کردند، وضعیت مالی همان لحظه همچنان قابل بررسی باشد.

---

### نام جدول

```text
financial_snapshots
```

---

### ستون‌های پیشنهادی

| ستون | نوع | توضیح |
|---|---|---|
| `id` | BIGINT UNSIGNED AUTO_INCREMENT | شناسه |
| `snapshot_number` | VARCHAR(50) | شماره رسمی Snapshot |
| `snapshot_type` | VARCHAR(100) | نوع Snapshot |
| `related_type` | VARCHAR(100) | نوع موجودیت |
| `related_id` | BIGINT UNSIGNED | شناسه موجودیت |
| `customer_id` | BIGINT UNSIGNED NULL | مشتری |
| `contract_id` | BIGINT UNSIGNED NULL | قرارداد |
| `installment_id` | BIGINT UNSIGNED NULL | قسط |
| `total_amount` | DECIMAL(15,2) NULL | مبلغ کل |
| `paid_amount` | DECIMAL(15,2) NULL | مبلغ پرداخت‌شده |
| `remaining_amount` | DECIMAL(15,2) NULL | مانده |
| `penalty_amount` | DECIMAL(15,2) NULL | جریمه |
| `discount_amount` | DECIMAL(15,2) NULL | تخفیف |
| `settlement_amount` | DECIMAL(15,2) NULL | مبلغ تسویه |
| `overdue_amount` | DECIMAL(15,2) NULL | مبلغ معوق |
| `overdue_count` | INT UNSIGNED NULL | تعداد معوقات |
| `snapshot_data` | JSON NULL | داده کامل Snapshot |
| `created_by` | BIGINT UNSIGNED NULL | ایجادکننده |
| `created_at` | DATETIME | زمان ایجاد |

---

### snapshot_typeهای پیشنهادی

```text
contract_created
contract_activated
payment_applied
installment_overdue
legal_referral
settlement_requested
settlement_completed
manual_review
before_adjustment
after_adjustment
```

---

### قوانین

- `snapshot_number` باید Unique باشد.
- Snapshot نباید ویرایش شود.
- Snapshot نباید حذف شود.
- Snapshot باید در لحظه‌های حساس مثل تسویه و ارجاع حقوقی ساخته شود.
- Snapshot باید از داده معتبر سمت سرور ساخته شود.
- Snapshot نباید جایگزین جدول اصلی شود.
- داده حساس داخل snapshot_data باید فقط برای نقش مجاز نمایش داده شود.

---

### Indexهای پیشنهادی

```text
uniq_financial_snapshots_snapshot_number
idx_financial_snapshots_snapshot_type
idx_financial_snapshots_related
idx_financial_snapshots_customer_id
idx_financial_snapshots_contract_id
idx_financial_snapshots_installment_id
idx_financial_snapshots_created_at
```

---

## جدول account_ledger_entries

### هدف جدول

جدول `account_ledger_entries` یک دفتر ساده از ورود و خروج‌های مالی سیستم است.

این جدول برای گزارش‌گیری، بررسی جریان مالی و ایجاد ساختار حسابداری ساده استفاده می‌شود.

این جدول جایگزین نرم‌افزار حسابداری کامل نیست، اما به سیستم کمک می‌کند رویدادهای مالی ساختاریافته‌تر ثبت شوند.

---

### نام جدول

```text
account_ledger_entries
```

---

### ستون‌های پیشنهادی

| ستون | نوع | توضیح |
|---|---|---|
| `id` | BIGINT UNSIGNED AUTO_INCREMENT | شناسه |
| `entry_number` | VARCHAR(50) | شماره رسمی Entry |
| `entry_type` | VARCHAR(100) | نوع Entry |
| `account_key` | VARCHAR(100) | کلید حساب داخلی |
| `related_type` | VARCHAR(100) | نوع موجودیت مرتبط |
| `related_id` | BIGINT UNSIGNED | شناسه موجودیت |
| `customer_id` | BIGINT UNSIGNED NULL | مشتری |
| `contract_id` | BIGINT UNSIGNED NULL | قرارداد |
| `payment_id` | BIGINT UNSIGNED NULL | پرداخت |
| `debit_amount` | DECIMAL(15,2) | بدهکار |
| `credit_amount` | DECIMAL(15,2) | بستانکار |
| `balance_after` | DECIMAL(15,2) NULL | مانده بعد |
| `currency` | VARCHAR(10) | واحد پول |
| `description` | TEXT NULL | توضیح |
| `financial_log_id` | BIGINT UNSIGNED NULL | لاگ مالی مرتبط |
| `posted_at` | DATETIME | زمان ثبت |
| `posted_by` | BIGINT UNSIGNED NULL | ثبت‌کننده |
| `metadata` | JSON NULL | داده تکمیلی |
| `created_at` | DATETIME NULL | زمان ایجاد |

---

### entry_typeهای پیشنهادی

```text
payment_received
payment_allocated
refund_processed
discount_applied
penalty_applied
penalty_waived
settlement_completed
manual_adjustment
guarantee_used
```

---

### account_keyهای پیشنهادی

```text
cash
card_to_card
bank_gateway
accounts_receivable
installment_receivable
penalty_receivable
discounts
refunds
settlements
manual_adjustments
```

---

### قوانین

- `entry_number` باید Unique باشد.
- هر Entry باید debit یا credit داشته باشد.
- مبلغ debit و credit نباید همزمان هر دو صفر باشند.
- Ledger Entry نباید ویرایش یا حذف شود.
- Ledger Entry باید به financial_log_id وصل شود، اگر رویداد مالی دارد.
- Ledger باید با گزارش مالی قابل استفاده باشد.
- این جدول برای حسابداری ساده است، نه جایگزین دفتر کل رسمی کامل.

---

### Indexهای پیشنهادی

```text
uniq_account_ledger_entries_entry_number
idx_account_ledger_entries_entry_type
idx_account_ledger_entries_account_key
idx_account_ledger_entries_related
idx_account_ledger_entries_customer_id
idx_account_ledger_entries_contract_id
idx_account_ledger_entries_payment_id
idx_account_ledger_entries_financial_log_id
idx_account_ledger_entries_posted_at
```

---

## جدول financial_calculation_logs

### هدف جدول

جدول `financial_calculation_logs` برای ثبت محاسبات مالی مهم استفاده می‌شود.

این جدول مخصوص مواقعی است که لازم است بدانیم سیستم چگونه به یک عدد رسیده است.

مثال:

- محاسبه مبلغ قرارداد
- محاسبه اقساط
- محاسبه مانده
- محاسبه دیرکرد
- محاسبه تسویه
- محاسبه تخفیف
- محاسبه Snapshot حقوقی

---

### نام جدول

```text
financial_calculation_logs
```

---

### ستون‌های پیشنهادی

| ستون | نوع | توضیح |
|---|---|---|
| `id` | BIGINT UNSIGNED AUTO_INCREMENT | شناسه |
| `calculation_key` | VARCHAR(100) | کلید محاسبه |
| `calculation_type` | VARCHAR(100) | نوع محاسبه |
| `related_type` | VARCHAR(100) | نوع موجودیت |
| `related_id` | BIGINT UNSIGNED | شناسه موجودیت |
| `customer_id` | BIGINT UNSIGNED NULL | مشتری |
| `contract_id` | BIGINT UNSIGNED NULL | قرارداد |
| `installment_id` | BIGINT UNSIGNED NULL | قسط |
| `input_data` | JSON NULL | ورودی محاسبه |
| `output_data` | JSON NULL | خروجی محاسبه |
| `result_amount` | DECIMAL(15,2) NULL | مبلغ نتیجه |
| `formula_version` | VARCHAR(50) NULL | نسخه فرمول |
| `calculated_by` | BIGINT UNSIGNED NULL | محاسبه‌کننده |
| `calculated_at` | DATETIME | زمان محاسبه |
| `status` | VARCHAR(50) | وضعیت |
| `error_message` | TEXT NULL | خطا |
| `metadata` | JSON NULL | داده تکمیلی |
| `created_at` | DATETIME NULL | زمان ایجاد |

---

### calculation_typeهای پیشنهادی

```text
contract_total
installment_schedule
remaining_balance
overdue_days
late_penalty
settlement_amount
legal_debt_snapshot
discount
manual_review
```

---

### statusهای پیشنهادی

```text
success
failed
cancelled
ignored
```

---

### قوانین

- محاسبات حساس مالی باید قابل ردیابی باشند.
- input_data و output_data نباید داده محرمانه غیرضروری ذخیره کنند.
- فرمول داخلی حساس نباید برای نقش غیرمجاز نمایش داده شود.
- calculation log نباید جایگزین financial log شود.
- محاسبه موفق مهم می‌تواند باعث ایجاد Snapshot یا Financial Log شود.
- خطای محاسبه باید Log شود.

---

### Indexهای پیشنهادی

```text
idx_financial_calculation_logs_calculation_key
idx_financial_calculation_logs_calculation_type
idx_financial_calculation_logs_related
idx_financial_calculation_logs_customer_id
idx_financial_calculation_logs_contract_id
idx_financial_calculation_logs_installment_id
idx_financial_calculation_logs_status
idx_financial_calculation_logs_calculated_at
```

---

## رابطه Financial Domain با سایر Domainها

Financial Domain با بخش‌های زیادی از سیستم ارتباط دارد.

| Domain | رابطه |
|---|---|
| Contracts | تغییر مبلغ قرارداد، تسویه، Snapshot |
| Installments | مبلغ قسط، مانده، جریمه، تخفیف |
| Payments | پرداخت approved، Allocation، Refund |
| Legal | Snapshot بدهی هنگام ارجاع حقوقی |
| Reports | گزارش مالی، گزارش تسویه، گزارش معوقات |
| Customers | وضعیت مالی مشتری و بدهی‌ها |
| Files | خروجی گزارش مالی یا مدارک تسویه |
| Audit | تغییرات حساس مالی |
| Security | تلاش غیرمجاز برای تغییر مالی |
| Plugins | پلاگین‌های مالی یا حسابداری باید از Financial Service استفاده کنند |

---

## قوانین مبلغ و دقت مالی

قوانین الزامی:

- همه مبلغ‌ها باید با `DECIMAL(15,2)` یا ساختار مشابه ذخیره شوند.
- استفاده از `FLOAT` و `DOUBLE` برای مبلغ ممنوع است.
- واحد پول باید مشخص باشد.
- محاسبات مالی باید سمت سرور انجام شود.
- Frontend فقط نمایش‌دهنده است.
- مبلغ‌های Snapshot باید در زمان رویداد ذخیره شوند.
- تغییر مبلغ بعد از approved شدن پرداخت یا active شدن قرارداد باید محدود باشد.
- اصلاح مبلغ باید با Adjustment رسمی انجام شود.

---

## قوانین Financial Log

Financial Log باید برای عملیات زیر ثبت شود:

- ایجاد قرارداد مالی
- تغییر مبلغ قرارداد
- ایجاد اقساط
- تغییر مبلغ قسط
- تأیید پرداخت
- تخصیص پرداخت
- برگشت تخصیص پرداخت
- Refund
- اعمال جریمه
- بخشودگی جریمه
- اعمال تخفیف
- تسویه قرارداد
- استفاده از ضمانت
- اصلاح دستی مالی
- ارجاع حقوقی همراه با Snapshot بدهی

قوانین:

- financial_log نباید حذف شود.
- financial_log نباید ویرایش شود.
- financial_log باید actor_user_id داشته باشد، مگر عملیات سیستمی باشد.
- عملیات سیستمی باید source = system داشته باشد.
- financial_log باید قابل گزارش‌گیری باشد.

---

## قوانین Adjustment

قوانین:

- Adjustment باید شماره رسمی داشته باشد.
- Adjustment باید دلیل داشته باشد.
- Adjustment حساس باید نیازمند Approval باشد.
- Adjustment applied باید Financial Log داشته باشد.
- Adjustment نباید بی‌صدا مبلغ‌ها را تغییر دهد.
- Adjustment باید در Transaction انجام شود.
- Adjustment روی داده حقوقی یا تسویه‌شده باید محدود باشد.
- Adjustment rejected باید دلیل رد داشته باشد.

---

## قوانین Settlement

قوانین:

- تسویه باید شماره رسمی داشته باشد.
- تسویه باید Snapshot قبل از تکمیل داشته باشد.
- تسویه completed باید وضعیت قرارداد را بروزرسانی کند.
- تسویه completed باید وضعیت اقساط را بروزرسانی کند.
- تسویه با تخفیف یا بخشودگی باید Audit داشته باشد.
- تسویه قرارداد حقوقی‌شده باید Permission خاص داشته باشد.
- تسویه باید Financial Log داشته باشد.
- تسویه باید داخل Transaction انجام شود.

---

## قوانین Snapshot مالی

Snapshot مالی در موارد زیر الزامی یا پیشنهادی است:

- ایجاد قرارداد
- فعال‌سازی قرارداد
- اعمال پرداخت مهم
- معوق شدن قسط
- ارجاع حقوقی
- قبل از Adjustment حساس
- بعد از Adjustment حساس
- درخواست تسویه
- تکمیل تسویه
- لغو قرارداد

قوانین:

- Snapshot نباید ویرایش شود.
- Snapshot نباید حذف شود.
- Snapshot باید داده مالی معتبر زمان رویداد را ذخیره کند.
- Snapshot باید برای اختلافات آینده قابل استناد باشد.
- Snapshot نباید جایگزین جدول اصلی شود.

---

## قوانین Ledger

قوانین:

- Ledger Entry باید شماره رسمی داشته باشد.
- Ledger Entry نباید حذف یا ویرایش شود.
- هر Entry باید debit یا credit معتبر داشته باشد.
- Ledger باید با Financial Log مرتبط باشد.
- Ledger برای گزارش داخلی استفاده می‌شود.
- Ledger نباید اطلاعات محرمانه غیرضروری ذخیره کند.
- Ledger می‌تواند در آینده به سیستم حسابداری خارجی Sync شود.

---

## قوانین Audit و Security Log

### Audit Log الزامی برای:

- ایجاد Adjustment
- تأیید Adjustment
- رد Adjustment
- اعمال Adjustment
- ایجاد تسویه
- تأیید تسویه
- تکمیل تسویه
- لغو تسویه
- اعمال تخفیف
- بخشودگی جریمه
- مشاهده گزارش مالی حساس
- خروجی گرفتن از گزارش مالی
- تغییر تنظیمات مالی
- تغییر policyهای محاسبه مالی

### Financial Log الزامی برای:

- هر تغییر قطعی روی مبلغ قرارداد
- هر تغییر قطعی روی مبلغ قسط
- هر پرداخت approved
- هر refund processed
- هر allocation applied
- هر penalty applied
- هر penalty waived
- هر settlement completed
- هر manual adjustment applied

### Security Log الزامی برای:

- تلاش تغییر مبلغ بدون Permission
- تلاش مشاهده گزارش مالی بدون Permission
- تلاش Export مالی بدون Permission
- تلاش اعمال Adjustment بدون Permission
- تلاش تسویه قرارداد خارج از Scope
- CSRF نامعتبر در عملیات مالی
- دستکاری amount از سمت Frontend
- دستکاری contract_id، installment_id یا payment_id در عملیات مالی

---

## قوانین Index و Performance

قوانین:

- گزارش مالی باید بر اساس تاریخ سریع باشد.
- گزارش مالی مشتری باید بر اساس customer_id سریع باشد.
- گزارش مالی قرارداد باید بر اساس contract_id سریع باشد.
- financial_logs ممکن است بزرگ شود و باید Index مناسب داشته باشد.
- خروجی گزارش مالی باید محدود و Permission-based باشد.
- financial_calculation_logs قدیمی می‌توانند Archive شوند.
- Queryهای financial_logs باید Pagination داشته باشند.
- Index زیاد و بی‌دلیل ممنوع است.

Indexهای مهم:

```text
financial_logs.event_type
financial_logs.customer_id
financial_logs.contract_id
financial_logs.installment_id
financial_logs.payment_id
financial_logs.occurred_at
financial_adjustments.status
financial_adjustments.contract_id
settlement_records.contract_id
settlement_records.status
financial_snapshots.contract_id
account_ledger_entries.account_key
account_ledger_entries.posted_at
```

---

## قوانین Validation

### financial_logs

- log_number الزامی و یکتا است.
- event_type الزامی است.
- related_type الزامی است.
- related_id الزامی است.
- amount باید DECIMAL باشد.
- direction باید معتبر باشد.
- occurred_at الزامی است.

### financial_adjustments

- adjustment_number الزامی و یکتا است.
- adjustment_type الزامی است.
- related_type و related_id الزامی هستند.
- amount باید معتبر باشد.
- reason الزامی است.
- status باید معتبر باشد.
- Adjustment applied باید applied_by و applied_at داشته باشد.

### settlement_records

- settlement_number الزامی و یکتا است.
- customer_id الزامی است.
- contract_id الزامی است.
- settlement_date الزامی است.
- final_settlement_amount باید غیرمنفی باشد.
- status باید معتبر باشد.
- completed settlement باید completed_by و completed_at داشته باشد.

### settlement_items

- settlement_id الزامی است.
- item_type الزامی است.
- final_amount باید معتبر باشد.
- status باید معتبر باشد.

### financial_snapshots

- snapshot_number الزامی و یکتا است.
- snapshot_type الزامی است.
- related_type و related_id الزامی هستند.
- created_at الزامی است.

### account_ledger_entries

- entry_number الزامی و یکتا است.
- entry_type الزامی است.
- account_key الزامی است.
- debit_amount و credit_amount باید DECIMAL باشند.
- هر دو مبلغ نباید همزمان صفر باشند.
- posted_at الزامی است.

---

## Seedهای پیشنهادی

### event_type

```text
contract_created
contract_amount_changed
installment_created
installment_amount_changed
payment_approved
payment_allocated
payment_reversed
payment_refunded
penalty_applied
penalty_waived
discount_applied
manual_adjustment
settlement_created
settlement_completed
legal_referral_snapshot
guarantee_used
```

### adjustment_type

```text
discount
penalty_waiver
penalty_addition
installment_amount_correction
contract_amount_correction
payment_correction
balance_correction
settlement_adjustment
manual_credit
manual_debit
other
```

### settlement_type

```text
full_payment
discounted_settlement
legal_settlement
manual_settlement
guarantee_settlement
installment_settlement
other
```

### account_key

```text
cash
card_to_card
bank_gateway
accounts_receivable
installment_receivable
penalty_receivable
discounts
refunds
settlements
manual_adjustments
```

---

## چک‌لیست پیاده‌سازی

قبل از پیاده‌سازی Financial Tables بررسی شود:

- [ ] جدول `financial_logs` ساخته شده است.
- [ ] `log_number` یکتا است.
- [ ] جدول `financial_adjustments` ساخته شده است.
- [ ] `adjustment_number` یکتا است.
- [ ] جدول `settlement_records` ساخته شده است.
- [ ] `settlement_number` یکتا است.
- [ ] جدول `settlement_items` ساخته شده است.
- [ ] جدول `financial_snapshots` ساخته شده است.
- [ ] جدول `account_ledger_entries` ساخته شده است.
- [ ] جدول `financial_calculation_logs` ساخته شده است.
- [ ] همه مبلغ‌ها DECIMAL هستند.
- [ ] هیچ مبلغی با FLOAT یا DOUBLE ذخیره نشده است.
- [ ] عملیات مالی داخل Transaction انجام می‌شود.
- [ ] پرداخت approved باعث Financial Log می‌شود.
- [ ] Adjustment applied باعث Financial Log می‌شود.
- [ ] Settlement completed باعث Financial Log و Snapshot می‌شود.
- [ ] تلاش‌های غیرمجاز Security Log دارند.
- [ ] گزارش‌های مالی Permission-based هستند.
- [ ] Export گزارش مالی Audit Log دارد.

---

## Definition of Done

Financial Tables زمانی کامل هستند که:

- همه رویدادهای مالی مهم قابل ثبت و ردیابی باشند.
- هر تغییر مالی حساس Financial Log داشته باشد.
- اصلاحات مالی با شماره رسمی، دلیل، وضعیت و Approval مدیریت شوند.
- تسویه قرارداد با Snapshot، آیتم‌ها و Financial Log ثبت شود.
- Snapshotهای مالی در لحظه‌های مهم ذخیره شوند.
- Ledger ساده برای رویدادهای مالی قابل استفاده باشد.
- محاسبات مالی مهم قابل Log و بررسی باشند.
- هیچ مبلغی با FLOAT یا DOUBLE ذخیره نشود.
- Frontend منبع حقیقت مالی نباشد.
- تغییرات مالی بدون Permission انجام نشوند.
- گزارش‌های مالی Scope و Permission را رعایت کنند.
- Export گزارش مالی Audit Log داشته باشد.
- تلاش‌های غیرمجاز Security Log داشته باشند.
- جدول‌ها با MySQL/MariaDB و PHP 7.4+ سازگار باشند.
- Codex بتواند از روی این مستندات Migrationهای Financial Domain را بسازد.

---

## پایان فایل