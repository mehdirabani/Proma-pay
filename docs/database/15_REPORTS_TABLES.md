# 15 — Reports Tables

مستند جدول‌های گزارش‌ها، خروجی‌ها، قالب‌های گزارش، زمان‌بندی گزارش، فیلترهای ذخیره‌شده، لاگ اجرای گزارش و داده‌های وابسته به Reports Domain در پروژه **Proma Pay**

---

## فهرست مطالب

- [هدف فایل](#هدف-فایل)
- [تعریف Reports Domain در دیتابیس](#تعریف-reports-domain-در-دیتابیس)
- [اصل مهم](#اصل-مهم)
- [لیست جدول‌های Reports](#لیست-جدولهای-reports)
- [جدول reports](#جدول-reports)
- [جدول report_templates](#جدول-report_templates)
- [جدول report_filters](#جدول-report_filters)
- [جدول report_exports](#جدول-report_exports)
- [جدول report_schedules](#جدول-report_schedules)
- [جدول report_execution_logs](#جدول-report_execution_logs)
- [جدول report_access_logs](#جدول-report_access_logs)
- [جدول report_dashboard_widgets](#جدول-report_dashboard_widgets)
- [رابطه Reports Domain با سایر Domainها](#رابطه-reports-domain-با-سایر-domainها)
- [قوانین گزارش‌گیری](#قوانین-گزارشگیری)
- [قوانین خروجی گرفتن](#قوانین-خروجی-گرفتن)
- [قوانین گزارش‌های مالی و حقوقی](#قوانین-گزارشهای-مالی-و-حقوقی)
- [قوانین فیلتر و Scope](#قوانین-فیلتر-و-scope)
- [قوانین زمان‌بندی گزارش](#قوانین-زمانبندی-گزارش)
- [قوانین Soft Delete](#قوانین-soft-delete)
- [قوانین Index و Performance](#قوانین-index-و-performance)
- [قوانین Validation](#قوانین-validation)
- [قوانین Audit و Security Log](#قوانین-audit-و-security-log)
- [Seedهای پیشنهادی](#seedهای-پیشنهادی)
- [چک‌لیست پیاده‌سازی](#چکلیست-پیادهسازی)
- [Definition of Done](#definition-of-done)

---

## هدف فایل

هدف این فایل این است که ساختار جدول‌های مربوط به گزارش‌ها در پروژه **Proma Pay** مشخص شود.

Reports Domain برای ساخت، ذخیره، اجرای گزارش‌ها و خروجی گرفتن از داده‌های سیستم استفاده می‌شود.

این فایل برای Codex مشخص می‌کند که:

- گزارش‌ها چگونه تعریف شوند.
- قالب گزارش‌ها چگونه ذخیره شوند.
- فیلترهای گزارش چگونه نگهداری شوند.
- خروجی گزارش‌ها چگونه ساخته و ذخیره شوند.
- گزارش‌های زمان‌بندی‌شده چگونه اجرا شوند.
- اجرای گزارش‌ها چگونه Log شود.
- دسترسی و دانلود گزارش‌های حساس چگونه کنترل شود.
- گزارش‌های مالی، حقوقی و مشتری چگونه Permission و Scope داشته باشند.

---

## تعریف Reports Domain در دیتابیس

Reports Domain مسئول مدیریت گزارش‌های قابل مشاهده، قابل دانلود و قابل زمان‌بندی است.

گزارش‌ها می‌توانند شامل موارد زیر باشند:

- گزارش مشتریان
- گزارش قراردادها
- گزارش اقساط
- گزارش پرداخت‌ها
- گزارش معوقات
- گزارش مالی
- گزارش تسویه
- گزارش پرونده‌های حقوقی
- گزارش عملکرد اپراتورها
- گزارش اعلان‌ها
- گزارش فایل‌ها
- گزارش بکاپ
- گزارش امنیتی
- گزارش پلاگین‌ها
- گزارش سفارشی مدیریتی

---

## اصل مهم

اصل مهم در Reports Tables:

> گزارش‌ها نباید Permission، Scope، Privacy و امنیت سیستم را دور بزنند.

اگر کاربر در صفحه اصلی اجازه دیدن یک قرارداد، پرداخت، قسط، فایل یا پرونده حقوقی را ندارد، در گزارش هم نباید به آن دسترسی داشته باشد.

قانون طلایی:

> خروجی گزارش حساس باید مثل فایل حساس مدیریت شود؛ یعنی Private Storage، Expiration، Permission، Audit Log و Download Token داشته باشد.

---

## لیست جدول‌های Reports

جدول‌های پیشنهادی Reports Domain:

| جدول | کاربرد |
|---|---|
| `reports` | اطلاعات اصلی گزارش‌ها |
| `report_templates` | قالب‌ها و ساختار گزارش |
| `report_filters` | فیلترهای ذخیره‌شده گزارش |
| `report_exports` | فایل‌های خروجی گزارش |
| `report_schedules` | زمان‌بندی اجرای گزارش |
| `report_execution_logs` | لاگ اجرای گزارش |
| `report_access_logs` | لاگ مشاهده و دانلود گزارش |
| `report_dashboard_widgets` | ویجت‌های داشبورد گزارش |

---

## جدول reports

### هدف جدول

جدول `reports` اطلاعات اصلی گزارش‌های قابل استفاده در سیستم را نگهداری می‌کند.

هر گزارش می‌تواند سیستمی، مدیریتی، مالی، حقوقی یا سفارشی باشد.

---

### نام جدول

```text
reports
```

---

### ستون‌های پیشنهادی

| ستون | نوع | توضیح |
|---|---|---|
| `id` | BIGINT UNSIGNED AUTO_INCREMENT | شناسه داخلی |
| `report_number` | VARCHAR(50) | شماره رسمی گزارش |
| `report_key` | VARCHAR(150) | کلید یکتا |
| `name` | VARCHAR(191) | نام گزارش |
| `description` | TEXT NULL | توضیح |
| `report_type` | VARCHAR(50) | نوع گزارش |
| `domain_key` | VARCHAR(100) | Domain مرتبط |
| `data_source` | VARCHAR(100) | منبع داده |
| `visibility` | VARCHAR(50) | سطح نمایش |
| `is_sensitive` | TINYINT(1) | حساس بودن |
| `is_system` | TINYINT(1) | سیستمی بودن |
| `is_exportable` | TINYINT(1) | قابلیت خروجی گرفتن |
| `requires_permission` | TINYINT(1) | نیاز به Permission |
| `permission_key` | VARCHAR(150) NULL | Permission لازم |
| `default_format` | VARCHAR(50) | فرمت پیش‌فرض |
| `status` | VARCHAR(50) | وضعیت گزارش |
| `sort_order` | INT UNSIGNED | ترتیب نمایش |
| `created_by` | BIGINT UNSIGNED NULL | ایجادکننده |
| `updated_by` | BIGINT UNSIGNED NULL | ویرایش‌کننده |
| `created_at` | DATETIME NULL | زمان ایجاد |
| `updated_at` | DATETIME NULL | زمان بروزرسانی |
| `deleted_at` | DATETIME NULL | زمان حذف نرم |
| `deleted_by` | BIGINT UNSIGNED NULL | حذف‌کننده |

---

### report_typeهای پیشنهادی

```text
table
summary
chart
dashboard
export_only
system
custom
```

---

### domain_keyهای پیشنهادی

```text
customers
contracts
installments
payments
financial
legal
files
notifications
calendar
backup
plugins
security
audit
system
```

---

### default_formatهای پیشنهادی

```text
html
pdf
xlsx
csv
json
```

---

### statusهای پیشنهادی

```text
active
inactive
draft
deprecated
deleted
```

---

### قوانین

- `report_number` باید Unique باشد.
- `report_key` باید Unique باشد.
- گزارش حساس باید is_sensitive = 1 داشته باشد.
- گزارش مالی و حقوقی باید Permission جدا داشته باشد.
- گزارش سیستمی نباید بدون Permission ویژه حذف شود.
- گزارش غیرفعال نباید در UI عمومی نمایش داده شود.
- گزارش exportable باید قوانین Export را رعایت کند.
- گزارش نباید داده خارج از Scope کاربر را نمایش دهد.
- حذف گزارش باید Soft Delete باشد.

---

### Indexهای پیشنهادی

```text
uniq_reports_report_number
uniq_reports_report_key
idx_reports_report_type
idx_reports_domain_key
idx_reports_visibility
idx_reports_is_sensitive
idx_reports_is_system
idx_reports_is_exportable
idx_reports_permission_key
idx_reports_status
idx_reports_sort_order
idx_reports_deleted_at
```

---

### نمونه ساختار SQL

```sql
CREATE TABLE reports (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    report_number VARCHAR(50) NOT NULL,
    report_key VARCHAR(150) NOT NULL,
    name VARCHAR(191) NOT NULL,
    description TEXT NULL,
    report_type VARCHAR(50) NOT NULL DEFAULT 'table',
    domain_key VARCHAR(100) NOT NULL,
    data_source VARCHAR(100) NOT NULL,
    visibility VARCHAR(50) NOT NULL DEFAULT 'internal',
    is_sensitive TINYINT(1) NOT NULL DEFAULT 0,
    is_system TINYINT(1) NOT NULL DEFAULT 0,
    is_exportable TINYINT(1) NOT NULL DEFAULT 1,
    requires_permission TINYINT(1) NOT NULL DEFAULT 1,
    permission_key VARCHAR(150) NULL,
    default_format VARCHAR(50) NOT NULL DEFAULT 'html',
    status VARCHAR(50) NOT NULL DEFAULT 'active',
    sort_order INT UNSIGNED NOT NULL DEFAULT 0,
    created_by BIGINT UNSIGNED NULL,
    updated_by BIGINT UNSIGNED NULL,
    created_at DATETIME NULL,
    updated_at DATETIME NULL,
    deleted_at DATETIME NULL,
    deleted_by BIGINT UNSIGNED NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uniq_reports_report_number (report_number),
    UNIQUE KEY uniq_reports_report_key (report_key),
    KEY idx_reports_report_type (report_type),
    KEY idx_reports_domain_key (domain_key),
    KEY idx_reports_visibility (visibility),
    KEY idx_reports_is_sensitive (is_sensitive),
    KEY idx_reports_is_system (is_system),
    KEY idx_reports_is_exportable (is_exportable),
    KEY idx_reports_permission_key (permission_key),
    KEY idx_reports_status (status),
    KEY idx_reports_sort_order (sort_order),
    KEY idx_reports_deleted_at (deleted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

---

## جدول report_templates

### هدف جدول

جدول `report_templates` قالب و ساختار نمایش یا خروجی گزارش را نگهداری می‌کند.

این جدول مشخص می‌کند گزارش چه ستون‌هایی، چه فیلدهایی، چه فرمت‌هایی و چه تنظیماتی دارد.

---

### نام جدول

```text
report_templates
```

---

### ستون‌های پیشنهادی

| ستون | نوع | توضیح |
|---|---|---|
| `id` | BIGINT UNSIGNED AUTO_INCREMENT | شناسه |
| `template_key` | VARCHAR(150) | کلید قالب |
| `report_id` | BIGINT UNSIGNED | گزارش |
| `name` | VARCHAR(191) | نام قالب |
| `description` | TEXT NULL | توضیح |
| `template_type` | VARCHAR(50) | نوع قالب |
| `layout_config` | JSON NULL | تنظیمات Layout |
| `columns_config` | JSON NULL | ستون‌ها |
| `filters_config` | JSON NULL | فیلترهای مجاز |
| `sorting_config` | JSON NULL | مرتب‌سازی |
| `export_config` | JSON NULL | تنظیمات خروجی |
| `chart_config` | JSON NULL | تنظیمات نمودار |
| `is_default` | TINYINT(1) | پیش‌فرض بودن |
| `is_active` | TINYINT(1) | فعال بودن |
| `created_by` | BIGINT UNSIGNED NULL | ایجادکننده |
| `updated_by` | BIGINT UNSIGNED NULL | ویرایش‌کننده |
| `created_at` | DATETIME NULL | زمان ایجاد |
| `updated_at` | DATETIME NULL | زمان بروزرسانی |
| `deleted_at` | DATETIME NULL | زمان حذف نرم |
| `deleted_by` | BIGINT UNSIGNED NULL | حذف‌کننده |

---

### template_typeهای پیشنهادی

```text
table
summary
chart
pdf
xlsx
csv
dashboard
custom
```

---

### قوانین

- `template_key` باید Unique باشد.
- هر Template باید report_id معتبر داشته باشد.
- فقط یک Template پیش‌فرض برای هر گزارش و نوع قالب مجاز است.
- columns_config باید فقط ستون‌های مجاز را شامل شود.
- filters_config باید فقط فیلترهای مجاز را شامل شود.
- Template حساس نباید ستون‌های محرمانه را برای نقش غیرمجاز نمایش دهد.
- تغییر Template گزارش حساس باید Audit Log داشته باشد.
- حذف Template باید Soft Delete باشد.

---

### Indexهای پیشنهادی

```text
uniq_report_templates_template_key
idx_report_templates_report_id
idx_report_templates_template_type
idx_report_templates_is_default
idx_report_templates_is_active
idx_report_templates_created_by
idx_report_templates_deleted_at
```

---

## جدول report_filters

### هدف جدول

جدول `report_filters` فیلترهای ذخیره‌شده برای گزارش‌ها را نگهداری می‌کند.

کاربر یا سیستم می‌تواند یک مجموعه فیلتر را ذخیره کند و دوباره استفاده کند.

---

### نام جدول

```text
report_filters
```

---

### ستون‌های پیشنهادی

| ستون | نوع | توضیح |
|---|---|---|
| `id` | BIGINT UNSIGNED AUTO_INCREMENT | شناسه |
| `filter_number` | VARCHAR(50) | شماره رسمی فیلتر |
| `report_id` | BIGINT UNSIGNED | گزارش |
| `user_id` | BIGINT UNSIGNED NULL | مالک کاربر |
| `name` | VARCHAR(191) | نام فیلتر |
| `description` | TEXT NULL | توضیح |
| `filter_config` | JSON | تنظیمات فیلتر |
| `scope_config` | JSON NULL | تنظیمات Scope |
| `is_default` | TINYINT(1) | فیلتر پیش‌فرض |
| `is_shared` | TINYINT(1) | اشتراکی بودن |
| `visibility` | VARCHAR(50) | سطح نمایش |
| `status` | VARCHAR(50) | وضعیت |
| `created_by` | BIGINT UNSIGNED NULL | ایجادکننده |
| `updated_by` | BIGINT UNSIGNED NULL | ویرایش‌کننده |
| `created_at` | DATETIME NULL | زمان ایجاد |
| `updated_at` | DATETIME NULL | زمان بروزرسانی |
| `deleted_at` | DATETIME NULL | زمان حذف نرم |
| `deleted_by` | BIGINT UNSIGNED NULL | حذف‌کننده |

---

### visibilityهای پیشنهادی

```text
private
team
role
global
system
```

---

### statusهای پیشنهادی

```text
active
inactive
deleted
```

---

### قوانین

- `filter_number` باید Unique باشد.
- هر Filter باید report_id داشته باشد.
- filter_config باید JSON معتبر باشد.
- فیلتر نباید اجازه دسترسی خارج از Scope بدهد.
- فیلتر اشتراکی باید Permission داشته باشد.
- فیلتر Global فقط با Permission مدیریتی ساخته شود.
- حذف فیلتر باید Soft Delete باشد.
- فیلتر پیش‌فرض کاربر نباید با فیلتر پیش‌فرض سیستم تداخل داشته باشد.

---

### Indexهای پیشنهادی

```text
uniq_report_filters_filter_number
idx_report_filters_report_id
idx_report_filters_user_id
idx_report_filters_is_default
idx_report_filters_is_shared
idx_report_filters_visibility
idx_report_filters_status
idx_report_filters_created_by
idx_report_filters_deleted_at
```

---

## جدول report_exports

### هدف جدول

جدول `report_exports` خروجی‌های ساخته‌شده از گزارش‌ها را نگهداری می‌کند.

خروجی گزارش معمولاً یک فایل است و باید از Files Domain استفاده کند.

---

### نام جدول

```text
report_exports
```

---

### ستون‌های پیشنهادی

| ستون | نوع | توضیح |
|---|---|---|
| `id` | BIGINT UNSIGNED AUTO_INCREMENT | شناسه |
| `export_number` | VARCHAR(50) | شماره رسمی خروجی |
| `report_id` | BIGINT UNSIGNED | گزارش |
| `template_id` | BIGINT UNSIGNED NULL | قالب استفاده‌شده |
| `filter_id` | BIGINT UNSIGNED NULL | فیلتر استفاده‌شده |
| `file_id` | BIGINT UNSIGNED NULL | فایل خروجی |
| `requested_by` | BIGINT UNSIGNED NULL | درخواست‌دهنده |
| `export_format` | VARCHAR(50) | فرمت خروجی |
| `status` | VARCHAR(50) | وضعیت خروجی |
| `row_count` | INT UNSIGNED NULL | تعداد ردیف |
| `file_size_bytes` | BIGINT UNSIGNED NULL | حجم فایل |
| `is_sensitive` | TINYINT(1) | حساس بودن |
| `expires_at` | DATETIME NULL | زمان انقضا |
| `started_at` | DATETIME NULL | زمان شروع |
| `completed_at` | DATETIME NULL | زمان تکمیل |
| `failed_at` | DATETIME NULL | زمان شکست |
| `error_message` | TEXT NULL | پیام خطا |
| `request_payload` | JSON NULL | داده درخواست |
| `metadata` | JSON NULL | داده تکمیلی |
| `created_at` | DATETIME NULL | زمان ایجاد |
| `updated_at` | DATETIME NULL | زمان بروزرسانی |
| `deleted_at` | DATETIME NULL | زمان حذف نرم |
| `deleted_by` | BIGINT UNSIGNED NULL | حذف‌کننده |

---

### export_formatهای پیشنهادی

```text
pdf
xlsx
csv
json
html
```

---

### statusهای پیشنهادی

```text
queued
processing
completed
failed
cancelled
expired
deleted
```

---

### قوانین

- `export_number` باید Unique باشد.
- هر Export باید report_id داشته باشد.
- خروجی completed باید file_id داشته باشد.
- خروجی حساس باید در Private Storage ذخیره شود.
- خروجی مالی، حقوقی، مشتری و پرداخت حساس است.
- خروجی باید expires_at داشته باشد، مگر Policy اجازه دهد.
- دانلود خروجی باید Permission و Audit Log داشته باشد.
- Export نباید داده خارج از Scope کاربر را شامل شود.
- Export بزرگ باید Queue/Job شود.
- Export failed باید error_message داشته باشد.
- حذف Export باید Soft Delete باشد.

---

### Indexهای پیشنهادی

```text
uniq_report_exports_export_number
idx_report_exports_report_id
idx_report_exports_template_id
idx_report_exports_filter_id
idx_report_exports_file_id
idx_report_exports_requested_by
idx_report_exports_export_format
idx_report_exports_status
idx_report_exports_is_sensitive
idx_report_exports_expires_at
idx_report_exports_created_at
idx_report_exports_deleted_at
```

---

## جدول report_schedules

### هدف جدول

جدول `report_schedules` زمان‌بندی اجرای خودکار گزارش‌ها را نگهداری می‌کند.

مثلاً:

- گزارش روزانه پرداخت‌ها
- گزارش هفتگی معوقات
- گزارش ماهانه مالی
- گزارش پرونده‌های حقوقی فعال
- گزارش بکاپ و خطاهای سیستم

---

### نام جدول

```text
report_schedules
```

---

### ستون‌های پیشنهادی

| ستون | نوع | توضیح |
|---|---|---|
| `id` | BIGINT UNSIGNED AUTO_INCREMENT | شناسه |
| `schedule_number` | VARCHAR(50) | شماره رسمی زمان‌بندی |
| `report_id` | BIGINT UNSIGNED | گزارش |
| `template_id` | BIGINT UNSIGNED NULL | قالب |
| `filter_id` | BIGINT UNSIGNED NULL | فیلتر |
| `name` | VARCHAR(191) | نام زمان‌بندی |
| `frequency` | VARCHAR(50) | فرکانس اجرا |
| `interval_value` | INT UNSIGNED | فاصله اجرا |
| `run_at_time` | TIME NULL | ساعت اجرا |
| `day_of_week` | INT UNSIGNED NULL | روز هفته |
| `day_of_month` | INT UNSIGNED NULL | روز ماه |
| `timezone` | VARCHAR(100) NULL | منطقه زمانی |
| `export_format` | VARCHAR(50) | فرمت خروجی |
| `send_to_users` | JSON NULL | کاربران گیرنده |
| `send_to_emails` | JSON NULL | ایمیل‌های گیرنده |
| `status` | VARCHAR(50) | وضعیت |
| `last_run_at` | DATETIME NULL | آخرین اجرا |
| `next_run_at` | DATETIME NULL | اجرای بعدی |
| `created_by` | BIGINT UNSIGNED NULL | ایجادکننده |
| `updated_by` | BIGINT UNSIGNED NULL | ویرایش‌کننده |
| `created_at` | DATETIME NULL | زمان ایجاد |
| `updated_at` | DATETIME NULL | زمان بروزرسانی |
| `deleted_at` | DATETIME NULL | زمان حذف نرم |
| `deleted_by` | BIGINT UNSIGNED NULL | حذف‌کننده |

---

### frequencyهای پیشنهادی

```text
hourly
daily
weekly
monthly
yearly
custom
```

---

### statusهای پیشنهادی

```text
active
paused
completed
cancelled
failed
deleted
```

---

### قوانین

- `schedule_number` باید Unique باشد.
- هر Schedule باید report_id داشته باشد.
- frequency باید معتبر باشد.
- next_run_at باید قابل محاسبه باشد.
- گزارش زمان‌بندی‌شده باید Permission ایجادکننده یا Service Account معتبر داشته باشد.
- ارسال گزارش حساس به Email خارجی باید محدود یا ممنوع باشد.
- Schedule غیرفعال نباید اجرا شود.
- حذف Schedule باید Soft Delete باشد.
- اجرای Schedule باید report_execution_log ایجاد کند.

---

### Indexهای پیشنهادی

```text
uniq_report_schedules_schedule_number
idx_report_schedules_report_id
idx_report_schedules_template_id
idx_report_schedules_filter_id
idx_report_schedules_frequency
idx_report_schedules_status
idx_report_schedules_last_run_at
idx_report_schedules_next_run_at
idx_report_schedules_created_by
idx_report_schedules_deleted_at
```

---

## جدول report_execution_logs

### هدف جدول

جدول `report_execution_logs` لاگ اجرای گزارش‌ها را ذخیره می‌کند.

هر بار که گزارشی اجرا شود، یک Log می‌تواند ثبت شود.

---

### نام جدول

```text
report_execution_logs
```

---

### ستون‌های پیشنهادی

| ستون | نوع | توضیح |
|---|---|---|
| `id` | BIGINT UNSIGNED AUTO_INCREMENT | شناسه |
| `execution_number` | VARCHAR(50) | شماره رسمی اجرا |
| `report_id` | BIGINT UNSIGNED | گزارش |
| `schedule_id` | BIGINT UNSIGNED NULL | زمان‌بندی مرتبط |
| `export_id` | BIGINT UNSIGNED NULL | خروجی مرتبط |
| `executed_by` | BIGINT UNSIGNED NULL | اجراکننده |
| `execution_type` | VARCHAR(50) | نوع اجرا |
| `status` | VARCHAR(50) | وضعیت اجرا |
| `filters_used` | JSON NULL | فیلترهای استفاده‌شده |
| `scope_used` | JSON NULL | Scope اعمال‌شده |
| `row_count` | INT UNSIGNED NULL | تعداد ردیف |
| `duration_ms` | INT UNSIGNED NULL | مدت اجرا |
| `memory_peak_mb` | DECIMAL(10,2) NULL | بیشینه حافظه |
| `started_at` | DATETIME | زمان شروع |
| `completed_at` | DATETIME NULL | زمان تکمیل |
| `failed_at` | DATETIME NULL | زمان شکست |
| `error_message` | TEXT NULL | پیام خطا |
| `metadata` | JSON NULL | داده تکمیلی |
| `created_at` | DATETIME NULL | زمان ایجاد |

---

### execution_typeهای پیشنهادی

```text
manual_view
manual_export
scheduled_export
dashboard_load
api
system
```

---

### statusهای پیشنهادی

```text
running
completed
failed
cancelled
timeout
blocked
```

---

### قوانین

- `execution_number` باید Unique باشد.
- هر Execution باید report_id داشته باشد.
- اجرای failed باید error_message داشته باشد.
- اجرای گزارش حساس باید scope_used ثبت کند.
- اجرای Export باید export_id داشته باشد.
- اجرای Scheduled باید schedule_id داشته باشد.
- Log اجرا نباید Soft Delete شود.
- Queryهای سنگین باید duration_ms و status مناسب ثبت کنند.
- اجرای Block شده به دلیل Permission باید Security Log هم داشته باشد.

---

### Indexهای پیشنهادی

```text
uniq_report_execution_logs_execution_number
idx_report_execution_logs_report_id
idx_report_execution_logs_schedule_id
idx_report_execution_logs_export_id
idx_report_execution_logs_executed_by
idx_report_execution_logs_execution_type
idx_report_execution_logs_status
idx_report_execution_logs_started_at
idx_report_execution_logs_completed_at
idx_report_execution_logs_failed_at
```

---

## جدول report_access_logs

### هدف جدول

جدول `report_access_logs` مشاهده، اجرا، دانلود یا تلاش دسترسی به گزارش را ثبت می‌کند.

این جدول برای گزارش‌های حساس بسیار مهم است.

---

### نام جدول

```text
report_access_logs
```

---

### ستون‌های پیشنهادی

| ستون | نوع | توضیح |
|---|---|---|
| `id` | BIGINT UNSIGNED AUTO_INCREMENT | شناسه |
| `report_id` | BIGINT UNSIGNED | گزارش |
| `export_id` | BIGINT UNSIGNED NULL | خروجی گزارش |
| `access_type` | VARCHAR(50) | نوع دسترسی |
| `access_result` | VARCHAR(50) | نتیجه دسترسی |
| `user_id` | BIGINT UNSIGNED NULL | کاربر |
| `customer_id` | BIGINT UNSIGNED NULL | مشتری در صورت نیاز |
| `ip_address` | VARCHAR(45) NULL | IP |
| `user_agent` | TEXT NULL | User Agent |
| `reason` | TEXT NULL | دلیل یا توضیح |
| `created_at` | DATETIME NULL | زمان ثبت |

---

### access_typeهای پیشنهادی

```text
view
execute
export
download
schedule
delete
permission_check
```

---

### access_resultهای پیشنهادی

```text
allowed
denied
failed
blocked
expired
not_found
```

---

### قوانین

- مشاهده گزارش حساس باید access_log داشته باشد.
- دانلود Export حساس باید access_log داشته باشد.
- تلاش Denied برای گزارش حساس باید Security Log هم داشته باشد.
- report_access_logs نباید Soft Delete شود.
- اطلاعات حساس غیرضروری نباید در reason ذخیره شود.

---

### Indexهای پیشنهادی

```text
idx_report_access_logs_report_id
idx_report_access_logs_export_id
idx_report_access_logs_access_type
idx_report_access_logs_access_result
idx_report_access_logs_user_id
idx_report_access_logs_customer_id
idx_report_access_logs_created_at
```

---

## جدول report_dashboard_widgets

### هدف جدول

جدول `report_dashboard_widgets` ویجت‌های داشبورد گزارش‌ها را نگهداری می‌کند.

این ویجت‌ها برای نمایش خلاصه‌های مدیریتی، مالی، اقساط، پرداخت، حقوقی و عملیاتی استفاده می‌شوند.

---

### نام جدول

```text
report_dashboard_widgets
```

---

### ستون‌های پیشنهادی

| ستون | نوع | توضیح |
|---|---|---|
| `id` | BIGINT UNSIGNED AUTO_INCREMENT | شناسه |
| `widget_key` | VARCHAR(150) | کلید ویجت |
| `report_id` | BIGINT UNSIGNED NULL | گزارش مرتبط |
| `name` | VARCHAR(191) | نام ویجت |
| `description` | TEXT NULL | توضیح |
| `widget_type` | VARCHAR(50) | نوع ویجت |
| `domain_key` | VARCHAR(100) | Domain مرتبط |
| `config` | JSON NULL | تنظیمات ویجت |
| `permission_key` | VARCHAR(150) NULL | Permission لازم |
| `visibility` | VARCHAR(50) | سطح نمایش |
| `is_sensitive` | TINYINT(1) | حساس بودن |
| `is_active` | TINYINT(1) | فعال بودن |
| `sort_order` | INT UNSIGNED | ترتیب نمایش |
| `created_by` | BIGINT UNSIGNED NULL | ایجادکننده |
| `updated_by` | BIGINT UNSIGNED NULL | ویرایش‌کننده |
| `created_at` | DATETIME NULL | زمان ایجاد |
| `updated_at` | DATETIME NULL | زمان بروزرسانی |
| `deleted_at` | DATETIME NULL | زمان حذف نرم |
| `deleted_by` | BIGINT UNSIGNED NULL | حذف‌کننده |

---

### widget_typeهای پیشنهادی

```text
stat_card
table
chart
progress
timeline
alert_list
summary
custom
```

---

### قوانین

- `widget_key` باید Unique باشد.
- Widget حساس باید Permission داشته باشد.
- Widget مالی و حقوقی نباید برای نقش غیرمجاز نمایش داده شود.
- config باید JSON معتبر باشد.
- Widget غیرفعال نباید در Dashboard عادی نمایش داده شود.
- حذف Widget باید Soft Delete باشد.
- Query ویجت باید Scope کاربر را رعایت کند.

---

### Indexهای پیشنهادی

```text
uniq_report_dashboard_widgets_widget_key
idx_report_dashboard_widgets_report_id
idx_report_dashboard_widgets_widget_type
idx_report_dashboard_widgets_domain_key
idx_report_dashboard_widgets_permission_key
idx_report_dashboard_widgets_visibility
idx_report_dashboard_widgets_is_sensitive
idx_report_dashboard_widgets_is_active
idx_report_dashboard_widgets_sort_order
idx_report_dashboard_widgets_deleted_at
```

---

## رابطه Reports Domain با سایر Domainها

Reports Domain به اکثر بخش‌های سیستم وابسته است.

| Domain | رابطه |
|---|---|
| Customers | گزارش مشتریان، اعتبار، مدارک |
| Contracts | گزارش قراردادها، وضعیت‌ها، تسویه |
| Installments | گزارش اقساط، سررسید، معوقات |
| Payments | گزارش پرداخت‌ها، رسیدها، درگاه |
| Financial | گزارش مالی، Ledger، Settlement |
| Legal | گزارش پرونده‌های حقوقی |
| Files | فایل خروجی گزارش‌ها |
| Notifications | ارسال گزارش زمان‌بندی‌شده |
| Calendar | گزارش تسک‌ها و رویدادها |
| Users | گزارش عملکرد کاربران |
| Audit | خروجی و مشاهده گزارش‌های حساس |
| Security | تلاش دسترسی غیرمجاز به گزارش‌ها |
| Backup | گزارش بکاپ‌ها و خطاها |
| Plugins | گزارش وضعیت پلاگین‌ها |

---

## قوانین گزارش‌گیری

قوانین:

- گزارش باید Permission داشته باشد.
- گزارش باید Scope کاربر را رعایت کند.
- گزارش‌های حساس باید access_log داشته باشند.
- گزارش‌های مالی و حقوقی باید محدودتر باشند.
- گزارش نباید داده Soft Deleted را بدون اجازه نمایش دهد.
- گزارش نباید Secret یا اطلاعات خام حساس را نمایش دهد.
- گزارش‌های بزرگ باید Pagination داشته باشند.
- گزارش‌های سنگین باید Queue یا محدودیت اجرا داشته باشند.
- فیلتر تاریخ برای گزارش‌های سنگین الزامی یا پیشنهادی است.

---

## قوانین خروجی گرفتن

قوانین:

- Export گزارش حساس باید در Private Storage ذخیره شود.
- Export باید file_id از Files Domain داشته باشد.
- Export باید expires_at داشته باشد.
- Export باید Audit Log داشته باشد.
- دانلود Export حساس باید access_log و file_access_log داشته باشد.
- Export نباید داده خارج از Scope کاربر را شامل شود.
- Export بزرگ باید به صورت Job اجرا شود.
- Export failed باید error_message داشته باشد.
- Export فایل مالی، حقوقی، مشتری، پرداخت و قرارداد حساس است.
- ارسال Export حساس به Email خارجی باید محدود یا ممنوع باشد.

---

## قوانین گزارش‌های مالی و حقوقی

قوانین:

- گزارش مالی باید Permission مالی داشته باشد.
- گزارش حقوقی باید Permission حقوقی داشته باشد.
- گزارش مالی نباید فرمول داخلی سود یا دیرکرد را برای نقش غیرمجاز نمایش دهد.
- گزارش حقوقی نباید یادداشت داخلی وکیل را برای نقش غیرمجاز نمایش دهد.
- گزارش پرداخت‌ها نباید اطلاعات کارت یا رسید را بدون Permission نمایش دهد.
- گزارش فایل‌ها نباید storage_path واقعی را نمایش دهد.
- Export گزارش مالی و حقوقی باید Audit Log داشته باشد.
- مشاهده گزارش مالی یا حقوقی حساس باید access_log داشته باشد.

---

## قوانین فیلتر و Scope

قوانین:

- فیلتر نباید Scope را دور بزند.
- اگر کاربر فقط به شعبه، قرارداد یا مشتری خاص دسترسی دارد، گزارش هم همان محدودیت را داشته باشد.
- filter_config باید فقط فیلدهای مجاز را قبول کند.
- فیلترهای ورودی باید Validate شوند.
- فیلترهای تاریخ باید محدودیت منطقی داشته باشند.
- فیلترهای ذخیره‌شده توسط کاربر دیگر نباید بدون Permission استفاده شوند.
- فیلتر Global باید با Permission مدیریتی ساخته شود.

---

## قوانین زمان‌بندی گزارش

قوانین:

- Schedule فعال باید next_run_at داشته باشد.
- Schedule غیرفعال نباید اجرا شود.
- Schedule گزارش حساس باید Permission ایجادکننده یا Service Account معتبر داشته باشد.
- اجرای Schedule باید execution_log بسازد.
- Export تولیدشده از Schedule باید expires_at داشته باشد.
- ارسال خودکار گزارش حساس باید محدود باشد.
- خطای Schedule باید قابل مشاهده در Dashboard یا Logs باشد.
- حذف Schedule باید Soft Delete باشد.

---

## قوانین Soft Delete

جدول‌های زیر باید Soft Delete داشته باشند:

- `reports`
- `report_templates`
- `report_filters`
- `report_exports`
- `report_schedules`
- `report_dashboard_widgets`

قوانین:

- گزارش سیستمی نباید فیزیکی حذف شود.
- Export حذف‌شده نباید دانلود شود.
- فایل Export حذف‌شده باید از دسترسی خارج شود.
- execution_logs و access_logs نباید Soft Delete شوند.
- حذف گزارش حساس باید Audit Log داشته باشد.
- حذف Template یا Schedule حساس باید Audit Log داشته باشد.

---

## قوانین Index و Performance

قوانین:

- گزارش‌ها باید با report_key سریع پیدا شوند.
- گزارش‌های فعال باید سریع فیلتر شوند.
- Exportهای کاربر باید سریع نمایش داده شوند.
- Scheduleهای next_run_at باید سریع پیدا شوند.
- Execution Log ممکن است بزرگ شود و باید Index مناسب داشته باشد.
- Access Log گزارش‌های حساس ممکن است بزرگ شود و باید Archive Policy داشته باشد.
- گزارش‌های بزرگ باید Pagination داشته باشند.
- Queryهای گزارش باید از Indexهای Domain اصلی استفاده کنند.

Indexهای مهم:

```text
reports.report_key
reports.domain_key
reports.status
report_exports.report_id
report_exports.requested_by
report_exports.status
report_exports.expires_at
report_schedules.next_run_at
report_schedules.status
report_execution_logs.report_id
report_execution_logs.started_at
report_access_logs.report_id
report_access_logs.created_at
```

---

## قوانین Validation

### reports

- report_number الزامی و یکتا است.
- report_key الزامی و یکتا است.
- name الزامی است.
- report_type معتبر باشد.
- domain_key معتبر باشد.
- default_format معتبر باشد.
- گزارش حساس باید permission_key داشته باشد.

### report_templates

- template_key الزامی و یکتا است.
- report_id الزامی است.
- template_type معتبر باشد.
- configهای JSON باید معتبر باشند.
- ستون‌های حساس باید Permission داشته باشند.

### report_filters

- filter_number الزامی و یکتا است.
- report_id الزامی است.
- name الزامی است.
- filter_config باید JSON معتبر باشد.
- visibility معتبر باشد.
- فیلتر Shared باید Permission داشته باشد.

### report_exports

- export_number الزامی و یکتا است.
- report_id الزامی است.
- export_format معتبر باشد.
- completed export باید file_id داشته باشد.
- failed export باید error_message داشته باشد.
- خروجی حساس باید expires_at داشته باشد.

### report_schedules

- schedule_number الزامی و یکتا است.
- report_id الزامی است.
- name الزامی است.
- frequency معتبر باشد.
- interval_value باید بیشتر از صفر باشد.
- active schedule باید next_run_at داشته باشد.

### report_execution_logs

- execution_number الزامی و یکتا است.
- report_id الزامی است.
- execution_type معتبر باشد.
- status معتبر باشد.
- started_at الزامی است.
- failed status باید error_message داشته باشد.

### report_dashboard_widgets

- widget_key الزامی و یکتا است.
- name الزامی است.
- widget_type معتبر باشد.
- domain_key معتبر باشد.
- Widget حساس باید permission_key داشته باشد.

---

## قوانین Audit و Security Log

### Audit Log الزامی برای:

- مشاهده گزارش مالی حساس
- مشاهده گزارش حقوقی حساس
- خروجی گرفتن از گزارش حساس
- دانلود خروجی گزارش حساس
- ایجاد یا ویرایش گزارش سیستمی
- حذف گزارش
- ایجاد Template حساس
- ویرایش Template حساس
- ایجاد فیلتر Global
- ایجاد Schedule برای گزارش حساس
- حذف Schedule حساس
- تغییر ویجت حساس Dashboard
- ارسال خودکار گزارش به گیرنده خارجی

### Security Log الزامی برای:

- تلاش مشاهده گزارش بدون Permission
- تلاش مشاهده گزارش خارج از Scope
- تلاش خروجی گرفتن بدون Permission
- تلاش دانلود Export منقضی‌شده
- تلاش دانلود Export کاربر دیگر
- تلاش مشاهده گزارش حقوقی توسط نقش غیرمجاز
- تلاش مشاهده گزارش مالی توسط نقش غیرمجاز
- تلاش دستکاری report_id یا export_id
- CSRF نامعتبر در Export یا Delete
- تلاش نمایش ستون حساس بدون Permission

---

## Seedهای پیشنهادی

### report_type

```text
table
summary
chart
dashboard
export_only
system
custom
```

### domain_key

```text
customers
contracts
installments
payments
financial
legal
files
notifications
calendar
backup
plugins
security
audit
system
```

### export_format

```text
pdf
xlsx
csv
json
html
```

### execution_type

```text
manual_view
manual_export
scheduled_export
dashboard_load
api
system
```

### access_type

```text
view
execute
export
download
schedule
delete
permission_check
```

### widget_type

```text
stat_card
table
chart
progress
timeline
alert_list
summary
custom
```

---

## چک‌لیست پیاده‌سازی

قبل از پیاده‌سازی Reports Tables بررسی شود:

- [ ] جدول `reports` ساخته شده است.
- [ ] `report_number` یکتا است.
- [ ] `report_key` یکتا است.
- [ ] گزارش‌های حساس Permission دارند.
- [ ] جدول `report_templates` ساخته شده است.
- [ ] Templateها فقط ستون‌های مجاز را نمایش می‌دهند.
- [ ] جدول `report_filters` ساخته شده است.
- [ ] فیلترها Scope را دور نمی‌زنند.
- [ ] جدول `report_exports` ساخته شده است.
- [ ] Exportهای حساس در Private Storage ذخیره می‌شوند.
- [ ] جدول `report_schedules` ساخته شده است.
- [ ] Scheduleهای فعال next_run_at دارند.
- [ ] جدول `report_execution_logs` ساخته شده است.
- [ ] اجرای گزارش‌های حساس Log می‌شود.
- [ ] جدول `report_access_logs` ساخته شده است.
- [ ] مشاهده و دانلود گزارش حساس Log می‌شود.
- [ ] جدول `report_dashboard_widgets` ساخته شده است.
- [ ] Widgetهای حساس Permission دارند.
- [ ] Export گزارش حساس Audit Log دارد.
- [ ] تلاش‌های غیرمجاز Security Log دارند.

---

## Definition of Done

Reports Tables زمانی کامل هستند که:

- گزارش‌ها با شماره رسمی و key یکتا قابل تعریف باشند.
- گزارش‌ها بتوانند به Domainهای مختلف سیستم وصل شوند.
- گزارش‌های حساس Permission و Scope داشته باشند.
- قالب گزارش‌ها قابل تعریف و کنترل باشند.
- فیلترهای گزارش قابل ذخیره و استفاده باشند.
- فیلترها نتوانند Scope کاربر را دور بزنند.
- خروجی گزارش‌ها به صورت فایل در Files Domain ذخیره شوند.
- Exportهای حساس Private، محدود، منقضی‌شونده و قابل Audit باشند.
- گزارش‌ها قابل زمان‌بندی باشند.
- اجرای گزارش‌ها در execution_logs ثبت شود.
- مشاهده، اجرا و دانلود گزارش‌های حساس در access_logs ثبت شود.
- ویجت‌های داشبورد گزارش قابل تعریف باشند.
- گزارش‌های مالی و حقوقی برای نقش غیرمجاز نمایش داده نشوند.
- عملیات حساس Audit Log داشته باشند.
- تلاش‌های غیرمجاز Security Log داشته باشند.
- جدول‌ها با MySQL/MariaDB و PHP 7.4+ سازگار باشند.
- Codex بتواند از روی این مستندات Migrationهای Reports Domain را بسازد.

---

## پایان فایل
````
