# 11 — Notifications Tables

مستند جدول‌های اعلان‌ها، قالب‌ها، گیرندگان، کانال‌های ارسال، وضعیت ارسال، ترجیحات کاربران و داده‌های وابسته به Notification Domain در پروژه **Proma Pay**

---

## فهرست مطالب

- [هدف فایل](#هدف-فایل)
- [تعریف Notification Domain در دیتابیس](#تعریف-notification-domain-در-دیتابیس)
- [اصل مهم](#اصل-مهم)
- [لیست جدول‌های Notifications](#لیست-جدولهای-notifications)
- [جدول notifications](#جدول-notifications)
- [جدول notification_recipients](#جدول-notification_recipients)
- [جدول notification_templates](#جدول-notification_templates)
- [جدول notification_channels](#جدول-notification_channels)
- [جدول notification_delivery_logs](#جدول-notification_delivery_logs)
- [جدول notification_preferences](#جدول-notification_preferences)
- [جدول notification_status_histories](#جدول-notification_status_histories)
- [جدول notification_events](#جدول-notification_events)
- [رابطه Notification Domain با سایر Domainها](#رابطه-notification-domain-با-سایر-domainها)
- [قوانین اعلان داخلی](#قوانین-اعلان-داخلی)
- [قوانین ارسال خارجی](#قوانین-ارسال-خارجی)
- [قوانین قالب اعلان](#قوانین-قالب-اعلان)
- [قوانین اولویت و وضعیت](#قوانین-اولویت-و-وضعیت)
- [قوانین Soft Delete](#قوانین-soft-delete)
- [قوانین Index و Performance](#قوانین-index-و-performance)
- [قوانین Validation](#قوانین-validation)
- [قوانین Audit و Security Log](#قوانین-audit-و-security-log)
- [Seedهای پیشنهادی](#seedهای-پیشنهادی)
- [چک‌لیست پیاده‌سازی](#چکلیست-پیادهسازی)
- [Definition of Done](#definition-of-done)

---

## هدف فایل

هدف این فایل این است که ساختار جدول‌های مربوط به اعلان‌ها در پروژه **Proma Pay** مشخص شود.

Notification Domain مسئول ایجاد، مدیریت و ارسال اعلان‌های داخلی و خارجی سیستم است.

این فایل برای Codex مشخص می‌کند که:

- اعلان‌ها چگونه ذخیره شوند.
- گیرندگان اعلان چگونه مشخص شوند.
- قالب پیام‌ها چگونه مدیریت شوند.
- کانال‌های ارسال چگونه تعریف شوند.
- وضعیت ارسال اعلان چگونه ثبت شود.
- ترجیحات کاربران و مشتریان چگونه ذخیره شود.
- اعلان‌ها چگونه به مشتری، قرارداد، قسط، پرداخت یا پرونده حقوقی وصل شوند.
- ارسال پیام از طریق SMS، Telegram، Email یا کانال‌های دیگر چگونه Log شود.
- چه اعلان‌هایی حساس هستند و نیاز به Audit یا Security Log دارند.

---

## تعریف Notification Domain در دیتابیس

Notification Domain مسئول اطلاع‌رسانی به کاربران داخلی، مدیران، اپراتورها، مشتریان یا سیستم‌های خارجی است.

اعلان می‌تواند برای موارد زیر ایجاد شود:

- سررسید قسط
- معوق شدن قسط
- ثبت پرداخت جدید
- تأیید پرداخت
- رد پرداخت
- ارجاع قرارداد به حقوقی
- نزدیک شدن مهلت حقوقی
- ایجاد بکاپ
- شکست Job
- نصب یا خطای پلاگین
- هشدار امنیتی
- پیام سیستمی
- تغییر وضعیت قرارداد یا پرونده

---

## اصل مهم

اصل مهم در Notification Tables:
****
> اعلان فقط اطلاع‌رسانی است و نباید جایگزین منطق اصلی مالی، حقوقی یا امنیتی شود.

یعنی:

- اعلان نباید پرداخت را تأیید کند.
- اعلان نباید وضعیت قسط را تغییر دهد.
- اعلان نباید پرونده حقوقی را ببندد.
- اعلان نباید Permission را دور بزند.
- اعلان نباید داده خارج از Scope را نمایش دهد.

اعلان باید نتیجه یک Event یا عملیات معتبر باشد، نه منبع حقیقت سیستم.

---

## لیست جدول‌های Notifications

جدول‌های پیشنهادی Notification Domain:

| جدول | کاربرد |
|---|---|
| `notifications` | اطلاعات اصلی اعلان |
| `notification_recipients` | گیرندگان اعلان |
| `notification_templates` | قالب‌های اعلان |
| `notification_channels` | کانال‌های ارسال اعلان |
| `notification_delivery_logs` | لاگ ارسال اعلان |
| `notification_preferences` | ترجیحات دریافت اعلان |
| `notification_status_histories` | تاریخچه تغییر وضعیت اعلان |
| `notification_events` | رویدادهای تولیدکننده اعلان |

---

## جدول notifications

### هدف جدول

جدول `notifications` اطلاعات اصلی هر اعلان را نگهداری می‌کند.

هر اعلان می‌تواند داخلی، خارجی، سیستمی، مالی، حقوقی یا امنیتی باشد.

---

### نام جدول

```text
notifications
```

---

### ستون‌های پیشنهادی

| ستون | نوع | توضیح |
|---|---|---|
| `id` | BIGINT UNSIGNED AUTO_INCREMENT | شناسه داخلی |
| `notification_number` | VARCHAR(50) | شماره رسمی اعلان |
| `notification_type` | VARCHAR(100) | نوع اعلان |
| `title` | VARCHAR(191) | عنوان اعلان |
| `body` | TEXT NULL | متن اعلان |
| `priority` | VARCHAR(50) | اولویت |
| `status` | VARCHAR(50) | وضعیت |
| `visibility` | VARCHAR(50) | سطح نمایش |
| `is_sensitive` | TINYINT(1) | حساس بودن |
| `requires_action` | TINYINT(1) | نیازمند اقدام |
| `action_url` | VARCHAR(255) NULL | لینک اقدام |
| `action_label` | VARCHAR(100) NULL | متن دکمه اقدام |
| `related_type` | VARCHAR(100) NULL | نوع موجودیت مرتبط |
| `related_id` | BIGINT UNSIGNED NULL | شناسه موجودیت مرتبط |
| `customer_id` | BIGINT UNSIGNED NULL | مشتری مرتبط |
| `contract_id` | BIGINT UNSIGNED NULL | قرارداد مرتبط |
| `installment_id` | BIGINT UNSIGNED NULL | قسط مرتبط |
| `payment_id` | BIGINT UNSIGNED NULL | پرداخت مرتبط |
| `legal_case_id` | BIGINT UNSIGNED NULL | پرونده حقوقی مرتبط |
| `template_key` | VARCHAR(100) NULL | قالب استفاده‌شده |
| `scheduled_at` | DATETIME NULL | زمان برنامه‌ریزی ارسال |
| `sent_at` | DATETIME NULL | زمان ارسال |
| `expires_at` | DATETIME NULL | زمان انقضا |
| `created_by` | BIGINT UNSIGNED NULL | ایجادکننده |
| `metadata` | JSON NULL | داده تکمیلی |
| `created_at` | DATETIME NULL | زمان ایجاد |
| `updated_at` | DATETIME NULL | زمان بروزرسانی |
| `deleted_at` | DATETIME NULL | زمان حذف نرم |
| `deleted_by` | BIGINT UNSIGNED NULL | حذف‌کننده |

---

### notification_typeهای پیشنهادی

```text
installment_due
installment_overdue
payment_received
payment_approved
payment_rejected
contract_created
contract_status_changed
legal_referral
legal_deadline
settlement_completed
backup_completed
backup_failed
plugin_installed
plugin_failed
security_alert
system_alert
manual
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

### statusهای پیشنهادی

```text
draft
queued
scheduled
sent
partially_sent
failed
cancelled
expired
deleted
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

- `notification_number` باید Unique باشد.
- اعلان باید title داشته باشد.
- اعلان حساس باید is_sensitive = 1 داشته باشد.
- اعلان حقوقی باید visibility مناسب داشته باشد.
- اعلان مالی نباید بدون Permission برای کاربر غیرمجاز نمایش داده شود.
- اعلان مشتری باید فقط برای همان مشتری قابل مشاهده باشد.
- اعلان داخلی نباید برای مشتری نمایش داده شود.
- اعلان expired نباید به عنوان اعلان فعال نمایش داده شود.
- اعلان حذف‌شده باید Soft Delete شود.
- اعلان نباید منطق اصلی مالی یا حقوقی را اجرا کند.

---

### Indexهای پیشنهادی

```text
uniq_notifications_notification_number
idx_notifications_notification_type
idx_notifications_priority
idx_notifications_status
idx_notifications_visibility
idx_notifications_is_sensitive
idx_notifications_related
idx_notifications_customer_id
idx_notifications_contract_id
idx_notifications_installment_id
idx_notifications_payment_id
idx_notifications_legal_case_id
idx_notifications_scheduled_at
idx_notifications_sent_at
idx_notifications_expires_at
idx_notifications_created_at
idx_notifications_deleted_at
```

---

### نمونه ساختار SQL

```sql
CREATE TABLE notifications (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    notification_number VARCHAR(50) NOT NULL,
    notification_type VARCHAR(100) NOT NULL,
    title VARCHAR(191) NOT NULL,
    body TEXT NULL,
    priority VARCHAR(50) NOT NULL DEFAULT 'normal',
    status VARCHAR(50) NOT NULL DEFAULT 'draft',
    visibility VARCHAR(50) NOT NULL DEFAULT 'internal',
    is_sensitive TINYINT(1) NOT NULL DEFAULT 0,
    requires_action TINYINT(1) NOT NULL DEFAULT 0,
    action_url VARCHAR(255) NULL,
    action_label VARCHAR(100) NULL,
    related_type VARCHAR(100) NULL,
    related_id BIGINT UNSIGNED NULL,
    customer_id BIGINT UNSIGNED NULL,
    contract_id BIGINT UNSIGNED NULL,
    installment_id BIGINT UNSIGNED NULL,
    payment_id BIGINT UNSIGNED NULL,
    legal_case_id BIGINT UNSIGNED NULL,
    template_key VARCHAR(100) NULL,
    scheduled_at DATETIME NULL,
    sent_at DATETIME NULL,
    expires_at DATETIME NULL,
    created_by BIGINT UNSIGNED NULL,
    metadata JSON NULL,
    created_at DATETIME NULL,
    updated_at DATETIME NULL,
    deleted_at DATETIME NULL,
    deleted_by BIGINT UNSIGNED NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uniq_notifications_notification_number (notification_number),
    KEY idx_notifications_notification_type (notification_type),
    KEY idx_notifications_priority (priority),
    KEY idx_notifications_status (status),
    KEY idx_notifications_visibility (visibility),
    KEY idx_notifications_is_sensitive (is_sensitive),
    KEY idx_notifications_related (related_type, related_id),
    KEY idx_notifications_customer_id (customer_id),
    KEY idx_notifications_contract_id (contract_id),
    KEY idx_notifications_installment_id (installment_id),
    KEY idx_notifications_payment_id (payment_id),
    KEY idx_notifications_legal_case_id (legal_case_id),
    KEY idx_notifications_scheduled_at (scheduled_at),
    KEY idx_notifications_sent_at (sent_at),
    KEY idx_notifications_expires_at (expires_at),
    KEY idx_notifications_created_at (created_at),
    KEY idx_notifications_deleted_at (deleted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

---

## جدول notification_recipients

### هدف جدول

جدول `notification_recipients` گیرندگان هر اعلان را نگهداری می‌کند.

یک اعلان می‌تواند برای یک یا چند کاربر یا مشتری ارسال شود.

---

### نام جدول

```text
notification_recipients
```

---

### ستون‌های پیشنهادی

| ستون | نوع | توضیح |
|---|---|---|
| `id` | BIGINT UNSIGNED AUTO_INCREMENT | شناسه |
| `notification_id` | BIGINT UNSIGNED | اعلان |
| `recipient_type` | VARCHAR(50) | نوع گیرنده |
| `user_id` | BIGINT UNSIGNED NULL | کاربر داخلی |
| `customer_id` | BIGINT UNSIGNED NULL | مشتری |
| `recipient_name` | VARCHAR(191) NULL | نام گیرنده |
| `recipient_contact` | VARCHAR(191) NULL | اطلاعات تماس |
| `recipient_contact_masked` | VARCHAR(191) NULL | اطلاعات تماس ماسک‌شده |
| `status` | VARCHAR(50) | وضعیت گیرنده |
| `is_read` | TINYINT(1) | خوانده‌شده |
| `read_at` | DATETIME NULL | زمان خواندن |
| `dismissed_at` | DATETIME NULL | زمان بستن اعلان |
| `action_taken_at` | DATETIME NULL | زمان انجام اقدام |
| `created_at` | DATETIME NULL | زمان ایجاد |
| `updated_at` | DATETIME NULL | زمان بروزرسانی |

---

### recipient_typeهای پیشنهادی

```text
user
customer
role
system
external
```

---

### statusهای پیشنهادی

```text
pending
sent
delivered
read
dismissed
failed
cancelled
expired
```

---

### قوانین

- هر Recipient باید notification_id داشته باشد.
- اگر recipient_type برابر user است، user_id الزامی است.
- اگر recipient_type برابر customer است، customer_id الزامی است.
- اطلاعات تماس باید در صورت نمایش Mask شود.
- گیرنده نباید اعلان خارج از Scope خود را ببیند.
- خواندن اعلان باید read_at را ثبت کند.
- اعلان customer_visible فقط برای customer_id مربوطه قابل نمایش است.
- اعلان internal نباید به customer ارسال شود، مگر Template مجاز باشد.

---

### Indexهای پیشنهادی

```text
idx_notification_recipients_notification_id
idx_notification_recipients_recipient_type
idx_notification_recipients_user_id
idx_notification_recipients_customer_id
idx_notification_recipients_status
idx_notification_recipients_is_read
idx_notification_recipients_read_at
```

---

## جدول notification_templates

### هدف جدول

جدول `notification_templates` قالب‌های قابل استفاده برای اعلان‌ها را نگهداری می‌کند.

قالب‌ها باعث می‌شوند متن اعلان‌ها استاندارد، قابل کنترل و قابل ترجمه باشند.

---

### نام جدول

```text
notification_templates
```

---

### ستون‌های پیشنهادی

| ستون | نوع | توضیح |
|---|---|---|
| `id` | BIGINT UNSIGNED AUTO_INCREMENT | شناسه |
| `template_key` | VARCHAR(100) | کلید یکتا |
| `name` | VARCHAR(191) | نام نمایشی |
| `description` | TEXT NULL | توضیح |
| `channel_type` | VARCHAR(50) | نوع کانال |
| `subject` | VARCHAR(191) NULL | عنوان یا Subject |
| `body` | TEXT | متن قالب |
| `language` | VARCHAR(20) | زبان |
| `variables` | JSON NULL | متغیرهای مجاز |
| `is_active` | TINYINT(1) | فعال بودن |
| `is_sensitive` | TINYINT(1) | حساس بودن |
| `requires_approval` | TINYINT(1) | نیاز به تأیید |
| `created_by` | BIGINT UNSIGNED NULL | ایجادکننده |
| `updated_by` | BIGINT UNSIGNED NULL | ویرایش‌کننده |
| `created_at` | DATETIME NULL | زمان ایجاد |
| `updated_at` | DATETIME NULL | زمان بروزرسانی |
| `deleted_at` | DATETIME NULL | زمان حذف نرم |
| `deleted_by` | BIGINT UNSIGNED NULL | حذف‌کننده |

---

### channel_typeهای پیشنهادی

```text
internal
sms
telegram
whatsapp
email
bot
other
```

---

### نمونه template_key

```text
installment_due_sms
installment_overdue_internal
payment_approved_customer
payment_rejected_customer
legal_warning_sms
backup_failed_admin
security_alert_admin
```

---

### قوانین

- `template_key` باید Unique باشد.
- Template حذف‌شده نباید برای اعلان جدید استفاده شود.
- Template حساس باید requires_approval داشته باشد.
- متغیرهای Template باید whitelist شوند.
- Template نباید اجازه اجرای کد داشته باشد.
- داده حساس نباید بدون Permission در Template قرار بگیرد.
- ویرایش Template حساس باید Audit Log داشته باشد.
- Template حقوقی باید متن کنترل‌شده داشته باشد.

---

### Indexهای پیشنهادی

```text
uniq_notification_templates_template_key
idx_notification_templates_channel_type
idx_notification_templates_language
idx_notification_templates_is_active
idx_notification_templates_is_sensitive
idx_notification_templates_requires_approval
idx_notification_templates_deleted_at
```

---

## جدول notification_channels

### هدف جدول

جدول `notification_channels` کانال‌های ارسال اعلان را نگهداری می‌کند.

این جدول برای فعال یا غیرفعال کردن کانال‌های داخلی و خارجی استفاده می‌شود.

---

### نام جدول

```text
notification_channels
```

---

### ستون‌های پیشنهادی

| ستون | نوع | توضیح |
|---|---|---|
| `id` | BIGINT UNSIGNED AUTO_INCREMENT | شناسه |
| `channel_key` | VARCHAR(100) | کلید کانال |
| `name` | VARCHAR(191) | نام نمایشی |
| `channel_type` | VARCHAR(50) | نوع کانال |
| `provider_key` | VARCHAR(100) NULL | Provider |
| `is_active` | TINYINT(1) | فعال بودن |
| `is_external` | TINYINT(1) | خارجی بودن |
| `supports_templates` | TINYINT(1) | پشتیبانی از Template |
| `supports_attachments` | TINYINT(1) | پشتیبانی از فایل |
| `requires_queue` | TINYINT(1) | نیاز به صف |
| `rate_limit_per_minute` | INT UNSIGNED NULL | محدودیت ارسال |
| `settings` | JSON NULL | تنظیمات غیرحساس |
| `sort_order` | INT UNSIGNED | ترتیب |
| `created_at` | DATETIME NULL | زمان ایجاد |
| `updated_at` | DATETIME NULL | زمان بروزرسانی |

---

### channel_typeهای پیشنهادی

```text
internal
sms
telegram
whatsapp
email
push
bot
other
```

---

### قوانین

- `channel_key` باید Unique باشد.
- کانال غیرفعال نباید اعلان جدید ارسال کند.
- تنظیمات حساس مثل API Key نباید خام در settings ذخیره شود.
- کانال خارجی باید Rate Limit داشته باشد.
- تغییر وضعیت کانال باید Audit Log داشته باشد.
- کانال پلاگینی باید provider_key یا namespace مشخص داشته باشد.
- کانال Core نباید به پلاگین اختیاری وابسته باشد.

---

### Indexهای پیشنهادی

```text
uniq_notification_channels_channel_key
idx_notification_channels_channel_type
idx_notification_channels_provider_key
idx_notification_channels_is_active
idx_notification_channels_is_external
idx_notification_channels_sort_order
```

---

## جدول notification_delivery_logs

### هدف جدول

جدول `notification_delivery_logs` نتیجه ارسال اعلان از طریق کانال‌های مختلف را ثبت می‌کند.

این جدول برای Debug، گزارش ارسال، Retry، بررسی خطا و وضعیت تحویل استفاده می‌شود.

---

### نام جدول

```text
notification_delivery_logs
```

---

### ستون‌های پیشنهادی

| ستون | نوع | توضیح |
|---|---|---|
| `id` | BIGINT UNSIGNED AUTO_INCREMENT | شناسه |
| `delivery_number` | VARCHAR(50) | شماره رسمی ارسال |
| `notification_id` | BIGINT UNSIGNED | اعلان |
| `recipient_id` | BIGINT UNSIGNED NULL | گیرنده |
| `channel_id` | BIGINT UNSIGNED NULL | کانال |
| `channel_key` | VARCHAR(100) | کلید کانال |
| `provider_key` | VARCHAR(100) NULL | Provider |
| `recipient_contact` | VARCHAR(191) NULL | گیرنده |
| `recipient_contact_masked` | VARCHAR(191) NULL | گیرنده ماسک‌شده |
| `status` | VARCHAR(50) | وضعیت ارسال |
| `attempt_count` | INT UNSIGNED | تعداد تلاش |
| `max_attempts` | INT UNSIGNED | حداکثر تلاش |
| `provider_message_id` | VARCHAR(191) NULL | شناسه پیام Provider |
| `provider_status` | VARCHAR(100) NULL | وضعیت Provider |
| `sent_at` | DATETIME NULL | زمان ارسال |
| `delivered_at` | DATETIME NULL | زمان تحویل |
| `failed_at` | DATETIME NULL | زمان شکست |
| `next_retry_at` | DATETIME NULL | زمان تلاش مجدد |
| `error_code` | VARCHAR(100) NULL | کد خطا |
| `error_message` | TEXT NULL | پیام خطا |
| `request_payload` | JSON NULL | داده درخواست |
| `response_payload` | JSON NULL | داده پاسخ |
| `metadata` | JSON NULL | داده تکمیلی |
| `created_at` | DATETIME NULL | زمان ایجاد |
| `updated_at` | DATETIME NULL | زمان بروزرسانی |

---

### statusهای پیشنهادی

```text
queued
sending
sent
delivered
failed
retrying
cancelled
blocked
expired
unknown
```

---

### قوانین

- `delivery_number` باید Unique باشد.
- هر Delivery باید notification_id داشته باشد.
- کانال ارسال باید مشخص باشد.
- اطلاعات تماس باید Mask شود.
- Provider API Key نباید در payload ذخیره شود.
- ارسال failed باید error_message یا error_code داشته باشد.
- Retry باید attempt_count را افزایش دهد.
- attempt_count نباید از max_attempts بیشتر شود.
- ارسال پیام حقوقی یا مالی حساس باید قابل Audit باشد.
- لاگ ارسال نباید حذف شود، مگر طبق Archive Policy.

---

### Indexهای پیشنهادی

```text
uniq_notification_delivery_logs_delivery_number
idx_notification_delivery_logs_notification_id
idx_notification_delivery_logs_recipient_id
idx_notification_delivery_logs_channel_id
idx_notification_delivery_logs_channel_key
idx_notification_delivery_logs_provider_key
idx_notification_delivery_logs_status
idx_notification_delivery_logs_attempt_count
idx_notification_delivery_logs_provider_message_id
idx_notification_delivery_logs_sent_at
idx_notification_delivery_logs_delivered_at
idx_notification_delivery_logs_failed_at
idx_notification_delivery_logs_next_retry_at
```

---

## جدول notification_preferences

### هدف جدول

جدول `notification_preferences` ترجیحات دریافت اعلان توسط کاربران یا مشتریان را ذخیره می‌کند.

مثلاً کاربر می‌تواند بعضی اعلان‌ها را فقط در سیستم ببیند یا مشتری فقط SMSهای ضروری را دریافت کند.

---

### نام جدول

```text
notification_preferences
```

---

### ستون‌های پیشنهادی

| ستون | نوع | توضیح |
|---|---|---|
| `id` | BIGINT UNSIGNED AUTO_INCREMENT | شناسه |
| `owner_type` | VARCHAR(50) | نوع مالک |
| `user_id` | BIGINT UNSIGNED NULL | کاربر داخلی |
| `customer_id` | BIGINT UNSIGNED NULL | مشتری |
| `notification_type` | VARCHAR(100) | نوع اعلان |
| `channel_type` | VARCHAR(50) | نوع کانال |
| `is_enabled` | TINYINT(1) | فعال بودن |
| `quiet_hours_start` | TIME NULL | شروع ساعات سکوت |
| `quiet_hours_end` | TIME NULL | پایان ساعات سکوت |
| `language` | VARCHAR(20) NULL | زبان ترجیحی |
| `metadata` | JSON NULL | داده تکمیلی |
| `created_at` | DATETIME NULL | زمان ایجاد |
| `updated_at` | DATETIME NULL | زمان بروزرسانی |

---

### owner_typeهای پیشنهادی

```text
user
customer
role
system
```

---

### قوانین

- اگر owner_type برابر user است، user_id الزامی است.
- اگر owner_type برابر customer است، customer_id الزامی است.
- اعلان‌های حیاتی امنیتی ممکن است قابل غیرفعال شدن نباشند.
- اعلان‌های حقوقی یا مالی ضروری ممکن است طبق Policy همیشه ارسال شوند.
- Preference نباید باعث حذف لاگ اعلان شود.
- تغییر Preference باید فقط توسط مالک یا کاربر مجاز انجام شود.
- تغییر Preference حساس می‌تواند Audit Log داشته باشد.

---

### Indexهای پیشنهادی

```text
idx_notification_preferences_owner_type
idx_notification_preferences_user_id
idx_notification_preferences_customer_id
idx_notification_preferences_notification_type
idx_notification_preferences_channel_type
idx_notification_preferences_is_enabled
```

---

## جدول notification_status_histories

### هدف جدول

جدول `notification_status_histories` تاریخچه تغییر وضعیت اعلان را نگهداری می‌کند.

این جدول برای بررسی روند ارسال، خطاها و Audit اعلان‌های حساس استفاده می‌شود.

---

### نام جدول

```text
notification_status_histories
```

---

### ستون‌های پیشنهادی

| ستون | نوع | توضیح |
|---|---|---|
| `id` | BIGINT UNSIGNED AUTO_INCREMENT | شناسه |
| `notification_id` | BIGINT UNSIGNED | اعلان |
| `old_status` | VARCHAR(50) NULL | وضعیت قبلی |
| `new_status` | VARCHAR(50) | وضعیت جدید |
| `reason` | TEXT NULL | دلیل تغییر |
| `changed_by` | BIGINT UNSIGNED NULL | تغییر دهنده |
| `changed_at` | DATETIME | زمان تغییر |
| `metadata` | JSON NULL | داده تکمیلی |

---

### قوانین

- هر تغییر وضعیت مهم اعلان باید ثبت شود.
- failed شدن اعلان باید reason یا error داشته باشد.
- cancelled شدن اعلان باید reason داشته باشد.
- این جدول نباید Soft Delete شود.
- مشاهده history اعلان حساس باید Permission داشته باشد.

---

### Indexهای پیشنهادی

```text
idx_notification_status_histories_notification_id
idx_notification_status_histories_new_status
idx_notification_status_histories_changed_by
idx_notification_status_histories_changed_at
```

---

## جدول notification_events

### هدف جدول

جدول `notification_events` رویدادهایی را ذخیره می‌کند که باعث ایجاد اعلان شده‌اند.

این جدول کمک می‌کند سیستم بداند اعلان از کدام Event ساخته شده و آیا Event پردازش شده است یا نه.

---

### نام جدول

```text
notification_events
```

---

### ستون‌های پیشنهادی

| ستون | نوع | توضیح |
|---|---|---|
| `id` | BIGINT UNSIGNED AUTO_INCREMENT | شناسه |
| `event_key` | VARCHAR(150) | کلید Event |
| `event_type` | VARCHAR(100) | نوع Event |
| `related_type` | VARCHAR(100) NULL | نوع موجودیت مرتبط |
| `related_id` | BIGINT UNSIGNED NULL | شناسه موجودیت مرتبط |
| `customer_id` | BIGINT UNSIGNED NULL | مشتری |
| `contract_id` | BIGINT UNSIGNED NULL | قرارداد |
| `installment_id` | BIGINT UNSIGNED NULL | قسط |
| `payment_id` | BIGINT UNSIGNED NULL | پرداخت |
| `legal_case_id` | BIGINT UNSIGNED NULL | پرونده حقوقی |
| `payload` | JSON NULL | داده Event |
| `status` | VARCHAR(50) | وضعیت پردازش |
| `processed_at` | DATETIME NULL | زمان پردازش |
| `failed_at` | DATETIME NULL | زمان شکست |
| `error_message` | TEXT NULL | پیام خطا |
| `created_at` | DATETIME NULL | زمان ایجاد |

---

### event_typeهای پیشنهادی

```text
installment_due
installment_overdue
payment_created
payment_approved
payment_rejected
contract_created
contract_updated
legal_case_created
legal_deadline_near
backup_failed
plugin_failed
security_alert
system_alert
```

---

### statusهای پیشنهادی

```text
pending
processed
failed
ignored
cancelled
```

---

### قوانین

- event_key باید تا حد امکان یکتا یا قابل Deduplicate باشد.
- Event پردازش‌شده نباید دوباره اعلان تکراری بسازد، مگر Policy اجازه دهد.
- payload نباید داده حساس غیرضروری داشته باشد.
- Event شکست‌خورده باید error_message داشته باشد.
- Event می‌تواند Job ایجاد کند.
- Event نباید منطق اصلی مالی یا حقوقی را تغییر دهد.

---

### Indexهای پیشنهادی

```text
idx_notification_events_event_key
idx_notification_events_event_type
idx_notification_events_related
idx_notification_events_customer_id
idx_notification_events_contract_id
idx_notification_events_installment_id
idx_notification_events_payment_id
idx_notification_events_legal_case_id
idx_notification_events_status
idx_notification_events_processed_at
idx_notification_events_created_at
```

---

## رابطه Notification Domain با سایر Domainها

Notification Domain با بخش‌های زیادی از سیستم ارتباط دارد.

| Domain | رابطه |
|---|---|
| Customers | ارسال اعلان به مشتری |
| Users | ارسال اعلان داخلی به کاربران |
| Contracts | اعلان تغییر وضعیت قرارداد |
| Installments | یادآوری سررسید و معوقه |
| Payments | اعلان ثبت، تأیید یا رد پرداخت |
| Legal | هشدار ارجاع حقوقی و مهلت پرونده |
| Chat | بعضی اعلان‌ها می‌توانند پیام Chat ایجاد کنند |
| Calendar | Reminderها و رویدادها اعلان ایجاد می‌کنند |
| Files | پیوست‌های اعلان در صورت نیاز از Files استفاده می‌کنند |
| Plugins | کانال‌های ارسال می‌توانند از پلاگین استفاده کنند |
| Audit | تغییرات حساس اعلان ثبت می‌شوند |
| Security | هشدارهای امنیتی و تلاش‌های غیرمجاز ثبت می‌شوند |

---

## قوانین اعلان داخلی

قوانین:

- اعلان داخلی برای کاربر داخلی نمایش داده می‌شود.
- اعلان داخلی مشتری نباید به Customer Panel ارسال شود.
- اعلان داخلی می‌تواند requires_action داشته باشد.
- اعلان داخلی باید Scope کاربر را رعایت کند.
- اعلان مربوط به قرارداد یا پرونده حقوقی نباید برای کاربر خارج از Scope نمایش داده شود.
- اعلان critical باید در Dashboard برجسته نمایش داده شود.
- اعلان خوانده‌شده باید وضعیت Recipient را تغییر دهد.

---

## قوانین ارسال خارجی

قوانین:

- ارسال خارجی باید از کانال فعال انجام شود.
- کانال خارجی باید Rate Limit داشته باشد.
- ارسال خارجی باید Delivery Log داشته باشد.
- پیام خارجی مالی یا حقوقی باید Template معتبر داشته باشد.
- اطلاعات تماس گیرنده باید Mask شود.
- API Key یا Token Provider نباید در Log ذخیره شود.
- خطای Provider باید ثبت شود.
- Retry باید کنترل‌شده باشد.
- ارسال SMS یا پیام حقوقی حساس باید قابل Audit باشد.

---

## قوانین قالب اعلان

قوانین:

- Template باید template_key یکتا داشته باشد.
- Template باید فقط متغیرهای مجاز را قبول کند.
- Template نباید کد قابل اجرا داشته باشد.
- Template حساس باید با Permission قابل ویرایش باشد.
- تغییر Template حقوقی باید Audit Log داشته باشد.
- حذف Template باید Soft Delete باشد.
- Template غیرفعال نباید برای اعلان جدید استفاده شود.
- Template باید از زبان فارسی RTL پشتیبانی کند.

---

## قوانین اولویت و وضعیت

Transitionهای پیشنهادی:

```text
draft -> queued
queued -> scheduled
scheduled -> sent
queued -> sent
sent -> partially_sent
queued -> failed
scheduled -> failed
draft -> cancelled
queued -> cancelled
sent -> expired
```

قوانین:

- اعلان sent نباید بی‌دلیل به draft برگردد.
- اعلان failed باید error در Delivery Log داشته باشد.
- اعلان cancelled باید reason داشته باشد.
- اعلان expired نباید فعال نمایش داده شود.
- اعلان critical می‌تواند Notification فوری ایجاد کند.
- اعلان sensitive باید Permission و Scope را رعایت کند.

---

## قوانین Soft Delete

جدول‌های زیر باید Soft Delete داشته باشند:

- `notifications`
- `notification_templates`

قوانین:

- اعلان حذف‌شده نباید در لیست عادی نمایش داده شود.
- اعلان حساس حذف‌شده باید Audit Log داشته باشد.
- Template حذف‌شده نباید برای اعلان جدید استفاده شود.
- Delivery Log و Status History نباید Soft Delete شوند.
- Eventهای تولیدکننده اعلان نباید حذف شوند، مگر طبق Archive Policy.

---

## قوانین Index و Performance

قوانین:

- لیست اعلان‌های کاربر باید سریع باشد.
- اعلان‌های خوانده‌نشده باید سریع شمارش شوند.
- اعلان‌های scheduled باید سریع پیدا شوند.
- Delivery Log ممکن است بزرگ شود و باید Index مناسب داشته باشد.
- Eventهای pending باید سریع پردازش شوند.
- اعلان‌های قدیمی می‌توانند Archive شوند.
- Export اعلان‌ها باید محدود و Permission-based باشد.
- Queryها باید Pagination داشته باشند.

Indexهای مهم:

```text
notifications.status
notifications.notification_type
notifications.customer_id
notifications.contract_id
notification_recipients.user_id
notification_recipients.customer_id
notification_recipients.is_read
notification_delivery_logs.notification_id
notification_delivery_logs.status
notification_events.status
notification_events.event_type
notification_events.created_at
```

---

## قوانین Validation

### notifications

- notification_number الزامی و یکتا است.
- notification_type الزامی است.
- title الزامی است.
- priority باید معتبر باشد.
- status باید معتبر باشد.
- visibility باید معتبر باشد.
- اعلان customer_visible باید customer_id یا recipient customer داشته باشد.

### notification_recipients

- notification_id الزامی است.
- recipient_type معتبر باشد.
- recipient_type برابر user باید user_id داشته باشد.
- recipient_type برابر customer باید customer_id داشته باشد.
- status معتبر باشد.

### notification_templates

- template_key الزامی و یکتا است.
- name الزامی است.
- channel_type معتبر باشد.
- body الزامی است.
- variables باید JSON معتبر باشد.

### notification_channels

- channel_key الزامی و یکتا است.
- channel_type معتبر باشد.
- rate_limit_per_minute در صورت ثبت باید مثبت باشد.

### notification_delivery_logs

- delivery_number الزامی و یکتا است.
- notification_id الزامی است.
- channel_key الزامی است.
- status معتبر باشد.
- failed status باید error_message یا error_code داشته باشد.

### notification_preferences

- owner_type معتبر باشد.
- notification_type الزامی است.
- channel_type الزامی است.

### notification_events

- event_type الزامی است.
- status معتبر باشد.
- failed status باید error_message داشته باشد.

---

## قوانین Audit و Security Log

### Audit Log الزامی برای:

- ایجاد اعلان دستی حساس
- حذف اعلان حساس
- ارسال اعلان حقوقی
- ارسال اعلان مالی حساس
- ویرایش Template حساس
- حذف Template
- فعال یا غیرفعال کردن کانال ارسال
- تغییر Preference توسط Admin
- ارسال Retry دستی
- Export اعلان‌ها
- تغییر تنظیمات Provider اعلان

### Security Log الزامی برای:

- تلاش مشاهده اعلان بدون Permission
- تلاش مشاهده اعلان خارج از Scope
- تلاش ارسال اعلان خارجی بدون Permission
- تلاش ارسال اعلان به مشتری دیگر
- تلاش دستکاری notification_id
- تلاش ویرایش Template بدون Permission
- CSRF نامعتبر در عملیات اعلان
- ارسال محتوای خطرناک در اعلان
- تلاش مشاهده Delivery Log حساس بدون Permission

---

## Seedهای پیشنهادی

### notification_type

```text
installment_due
installment_overdue
payment_received
payment_approved
payment_rejected
contract_created
contract_status_changed
legal_referral
legal_deadline
settlement_completed
backup_completed
backup_failed
plugin_installed
plugin_failed
security_alert
system_alert
manual
```

### priority

```text
low
normal
high
critical
```

### status

```text
draft
queued
scheduled
sent
partially_sent
failed
cancelled
expired
deleted
```

### visibility

```text
internal
customer_visible
manager_only
legal_only
accounting_only
system_only
```

### channel_type

```text
internal
sms
telegram
whatsapp
email
push
bot
other
```

### delivery_status

```text
queued
sending
sent
delivered
failed
retrying
cancelled
blocked
expired
unknown
```

---

## چک‌لیست پیاده‌سازی

قبل از پیاده‌سازی Notification Tables بررسی شود:

- [ ] جدول `notifications` ساخته شده است.
- [ ] `notification_number` یکتا است.
- [ ] اعلان‌ها به customer، contract، installment، payment و legal_case قابل اتصال هستند.
- [ ] جدول `notification_recipients` وجود دارد.
- [ ] گیرندگان user و customer قابل ثبت هستند.
- [ ] جدول `notification_templates` وجود دارد.
- [ ] Templateها متغیرهای کنترل‌شده دارند.
- [ ] جدول `notification_channels` وجود دارد.
- [ ] کانال‌های داخلی و خارجی قابل مدیریت هستند.
- [ ] جدول `notification_delivery_logs` وجود دارد.
- [ ] ارسال خارجی لاگ کامل دارد.
- [ ] جدول `notification_preferences` وجود دارد.
- [ ] جدول `notification_status_histories` وجود دارد.
- [ ] جدول `notification_events` وجود دارد.
- [ ] اعلان‌ها Scope و Permission را رعایت می‌کنند.
- [ ] اعلان‌های حساس Audit Log دارند.
- [ ] تلاش‌های غیرمجاز Security Log دارند.
- [ ] Delivery Logها قابل Retry و گزارش‌گیری هستند.

---

## Definition of Done

Notification Tables زمانی کامل هستند که:

- اعلان با شماره رسمی یکتا قابل ثبت باشد.
- اعلان بتواند به مشتری، قرارداد، قسط، پرداخت یا پرونده حقوقی وصل شود.
- اعلان بتواند چند گیرنده داشته باشد.
- گیرندگان بتوانند اعلان را بخوانند، ببندند یا اقدام کنند.
- قالب‌های اعلان قابل تعریف و کنترل باشند.
- کانال‌های ارسال داخلی و خارجی قابل مدیریت باشند.
- ارسال خارجی Delivery Log داشته باشد.
- خطاهای ارسال قابل مشاهده و Retry باشند.
- ترجیحات دریافت اعلان قابل ذخیره باشند.
- تاریخچه وضعیت اعلان قابل ردیابی باشد.
- Eventها بتوانند اعلان تولید کنند.
- اعلان‌های داخلی برای مشتری نمایش داده نشوند.
- اعلان‌های مالی و حقوقی بدون Permission نمایش یا ارسال نشوند.
- عملیات حساس Audit Log داشته باشند.
- تلاش‌های غیرمجاز Security Log داشته باشند.
- جدول‌ها با MySQL/MariaDB و PHP 7.4+ سازگار باشند.
- Codex بتواند از روی این مستندات Migrationهای Notification Domain را بسازد.

---

## پایان فایل
````
