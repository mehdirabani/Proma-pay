# 01 — Database Naming Conventions

مستند استاندارد نام‌گذاری جدول‌ها، ستون‌ها، کلیدها، Indexها، Migrationها و داده‌های دیتابیس در پروژه **Proma Pay**

---

## فهرست مطالب

- [01 — Database Naming Conventions](#01--database-naming-conventions)
  - [فهرست مطالب](#فهرست-مطالب)
  - [هدف فایل](#هدف-فایل)
  - [اصل کلی نام‌گذاری](#اصل-کلی-نامگذاری)
  - [زبان نام‌گذاری](#زبان-نامگذاری)
  - [فرمت نام‌گذاری](#فرمت-نامگذاری)
  - [نام‌گذاری جدول‌ها](#نامگذاری-جدولها)
  - [نام‌گذاری ستون‌ها](#نامگذاری-ستونها)
  - [نام‌گذاری Primary Key](#نامگذاری-primary-key)
  - [نام‌گذاری Foreign Key](#نامگذاری-foreign-key)
  - [نام‌گذاری جدول‌های Pivot](#نامگذاری-جدولهای-pivot)
  - [نام‌گذاری ستون‌های تاریخ](#نامگذاری-ستونهای-تاریخ)
    - [ستون‌های DateTime](#ستونهای-datetime)
    - [ستون‌های Date](#ستونهای-date)
  - [نام‌گذاری ستون‌های مبلغ](#نامگذاری-ستونهای-مبلغ)
  - [نام‌گذاری ستون‌های وضعیت](#نامگذاری-ستونهای-وضعیت)
  - [نام‌گذاری ستون‌های Boolean](#نامگذاری-ستونهای-boolean)
  - [نام‌گذاری ستون‌های شماره رسمی](#نامگذاری-ستونهای-شماره-رسمی)
  - [نام‌گذاری ستون‌های فایل](#نامگذاری-ستونهای-فایل)
  - [نام‌گذاری ستون‌های امنیتی](#نامگذاری-ستونهای-امنیتی)
  - [نام‌گذاری Indexها](#نامگذاری-indexها)
  - [نام‌گذاری Unique Constraintها](#نامگذاری-unique-constraintها)
  - [نام‌گذاری Migrationها](#نامگذاری-migrationها)
  - [نام‌گذاری جدول‌های Log](#نامگذاری-جدولهای-log)
  - [نام‌گذاری جدول‌های پلاگین](#نامگذاری-جدولهای-پلاگین)
  - [نام‌گذاری Permissionها](#نامگذاری-permissionها)
  - [نام‌گذاری Settings](#نامگذاری-settings)
  - [نام‌گذاری ستون‌های Metadata](#نامگذاری-ستونهای-metadata)
  - [نام‌گذاری ستون‌های Snapshot](#نامگذاری-ستونهای-snapshot)
  - [نام‌گذاری ستون‌های توضیح](#نامگذاری-ستونهای-توضیح)
  - [نام‌گذاری برای Scope و Ownership](#نامگذاری-برای-scope-و-ownership)
  - [نمونه‌های صحیح و اشتباه](#نمونههای-صحیح-و-اشتباه)
    - [جدول مشتریان](#جدول-مشتریان)
    - [ستون مبلغ پرداخت](#ستون-مبلغ-پرداخت)
    - [ستون تاریخ پرداخت](#ستون-تاریخ-پرداخت)
    - [ستون کاربر تأییدکننده](#ستون-کاربر-تأییدکننده)
  - [چک‌لیست نام‌گذاری](#چکلیست-نامگذاری)
  - [Definition of Done](#definition-of-done)
  - [پایان فایل](#پایان-فایل)

---

## هدف فایل

هدف این فایل این است که تمام نام‌های دیتابیس پروژه **Proma Pay** یکدست، قابل فهم، قابل توسعه و قابل نگهداری باشند.

این فایل باید برای موارد زیر استفاده شود:

- ساخت جدول جدید
- ساخت ستون جدید
- نوشتن Migration
- تعریف Foreign Key
- تعریف Index
- تعریف جدول‌های پلاگین
- تعریف Permissionهای دیتابیس‌محور
- طراحی گزارش‌ها
- ساخت Queryهای قابل فهم

---

## اصل کلی نام‌گذاری

اصل اصلی:

> نام هر جدول یا ستون باید بدون نیاز به توضیح اضافه، مفهوم خودش را منتقل کند.

نام‌گذاری باید:

- واضح باشد.
- انگلیسی باشد.
- کوتاه ولی قابل فهم باشد.
- یکدست باشد.
- قابل جستجو در کد باشد.
- قابل توسعه باشد.
- با Domain مربوطه هماهنگ باشد.

نام‌گذاری نباید:

- مبهم باشد.
- خیلی کوتاه و رمزگونه باشد.
- ترکیبی از چند سبک باشد.
- شامل فارسی باشد.
- شامل فاصله باشد.
- شامل حروف بزرگ باشد.
- شامل مخفف‌های نامفهوم باشد.

---

## زبان نام‌گذاری

تمام نام‌های دیتابیس باید انگلیسی باشند.

نمونه صحیح:

```text
customers
contracts
installments
payments
legal_cases
```

نمونه اشتباه:

```text
moshtariha
gharardadha
مشتریان
اقساط
pardakht_tbl
```

قانون:

نام‌های فارسی فقط در داده‌های نمایشی، Labelها، Translationها و UI استفاده می‌شوند؛ نه در نام جدول یا ستون.

---

## فرمت نام‌گذاری

فرمت استاندارد:

```text
snake_case
```

نمونه صحیح:

```text
customer_id
contract_number
payment_amount
created_at
is_active
```

نمونه اشتباه:

```text
customerId
CustomerID
contract-number
payment amount
CreatedAt
isActive
```

قانون:

در دیتابیس پروژه Proma Pay از `camelCase`، `PascalCase` و `kebab-case` استفاده نشود.

---

## نام‌گذاری جدول‌ها

قوانین جدول‌ها:

- نام جدول باید جمع باشد.
- نام جدول باید `snake_case` باشد.
- نام جدول باید مفهوم Domain را مشخص کند.
- نام جدول نباید با `tbl_` شروع شود.
- نام جدول نباید خیلی عمومی باشد.
- نام جدول نباید مخفف نامفهوم داشته باشد.

نمونه صحیح:

```text
customers
contracts
contract_items
installments
payments
payment_receipts
legal_cases
chat_threads
chat_messages
calendar_events
notifications
files
settings
plugins
audit_logs
security_logs
```

نمونه اشتباه:

```text
tbl_customers
customer
data
info
items
cst
pays
```

---

## نام‌گذاری ستون‌ها

قوانین ستون‌ها:

- نام ستون باید مفرد و واضح باشد.
- نام ستون باید `snake_case` باشد.
- ستون باید با مفهوم جدول هماهنگ باشد.
- ستون‌های رابطه باید با `_id` تمام شوند.
- ستون‌های تاریخ و زمان باید با `_at` یا `_date` تمام شوند.
- ستون‌های مبلغ باید با `_amount` تمام شوند.
- ستون‌های Boolean باید با `is_` یا `has_` شروع شوند.

نمونه صحیح:

```text
first_name
last_name
phone
national_code
contract_number
total_amount
paid_amount
remaining_amount
status
created_at
updated_at
deleted_at
```

نمونه اشتباه:

```text
fname
lname
mobileNumber
nc
money
date
stat
desc
```

---

## نام‌گذاری Primary Key

قانون اصلی:

تمام جدول‌های اصلی باید ستون Primary Key با نام زیر داشته باشند:

```text
id
```

نوع پیشنهادی:

```sql
BIGINT UNSIGNED NOT NULL AUTO_INCREMENT
```

نمونه:

```sql
id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY
```

قوانین:

- از نام‌هایی مثل `customer_id` به عنوان Primary Key اصلی همان جدول استفاده نشود.
- `customer_id` باید برای Foreign Key استفاده شود.
- شناسه داخلی دیتابیس نباید به عنوان شماره رسمی قرارداد یا پرداخت نمایش داده شود.
- برای شماره‌های رسمی ستون جدا تعریف شود.

---

## نام‌گذاری Foreign Key

قانون اصلی:

Foreign Key باید با نام جدول مفرد + `_id` ساخته شود.

نمونه:

```text
customer_id
contract_id
installment_id
payment_id
legal_case_id
file_id
user_id
role_id
permission_id
```

برای رابطه‌هایی که نقش خاص دارند، نام باید نقش را مشخص کند:

```text
assigned_user_id
created_by
updated_by
deleted_by
approved_by
rejected_by
reviewed_by
settled_by
referred_by
lawyer_id
operator_id
```

قوانین:

- نام Foreign Key باید هدف رابطه را مشخص کند.
- اگر چند رابطه به جدول users وجود دارد، نباید همه را `user_id` گذاشت.
- در رابطه‌های حساس، نقش کاربر باید واضح باشد.

نمونه صحیح:

```text
approved_by
rejected_by
assigned_operator_id
assigned_lawyer_id
```

نمونه اشتباه:

```text
user1
user2
uid
admin
person
```

---

## نام‌گذاری جدول‌های Pivot

جدول‌های Pivot برای رابطه چندبه‌چند استفاده می‌شوند.

قانون نام‌گذاری:

```text
entity_a_entity_b
```

نام‌ها باید به ترتیب منطقی و قابل فهم باشند.

نمونه صحیح:

```text
role_permissions
user_roles
contract_guarantors
legal_case_installments
chat_thread_participants
notification_user_reads
```

قوانین:

- جدول Pivot باید نام دو موجودیت را واضح نشان دهد.
- اگر رابطه مفهوم مستقل دارد، می‌تواند نام Domain-specific داشته باشد.
- جدول Pivot اگر داده اضافی دارد، باید ستون‌های استاندارد مثل `created_at` داشته باشد.

نمونه اشتباه:

```text
role_permission_map
users_roles_tbl
rel1
pivot_data
```

---

## نام‌گذاری ستون‌های تاریخ

دو نوع ستون تاریخ وجود دارد:

### ستون‌های DateTime

برای زمان دقیق رویدادها:

```text
created_at
updated_at
deleted_at
paid_at
approved_at
rejected_at
reviewed_at
settled_at
referred_at
completed_at
failed_at
started_at
ended_at
```

### ستون‌های Date

برای تاریخ بدون ساعت:

```text
due_date
contract_date
birth_date
promise_date
reminder_date
```

قوانین:

- `_at` برای زمان دقیق استفاده شود.
- `_date` برای تاریخ بدون ساعت استفاده شود.
- تاریخ شمسی به عنوان منبع اصلی ذخیره نشود.
- نمایش شمسی فقط در UI انجام شود.
- همه زمان‌ها در دیتابیس به شکل میلادی ذخیره شوند.

---

## نام‌گذاری ستون‌های مبلغ

ستون‌های مبلغ باید با `_amount` تمام شوند.

نمونه صحیح:

```text
total_amount
base_amount
discount_amount
paid_amount
remaining_amount
installment_amount
penalty_amount
settlement_amount
debt_amount
debt_amount_at_referral
```

قوانین:

- برای مبلغ از `DECIMAL` استفاده شود.
- استفاده از `FLOAT` و `DOUBLE` ممنوع است.
- واحد پول در Settings سیستم مشخص می‌شود.
- نام ستون باید مشخص کند مبلغ مربوط به چیست.
- ستون `amount` به تنهایی فقط زمانی مجاز است که جدول فقط یک مفهوم مبلغ داشته باشد.

نمونه اشتباه:

```text
money
price
cost
num
value
```

---

## نام‌گذاری ستون‌های وضعیت

ستون وضعیت اصلی هر جدول باید معمولاً این نام را داشته باشد:

```text
status
```

اگر جدول چند وضعیت متفاوت دارد، نام دقیق‌تر استفاده شود:

```text
payment_status
review_status
legal_status
sync_status
backup_status
export_status
```

نمونه وضعیت قرارداد:

```text
draft
active
completed
cancelled
legal
```

نمونه وضعیت پرداخت:

```text
initiated
waiting
pending_review
approved
failed
rejected
cancelled
expired
```

قوانین:

- مقدارهای status باید در Domain مربوطه مستند شوند.
- مقدار status نباید آزاد و بی‌قاعده باشد.
- تغییر status حساس باید Audit Log داشته باشد.
- تغییر status مالی باید Financial Log داشته باشد.

---

## نام‌گذاری ستون‌های Boolean

ستون‌های Boolean باید با یکی از Prefixهای زیر شروع شوند:

```text
is_
has_
can_
should_
requires_
```

نمونه صحیح:

```text
is_active
is_verified
is_default
is_required
has_overdue
has_guarantee
can_login
should_notify
requires_backup
```

نمونه اشتباه:

```text
active
verified
default
required
notify
backup
```

قانون:

نام Boolean باید در خواندن جمله معنی داشته باشد.

مثال:

```text
is_active = true
has_guarantee = false
requires_backup = true
```

---

## نام‌گذاری ستون‌های شماره رسمی

برای موجودیت‌هایی که شماره رسمی یا قابل نمایش دارند، از ستون جدا استفاده شود.

الگو:

```text
{entity}_number
```

نمونه:

```text
contract_number
payment_number
receipt_number
legal_case_number
backup_number
export_number
invoice_number
```

قوانین:

- شماره رسمی باید برای نمایش استفاده شود.
- `id` نباید به عنوان شماره رسمی استفاده شود.
- شماره رسمی باید Unique باشد.
- تولید شماره رسمی باید در Service مرکزی انجام شود.
- فرمت شماره رسمی باید در Settings یا Domain مربوطه مشخص باشد.

---

## نام‌گذاری ستون‌های فایل

برای جدول `files` و جدول‌های مرتبط با فایل، نام‌گذاری باید واضح باشد.

نمونه ستون‌ها:

```text
original_name
stored_name
storage_disk
storage_path
mime_type
file_size
file_hash
file_extension
visibility
related_type
related_id
uploaded_by
uploaded_at
deleted_at
```

قوانین:

- مسیر واقعی فایل حساس نباید در UI نمایش داده شود.
- فایل‌های حساس باید Private باشند.
- نام فایل آپلودشده نباید منبع اعتماد باشد.
- فایل واقعی نباید داخل دیتابیس ذخیره شود.
- فقط Metadata و مسیر داخلی ذخیره شود.

---

## نام‌گذاری ستون‌های امنیتی

برای داده‌های امنیتی، نام ستون باید دقیق و غیرمبهم باشد.

نمونه:

```text
password_hash
remember_token
two_factor_secret
last_login_at
last_login_ip
failed_login_count
locked_until
password_changed_at
api_token_hash
```

قوانین:

- ستون رمز عبور نباید `password` باشد.
- رمز عبور باید hash شده ذخیره شود.
- Token خام نباید ذخیره شود؛ Hash ذخیره شود.
- Secretها باید encrypted یا محافظت‌شده باشند.
- مقدارهای امنیتی نباید در Log خام ذخیره شوند.

---

## نام‌گذاری Indexها

الگوی پیشنهادی:

```text
idx_{table}_{column}
```

برای Index ترکیبی:

```text
idx_{table}_{column1}_{column2}
```

نمونه:

```text
idx_customers_phone
idx_contracts_customer_id
idx_installments_contract_id_due_date
idx_payments_contract_id_status
idx_legal_cases_status_created_at
```

قوانین:

- نام Index باید مشخص کند روی چه جدول و ستون‌هایی است.
- Indexهای Foreign Key باید قابل تشخیص باشند.
- Indexهای گزارش‌گیری باید در فایل Performance مستند شوند.
- از نام‌های خودکار نامفهوم تا حد امکان پرهیز شود.

---

## نام‌گذاری Unique Constraintها

الگوی پیشنهادی:

```text
uniq_{table}_{column}
```

برای Unique ترکیبی:

```text
uniq_{table}_{column1}_{column2}
```

نمونه:

```text
uniq_users_email
uniq_customers_phone
uniq_customers_national_code
uniq_contracts_contract_number
uniq_payments_payment_number
uniq_plugins_plugin_id
```

قوانین:

- Unique باید فقط جایی استفاده شود که واقعاً یکتایی تجاری لازم است.
- Unique روی داده حساس باید با دقت طراحی شود.
- Unique ترکیبی باید دلیل مشخص داشته باشد.

---

## نام‌گذاری Migrationها

الگوی Migration:

```text
YYYY_MM_DD_HHMMSS_action_table_name
```

نمونه:

```text
2026_01_01_000001_create_customers_table
2026_01_01_000002_create_contracts_table
2026_01_01_000003_create_installments_table
2026_01_01_000004_add_status_to_payments_table
2026_01_01_000005_create_legal_cases_table
```

Actionهای رایج:

```text
create
add
update
rename
drop
modify
```

قوانین:

- نام Migration باید دقیق باشد.
- Migration مخرب باید واضح مشخص باشد.
- Migration پلاگین باید از Migration Core قابل تشخیص باشد.
- Migration نباید نام مبهم داشته باشد.

نمونه اشتباه:

```text
migration1
update_db
new_table
fix
test
```

---

## نام‌گذاری جدول‌های Log

جدول‌های Log باید نوع Log را مشخص کنند.

نمونه:

```text
audit_logs
security_logs
financial_logs
system_logs
activity_logs
payment_gateway_logs
backup_logs
update_logs
report_logs
```

قوانین:

- نام جدول Log باید مشخص کند چه چیزی را ثبت می‌کند.
- Logهای مالی، امنیتی و Audit از هم جدا باشند.
- Log نباید جایگزین داده اصلی شود.
- Log باید قابل فیلتر و گزارش‌گیری باشد.

---

## نام‌گذاری جدول‌های پلاگین

جدول‌های پلاگین باید Prefix داشته باشند.

الگو:

```text
plugin_{plugin_id}_{table_name}
```

نمونه:

```text
plugin_sms_provider_logs
plugin_ai_assistant_requests
plugin_accounting_sync_records
plugin_cloud_backup_jobs
```

قوانین:

- plugin_id باید lowercase و `snake_case` یا `kebab-case` کنترل‌شده باشد.
- در نام جدول دیتابیس بهتر است `-` به `_` تبدیل شود.
- پلاگین نباید جدول Core را بدون Contract رسمی تغییر دهد.
- Migration پلاگین باید جدا ثبت شود.
- حذف پلاگین نباید داده Core را خراب کند.

---

## نام‌گذاری Permissionها

Permissionها باید ساختار نقطه‌ای داشته باشند.

الگو:

```text
domain.resource.action
```

نمونه:

```text
customers.view
customers.create
customers.update
customers.delete
contracts.view
contracts.create
contracts.approve
payments.approve
payments.reject
reports.export
plugins.manage
```

برای پلاگین‌ها:

```text
plugin.{plugin_id}.{action}
```

نمونه:

```text
plugin.sms_provider.view
plugin.sms_provider.manage
plugin.ai_assistant.use
```

قوانین:

- Permission باید واضح و قابل فهم باشد.
- Permission نباید خیلی کلی باشد.
- Permissionهای حساس باید جدا باشند.
- Export گزارش‌ها باید Permission جدا داشته باشد.

---

## نام‌گذاری Settings

Settings باید namespace داشته باشند.

الگو:

```text
domain.setting_key
```

نمونه:

```text
app.name
app.timezone
app.currency
contracts.default_installment_count
payments.card_to_card_enabled
reports.max_export_rows
backups.auto_backup_enabled
plugins.allow_upload
```

برای پلاگین‌ها:

```text
plugin.{plugin_id}.{setting_key}
```

نمونه:

```text
plugin.sms_provider.api_key
plugin.sms_provider.sender_number
plugin.cloud_backup.bucket_name
```

قوانین:

- Settings حساس باید encrypted باشند.
- کلید Setting باید واضح باشد.
- Setting نباید نام عمومی و مبهم داشته باشد.
- Setting پلاگین باید namespace جدا داشته باشد.

---

## نام‌گذاری ستون‌های Metadata

ستون Metadata معمولاً با نام زیر تعریف شود:

```text
metadata
```

اگر چند نوع Metadata وجود دارد، نام دقیق‌تر استفاده شود:

```text
request_metadata
response_metadata
calculation_metadata
gateway_metadata
device_metadata
```

قوانین:

- Metadata نباید جای ستون‌های اصلی را بگیرد.
- داده‌ای که زیاد فیلتر می‌شود نباید فقط در Metadata باشد.
- اطلاعات حساس داخل Metadata باید کنترل شود.
- ساختار Metadata باید در Domain مربوطه مستند شود.

---

## نام‌گذاری ستون‌های Snapshot

برای ذخیره وضعیت یک داده در زمان مشخص، از suffix مشخص استفاده شود.

نمونه:

```text
debt_amount_at_referral
customer_name_snapshot
contract_total_amount_snapshot
installment_status_snapshot
settled_amount_snapshot
```

قوانین:

- Snapshot باید مشخص کند مربوط به چه زمانی است.
- Snapshot نباید با داده زنده اشتباه گرفته شود.
- گزارش‌ها باید بدانند از Snapshot استفاده می‌کنند یا داده زنده.

---

## نام‌گذاری ستون‌های توضیح

برای توضیحات کوتاه:

```text
note
```

برای توضیح کامل‌تر:

```text
description
```

برای دلیل یک عملیات:

```text
reason
```

برای دلیل رد شدن:

```text
rejection_reason
```

برای دلیل حذف:

```text
delete_reason
```

نمونه:

```text
admin_note
customer_note
internal_note
description
reason
rejection_reason
delete_reason
```

قوانین:

- `note` برای یادداشت کوتاه استفاده شود.
- `description` برای توضیح کامل استفاده شود.
- یادداشت داخلی نباید برای مشتری نمایش داده شود.
- ستون‌های note حساس باید Permission داشته باشند.

---

## نام‌گذاری برای Scope و Ownership

برای کنترل دسترسی، بعضی جدول‌ها باید مالک یا مسئول داشته باشند.

نمونه:

```text
owner_user_id
assigned_user_id
assigned_operator_id
assigned_lawyer_id
department_id
branch_id
created_by
```

قوانین:

- اگر داده متعلق به کاربر خاصی است، مالکیت باید واضح باشد.
- اگر داده به اپراتور ارجاع شده، assigned مشخص باشد.
- Scope نباید فقط در UI کنترل شود.
- Queryهای دیتابیس باید Scope را اعمال کنند.

---

## نمونه‌های صحیح و اشتباه

### جدول مشتریان

صحیح:

```text
customers
```

اشتباه:

```text
customer
tbl_customer
moshtari
data
```

### ستون مبلغ پرداخت

صحیح:

```text
payment_amount
```

اشتباه:

```text
money
price
pay
value
```

### ستون تاریخ پرداخت

صحیح:

```text
paid_at
```

اشتباه:

```text
date
paymentDate
pay_time
```

### ستون کاربر تأییدکننده

صحیح:

```text
approved_by
```

اشتباه:

```text
user_id
admin
ok_by
```

---

## چک‌لیست نام‌گذاری

قبل از ساخت جدول یا ستون جدید بررسی شود:

- [ ] نام انگلیسی است.
- [ ] نام `snake_case` است.
- [ ] نام واضح و قابل فهم است.
- [ ] نام جدول جمع است.
- [ ] نام ستون با مفهوم جدول هماهنگ است.
- [ ] Foreign Key با `_id` تمام می‌شود.
- [ ] ستون مبلغ با `_amount` تمام می‌شود.
- [ ] ستون DateTime با `_at` تمام می‌شود.
- [ ] ستون Date با `_date` تمام می‌شود.
- [ ] ستون Boolean با `is_`، `has_`، `can_` یا `requires_` شروع می‌شود.
- [ ] ستون status مقدارهای مستند دارد.
- [ ] Index نام قابل فهم دارد.
- [ ] Migration نام دقیق دارد.
- [ ] نام با استاندارد پلاگین‌ها تداخل ندارد.
- [ ] نام باعث ابهام در گزارش‌گیری نمی‌شود.

---

## Definition of Done

استاندارد نام‌گذاری زمانی درست رعایت شده است که:

- همه جدول‌ها با یک سبک نام‌گذاری شده باشند.
- همه ستون‌ها واضح و قابل فهم باشند.
- هیچ جدول یا ستون فارسی وجود نداشته باشد.
- هیچ نام مبهم مثل `data`، `info` یا `value` بدون Context وجود نداشته باشد.
- Foreign Keyها قابل تشخیص باشند.
- ستون‌های مالی، تاریخی، Boolean و status استاندارد باشند.
- Migrationها قابل فهم و قابل پیگیری باشند.
- پلاگین‌ها Prefix و namespace جدا داشته باشند.
- Codex بتواند بدون حدس‌زدن، ساختار دیتابیس را از روی نام‌ها درک کند.

---

## پایان فایل