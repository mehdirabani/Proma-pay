نام فایل:

`10_CHAT_TABLES.md`

پوشه هدف:

`docs/database/`

نگارش شده محتویات فایل با زبان README.md:

````markdown
# 10 — Chat Tables

مستند جدول‌های گفتگو، پیام‌ها، شرکت‌کنندگان، فایل‌های پیوست، خوانده‌شدن پیام‌ها، بات، کانال‌های پیام‌رسان و داده‌های وابسته به Chat Domain در پروژه **Proma Pay**

---

## فهرست مطالب

- [هدف فایل](#هدف-فایل)
- [تعریف Chat Domain در دیتابیس](#تعریف-chat-domain-در-دیتابیس)
- [اصل مهم](#اصل-مهم)
- [لیست جدول‌های Chat](#لیست-جدولهای-chat)
- [جدول chat_threads](#جدول-chat_threads)
- [جدول chat_participants](#جدول-chat_participants)
- [جدول chat_messages](#جدول-chat_messages)
- [جدول chat_message_attachments](#جدول-chat_message_attachments)
- [جدول chat_message_reads](#جدول-chat_message_reads)
- [جدول chat_message_status_histories](#جدول-chat_message_status_histories)
- [جدول bot_conversations](#جدول-bot_conversations)
- [جدول bot_messages](#جدول-bot_messages)
- [جدول external_message_channels](#جدول-external_message_channels)
- [جدول external_message_logs](#جدول-external_message_logs)
- [رابطه Chat Domain با سایر Domainها](#رابطه-chat-domain-با-سایر-domainها)
- [قوانین پیام‌های داخلی](#قوانین-پیامهای-داخلی)
- [قوانین پیام مشتری](#قوانین-پیام-مشتری)
- [قوانین Bot](#قوانین-bot)
- [قوانین فایل‌های پیوست گفتگو](#قوانین-فایلهای-پیوست-گفتگو)
- [قوانین وضعیت پیام](#قوانین-وضعیت-پیام)
- [قوانین Soft Delete](#قوانین-soft-delete)
- [قوانین Index و Performance](#قوانین-index-و-performance)
- [قوانین Validation](#قوانین-validation)
- [قوانین Audit و Security Log](#قوانین-audit-و-security-log)
- [Seedهای پیشنهادی](#seedهای-پیشنهادی)
- [چک‌لیست پیاده‌سازی](#چکلیست-پیادهسازی)
- [Definition of Done](#definition-of-done)

---

## هدف فایل

هدف این فایل این است که ساختار جدول‌های مربوط به گفتگوها و پیام‌ها در پروژه **Proma Pay** مشخص شود.

Chat Domain برای ثبت ارتباطات داخلی، ارتباط با مشتری، پیام‌های سیستمی، پیام‌های بات و لاگ پیام‌های ارسال‌شده از کانال‌های خارجی استفاده می‌شود.

این فایل برای Codex مشخص می‌کند که:

- گفتگوها چگونه ذخیره شوند.
- پیام‌ها چگونه ثبت شوند.
- شرکت‌کنندگان گفتگو چگونه مدیریت شوند.
- فایل‌های پیوست پیام چگونه در Files Domain ذخیره شوند.
- وضعیت ارسال، دریافت و خوانده‌شدن پیام چگونه ثبت شود.
- پیام‌های بات چگونه ذخیره شوند.
- پیام‌های خروجی به SMS، Telegram، WhatsApp یا سایر کانال‌ها چگونه Log شوند.
- ارتباط پیام‌ها با مشتری، قرارداد، قسط، پرداخت یا پرونده حقوقی چگونه باشد.
- چه داده‌هایی حساس هستند و چه Permissionهایی لازم دارند.

---

## تعریف Chat Domain در دیتابیس

Chat Domain مسئول نگهداری ارتباطات متنی و پیام‌محور سیستم است.

این ارتباطات می‌توانند شامل موارد زیر باشند:

- گفتگوی داخلی بین کاربران سیستم
- گفتگوی اپراتور با مشتری
- پیام‌های مرتبط با قرارداد
- پیام‌های مرتبط با قسط
- پیام‌های مرتبط با پرداخت
- پیام‌های مرتبط با پرونده حقوقی
- پیام‌های بات
- پیام‌های سیستمی
- لاگ ارسال پیام از کانال‌های خارجی

Chat Domain جایگزین Notification Domain نیست.

تفاوت مهم:

| بخش | کاربرد |
|---|---|
| Chat | گفتگو و پیام قابل مشاهده در Thread |
| Notification | اعلان کوتاه برای اطلاع‌رسانی یا اقدام |
| External Message Log | ثبت نتیجه ارسال پیام به سرویس خارجی |

---

## اصل مهم

اصل مهم در Chat Tables:

> پیام‌ها ممکن است شامل اطلاعات مالی، حقوقی و شخصی باشند؛ بنابراین نمایش، جستجو، Export و حذف آن‌ها باید با Permission، Scope و Audit کنترل شود.

موارد حساس در پیام‌ها:

- اطلاعات مشتری
- شماره موبایل
- کد ملی
- مبلغ بدهی
- وضعیت اقساط
- رسید پرداخت
- مدارک قرارداد
- پرونده حقوقی
- یادداشت داخلی
- پیام‌های وکیل یا مدیر
- اطلاعات ضمانت

قانون طلایی:

> هر پیامی که به مشتری، قرارداد، قسط، پرداخت یا پرونده حقوقی وصل است باید Scope همان موجودیت را رعایت کند.

---

## لیست جدول‌های Chat

جدول‌های پیشنهادی Chat Domain:

| جدول | کاربرد |
|---|---|
| `chat_threads` | گفتگوها یا Threadهای اصلی |
| `chat_participants` | شرکت‌کنندگان هر گفتگو |
| `chat_messages` | پیام‌های داخل گفتگو |
| `chat_message_attachments` | فایل‌های پیوست پیام |
| `chat_message_reads` | وضعیت خوانده‌شدن پیام‌ها |
| `chat_message_status_histories` | تاریخچه وضعیت پیام |
| `bot_conversations` | نشست یا مکالمه بات |
| `bot_messages` | پیام‌های ورودی و خروجی بات |
| `external_message_channels` | کانال‌های خارجی ارسال پیام |
| `external_message_logs` | لاگ ارسال پیام از کانال‌های خارجی |

---

## جدول chat_threads

### هدف جدول

جدول `chat_threads` گفتگوهای اصلی سیستم را نگهداری می‌کند.

هر Thread می‌تواند مربوط به یک مشتری، قرارداد، قسط، پرداخت، پرونده حقوقی یا گفتگوی داخلی باشد.

---

### نام جدول

```text
chat_threads
```

---

### ستون‌های پیشنهادی

| ستون | نوع | توضیح |
|---|---|---|
| `id` | BIGINT UNSIGNED AUTO_INCREMENT | شناسه داخلی |
| `thread_number` | VARCHAR(50) | شماره رسمی گفتگو |
| `thread_type` | VARCHAR(50) | نوع گفتگو |
| `title` | VARCHAR(191) NULL | عنوان گفتگو |
| `customer_id` | BIGINT UNSIGNED NULL | مشتری مرتبط |
| `contract_id` | BIGINT UNSIGNED NULL | قرارداد مرتبط |
| `installment_id` | BIGINT UNSIGNED NULL | قسط مرتبط |
| `payment_id` | BIGINT UNSIGNED NULL | پرداخت مرتبط |
| `legal_case_id` | BIGINT UNSIGNED NULL | پرونده حقوقی مرتبط |
| `related_type` | VARCHAR(100) NULL | نوع موجودیت مرتبط |
| `related_id` | BIGINT UNSIGNED NULL | شناسه موجودیت مرتبط |
| `status` | VARCHAR(50) | وضعیت گفتگو |
| `priority` | VARCHAR(50) | اولویت |
| `visibility` | VARCHAR(50) | سطح نمایش |
| `last_message_id` | BIGINT UNSIGNED NULL | آخرین پیام |
| `last_message_at` | DATETIME NULL | زمان آخرین پیام |
| `last_sender_id` | BIGINT UNSIGNED NULL | آخرین ارسال‌کننده |
| `assigned_user_id` | BIGINT UNSIGNED NULL | کاربر مسئول |
| `closed_at` | DATETIME NULL | زمان بسته‌شدن |
| `closed_by` | BIGINT UNSIGNED NULL | بستن توسط |
| `close_reason` | TEXT NULL | دلیل بسته‌شدن |
| `metadata` | JSON NULL | داده تکمیلی |
| `created_at` | DATETIME NULL | زمان ایجاد |
| `updated_at` | DATETIME NULL | زمان بروزرسانی |
| `deleted_at` | DATETIME NULL | زمان حذف نرم |
| `created_by` | BIGINT UNSIGNED NULL | ایجادکننده |
| `updated_by` | BIGINT UNSIGNED NULL | ویرایش‌کننده |
| `deleted_by` | BIGINT UNSIGNED NULL | حذف‌کننده |

---

### thread_typeهای پیشنهادی

```text
internal
customer_support
contract
installment
payment
legal
bot
system
other
```

---

### statusهای پیشنهادی

```text
open
pending
waiting_customer
waiting_staff
closed
archived
deleted
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

- `thread_number` باید Unique باشد.
- Thread مربوط به مشتری باید customer_id داشته باشد.
- Thread مربوط به قرارداد باید contract_id داشته باشد.
- Thread حقوقی باید legal_case_id یا contract_id داشته باشد.
- Thread بسته‌شده باید close_reason داشته باشد، اگر نوع آن حساس باشد.
- Thread داخلی برای مشتری نمایش داده نشود.
- Thread حقوقی فقط برای نقش مجاز نمایش داده شود.
- حذف Thread باید Soft Delete باشد.
- Thread دارای پیام نباید فیزیکی حذف شود.
- هر Thread باید Scope موجودیت مرتبط را رعایت کند.

---

### Indexهای پیشنهادی

```text
uniq_chat_threads_thread_number
idx_chat_threads_thread_type
idx_chat_threads_customer_id
idx_chat_threads_contract_id
idx_chat_threads_installment_id
idx_chat_threads_payment_id
idx_chat_threads_legal_case_id
idx_chat_threads_related
idx_chat_threads_status
idx_chat_threads_priority
idx_chat_threads_visibility
idx_chat_threads_assigned_user_id
idx_chat_threads_last_message_at
idx_chat_threads_deleted_at
```

---

### نمونه ساختار SQL

```sql
CREATE TABLE chat_threads (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    thread_number VARCHAR(50) NOT NULL,
    thread_type VARCHAR(50) NOT NULL DEFAULT 'internal',
    title VARCHAR(191) NULL,
    customer_id BIGINT UNSIGNED NULL,
    contract_id BIGINT UNSIGNED NULL,
    installment_id BIGINT UNSIGNED NULL,
    payment_id BIGINT UNSIGNED NULL,
    legal_case_id BIGINT UNSIGNED NULL,
    related_type VARCHAR(100) NULL,
    related_id BIGINT UNSIGNED NULL,
    status VARCHAR(50) NOT NULL DEFAULT 'open',
    priority VARCHAR(50) NOT NULL DEFAULT 'normal',
    visibility VARCHAR(50) NOT NULL DEFAULT 'internal',
    last_message_id BIGINT UNSIGNED NULL,
    last_message_at DATETIME NULL,
    last_sender_id BIGINT UNSIGNED NULL,
    assigned_user_id BIGINT UNSIGNED NULL,
    closed_at DATETIME NULL,
    closed_by BIGINT UNSIGNED NULL,
    close_reason TEXT NULL,
    metadata JSON NULL,
    created_at DATETIME NULL,
    updated_at DATETIME NULL,
    deleted_at DATETIME NULL,
    created_by BIGINT UNSIGNED NULL,
    updated_by BIGINT UNSIGNED NULL,
    deleted_by BIGINT UNSIGNED NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uniq_chat_threads_thread_number (thread_number),
    KEY idx_chat_threads_thread_type (thread_type),
    KEY idx_chat_threads_customer_id (customer_id),
    KEY idx_chat_threads_contract_id (contract_id),
    KEY idx_chat_threads_installment_id (installment_id),
    KEY idx_chat_threads_payment_id (payment_id),
    KEY idx_chat_threads_legal_case_id (legal_case_id),
    KEY idx_chat_threads_related (related_type, related_id),
    KEY idx_chat_threads_status (status),
    KEY idx_chat_threads_priority (priority),
    KEY idx_chat_threads_visibility (visibility),
    KEY idx_chat_threads_assigned_user_id (assigned_user_id),
    KEY idx_chat_threads_last_message_at (last_message_at),
    KEY idx_chat_threads_deleted_at (deleted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

---

## جدول chat_participants

### هدف جدول

جدول `chat_participants` شرکت‌کنندگان هر گفتگو را نگهداری می‌کند.

شرکت‌کننده می‌تواند کاربر داخلی، مشتری، بات یا سیستم باشد.

---

### نام جدول

```text
chat_participants
```

---

### ستون‌های پیشنهادی

| ستون | نوع | توضیح |
|---|---|---|
| `id` | BIGINT UNSIGNED AUTO_INCREMENT | شناسه |
| `thread_id` | BIGINT UNSIGNED | گفتگو |
| `participant_type` | VARCHAR(50) | نوع شرکت‌کننده |
| `user_id` | BIGINT UNSIGNED NULL | کاربر داخلی |
| `customer_id` | BIGINT UNSIGNED NULL | مشتری |
| `display_name` | VARCHAR(191) NULL | نام نمایشی |
| `role_in_thread` | VARCHAR(50) | نقش در گفتگو |
| `is_active` | TINYINT(1) | فعال بودن |
| `joined_at` | DATETIME NULL | زمان ورود |
| `left_at` | DATETIME NULL | زمان خروج |
| `last_read_message_id` | BIGINT UNSIGNED NULL | آخرین پیام خوانده‌شده |
| `last_read_at` | DATETIME NULL | آخرین زمان خواندن |
| `muted_until` | DATETIME NULL | بی‌صدا تا زمان مشخص |
| `metadata` | JSON NULL | داده تکمیلی |
| `created_at` | DATETIME NULL | زمان ایجاد |
| `updated_at` | DATETIME NULL | زمان بروزرسانی |

---

### participant_typeهای پیشنهادی

```text
user
customer
bot
system
external
```

---

### role_in_threadهای پیشنهادی

```text
owner
assignee
participant
viewer
customer
bot
system
```

---

### قوانین

- هر Participant باید thread_id داشته باشد.
- اگر participant_type برابر user است، user_id الزامی است.
- اگر participant_type برابر customer است، customer_id الزامی است.
- شرکت‌کننده غیرفعال نباید پیام جدید ارسال کند.
- مشتری نباید به Thread داخلی اضافه شود.
- تغییر شرکت‌کنندگان Thread حساس باید Audit Log داشته باشد.
- دسترسی به Thread باید از participant و Permission/Scope بررسی شود.

---

### Indexهای پیشنهادی

```text
idx_chat_participants_thread_id
idx_chat_participants_participant_type
idx_chat_participants_user_id
idx_chat_participants_customer_id
idx_chat_participants_role_in_thread
idx_chat_participants_is_active
idx_chat_participants_last_read_at
```

---

## جدول chat_messages

### هدف جدول

جدول `chat_messages` پیام‌های داخل گفتگوها را نگهداری می‌کند.

هر پیام باید به یک Thread وصل باشد.

---

### نام جدول

```text
chat_messages
```

---

### ستون‌های پیشنهادی

| ستون | نوع | توضیح |
|---|---|---|
| `id` | BIGINT UNSIGNED AUTO_INCREMENT | شناسه |
| `message_number` | VARCHAR(50) | شماره رسمی پیام |
| `thread_id` | BIGINT UNSIGNED | گفتگو |
| `parent_message_id` | BIGINT UNSIGNED NULL | پیام والد برای Reply |
| `sender_type` | VARCHAR(50) | نوع ارسال‌کننده |
| `sender_user_id` | BIGINT UNSIGNED NULL | کاربر ارسال‌کننده |
| `sender_customer_id` | BIGINT UNSIGNED NULL | مشتری ارسال‌کننده |
| `message_type` | VARCHAR(50) | نوع پیام |
| `body` | TEXT NULL | متن پیام |
| `body_format` | VARCHAR(50) | فرمت متن |
| `status` | VARCHAR(50) | وضعیت پیام |
| `visibility` | VARCHAR(50) | سطح نمایش پیام |
| `is_internal` | TINYINT(1) | داخلی بودن |
| `is_pinned` | TINYINT(1) | سنجاق‌شده |
| `is_edited` | TINYINT(1) | ویرایش‌شده |
| `edited_at` | DATETIME NULL | زمان ویرایش |
| `edited_by` | BIGINT UNSIGNED NULL | ویرایش‌کننده |
| `sent_at` | DATETIME NULL | زمان ارسال |
| `delivered_at` | DATETIME NULL | زمان تحویل |
| `failed_at` | DATETIME NULL | زمان شکست |
| `deleted_at` | DATETIME NULL | زمان حذف نرم |
| `deleted_by` | BIGINT UNSIGNED NULL | حذف‌کننده |
| `delete_reason` | TEXT NULL | دلیل حذف |
| `metadata` | JSON NULL | داده تکمیلی |
| `created_at` | DATETIME NULL | زمان ایجاد |
| `updated_at` | DATETIME NULL | زمان بروزرسانی |

---

### sender_typeهای پیشنهادی

```text
user
customer
bot
system
external
```

---

### message_typeهای پیشنهادی

```text
text
file
image
system_event
payment_notice
installment_reminder
legal_warning
bot_reply
template
other
```

---

### body_formatهای پیشنهادی

```text
plain_text
markdown
html_sanitized
template
```

---

### statusهای پیشنهادی

```text
draft
sent
delivered
read
failed
deleted
archived
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

- `message_number` باید Unique باشد.
- هر پیام باید thread_id داشته باشد.
- پیام باید sender_type داشته باشد.
- اگر sender_type برابر user است، sender_user_id الزامی است.
- اگر sender_type برابر customer است، sender_customer_id الزامی است.
- پیام داخلی نباید برای مشتری نمایش داده شود.
- پیام حقوقی فقط برای نقش مجاز نمایش داده شود.
- پیام حذف‌شده باید Soft Delete شود.
- متن پیام نباید شامل HTML خطرناک باشد.
- اگر body_format برابر html_sanitized است، پاکسازی HTML الزامی است.
- پیام‌های حساس نباید بدون Permission در Export بیایند.
- ویرایش پیام حساس باید Audit Log داشته باشد.
- حذف پیام حساس باید Audit Log داشته باشد.

---

### Indexهای پیشنهادی

```text
uniq_chat_messages_message_number
idx_chat_messages_thread_id
idx_chat_messages_parent_message_id
idx_chat_messages_sender_type
idx_chat_messages_sender_user_id
idx_chat_messages_sender_customer_id
idx_chat_messages_message_type
idx_chat_messages_status
idx_chat_messages_visibility
idx_chat_messages_is_internal
idx_chat_messages_sent_at
idx_chat_messages_created_at
idx_chat_messages_deleted_at
```

---

## جدول chat_message_attachments

### هدف جدول

جدول `chat_message_attachments` فایل‌های پیوست پیام‌ها را نگهداری می‌کند.

فایل واقعی باید در Files Domain ذخیره شود.

---

### نام جدول

```text
chat_message_attachments
```

---

### ستون‌های پیشنهادی

| ستون | نوع | توضیح |
|---|---|---|
| `id` | BIGINT UNSIGNED AUTO_INCREMENT | شناسه |
| `message_id` | BIGINT UNSIGNED | پیام |
| `thread_id` | BIGINT UNSIGNED | گفتگو |
| `file_id` | BIGINT UNSIGNED | فایل |
| `attachment_type` | VARCHAR(50) | نوع پیوست |
| `title` | VARCHAR(191) NULL | عنوان |
| `description` | TEXT NULL | توضیح |
| `is_sensitive` | TINYINT(1) | حساس بودن |
| `status` | VARCHAR(50) | وضعیت |
| `uploaded_by` | BIGINT UNSIGNED NULL | آپلودکننده |
| `uploaded_at` | DATETIME NULL | زمان آپلود |
| `deleted_at` | DATETIME NULL | زمان حذف نرم |
| `deleted_by` | BIGINT UNSIGNED NULL | حذف‌کننده |
| `metadata` | JSON NULL | داده تکمیلی |
| `created_at` | DATETIME NULL | زمان ایجاد |
| `updated_at` | DATETIME NULL | زمان بروزرسانی |

---

### attachment_typeهای پیشنهادی

```text
image
document
receipt
contract_file
legal_file
audio
other
```

---

### statusهای پیشنهادی

```text
active
deleted
blocked
archived
```

---

### قوانین

- هر Attachment باید message_id داشته باشد.
- هر Attachment باید file_id معتبر داشته باشد.
- فایل حساس باید در Private Storage باشد.
- دانلود پیوست حساس باید Audit Log داشته باشد.
- پیوست حقوقی باید Permission حقوقی داشته باشد.
- حذف پیوست باید Soft Delete باشد.
- مسیر واقعی فایل نباید در UI نمایش داده شود.
- فایل پیام نباید مستقیماً از Public Path قابل دسترسی باشد، مگر اگر Policy اجازه دهد.

---

### Indexهای پیشنهادی

```text
idx_chat_message_attachments_message_id
idx_chat_message_attachments_thread_id
idx_chat_message_attachments_file_id
idx_chat_message_attachments_attachment_type
idx_chat_message_attachments_is_sensitive
idx_chat_message_attachments_status
idx_chat_message_attachments_uploaded_by
idx_chat_message_attachments_uploaded_at
idx_chat_message_attachments_deleted_at
```

---

## جدول chat_message_reads

### هدف جدول

جدول `chat_message_reads` وضعیت خوانده‌شدن پیام توسط شرکت‌کنندگان را ذخیره می‌کند.

این جدول برای نمایش پیام‌های خوانده‌شده، تعداد پیام‌های خوانده‌نشده و پیگیری گفتگوها کاربرد دارد.

---

### نام جدول

```text
chat_message_reads
```

---

### ستون‌های پیشنهادی

| ستون | نوع | توضیح |
|---|---|---|
| `id` | BIGINT UNSIGNED AUTO_INCREMENT | شناسه |
| `thread_id` | BIGINT UNSIGNED | گفتگو |
| `message_id` | BIGINT UNSIGNED | پیام |
| `participant_id` | BIGINT UNSIGNED NULL | شرکت‌کننده |
| `reader_type` | VARCHAR(50) | نوع خواننده |
| `reader_user_id` | BIGINT UNSIGNED NULL | کاربر خواننده |
| `reader_customer_id` | BIGINT UNSIGNED NULL | مشتری خواننده |
| `read_at` | DATETIME | زمان خوانده‌شدن |
| `ip_address` | VARCHAR(45) NULL | IP |
| `user_agent` | TEXT NULL | User Agent |
| `created_at` | DATETIME NULL | زمان ایجاد |

---

### reader_typeهای پیشنهادی

```text
user
customer
bot
system
```

---

### قوانین

- هر Read باید thread_id و message_id داشته باشد.
- برای هر reader و message فقط یک رکورد read معتبر وجود داشته باشد.
- پیام داخلی نباید برای مشتری read ثبت کند.
- خواندن پیام حساس باید Scope را رعایت کند.
- این جدول معمولاً Soft Delete ندارد.
- برای عملکرد بهتر می‌توان last_read در chat_participants را هم نگهداری کرد.

---

### Indexهای پیشنهادی

```text
uniq_chat_message_reads_message_reader
idx_chat_message_reads_thread_id
idx_chat_message_reads_message_id
idx_chat_message_reads_participant_id
idx_chat_message_reads_reader_type
idx_chat_message_reads_reader_user_id
idx_chat_message_reads_reader_customer_id
idx_chat_message_reads_read_at
```

---

## جدول chat_message_status_histories

### هدف جدول

جدول `chat_message_status_histories` تاریخچه تغییر وضعیت پیام را نگهداری می‌کند.

این جدول برای پیام‌های حساس، ارسال خارجی و بررسی خطا مفید است.

---

### نام جدول

```text
chat_message_status_histories
```

---

### ستون‌های پیشنهادی

| ستون | نوع | توضیح |
|---|---|---|
| `id` | BIGINT UNSIGNED AUTO_INCREMENT | شناسه |
| `message_id` | BIGINT UNSIGNED | پیام |
| `thread_id` | BIGINT UNSIGNED | گفتگو |
| `old_status` | VARCHAR(50) NULL | وضعیت قبلی |
| `new_status` | VARCHAR(50) | وضعیت جدید |
| `reason` | TEXT NULL | دلیل تغییر |
| `changed_by` | BIGINT UNSIGNED NULL | تغییر دهنده |
| `changed_at` | DATETIME | زمان تغییر |
| `metadata` | JSON NULL | داده تکمیلی |

---

### قوانین

- تغییر وضعیت مهم پیام باید history داشته باشد.
- failed شدن پیام خارجی باید history داشته باشد.
- deleted شدن پیام حساس باید history داشته باشد.
- این جدول نباید Soft Delete شود.
- مشاهده history پیام باید Permission داشته باشد.

---

### Indexهای پیشنهادی

```text
idx_chat_message_status_histories_message_id
idx_chat_message_status_histories_thread_id
idx_chat_message_status_histories_new_status
idx_chat_message_status_histories_changed_by
idx_chat_message_status_histories_changed_at
```

---

## جدول bot_conversations

### هدف جدول

جدول `bot_conversations` نشست‌های گفتگو با بات را نگهداری می‌کند.

بات می‌تواند برای پاسخ‌گویی، راهنمایی مشتری، پیگیری اقساط، ثبت درخواست یا تولید پیام استفاده شود.

---

### نام جدول

```text
bot_conversations
```

---

### ستون‌های پیشنهادی

| ستون | نوع | توضیح |
|---|---|---|
| `id` | BIGINT UNSIGNED AUTO_INCREMENT | شناسه |
| `bot_conversation_number` | VARCHAR(50) | شماره رسمی مکالمه بات |
| `thread_id` | BIGINT UNSIGNED NULL | Thread مرتبط |
| `customer_id` | BIGINT UNSIGNED NULL | مشتری |
| `user_id` | BIGINT UNSIGNED NULL | کاربر داخلی |
| `bot_key` | VARCHAR(100) | کلید بات |
| `conversation_type` | VARCHAR(50) | نوع مکالمه |
| `status` | VARCHAR(50) | وضعیت |
| `started_at` | DATETIME NULL | زمان شروع |
| `ended_at` | DATETIME NULL | زمان پایان |
| `last_message_at` | DATETIME NULL | زمان آخرین پیام |
| `handoff_to_user_id` | BIGINT UNSIGNED NULL | ارجاع به کاربر انسانی |
| `handoff_at` | DATETIME NULL | زمان ارجاع |
| `summary` | TEXT NULL | خلاصه مکالمه |
| `metadata` | JSON NULL | داده تکمیلی |
| `created_at` | DATETIME NULL | زمان ایجاد |
| `updated_at` | DATETIME NULL | زمان بروزرسانی |

---

### conversation_typeهای پیشنهادی

```text
customer_support
installment_followup
payment_help
contract_help
legal_help
internal_assistant
system
other
```

---

### statusهای پیشنهادی

```text
active
waiting_user
waiting_bot
handoff
closed
failed
cancelled
```

---

### قوانین

- `bot_conversation_number` باید Unique باشد.
- مکالمه بات با مشتری باید customer_id داشته باشد.
- اگر بات در Thread اصلی کار می‌کند، thread_id ثبت شود.
- ارجاع به انسان باید handoff_to_user_id و handoff_at داشته باشد.
- بات نباید بدون Permission به اطلاعات حساس دسترسی داشته باشد.
- خروجی بات نباید به صورت قطعی عملیات مالی یا حقوقی انجام دهد، مگر از Serviceهای رسمی و Permission-based استفاده کند.
- مکالمه بات حساس باید Audit یا حداقل Log قابل بررسی داشته باشد.

---

### Indexهای پیشنهادی

```text
uniq_bot_conversations_bot_conversation_number
idx_bot_conversations_thread_id
idx_bot_conversations_customer_id
idx_bot_conversations_user_id
idx_bot_conversations_bot_key
idx_bot_conversations_conversation_type
idx_bot_conversations_status
idx_bot_conversations_started_at
idx_bot_conversations_last_message_at
```

---

## جدول bot_messages

### هدف جدول

جدول `bot_messages` پیام‌های ورودی و خروجی بات را ذخیره می‌کند.

این جدول برای Debug، بررسی کیفیت پاسخ، ردیابی تصمیمات بات و پشتیبانی استفاده می‌شود.

---

### نام جدول

```text
bot_messages
```

---

### ستون‌های پیشنهادی

| ستون | نوع | توضیح |
|---|---|---|
| `id` | BIGINT UNSIGNED AUTO_INCREMENT | شناسه |
| `bot_conversation_id` | BIGINT UNSIGNED | مکالمه بات |
| `thread_id` | BIGINT UNSIGNED NULL | Thread مرتبط |
| `chat_message_id` | BIGINT UNSIGNED NULL | پیام Chat مرتبط |
| `direction` | VARCHAR(20) | جهت پیام |
| `sender_type` | VARCHAR(50) | نوع ارسال‌کننده |
| `message_type` | VARCHAR(50) | نوع پیام |
| `body` | TEXT NULL | متن پیام |
| `input_payload` | JSON NULL | ورودی بات |
| `output_payload` | JSON NULL | خروجی بات |
| `status` | VARCHAR(50) | وضعیت |
| `error_message` | TEXT NULL | پیام خطا |
| `tokens_used` | INT UNSIGNED NULL | مصرف توکن در صورت نیاز |
| `model_name` | VARCHAR(100) NULL | مدل استفاده‌شده در صورت نیاز |
| `created_at` | DATETIME NULL | زمان ایجاد |
| `processed_at` | DATETIME NULL | زمان پردازش |
| `metadata` | JSON NULL | داده تکمیلی |

---

### directionهای پیشنهادی

```text
inbound
outbound
system
```

---

### sender_typeهای پیشنهادی

```text
customer
user
bot
system
```

---

### statusهای پیشنهادی

```text
received
processed
sent
failed
ignored
blocked
```

---

### قوانین

- هر bot_message باید bot_conversation_id داشته باشد.
- داده حساس غیرضروری نباید در input_payload یا output_payload ذخیره شود.
- خروجی بات باید قبل از اقدام حساس توسط Service رسمی بررسی شود.
- خطاهای بات باید Log شوند.
- اگر پیام بات در Chat Thread نمایش داده می‌شود، chat_message_id ثبت شود.
- پیام بات نباید بدون Permission اطلاعات حقوقی یا مالی را تولید کند.
- اطلاعات مدل یا توکن در صورت نیاز فقط برای Admin قابل نمایش باشد.

---

### Indexهای پیشنهادی

```text
idx_bot_messages_bot_conversation_id
idx_bot_messages_thread_id
idx_bot_messages_chat_message_id
idx_bot_messages_direction
idx_bot_messages_sender_type
idx_bot_messages_message_type
idx_bot_messages_status
idx_bot_messages_created_at
idx_bot_messages_processed_at
```

---

## جدول external_message_channels

### هدف جدول

جدول `external_message_channels` کانال‌های خارجی ارسال پیام را نگهداری می‌کند.

این کانال‌ها می‌توانند برای ارسال پیام از طریق SMS، Telegram، WhatsApp یا Providerهای دیگر استفاده شوند.

---

### نام جدول

```text
external_message_channels
```

---

### ستون‌های پیشنهادی

| ستون | نوع | توضیح |
|---|---|---|
| `id` | BIGINT UNSIGNED AUTO_INCREMENT | شناسه |
| `channel_key` | VARCHAR(100) | کلید کانال |
| `name` | VARCHAR(191) | نام نمایشی |
| `channel_type` | VARCHAR(50) | نوع کانال |
| `provider_key` | VARCHAR(100) NULL | کلید Provider |
| `is_active` | TINYINT(1) | فعال بودن |
| `requires_template` | TINYINT(1) | نیاز به Template |
| `supports_attachments` | TINYINT(1) | پشتیبانی از پیوست |
| `sort_order` | INT UNSIGNED | ترتیب |
| `settings` | JSON NULL | تنظیمات غیرحساس |
| `created_at` | DATETIME NULL | زمان ایجاد |
| `updated_at` | DATETIME NULL | زمان بروزرسانی |

---

### channel_typeهای پیشنهادی

```text
sms
telegram
whatsapp
email
internal
bot
other
```

---

### قوانین

- `channel_key` باید Unique باشد.
- تنظیمات حساس مثل API Key نباید خام در settings ذخیره شود.
- کلیدهای حساس باید در Settings امن یا encrypted ذخیره شوند.
- تغییر وضعیت کانال باید Audit Log داشته باشد.
- کانال پلاگینی باید namespace مشخص داشته باشد.
- کانال غیرفعال نباید پیام جدید ارسال کند.

---

### Indexهای پیشنهادی

```text
uniq_external_message_channels_channel_key
idx_external_message_channels_channel_type
idx_external_message_channels_provider_key
idx_external_message_channels_is_active
idx_external_message_channels_sort_order
```

---

## جدول external_message_logs

### هدف جدول

جدول `external_message_logs` نتیجه ارسال پیام به کانال‌های خارجی را نگهداری می‌کند.

این جدول برای بررسی ارسال SMS، پیام Telegram، پیام WhatsApp، خطاهای Provider و وضعیت تحویل استفاده می‌شود.

---

### نام جدول

```text
external_message_logs
```

---

### ستون‌های پیشنهادی

| ستون | نوع | توضیح |
|---|---|---|
| `id` | BIGINT UNSIGNED AUTO_INCREMENT | شناسه |
| `external_message_number` | VARCHAR(50) | شماره رسمی پیام خارجی |
| `channel_id` | BIGINT UNSIGNED NULL | کانال |
| `channel_key` | VARCHAR(100) | کلید کانال |
| `provider_key` | VARCHAR(100) NULL | Provider |
| `chat_message_id` | BIGINT UNSIGNED NULL | پیام Chat مرتبط |
| `notification_id` | BIGINT UNSIGNED NULL | Notification مرتبط |
| `customer_id` | BIGINT UNSIGNED NULL | مشتری |
| `user_id` | BIGINT UNSIGNED NULL | کاربر داخلی |
| `recipient` | VARCHAR(191) | گیرنده |
| `recipient_masked` | VARCHAR(191) NULL | گیرنده ماسک‌شده |
| `message_type` | VARCHAR(50) | نوع پیام |
| `template_key` | VARCHAR(100) NULL | کلید Template |
| `body` | TEXT NULL | متن ارسالی |
| `status` | VARCHAR(50) | وضعیت ارسال |
| `provider_message_id` | VARCHAR(191) NULL | شناسه پیام در Provider |
| `provider_status` | VARCHAR(100) NULL | وضعیت Provider |
| `sent_at` | DATETIME NULL | زمان ارسال |
| `delivered_at` | DATETIME NULL | زمان تحویل |
| `failed_at` | DATETIME NULL | زمان شکست |
| `error_code` | VARCHAR(100) NULL | کد خطا |
| `error_message` | TEXT NULL | پیام خطا |
| `request_payload` | JSON NULL | داده درخواست |
| `response_payload` | JSON NULL | داده پاسخ |
| `metadata` | JSON NULL | داده تکمیلی |
| `created_at` | DATETIME NULL | زمان ایجاد |
| `updated_at` | DATETIME NULL | زمان بروزرسانی |

---

### message_typeهای پیشنهادی

```text
installment_reminder
payment_confirmation
payment_rejection
overdue_warning
legal_warning
settlement_notice
system_alert
manual_message
other
```

---

### statusهای پیشنهادی

```text
queued
sent
delivered
failed
cancelled
blocked
expired
unknown
```

---

### قوانین

- `external_message_number` باید Unique باشد.
- recipient بهتر است Mask شود.
- اطلاعات حساس گیرنده نباید بی‌دلیل در Log نمایش داده شود.
- پیام مالی یا حقوقی باید Permission و Template معتبر داشته باشد.
- خطاهای Provider باید ثبت شوند.
- Provider API Key نباید در payloadها ذخیره شود.
- ارسال پیام خارجی نباید بدون Rate Limit انجام شود.
- پیام failed می‌تواند Retry شود.
- Retry باید تعداد تلاش یا metadata مناسب داشته باشد.
- پیام‌های حقوقی حساس باید Audit یا حداقل Log قابل بررسی داشته باشند.

---

### Indexهای پیشنهادی

```text
uniq_external_message_logs_external_message_number
idx_external_message_logs_channel_id
idx_external_message_logs_channel_key
idx_external_message_logs_provider_key
idx_external_message_logs_chat_message_id
idx_external_message_logs_notification_id
idx_external_message_logs_customer_id
idx_external_message_logs_user_id
idx_external_message_logs_recipient
idx_external_message_logs_message_type
idx_external_message_logs_status
idx_external_message_logs_provider_message_id
idx_external_message_logs_sent_at
idx_external_message_logs_delivered_at
idx_external_message_logs_failed_at
```

---

## رابطه Chat Domain با سایر Domainها

Chat Domain با بخش‌های زیادی از سیستم ارتباط دارد.

| Domain | رابطه |
|---|---|
| Customers | گفتگو با مشتری و پیام‌های پشتیبانی |
| Contracts | پیام‌های مرتبط با قرارداد |
| Installments | یادآوری و پیگیری اقساط |
| Payments | پیام‌های تأیید یا رد پرداخت |
| Legal | پیام‌ها و هشدارهای حقوقی |
| Files | فایل‌های پیوست پیام در Files Domain ذخیره می‌شوند |
| Notifications | برخی پیام‌ها می‌توانند Notification ایجاد کنند |
| Calendar | پیام‌ها می‌توانند پیگیری یا Reminder بسازند |
| Audit | پیام‌های حساس و حذف/ویرایش آن‌ها Audit می‌شوند |
| Security | تلاش غیرمجاز برای مشاهده پیام‌ها ثبت می‌شود |
| Plugins | Providerهای پیام‌رسان می‌توانند به صورت پلاگین اضافه شوند |

---

## قوانین پیام‌های داخلی

قوانین:

- پیام داخلی برای مشتری نمایش داده نشود.
- پیام داخلی باید visibility مناسب داشته باشد.
- پیام داخلی مرتبط با پرونده حقوقی فقط برای نقش مجاز نمایش داده شود.
- حذف یا ویرایش پیام داخلی حساس باید Audit Log داشته باشد.
- Export پیام‌های داخلی باید Permission جدا داشته باشد.
- پیام داخلی نباید از طریق کانال خارجی برای مشتری ارسال شود، مگر کاربر مجاز آن را تبدیل به پیام رسمی کند.

---

## قوانین پیام مشتری

قوانین:

- مشتری فقط Threadهای مربوط به خودش را ببیند.
- مشتری نباید پیام‌های داخلی را ببیند.
- مشتری نباید Thread مشتری دیگر را ببیند.
- پیام مشتری باید customer_id و Scope معتبر داشته باشد.
- فایل ارسالی مشتری باید Validate شود.
- پیام مشتری می‌تواند باعث Notification برای اپراتور شود.
- پیام مشتری در موضوع مالی یا حقوقی ممکن است نیازمند بررسی نقش مجاز باشد.

---

## قوانین Bot

قوانین:

- بات نباید بدون Permission به داده‌های حساس دسترسی داشته باشد.
- بات نباید مستقیماً عملیات مالی قطعی انجام دهد، مگر از Service رسمی استفاده کند.
- پاسخ بات در موضوع حقوقی نباید جایگزین نظر وکیل یا کاربر مجاز شود.
- مکالمات بات باید قابل ردیابی باشند.
- داده حساس غیرضروری نباید در payload بات ذخیره شود.
- اگر مکالمه به انسان ارجاع شد، handoff ثبت شود.
- خروجی بات در عملیات مهم باید قابل Review باشد.

---

## قوانین فایل‌های پیوست گفتگو

قوانین:

- فایل پیوست باید در Files Domain ذخیره شود.
- فایل حساس باید Private باشد.
- دانلود فایل حساس باید Audit Log داشته باشد.
- فایل حقوقی باید Permission حقوقی داشته باشد.
- فایل رسید پرداخت باید Permission مالی داشته باشد.
- مسیر واقعی فایل نباید در UI نمایش داده شود.
- فایل حذف‌شده نباید از لینک قبلی قابل دانلود باشد.
- فایل‌های خطرناک یا غیرمجاز باید Block شوند.

---

## قوانین وضعیت پیام

Transitionهای پیشنهادی:

```text
draft -> sent
sent -> delivered
delivered -> read
sent -> failed
failed -> sent
sent -> deleted
delivered -> deleted
read -> deleted
```

قوانین:

- پیام failed باید error یا reason داشته باشد.
- پیام deleted باید deleted_by و delete_reason داشته باشد، اگر حساس باشد.
- پیام read برای هر خواننده جداگانه ثبت می‌شود.
- وضعیت delivered برای پیام داخلی ممکن است اختیاری باشد.
- پیام خارجی باید external_message_log داشته باشد.

---

## قوانین Soft Delete

جدول‌های زیر باید Soft Delete داشته باشند:

- `chat_threads`
- `chat_messages`
- `chat_message_attachments`

قوانین:

- Thread دارای پیام نباید فیزیکی حذف شود.
- پیام حذف‌شده باید برای کاربران عادی مخفی شود.
- پیام حذف‌شده حساس باید در Audit قابل ردیابی باشد.
- پیوست حذف‌شده نباید دانلود شود.
- message_reads و status_histories نباید Soft Delete شوند.
- پیام‌های حقوقی و مالی حساس نباید فیزیکی حذف شوند.

---

## قوانین Index و Performance

قوانین:

- نمایش لیست گفتگوها باید بر اساس last_message_at سریع باشد.
- پیام‌های یک Thread باید با thread_id و created_at سریع خوانده شوند.
- شمارش پیام‌های خوانده‌نشده باید بهینه باشد.
- پیام‌های قدیمی باید Pagination داشته باشند.
- جستجوی پیام باید محدود، Permission-based و در صورت نیاز Fulltext باشد.
- external_message_logs ممکن است بزرگ شود و نیازمند Archive باشد.
- Export گفتگوها باید محدود و Permission-based باشد.

Indexهای مهم:

```text
chat_threads.customer_id
chat_threads.contract_id
chat_threads.status
chat_threads.last_message_at
chat_messages.thread_id
chat_messages.sender_user_id
chat_messages.sender_customer_id
chat_messages.created_at
chat_message_reads.message_id
chat_message_reads.reader_user_id
external_message_logs.customer_id
external_message_logs.status
external_message_logs.sent_at
```

---

## قوانین Validation

### chat_threads

- thread_number الزامی و یکتا است.
- thread_type الزامی و معتبر است.
- status معتبر باشد.
- visibility معتبر باشد.
- Thread مربوط به مشتری باید customer_id داشته باشد.
- Thread مربوط به پرونده حقوقی باید legal_case_id یا contract_id داشته باشد.

### chat_participants

- thread_id الزامی است.
- participant_type الزامی و معتبر است.
- participant_type برابر user باید user_id داشته باشد.
- participant_type برابر customer باید customer_id داشته باشد.
- role_in_thread معتبر باشد.

### chat_messages

- message_number الزامی و یکتا است.
- thread_id الزامی است.
- sender_type الزامی و معتبر است.
- message_type الزامی و معتبر است.
- پیام text باید body داشته باشد.
- visibility معتبر باشد.
- پیام حذف‌شده حساس باید delete_reason داشته باشد.

### chat_message_attachments

- message_id الزامی است.
- file_id الزامی است.
- attachment_type معتبر باشد.
- فایل حساس باید is_sensitive داشته باشد.

### bot_conversations

- bot_conversation_number الزامی و یکتا است.
- bot_key الزامی است.
- status معتبر باشد.
- conversation_type معتبر باشد.

### external_message_logs

- external_message_number الزامی و یکتا است.
- channel_key الزامی است.
- recipient الزامی است.
- message_type معتبر باشد.
- status معتبر باشد.
- failed status باید error_message یا error_code داشته باشد.

---

## قوانین Audit و Security Log

### Audit Log الزامی برای:

- ایجاد Thread حقوقی یا مالی حساس
- افزودن شرکت‌کننده به Thread حساس
- حذف شرکت‌کننده از Thread حساس
- ویرایش پیام حساس
- حذف پیام حساس
- دانلود پیوست حساس
- حذف پیوست حساس
- ارسال پیام حقوقی
- ارسال پیام مالی مهم
- Export گفتگوها
- تغییر تنظیمات کانال پیام‌رسان
- فعال یا غیرفعال کردن کانال خارجی

### Security Log الزامی برای:

- تلاش مشاهده Thread بدون Permission
- تلاش مشاهده Thread خارج از Scope
- تلاش مشاهده پیام داخلی توسط مشتری
- تلاش دانلود پیوست بدون Permission
- تلاش ارسال پیام به جای کاربر دیگر
- تلاش دستکاری thread_id یا message_id
- تلاش ارسال پیام خارجی بدون Permission
- CSRF نامعتبر در ارسال یا حذف پیام
- ارسال پیام با محتوای خطرناک یا فایل غیرمجاز

---

## Seedهای پیشنهادی

### thread_type

```text
internal
customer_support
contract
installment
payment
legal
bot
system
other
```

### message_type

```text
text
file
image
system_event
payment_notice
installment_reminder
legal_warning
bot_reply
template
other
```

### message_status

```text
draft
sent
delivered
read
failed
deleted
archived
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
sms
telegram
whatsapp
email
internal
bot
other
```

### external_message_status

```text
queued
sent
delivered
failed
cancelled
blocked
expired
unknown
```

---

## چک‌لیست پیاده‌سازی

قبل از پیاده‌سازی Chat Tables بررسی شود:

- [ ] جدول `chat_threads` ساخته شده است.
- [ ] `thread_number` یکتا است.
- [ ] Threadها به customer، contract، installment، payment یا legal_case قابل اتصال هستند.
- [ ] جدول `chat_participants` وجود دارد.
- [ ] شرکت‌کنندگان بر اساس user یا customer قابل ثبت هستند.
- [ ] جدول `chat_messages` وجود دارد.
- [ ] `message_number` یکتا است.
- [ ] پیام داخلی از مشتری مخفی می‌شود.
- [ ] جدول `chat_message_attachments` وجود دارد.
- [ ] فایل‌های پیوست در Files Domain مدیریت می‌شوند.
- [ ] جدول `chat_message_reads` وجود دارد.
- [ ] جدول `chat_message_status_histories` وجود دارد.
- [ ] جدول `bot_conversations` وجود دارد.
- [ ] جدول `bot_messages` وجود دارد.
- [ ] جدول `external_message_channels` وجود دارد.
- [ ] جدول `external_message_logs` وجود دارد.
- [ ] پیام‌ها Scope و Permission را رعایت می‌کنند.
- [ ] پیام‌ها و پیوست‌های حساس Audit Log دارند.
- [ ] تلاش‌های غیرمجاز Security Log دارند.
- [ ] Export گفتگوها Permission-based است.

---

## Definition of Done

Chat Tables زمانی کامل هستند که:

- گفتگوها با شماره رسمی یکتا قابل ثبت باشند.
- گفتگوها بتوانند به مشتری، قرارداد، قسط، پرداخت و پرونده حقوقی وصل شوند.
- شرکت‌کنندگان گفتگو قابل مدیریت باشند.
- پیام‌ها با وضعیت، ارسال‌کننده، نوع پیام و سطح نمایش ذخیره شوند.
- پیام‌های داخلی برای مشتری نمایش داده نشوند.
- پیام‌های حقوقی و مالی فقط برای نقش مجاز نمایش داده شوند.
- فایل‌های پیوست پیام در Files Domain و Private Storage مدیریت شوند.
- خوانده‌شدن پیام‌ها قابل ثبت باشد.
- تاریخچه وضعیت پیام‌های مهم قابل ردیابی باشد.
- مکالمات بات و پیام‌های بات قابل ثبت و بررسی باشند.
- کانال‌های خارجی ارسال پیام قابل تعریف باشند.
- لاگ ارسال پیام خارجی قابل بررسی، Retry و گزارش‌گیری باشد.
- اطلاعات حساس بدون Permission نمایش یا Export نشود.
- عملیات حساس Audit Log داشته باشند.
- تلاش‌های غیرمجاز Security Log داشته باشند.
- جدول‌ها با MySQL/MariaDB و PHP 7.4+ سازگار باشند.
- Codex بتواند از روی این مستندات Migrationهای Chat Domain را بسازد.

---

## پایان فایل
````
