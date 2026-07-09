# جلد ۱۳

## نام فایل

```text
12_ERROR_HANDLING.md
```

````md
# Proma Pay — Error Handling Specification

Version: 1.0  
Status: Master Specification  
Priority: CRITICAL

---

# Introduction

این فایل استاندارد رسمی مدیریت خطا در پروژه Proma Pay است.

تمام خطاهای سیستم باید به شکل کنترل‌شده، قابل فهم، قابل لاگ‌گیری و قابل پیگیری مدیریت شوند.

هیچ خطای PHP، SQL، Exception، Warning، Notice یا Fatal Error نباید به کاربر نهایی نمایش داده شود.

---

# Constitution

## Rule 1 — No Raw Error For User

هیچ خطای خامی نباید در UI نمایش داده شود.

موارد ممنوع:

```text
Fatal error
Warning
Notice
Stack trace
SQL error
File path
Server path
Database credentials
PHP internal error
```

---

## Rule 2 — User Message Must Be Persian And Clear

تمام پیام‌های خطا برای کاربر باید فارسی، ساده و قابل فهم باشند.

نمونه صحیح:

```text
امکان ثبت پرداخت وجود ندارد. لطفاً اطلاعات واردشده را بررسی کنید.
```

نمونه غلط:

```text
SQLSTATE[23000]: Integrity constraint violation
```

---

## Rule 3 — Technical Error Must Be Logged

جزئیات فنی خطا فقط باید در Log ذخیره شود.

Log باید شامل موارد زیر باشد:

```text
نوع خطا
پیام خطا
فایل
خط
Route
User ID
Role
IP
User Agent
Request Method
Request Payload امن‌شده
تاریخ و زمان
```

---

## Rule 4 — System Must Fail Gracefully

در صورت بروز خطا، سیستم نباید به صورت کامل از کار بیفتد.

رفتار صحیح:

```text
نمایش پیام مناسب به کاربر
ثبت Log
بازگرداندن کاربر به صفحه مناسب
حفظ داده‌های فرم تا حد امکان
عدم تغییر ناقص وضعیت دیتابیس
```

---

## Rule 5 — Critical Operations Must Be Transactional

تمام عملیات حساس باید در Transaction انجام شوند.

نمونه عملیات حساس:

```text
ثبت قرارداد
تولید اقساط
ثبت پرداخت
تأیید رسید پرداخت
ارجاع به حقوقی
بروزرسانی سیستم
بازیابی بکاپ
ثبت پرونده حقوقی
```

اگر بخشی از عملیات شکست خورد، کل عملیات باید Rollback شود.

---

## Rule 6 — Validation Error Is Not System Error

خطاهای Validation نباید به عنوان خطای سیستمی ثبت شوند مگر مشکوک یا پرتکرار باشند.

مثلاً:

```text
شماره موبایل وارد نشده است.
کد ملی نامعتبر است.
مبلغ پرداختی صحیح نیست.
```

این‌ها خطای کاربر هستند، نه خطای سیستم.

---

## Rule 7 — Security Errors Must Be Logged Separately

خطاهای امنیتی باید در دسته جداگانه ثبت شوند.

نمونه:

```text
CSRF نامعتبر
دسترسی غیرمجاز
تلاش برای مشاهده داده دیگران
آپلود فایل غیرمجاز
تلاش برای تغییر ID در URL
ورود ناموفق مکرر
```

---

# Error Types

## Validation Error

خطای داده ورودی کاربر.

رفتار:

```text
نمایش خطا زیر فیلد مربوطه
حفظ مقدارهای واردشده
عدم ثبت عملیات
```

---

## Permission Error

خطای دسترسی.

رفتار:

```text
نمایش پیام عدم دسترسی
ثبت Security Log
عدم اجرای عملیات
```

پیام پیشنهادی:

```text
شما مجوز دسترسی به این بخش را ندارید.
```

---

## Not Found Error

زمانی که موجودیت وجود ندارد یا کاربر اجازه دیدن آن را ندارد.

رفتار:

```text
نمایش صفحه 404 یا پیام مناسب
عدم افشای وجود داده حساس
```

برای داده‌های حساس، بهتر است بین «وجود ندارد» و «اجازه نداری» تفاوت واضح نمایش داده نشود.

---

## Database Error

خطاهای دیتابیس.

رفتار:

```text
Rollback
ثبت Log کامل
نمایش پیام عمومی
```

پیام کاربر:

```text
در پردازش اطلاعات مشکلی رخ داد. لطفاً دوباره تلاش کنید.
```

---

## Payment Error

خطاهای پرداخت.

رفتار:

```text
عدم تغییر وضعیت قسط بدون تأیید قطعی
ثبت Log
ثبت Timeline در صورت نیاز
نمایش پیام مناسب
```

پیام نمونه:

```text
پرداخت با موفقیت تأیید نشد. در صورت کسر وجه، موضوع را از بخش پشتیبانی پیگیری کنید.
```

---

## File Upload Error

خطاهای آپلود فایل.

موارد:

```text
حجم بیش از حد مجاز
فرمت غیرمجاز
MIME نامعتبر
خطا در ذخیره فایل
فایل خراب
```

رفتار:

```text
حذف فایل موقت
نمایش پیام مناسب
ثبت Log در صورت خطای فنی
```

---

## Update Error

خطاهای بروزرسانی سامانه.

رفتار:

```text
توقف بروزرسانی
Rollback در صورت امکان
خروج از Maintenance Mode
ثبت Log
نمایش گزارش مرحله شکست‌خورده
```

---

## Backup / Restore Error

خطاهای بکاپ و بازیابی.

رفتار:

```text
عدم ادامه عملیات در صورت ناقص بودن بکاپ
ثبت Log
نمایش هشدار واضح
عدم حذف دیتای فعلی مگر Restore کامل و موفق باشد
```

---

# Error Response Standards

## Web Response

برای صفحات معمولی:

```text
Flash Message
Toast
Inline Error
Redirect Safe
```

---

## JSON Response

برای درخواست‌های Ajax/API:

```json
{
  "ok": false,
  "message": "پیام قابل فهم برای کاربر",
  "errors": {},
  "code": "ERROR_CODE"
}
```

---

# Error Codes

هر خطای مهم باید کد مشخص داشته باشد.

نمونه:

```text
VALIDATION_FAILED
PERMISSION_DENIED
RESOURCE_NOT_FOUND
PAYMENT_FAILED
UPLOAD_FAILED
DATABASE_ERROR
UPDATE_FAILED
BACKUP_FAILED
SECURITY_VIOLATION
```

---

# Logging Policy

خطاها باید در دسته‌های زیر ثبت شوند:

```text
application
security
payment
upload
database
update
backup
legal
chat
notification
```

---

# User Experience Rules

## Forms

اگر فرم خطا داشت:

```text
کاربر به همان فرم برگردد.
داده‌های قبلی حفظ شوند.
فیلد دارای خطا مشخص شود.
پیام خطا کنار همان فیلد نمایش داده شود.
```

---

## Modals

اگر عملیات داخل Modal خطا داشت:

```text
Modal بسته نشود.
پیام خطا داخل Modal نمایش داده شود.
دکمه Submit دوباره فعال شود.
```

---

## Payment

اگر پرداخت شکست خورد:

```text
وضعیت قسط نباید پرداخت‌شده شود.
پرداخت باید در حالت ناموفق یا در انتظار باقی بماند.
کاربر پیام واضح ببیند.
```

---

## Card To Card Receipt

اگر آپلود رسید ناموفق بود:

```text
وضعیت قسط تغییر نکند.
رسید ناقص حذف شود.
کاربر امکان تلاش دوباره داشته باشد.
```

---

# Transaction Rules

عملیات زیر باید Transaction داشته باشند:

```text
ثبت قرارداد + اقساط
ویرایش قرارداد + اقساط
ثبت پرداخت + تغییر وضعیت قسط
تأیید رسید + حذف فایل + تغییر وضعیت
رد رسید + حذف فایل + بازگشت وضعیت
ارجاع حقوقی + ایجاد پرونده + اعلان
بروزرسانی سیستم
بازیابی بکاپ
```

---

# Retry Rules

برای عملیات‌هایی که ممکن است موقتاً شکست بخورند، امکان Retry در نظر گرفته شود.

نمونه:

```text
ارسال Notification
ارسال پیام ربات
اجرای Job حذف فایل‌های قدیمی
Backup
```

---

# Silent Failure Is Forbidden

هیچ خطایی نباید بدون Log و بدون اطلاع مناسب نادیده گرفته شود.

ممنوع:

```php
try {
    // code
} catch (Exception $e) {
    // empty
}
```

صحیح:

```php
try {
    // code
} catch (Throwable $e) {
    Logger::error('payment_failed', [
        'message' => $e->getMessage(),
        'context' => $context,
    ]);

    throw new UserFriendlyException('پرداخت با خطا مواجه شد.');
}
```

---

# Implementation Rules

Codex هنگام پیاده‌سازی هر Feature باید بررسی کند:

```text
آیا عملیات ممکن است خطا بدهد؟
آیا خطا قابل نمایش به کاربر است؟
آیا خطای فنی باید Log شود؟
آیا Transaction لازم است؟
آیا Rollback لازم است؟
آیا فایل موقت باید حذف شود؟
آیا وضعیت دیتابیس ممکن است نیمه‌کاره بماند؟
آیا پاسخ Ajax استاندارد است؟
آیا پیام فارسی و قابل فهم است؟
```

---

# Review Checklist

قبل از Merge هر Feature:

```text
□ هیچ Fatal/Warning/Notice قابل مشاهده نیست.
□ تمام Exceptionها مدیریت شده‌اند.
□ Validation خطاهای قابل فهم دارد.
□ خطاهای فنی Log می‌شوند.
□ خطاهای امنیتی جداگانه Log می‌شوند.
□ عملیات حساس Transaction دارد.
□ در صورت شکست عملیات، وضعیت ناقص ایجاد نمی‌شود.
□ خطای Ajax ساختار استاندارد دارد.
□ Modalها در خطا بسته نمی‌شوند.
□ فرم‌ها داده‌های قبلی را حفظ می‌کنند.
□ پیام خطا فارسی و واضح است.
```

---

# Definition of Done

یک Feature از نظر Error Handling زمانی کامل است که:

```text
✔ هیچ خطای خامی به کاربر نمایش داده نشود.
✔ خطاهای فنی Log شوند.
✔ خطاهای کاربر واضح نمایش داده شوند.
✔ عملیات حساس Transaction داشته باشند.
✔ Rollback در صورت نیاز انجام شود.
✔ وضعیت ناقص در دیتابیس ایجاد نشود.
✔ پاسخ‌های JSON استاندارد باشند.
✔ UI بعد از خطا قابل ادامه باشد.
```

---

# Future Considerations

در نسخه‌های آینده، سیستم باید آماده موارد زیر باشد:

```text
Centralized Error Dashboard
Error Code Documentation
Admin Error Viewer
Automatic Error Alert
Telegram/Email Error Notification
Sentry-like Integration
Failed Job Retry
Error Severity Levels
```

---

# End of File
````
