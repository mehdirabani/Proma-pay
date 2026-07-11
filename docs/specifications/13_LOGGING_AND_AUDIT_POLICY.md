# Proma Pay — Logging & Audit Policy

Version: 1.0  
Status: Master Specification  
Priority: CRITICAL

---

# Introduction

این فایل استاندارد رسمی ثبت Log و Audit در پروژه Proma Pay است.

هدف این سند این است که تمام عملیات مهم سیستم قابل ردیابی، قابل بررسی، قابل اثبات و قابل تحلیل باشند.

هیچ عملیات حساس، مالی، حقوقی، امنیتی یا مدیریتی نباید بدون ثبت Log انجام شود.

Log فقط برای خطا نیست.

Log حافظه رسمی سیستم است.

---

# Constitution

## Rule 1 — Every Sensitive Action Must Be Logged

هر عملیات حساس باید ثبت شود.

نمونه عملیات حساس:

```text
ورود کاربر
خروج کاربر
تلاش ناموفق برای ورود
تغییر رمز عبور
تغییر تنظیمات
ثبت مشتری
ویرایش مشتری
حذف مشتری
ثبت قرارداد
ویرایش قرارداد
حذف قرارداد
ثبت قسط
ویرایش قسط
ثبت پرداخت
تأیید پرداخت
رد پرداخت
آپلود رسید
آپلود مدرک هویتی
تأیید مدرک هویتی
رد مدرک هویتی
ارجاع پرونده به حقوقی
ثبت اقدام حقوقی
ثبت هزینه حقوقی
بکاپ‌گیری
بازیابی بکاپ
بروزرسانی سیستم
```

---

## Rule 2 — Logs Are Append Only

Logها نباید ویرایش شوند.

Logها نباید حذف شوند.

اگر اصلاحی لازم بود، باید Log جدید ثبت شود.

حذف Log فقط در عملیات نگهداری سیستم و با سطح دسترسی مدیر اصلی مجاز است.

---

## Rule 3 — User Actions Must Be Traceable

هر Log باید مشخص کند:

```text
چه کسی؟
چه کاری؟
روی چه چیزی؟
چه زمانی؟
از کجا؟
با چه نتیجه‌ای؟
```

---

## Rule 4 — Technical Logs Must Not Be Shown To Users

جزئیات فنی Log فقط برای مدیران مجاز قابل مشاهده است.

کاربر نهایی نباید هیچ اطلاعات فنی از Log دریافت کند.

---

## Rule 5 — Security Logs Have Higher Priority

رویدادهای امنیتی باید جداگانه و با اولویت بالا ثبت شوند.

نمونه:

```text
CSRF نامعتبر
دسترسی غیرمجاز
تلاش برای مشاهده داده دیگران
تغییر ID در URL
آپلود فایل غیرمجاز
ورود ناموفق مکرر
تلاش برای حذف غیرمجاز
تلاش برای تغییر تنظیمات بدون مجوز
```

---

## Rule 6 — Financial Logs Must Be Immutable

Logهای مالی نباید حذف یا ویرایش شوند.

موارد مالی شامل:

```text
پرداخت
تأیید پرداخت
رد پرداخت
تغییر وضعیت قسط
محاسبه جریمه
محاسبه تخفیف
تسویه زودتر از موعد
ثبت هزینه حقوقی
```

---

## Rule 7 — Legal Logs Must Be Complete

هر اقدام حقوقی باید Log کامل داشته باشد.

Log حقوقی باید بتواند مسیر پرونده را از ابتدا تا انتها نشان دهد.

---

# Log Types

## Application Log

برای رویدادهای عمومی سیستم.

نمونه:

```text
ثبت مشتری جدید
ویرایش قرارداد
ایجاد رویداد تقویم
ارسال اعلان
```

---

## Error Log

برای خطاهای فنی سیستم.

نمونه:

```text
Exception
Database Error
Upload Error
Payment Gateway Error
Backup Error
Update Error
```

---

## Security Log

برای رویدادهای امنیتی.

نمونه:

```text
Permission Denied
Invalid CSRF
Invalid Login
Suspicious Request
Unauthorized File Access
```

---

## Audit Log

برای عملیات مهم مدیریتی و قابل اثبات.

نمونه:

```text
تغییر تنظیمات
تغییر نقش کاربر
تأیید مدرک هویتی
تأیید رسید پرداخت
حذف قرارداد
بازیابی بکاپ
```

---

## Financial Log

برای رویدادهای مالی.

نمونه:

```text
ثبت پرداخت
تأیید پرداخت
رد پرداخت
ثبت جریمه
ثبت تخفیف
ثبت هزینه حقوقی
```

---

## Legal Log

برای رویدادهای حقوقی.

نمونه:

```text
ارجاع پرونده
ثبت اظهارنامه
ثبت شکایت
ثبت جلسه دادگاه
ثبت هزینه دادرسی
ثبت نتیجه پرونده
```

---

## Update Log

برای نصب و بروزرسانی سیستم.

نمونه:

```text
شروع آپدیت
بررسی فایل update.json
اجرای Migration
کپی فایل‌ها
خطای آپدیت
Rollback
پایان موفق آپدیت
```

---

## Backup Log

برای بکاپ و بازیابی.

نمونه:

```text
شروع بکاپ
بکاپ دیتابیس
بکاپ فایل‌ها
خطای ZipArchive
Fallback Backup
Restore
خطای Restore
```

---

# Required Log Fields

هر Log باید تا حد امکان شامل این فیلدها باشد:

```text
id
log_type
level
action
message
user_id
user_role
entity_type
entity_id
related_customer_id
related_contract_id
ip_address
user_agent
request_method
route
old_values
new_values
metadata
created_at
```

---

# Log Levels

سطوح Log باید مشخص باشند:

```text
info
warning
error
critical
security
audit
```

---

## info

برای عملیات عادی موفق.

نمونه:

```text
قرارداد جدید ثبت شد.
```

---

## warning

برای رفتارهای غیرعادی اما غیر بحرانی.

نمونه:

```text
کاربر چند بار اطلاعات اشتباه وارد کرد.
```

---

## error

برای خطاهای فنی قابل کنترل.

نمونه:

```text
آپلود رسید پرداخت ناموفق بود.
```

---

## critical

برای خطاهای حیاتی.

نمونه:

```text
بازیابی بکاپ شکست خورد.
```

---

## security

برای تهدید یا رخداد امنیتی.

نمونه:

```text
تلاش برای دسترسی غیرمجاز به قرارداد دیگران.
```

---

## audit

برای عملیات رسمی قابل پیگیری.

نمونه:

```text
مدیر رسید پرداخت را تأیید کرد.
```

---

# Entity Types

مقدار entity_type باید استاندارد باشد.

نمونه:

```text
user
customer
contract
installment
payment
receipt
identity_document
legal_case
legal_cost
calendar_event
chat_message
notification
setting
backup
update
```

---

# Logging Services

ثبت Log نباید مستقیم داخل Controllerها تکرار شود.

باید از Service مرکزی استفاده شود.

نمونه سرویس‌ها:

```text
LogService
AuditService
SecurityLogService
FinancialLogService
LegalLogService
```

---

# Forbidden Patterns

موارد زیر ممنوع هستند:

```php
file_put_contents('log.txt', $error);
```

```php
echo $exception->getMessage();
```

```php
var_dump($error);
```

```php
die($error);
```

```php
catch (Exception $e) {
    // do nothing
}
```

---

# Correct Pattern

نمونه الگوی صحیح:

```php
try {
    $paymentService->approveReceipt($receiptId, $adminId);
} catch (Throwable $e) {
    LogService::error('payment_receipt_approval_failed', [
        'receipt_id' => $receiptId,
        'admin_id' => $adminId,
        'message' => $e->getMessage(),
    ]);

    throw new UserFriendlyException('تأیید رسید پرداخت با خطا مواجه شد.');
}
```

---

# Audit Old And New Values

در عملیات ویرایش مهم، مقدار قبلی و مقدار جدید باید ثبت شود.

نمونه:

```json
{
  "old_values": {
    "status": "pending"
  },
  "new_values": {
    "status": "approved"
  }
}
```

---

# Sensitive Data Masking

اطلاعات حساس نباید کامل داخل Log ذخیره شوند.

موارد حساس:

```text
رمز عبور
توکن
Session ID
شماره کامل کارت بانکی
اطلاعات اتصال دیتابیس
کلید API
کد تأیید پیامکی
```

نمونه صحیح:

```text
6037 **** **** 1234
```

---

# Payment Logging Rules

در پرداخت‌ها باید ثبت شود:

```text
شناسه پرداخت
شناسه قسط
شناسه قرارداد
مبلغ
روش پرداخت
وضعیت قبل
وضعیت بعد
کاربر انجام‌دهنده
زمان انجام عملیات
نتیجه Gateway در صورت وجود
```

در پرداخت Gateway، هیچ پرداختی بدون تأیید Callback معتبر نباید موفق ثبت شود.

---

# Card To Card Receipt Logging

برای رسید کارت‌به‌کارت باید ثبت شود:

```text
آپلود رسید
وضعیت بررسی
تأیید یا رد
کاربر بررسی‌کننده
زمان بررسی
علت رد در صورت وجود
حذف فایل رسید پس از بررسی
```

---

# Identity Document Logging

برای مدارک هویتی باید ثبت شود:

```text
آپلود مدرک جدید
وضعیت Pending
تأیید
رد
حذف مدرک قبلی پس از تأیید
حذف مدرک جدید پس از رد
نمایش یا حذف تیک آبی
```

---

# Legal Logging Rules

برای پرونده حقوقی باید ثبت شود:

```text
ارجاع به حقوقی
تغییر وضعیت پرونده
ثبت اقدام حقوقی
ثبت هزینه حقوقی
ثبت جلسه دادگاه
ثبت فایل حقوقی
ثبت نتیجه پرونده
```

هر Log حقوقی باید به پرونده، قرارداد و مشتری مرتبط شود.

---

# Settings Logging Rules

هر تغییر تنظیمات باید Audit شود.

موارد مهم:

```text
شماره کارت
درگاه پرداخت
قالب قرارداد
نرخ جریمه
نرخ پاداش
تنظیمات پیامک
تنظیمات اعلان
تنظیمات بکاپ
تنظیمات بروزرسانی
لوگو
متن فوتر
```

---

# Backup And Update Logging

بکاپ و بروزرسانی باید Step Log داشته باشند.

نمونه:

```text
Step 1: Maintenance Mode Enabled
Step 2: Backup Started
Step 3: Database Backup Completed
Step 4: Files Backup Completed
Step 5: Migration Started
Step 6: Migration Completed
Step 7: Maintenance Mode Disabled
```

اگر مرحله‌ای شکست خورد، همان مرحله باید مشخص باشد.

---

# Log Visibility Rules

## Admin

مدیر اصلی می‌تواند همه Logها را ببیند.

---

## Department Manager

مدیر واحد فقط Logهای مرتبط با واحد خود را ببیند.

---

## Operator

اپراتور فقط Logهای پیگیری‌های خودش را ببیند.

---

## Lawyer

واحد حقوقی فقط Logهای پرونده‌های حقوقی مجاز خود را ببیند.

---

## Customer

مشتری نباید Log فنی ببیند.

مشتری فقط Timeline ساده‌شده مربوط به خودش را می‌بیند.

---

# User Facing Timeline vs Internal Logs

Timeline با Log متفاوت است.

## Timeline

برای نمایش قابل فهم به کاربر یا مدیر.

نمونه:

```text
رسید پرداخت توسط مشتری ارسال شد.
```

## Log

برای ردیابی فنی و داخلی.

نمونه:

```text
payment_receipt_uploaded: receipt_id=245, user_id=81, ip=...
```

هر Timeline مهم می‌تواند Log هم داشته باشد، اما هر Log لازم نیست Timeline داشته باشد.

---

# Log Retention Policy

Logها باید سیاست نگهداری داشته باشند.

پیشنهاد:

```text
Security Logs: دائمی یا حداقل 5 سال
Financial Logs: دائمی
Legal Logs: دائمی
Audit Logs: دائمی
Application Logs: حداقل 1 سال
Error Logs: حداقل 1 سال
Temporary Debug Logs: حداکثر 30 روز
```

Debug Log در نسخه نهایی نباید فعال باشد مگر با تنظیمات مدیر اصلی.

---

# Log Storage Rules

Logها می‌توانند در دیتابیس یا فایل ذخیره شوند.

اما Logهای مهم باید در دیتابیس ذخیره شوند.

مواردی که باید در دیتابیس باشند:

```text
Audit Logs
Security Logs
Financial Logs
Legal Logs
Update Logs
Backup Logs
```

---

# Log Search And Filter

بخش مدیریت Log باید امکان موارد زیر را داشته باشد:

```text
جستجو
فیلتر بر اساس نوع
فیلتر بر اساس کاربر
فیلتر بر اساس موجودیت
فیلتر بر اساس سطح
فیلتر بر اساس تاریخ
خروجی گرفتن
مشاهده جزئیات
```

---

# Implementation Rules

Codex هنگام توسعه هر Feature باید بررسی کند:

```text
آیا این عملیات حساس است؟
آیا نیاز به Audit دارد؟
آیا اثر مالی دارد؟
آیا اثر حقوقی دارد؟
آیا اثر امنیتی دارد؟
آیا باید در Timeline هم ثبت شود؟
آیا باید Notification ایجاد کند؟
آیا مقدار قبلی و جدید لازم است؟
آیا داده حساس باید Mask شود؟
آیا سطح دسترسی مشاهده Log مشخص است؟
```

---

# Review Checklist

قبل از Merge هر Feature:

```text
□ عملیات حساس Log دارد.
□ عملیات مالی Financial Log دارد.
□ عملیات حقوقی Legal Log دارد.
□ عملیات امنیتی Security Log دارد.
□ عملیات مدیریتی Audit Log دارد.
□ اطلاعات حساس Mask شده‌اند.
□ خطاها Log می‌شوند.
□ Logها حذف یا ویرایش نمی‌شوند.
□ user_id و entity_id ثبت می‌شوند.
□ IP و User Agent ثبت می‌شوند.
□ سطح دسترسی مشاهده Log رعایت شده است.
□ Timeline و Log با هم اشتباه گرفته نشده‌اند.
```

---

# Definition of Done

یک Feature از نظر Logging زمانی کامل است که:

```text
✔ عملیات مهم آن قابل ردیابی باشد.
✔ عملیات حساس آن Audit شود.
✔ خطاهای آن ثبت شوند.
✔ اطلاعات حساس در Log ذخیره نشود.
✔ Log با Permission مناسب قابل مشاهده باشد.
✔ Timeline کاربرپسند در صورت نیاز ساخته شود.
✔ Log داخلی دقیق و فنی باشد.
✔ هیچ خطایی Silent Fail نشود.
```

---

# Future Considerations

سیستم باید در آینده آماده موارد زیر باشد:

```text
Advanced Log Viewer
Security Dashboard
Financial Audit Report
Legal Case History Export
Error Alert Notification
Telegram Error Alert
Log Archive
Log Rotation
External Log Storage
SIEM Integration
Anomaly Detection
AI Log Analysis
```

---

# End of File