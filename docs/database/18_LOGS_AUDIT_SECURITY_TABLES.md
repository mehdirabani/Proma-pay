# 18 — Logs, Audit & Security Tables

مستند جدول‌های Audit، Security، Activity، System، Login، Permission، Export، Error و داده‌های وابسته به Logs Domain در پروژه **Proma Pay**

---

## فهرست مطالب

- [هدف فایل](#هدف-فایل)
- [تعریف Logs Domain در دیتابیس](#تعریف-logs-domain-در-دیتابیس)
- [اصل مهم](#اصل-مهم)
- [لیست جدول‌های Logs](#لیست-جدولهای-logs)
- [جدول audit_logs](#جدول-audit_logs)
- [جدول security_logs](#جدول-security_logs)
- [جدول activity_logs](#جدول-activity_logs)
- [جدول system_logs](#جدول-system_logs)
- [جدول login_logs](#جدول-login_logs)
- [جدول permission_check_logs](#جدول-permission_check_logs)
- [جدول data_change_logs](#جدول-data_change_logs)
- [جدول export_logs](#جدول-export_logs)
- [جدول error_logs](#جدول-error_logs)
- [جدول job_logs](#جدول-job_logs)
- [رابطه Logs Domain با سایر Domainها](#رابطه-logs-domain-با-سایر-domainها)
- [قوانین Audit Log](#قوانین-audit-log)
- [قوانین Security Log](#قوانین-security-log)
- [قوانین Activity Log](#قوانین-activity-log)
- [قوانین System Log](#قوانین-system-log)
- [قوانین Login Log](#قوانین-login-log)
- [قوانین Permission Check Log](#قوانین-permission-check-log)
- [قوانین Data Change Log](#قوانین-data-change-log)
- [قوانین Export Log](#قوانین-export-log)
- [قوانین Error و Job Log](#قوانین-error-و-job-log)
- [قوانین نگهداری و پاکسازی Logها](#قوانین-نگهداری-و-پاکسازی-logها)
- [قوانین Index و Performance](#قوانین-index-و-performance)
- [قوانین Validation](#قوانین-validation)
- [قوانین امنیت داده‌های Log](#قوانین-امنیت-دادههای-log)
- [Seedهای پیشنهادی](#seedهای-پیشنهادی)
- [چک‌لیست پیاده‌سازی](#چکلیست-پیادهسازی)
- [Definition of Done](#definition-of-done)

---

## هدف فایل

هدف این فایل این است که ساختار جدول‌های مربوط به لاگ‌ها، Audit و Security در پروژه **Proma Pay** مشخص شود.

Logs Domain برای ردیابی رفتار سیستم، کاربران، عملیات حساس، تلاش‌های غیرمجاز، خطاها، Exportها، ورودها، تغییرات داده و اجرای Jobها استفاده می‌شود.

این فایل برای Codex مشخص می‌کند که:

- عملیات حساس چگونه Audit شوند.
- تلاش‌های خطرناک یا غیرمجاز چگونه Security Log شوند.
- فعالیت‌های عمومی کاربران چگونه ذخیره شوند.
- خطاهای سیستم چگونه ثبت شوند.
- ورود و خروج کاربران چگونه Log شود.
- بررسی Permission چگونه قابل ردیابی باشد.
- تغییر داده‌های مهم چگونه ثبت شود.
- Export فایل‌ها و گزارش‌ها چگونه ثبت شود.
- Jobهای پس‌زمینه چگونه قابل بررسی باشند.
- چه Logهایی نباید حذف شوند.
- چه Logهایی قابل Archive هستند.
- چه داده‌هایی نباید در Log ذخیره شوند.

---

## تعریف Logs Domain در دیتابیس

Logs Domain مسئول نگهداری ردپای اتفاقات مهم سیستم است.

این Domain شامل چند سطح Log است:

| سطح | کاربرد |
|---|---|
| Audit Log | ثبت عملیات حساس و قابل پیگیری |
| Security Log | ثبت تلاش‌های غیرمجاز، مشکوک یا خطرناک |
| Activity Log | ثبت فعالیت‌های عمومی کاربر |
| System Log | ثبت اتفاقات داخلی سیستم |
| Login Log | ثبت ورود، خروج و تلاش‌های ورود |
| Permission Check Log | ثبت بررسی‌های مهم Permission |
| Data Change Log | ثبت تغییرات داده‌های مهم |
| Export Log | ثبت خروجی گرفتن از داده‌ها |
| Error Log | ثبت خطاها و Exceptionها |
| Job Log | ثبت اجرای Jobهای پس‌زمینه |

---

## اصل مهم

اصل مهم در Logs Domain:

> هر اتفاق حساس باید قابل ردیابی باشد، اما Log نباید خودش به منبع نشت اطلاعات محرمانه تبدیل شود.

یعنی:

- Secret خام در Log ذخیره نشود.
- رمز عبور در Log ذخیره نشود.
- Token خام در Log ذخیره نشود.
- API Key خام در Log ذخیره نشود.
- مسیر واقعی فایل Private در Log عمومی ذخیره نشود.
- اطلاعات مالی حساس بدون نیاز در Log ذخیره نشود.
- متن کامل مدارک یا قراردادها در Log ذخیره نشود.
- اطلاعات کارت یا حساب بانکی خام در Log ذخیره نشود.

قانون طلایی:

> Log باید برای ردیابی کافی باشد، نه برای بازسازی اطلاعات محرمانه.

---

## لیست جدول‌های Logs

جدول‌های پیشنهادی Logs Domain:

| جدول | کاربرد |
|---|---|
| `audit_logs` | ثبت عملیات حساس |
| `security_logs` | ثبت رویدادهای امنیتی و تلاش‌های غیرمجاز |
| `activity_logs` | ثبت فعالیت‌های عمومی کاربران |
| `system_logs` | ثبت رویدادهای داخلی سیستم |
| `login_logs` | ثبت ورود، خروج و تلاش ورود |
| `permission_check_logs` | ثبت بررسی Permissionهای حساس |
| `data_change_logs` | ثبت تغییرات داده‌های مهم |
| `export_logs` | ثبت خروجی گرفتن از داده‌ها |
| `error_logs` | ثبت خطاها |
| `job_logs` | ثبت اجرای Jobهای پس‌زمینه |

نکته:

جدول `financial_logs` در فایل `08_FINANCIAL_TABLES.md` تعریف شده است و برای رویدادهای مالی قطعی استفاده می‌شود. این فایل روی Audit، Security و سایر Logهای عمومی سیستم تمرکز دارد.

---

## جدول audit_logs

### هدف جدول

جدول `audit_logs` برای ثبت عملیات حساس و مهم سیستم استفاده می‌شود.

Audit Log باید بتواند مشخص کند:

- چه عملیاتی انجام شده است.
- چه کسی آن را انجام داده است.
- روی چه موجودیتی انجام شده است.
- قبل و بعد از عملیات چه وضعیتی وجود داشته است.
- عملیات از چه IP، دستگاه یا مسیر انجام شده است.
- نتیجه عملیات چه بوده است.

---

### نام جدول

```text
audit_logs
```

---

### ستون‌های پیشنهادی

| ستون | نوع | توضیح |
|---|---|---|
| `id` | BIGINT UNSIGNED AUTO_INCREMENT | شناسه داخلی |
| `audit_number` | VARCHAR(50) | شماره رسمی Audit |
| `event_type` | VARCHAR(100) | نوع رویداد |
| `event_action` | VARCHAR(100) | عملیات انجام‌شده |
| `event_result` | VARCHAR(50) | نتیجه عملیات |
| `severity` | VARCHAR(50) | سطح اهمیت |
| `actor_type` | VARCHAR(50) | نوع انجام‌دهنده |
| `actor_user_id` | BIGINT UNSIGNED NULL | کاربر داخلی |
| `actor_customer_id` | BIGINT UNSIGNED NULL | مشتری |
| `actor_name` | VARCHAR(191) NULL | نام نمایشی انجام‌دهنده |
| `related_type` | VARCHAR(100) | نوع موجودیت مرتبط |
| `related_id` | BIGINT UNSIGNED | شناسه موجودیت مرتبط |
| `customer_id` | BIGINT UNSIGNED NULL | مشتری مرتبط |
| `contract_id` | BIGINT UNSIGNED NULL | قرارداد مرتبط |
| `installment_id` | BIGINT UNSIGNED NULL | قسط مرتبط |
| `payment_id` | BIGINT UNSIGNED NULL | پرداخت مرتبط |
| `legal_case_id` | BIGINT UNSIGNED NULL | پرونده حقوقی |
| `old_values` | JSON NULL | مقدارهای قبلی کنترل‌شده |
| `new_values` | JSON NULL | مقدارهای جدید کنترل‌شده |
| `changed_fields` | JSON NULL | فیلدهای تغییرکرده |
| `description` | TEXT NULL | توضیح |
| `ip_address` | VARCHAR(45) NULL | IP |
| `user_agent` | TEXT NULL | User Agent |
| `request_method` | VARCHAR(20) NULL | متد درخواست |
| `request_path` | VARCHAR(255) NULL | مسیر درخواست |
| `session_id_hash` | VARCHAR(191) NULL | هش Session |
| `metadata` | JSON NULL | داده تکمیلی غیرحساس |
| `created_at` | DATETIME | زمان ثبت |

---

### event_typeهای پیشنهادی

```text
auth
customer
contract
installment
payment
financial
legal
file
report
settings
backup
restore
update
plugin
permission
security
system
```

---

### event_actionهای پیشنهادی

```text
created
updated
deleted
restored
approved
rejected
cancelled
exported
downloaded
uploaded
viewed
changed_status
assigned
unassigned
enabled
disabled
installed
uninstalled
restored_backup
```

---

### event_resultهای پیشنهادی

```text
success
failed
denied
blocked
cancelled
partial
```

---

### severityهای پیشنهادی

```text
low
medium
high
critical
```

---

### actor_typeهای پیشنهادی

```text
user
customer
system
bot
plugin
external
```

---

### قوانین

- `audit_number` باید Unique باشد.
- عملیات حساس باید Audit Log داشته باشد.
- Audit Log نباید Soft Delete شود.
- Audit Log نباید ویرایش شود.
- داده‌های حساس خام نباید در old_values و new_values ذخیره شوند.
- Secret، Password، Token و API Key باید Mask یا حذف شوند.
- عملیات مالی حساس علاوه بر Audit Log باید Financial Log هم داشته باشد.
- عملیات غیرمجاز علاوه بر Audit Log می‌تواند Security Log هم داشته باشد.
- Export و دانلود داده حساس باید Audit Log داشته باشد.
- تغییر تنظیمات حساس باید Audit Log داشته باشد.
- نصب، آپدیت و Restore باید Audit Log داشته باشند.

---

### Indexهای پیشنهادی

```text
uniq_audit_logs_audit_number
idx_audit_logs_event_type
idx_audit_logs_event_action
idx_audit_logs_event_result
idx_audit_logs_severity
idx_audit_logs_actor_user_id
idx_audit_logs_actor_customer_id
idx_audit_logs_related
idx_audit_logs_customer_id
idx_audit_logs_contract_id
idx_audit_logs_installment_id
idx_audit_logs_payment_id
idx_audit_logs_legal_case_id
idx_audit_logs_created_at
```

---

### نمونه ساختار SQL

```sql
CREATE TABLE audit_logs (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    audit_number VARCHAR(50) NOT NULL,
    event_type VARCHAR(100) NOT NULL,
    event_action VARCHAR(100) NOT NULL,
    event_result VARCHAR(50) NOT NULL DEFAULT 'success',
    severity VARCHAR(50) NOT NULL DEFAULT 'medium',
    actor_type VARCHAR(50) NOT NULL DEFAULT 'user',
    actor_user_id BIGINT UNSIGNED NULL,
    actor_customer_id BIGINT UNSIGNED NULL,
    actor_name VARCHAR(191) NULL,
    related_type VARCHAR(100) NOT NULL,
    related_id BIGINT UNSIGNED NOT NULL,
    customer_id BIGINT UNSIGNED NULL,
    contract_id BIGINT UNSIGNED NULL,
    installment_id BIGINT UNSIGNED NULL,
    payment_id BIGINT UNSIGNED NULL,
    legal_case_id BIGINT UNSIGNED NULL,
    old_values JSON NULL,
    new_values JSON NULL,
    changed_fields JSON NULL,
    description TEXT NULL,
    ip_address VARCHAR(45) NULL,
    user_agent TEXT NULL,
    request_method VARCHAR(20) NULL,
    request_path VARCHAR(255) NULL,
    session_id_hash VARCHAR(191) NULL,
    metadata JSON NULL,
    created_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uniq_audit_logs_audit_number (audit_number),
    KEY idx_audit_logs_event_type (event_type),
    KEY idx_audit_logs_event_action (event_action),
    KEY idx_audit_logs_event_result (event_result),
    KEY idx_audit_logs_severity (severity),
    KEY idx_audit_logs_actor_user_id (actor_user_id),
    KEY idx_audit_logs_actor_customer_id (actor_customer_id),
    KEY idx_audit_logs_related (related_type, related_id),
    KEY idx_audit_logs_customer_id (customer_id),
    KEY idx_audit_logs_contract_id (contract_id),
    KEY idx_audit_logs_installment_id (installment_id),
    KEY idx_audit_logs_payment_id (payment_id),
    KEY idx_audit_logs_legal_case_id (legal_case_id),
    KEY idx_audit_logs_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

---

## جدول security_logs

### هدف جدول

جدول `security_logs` برای ثبت تلاش‌های غیرمجاز، مشکوک، خطرناک یا امنیتی استفاده می‌شود.

این جدول باید بتواند رفتارهای مشکوک را برای بررسی بعدی ذخیره کند.

---

### نام جدول

```text
security_logs
```

---

### ستون‌های پیشنهادی

| ستون | نوع | توضیح |
|---|---|---|
| `id` | BIGINT UNSIGNED AUTO_INCREMENT | شناسه داخلی |
| `security_number` | VARCHAR(50) | شماره رسمی رویداد امنیتی |
| `event_type` | VARCHAR(100) | نوع رویداد امنیتی |
| `event_action` | VARCHAR(100) | عملیات |
| `threat_level` | VARCHAR(50) | سطح تهدید |
| `risk_score` | INT UNSIGNED NULL | امتیاز ریسک |
| `status` | VARCHAR(50) | وضعیت بررسی |
| `actor_type` | VARCHAR(50) NULL | نوع عامل |
| `actor_user_id` | BIGINT UNSIGNED NULL | کاربر داخلی |
| `actor_customer_id` | BIGINT UNSIGNED NULL | مشتری |
| `ip_address` | VARCHAR(45) NULL | IP |
| `user_agent` | TEXT NULL | User Agent |
| `request_method` | VARCHAR(20) NULL | متد درخواست |
| `request_path` | VARCHAR(255) NULL | مسیر درخواست |
| `related_type` | VARCHAR(100) NULL | موجودیت مرتبط |
| `related_id` | BIGINT UNSIGNED NULL | شناسه موجودیت |
| `permission_key` | VARCHAR(150) NULL | Permission مرتبط |
| `scope_key` | VARCHAR(150) NULL | Scope مرتبط |
| `reason` | TEXT NULL | دلیل |
| `blocked` | TINYINT(1) | آیا Block شده؟ |
| `resolved_at` | DATETIME NULL | زمان رسیدگی |
| `resolved_by` | BIGINT UNSIGNED NULL | رسیدگی‌کننده |
| `resolution_note` | TEXT NULL | توضیح رسیدگی |
| `metadata` | JSON NULL | داده تکمیلی غیرحساس |
| `created_at` | DATETIME | زمان ثبت |

---

### event_typeهای پیشنهادی

```text
auth_failed
permission_denied
scope_violation
csrf_failed
rate_limit
suspicious_upload
invalid_token
expired_token
direct_file_access
dangerous_input
plugin_security
backup_security
report_security
settings_security
api_security
system_security
```

---

### threat_levelهای پیشنهادی

```text
low
medium
high
critical
```

---

### statusهای پیشنهادی

```text
new
reviewing
resolved
ignored
false_positive
blocked
```

---

### قوانین

- `security_number` باید Unique باشد.
- تلاش غیرمجاز برای مشاهده داده حساس باید Security Log داشته باشد.
- CSRF نامعتبر باید Security Log داشته باشد.
- Permission Denied در عملیات حساس باید Security Log داشته باشد.
- Scope Violation باید Security Log داشته باشد.
- تلاش دانلود فایل Private بدون Permission باید Security Log داشته باشد.
- تلاش Export غیرمجاز باید Security Log داشته باشد.
- تلاش نصب پلاگین خطرناک باید Security Log داشته باشد.
- تلاش Restore یا Update بدون Permission باید Security Log داشته باشد.
- Security Log نباید حذف یا ویرایش شود.
- داده خام حساس نباید در metadata ذخیره شود.

---

### Indexهای پیشنهادی

```text
uniq_security_logs_security_number
idx_security_logs_event_type
idx_security_logs_event_action
idx_security_logs_threat_level
idx_security_logs_status
idx_security_logs_actor_user_id
idx_security_logs_actor_customer_id
idx_security_logs_ip_address
idx_security_logs_related
idx_security_logs_permission_key
idx_security_logs_blocked
idx_security_logs_created_at
```

---

## جدول activity_logs

### هدف جدول

جدول `activity_logs` فعالیت‌های عمومی و معمول کاربران را ثبت می‌کند.

این جدول برای نمایش Timeline، آخرین فعالیت‌ها و بررسی رفتار عمومی سیستم کاربرد دارد.

---

### نام جدول

```text
activity_logs
```

---

### ستون‌های پیشنهادی

| ستون | نوع | توضیح |
|---|---|---|
| `id` | BIGINT UNSIGNED AUTO_INCREMENT | شناسه |
| `activity_number` | VARCHAR(50) | شماره رسمی فعالیت |
| `activity_type` | VARCHAR(100) | نوع فعالیت |
| `activity_action` | VARCHAR(100) | عملیات |
| `actor_type` | VARCHAR(50) | نوع انجام‌دهنده |
| `actor_user_id` | BIGINT UNSIGNED NULL | کاربر |
| `actor_customer_id` | BIGINT UNSIGNED NULL | مشتری |
| `related_type` | VARCHAR(100) NULL | موجودیت مرتبط |
| `related_id` | BIGINT UNSIGNED NULL | شناسه موجودیت |
| `customer_id` | BIGINT UNSIGNED NULL | مشتری مرتبط |
| `contract_id` | BIGINT UNSIGNED NULL | قرارداد مرتبط |
| `title` | VARCHAR(191) NULL | عنوان فعالیت |
| `description` | TEXT NULL | توضیح |
| `visibility` | VARCHAR(50) | سطح نمایش |
| `metadata` | JSON NULL | داده تکمیلی غیرحساس |
| `created_at` | DATETIME | زمان ثبت |

---

### activity_typeهای پیشنهادی

```text
customer
contract
installment
payment
chat
calendar
task
file
report
notification
system
```

---

### visibilityهای پیشنهادی

```text
internal
customer_visible
manager_only
legal_only
accounting_only
system_only
```

---

### قوانین

- `activity_number` باید Unique باشد.
- Activity Log برای عملیات عمومی و Timeline استفاده می‌شود.
- عملیات حساس فقط Activity Log کافی نیست و باید Audit Log هم داشته باشد.
- Activity Log نباید داده محرمانه ذخیره کند.
- Activity customer_visible باید فقط برای همان مشتری قابل نمایش باشد.
- Activity داخلی نباید به مشتری نمایش داده شود.
- Activity Log می‌تواند طبق Retention Policy آرشیو شود.

---

### Indexهای پیشنهادی

```text
uniq_activity_logs_activity_number
idx_activity_logs_activity_type
idx_activity_logs_activity_action
idx_activity_logs_actor_user_id
idx_activity_logs_actor_customer_id
idx_activity_logs_related
idx_activity_logs_customer_id
idx_activity_logs_contract_id
idx_activity_logs_visibility
idx_activity_logs_created_at
```

---

## جدول system_logs

### هدف جدول

جدول `system_logs` اتفاقات داخلی سیستم را ثبت می‌کند.

این جدول برای Debug سطح سیستم، مانیتورینگ و بررسی اتفاقات غیرکاربری استفاده می‌شود.

---

### نام جدول

```text
system_logs
```

---

### ستون‌های پیشنهادی

| ستون | نوع | توضیح |
|---|---|---|
| `id` | BIGINT UNSIGNED AUTO_INCREMENT | شناسه |
| `log_level` | VARCHAR(50) | سطح لاگ |
| `log_type` | VARCHAR(100) | نوع لاگ |
| `message` | TEXT | پیام |
| `context` | JSON NULL | Context غیرحساس |
| `source` | VARCHAR(100) NULL | منبع |
| `related_type` | VARCHAR(100) NULL | موجودیت مرتبط |
| `related_id` | BIGINT UNSIGNED NULL | شناسه موجودیت |
| `request_id` | VARCHAR(100) NULL | شناسه درخواست |
| `created_at` | DATETIME | زمان ثبت |

---

### log_levelهای پیشنهادی

```text
debug
info
notice
warning
error
critical
alert
emergency
```

---

### log_typeهای پیشنهادی

```text
system
database
cache
queue
mail
sms
payment_gateway
file_storage
backup
update
plugin
api
security
performance
```

---

### قوانین

- System Log برای رویدادهای داخلی است.
- خطاهای حساس امنیتی باید Security Log هم داشته باشند.
- خطاهای قابل Audit باید Audit Log هم داشته باشند.
- Secret خام نباید در context ذخیره شود.
- Query خام شامل داده حساس نباید ذخیره شود.
- system_logs می‌تواند Retention و Archive داشته باشد.
- log_level critical باید Notification مدیریتی ایجاد کند.

---

### Indexهای پیشنهادی

```text
idx_system_logs_log_level
idx_system_logs_log_type
idx_system_logs_source
idx_system_logs_related
idx_system_logs_request_id
idx_system_logs_created_at
```

---

## جدول login_logs

### هدف جدول

جدول `login_logs` تلاش‌های ورود، خروج و نشست‌های کاربر را ثبت می‌کند.

این جدول برای امنیت حساب‌ها و تشخیص رفتار مشکوک استفاده می‌شود.

---

### نام جدول

```text
login_logs
```

---

### ستون‌های پیشنهادی

| ستون | نوع | توضیح |
|---|---|---|
| `id` | BIGINT UNSIGNED AUTO_INCREMENT | شناسه |
| `login_number` | VARCHAR(50) | شماره رسمی Login Log |
| `actor_type` | VARCHAR(50) | نوع کاربر |
| `user_id` | BIGINT UNSIGNED NULL | کاربر داخلی |
| `customer_id` | BIGINT UNSIGNED NULL | مشتری |
| `identifier` | VARCHAR(191) NULL | شناسه ورود ماسک‌شده |
| `login_method` | VARCHAR(50) | روش ورود |
| `event_type` | VARCHAR(50) | نوع رویداد |
| `result` | VARCHAR(50) | نتیجه |
| `failure_reason` | VARCHAR(100) NULL | دلیل شکست |
| `ip_address` | VARCHAR(45) NULL | IP |
| `user_agent` | TEXT NULL | User Agent |
| `device_fingerprint` | VARCHAR(191) NULL | اثر انگشت دستگاه |
| `session_id_hash` | VARCHAR(191) NULL | هش Session |
| `logged_at` | DATETIME | زمان رویداد |
| `metadata` | JSON NULL | داده تکمیلی غیرحساس |
| `created_at` | DATETIME NULL | زمان ثبت |

---

### login_methodهای پیشنهادی

```text
password
sms_otp
email_otp
remember_token
api_token
admin_impersonation
system
```

---

### event_typeهای پیشنهادی

```text
login_attempt
login_success
login_failed
logout
session_expired
password_reset_requested
password_changed
otp_requested
otp_verified
otp_failed
```

---

### resultهای پیشنهادی

```text
success
failed
blocked
expired
cancelled
```

---

### قوانین

- `login_number` باید Unique باشد.
- ورود موفق باید Log شود.
- ورود ناموفق باید Log شود.
- تلاش‌های متعدد ناموفق باید Security Log ایجاد کنند.
- identifier باید Mask شود.
- رمز عبور، OTP و Token خام نباید ذخیره شود.
- session_id خام نباید ذخیره شود؛ فقط Hash ذخیره شود.
- تغییر رمز عبور باید Audit Log هم داشته باشد.
- ورود مشکوک می‌تواند Notification امنیتی ایجاد کند.

---

### Indexهای پیشنهادی

```text
uniq_login_logs_login_number
idx_login_logs_actor_type
idx_login_logs_user_id
idx_login_logs_customer_id
idx_login_logs_login_method
idx_login_logs_event_type
idx_login_logs_result
idx_login_logs_ip_address
idx_login_logs_session_id_hash
idx_login_logs_logged_at
```

---

## جدول permission_check_logs

### هدف جدول

جدول `permission_check_logs` بررسی Permissionهای حساس را ثبت می‌کند.

این جدول برای حالتی استفاده می‌شود که لازم است بدانیم چرا یک دسترسی حساس Allowed یا Denied شده است.

---

### نام جدول

```text
permission_check_logs
```

---

### ستون‌های پیشنهادی

| ستون | نوع | توضیح |
|---|---|---|
| `id` | BIGINT UNSIGNED AUTO_INCREMENT | شناسه |
| `check_number` | VARCHAR(50) | شماره رسمی بررسی |
| `permission_key` | VARCHAR(150) | Permission بررسی‌شده |
| `scope_key` | VARCHAR(150) NULL | Scope بررسی‌شده |
| `actor_type` | VARCHAR(50) | نوع عامل |
| `user_id` | BIGINT UNSIGNED NULL | کاربر |
| `customer_id` | BIGINT UNSIGNED NULL | مشتری |
| `related_type` | VARCHAR(100) NULL | موجودیت مرتبط |
| `related_id` | BIGINT UNSIGNED NULL | شناسه موجودیت |
| `result` | VARCHAR(50) | نتیجه |
| `reason` | TEXT NULL | دلیل |
| `ip_address` | VARCHAR(45) NULL | IP |
| `request_path` | VARCHAR(255) NULL | مسیر درخواست |
| `checked_at` | DATETIME | زمان بررسی |
| `metadata` | JSON NULL | داده تکمیلی غیرحساس |

---

### resultهای پیشنهادی

```text
allowed
denied
blocked
not_found
scope_failed
role_failed
```

---

### قوانین

- `check_number` باید Unique باشد.
- لازم نیست همه Permission Checkهای معمولی ذخیره شوند.
- فقط Permissionهای حساس یا Denied مهم ذخیره شوند.
- Denied برای عملیات حساس باید Security Log هم داشته باشد.
- result = allowed برای عملیات بسیار حساس می‌تواند Audit Log هم داشته باشد.
- داده حساس خام نباید در metadata ذخیره شود.
- این جدول می‌تواند Retention کوتاه‌تر از Audit داشته باشد.

---

### Indexهای پیشنهادی

```text
uniq_permission_check_logs_check_number
idx_permission_check_logs_permission_key
idx_permission_check_logs_scope_key
idx_permission_check_logs_user_id
idx_permission_check_logs_customer_id
idx_permission_check_logs_related
idx_permission_check_logs_result
idx_permission_check_logs_checked_at
```

---

## جدول data_change_logs

### هدف جدول

جدول `data_change_logs` تغییرات داده‌های مهم را ثبت می‌کند.

این جدول برای ردیابی تغییرات رکوردها استفاده می‌شود و با Audit Log تفاوت دارد:

- Audit Log روی عملیات کاربر تمرکز دارد.
- Data Change Log روی تغییر داده تمرکز دارد.

---

### نام جدول

```text
data_change_logs
```

---

### ستون‌های پیشنهادی

| ستون | نوع | توضیح |
|---|---|---|
| `id` | BIGINT UNSIGNED AUTO_INCREMENT | شناسه |
| `change_number` | VARCHAR(50) | شماره رسمی تغییر |
| `table_name` | VARCHAR(100) | نام جدول |
| `record_id` | BIGINT UNSIGNED | شناسه رکورد |
| `change_type` | VARCHAR(50) | نوع تغییر |
| `changed_fields` | JSON NULL | فیلدهای تغییرکرده |
| `old_values` | JSON NULL | مقدارهای قبلی کنترل‌شده |
| `new_values` | JSON NULL | مقدارهای جدید کنترل‌شده |
| `actor_user_id` | BIGINT UNSIGNED NULL | کاربر تغییر‌دهنده |
| `actor_type` | VARCHAR(50) | نوع عامل |
| `source` | VARCHAR(50) | منبع تغییر |
| `request_id` | VARCHAR(100) NULL | شناسه درخواست |
| `audit_log_id` | BIGINT UNSIGNED NULL | Audit مرتبط |
| `created_at` | DATETIME | زمان ثبت |

---

### change_typeهای پیشنهادی

```text
insert
update
delete
restore
status_change
soft_delete
system_update
migration_update
```

---

### sourceهای پیشنهادی

```text
user
system
migration
job
plugin
api
import
```

---

### قوانین

- `change_number` باید Unique باشد.
- Data Change Log برای جدول‌های حساس توصیه می‌شود.
- Secret، Password، Token و داده خام حساس نباید در old_values/new_values ذخیره شوند.
- برای تغییرات بسیار حساس باید audit_log_id ثبت شود.
- Data Change Log نباید Soft Delete شود.
- تغییرات مالی قطعی باید Financial Log هم داشته باشند.
- تغییرات حقوقی حساس باید Audit Log داشته باشند.

---

### Indexهای پیشنهادی

```text
uniq_data_change_logs_change_number
idx_data_change_logs_table_record
idx_data_change_logs_table_name
idx_data_change_logs_record_id
idx_data_change_logs_change_type
idx_data_change_logs_actor_user_id
idx_data_change_logs_source
idx_data_change_logs_audit_log_id
idx_data_change_logs_created_at
```

---

## جدول export_logs

### هدف جدول

جدول `export_logs` خروجی گرفتن از داده‌ها را ثبت می‌کند.

هر Export حساس باید در این جدول ثبت شود.

---

### نام جدول

```text
export_logs
```

---

### ستون‌های پیشنهادی

| ستون | نوع | توضیح |
|---|---|---|
| `id` | BIGINT UNSIGNED AUTO_INCREMENT | شناسه |
| `export_log_number` | VARCHAR(50) | شماره رسمی Export Log |
| `export_type` | VARCHAR(100) | نوع خروجی |
| `export_format` | VARCHAR(50) | فرمت خروجی |
| `related_type` | VARCHAR(100) NULL | موجودیت مرتبط |
| `related_id` | BIGINT UNSIGNED NULL | شناسه موجودیت |
| `report_id` | BIGINT UNSIGNED NULL | گزارش |
| `file_id` | BIGINT UNSIGNED NULL | فایل خروجی |
| `requested_by` | BIGINT UNSIGNED NULL | درخواست‌دهنده |
| `row_count` | INT UNSIGNED NULL | تعداد ردیف |
| `is_sensitive` | TINYINT(1) | حساس بودن |
| `scope_summary` | JSON NULL | خلاصه Scope اعمال‌شده |
| `filters_summary` | JSON NULL | خلاصه فیلترها |
| `status` | VARCHAR(50) | وضعیت |
| `started_at` | DATETIME NULL | زمان شروع |
| `completed_at` | DATETIME NULL | زمان تکمیل |
| `failed_at` | DATETIME NULL | زمان شکست |
| `expires_at` | DATETIME NULL | زمان انقضا |
| `error_message` | TEXT NULL | پیام خطا |
| `ip_address` | VARCHAR(45) NULL | IP |
| `user_agent` | TEXT NULL | User Agent |
| `metadata` | JSON NULL | داده تکمیلی غیرحساس |
| `created_at` | DATETIME NULL | زمان ایجاد |

---

### export_typeهای پیشنهادی

```text
customers
contracts
installments
payments
financial
legal
files
reports
audit
security
backup
custom
```

---

### export_formatهای پیشنهادی

```text
pdf
xlsx
csv
json
html
zip
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
blocked
```

---

### قوانین

- `export_log_number` باید Unique باشد.
- Export حساس باید Log شود.
- Export مالی و حقوقی حساس است.
- Export مشتریان و قراردادها حساس است.
- Export باید Scope اعمال‌شده را ثبت کند.
- فایل Export حساس باید در Files Domain و Private Storage باشد.
- Export completed باید file_id داشته باشد، اگر فایل تولید می‌شود.
- Export failed باید error_message داشته باشد.
- Export غیرمجاز باید Security Log داشته باشد.
- دانلود Export حساس باید Audit/File Access Log هم داشته باشد.

---

### Indexهای پیشنهادی

```text
uniq_export_logs_export_log_number
idx_export_logs_export_type
idx_export_logs_export_format
idx_export_logs_related
idx_export_logs_report_id
idx_export_logs_file_id
idx_export_logs_requested_by
idx_export_logs_is_sensitive
idx_export_logs_status
idx_export_logs_started_at
idx_export_logs_completed_at
idx_export_logs_expires_at
```

---

## جدول error_logs

### هدف جدول

جدول `error_logs` خطاهای نرم‌افزاری و Exceptionها را ثبت می‌کند.

این جدول برای Debug و پایش سلامت سیستم استفاده می‌شود.

---

### نام جدول

```text
error_logs
```

---

### ستون‌های پیشنهادی

| ستون | نوع | توضیح |
|---|---|---|
| `id` | BIGINT UNSIGNED AUTO_INCREMENT | شناسه |
| `error_number` | VARCHAR(50) | شماره رسمی خطا |
| `error_level` | VARCHAR(50) | سطح خطا |
| `error_type` | VARCHAR(100) | نوع خطا |
| `message` | TEXT | پیام خطا |
| `exception_class` | VARCHAR(191) NULL | کلاس Exception |
| `file_path_masked` | VARCHAR(255) NULL | مسیر Mask شده |
| `line_number` | INT UNSIGNED NULL | شماره خط |
| `request_id` | VARCHAR(100) NULL | شناسه درخواست |
| `user_id` | BIGINT UNSIGNED NULL | کاربر مرتبط |
| `customer_id` | BIGINT UNSIGNED NULL | مشتری مرتبط |
| `related_type` | VARCHAR(100) NULL | موجودیت مرتبط |
| `related_id` | BIGINT UNSIGNED NULL | شناسه موجودیت |
| `status` | VARCHAR(50) | وضعیت رسیدگی |
| `resolved_at` | DATETIME NULL | زمان رسیدگی |
| `resolved_by` | BIGINT UNSIGNED NULL | رسیدگی‌کننده |
| `resolution_note` | TEXT NULL | توضیح رسیدگی |
| `metadata` | JSON NULL | داده تکمیلی غیرحساس |
| `created_at` | DATETIME | زمان ثبت |

---

### error_levelهای پیشنهادی

```text
debug
info
warning
error
critical
fatal
```

---

### error_typeهای پیشنهادی

```text
php
database
validation
payment_gateway
file_storage
notification
backup
update
plugin
security
api
queue
unknown
```

---

### statusهای پیشنهادی

```text
new
reviewing
resolved
ignored
recurring
```

---

### قوانین

- `error_number` باید Unique باشد.
- خطاهای critical باید Notification مدیریتی ایجاد کنند.
- خطای امنیتی باید Security Log هم داشته باشد.
- خطای عملیات حساس می‌تواند Audit Log هم داشته باشد.
- Stack Trace کامل حاوی داده حساس نباید خام ذخیره شود.
- مسیر واقعی حساس باید Mask شود.
- error_logs می‌تواند Retention و Archive داشته باشد.
- خطاهای تکراری می‌توانند گروه‌بندی شوند.

---

### Indexهای پیشنهادی

```text
uniq_error_logs_error_number
idx_error_logs_error_level
idx_error_logs_error_type
idx_error_logs_exception_class
idx_error_logs_request_id
idx_error_logs_user_id
idx_error_logs_customer_id
idx_error_logs_related
idx_error_logs_status
idx_error_logs_created_at
```

---

## جدول job_logs

### هدف جدول

جدول `job_logs` اجرای Jobهای پس‌زمینه را ثبت می‌کند.

Jobها می‌توانند برای Backup، Notification، Report Export، Reminder، Cleanup، Update و سایر عملیات استفاده شوند.

---

### نام جدول

```text
job_logs
```

---

### ستون‌های پیشنهادی

| ستون | نوع | توضیح |
|---|---|---|
| `id` | BIGINT UNSIGNED AUTO_INCREMENT | شناسه |
| `job_number` | VARCHAR(50) | شماره رسمی Job |
| `job_key` | VARCHAR(150) | کلید Job |
| `job_type` | VARCHAR(100) | نوع Job |
| `queue_name` | VARCHAR(100) NULL | نام صف |
| `status` | VARCHAR(50) | وضعیت |
| `attempt` | INT UNSIGNED | شماره تلاش |
| `max_attempts` | INT UNSIGNED | حداکثر تلاش |
| `related_type` | VARCHAR(100) NULL | موجودیت مرتبط |
| `related_id` | BIGINT UNSIGNED NULL | شناسه موجودیت |
| `started_at` | DATETIME NULL | زمان شروع |
| `completed_at` | DATETIME NULL | زمان تکمیل |
| `failed_at` | DATETIME NULL | زمان شکست |
| `duration_ms` | INT UNSIGNED NULL | مدت اجرا |
| `error_message` | TEXT NULL | پیام خطا |
| `payload_summary` | JSON NULL | خلاصه Payload غیرحساس |
| `result_summary` | JSON NULL | خلاصه نتیجه غیرحساس |
| `created_at` | DATETIME NULL | زمان ایجاد |
| `updated_at` | DATETIME NULL | زمان بروزرسانی |

---

### job_typeهای پیشنهادی

```text
notification_send
report_export
backup_run
backup_cleanup
reminder_send
payment_gateway_verify
file_processing
security_scan
plugin_scan
update_install
database_migration
cleanup
system
```

---

### statusهای پیشنهادی

```text
queued
running
completed
failed
retrying
cancelled
timeout
blocked
```

---

### قوانین

- `job_number` باید Unique باشد.
- Job failed باید error_message داشته باشد.
- attempt نباید از max_attempts بیشتر شود.
- Payload حساس نباید خام ذخیره شود.
- Jobهای حساس باید Audit یا System Log مناسب داشته باشند.
- Jobهای امنیتی failed باید Notification مدیریتی ایجاد کنند.
- job_logs می‌تواند Retention و Archive داشته باشد.

---

### Indexهای پیشنهادی

```text
uniq_job_logs_job_number
idx_job_logs_job_key
idx_job_logs_job_type
idx_job_logs_queue_name
idx_job_logs_status
idx_job_logs_related
idx_job_logs_started_at
idx_job_logs_completed_at
idx_job_logs_failed_at
idx_job_logs_created_at
```

---

## رابطه Logs Domain با سایر Domainها

Logs Domain با همه بخش‌های سیستم ارتباط دارد.

| Domain | رابطه |
|---|---|
| Users | ورود، خروج، Permission، فعالیت‌ها |
| Customers | مشاهده، تغییر و Export اطلاعات مشتری |
| Contracts | ایجاد، تغییر، حذف، چاپ و Export قرارداد |
| Installments | تغییر وضعیت، پرداخت، معوقه، پیگیری |
| Payments | تأیید، رد، اصلاح، Refund |
| Financial | رویدادهای مالی قطعی با Financial Log |
| Legal | ارجاع، تغییر وضعیت، مدارک، هزینه‌ها |
| Files | آپلود، دانلود، حذف فایل حساس |
| Reports | مشاهده، اجرا، Export |
| Settings | تغییر تنظیمات و Secretها |
| Backup & Update | Backup، Restore، Update و Maintenance |
| Plugins | نصب، فعال‌سازی، Route، Hook، Migration |
| Notifications | ارسال اعلان، خطاهای ارسال |
| Calendar | تغییر رویدادها و تسک‌های حساس |
| Security | تمام تلاش‌های غیرمجاز و مشکوک |

---

## قوانین Audit Log

Audit Log الزامی است برای:

- ایجاد یا تغییر قرارداد
- تغییر مبلغ قرارداد
- تغییر مبلغ قسط
- تأیید یا رد پرداخت
- اصلاح مالی
- تسویه
- ارجاع حقوقی
- تغییر وضعیت پرونده حقوقی
- آپلود یا دانلود فایل حساس
- Export گزارش حساس
- تغییر تنظیمات حساس
- تغییر Secret
- Backup دستی
- Restore
- Update
- نصب یا حذف پلاگین
- تغییر Permission یا Role
- Login Impersonation
- حذف داده مهم

---

## قوانین Security Log

Security Log الزامی است برای:

- ورود ناموفق تکراری
- CSRF نامعتبر
- Permission Denied در عملیات حساس
- Scope Violation
- تلاش دانلود فایل Private بدون Permission
- تلاش مشاهده پرونده حقوقی بدون Permission
- تلاش Export بدون Permission
- تلاش تغییر مبلغ از Frontend
- تلاش دستکاری IDها
- تلاش نصب پلاگین خطرناک
- تلاش Restore یا Update بدون Permission
- تلاش مشاهده Secret
- تلاش دریافت Token خام
- آپلود فایل مشکوک یا خطرناک
- Rate Limit
- API Token نامعتبر

---

## قوانین Activity Log

قوانین:

- برای Timeline مشتری و قرارداد استفاده شود.
- برای عملیات عمومی مفید است.
- جایگزین Audit نیست.
- نباید داده محرمانه داشته باشد.
- باید visibility داشته باشد.
- برای customer_visible باید Scope دقیق رعایت شود.
- می‌تواند Retention کوتاه‌تر از Audit داشته باشد.

---

## قوانین System Log

قوانین:

- برای Debug و Monitoring است.
- Secret خام ذخیره نشود.
- خطاهای critical باید Notification ایجاد کنند.
- خطاهای امنیتی باید Security Log هم داشته باشند.
- خطاهای عملیات حساس می‌توانند Audit Log هم داشته باشند.
- system_logs می‌تواند Archive شود.

---

## قوانین Login Log

قوانین:

- تلاش ورود موفق و ناموفق باید Log شود.
- رمز عبور، OTP و Token خام ذخیره نشود.
- Session خام ذخیره نشود؛ Hash ذخیره شود.
- ورود ناموفق زیاد باید Security Log ایجاد کند.
- تغییر رمز عبور باید Audit Log داشته باشد.
- ورود از دستگاه مشکوک می‌تواند Notification ایجاد کند.

---

## قوانین Permission Check Log

قوانین:

- همه Checkهای عادی ذخیره نشوند.
- Checkهای حساس یا Denied ذخیره شوند.
- Denied در عملیات حساس باید Security Log داشته باشد.
- Scope Failure باید Security Log داشته باشد.
- Permission Check Log نباید باعث کندی سیستم شود.
- داده حساس خام در metadata ذخیره نشود.

---

## قوانین Data Change Log

قوانین:

- برای جدول‌های حساس فعال شود.
- تغییرات مالی، حقوقی و تنظیمات حساس باید ردیابی شوند.
- old_values و new_values باید Mask شوند.
- فیلدهای محرمانه ذخیره نشوند.
- Data Change Log جایگزین Audit Log نیست.
- تغییرات مالی قطعی باید Financial Log هم داشته باشند.

---

## قوانین Export Log

قوانین:

- هر Export حساس باید ثبت شود.
- Export مالی، حقوقی، مشتری، قرارداد و پرداخت حساس است.
- Export باید Scope و Filter را ثبت کند.
- فایل Export باید Private باشد.
- Export باید انقضا داشته باشد.
- Export غیرمجاز باید Security Log داشته باشد.
- دانلود Export حساس باید Audit و File Access Log داشته باشد.

---

## قوانین Error و Job Log

قوانین:

- خطاهای critical باید Notification مدیریتی بسازند.
- خطاهای امنیتی باید Security Log هم داشته باشند.
- Job failed باید error_message داشته باشد.
- Jobهای Retry باید attempt را ثبت کنند.
- Payload حساس نباید خام ذخیره شود.
- Jobهای قدیمی می‌توانند Archive شوند.
- خطاهای تکراری باید قابل گروه‌بندی باشند.

---

## قوانین نگهداری و پاکسازی Logها

قوانین پیشنهادی:

| نوع Log | سیاست نگهداری |
|---|---|
| Audit Logs | بلندمدت، حذف نشود مگر Archive رسمی |
| Security Logs | بلندمدت، حذف نشود مگر Archive رسمی |
| Financial Logs | بلندمدت و غیرقابل حذف |
| Activity Logs | قابل Archive |
| System Logs | قابل Archive و Cleanup |
| Login Logs | نگهداری میان‌مدت تا بلندمدت |
| Permission Check Logs | قابل Archive |
| Data Change Logs | بلندمدت برای جداول حساس |
| Export Logs | بلندمدت برای Export حساس |
| Error Logs | قابل Archive |
| Job Logs | قابل Cleanup طبق Policy |

قوانین:

- حذف Log حساس نباید از UI عادی ممکن باشد.
- Archive باید Audit Log داشته باشد.
- پاکسازی Log نباید Audit و Securityهای مهم را حذف کند.
- Logهای مربوط به پرونده حقوقی یا مالی باید طولانی‌تر نگهداری شوند.
- Retention Policy باید در Settings/System Policies تعریف شود.

---

## قوانین Index و Performance

قوانین:

- جدول‌های Log بزرگ می‌شوند.
- همه Queryها باید Pagination داشته باشند.
- فیلتر بر اساس created_at الزامی یا شدیداً توصیه می‌شود.
- Index روی زمان ثبت ضروری است.
- Index روی user_id، customer_id، contract_id و related لازم است.
- نگهداری JSON سنگین باید محدود باشد.
- برای گزارش‌های سنگین Logها باید Archive یا Partitioning در آینده قابل بررسی باشد.
- نوشتن Log نباید تراکنش اصلی را بیش از حد کند کند.
- برای Logهای کم‌اهمیت می‌توان Queue استفاده کرد.
- Audit و Security حساس بهتر است در همان Transaction یا نزدیک‌ترین نقطه مطمئن ثبت شوند.

Indexهای مهم:

```text
audit_logs.created_at
audit_logs.actor_user_id
audit_logs.related_type
audit_logs.related_id
security_logs.created_at
security_logs.threat_level
security_logs.ip_address
activity_logs.customer_id
activity_logs.contract_id
login_logs.user_id
login_logs.ip_address
permission_check_logs.permission_key
data_change_logs.table_name
data_change_logs.record_id
export_logs.requested_by
error_logs.error_level
job_logs.job_type
job_logs.status
```

---

## قوانین Validation

### audit_logs

- audit_number الزامی و یکتا است.
- event_type الزامی است.
- event_action الزامی است.
- event_result معتبر باشد.
- related_type و related_id الزامی هستند.
- created_at الزامی است.
- Secret خام نباید ذخیره شود.

### security_logs

- security_number الزامی و یکتا است.
- event_type الزامی است.
- threat_level معتبر باشد.
- status معتبر باشد.
- created_at الزامی است.
- blocked باید Boolean باشد.

### activity_logs

- activity_number الزامی و یکتا است.
- activity_type الزامی است.
- visibility معتبر باشد.
- created_at الزامی است.

### system_logs

- log_level معتبر باشد.
- log_type معتبر باشد.
- message الزامی است.
- created_at الزامی است.

### login_logs

- login_number الزامی و یکتا است.
- actor_type معتبر باشد.
- login_method معتبر باشد.
- event_type معتبر باشد.
- result معتبر باشد.
- logged_at الزامی است.
- Password، OTP و Token خام ذخیره نشود.

### permission_check_logs

- check_number الزامی و یکتا است.
- permission_key الزامی است.
- result معتبر باشد.
- checked_at الزامی است.

### data_change_logs

- change_number الزامی و یکتا است.
- table_name الزامی است.
- record_id الزامی است.
- change_type معتبر باشد.
- created_at الزامی است.

### export_logs

- export_log_number الزامی و یکتا است.
- export_type معتبر باشد.
- export_format معتبر باشد.
- status معتبر باشد.
- failed status باید error_message داشته باشد.

### error_logs

- error_number الزامی و یکتا است.
- error_level معتبر باشد.
- error_type معتبر باشد.
- message الزامی است.
- created_at الزامی است.

### job_logs

- job_number الزامی و یکتا است.
- job_key الزامی است.
- job_type معتبر باشد.
- status معتبر باشد.
- failed status باید error_message داشته باشد.

---

## قوانین امنیت داده‌های Log

داده‌های زیر نباید خام در Log ذخیره شوند:

```text
password
password_confirmation
otp
token
api_key
secret
private_key
webhook_secret
session_id
remember_token
card_number
bank_account_sensitive_data
raw_file_path_private
full_identity_document_content
full_contract_text_unmasked
```

قوانین:

- داده‌های حساس باید Mask شوند.
- Session فقط Hash شود.
- Token فقط Hash یا Mask شود.
- شماره موبایل در صورت نیاز Mask شود.
- کد ملی در Log عمومی Mask شود.
- مسیر فایل Private Mask شود.
- Logهای حساس فقط برای نقش مجاز نمایش داده شوند.
- Export Logها خودش نیازمند Permission است.

---

## Seedهای پیشنهادی

### audit event_type

```text
auth
customer
contract
installment
payment
financial
legal
file
report
settings
backup
restore
update
plugin
permission
security
system
```

### security event_type

```text
auth_failed
permission_denied
scope_violation
csrf_failed
rate_limit
suspicious_upload
invalid_token
expired_token
direct_file_access
dangerous_input
plugin_security
backup_security
report_security
settings_security
api_security
system_security
```

### severity

```text
low
medium
high
critical
```

### log_level

```text
debug
info
notice
warning
error
critical
alert
emergency
```

### export_type

```text
customers
contracts
installments
payments
financial
legal
files
reports
audit
security
backup
custom
```

### job_type

```text
notification_send
report_export
backup_run
backup_cleanup
reminder_send
payment_gateway_verify
file_processing
security_scan
plugin_scan
update_install
database_migration
cleanup
system
```

---

## چک‌لیست پیاده‌سازی

قبل از پیاده‌سازی Logs Tables بررسی شود:

- [ ] جدول `audit_logs` ساخته شده است.
- [ ] `audit_number` یکتا است.
- [ ] عملیات حساس Audit Log دارند.
- [ ] جدول `security_logs` ساخته شده است.
- [ ] `security_number` یکتا است.
- [ ] تلاش‌های غیرمجاز Security Log دارند.
- [ ] جدول `activity_logs` ساخته شده است.
- [ ] Timeline عمومی قابل ثبت است.
- [ ] جدول `system_logs` ساخته شده است.
- [ ] خطاهای داخلی سیستم قابل ثبت هستند.
- [ ] جدول `login_logs` ساخته شده است.
- [ ] ورود موفق و ناموفق ثبت می‌شود.
- [ ] جدول `permission_check_logs` ساخته شده است.
- [ ] Deniedهای حساس ثبت می‌شوند.
- [ ] جدول `data_change_logs` ساخته شده است.
- [ ] تغییرات داده‌های حساس قابل ردیابی هستند.
- [ ] جدول `export_logs` ساخته شده است.
- [ ] Exportهای حساس ثبت می‌شوند.
- [ ] جدول `error_logs` ساخته شده است.
- [ ] Exceptionهای مهم ذخیره می‌شوند.
- [ ] جدول `job_logs` ساخته شده است.
- [ ] Jobهای پس‌زمینه قابل بررسی هستند.
- [ ] Secret خام در هیچ Log ذخیره نمی‌شود.
- [ ] Logهای حساس Permission دارند.
- [ ] Retention و Archive Policy مشخص است.

---

## Definition of Done

Logs, Audit & Security Tables زمانی کامل هستند که:

- عملیات حساس سیستم Audit Log داشته باشند.
- تلاش‌های غیرمجاز و مشکوک Security Log داشته باشند.
- فعالیت‌های عمومی برای Timeline قابل ثبت باشند.
- ورود، خروج و تلاش‌های ورود قابل ردیابی باشند.
- بررسی Permissionهای حساس قابل ثبت باشد.
- تغییر داده‌های مهم قابل ردیابی باشد.
- Exportهای حساس ثبت و قابل بررسی باشند.
- خطاها و Exceptionهای مهم ذخیره شوند.
- Jobهای پس‌زمینه قابل پایش باشند.
- Logها Secret، Token، Password یا داده محرمانه خام ذخیره نکنند.
- Logهای حساس بدون Permission نمایش داده نشوند.
- Audit و Security Logها حذف یا ویرایش نشوند.
- Retention و Archive برای Logهای حجیم قابل اجرا باشد.
- جدول‌ها با MySQL/MariaDB و PHP 7.4+ سازگار باشند.
- Codex بتواند از روی این مستندات Migrationهای Logs Domain را بسازد.

---

## پایان فایل
````
