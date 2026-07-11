# 12 — Calendar Tables

مستند جدول‌های تقویم، رویدادها، یادآوری‌ها، زمان‌بندی‌ها، تکرار رویدادها، دعوت‌شدگان و داده‌های وابسته به Calendar Domain در پروژه **Proma Pay**

---

## فهرست مطالب

- [هدف فایل](#هدف-فایل)
- [تعریف Calendar Domain در دیتابیس](#تعریف-calendar-domain-در-دیتابیس)
- [اصل مهم](#اصل-مهم)
- [لیست جدول‌های Calendar](#لیست-جدولهای-calendar)
- [جدول calendar_events](#جدول-calendar_events)
- [جدول calendar_event_attendees](#جدول-calendar_event_attendees)
- [جدول calendar_reminders](#جدول-calendar_reminders)
- [جدول calendar_recurring_rules](#جدول-calendar_recurring_rules)
- [جدول calendar_event_status_histories](#جدول-calendar_event_status_histories)
- [جدول calendar_tasks](#جدول-calendar_tasks)
- [جدول calendar_task_comments](#جدول-calendar_task_comments)
- [جدول calendar_task_status_histories](#جدول-calendar_task_status_histories)
- [رابطه Calendar Domain با سایر Domainها](#رابطه-calendar-domain-با-سایر-domainها)
- [قوانین رویدادها](#قوانین-رویدادها)
- [قوانین یادآوری‌ها](#قوانین-یادآوریها)
- [قوانین تسک‌ها](#قوانین-تسکها)
- [قوانین رویدادهای تکرارشونده](#قوانین-رویدادهای-تکرارشونده)
- [قوانین Soft Delete](#قوانین-soft-delete)
- [قوانین Index و Performance](#قوانین-index-و-performance)
- [قوانین Validation](#قوانین-validation)
- [قوانین Audit و Security Log](#قوانین-audit-و-security-log)
- [Seedهای پیشنهادی](#seedهای-پیشنهادی)
- [چک‌لیست پیاده‌سازی](#چکلیست-پیادهسازی)
- [Definition of Done](#definition-of-done)

---

## هدف فایل

هدف این فایل این است که ساختار جدول‌های مربوط به تقویم، رویدادها، یادآوری‌ها و کارهای زمان‌بندی‌شده در پروژه **Proma Pay** مشخص شود.

Calendar Domain برای مدیریت موارد زیر استفاده می‌شود:

- یادآوری سررسید اقساط
- پیگیری معوقات
- تماس با مشتری
- وعده پرداخت
- جلسه حقوقی
- مهلت پرونده حقوقی
- یادآوری بکاپ
- یادآوری تسک‌های داخلی
- رویدادهای مدیریتی
- تسک‌های اپراتورها، حسابدارها و وکیل

این فایل برای Codex مشخص می‌کند که:

- رویدادهای تقویم چگونه ذخیره شوند.
- یادآوری‌ها چگونه ساخته شوند.
- تسک‌ها چگونه تعریف و پیگیری شوند.
- رویدادهای تکرارشونده چگونه مدیریت شوند.
- رویدادها چگونه به مشتری، قرارداد، قسط، پرداخت یا پرونده حقوقی وصل شوند.
- تغییر وضعیت رویداد و تسک چگونه ثبت شود.
- چه رویدادهایی حساس هستند و چه Permissionهایی لازم دارند.

---

## تعریف Calendar Domain در دیتابیس

Calendar Domain مسئول نگهداری زمان‌بندی‌ها و برنامه‌های قابل پیگیری سیستم است.

این Domain می‌تواند شامل موارد زیر باشد:

- Event
- Reminder
- Task
- Deadline
- Follow-up
- Recurring Schedule

Calendar Domain نباید جایگزین Workflow اصلی سیستم شود.

مثلاً:

- تقویم فقط یادآوری پرداخت ایجاد می‌کند.
- پرداخت واقعی باید در Payment Domain ثبت شود.
- یادآوری حقوقی فقط زمان پیگیری را نشان می‌دهد.
- اقدام حقوقی واقعی باید در Legal Domain ثبت شود.

---

## اصل مهم

اصل مهم در Calendar Tables:

> تقویم و یادآوری فقط ابزار زمان‌بندی و پیگیری هستند؛ نه منبع حقیقت مالی، حقوقی یا عملیاتی.

بنابراین:

- سررسید قسط از Installment Domain می‌آید.
- مهلت حقوقی از Legal Domain می‌آید.
- وعده پرداخت از Installment/Payment Promise می‌آید.
- تسک اپراتور می‌تواند در Calendar نمایش داده شود، اما تغییر مالی انجام نمی‌دهد.
- Reminder نباید بدون Service رسمی وضعیت قرارداد یا قسط را تغییر دهد.

---

## لیست جدول‌های Calendar

جدول‌های پیشنهادی Calendar Domain:

| جدول | کاربرد |
|---|---|
| `calendar_events` | رویدادهای تقویم |
| `calendar_event_attendees` | شرکت‌کنندگان یا مسئولان رویداد |
| `calendar_reminders` | یادآوری‌های رویداد یا تسک |
| `calendar_recurring_rules` | قوانین تکرار رویدادها |
| `calendar_event_status_histories` | تاریخچه تغییر وضعیت رویداد |
| `calendar_tasks` | تسک‌های داخلی و پیگیری‌ها |
| `calendar_task_comments` | کامنت‌ها و یادداشت‌های تسک |
| `calendar_task_status_histories` | تاریخچه تغییر وضعیت تسک |

---

## جدول calendar_events

### هدف جدول

جدول `calendar_events` اطلاعات اصلی رویدادهای تقویم را نگهداری می‌کند.

هر رویداد می‌تواند به مشتری، قرارداد، قسط، پرداخت یا پرونده حقوقی مرتبط باشد.

---

### نام جدول

```text
calendar_events
```

---

### ستون‌های پیشنهادی

| ستون | نوع | توضیح |
|---|---|---|
| `id` | BIGINT UNSIGNED AUTO_INCREMENT | شناسه داخلی |
| `event_number` | VARCHAR(50) | شماره رسمی رویداد |
| `event_type` | VARCHAR(50) | نوع رویداد |
| `title` | VARCHAR(191) | عنوان رویداد |
| `description` | TEXT NULL | توضیحات |
| `start_at` | DATETIME | زمان شروع |
| `end_at` | DATETIME NULL | زمان پایان |
| `all_day` | TINYINT(1) | تمام‌روزه بودن |
| `timezone` | VARCHAR(100) NULL | منطقه زمانی |
| `status` | VARCHAR(50) | وضعیت رویداد |
| `priority` | VARCHAR(50) | اولویت |
| `visibility` | VARCHAR(50) | سطح نمایش |
| `is_sensitive` | TINYINT(1) | حساس بودن |
| `is_recurring` | TINYINT(1) | تکرارشونده بودن |
| `recurring_rule_id` | BIGINT UNSIGNED NULL | قانون تکرار |
| `customer_id` | BIGINT UNSIGNED NULL | مشتری مرتبط |
| `contract_id` | BIGINT UNSIGNED NULL | قرارداد مرتبط |
| `installment_id` | BIGINT UNSIGNED NULL | قسط مرتبط |
| `payment_id` | BIGINT UNSIGNED NULL | پرداخت مرتبط |
| `legal_case_id` | BIGINT UNSIGNED NULL | پرونده حقوقی مرتبط |
| `related_type` | VARCHAR(100) NULL | نوع موجودیت مرتبط |
| `related_id` | BIGINT UNSIGNED NULL | شناسه موجودیت مرتبط |
| `assigned_user_id` | BIGINT UNSIGNED NULL | مسئول اصلی |
| `completed_at` | DATETIME NULL | زمان تکمیل |
| `completed_by` | BIGINT UNSIGNED NULL | تکمیل‌کننده |
| `cancelled_at` | DATETIME NULL | زمان لغو |
| `cancelled_by` | BIGINT UNSIGNED NULL | لغوکننده |
| `cancellation_reason` | TEXT NULL | دلیل لغو |
| `metadata` | JSON NULL | داده تکمیلی |
| `created_at` | DATETIME NULL | زمان ایجاد |
| `updated_at` | DATETIME NULL | زمان بروزرسانی |
| `deleted_at` | DATETIME NULL | زمان حذف نرم |
| `created_by` | BIGINT UNSIGNED NULL | ایجادکننده |
| `updated_by` | BIGINT UNSIGNED NULL | ویرایش‌کننده |
| `deleted_by` | BIGINT UNSIGNED NULL | حذف‌کننده |

---

### event_typeهای پیشنهادی

```text
installment_due
installment_followup
payment_promise
customer_call
legal_deadline
court_session
settlement_meeting
backup_schedule
internal_meeting
manual
system
other
```

---

### statusهای پیشنهادی

```text
scheduled
pending
completed
cancelled
missed
overdue
rescheduled
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

- `event_number` باید Unique باشد.
- هر رویداد باید title و start_at داشته باشد.
- end_at نباید قبل از start_at باشد.
- رویداد مربوط به قسط باید installment_id داشته باشد.
- رویداد مربوط به پرونده حقوقی باید legal_case_id داشته باشد.
- رویداد حقوقی باید visibility مناسب داشته باشد.
- رویداد حساس باید is_sensitive = 1 داشته باشد.
- لغو رویداد مهم باید cancellation_reason داشته باشد.
- حذف رویداد باید Soft Delete باشد.
- رویداد نباید بدون Service رسمی وضعیت مالی یا حقوقی را تغییر دهد.

---

### Indexهای پیشنهادی

```text
uniq_calendar_events_event_number
idx_calendar_events_event_type
idx_calendar_events_start_at
idx_calendar_events_end_at
idx_calendar_events_status
idx_calendar_events_priority
idx_calendar_events_visibility
idx_calendar_events_is_sensitive
idx_calendar_events_is_recurring
idx_calendar_events_customer_id
idx_calendar_events_contract_id
idx_calendar_events_installment_id
idx_calendar_events_payment_id
idx_calendar_events_legal_case_id
idx_calendar_events_related
idx_calendar_events_assigned_user_id
idx_calendar_events_deleted_at
```

---

### نمونه ساختار SQL

```sql
CREATE TABLE calendar_events (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    event_number VARCHAR(50) NOT NULL,
    event_type VARCHAR(50) NOT NULL DEFAULT 'manual',
    title VARCHAR(191) NOT NULL,
    description TEXT NULL,
    start_at DATETIME NOT NULL,
    end_at DATETIME NULL,
    all_day TINYINT(1) NOT NULL DEFAULT 0,
    timezone VARCHAR(100) NULL,
    status VARCHAR(50) NOT NULL DEFAULT 'scheduled',
    priority VARCHAR(50) NOT NULL DEFAULT 'normal',
    visibility VARCHAR(50) NOT NULL DEFAULT 'internal',
    is_sensitive TINYINT(1) NOT NULL DEFAULT 0,
    is_recurring TINYINT(1) NOT NULL DEFAULT 0,
    recurring_rule_id BIGINT UNSIGNED NULL,
    customer_id BIGINT UNSIGNED NULL,
    contract_id BIGINT UNSIGNED NULL,
    installment_id BIGINT UNSIGNED NULL,
    payment_id BIGINT UNSIGNED NULL,
    legal_case_id BIGINT UNSIGNED NULL,
    related_type VARCHAR(100) NULL,
    related_id BIGINT UNSIGNED NULL,
    assigned_user_id BIGINT UNSIGNED NULL,
    completed_at DATETIME NULL,
    completed_by BIGINT UNSIGNED NULL,
    cancelled_at DATETIME NULL,
    cancelled_by BIGINT UNSIGNED NULL,
    cancellation_reason TEXT NULL,
    metadata JSON NULL,
    created_at DATETIME NULL,
    updated_at DATETIME NULL,
    deleted_at DATETIME NULL,
    created_by BIGINT UNSIGNED NULL,
    updated_by BIGINT UNSIGNED NULL,
    deleted_by BIGINT UNSIGNED NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uniq_calendar_events_event_number (event_number),
    KEY idx_calendar_events_event_type (event_type),
    KEY idx_calendar_events_start_at (start_at),
    KEY idx_calendar_events_end_at (end_at),
    KEY idx_calendar_events_status (status),
    KEY idx_calendar_events_priority (priority),
    KEY idx_calendar_events_visibility (visibility),
    KEY idx_calendar_events_is_sensitive (is_sensitive),
    KEY idx_calendar_events_is_recurring (is_recurring),
    KEY idx_calendar_events_customer_id (customer_id),
    KEY idx_calendar_events_contract_id (contract_id),
    KEY idx_calendar_events_installment_id (installment_id),
    KEY idx_calendar_events_payment_id (payment_id),
    KEY idx_calendar_events_legal_case_id (legal_case_id),
    KEY idx_calendar_events_related (related_type, related_id),
    KEY idx_calendar_events_assigned_user_id (assigned_user_id),
    KEY idx_calendar_events_deleted_at (deleted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

---

## جدول calendar_event_attendees

### هدف جدول

جدول `calendar_event_attendees` شرکت‌کنندگان، مسئولان یا افراد مرتبط با رویداد را نگهداری می‌کند.

---

### نام جدول

```text
calendar_event_attendees
```

---

### ستون‌های پیشنهادی

| ستون | نوع | توضیح |
|---|---|---|
| `id` | BIGINT UNSIGNED AUTO_INCREMENT | شناسه |
| `event_id` | BIGINT UNSIGNED | رویداد |
| `attendee_type` | VARCHAR(50) | نوع شرکت‌کننده |
| `user_id` | BIGINT UNSIGNED NULL | کاربر داخلی |
| `customer_id` | BIGINT UNSIGNED NULL | مشتری |
| `name` | VARCHAR(191) NULL | نام نمایشی |
| `email` | VARCHAR(191) NULL | ایمیل |
| `phone` | VARCHAR(30) NULL | موبایل |
| `role` | VARCHAR(50) | نقش در رویداد |
| `response_status` | VARCHAR(50) | وضعیت پاسخ |
| `responded_at` | DATETIME NULL | زمان پاسخ |
| `is_required` | TINYINT(1) | حضور الزامی |
| `created_at` | DATETIME NULL | زمان ایجاد |
| `updated_at` | DATETIME NULL | زمان بروزرسانی |

---

### attendee_typeهای پیشنهادی

```text
user
customer
external
system
```

---

### roleهای پیشنهادی

```text
owner
assignee
participant
viewer
customer
lawyer
accountant
operator
```

---

### response_statusهای پیشنهادی

```text
pending
accepted
declined
tentative
not_required
```

---

### قوانین

- هر Attendee باید event_id داشته باشد.
- اگر attendee_type برابر user است، user_id الزامی است.
- اگر attendee_type برابر customer است، customer_id الزامی است.
- مشتری نباید به رویداد داخلی اضافه شود، مگر visibility اجازه دهد.
- رویداد حقوقی فقط برای نقش مجاز قابل مشاهده باشد.
- تغییر Attendee رویداد حساس باید Audit Log داشته باشد.

---

### Indexهای پیشنهادی

```text
idx_calendar_event_attendees_event_id
idx_calendar_event_attendees_attendee_type
idx_calendar_event_attendees_user_id
idx_calendar_event_attendees_customer_id
idx_calendar_event_attendees_role
idx_calendar_event_attendees_response_status
idx_calendar_event_attendees_is_required
```

---

## جدول calendar_reminders

### هدف جدول

جدول `calendar_reminders` یادآوری‌های مربوط به رویدادها یا تسک‌ها را نگهداری می‌کند.

یک رویداد می‌تواند چند یادآوری داشته باشد.

---

### نام جدول

```text
calendar_reminders
```

---

### ستون‌های پیشنهادی

| ستون | نوع | توضیح |
|---|---|---|
| `id` | BIGINT UNSIGNED AUTO_INCREMENT | شناسه |
| `reminder_number` | VARCHAR(50) | شماره رسمی یادآوری |
| `event_id` | BIGINT UNSIGNED NULL | رویداد مرتبط |
| `task_id` | BIGINT UNSIGNED NULL | تسک مرتبط |
| `reminder_type` | VARCHAR(50) | نوع یادآوری |
| `channel_type` | VARCHAR(50) | کانال یادآوری |
| `recipient_type` | VARCHAR(50) | نوع گیرنده |
| `user_id` | BIGINT UNSIGNED NULL | کاربر داخلی |
| `customer_id` | BIGINT UNSIGNED NULL | مشتری |
| `remind_at` | DATETIME | زمان یادآوری |
| `status` | VARCHAR(50) | وضعیت |
| `sent_at` | DATETIME NULL | زمان ارسال |
| `failed_at` | DATETIME NULL | زمان شکست |
| `notification_id` | BIGINT UNSIGNED NULL | اعلان ایجادشده |
| `error_message` | TEXT NULL | پیام خطا |
| `metadata` | JSON NULL | داده تکمیلی |
| `created_at` | DATETIME NULL | زمان ایجاد |
| `updated_at` | DATETIME NULL | زمان بروزرسانی |
| `deleted_at` | DATETIME NULL | زمان حذف نرم |
| `deleted_by` | BIGINT UNSIGNED NULL | حذف‌کننده |

---

### reminder_typeهای پیشنهادی

```text
before_event
at_event_time
after_event
followup
deadline_warning
manual
system
```

---

### channel_typeهای پیشنهادی

```text
internal
sms
telegram
whatsapp
email
push
other
```

---

### statusهای پیشنهادی

```text
pending
queued
sent
failed
cancelled
expired
deleted
```

---

### قوانین

- `reminder_number` باید Unique باشد.
- Reminder باید event_id یا task_id داشته باشد.
- remind_at الزامی است.
- Reminder حذف‌شده نباید ارسال شود.
- Reminder برای مشتری باید Scope و visibility را رعایت کند.
- ارسال Reminder خارجی باید Notification/Delivery Log داشته باشد.
- Reminder حقوقی باید فقط برای افراد مجاز ارسال شود.
- failed شدن Reminder باید error_message داشته باشد.

---

### Indexهای پیشنهادی

```text
uniq_calendar_reminders_reminder_number
idx_calendar_reminders_event_id
idx_calendar_reminders_task_id
idx_calendar_reminders_reminder_type
idx_calendar_reminders_channel_type
idx_calendar_reminders_recipient_type
idx_calendar_reminders_user_id
idx_calendar_reminders_customer_id
idx_calendar_reminders_remind_at
idx_calendar_reminders_status
idx_calendar_reminders_notification_id
idx_calendar_reminders_deleted_at
```

---

## جدول calendar_recurring_rules

### هدف جدول

جدول `calendar_recurring_rules` قوانین تکرار رویدادها را نگهداری می‌کند.

مثلاً:

- هر روز
- هر هفته
- هر ماه
- هر سال
- هر چند روز یک‌بار
- تا تاریخ مشخص
- با تعداد تکرار مشخص

---

### نام جدول

```text
calendar_recurring_rules
```

---

### ستون‌های پیشنهادی

| ستون | نوع | توضیح |
|---|---|---|
| `id` | BIGINT UNSIGNED AUTO_INCREMENT | شناسه |
| `rule_key` | VARCHAR(100) | کلید قانون |
| `frequency` | VARCHAR(50) | فرکانس تکرار |
| `interval_value` | INT UNSIGNED | فاصله تکرار |
| `days_of_week` | JSON NULL | روزهای هفته |
| `day_of_month` | INT UNSIGNED NULL | روز ماه |
| `month_of_year` | INT UNSIGNED NULL | ماه سال |
| `starts_at` | DATETIME | شروع تکرار |
| `ends_at` | DATETIME NULL | پایان تکرار |
| `max_occurrences` | INT UNSIGNED NULL | حداکثر تعداد رخداد |
| `timezone` | VARCHAR(100) NULL | منطقه زمانی |
| `status` | VARCHAR(50) | وضعیت |
| `metadata` | JSON NULL | داده تکمیلی |
| `created_at` | DATETIME NULL | زمان ایجاد |
| `updated_at` | DATETIME NULL | زمان بروزرسانی |
| `deleted_at` | DATETIME NULL | زمان حذف نرم |

---

### frequencyهای پیشنهادی

```text
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
deleted
```

---

### قوانین

- rule_key باید Unique باشد.
- frequency الزامی است.
- interval_value باید بیشتر از صفر باشد.
- starts_at الزامی است.
- ends_at نباید قبل از starts_at باشد.
- اگر max_occurrences مشخص است، تولید رخداد نباید از آن بیشتر شود.
- قوانین تکرار نباید باعث تولید بی‌نهایت رکورد شوند.
- تغییر قانون تکرار رویداد حساس باید Audit Log داشته باشد.
- قانون حذف‌شده نباید رخداد جدید تولید کند.

---

### Indexهای پیشنهادی

```text
uniq_calendar_recurring_rules_rule_key
idx_calendar_recurring_rules_frequency
idx_calendar_recurring_rules_starts_at
idx_calendar_recurring_rules_ends_at
idx_calendar_recurring_rules_status
idx_calendar_recurring_rules_deleted_at
```

---

## جدول calendar_event_status_histories

### هدف جدول

جدول `calendar_event_status_histories` تاریخچه تغییر وضعیت رویدادهای تقویم را نگهداری می‌کند.

---

### نام جدول

```text
calendar_event_status_histories
```

---

### ستون‌های پیشنهادی

| ستون | نوع | توضیح |
|---|---|---|
| `id` | BIGINT UNSIGNED AUTO_INCREMENT | شناسه |
| `event_id` | BIGINT UNSIGNED | رویداد |
| `old_status` | VARCHAR(50) NULL | وضعیت قبلی |
| `new_status` | VARCHAR(50) | وضعیت جدید |
| `old_start_at` | DATETIME NULL | زمان شروع قبلی |
| `new_start_at` | DATETIME NULL | زمان شروع جدید |
| `reason` | TEXT NULL | دلیل تغییر |
| `changed_by` | BIGINT UNSIGNED NULL | تغییر‌دهنده |
| `changed_at` | DATETIME | زمان تغییر |
| `metadata` | JSON NULL | داده تکمیلی |

---

### قوانین

- تغییر وضعیت مهم رویداد باید history داشته باشد.
- تغییر زمان رویداد حساس باید history داشته باشد.
- لغو رویداد باید reason داشته باشد.
- این جدول نباید Soft Delete شود.
- مشاهده history رویداد حساس باید Permission داشته باشد.

---

### Indexهای پیشنهادی

```text
idx_calendar_event_status_histories_event_id
idx_calendar_event_status_histories_new_status
idx_calendar_event_status_histories_changed_by
idx_calendar_event_status_histories_changed_at
```

---

## جدول calendar_tasks

### هدف جدول

جدول `calendar_tasks` تسک‌های داخلی و پیگیری‌های قابل انجام را نگهداری می‌کند.

Task می‌تواند برای اپراتور، حسابدار، مدیر، وکیل یا سیستم ساخته شود.

---

### نام جدول

```text
calendar_tasks
```

---

### ستون‌های پیشنهادی

| ستون | نوع | توضیح |
|---|---|---|
| `id` | BIGINT UNSIGNED AUTO_INCREMENT | شناسه |
| `task_number` | VARCHAR(50) | شماره رسمی تسک |
| `task_type` | VARCHAR(50) | نوع تسک |
| `title` | VARCHAR(191) | عنوان |
| `description` | TEXT NULL | توضیحات |
| `status` | VARCHAR(50) | وضعیت |
| `priority` | VARCHAR(50) | اولویت |
| `visibility` | VARCHAR(50) | سطح نمایش |
| `is_sensitive` | TINYINT(1) | حساس بودن |
| `due_at` | DATETIME NULL | زمان سررسید |
| `start_at` | DATETIME NULL | زمان شروع |
| `completed_at` | DATETIME NULL | زمان تکمیل |
| `completed_by` | BIGINT UNSIGNED NULL | تکمیل‌کننده |
| `cancelled_at` | DATETIME NULL | زمان لغو |
| `cancelled_by` | BIGINT UNSIGNED NULL | لغوکننده |
| `cancellation_reason` | TEXT NULL | دلیل لغو |
| `assigned_user_id` | BIGINT UNSIGNED NULL | کاربر مسئول |
| `created_by` | BIGINT UNSIGNED NULL | ایجادکننده |
| `customer_id` | BIGINT UNSIGNED NULL | مشتری مرتبط |
| `contract_id` | BIGINT UNSIGNED NULL | قرارداد مرتبط |
| `installment_id` | BIGINT UNSIGNED NULL | قسط مرتبط |
| `payment_id` | BIGINT UNSIGNED NULL | پرداخت مرتبط |
| `legal_case_id` | BIGINT UNSIGNED NULL | پرونده حقوقی مرتبط |
| `related_type` | VARCHAR(100) NULL | نوع موجودیت مرتبط |
| `related_id` | BIGINT UNSIGNED NULL | شناسه موجودیت مرتبط |
| `metadata` | JSON NULL | داده تکمیلی |
| `created_at` | DATETIME NULL | زمان ایجاد |
| `updated_at` | DATETIME NULL | زمان بروزرسانی |
| `deleted_at` | DATETIME NULL | زمان حذف نرم |
| `deleted_by` | BIGINT UNSIGNED NULL | حذف‌کننده |

---

### task_typeهای پیشنهادی

```text
customer_followup
installment_followup
payment_review
legal_followup
document_review
settlement_followup
backup_check
plugin_check
internal_task
manual
system
other
```

---

### statusهای پیشنهادی

```text
todo
in_progress
waiting
completed
cancelled
overdue
deleted
```

---

### قوانین

- `task_number` باید Unique باشد.
- Task باید title داشته باشد.
- Task بهتر است assigned_user_id داشته باشد، مگر سیستمی باشد.
- Task حقوقی باید legal_case_id یا related_type مرتبط داشته باشد.
- Task مالی حساس باید visibility مناسب داشته باشد.
- تکمیل Task نباید بدون Service رسمی پرداخت یا قرارداد را تغییر دهد.
- لغو Task حساس باید cancellation_reason داشته باشد.
- حذف Task باید Soft Delete باشد.

---

### Indexهای پیشنهادی

```text
uniq_calendar_tasks_task_number
idx_calendar_tasks_task_type
idx_calendar_tasks_status
idx_calendar_tasks_priority
idx_calendar_tasks_visibility
idx_calendar_tasks_is_sensitive
idx_calendar_tasks_due_at
idx_calendar_tasks_assigned_user_id
idx_calendar_tasks_created_by
idx_calendar_tasks_customer_id
idx_calendar_tasks_contract_id
idx_calendar_tasks_installment_id
idx_calendar_tasks_payment_id
idx_calendar_tasks_legal_case_id
idx_calendar_tasks_related
idx_calendar_tasks_deleted_at
```

---

## جدول calendar_task_comments

### هدف جدول

جدول `calendar_task_comments` یادداشت‌ها و کامنت‌های مربوط به تسک را نگهداری می‌کند.

---

### نام جدول

```text
calendar_task_comments
```

---

### ستون‌های پیشنهادی

| ستون | نوع | توضیح |
|---|---|---|
| `id` | BIGINT UNSIGNED AUTO_INCREMENT | شناسه |
| `task_id` | BIGINT UNSIGNED | تسک |
| `comment_type` | VARCHAR(50) | نوع کامنت |
| `body` | TEXT | متن کامنت |
| `visibility` | VARCHAR(50) | سطح نمایش |
| `is_internal` | TINYINT(1) | داخلی بودن |
| `created_by` | BIGINT UNSIGNED NULL | نویسنده |
| `updated_by` | BIGINT UNSIGNED NULL | ویرایش‌کننده |
| `deleted_by` | BIGINT UNSIGNED NULL | حذف‌کننده |
| `created_at` | DATETIME NULL | زمان ایجاد |
| `updated_at` | DATETIME NULL | زمان بروزرسانی |
| `deleted_at` | DATETIME NULL | زمان حذف نرم |

---

### comment_typeهای پیشنهادی

```text
general
progress_update
manager_note
legal_note
accounting_note
system
other
```

---

### قوانین

- هر کامنت باید task_id داشته باشد.
- body الزامی است.
- کامنت داخلی نباید برای مشتری نمایش داده شود.
- کامنت حقوقی باید Permission حقوقی داشته باشد.
- حذف کامنت باید Soft Delete باشد.
- ویرایش یا حذف کامنت حساس باید Audit Log داشته باشد.

---

### Indexهای پیشنهادی

```text
idx_calendar_task_comments_task_id
idx_calendar_task_comments_comment_type
idx_calendar_task_comments_visibility
idx_calendar_task_comments_is_internal
idx_calendar_task_comments_created_by
idx_calendar_task_comments_created_at
idx_calendar_task_comments_deleted_at
```

---

## جدول calendar_task_status_histories

### هدف جدول

جدول `calendar_task_status_histories` تاریخچه تغییر وضعیت تسک‌ها را ذخیره می‌کند.

---

### نام جدول

```text
calendar_task_status_histories
```

---

### ستون‌های پیشنهادی

| ستون | نوع | توضیح |
|---|---|---|
| `id` | BIGINT UNSIGNED AUTO_INCREMENT | شناسه |
| `task_id` | BIGINT UNSIGNED | تسک |
| `old_status` | VARCHAR(50) NULL | وضعیت قبلی |
| `new_status` | VARCHAR(50) | وضعیت جدید |
| `old_due_at` | DATETIME NULL | سررسید قبلی |
| `new_due_at` | DATETIME NULL | سررسید جدید |
| `reason` | TEXT NULL | دلیل تغییر |
| `changed_by` | BIGINT UNSIGNED NULL | تغییر‌دهنده |
| `changed_at` | DATETIME | زمان تغییر |
| `metadata` | JSON NULL | داده تکمیلی |

---

### قوانین

- تغییر وضعیت Task باید history داشته باشد.
- تغییر due_at تسک حساس باید history داشته باشد.
- cancelled شدن Task باید reason داشته باشد.
- این جدول نباید Soft Delete شود.
- مشاهده history تسک حساس باید Permission داشته باشد.

---

### Indexهای پیشنهادی

```text
idx_calendar_task_status_histories_task_id
idx_calendar_task_status_histories_new_status
idx_calendar_task_status_histories_changed_by
idx_calendar_task_status_histories_changed_at
```

---

## رابطه Calendar Domain با سایر Domainها

Calendar Domain با بخش‌های زیادی از سیستم ارتباط دارد.

| Domain | رابطه |
|---|---|
| Customers | پیگیری مشتری، تماس، وعده پرداخت |
| Contracts | جلسات، پیگیری قرارداد، تسویه |
| Installments | سررسید قسط، پیگیری معوقه |
| Payments | بررسی پرداخت، پیگیری رسید |
| Legal | مهلت‌ها، جلسات، پیگیری پرونده |
| Notifications | Reminderها می‌توانند Notification بسازند |
| Chat | تسک یا رویداد می‌تواند از گفتگو ساخته شود |
| Files | در صورت نیاز فایل‌های مرتبط به تسک از Files استفاده می‌کنند |
| Reports | گزارش عملکرد اپراتور، تسک‌ها و یادآوری‌ها |
| Audit | تغییرات حساس رویدادها و تسک‌ها |
| Security | تلاش غیرمجاز برای مشاهده یا تغییر رویداد حساس |

---

## قوانین رویدادها

قوانین:

- رویداد باید زمان شروع داشته باشد.
- رویداد حساس باید visibility مناسب داشته باشد.
- رویداد حقوقی فقط برای نقش مجاز نمایش داده شود.
- رویداد مربوط به مشتری باید Scope مشتری را رعایت کند.
- رویداد مربوط به قرارداد باید Scope قرارداد را رعایت کند.
- تغییر زمان رویداد حساس باید Audit Log داشته باشد.
- لغو رویداد حساس باید دلیل داشته باشد.
- رویداد completed نباید بدون Permission ویژه به scheduled برگردد.

---

## قوانین یادآوری‌ها

قوانین:

- Reminder باید زمان remind_at داشته باشد.
- Reminder حذف‌شده یا cancelled نباید ارسال شود.
- Reminder خارجی باید از Notification Domain ارسال شود.
- Reminder حقوقی یا مالی باید فقط برای گیرنده مجاز ارسال شود.
- Reminder failed باید قابل Retry باشد.
- Reminder نباید عملیات مالی یا حقوقی قطعی انجام دهد.
- Reminder می‌تواند Dashboard و Notification ایجاد کند.

---

## قوانین تسک‌ها

قوانین:

- Task باید عنوان داشته باشد.
- Task بهتر است مسئول داشته باشد.
- Task overdue باید در Dashboard کاربر نمایش داده شود.
- Task حقوقی و مالی باید Permission و Scope را رعایت کند.
- تکمیل Task فقط همان Task را تکمیل می‌کند.
- اگر تکمیل Task باید اثر عملیاتی داشته باشد، باید از Service رسمی همان Domain انجام شود.
- حذف Task حساس باید Audit Log داشته باشد.

---

## قوانین رویدادهای تکرارشونده

قوانین:

- قانون تکرار باید محدود باشد.
- تولید رخدادهای تکراری نباید بی‌نهایت باشد.
- رخدادهای آینده می‌توانند با Job ساخته شوند.
- تغییر قانون تکرار باید مشخص کند روی رخدادهای قبلی اثر دارد یا فقط آینده.
- قانون paused نباید رخداد جدید بسازد.
- قانون deleted نباید رخداد جدید بسازد.
- تکرار رویداد حساس باید Audit Log داشته باشد.

---

## قوانین Soft Delete

جدول‌های زیر باید Soft Delete داشته باشند:

- `calendar_events`
- `calendar_reminders`
- `calendar_recurring_rules`
- `calendar_tasks`
- `calendar_task_comments`

قوانین:

- رویداد حذف‌شده در تقویم عادی نمایش داده نشود.
- Reminder حذف‌شده ارسال نشود.
- Task حذف‌شده در لیست عادی نمایش داده نشود.
- historyها نباید Soft Delete شوند.
- حذف داده حساس باید Audit Log داشته باشد.
- حذف فیزیکی فقط طبق Archive Policy مجاز است.

---

## قوانین Index و Performance

قوانین:

- نمایش تقویم بر اساس بازه زمانی باید سریع باشد.
- رویدادهای یک کاربر باید با assigned_user_id و start_at سریع فیلتر شوند.
- Reminderهای pending باید با remind_at سریع پیدا شوند.
- Taskهای overdue باید سریع پیدا شوند.
- Dashboard باید تسک‌ها و رویدادهای امروز را سریع بخواند.
- Queryها باید Pagination یا بازه زمانی داشته باشند.
- Export تقویم باید Permission-based باشد.

Indexهای مهم:

```text
calendar_events.start_at
calendar_events.assigned_user_id
calendar_events.status
calendar_events.customer_id
calendar_events.contract_id
calendar_reminders.remind_at
calendar_reminders.status
calendar_tasks.due_at
calendar_tasks.assigned_user_id
calendar_tasks.status
calendar_tasks.priority
```

---

## قوانین Validation

### calendar_events

- event_number الزامی و یکتا است.
- event_type الزامی و معتبر است.
- title الزامی است.
- start_at الزامی است.
- end_at در صورت وجود نباید قبل از start_at باشد.
- status معتبر باشد.
- visibility معتبر باشد.
- رویداد حقوقی باید visibility مناسب داشته باشد.

### calendar_event_attendees

- event_id الزامی است.
- attendee_type معتبر باشد.
- attendee_type برابر user باید user_id داشته باشد.
- attendee_type برابر customer باید customer_id داشته باشد.
- response_status معتبر باشد.

### calendar_reminders

- reminder_number الزامی و یکتا است.
- event_id یا task_id الزامی است.
- remind_at الزامی است.
- status معتبر باشد.
- channel_type معتبر باشد.

### calendar_recurring_rules

- rule_key الزامی و یکتا است.
- frequency معتبر باشد.
- interval_value باید بیشتر از صفر باشد.
- starts_at الزامی است.
- ends_at نباید قبل از starts_at باشد.

### calendar_tasks

- task_number الزامی و یکتا است.
- title الزامی است.
- task_type معتبر باشد.
- status معتبر باشد.
- priority معتبر باشد.
- due_at در صورت وجود باید DATETIME معتبر باشد.

### calendar_task_comments

- task_id الزامی است.
- body الزامی است.
- visibility معتبر باشد.

---

## قوانین Audit و Security Log

### Audit Log الزامی برای:

- ایجاد رویداد حقوقی حساس
- تغییر زمان رویداد حقوقی یا مالی
- لغو رویداد حساس
- حذف رویداد حساس
- افزودن یا حذف Attendee در رویداد حساس
- ایجاد Reminder حقوقی یا مالی
- تغییر Reminder حساس
- حذف Reminder حساس
- ایجاد Task حقوقی یا مالی حساس
- تغییر مسئول Task حساس
- تکمیل Task حقوقی
- حذف Task حساس
- ویرایش یا حذف کامنت حساس
- Export تقویم یا تسک‌های حساس

### Security Log الزامی برای:

- تلاش مشاهده رویداد بدون Permission
- تلاش مشاهده رویداد خارج از Scope
- تلاش مشاهده رویداد حقوقی توسط نقش غیرمجاز
- تلاش تغییر رویداد بدون Permission
- تلاش تغییر Task بدون Permission
- تلاش ارسال Reminder بدون Permission
- تلاش دستکاری event_id یا task_id
- CSRF نامعتبر در عملیات تقویم
- تلاش Export داده‌های خارج از Scope

---

## Seedهای پیشنهادی

### event_type

```text
installment_due
installment_followup
payment_promise
customer_call
legal_deadline
court_session
settlement_meeting
backup_schedule
internal_meeting
manual
system
other
```

### event_status

```text
scheduled
pending
completed
cancelled
missed
overdue
rescheduled
deleted
```

### task_type

```text
customer_followup
installment_followup
payment_review
legal_followup
document_review
settlement_followup
backup_check
plugin_check
internal_task
manual
system
other
```

### task_status

```text
todo
in_progress
waiting
completed
cancelled
overdue
deleted
```

### reminder_status

```text
pending
queued
sent
failed
cancelled
expired
deleted
```

### priority

```text
low
normal
high
critical
```

---

## چک‌لیست پیاده‌سازی

قبل از پیاده‌سازی Calendar Tables بررسی شود:

- [ ] جدول `calendar_events` ساخته شده است.
- [ ] `event_number` یکتا است.
- [ ] رویدادها به customer، contract، installment، payment و legal_case قابل اتصال هستند.
- [ ] جدول `calendar_event_attendees` وجود دارد.
- [ ] جدول `calendar_reminders` وجود دارد.
- [ ] `reminder_number` یکتا است.
- [ ] Reminderها می‌توانند Notification ایجاد کنند.
- [ ] جدول `calendar_recurring_rules` وجود دارد.
- [ ] قوانین تکرار محدود و کنترل‌شده هستند.
- [ ] جدول `calendar_event_status_histories` وجود دارد.
- [ ] جدول `calendar_tasks` وجود دارد.
- [ ] `task_number` یکتا است.
- [ ] جدول `calendar_task_comments` وجود دارد.
- [ ] جدول `calendar_task_status_histories` وجود دارد.
- [ ] رویدادها و تسک‌های حساس Audit Log دارند.
- [ ] تلاش‌های غیرمجاز Security Log دارند.
- [ ] Queryهای تقویم Scope را رعایت می‌کنند.
- [ ] Export تقویم Permission-based است.

---

## Definition of Done

Calendar Tables زمانی کامل هستند که:

- رویداد تقویم با شماره رسمی یکتا قابل ثبت باشد.
- رویداد بتواند به مشتری، قرارداد، قسط، پرداخت یا پرونده حقوقی وصل شود.
- شرکت‌کنندگان رویداد قابل مدیریت باشند.
- یادآوری‌ها برای رویداد یا تسک قابل ثبت و ارسال باشند.
- یادآوری خارجی از Notification Domain استفاده کند.
- قوانین تکرار رویدادها قابل ذخیره و کنترل باشند.
- تغییر وضعیت رویدادها در history ثبت شود.
- تسک‌های داخلی با مسئول، وضعیت، اولویت و سررسید قابل مدیریت باشند.
- کامنت‌های تسک قابل ثبت و کنترل با Permission باشند.
- تغییر وضعیت تسک‌ها در history ثبت شود.
- رویدادها و تسک‌های حقوقی یا مالی بدون Permission نمایش داده نشوند.
- Reminder و Task منطق مالی یا حقوقی را دور نزنند.
- عملیات حساس Audit Log داشته باشند.
- تلاش‌های غیرمجاز Security Log داشته باشند.
- جدول‌ها با MySQL/MariaDB و PHP 7.4+ سازگار باشند.
- Codex بتواند از روی این مستندات Migrationهای Calendar Domain را بسازد.

---

## پایان فایل
````
