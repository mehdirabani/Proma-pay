فعلاً هنوز داخل همین پوشه ادامه بده:

```text
docs/specifications/
```

# جلد ۱۶

## نام فایل

```text
15_EVENT_ARCHITECTURE.md
```

````md
# Proma Pay — Event Architecture Specification

Version: 1.0  
Status: Master Specification  
Priority: CRITICAL

---

# Introduction

این فایل معماری رسمی Event System در پروژه Proma Pay را تعریف می‌کند.

هدف Event System این است که عملیات مهم سیستم به‌صورت تمیز، قابل توسعه، قابل ردیابی و بدون وابستگی مستقیم بین ماژول‌ها اجرا شوند.

هیچ Controller یا Service نباید برای هر اثر جانبی، مستقیم به چندین بخش دیگر وابسته شود.

مثلاً وقتی پرداخت تأیید می‌شود، نباید داخل همان Controller به‌صورت مستقیم:

```text
قسط را آپدیت کند
اعلان بسازد
Timeline بسازد
Log ثبت کند
پیام ربات بفرستد
گزارش را آپدیت کند
فایل رسید را حذف کند
```

بلکه باید یک Event ایجاد شود و Listenerهای مربوطه کارهای وابسته را انجام دهند.

---

# Constitution

## Rule 1 — Important Actions Must Dispatch Events

هر عملیات مهم باید Event ایجاد کند.

نمونه عملیات مهم:

```text
CustomerCreated
CustomerUpdated
ContractCreated
ContractUpdated
InstallmentCreated
InstallmentPaid
PaymentCreated
PaymentApproved
PaymentRejected
ReceiptUploaded
IdentityDocumentUploaded
IdentityDocumentApproved
IdentityDocumentRejected
LegalCaseCreated
LegalCaseUpdated
LegalCostAdded
CalendarEventCreated
NotificationCreated
BackupCompleted
BackupFailed
UpdateCompleted
UpdateFailed
```

---

## Rule 2 — Event Must Describe What Happened

Event نباید دستور انجام کاری باشد.

Event باید فقط اعلام کند چه اتفاقی افتاده است.

نام صحیح:

```text
PaymentApproved
```

نام غلط:

```text
SendPaymentApprovedNotification
```

---

## Rule 3 — Event Dispatching Must Not Replace Business Logic

Business Logic باید داخل Serviceهای دامنه باقی بماند.

Event فقط برای اجرای اثرهای جانبی استفاده می‌شود.

---

## Rule 4 — Listeners Must Be Small

هر Listener فقط یک مسئولیت داشته باشد.

نمونه صحیح:

```text
CreatePaymentApprovedNotificationListener
CreatePaymentTimelineListener
DeleteApprovedReceiptFileListener
WriteFinancialLogListener
```

نمونه غلط:

```text
HandleEverythingAfterPaymentApprovedListener
```

---

## Rule 5 — Events Must Be Traceable

هر Event مهم باید قابل ردیابی باشد.

در صورت نیاز باید در Event Log ذخیره شود.

---

## Rule 6 — Failed Listener Must Not Corrupt Main Operation

اگر Listener غیرحیاتی شکست خورد، نباید باعث خراب شدن عملیات اصلی شود.

مثلاً اگر ارسال پیام ربات شکست خورد، پرداخت نباید ناموفق شود.

اما اگر Listener حیاتی شکست خورد، باید Transaction Rollback شود.

---

# Event Categories

Eventها باید در دسته‌های مشخص قرار بگیرند:

```text
Customer Events
Contract Events
Installment Events
Payment Events
Receipt Events
Identity Events
Legal Events
Calendar Events
Chat Events
Notification Events
Backup Events
Update Events
Security Events
System Events
```

---

# Event Naming Convention

نام Event باید با Past Tense نوشته شود.

الگو:

```text
Entity + ActionPast
```

نمونه:

```text
CustomerCreated
ContractSigned
PaymentApproved
InstallmentOverdue
ReceiptRejected
LegalCaseReferred
BackupCompleted
UpdateFailed
```

---

# Listener Naming Convention

نام Listener باید دقیقاً کاری را که انجام می‌دهد بیان کند.

الگو:

```text
Verb + Target + Listener
```

نمونه:

```text
CreatePaymentApprovedNotificationListener
WritePaymentAuditLogListener
CreateContractTimelineListener
SyncInstallmentWithCalendarListener
DeleteRejectedReceiptFileListener
SendBotMessageToCustomerListener
```

---

# Event Payload Rules

هر Event باید فقط داده‌های لازم را حمل کند.

Payload نباید سنگین باشد.

ترجیحاً شامل IDها باشد، نه کل Objectها.

نمونه صحیح:

```php
new PaymentApprovedEvent(
    paymentId: $paymentId,
    installmentId: $installmentId,
    contractId: $contractId,
    customerId: $customerId,
    approvedBy: $adminId
);
```

نمونه غلط:

```php
new PaymentApprovedEvent($fullCustomerObject, $fullContractObject, $allInstallments);
```

---

# Required Event Fields

هر Event مهم باید تا حد امکان شامل موارد زیر باشد:

```text
event_name
entity_type
entity_id
related_customer_id
related_contract_id
actor_user_id
actor_role
occurred_at
metadata
```

---

# Sync vs Async Events

همه Eventها یکسان نیستند.

بعضی باید همزمان اجرا شوند.

بعضی می‌توانند در پس‌زمینه اجرا شوند.

---

## Synchronous Events

برای عملیات حیاتی که باید همراه عملیات اصلی انجام شوند.

نمونه:

```text
ثبت Financial Log برای پرداخت
ثبت Audit Log برای تغییر تنظیمات
ثبت Timeline اصلی قرارداد
تغییر وضعیت قسط بعد از پرداخت
```

---

## Asynchronous Events

برای عملیات‌هایی که شکست آن‌ها نباید عملیات اصلی را خراب کند.

نمونه:

```text
ارسال پیام ربات
ارسال ایمیل
ارسال پیامک
پاکسازی فایل موقت
ارسال اعلان خارجی
تولید گزارش سنگین
```

---

# Transaction Rules

Eventهایی که داخل Transaction ایجاد می‌شوند باید با دقت مدیریت شوند.

قانون:

```text
اگر Listener به دیتای Commit شده نیاز دارد، بعد از Commit اجرا شود.
```

نمونه:

پرداخت تأیید می‌شود:

```text
Start Transaction
Update Payment
Update Installment
Write Financial Log
Commit
Dispatch AfterCommit Events
Send Notification
Send Bot Message
Delete Receipt File
```

---

# Critical Listener Rules

Listenerهای حیاتی باید در همان Transaction اجرا شوند.

نمونه Listenerهای حیاتی:

```text
UpdateInstallmentStatusListener
WriteFinancialLogListener
WriteAuditLogListener
CreateLegalCaseRecordListener
```

اگر این Listenerها شکست بخورند، عملیات باید Rollback شود.

---

# Non-Critical Listener Rules

Listenerهای غیرحیاتی باید خطا را Log کنند ولی عملیات اصلی را خراب نکنند.

نمونه:

```text
SendBotMessageListener
SendEmailListener
SendTelegramMessageListener
GenerateReportSnapshotListener
```

---

# Event Log

برای Eventهای مهم باید امکان ثبت Event Log وجود داشته باشد.

فیلدهای پیشنهادی:

```text
id
event_name
entity_type
entity_id
actor_user_id
status
payload
error_message
dispatched_at
processed_at
created_at
```

---

# Event Status

وضعیت‌های پیشنهادی Event:

```text
pending
processing
processed
failed
retrying
ignored
```

---

# Retry Rules

Eventهای قابل Retry باید دارای تعداد تلاش باشند.

فیلدهای پیشنهادی:

```text
attempts
max_attempts
last_error
next_retry_at
```

Retry برای موارد زیر مناسب است:

```text
ارسال پیام ربات
ارسال ایمیل
ارسال پیامک
ارسال اعلان خارجی
پردازش فایل
تولید گزارش
```

Retry برای عملیات مالی حساس نباید بدون طراحی دقیق انجام شود.

---

# Idempotency

Listenerها باید تا حد امکان Idempotent باشند.

یعنی اگر دوباره اجرا شدند، اثر تکراری ایجاد نکنند.

نمونه:

اگر اعلان پرداخت قبلاً ساخته شده:

```text
اعلان جدید تکراری ساخته نشود.
```

اگر Timeline قبلاً ثبت شده:

```text
Timeline تکراری ثبت نشود.
```

کلید پیشنهادی:

```text
event_name + entity_type + entity_id + listener_name
```

---

# Event To Side Effect Mapping

## PaymentApproved

اثرات لازم:

```text
Update Installment Status
Write Financial Log
Write Audit Log
Create Timeline
Create Notification For Customer
Send Bot Message
Delete Receipt File If Exists
Update Contract Financial Summary Cache If Used
```

---

## PaymentRejected

اثرات لازم:

```text
Return Payment To Waiting State
Write Financial Log
Write Audit Log
Create Timeline
Create Notification For Customer
Send Bot Message
Delete Receipt File
```

---

## ReceiptUploaded

اثرات لازم:

```text
Create Review Item
Notify Admin
Create Timeline
Write Log
```

---

## IdentityDocumentUploaded

اثرات لازم:

```text
Create Review Item
Notify Admin
Write Log
Create Timeline
```

---

## IdentityDocumentApproved

اثرات لازم:

```text
Activate New Document
Delete Old Document
Enable Customer Blue Tick
Create Notification For Customer
Send Bot Message
Create Timeline
Write Audit Log
```

---

## IdentityDocumentRejected

اثرات لازم:

```text
Delete New Pending Document
Keep Old Document Active
Create Notification For Customer
Send Bot Message
Create Timeline
Write Audit Log
```

---

## ContractCreated

اثرات لازم:

```text
Create Contract Timeline
Generate Installments
Sync Installments With Calendar
Write Audit Log
Create Notification For Admin
```

---

## InstallmentOverdue

اثرات لازم:

```text
Mark Installment As Overdue
Notify Customer
Notify Operator
Notify Admin If Needed
Create Timeline
Write Financial Log
Add To Overdue Queue
```

---

## LegalCaseReferred

اثرات لازم:

```text
Create Legal Case
Notify Lawyer
Notify Admin
Create Contract Timeline
Create Legal Timeline
Write Legal Log
Create Calendar Event If Date Exists
```

---

## CourtDateCreated

اثرات لازم:

```text
Create Calendar Event
Notify Assigned Lawyer
Notify Admin
Notify Customer If Needed
Write Legal Log
Create Timeline
```

---

## BackupCompleted

اثرات لازم:

```text
Write Backup Log
Notify Admin
Create System Timeline If Exists
```

---

## BackupFailed

اثرات لازم:

```text
Write Critical Log
Notify Admin
Create Security/System Alert
```

---

## UpdateCompleted

اثرات لازم:

```text
Write Update Log
Notify Admin
Store New Version
Disable Maintenance Mode
```

---

## UpdateFailed

اثرات لازم:

```text
Write Critical Update Log
Notify Admin
Rollback If Possible
Disable Maintenance Mode If Safe
```

---

# Forbidden Patterns

موارد زیر ممنوع هستند:

```php
// Controller doing everything
$payment->status = 'approved';
$installment->status = 'paid';
Notification::create(...);
Timeline::create(...);
Log::create(...);
unlink($receiptPath);
```

---

# Correct Pattern

الگوی صحیح:

```php
$paymentService->approveReceipt($receiptId, $adminId);

EventDispatcher::dispatch(new PaymentApprovedEvent(
    paymentId: $paymentId,
    installmentId: $installmentId,
    contractId: $contractId,
    customerId: $customerId,
    approvedBy: $adminId
));
```

Listenerها:

```text
UpdateInstallmentStatusListener
WritePaymentFinancialLogListener
CreatePaymentTimelineListener
CreatePaymentApprovedNotificationListener
SendPaymentApprovedBotMessageListener
DeleteApprovedReceiptFileListener
```

---

# Event Dispatcher Responsibilities

EventDispatcher مسئول موارد زیر است:

```text
ثبت Event
پیدا کردن Listenerهای مرتبط
اجرای Listenerها
مدیریت Sync/Async
مدیریت خطا
ثبت وضعیت اجرای Event
Retry در صورت نیاز
```

---

# Listener Responsibilities

هر Listener باید:

```text
یک کار مشخص انجام دهد
Permission لازم را بررسی کند اگر لازم است
Duplicate ایجاد نکند
خطا را درست مدیریت کند
Log لازم را ثبت کند
قابل تست باشد
```

---

# Queue Readiness

حتی اگر نسخه فعلی Queue کامل نداشته باشد، طراحی Eventها باید آماده Queue باشد.

یعنی Event و Listener نباید به Request جاری وابستگی غیرضروری داشته باشند.

ممنوع:

```text
وابستگی مستقیم به $_POST
وابستگی مستقیم به $_SESSION
وابستگی مستقیم به متغیرهای View
وابستگی به فایل موقت بدون ذخیره مسیر امن
```

---

# Security Rules

Eventها نباید باعث دور زدن Permission شوند.

اگر Listener عملیاتی انجام می‌دهد که نیاز به دسترسی دارد، باید نقش actor بررسی شود یا عملیات به عنوان System Action ثبت شود.

System Action باید واضح در Log مشخص شود.

---

# Performance Rules

Eventها نباید صفحه را کند کنند.

Listenerهای سنگین باید Async شوند.

نمونه Listenerهای سنگین:

```text
GenerateLargeReportListener
SendBulkNotificationListener
CompressUploadedFileListener
CreateBackupArchiveListener
```

---

# Error Handling Rules

اگر Listener شکست خورد:

```text
خطا Log شود
وضعیت Event ثبت شود
اگر حیاتی است Rollback شود
اگر غیرحیاتی است عملیات اصلی ادامه پیدا کند
در صورت امکان Retry شود
```

Silent Failure ممنوع است.

---

# Implementation Rules

Codex هنگام پیاده‌سازی هر Feature باید بررسی کند:

```text
آیا این عملیات باید Event داشته باشد؟
نام Event چیست؟
Payload چه داده‌هایی لازم دارد؟
کدام Listenerها باید اجرا شوند؟
کدام Listenerها حیاتی هستند؟
کدام Listenerها Async هستند؟
آیا Event نیاز به Log دارد؟
آیا Listenerها Idempotent هستند؟
آیا احتمال Duplicate وجود دارد؟
آیا Permission رعایت شده؟
آیا خطاها مدیریت شده‌اند؟
```

---

# Review Checklist

قبل از Merge هر Feature:

```text
□ عملیات مهم Event دارد.
□ نام Event با Past Tense نوشته شده.
□ Event فقط اتفاق را توصیف می‌کند، نه دستور را.
□ Payload سبک است.
□ Listenerها کوچک و تک‌مسئولیتی هستند.
□ Listenerهای حیاتی Transaction-aware هستند.
□ Listenerهای غیرحیاتی خطا را Log می‌کنند.
□ Duplicate Side Effect ایجاد نمی‌شود.
□ Notification/Timeline/Log از طریق Listener ساخته می‌شوند.
□ Eventها آماده Queue هستند.
□ Eventها Security-aware هستند.
□ Eventها Performance را خراب نمی‌کنند.
```

---

# Definition of Done

یک Feature از نظر Event Architecture زمانی کامل است که:

```text
✔ عملیات مهم آن Event Dispatch کند.
✔ اثرهای جانبی داخل Controller پخش نشده باشند.
✔ Listenerها تک‌مسئولیتی باشند.
✔ Timeline، Notification و Log از طریق Listener ایجاد شوند.
✔ Eventها قابل ردیابی باشند.
✔ Listenerها خطا را درست مدیریت کنند.
✔ اجرای مجدد Event باعث داده تکراری نشود.
✔ طراحی برای Queue آینده آماده باشد.
```

---

# Future Considerations

سیستم باید در آینده آماده موارد زیر باشد:

```text
Queue Worker
Failed Event Dashboard
Retry Manager
Event Replay
Event Sourcing Partial Support
Webhook Dispatcher
External Integrations
Workflow Engine
Rule Engine
Automation Builder
AI Event Analyzer
```

---

# End of File
````
