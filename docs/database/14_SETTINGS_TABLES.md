# 14 — Settings Tables

مستند جدول‌های تنظیمات سیستم، تنظیمات امن، گروه‌بندی تنظیمات، تاریخچه تغییرات، سیاست‌های کسب‌وکار، تنظیمات اقساط و داده‌های وابسته به Settings Domain در پروژه **Proma Pay**

---

## فهرست مطالب

- [هدف فایل](#هدف-فایل)
- [تعریف Settings Domain در دیتابیس](#تعریف-settings-domain-در-دیتابیس)
- [اصل مهم](#اصل-مهم)
- [لیست جدول‌های Settings](#لیست-جدولهای-settings)
- [جدول settings](#جدول-settings)
- [جدول setting_groups](#جدول-setting_groups)
- [جدول setting_histories](#جدول-setting_histories)
- [جدول secure_settings](#جدول-secure_settings)
- [جدول system_policies](#جدول-system_policies)
- [جدول installment_policies](#جدول-installment_policies)
- [جدول company_profile_settings](#جدول-company_profile_settings)
- [جدول user_preferences](#جدول-user_preferences)
- [رابطه Settings Domain با سایر Domainها](#رابطه-settings-domain-با-سایر-domainها)
- [قوانین تنظیمات عمومی](#قوانین-تنظیمات-عمومی)
- [قوانین تنظیمات حساس](#قوانین-تنظیمات-حساس)
- [قوانین سیاست‌های کسب‌وکار](#قوانین-سیاستهای-کسبوکار)
- [قوانین تنظیمات اقساط](#قوانین-تنظیمات-اقساط)
- [قوانین Cache تنظیمات](#قوانین-cache-تنظیمات)
- [قوانین Soft Delete](#قوانین-soft-delete)
- [قوانین Index و Performance](#قوانین-index-و-performance)
- [قوانین Validation](#قوانین-validation)
- [قوانین Audit و Security Log](#قوانین-audit-و-security-log)
- [Seedهای پیشنهادی](#seedهای-پیشنهادی)
- [چک‌لیست پیاده‌سازی](#چکلیست-پیادهسازی)
- [Definition of Done](#definition-of-done)

---

## هدف فایل

هدف این فایل این است که ساختار جدول‌های مربوط به تنظیمات سیستم در پروژه **Proma Pay** مشخص شود.

Settings Domain مسئول مدیریت تنظیمات قابل تغییر سیستم است.

این فایل برای Codex مشخص می‌کند که:

- تنظیمات عمومی چگونه ذخیره شوند.
- تنظیمات حساس چگونه امن نگهداری شوند.
- تنظیمات چطور گروه‌بندی شوند.
- تغییرات تنظیمات چگونه تاریخچه داشته باشند.
- سیاست‌های کسب‌وکار چگونه ذخیره شوند.
- سیاست‌های اقساط چگونه مدیریت شوند.
- تنظیمات هویتی شرکت چگونه ذخیره شوند.
- ترجیحات کاربر چگونه نگهداری شوند.
- چه تنظیماتی نیاز به Audit Log یا Security Log دارند.

---

## تعریف Settings Domain در دیتابیس

Settings Domain محل نگهداری تنظیماتی است که رفتار سیستم را کنترل می‌کنند.

نمونه تنظیمات:

- نام سیستم
- لوگو و اطلاعات شرکت
- تنظیمات فاکتور
- تنظیمات قرارداد
- تنظیمات اقساط
- تنظیمات جریمه یا دیرکرد
- تنظیمات Notification
- تنظیمات File Upload
- تنظیمات Backup
- تنظیمات Plugin
- تنظیمات UI
- تنظیمات Security Policy
- تنظیمات API Providerها
- تنظیمات پیامک یا درگاه پرداخت

---

## اصل مهم

اصل مهم در Settings Tables:

> تنظیمات نباید راهی برای دور زدن منطق اصلی، Permission، Scope، CSRF، Audit یا امنیت سیستم باشند.

تنظیمات می‌توانند رفتار سیستم را کنترل کنند، اما نباید باعث شوند:

- عملیات مالی بدون Financial Log انجام شود.
- عملیات حساس بدون Audit انجام شود.
- فایل حساس Public شود.
- کاربر بدون Permission به داده دسترسی بگیرد.
- پلاگین بدون بررسی امنیتی نصب شود.
- پرداخت بدون تأیید معتبر approved شود.
- اطلاعات محرمانه خام در دیتابیس ذخیره شود.

قانون طلایی:

> تنظیمات حساس فقط با Permission ویژه، Audit Log و Validation سخت‌گیرانه قابل تغییر هستند.

---

## لیست جدول‌های Settings

جدول‌های پیشنهادی Settings Domain:

| جدول | کاربرد |
|---|---|
| `settings` | تنظیمات عمومی سیستم |
| `setting_groups` | گروه‌بندی تنظیمات |
| `setting_histories` | تاریخچه تغییر تنظیمات |
| `secure_settings` | تنظیمات حساس و رمزنگاری‌شده |
| `system_policies` | سیاست‌های عمومی سیستم |
| `installment_policies` | سیاست‌های فروش اقساطی |
| `company_profile_settings` | اطلاعات شرکت، فروشگاه و فاکتور |
| `user_preferences` | ترجیحات اختصاصی کاربران |

---

## جدول settings

### هدف جدول

جدول `settings` تنظیمات عمومی و قابل مدیریت سیستم را نگهداری می‌کند.

این جدول برای تنظیماتی استفاده می‌شود که حساسیت بسیار بالا ندارند یا مقدار آن‌ها باید در محیط مدیریتی قابل مشاهده باشد.

---

### نام جدول

```text
settings
```

---

### ستون‌های پیشنهادی

| ستون | نوع | توضیح |
|---|---|---|
| `id` | BIGINT UNSIGNED AUTO_INCREMENT | شناسه داخلی |
| `setting_key` | VARCHAR(150) | کلید یکتا |
| `group_key` | VARCHAR(100) | گروه تنظیم |
| `name` | VARCHAR(191) | نام نمایشی |
| `description` | TEXT NULL | توضیح |
| `value` | LONGTEXT NULL | مقدار تنظیم |
| `value_type` | VARCHAR(50) | نوع مقدار |
| `default_value` | LONGTEXT NULL | مقدار پیش‌فرض |
| `scope` | VARCHAR(50) | محدوده تنظیم |
| `is_public` | TINYINT(1) | قابل نمایش عمومی |
| `is_sensitive` | TINYINT(1) | حساس بودن |
| `is_readonly` | TINYINT(1) | فقط خواندنی |
| `requires_restart` | TINYINT(1) | نیازمند Reload/Restart |
| `validation_rules` | JSON NULL | قوانین اعتبارسنجی |
| `sort_order` | INT UNSIGNED | ترتیب نمایش |
| `status` | VARCHAR(50) | وضعیت |
| `created_at` | DATETIME NULL | زمان ایجاد |
| `updated_at` | DATETIME NULL | زمان بروزرسانی |
| `updated_by` | BIGINT UNSIGNED NULL | ویرایش‌کننده |

---

### value_typeهای پیشنهادی

```text
string
text
integer
decimal
boolean
json
array
date
datetime
url
email
file_id
```

---

### scopeهای پیشنهادی

```text
global
system
company
module
plugin
user
customer
```

---

### statusهای پیشنهادی

```text
active
inactive
deprecated
readonly
```

---

### قوانین

- `setting_key` باید Unique باشد.
- مقدار تنظیم باید با value_type سازگار باشد.
- تنظیم حساس نباید در این جدول به صورت خام ذخیره شود، مگر فقط Mask شده باشد.
- تنظیمات دارای is_readonly نباید از UI تغییر کنند.
- تنظیمات Public نباید اطلاعات محرمانه داشته باشند.
- تغییر تنظیمات حساس باید Audit Log داشته باشد.
- تغییر تنظیمات امنیتی باید Security Log هم داشته باشد.
- تنظیمات Plugin باید namespace مشخص داشته باشند.
- تنظیمات مربوط به پلاگین باید با الگوی `plugin.{plugin_id}.{setting_key}` ذخیره شوند.

---

### Indexهای پیشنهادی

```text
uniq_settings_setting_key
idx_settings_group_key
idx_settings_value_type
idx_settings_scope
idx_settings_is_public
idx_settings_is_sensitive
idx_settings_is_readonly
idx_settings_status
idx_settings_sort_order
idx_settings_updated_by
```

---

### نمونه ساختار SQL

```sql
CREATE TABLE settings (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    setting_key VARCHAR(150) NOT NULL,
    group_key VARCHAR(100) NOT NULL,
    name VARCHAR(191) NOT NULL,
    description TEXT NULL,
    value LONGTEXT NULL,
    value_type VARCHAR(50) NOT NULL DEFAULT 'string',
    default_value LONGTEXT NULL,
    scope VARCHAR(50) NOT NULL DEFAULT 'global',
    is_public TINYINT(1) NOT NULL DEFAULT 0,
    is_sensitive TINYINT(1) NOT NULL DEFAULT 0,
    is_readonly TINYINT(1) NOT NULL DEFAULT 0,
    requires_restart TINYINT(1) NOT NULL DEFAULT 0,
    validation_rules JSON NULL,
    sort_order INT UNSIGNED NOT NULL DEFAULT 0,
    status VARCHAR(50) NOT NULL DEFAULT 'active',
    created_at DATETIME NULL,
    updated_at DATETIME NULL,
    updated_by BIGINT UNSIGNED NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uniq_settings_setting_key (setting_key),
    KEY idx_settings_group_key (group_key),
    KEY idx_settings_value_type (value_type),
    KEY idx_settings_scope (scope),
    KEY idx_settings_is_public (is_public),
    KEY idx_settings_is_sensitive (is_sensitive),
    KEY idx_settings_is_readonly (is_readonly),
    KEY idx_settings_status (status),
    KEY idx_settings_sort_order (sort_order),
    KEY idx_settings_updated_by (updated_by)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

---

## جدول setting_groups

### هدف جدول

جدول `setting_groups` گروه‌های تنظیمات را نگهداری می‌کند.

این جدول برای نمایش منظم تنظیمات در پنل مدیریت استفاده می‌شود.

---

### نام جدول

```text
setting_groups
```

---

### ستون‌های پیشنهادی

| ستون | نوع | توضیح |
|---|---|---|
| `id` | BIGINT UNSIGNED AUTO_INCREMENT | شناسه |
| `group_key` | VARCHAR(100) | کلید گروه |
| `name` | VARCHAR(191) | نام نمایشی |
| `description` | TEXT NULL | توضیح |
| `icon` | VARCHAR(100) NULL | آیکون نمایشی |
| `parent_group_key` | VARCHAR(100) NULL | گروه والد |
| `is_system` | TINYINT(1) | سیستمی بودن |
| `is_active` | TINYINT(1) | فعال بودن |
| `sort_order` | INT UNSIGNED | ترتیب نمایش |
| `created_at` | DATETIME NULL | زمان ایجاد |
| `updated_at` | DATETIME NULL | زمان بروزرسانی |

---

### نمونه group_key

```text
general
company
financial
installments
payments
notifications
files
security
backup
plugins
ui
legal
reports
```

---

### قوانین

- `group_key` باید Unique باشد.
- گروه سیستمی نباید بدون Permission ویژه حذف یا غیرفعال شود.
- گروه غیرفعال نباید در UI عادی نمایش داده شود.
- تنظیمات داخل گروه باید با group_key به آن وصل شوند.
- گروه‌های پلاگین باید namespace مشخص داشته باشند.

---

### Indexهای پیشنهادی

```text
uniq_setting_groups_group_key
idx_setting_groups_parent_group_key
idx_setting_groups_is_system
idx_setting_groups_is_active
idx_setting_groups_sort_order
```

---

## جدول setting_histories

### هدف جدول

جدول `setting_histories` تاریخچه تغییر تنظیمات را ذخیره می‌کند.

این جدول برای Audit، بررسی تغییرات و بازگشت به مقدار قبلی کاربرد دارد.

---

### نام جدول

```text
setting_histories
```

---

### ستون‌های پیشنهادی

| ستون | نوع | توضیح |
|---|---|---|
| `id` | BIGINT UNSIGNED AUTO_INCREMENT | شناسه |
| `setting_key` | VARCHAR(150) | کلید تنظیم |
| `group_key` | VARCHAR(100) NULL | گروه تنظیم |
| `old_value` | LONGTEXT NULL | مقدار قبلی |
| `new_value` | LONGTEXT NULL | مقدار جدید |
| `old_value_masked` | VARCHAR(255) NULL | مقدار قبلی ماسک‌شده |
| `new_value_masked` | VARCHAR(255) NULL | مقدار جدید ماسک‌شده |
| `value_type` | VARCHAR(50) | نوع مقدار |
| `change_type` | VARCHAR(50) | نوع تغییر |
| `reason` | TEXT NULL | دلیل تغییر |
| `changed_by` | BIGINT UNSIGNED NULL | تغییر‌دهنده |
| `changed_at` | DATETIME | زمان تغییر |
| `ip_address` | VARCHAR(45) NULL | IP |
| `user_agent` | TEXT NULL | User Agent |
| `metadata` | JSON NULL | داده تکمیلی |

---

### change_typeهای پیشنهادی

```text
created
updated
reset_to_default
enabled
disabled
deleted
restored
encrypted_updated
```

---

### قوانین

- هر تغییر مهم تنظیمات باید در این جدول ثبت شود.
- تنظیمات حساس نباید مقدار خام در old_value و new_value داشته باشند.
- برای تنظیمات حساس فقط مقدار Mask شده یا Hash شده ذخیره شود.
- این جدول نباید Soft Delete شود.
- تغییر تنظیمات امنیتی باید reason داشته باشد.
- تغییر تنظیمات مالی، اقساط، پرداخت و حقوقی باید Audit Log هم داشته باشد.

---

### Indexهای پیشنهادی

```text
idx_setting_histories_setting_key
idx_setting_histories_group_key
idx_setting_histories_change_type
idx_setting_histories_changed_by
idx_setting_histories_changed_at
```

---

## جدول secure_settings

### هدف جدول

جدول `secure_settings` تنظیمات حساس و محرمانه را نگهداری می‌کند.

این جدول برای داده‌هایی مثل API Key، Token، Secret، Webhook Secret، اطلاعات Provider و رمزهای اتصال استفاده می‌شود.

---

### نام جدول

```text
secure_settings
```

---

### ستون‌های پیشنهادی

| ستون | نوع | توضیح |
|---|---|---|
| `id` | BIGINT UNSIGNED AUTO_INCREMENT | شناسه |
| `secure_key` | VARCHAR(150) | کلید یکتا |
| `group_key` | VARCHAR(100) | گروه |
| `name` | VARCHAR(191) | نام نمایشی |
| `description` | TEXT NULL | توضیح |
| `encrypted_value` | LONGTEXT | مقدار رمزنگاری‌شده |
| `value_masked` | VARCHAR(255) NULL | مقدار ماسک‌شده |
| `encryption_version` | VARCHAR(50) NULL | نسخه رمزنگاری |
| `value_type` | VARCHAR(50) | نوع مقدار |
| `provider_key` | VARCHAR(100) NULL | Provider مرتبط |
| `scope` | VARCHAR(50) | محدوده |
| `is_active` | TINYINT(1) | فعال بودن |
| `last_rotated_at` | DATETIME NULL | آخرین زمان چرخش Secret |
| `expires_at` | DATETIME NULL | زمان انقضا |
| `created_at` | DATETIME NULL | زمان ایجاد |
| `updated_at` | DATETIME NULL | زمان بروزرسانی |
| `updated_by` | BIGINT UNSIGNED NULL | ویرایش‌کننده |

---

### secure_keyهای نمونه

```text
sms.provider.api_key
payment.gateway.secret_key
telegram.bot.token
backup.remote_storage.secret
plugin.sms_provider.api_key
webhook.payment.secret
```

---

### value_typeهای پیشنهادی

```text
api_key
token
secret
password
private_key
webhook_secret
json_secret
```

---

### قوانین

- `secure_key` باید Unique باشد.
- مقدار خام Secret نباید ذخیره شود.
- فقط encrypted_value ذخیره شود.
- مقدار Secret نباید در Log خام ثبت شود.
- مقدار Secret نباید در API خروجی برگردد.
- UI فقط value_masked را نمایش دهد.
- تغییر secure_setting باید Audit و Security Log داشته باشد.
- Secret منقضی‌شده نباید استفاده شود.
- چرخش Secret باید last_rotated_at را بروزرسانی کند.
- تنظیمات پلاگین باید namespace مشخص داشته باشند.

---

### Indexهای پیشنهادی

```text
uniq_secure_settings_secure_key
idx_secure_settings_group_key
idx_secure_settings_provider_key
idx_secure_settings_scope
idx_secure_settings_is_active
idx_secure_settings_last_rotated_at
idx_secure_settings_expires_at
idx_secure_settings_updated_by
```

---

## جدول system_policies

### هدف جدول

جدول `system_policies` سیاست‌های عمومی و رفتاری سیستم را نگهداری می‌کند.

Policyها معمولاً رفتارهای مهم سیستم را کنترل می‌کنند و از تنظیمات ساده حساس‌تر هستند.

---

### نام جدول

```text
system_policies
```

---

### ستون‌های پیشنهادی

| ستون | نوع | توضیح |
|---|---|---|
| `id` | BIGINT UNSIGNED AUTO_INCREMENT | شناسه |
| `policy_key` | VARCHAR(150) | کلید Policy |
| `name` | VARCHAR(191) | نام نمایشی |
| `description` | TEXT NULL | توضیح |
| `policy_type` | VARCHAR(50) | نوع Policy |
| `policy_value` | LONGTEXT NULL | مقدار Policy |
| `value_type` | VARCHAR(50) | نوع مقدار |
| `is_enabled` | TINYINT(1) | فعال بودن |
| `is_system` | TINYINT(1) | سیستمی بودن |
| `requires_approval` | TINYINT(1) | نیاز به تأیید |
| `effective_from` | DATETIME NULL | شروع اثر |
| `effective_until` | DATETIME NULL | پایان اثر |
| `created_by` | BIGINT UNSIGNED NULL | ایجادکننده |
| `updated_by` | BIGINT UNSIGNED NULL | ویرایش‌کننده |
| `created_at` | DATETIME NULL | زمان ایجاد |
| `updated_at` | DATETIME NULL | زمان بروزرسانی |

---

### policy_typeهای پیشنهادی

```text
security
financial
installment
payment
legal
file
backup
plugin
notification
report
system
```

---

### نمونه policy_key

```text
security.max_login_attempts
files.max_upload_size
reports.export_expiration_hours
plugins.upload_enabled
backup.require_before_update
payments.card_to_card_requires_review
legal.referral_requires_snapshot
```

---

### قوانین

- `policy_key` باید Unique باشد.
- Policy سیستمی نباید بدون Permission ویژه تغییر کند.
- Policy مالی، پرداخت، حقوقی و امنیتی باید Audit Log داشته باشد.
- تغییر Policy حساس می‌تواند نیازمند Approval باشد.
- Policy غیرفعال نباید رفتار فعال ایجاد کند.
- Policy نباید Permission را دور بزند.
- Policy باید سمت سرور اعمال شود.

---

### Indexهای پیشنهادی

```text
uniq_system_policies_policy_key
idx_system_policies_policy_type
idx_system_policies_is_enabled
idx_system_policies_is_system
idx_system_policies_requires_approval
idx_system_policies_effective_from
idx_system_policies_effective_until
```

---

## جدول installment_policies

### هدف جدول

جدول `installment_policies` سیاست‌های مربوط به فروش اقساطی را نگهداری می‌کند.

این جدول برای کنترل شرایط اقساط، پیش‌پرداخت، سقف اعتبار، تعداد اقساط، نیاز به ضمانت و شرایط مشتری استفاده می‌شود.

---

### نام جدول

```text
installment_policies
```

---

### ستون‌های پیشنهادی

| ستون | نوع | توضیح |
|---|---|---|
| `id` | BIGINT UNSIGNED AUTO_INCREMENT | شناسه |
| `policy_number` | VARCHAR(50) | شماره رسمی Policy |
| `policy_key` | VARCHAR(150) | کلید Policy |
| `name` | VARCHAR(191) | نام نمایشی |
| `description` | TEXT NULL | توضیح |
| `customer_group` | VARCHAR(50) | گروه مشتری |
| `min_down_payment_percent` | DECIMAL(5,2) NULL | حداقل درصد پیش‌پرداخت |
| `max_credit_amount` | DECIMAL(15,2) NULL | سقف اعتبار |
| `max_installment_months` | INT UNSIGNED NULL | حداکثر ماه اقساط |
| `requires_guarantee` | TINYINT(1) | نیاز به ضمانت |
| `guarantee_policy` | JSON NULL | قوانین ضمانت |
| `profit_policy` | JSON NULL | سیاست سود داخلی |
| `late_penalty_policy` | JSON NULL | سیاست دیرکرد |
| `is_active` | TINYINT(1) | فعال بودن |
| `requires_manual_approval` | TINYINT(1) | نیاز به تأیید دستی |
| `effective_from` | DATETIME NULL | شروع اثر |
| `effective_until` | DATETIME NULL | پایان اثر |
| `created_by` | BIGINT UNSIGNED NULL | ایجادکننده |
| `updated_by` | BIGINT UNSIGNED NULL | ویرایش‌کننده |
| `created_at` | DATETIME NULL | زمان ایجاد |
| `updated_at` | DATETIME NULL | زمان بروزرسانی |

---

### customer_groupهای پیشنهادی

```text
normal
employee
retired
social_security_retired
cultural_worker
business
vip
manual
```

---

### قوانین

- `policy_number` باید Unique باشد.
- `policy_key` باید Unique باشد.
- مبلغ‌ها باید DECIMAL باشند.
- درصد پیش‌پرداخت باید بین 0 تا 100 باشد.
- max_installment_months باید عدد مثبت باشد.
- سیاست سود داخلی نباید برای نقش غیرمجاز نمایش داده شود.
- تغییر سیاست اقساط باید Audit Log داشته باشد.
- تغییر سیاست مالی اقساط می‌تواند Financial Policy Change محسوب شود.
- قراردادهای قبلی نباید بدون Migration یا Adjustment رسمی از تغییر Policy اثر بگیرند.
- Policy جدید فقط روی قراردادهای جدید یا طبق تصمیم رسمی اعمال شود.
- effective_from و effective_until باید کنترل شوند.

---

### Indexهای پیشنهادی

```text
uniq_installment_policies_policy_number
uniq_installment_policies_policy_key
idx_installment_policies_customer_group
idx_installment_policies_is_active
idx_installment_policies_requires_guarantee
idx_installment_policies_requires_manual_approval
idx_installment_policies_effective_from
idx_installment_policies_effective_until
```

---

## جدول company_profile_settings

### هدف جدول

جدول `company_profile_settings` اطلاعات هویتی شرکت، فروشگاه یا مجموعه را نگهداری می‌کند.

این اطلاعات در فاکتور، قرارداد، رسید، گزارش‌ها و پنل مدیریتی استفاده می‌شود.

---

### نام جدول

```text
company_profile_settings
```

---

### ستون‌های پیشنهادی

| ستون | نوع | توضیح |
|---|---|---|
| `id` | BIGINT UNSIGNED AUTO_INCREMENT | شناسه |
| `profile_key` | VARCHAR(100) | کلید پروفایل |
| `company_name` | VARCHAR(191) | نام مجموعه |
| `brand_name` | VARCHAR(191) NULL | نام برند |
| `registration_number` | VARCHAR(100) NULL | شماره ثبت |
| `national_id` | VARCHAR(100) NULL | شناسه ملی |
| `economic_code` | VARCHAR(100) NULL | کد اقتصادی |
| `phone` | VARCHAR(50) NULL | تلفن |
| `mobile` | VARCHAR(50) NULL | موبایل |
| `email` | VARCHAR(191) NULL | ایمیل |
| `website` | VARCHAR(255) NULL | وب‌سایت |
| `province` | VARCHAR(100) NULL | استان |
| `city` | VARCHAR(100) NULL | شهر |
| `address` | TEXT NULL | آدرس |
| `postal_code` | VARCHAR(20) NULL | کد پستی |
| `logo_file_id` | BIGINT UNSIGNED NULL | لوگو |
| `seal_file_id` | BIGINT UNSIGNED NULL | مهر |
| `signature_file_id` | BIGINT UNSIGNED NULL | امضا |
| `invoice_footer_text` | TEXT NULL | متن پایین فاکتور |
| `contract_footer_text` | TEXT NULL | متن پایین قرارداد |
| `is_active` | TINYINT(1) | فعال بودن |
| `created_at` | DATETIME NULL | زمان ایجاد |
| `updated_at` | DATETIME NULL | زمان بروزرسانی |
| `updated_by` | BIGINT UNSIGNED NULL | ویرایش‌کننده |

---

### قوانین

- `profile_key` باید Unique باشد.
- حداقل یک پروفایل فعال باید وجود داشته باشد.
- لوگو، مهر و امضا باید از Files Domain بیایند.
- فایل مهر و امضا حساس هستند و باید Private باشند.
- تغییر اطلاعات رسمی شرکت باید Audit Log داشته باشد.
- تغییر مهر یا امضا باید Audit و Security Log داشته باشد.
- اطلاعات شرکت در فاکتور و قرارداد از این جدول خوانده می‌شود.
- داده‌های حقوقی شرکت باید قبل از چاپ قرارداد Validate شوند.

---

### Indexهای پیشنهادی

```text
uniq_company_profile_settings_profile_key
idx_company_profile_settings_company_name
idx_company_profile_settings_brand_name
idx_company_profile_settings_is_active
idx_company_profile_settings_updated_by
```

---

## جدول user_preferences

### هدف جدول

جدول `user_preferences` ترجیحات اختصاصی کاربران را نگهداری می‌کند.

این جدول برای تنظیمات شخصی کاربر استفاده می‌شود و نباید برای تنظیمات حیاتی سیستم به کار رود.

---

### نام جدول

```text
user_preferences
```

---

### ستون‌های پیشنهادی

| ستون | نوع | توضیح |
|---|---|---|
| `id` | BIGINT UNSIGNED AUTO_INCREMENT | شناسه |
| `user_id` | BIGINT UNSIGNED | کاربر |
| `preference_key` | VARCHAR(150) | کلید ترجیح |
| `value` | LONGTEXT NULL | مقدار |
| `value_type` | VARCHAR(50) | نوع مقدار |
| `group_key` | VARCHAR(100) NULL | گروه |
| `metadata` | JSON NULL | داده تکمیلی |
| `created_at` | DATETIME NULL | زمان ایجاد |
| `updated_at` | DATETIME NULL | زمان بروزرسانی |

---

### نمونه preference_key

```text
ui.sidebar_collapsed
ui.theme_mode
ui.table_page_size
dashboard.default_view
notifications.sound_enabled
calendar.default_view
reports.default_export_format
```

---

### قوانین

- ترکیب user_id و preference_key باید Unique باشد.
- Preference کاربر نباید منطق امنیتی یا مالی را کنترل کند.
- Preference فقط روی تجربه کاربری همان کاربر اثر دارد.
- کاربر نباید Preference کاربر دیگر را تغییر دهد.
- مقدار باید با value_type سازگار باشد.
- داده حساس نباید در user_preferences ذخیره شود.

---

### Indexهای پیشنهادی

```text
uniq_user_preferences_user_key
idx_user_preferences_user_id
idx_user_preferences_preference_key
idx_user_preferences_group_key
idx_user_preferences_value_type
```

---

## رابطه Settings Domain با سایر Domainها

Settings Domain با بخش‌های زیادی از سیستم ارتباط دارد.

| Domain | رابطه |
|---|---|
| Users | تنظیمات امنیت ورود، ترجیحات کاربر |
| Customers | Policyهای مشتری و اعتبارسنجی |
| Contracts | تنظیمات قرارداد، شماره‌گذاری، متن‌های پیش‌فرض |
| Installments | سیاست اقساط، پیش‌پرداخت، دیرکرد |
| Payments | تنظیمات روش‌های پرداخت و بررسی رسید |
| Financial | سیاست‌های مالی، تسویه و Adjustment |
| Legal | سیاست ارجاع حقوقی و Snapshot |
| Files | محدودیت Upload، Storage و Private Files |
| Notifications | Template، کانال‌ها و ارسال |
| Reports | محدودیت Export و انقضای فایل‌ها |
| Backup & Update | نیاز به Backup قبل از Update |
| Plugins | تنظیمات و Secretهای پلاگین |
| Audit | ثبت تغییرات حساس تنظیمات |
| Security | تلاش تغییر غیرمجاز تنظیمات |

---

## قوانین تنظیمات عمومی

قوانین:

- تنظیمات باید setting_key یکتا داشته باشند.
- تنظیمات باید value_type مشخص داشته باشند.
- مقدار تنظیم باید Validate شود.
- تنظیمات read-only نباید از UI تغییر کنند.
- تنظیمات deprecated نباید برای منطق جدید استفاده شوند.
- تغییر تنظیمات مهم باید در history ثبت شود.
- تنظیمات Public نباید داده حساس داشته باشند.
- تنظیمات باید قابل Cache باشند، اما دیتابیس منبع حقیقت است.

---

## قوانین تنظیمات حساس

تنظیمات حساس شامل موارد زیر هستند:

```text
API Key
Token
Secret
Webhook Secret
Gateway Secret
SMS Provider Key
Backup Storage Secret
Plugin Secret
Encryption Key Reference
```

قوانین:

- مقدار خام Secret ذخیره نشود.
- مقدار Secret در Response نمایش داده نشود.
- Secret فقط Mask شده نمایش داده شود.
- Secret باید encrypted ذخیره شود.
- تغییر Secret باید Audit و Security Log داشته باشد.
- Secretهای منقضی‌شده نباید استفاده شوند.
- Secretهای پلاگین باید namespace مشخص داشته باشند.
- تنظیمات بسیار حیاتی می‌توانند فقط در `.env` باشند و در دیتابیس فقط Reference ذخیره شود.

---

## قوانین سیاست‌های کسب‌وکار

قوانین:

- Policy باید policy_key یکتا داشته باشد.
- Policy حساس باید سمت سرور اعمال شود.
- Policy نباید Permission را دور بزند.
- Policy مالی یا حقوقی باید Audit Log داشته باشد.
- Policy دارای effective_from و effective_until باید در زمان اجرا بررسی شود.
- Policy تغییرکرده نباید بی‌صدا روی قراردادهای قبلی اثر بگذارد.
- Policy غیرفعال نباید در محاسبات جدید استفاده شود.
- Policyهای حساس می‌توانند نیازمند Approval باشند.

---

## قوانین تنظیمات اقساط

قوانین:

- سیاست اقساط باید شفاف و نسخه‌پذیر باشد.
- درصد پیش‌پرداخت باید بین 0 تا 100 باشد.
- سقف اعتبار باید DECIMAL باشد.
- تعداد ماه‌های اقساط باید عدد مثبت باشد.
- نیاز به ضمانت باید در Policy مشخص شود.
- گروه مشتری باید مشخص باشد.
- تغییر سیاست اقساط نباید قراردادهای فعال را بدون Adjustment تغییر دهد.
- فرمول داخلی سود یا دیرکرد نباید برای نقش غیرمجاز نمایش داده شود.
- اعمال Policy باید سمت سرور انجام شود.

---

## قوانین Cache تنظیمات

قوانین:

- تنظیمات می‌توانند Cache شوند.
- Cache نباید منبع حقیقت باشد.
- بعد از تغییر تنظیمات، Cache باید پاکسازی یا Refresh شود.
- تنظیمات حساس نباید در Cache ناامن ذخیره شوند.
- Cache سمت Frontend نباید شامل Secret باشد.
- اگر requires_restart فعال باشد، سیستم باید پیام مناسب نمایش دهد.
- تنظیمات سیستم باید در زمان Boot قابل خواندن باشند.

---

## قوانین Soft Delete

جدول‌های زیر می‌توانند Soft Delete یا وضعیت غیرفعال داشته باشند:

- `notification_templates` در فایل مربوطه
- `settings` معمولاً با status کنترل شود.
- `setting_groups` معمولاً با is_active کنترل شود.
- `user_preferences` معمولاً حذف فیزیکی کم‌ریسک است، اما ترجیحاً با پاکسازی کنترل‌شده انجام شود.

قوانین:

- تنظیمات سیستمی مهم نباید فیزیکی حذف شوند.
- حذف یا غیرفعال کردن تنظیم حساس باید Audit Log داشته باشد.
- history تنظیمات حذف نشود.
- secure_settings حذف نشوند، فقط inactive یا rotated شوند.
- Policyهای حساس حذف نشوند؛ deprecated یا inactive شوند.

---

## قوانین Index و Performance

قوانین:

- خواندن تنظیم با setting_key باید سریع باشد.
- خواندن گروه تنظیمات باید سریع باشد.
- secure_settings باید با secure_key سریع پیدا شود.
- history ممکن است بزرگ شود و باید بر اساس changed_at Index داشته باشد.
- Settings باید Cache شوند تا در هر Request Query سنگین ایجاد نشود.
- Queryهای Settings باید فقط تنظیمات لازم را بخوانند.
- Export تنظیمات حساس ممنوع یا بسیار محدود باشد.

Indexهای مهم:

```text
settings.setting_key
settings.group_key
settings.status
secure_settings.secure_key
secure_settings.group_key
system_policies.policy_key
installment_policies.policy_key
setting_histories.setting_key
setting_histories.changed_at
user_preferences.user_id
user_preferences.preference_key
```

---

## قوانین Validation

### settings

- setting_key الزامی و یکتا است.
- group_key الزامی است.
- name الزامی است.
- value_type معتبر باشد.
- value باید با value_type سازگار باشد.
- is_public و is_sensitive نباید همزمان برای Secret خام استفاده شوند.
- status معتبر باشد.

### setting_groups

- group_key الزامی و یکتا است.
- name الزامی است.
- sort_order باید غیرمنفی باشد.

### setting_histories

- setting_key الزامی است.
- change_type معتبر باشد.
- changed_at الزامی است.
- تنظیم حساس نباید مقدار خام در old_value یا new_value ذخیره کند.

### secure_settings

- secure_key الزامی و یکتا است.
- encrypted_value الزامی است.
- value_masked بهتر است وجود داشته باشد.
- value_type معتبر باشد.
- Secret منقضی‌شده نباید active استفاده شود.

### system_policies

- policy_key الزامی و یکتا است.
- policy_type معتبر باشد.
- value_type معتبر باشد.
- effective_until نباید قبل از effective_from باشد.

### installment_policies

- policy_number الزامی و یکتا است.
- policy_key الزامی و یکتا است.
- customer_group معتبر باشد.
- min_down_payment_percent بین 0 تا 100 باشد.
- max_credit_amount غیرمنفی باشد.
- max_installment_months مثبت باشد.
- effective_until نباید قبل از effective_from باشد.

### company_profile_settings

- profile_key الزامی و یکتا است.
- company_name الزامی است.
- email در صورت ثبت معتبر باشد.
- website در صورت ثبت معتبر باشد.
- فایل مهر و امضا باید file_id معتبر داشته باشند.

### user_preferences

- user_id الزامی است.
- preference_key الزامی است.
- مقدار باید با value_type سازگار باشد.
- ترکیب user_id و preference_key یکتا باشد.

---

## قوانین Audit و Security Log

### Audit Log الزامی برای:

- تغییر تنظیمات مالی
- تغییر تنظیمات اقساط
- تغییر تنظیمات پرداخت
- تغییر تنظیمات حقوقی
- تغییر تنظیمات فایل‌های حساس
- تغییر تنظیمات Backup
- تغییر تنظیمات Plugin
- تغییر Template یا کانال Notification حساس
- تغییر اطلاعات رسمی شرکت
- تغییر مهر یا امضای شرکت
- تغییر Policyهای امنیتی
- تغییر تنظیمات Export گزارش‌ها
- Reset کردن تنظیمات حساس به مقدار پیش‌فرض

### Security Log الزامی برای:

- تلاش تغییر تنظیمات بدون Permission
- تلاش مشاهده secure_setting بدون Permission
- تلاش دریافت مقدار خام Secret
- تلاش تغییر Policy امنیتی بدون Permission
- تلاش تغییر تنظیمات Plugin بدون Permission
- CSRF نامعتبر در تغییر تنظیمات
- ارسال مقدار خطرناک در تنظیمات مسیر فایل
- تلاش فعال‌سازی Public برای فایل‌های حساس
- تلاش تغییر تنظیمات Backup یا Restore بدون Permission

---

## Seedهای پیشنهادی

### setting_groups

```text
general
company
financial
installments
payments
notifications
files
security
backup
plugins
ui
legal
reports
```

### value_type

```text
string
text
integer
decimal
boolean
json
array
date
datetime
url
email
file_id
```

### system_policies

```text
security.max_login_attempts
files.max_upload_size
reports.export_expiration_hours
plugins.upload_enabled
backup.require_before_update
payments.card_to_card_requires_review
legal.referral_requires_snapshot
```

### installment customer_group

```text
normal
employee
retired
social_security_retired
cultural_worker
business
vip
manual
```

### user_preferences

```text
ui.sidebar_collapsed
ui.theme_mode
ui.table_page_size
dashboard.default_view
notifications.sound_enabled
calendar.default_view
reports.default_export_format
```

---

## چک‌لیست پیاده‌سازی

قبل از پیاده‌سازی Settings Tables بررسی شود:

- [ ] جدول `settings` ساخته شده است.
- [ ] `setting_key` یکتا است.
- [ ] جدول `setting_groups` ساخته شده است.
- [ ] `group_key` یکتا است.
- [ ] جدول `setting_histories` وجود دارد.
- [ ] تغییرات مهم تنظیمات در history ثبت می‌شوند.
- [ ] جدول `secure_settings` وجود دارد.
- [ ] Secret خام ذخیره نمی‌شود.
- [ ] Secret فقط Mask شده نمایش داده می‌شود.
- [ ] جدول `system_policies` وجود دارد.
- [ ] Policyهای حساس سمت سرور اعمال می‌شوند.
- [ ] جدول `installment_policies` وجود دارد.
- [ ] سیاست‌های اقساط نسخه‌پذیر و قابل کنترل هستند.
- [ ] جدول `company_profile_settings` وجود دارد.
- [ ] فایل مهر و امضا از Files Domain می‌آید.
- [ ] جدول `user_preferences` وجود دارد.
- [ ] تنظیمات Cache می‌شوند اما دیتابیس منبع حقیقت است.
- [ ] تغییرات حساس Audit Log دارند.
- [ ] تلاش‌های غیرمجاز Security Log دارند.

---

## Definition of Done

Settings Tables زمانی کامل هستند که:

- تنظیمات عمومی با key یکتا قابل ذخیره و خواندن باشند.
- تنظیمات گروه‌بندی‌شده و قابل نمایش در پنل باشند.
- تغییرات تنظیمات در history ثبت شوند.
- تنظیمات حساس به صورت رمزنگاری‌شده نگهداری شوند.
- Secret خام در دیتابیس، Log یا Response نمایش داده نشود.
- Policyهای سیستم قابل تعریف و اعمال باشند.
- Policyهای اقساط قابل تعریف، نسخه‌پذیر و قابل کنترل باشند.
- اطلاعات شرکت برای فاکتور و قرارداد قابل ذخیره باشد.
- ترجیحات کاربر جدا از تنظیمات حیاتی سیستم ذخیره شوند.
- تنظیمات حساس فقط با Permission ویژه تغییر کنند.
- تغییرات حساس Audit Log داشته باشند.
- تلاش‌های غیرمجاز Security Log داشته باشند.
- Settings Cache قابل پاکسازی یا Refresh باشد.
- جدول‌ها با MySQL/MariaDB و PHP 7.4+ سازگار باشند.
- Codex بتواند از روی این مستندات Migrationهای Settings Domain را بسازد.

---

## پایان فایل
````
