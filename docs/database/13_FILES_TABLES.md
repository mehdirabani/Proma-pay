# 13 — Files Tables

مستند جدول‌های فایل‌ها، آپلودها، دسترسی‌ها، دانلودها، نسخه‌ها، ارتباط فایل با موجودیت‌ها و داده‌های وابسته به Files Domain در پروژه **Proma Pay**

---

## فهرست مطالب

- [هدف فایل](#هدف-فایل)
- [تعریف Files Domain در دیتابیس](#تعریف-files-domain-در-دیتابیس)
- [اصل مهم](#اصل-مهم)
- [لیست جدول‌های Files](#لیست-جدولهای-files)
- [جدول files](#جدول-files)
- [جدول file_links](#جدول-file_links)
- [جدول file_access_logs](#جدول-file_access_logs)
- [جدول file_download_tokens](#جدول-file_download_tokens)
- [جدول file_versions](#جدول-file_versions)
- [جدول file_processing_jobs](#جدول-file_processing_jobs)
- [جدول file_storage_disks](#جدول-file_storage_disks)
- [جدول file_security_scans](#جدول-file_security_scans)
- [رابطه Files Domain با سایر Domainها](#رابطه-files-domain-با-سایر-domainها)
- [قوانین ذخیره‌سازی فایل](#قوانین-ذخیرهسازی-فایل)
- [قوانین Private Storage](#قوانین-private-storage)
- [قوانین دانلود فایل](#قوانین-دانلود-فایل)
- [قوانین فایل‌های حساس](#قوانین-فایلهای-حساس)
- [قوانین نسخه‌بندی فایل](#قوانین-نسخهبندی-فایل)
- [قوانین Soft Delete](#قوانین-soft-delete)
- [قوانین Index و Performance](#قوانین-index-و-performance)
- [قوانین Validation](#قوانین-validation)
- [قوانین Audit و Security Log](#قوانین-audit-و-security-log)
- [Seedهای پیشنهادی](#seedهای-پیشنهادی)
- [چک‌لیست پیاده‌سازی](#چکلیست-پیادهسازی)
- [Definition of Done](#definition-of-done)

---

## هدف فایل

هدف این فایل این است که ساختار جدول‌های مربوط به مدیریت فایل‌ها در پروژه **Proma Pay** مشخص شود.

Files Domain یکی از حساس‌ترین بخش‌های سیستم است؛ چون مدارک مشتری، قراردادها، رسیدهای پرداخت، مدارک ضمانت، فایل‌های حقوقی، خروجی گزارش‌ها، بکاپ‌ها و فایل‌های پلاگین همگی می‌توانند از طریق این بخش مدیریت شوند.

این فایل برای Codex مشخص می‌کند که:

- فایل‌ها چگونه در دیتابیس ثبت شوند.
- فایل واقعی کجا ذخیره شود.
- فایل‌های حساس چگونه Private نگهداری شوند.
- ارتباط فایل با مشتری، قرارداد، قسط، پرداخت یا پرونده حقوقی چگونه ذخیره شود.
- دانلود فایل چگونه کنترل شود.
- دسترسی به فایل چگونه Log شود.
- نسخه‌های فایل چگونه مدیریت شوند.
- فایل‌های خطرناک چگونه بررسی یا Block شوند.
- چه فایل‌هایی نیاز به Audit یا Security Log دارند.

---

## تعریف Files Domain در دیتابیس

Files Domain مسئول نگهداری Metadata فایل‌ها است.

نکته مهم:

> فایل واقعی داخل دیتابیس ذخیره نمی‌شود.

دیتابیس فقط اطلاعات زیر را نگهداری می‌کند:

- نام فایل
- مسیر ذخیره‌سازی
- نوع فایل
- حجم فایل
- هش فایل
- مالک فایل
- سطح حساسیت
- وضعیت فایل
- موجودیت مرتبط
- اطلاعات آپلود
- اطلاعات حذف
- لاگ دسترسی
- توکن دانلود امن

فایل واقعی باید در Storage ذخیره شود.

---

## اصل مهم

اصل مهم در Files Tables:

> مسیر واقعی فایل نباید مستقیم در اختیار کاربر قرار بگیرد.

کاربر نباید بتواند با داشتن مسیر فایل، آن را مستقیماً دانلود کند.

به‌خصوص برای فایل‌های زیر:

- تصویر کارت ملی
- فیش حقوقی
- حکم کارگزینی
- قرارداد امضاشده
- چک
- سفته
- رسید پرداخت
- مدارک حقوقی
- گزارش‌های مالی
- بکاپ‌ها
- فایل‌های پلاگین
- فایل‌های خروجی محرمانه

قانون طلایی:

> هر دانلود فایل حساس باید از Controller امن، Permission Check، Scope Check، Token Check و Audit Log عبور کند.

---

## لیست جدول‌های Files

جدول‌های پیشنهادی Files Domain:

| جدول | کاربرد |
|---|---|
| `files` | اطلاعات اصلی فایل‌ها |
| `file_links` | ارتباط فایل با موجودیت‌های مختلف |
| `file_access_logs` | لاگ مشاهده یا دانلود فایل |
| `file_download_tokens` | توکن‌های دانلود موقت و امن |
| `file_versions` | نسخه‌های مختلف یک فایل |
| `file_processing_jobs` | پردازش‌های مرتبط با فایل |
| `file_storage_disks` | محل‌های ذخیره‌سازی فایل |
| `file_security_scans` | بررسی امنیتی فایل‌ها |

---

## جدول files

### هدف جدول

جدول `files` اطلاعات اصلی هر فایل را نگهداری می‌کند.

این جدول مرجع اصلی فایل‌ها در سیستم است.

---

### نام جدول

```text
files
```

---

### ستون‌های پیشنهادی

| ستون | نوع | توضیح |
|---|---|---|
| `id` | BIGINT UNSIGNED AUTO_INCREMENT | شناسه داخلی |
| `file_number` | VARCHAR(50) | شماره رسمی فایل |
| `disk_key` | VARCHAR(100) | کلید Storage |
| `storage_type` | VARCHAR(50) | نوع ذخیره‌سازی |
| `visibility` | VARCHAR(50) | سطح دسترسی فایل |
| `file_category` | VARCHAR(100) | دسته فایل |
| `file_type` | VARCHAR(50) | نوع فایل |
| `original_name` | VARCHAR(255) | نام اصلی فایل |
| `stored_name` | VARCHAR(255) | نام ذخیره‌شده |
| `storage_path` | VARCHAR(500) | مسیر داخلی ذخیره‌سازی |
| `extension` | VARCHAR(20) NULL | پسوند فایل |
| `mime_type` | VARCHAR(191) NULL | MIME Type |
| `size_bytes` | BIGINT UNSIGNED | حجم فایل |
| `checksum_sha256` | VARCHAR(128) NULL | هش فایل |
| `is_sensitive` | TINYINT(1) | حساس بودن |
| `is_public` | TINYINT(1) | عمومی بودن |
| `is_encrypted` | TINYINT(1) | رمزنگاری‌شده بودن |
| `status` | VARCHAR(50) | وضعیت فایل |
| `scan_status` | VARCHAR(50) | وضعیت بررسی امنیتی |
| `owner_type` | VARCHAR(50) NULL | نوع مالک |
| `owner_user_id` | BIGINT UNSIGNED NULL | مالک کاربر |
| `owner_customer_id` | BIGINT UNSIGNED NULL | مالک مشتری |
| `uploaded_by` | BIGINT UNSIGNED NULL | آپلودکننده |
| `uploaded_at` | DATETIME NULL | زمان آپلود |
| `approved_by` | BIGINT UNSIGNED NULL | تأییدکننده |
| `approved_at` | DATETIME NULL | زمان تأیید |
| `deleted_at` | DATETIME NULL | زمان حذف نرم |
| `deleted_by` | BIGINT UNSIGNED NULL | حذف‌کننده |
| `delete_reason` | TEXT NULL | دلیل حذف |
| `metadata` | JSON NULL | داده تکمیلی |
| `created_at` | DATETIME NULL | زمان ایجاد |
| `updated_at` | DATETIME NULL | زمان بروزرسانی |

---

### storage_typeهای پیشنهادی

```text
local
private_local
public_local
remote
s3
backup_storage
temporary
```

---

### visibilityهای پیشنهادی

```text
private
public
internal
customer_private
legal_only
accounting_only
system_only
```

---

### file_categoryهای پیشنهادی

```text
customer_document
contract_document
payment_receipt
guarantee_document
legal_document
report_export
backup
plugin_package
profile_avatar
chat_attachment
system_file
other
```

---

### file_typeهای پیشنهادی

```text
image
pdf
document
spreadsheet
archive
text
audio
video
other
```

---

### statusهای پیشنهادی

```text
uploaded
pending_review
approved
rejected
blocked
quarantined
archived
deleted
```

---

### scan_statusهای پیشنهادی

```text
not_required
pending
clean
suspicious
infected
failed
blocked
```

---

### قوانین

- `file_number` باید Unique باشد.
- فایل واقعی داخل دیتابیس ذخیره نشود.
- مسیر داخلی فایل نباید مستقیم به کاربر نمایش داده شود.
- فایل حساس باید visibility مناسب داشته باشد.
- فایل حساس نباید در Public Storage ذخیره شود.
- فایل‌های حقوقی، مالی، قرارداد، ضمانت و مدارک مشتری باید Private باشند.
- فایل حذف‌شده باید Soft Delete شود.
- فایل blocked یا quarantined نباید دانلود شود.
- فایل approved می‌تواند برای استفاده رسمی معتبر باشد.
- checksum برای تشخیص تغییر یا تکرار فایل مفید است.
- نام فایل ذخیره‌شده نباید فقط original_name باشد.
- فایل باید با نام امن و غیرقابل حدس ذخیره شود.

---

### Indexهای پیشنهادی

```text
uniq_files_file_number
idx_files_disk_key
idx_files_storage_type
idx_files_visibility
idx_files_file_category
idx_files_file_type
idx_files_extension
idx_files_mime_type
idx_files_is_sensitive
idx_files_is_public
idx_files_status
idx_files_scan_status
idx_files_owner
idx_files_uploaded_by
idx_files_uploaded_at
idx_files_deleted_at
idx_files_checksum_sha256
```

---

### نمونه ساختار SQL

```sql
CREATE TABLE files (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    file_number VARCHAR(50) NOT NULL,
    disk_key VARCHAR(100) NOT NULL,
    storage_type VARCHAR(50) NOT NULL DEFAULT 'private_local',
    visibility VARCHAR(50) NOT NULL DEFAULT 'private',
    file_category VARCHAR(100) NOT NULL DEFAULT 'other',
    file_type VARCHAR(50) NOT NULL DEFAULT 'other',
    original_name VARCHAR(255) NOT NULL,
    stored_name VARCHAR(255) NOT NULL,
    storage_path VARCHAR(500) NOT NULL,
    extension VARCHAR(20) NULL,
    mime_type VARCHAR(191) NULL,
    size_bytes BIGINT UNSIGNED NOT NULL DEFAULT 0,
    checksum_sha256 VARCHAR(128) NULL,
    is_sensitive TINYINT(1) NOT NULL DEFAULT 1,
    is_public TINYINT(1) NOT NULL DEFAULT 0,
    is_encrypted TINYINT(1) NOT NULL DEFAULT 0,
    status VARCHAR(50) NOT NULL DEFAULT 'uploaded',
    scan_status VARCHAR(50) NOT NULL DEFAULT 'pending',
    owner_type VARCHAR(50) NULL,
    owner_user_id BIGINT UNSIGNED NULL,
    owner_customer_id BIGINT UNSIGNED NULL,
    uploaded_by BIGINT UNSIGNED NULL,
    uploaded_at DATETIME NULL,
    approved_by BIGINT UNSIGNED NULL,
    approved_at DATETIME NULL,
    deleted_at DATETIME NULL,
    deleted_by BIGINT UNSIGNED NULL,
    delete_reason TEXT NULL,
    metadata JSON NULL,
    created_at DATETIME NULL,
    updated_at DATETIME NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uniq_files_file_number (file_number),
    KEY idx_files_disk_key (disk_key),
    KEY idx_files_storage_type (storage_type),
    KEY idx_files_visibility (visibility),
    KEY idx_files_file_category (file_category),
    KEY idx_files_file_type (file_type),
    KEY idx_files_extension (extension),
    KEY idx_files_mime_type (mime_type),
    KEY idx_files_is_sensitive (is_sensitive),
    KEY idx_files_is_public (is_public),
    KEY idx_files_status (status),
    KEY idx_files_scan_status (scan_status),
    KEY idx_files_owner (owner_type, owner_user_id, owner_customer_id),
    KEY idx_files_uploaded_by (uploaded_by),
    KEY idx_files_uploaded_at (uploaded_at),
    KEY idx_files_deleted_at (deleted_at),
    KEY idx_files_checksum_sha256 (checksum_sha256)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

---

## جدول file_links

### هدف جدول

جدول `file_links` ارتباط فایل با موجودیت‌های مختلف سیستم را نگهداری می‌کند.

یک فایل می‌تواند به مشتری، قرارداد، پرداخت، قسط، پرونده حقوقی، چت یا گزارش مرتبط باشد.

---

### نام جدول

```text
file_links
```

---

### ستون‌های پیشنهادی

| ستون | نوع | توضیح |
|---|---|---|
| `id` | BIGINT UNSIGNED AUTO_INCREMENT | شناسه |
| `file_id` | BIGINT UNSIGNED | فایل |
| `related_type` | VARCHAR(100) | نوع موجودیت مرتبط |
| `related_id` | BIGINT UNSIGNED | شناسه موجودیت مرتبط |
| `customer_id` | BIGINT UNSIGNED NULL | مشتری مرتبط |
| `contract_id` | BIGINT UNSIGNED NULL | قرارداد مرتبط |
| `installment_id` | BIGINT UNSIGNED NULL | قسط مرتبط |
| `payment_id` | BIGINT UNSIGNED NULL | پرداخت مرتبط |
| `legal_case_id` | BIGINT UNSIGNED NULL | پرونده حقوقی مرتبط |
| `link_type` | VARCHAR(50) | نوع ارتباط |
| `title` | VARCHAR(191) NULL | عنوان ارتباط |
| `description` | TEXT NULL | توضیح |
| `is_primary` | TINYINT(1) | فایل اصلی بودن |
| `status` | VARCHAR(50) | وضعیت ارتباط |
| `created_by` | BIGINT UNSIGNED NULL | ایجادکننده ارتباط |
| `created_at` | DATETIME NULL | زمان ایجاد |
| `deleted_at` | DATETIME NULL | زمان حذف نرم |
| `deleted_by` | BIGINT UNSIGNED NULL | حذف‌کننده ارتباط |

---

### related_typeهای پیشنهادی

```text
customer
contract
installment
payment
legal_case
chat_message
report_export
backup
plugin
user
system
other
```

---

### link_typeهای پیشنهادی

```text
document
receipt
attachment
avatar
signed_file
export
backup_file
plugin_package
evidence
other
```

---

### statusهای پیشنهادی

```text
active
archived
deleted
```

---

### قوانین

- هر file_link باید file_id داشته باشد.
- هر file_link باید related_type و related_id داشته باشد.
- فایل مشتری باید customer_id داشته باشد.
- فایل قرارداد باید contract_id داشته باشد.
- فایل حقوقی باید legal_case_id یا contract_id داشته باشد.
- حذف ارتباط فایل باید Soft Delete شود.
- حذف ارتباط نباید الزاماً فایل اصلی را حذف کند.
- ارتباط فایل حساس باید Permission و Scope همان موجودیت را رعایت کند.

---

### Indexهای پیشنهادی

```text
idx_file_links_file_id
idx_file_links_related
idx_file_links_customer_id
idx_file_links_contract_id
idx_file_links_installment_id
idx_file_links_payment_id
idx_file_links_legal_case_id
idx_file_links_link_type
idx_file_links_is_primary
idx_file_links_status
idx_file_links_created_at
idx_file_links_deleted_at
```

---

## جدول file_access_logs

### هدف جدول

جدول `file_access_logs` مشاهده، دانلود، پیش‌نمایش یا تلاش دسترسی به فایل را ثبت می‌کند.

این جدول برای Audit و Security بسیار مهم است.

---

### نام جدول

```text
file_access_logs
```

---

### ستون‌های پیشنهادی

| ستون | نوع | توضیح |
|---|---|---|
| `id` | BIGINT UNSIGNED AUTO_INCREMENT | شناسه |
| `file_id` | BIGINT UNSIGNED | فایل |
| `access_type` | VARCHAR(50) | نوع دسترسی |
| `access_result` | VARCHAR(50) | نتیجه دسترسی |
| `user_id` | BIGINT UNSIGNED NULL | کاربر داخلی |
| `customer_id` | BIGINT UNSIGNED NULL | مشتری |
| `related_type` | VARCHAR(100) NULL | موجودیت مرتبط |
| `related_id` | BIGINT UNSIGNED NULL | شناسه موجودیت |
| `ip_address` | VARCHAR(45) NULL | IP |
| `user_agent` | TEXT NULL | User Agent |
| `download_token_id` | BIGINT UNSIGNED NULL | توکن دانلود |
| `reason` | TEXT NULL | دلیل یا توضیح |
| `created_at` | DATETIME NULL | زمان ثبت |

---

### access_typeهای پیشنهادی

```text
view
preview
download
upload
replace
delete
restore
permission_check
```

---

### access_resultهای پیشنهادی

```text
allowed
denied
failed
blocked
expired_token
invalid_token
not_found
```

---

### قوانین

- دانلود فایل حساس باید در این جدول ثبت شود.
- تلاش ناموفق برای دانلود فایل حساس باید ثبت شود.
- مشاهده فایل حقوقی باید Log شود.
- مشاهده فایل مالی یا رسید باید Log شود.
- این جدول نباید Soft Delete شود.
- اطلاعات حساس غیرضروری نباید در reason ذخیره شود.
- دسترسی denied می‌تواند همزمان Security Log هم ایجاد کند.

---

### Indexهای پیشنهادی

```text
idx_file_access_logs_file_id
idx_file_access_logs_access_type
idx_file_access_logs_access_result
idx_file_access_logs_user_id
idx_file_access_logs_customer_id
idx_file_access_logs_related
idx_file_access_logs_download_token_id
idx_file_access_logs_created_at
```

---

## جدول file_download_tokens

### هدف جدول

جدول `file_download_tokens` توکن‌های موقت دانلود فایل را نگهداری می‌کند.

برای فایل‌های Private، دانلود باید از طریق توکن امن و موقت انجام شود.

---

### نام جدول

```text
file_download_tokens
```

---

### ستون‌های پیشنهادی

| ستون | نوع | توضیح |
|---|---|---|
| `id` | BIGINT UNSIGNED AUTO_INCREMENT | شناسه |
| `file_id` | BIGINT UNSIGNED | فایل |
| `token_hash` | VARCHAR(191) | Hash توکن |
| `purpose` | VARCHAR(50) | هدف توکن |
| `user_id` | BIGINT UNSIGNED NULL | کاربر مجاز |
| `customer_id` | BIGINT UNSIGNED NULL | مشتری مجاز |
| `max_uses` | INT UNSIGNED | حداکثر استفاده |
| `used_count` | INT UNSIGNED | تعداد استفاده |
| `expires_at` | DATETIME | زمان انقضا |
| `last_used_at` | DATETIME NULL | آخرین استفاده |
| `revoked_at` | DATETIME NULL | زمان ابطال |
| `revoked_by` | BIGINT UNSIGNED NULL | ابطال‌کننده |
| `status` | VARCHAR(50) | وضعیت |
| `created_by` | BIGINT UNSIGNED NULL | ایجادکننده |
| `created_at` | DATETIME NULL | زمان ایجاد |

---

### purposeهای پیشنهادی

```text
download
preview
temporary_share
report_export
backup_download
plugin_download
```

---

### statusهای پیشنهادی

```text
active
used
expired
revoked
blocked
```

---

### قوانین

- توکن خام نباید ذخیره شود.
- فقط token_hash ذخیره شود.
- توکن باید زمان انقضا داشته باشد.
- توکن فایل حساس باید کوتاه‌مدت باشد.
- توکن باید max_uses داشته باشد.
- توکن revoked نباید معتبر باشد.
- توکن expired نباید معتبر باشد.
- استفاده از توکن باید file_access_log ثبت کند.
- توکن نباید Permission و Scope را دور بزند.

---

### Indexهای پیشنهادی

```text
idx_file_download_tokens_file_id
idx_file_download_tokens_token_hash
idx_file_download_tokens_purpose
idx_file_download_tokens_user_id
idx_file_download_tokens_customer_id
idx_file_download_tokens_expires_at
idx_file_download_tokens_status
idx_file_download_tokens_created_at
```

---

## جدول file_versions

### هدف جدول

جدول `file_versions` نسخه‌های مختلف یک فایل را نگهداری می‌کند.

این جدول برای مواردی مثل جایگزینی قرارداد، اصلاح سند، نسخه جدید گزارش یا آپلود مجدد مدرک کاربرد دارد.

---

### نام جدول

```text
file_versions
```

---

### ستون‌های پیشنهادی

| ستون | نوع | توضیح |
|---|---|---|
| `id` | BIGINT UNSIGNED AUTO_INCREMENT | شناسه |
| `file_id` | BIGINT UNSIGNED | فایل اصلی |
| `version_number` | INT UNSIGNED | شماره نسخه |
| `version_file_id` | BIGINT UNSIGNED | فایل نسخه |
| `change_type` | VARCHAR(50) | نوع تغییر |
| `change_reason` | TEXT NULL | دلیل تغییر |
| `created_by` | BIGINT UNSIGNED NULL | ایجادکننده |
| `created_at` | DATETIME NULL | زمان ایجاد |

---

### change_typeهای پیشنهادی

```text
uploaded_new_version
corrected_file
replaced_file
generated_again
manual_update
system_update
```

---

### قوانین

- هر نسخه باید file_id اصلی داشته باشد.
- هر نسخه باید version_file_id داشته باشد.
- version_number برای هر file_id باید یکتا باشد.
- جایگزینی فایل حساس باید Audit Log داشته باشد.
- نسخه‌های قدیمی فایل رسمی نباید حذف شوند.
- نسخه جدید فایل حقوقی یا قرارداد باید دلیل داشته باشد.
- نسخه‌بندی نباید فایل اصلی را بی‌ردپا تغییر دهد.

---

### Indexهای پیشنهادی

```text
idx_file_versions_file_id
idx_file_versions_version_file_id
idx_file_versions_version_number
idx_file_versions_change_type
idx_file_versions_created_by
idx_file_versions_created_at
```

---

## جدول file_processing_jobs

### هدف جدول

جدول `file_processing_jobs` پردازش‌های مربوط به فایل را نگهداری می‌کند.

مثال:

- ساخت Thumbnail
- تبدیل تصویر
- فشرده‌سازی
- بررسی MIME
- محاسبه Checksum
- اسکن امنیتی
- تولید PDF
- پاکسازی فایل موقت

---

### نام جدول

```text
file_processing_jobs
```

---

### ستون‌های پیشنهادی

| ستون | نوع | توضیح |
|---|---|---|
| `id` | BIGINT UNSIGNED AUTO_INCREMENT | شناسه |
| `file_id` | BIGINT UNSIGNED | فایل |
| `job_type` | VARCHAR(50) | نوع پردازش |
| `status` | VARCHAR(50) | وضعیت |
| `attempts` | INT UNSIGNED | تعداد تلاش |
| `max_attempts` | INT UNSIGNED | حداکثر تلاش |
| `started_at` | DATETIME NULL | زمان شروع |
| `completed_at` | DATETIME NULL | زمان تکمیل |
| `failed_at` | DATETIME NULL | زمان شکست |
| `error_message` | TEXT NULL | پیام خطا |
| `result_data` | JSON NULL | نتیجه پردازش |
| `created_at` | DATETIME NULL | زمان ایجاد |
| `updated_at` | DATETIME NULL | زمان بروزرسانی |

---

### job_typeهای پیشنهادی

```text
thumbnail
mime_detect
checksum
security_scan
image_optimize
pdf_generate
archive_extract_check
temporary_cleanup
other
```

---

### statusهای پیشنهادی

```text
pending
running
completed
failed
cancelled
```

---

### قوانین

- هر Job باید file_id داشته باشد.
- Job شکست‌خورده باید error_message داشته باشد.
- Jobهای فایل نباید باعث Timeout طولانی شوند.
- پردازش فایل‌های بزرگ باید محدود شود.
- پردازش فایل حساس نباید فایل را Public کند.
- نتیجه پردازش باید در result_data به اندازه نیاز ذخیره شود.
- برای Jobهای مهم می‌توان Core Jobs هم استفاده کرد.

---

### Indexهای پیشنهادی

```text
idx_file_processing_jobs_file_id
idx_file_processing_jobs_job_type
idx_file_processing_jobs_status
idx_file_processing_jobs_started_at
idx_file_processing_jobs_completed_at
idx_file_processing_jobs_failed_at
```

---

## جدول file_storage_disks

### هدف جدول

جدول `file_storage_disks` محل‌های ذخیره‌سازی فایل را معرفی می‌کند.

این جدول کمک می‌کند سیستم بداند هر فایل روی چه نوع Storage ذخیره شده است.

---

### نام جدول

```text
file_storage_disks
```

---

### ستون‌های پیشنهادی

| ستون | نوع | توضیح |
|---|---|---|
| `id` | BIGINT UNSIGNED AUTO_INCREMENT | شناسه |
| `disk_key` | VARCHAR(100) | کلید Storage |
| `name` | VARCHAR(191) | نام نمایشی |
| `storage_type` | VARCHAR(50) | نوع Storage |
| `base_path` | VARCHAR(500) NULL | مسیر پایه داخلی |
| `is_default` | TINYINT(1) | پیش‌فرض بودن |
| `is_private` | TINYINT(1) | خصوصی بودن |
| `is_active` | TINYINT(1) | فعال بودن |
| `max_file_size_bytes` | BIGINT UNSIGNED NULL | حداکثر حجم فایل |
| `allowed_extensions` | JSON NULL | پسوندهای مجاز |
| `settings` | JSON NULL | تنظیمات غیرحساس |
| `created_at` | DATETIME NULL | زمان ایجاد |
| `updated_at` | DATETIME NULL | زمان بروزرسانی |

---

### storage_typeهای پیشنهادی

```text
local
private_local
public_local
remote
s3
temporary
backup_storage
```

---

### قوانین

- disk_key باید Unique باشد.
- Storage پیش‌فرض باید مشخص باشد.
- Storage خصوصی باید خارج از Public Web Root باشد.
- تنظیمات حساس Storage نباید خام در settings ذخیره شود.
- تغییر Storage فعال باید Audit Log داشته باشد.
- غیرفعال کردن Storage دارای فایل فعال باید محدود شود.
- Backup Storage نباید برای فایل‌های عمومی استفاده شود.

---

### Indexهای پیشنهادی

```text
uniq_file_storage_disks_disk_key
idx_file_storage_disks_storage_type
idx_file_storage_disks_is_default
idx_file_storage_disks_is_private
idx_file_storage_disks_is_active
```

---

## جدول file_security_scans

### هدف جدول

جدول `file_security_scans` نتیجه بررسی امنیتی فایل را نگهداری می‌کند.

این جدول برای تشخیص فایل‌های خطرناک، پسوندهای غیرمجاز یا فایل‌های مشکوک استفاده می‌شود.

---

### نام جدول

```text
file_security_scans
```

---

### ستون‌های پیشنهادی

| ستون | نوع | توضیح |
|---|---|---|
| `id` | BIGINT UNSIGNED AUTO_INCREMENT | شناسه |
| `file_id` | BIGINT UNSIGNED | فایل |
| `scan_type` | VARCHAR(50) | نوع بررسی |
| `status` | VARCHAR(50) | وضعیت بررسی |
| `risk_level` | VARCHAR(50) | سطح ریسک |
| `result_message` | TEXT NULL | پیام نتیجه |
| `detected_mime_type` | VARCHAR(191) NULL | MIME تشخیص‌داده‌شده |
| `detected_extension` | VARCHAR(20) NULL | پسوند تشخیص‌داده‌شده |
| `scanner_name` | VARCHAR(100) NULL | نام اسکنر |
| `scanned_at` | DATETIME NULL | زمان اسکن |
| `scanned_by` | BIGINT UNSIGNED NULL | اسکن‌کننده |
| `metadata` | JSON NULL | داده تکمیلی |
| `created_at` | DATETIME NULL | زمان ایجاد |

---

### scan_typeهای پیشنهادی

```text
mime_check
extension_check
checksum_check
virus_scan
archive_check
content_check
manual_review
```

---

### statusهای پیشنهادی

```text
pending
clean
suspicious
infected
failed
blocked
approved_manually
```

---

### risk_levelهای پیشنهادی

```text
low
medium
high
critical
unknown
```

---

### قوانین

- هر Scan باید file_id داشته باشد.
- فایل infected یا blocked نباید دانلود شود.
- فایل suspicious باید نیازمند بررسی باشد.
- نتیجه اسکن باید روی files.scan_status اثر بگذارد.
- اسکن دستی باید scanned_by داشته باشد.
- خطای اسکن باید قابل مشاهده برای Admin باشد.
- فایل‌های ZIP پلاگین باید بررسی مسیر و محتوا شوند.
- فایل‌های اجرایی خطرناک باید Block شوند.

---

### Indexهای پیشنهادی

```text
idx_file_security_scans_file_id
idx_file_security_scans_scan_type
idx_file_security_scans_status
idx_file_security_scans_risk_level
idx_file_security_scans_scanned_at
idx_file_security_scans_scanned_by
```

---

## رابطه Files Domain با سایر Domainها

Files Domain با تقریباً همه بخش‌های سیستم ارتباط دارد.

| Domain | رابطه |
|---|---|
| Customers | مدارک مشتری |
| Contracts | قراردادها، مدارک امضا، ضمانت‌ها |
| Installments | فایل‌های مرتبط با پیگیری یا تسویه قسط |
| Payments | رسیدهای پرداخت |
| Legal | مدارک حقوقی، دادخواست، ابلاغیه، رأی |
| Chat | پیوست پیام‌ها |
| Reports | فایل خروجی گزارش‌ها |
| Backup & Update | فایل بکاپ و بسته Update |
| Plugins | فایل ZIP پلاگین |
| Users | آواتار یا مدارک کاربر داخلی |
| Audit | دانلود و تغییر فایل حساس |
| Security | تلاش دسترسی غیرمجاز به فایل |

---

## قوانین ذخیره‌سازی فایل

قوانین:

- فایل واقعی داخل دیتابیس ذخیره نشود.
- فقط Metadata و مسیر داخلی فایل در دیتابیس ذخیره شود.
- فایل با نام امن، تصادفی و غیرقابل حدس ذخیره شود.
- original_name فقط برای نمایش کنترل‌شده استفاده شود.
- پسوند فایل باید Validate شود.
- MIME Type باید Validate شود.
- حجم فایل باید محدود شود.
- فایل موقت باید پاکسازی شود.
- مسیر ذخیره‌سازی نباید از ورودی کاربر ساخته شود.
- `../` و مسیرهای خطرناک باید ممنوع باشند.

---

## قوانین Private Storage

قوانین:

- فایل‌های حساس باید خارج از Public Web Root ذخیره شوند.
- دسترسی مستقیم URL به فایل حساس ممنوع است.
- دانلود باید از Controller امن انجام شود.
- Controller باید Permission، Scope و Token را بررسی کند.
- فایل‌های زیر همیشه Private هستند:

```text
customer_document
contract_document
payment_receipt
guarantee_document
legal_document
report_export
backup
plugin_package
```

---

## قوانین دانلود فایل

قوانین:

- فایل Private فقط با Permission قابل دانلود است.
- فایل Private می‌تواند Token موقت داشته باشد.
- token خام نباید در دیتابیس ذخیره شود.
- هر دانلود فایل حساس باید file_access_log داشته باشد.
- فایل deleted، blocked یا quarantined نباید دانلود شود.
- دانلود فایل خارج از Scope باید رد شود.
- تلاش دانلود نامعتبر باید Security Log داشته باشد.
- نام فایل دانلودی باید امن باشد.

---

## قوانین فایل‌های حساس

فایل‌های حساس شامل موارد زیر هستند:

```text
کارت ملی
شناسنامه
فیش حقوقی
حکم کارگزینی
قرارداد امضاشده
چک
سفته
رسید پرداخت
مدارک حقوقی
گزارش مالی
بکاپ
بسته پلاگین
```

قوانین:

- نمایش فایل حساس باید Permission داشته باشد.
- دانلود فایل حساس باید Audit Log داشته باشد.
- Export یا اشتراک‌گذاری فایل حساس باید محدود باشد.
- فایل حساس نباید Public شود.
- فایل حساس نباید با لینک دائمی عمومی منتشر شود.
- فایل حساس حذف‌شده باید از دسترس خارج شود.

---

## قوانین نسخه‌بندی فایل

قوانین:

- جایگزینی فایل رسمی باید نسخه جدید بسازد.
- نسخه قبلی نباید بدون دلیل حذف شود.
- نسخه جدید باید reason داشته باشد.
- نسخه‌بندی قرارداد و سند حقوقی باید Audit Log داشته باشد.
- نسخه‌های فایل باید قابل مشاهده برای نقش مجاز باشند.
- نسخه‌بندی نباید مسیر فایل اصلی را بدون ردپا تغییر دهد.

---

## قوانین Soft Delete

جدول‌های زیر باید Soft Delete داشته باشند:

- `files`
- `file_links`

قوانین:

- حذف فایل باید deleted_at داشته باشد.
- فایل حذف‌شده نباید دانلود شود.
- حذف فایل حساس باید Audit Log داشته باشد.
- فایل دارای لاگ یا ارتباط رسمی نباید فیزیکی حذف شود.
- حذف فیزیکی فقط طبق Archive/Cleanup Policy مجاز است.
- file_access_logs حذف نشوند.
- file_download_tokens حذف نشوند، فقط expired یا revoked شوند.

---

## قوانین Index و Performance

قوانین:

- جستجوی فایل با file_number باید سریع باشد.
- فایل‌های یک مشتری باید سریع قابل فیلتر باشند.
- فایل‌های یک قرارداد یا پرونده حقوقی باید سریع قابل فیلتر باشند.
- فایل‌های pending_review باید سریع پیدا شوند.
- فایل‌های scan_status مشکوک باید سریع پیدا شوند.
- لاگ‌های دسترسی ممکن است بزرگ شوند و نیاز به Archive دارند.
- فایل‌های موقت باید با Job پاکسازی شوند.
- Queryهای Files باید Pagination داشته باشند.

Indexهای مهم:

```text
files.file_number
files.file_category
files.status
files.scan_status
files.uploaded_by
files.uploaded_at
file_links.file_id
file_links.related_type
file_links.related_id
file_links.customer_id
file_links.contract_id
file_access_logs.file_id
file_access_logs.created_at
file_download_tokens.token_hash
file_download_tokens.expires_at
```

---

## قوانین Validation

### files

- file_number الزامی و یکتا است.
- disk_key الزامی است.
- original_name الزامی است.
- stored_name الزامی است.
- storage_path الزامی است.
- size_bytes باید غیرمنفی باشد.
- visibility معتبر باشد.
- file_category معتبر باشد.
- status معتبر باشد.
- scan_status معتبر باشد.
- فایل حساس نباید is_public داشته باشد.

### file_links

- file_id الزامی است.
- related_type الزامی است.
- related_id الزامی است.
- link_type معتبر باشد.
- status معتبر باشد.

### file_access_logs

- file_id الزامی است.
- access_type معتبر باشد.
- access_result معتبر باشد.

### file_download_tokens

- file_id الزامی است.
- token_hash الزامی است.
- expires_at الزامی است.
- max_uses باید بیشتر از صفر باشد.
- status معتبر باشد.

### file_versions

- file_id الزامی است.
- version_file_id الزامی است.
- version_number باید بیشتر از صفر باشد.
- برای فایل رسمی، change_reason الزامی باشد.

### file_security_scans

- file_id الزامی است.
- scan_type معتبر باشد.
- status معتبر باشد.
- risk_level معتبر باشد.

---

## قوانین Audit و Security Log

### Audit Log الزامی برای:

- آپلود فایل حساس
- تأیید یا رد فایل حساس
- دانلود فایل حساس
- حذف فایل حساس
- جایگزینی فایل رسمی
- ایجاد نسخه جدید برای قرارداد یا سند حقوقی
- تغییر Storage Disk
- تغییر دسترسی فایل
- ایجاد توکن دانلود برای فایل حساس
- ابطال توکن دانلود
- Export فایل‌های حساس
- دانلود بکاپ
- آپلود پلاگین

### Security Log الزامی برای:

- تلاش دانلود فایل بدون Permission
- تلاش دانلود فایل خارج از Scope
- تلاش دانلود با Token نامعتبر
- تلاش دانلود با Token منقضی‌شده
- تلاش دسترسی مستقیم به مسیر فایل
- آپلود فایل با پسوند خطرناک
- آپلود فایل با MIME نامعتبر
- آپلود فایل دارای مسیر `../`
- تلاش مشاهده فایل حقوقی توسط نقش غیرمجاز
- تلاش دانلود بکاپ بدون Permission
- CSRF نامعتبر در آپلود یا حذف فایل

---

## Seedهای پیشنهادی

### file_category

```text
customer_document
contract_document
payment_receipt
guarantee_document
legal_document
report_export
backup
plugin_package
profile_avatar
chat_attachment
system_file
other
```

### file_type

```text
image
pdf
document
spreadsheet
archive
text
audio
video
other
```

### visibility

```text
private
public
internal
customer_private
legal_only
accounting_only
system_only
```

### file_status

```text
uploaded
pending_review
approved
rejected
blocked
quarantined
archived
deleted
```

### scan_status

```text
not_required
pending
clean
suspicious
infected
failed
blocked
```

### storage_disk

```text
private_local
public_local
temporary
backup_storage
plugin_storage
```

---

## چک‌لیست پیاده‌سازی

قبل از پیاده‌سازی Files Tables بررسی شود:

- [ ] جدول `files` ساخته شده است.
- [ ] `file_number` یکتا است.
- [ ] فایل واقعی داخل دیتابیس ذخیره نمی‌شود.
- [ ] فایل‌های حساس در Private Storage ذخیره می‌شوند.
- [ ] مسیر واقعی فایل در UI نمایش داده نمی‌شود.
- [ ] جدول `file_links` وجود دارد.
- [ ] فایل‌ها به customer، contract، payment، legal_case و سایر موجودیت‌ها قابل اتصال هستند.
- [ ] جدول `file_access_logs` وجود دارد.
- [ ] دانلود فایل حساس Log می‌شود.
- [ ] جدول `file_download_tokens` وجود دارد.
- [ ] token خام ذخیره نمی‌شود.
- [ ] جدول `file_versions` وجود دارد.
- [ ] جایگزینی فایل رسمی نسخه جدید می‌سازد.
- [ ] جدول `file_processing_jobs` وجود دارد.
- [ ] جدول `file_storage_disks` وجود دارد.
- [ ] جدول `file_security_scans` وجود دارد.
- [ ] فایل‌های خطرناک Block می‌شوند.
- [ ] عملیات حساس Audit Log دارند.
- [ ] تلاش‌های غیرمجاز Security Log دارند.
- [ ] دانلود فایل Permission و Scope را رعایت می‌کند.

---

## Definition of Done

Files Tables زمانی کامل هستند که:

- فایل با شماره رسمی یکتا قابل ثبت باشد.
- فایل واقعی خارج از دیتابیس ذخیره شود.
- Metadata فایل در دیتابیس کامل و قابل جستجو باشد.
- فایل‌های حساس در Private Storage باشند.
- مسیر واقعی فایل برای کاربر قابل مشاهده نباشد.
- فایل به موجودیت‌های مختلف سیستم قابل اتصال باشد.
- دانلود فایل Private فقط از مسیر امن انجام شود.
- توکن‌های دانلود موقت و امن قابل ایجاد و ابطال باشند.
- دسترسی به فایل‌های حساس Log شود.
- نسخه‌های مختلف فایل قابل ثبت باشند.
- فایل‌های خطرناک یا مشکوک قابل Block یا Quarantine باشند.
- Storage Diskها قابل مدیریت باشند.
- فایل حذف‌شده از دسترس خارج شود.
- عملیات حساس Audit Log داشته باشند.
- تلاش‌های غیرمجاز Security Log داشته باشند.
- جدول‌ها با MySQL/MariaDB و PHP 7.4+ سازگار باشند.
- Codex بتواند از روی این مستندات Migrationهای Files Domain را بسازد.

---

## پایان فایل
````
