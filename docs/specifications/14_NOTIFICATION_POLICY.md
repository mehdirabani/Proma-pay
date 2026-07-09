# جلد ۱۵

## نام فایل

```text
14_NOTIFICATION_POLICY.md
```

````md
# Proma Pay — Notification Policy

Version: 1.0  
Status: Master Specification  
Priority: CRITICAL

---

# Introduction

این فایل استاندارد رسمی اعلان‌ها در پروژه Proma Pay است.

هدف این سند این است که تمام پیام‌ها، هشدارها، یادآوری‌ها، اعلان‌های سیستمی، اعلان‌های مدیریتی و پیام‌های ربات به شکل استاندارد، امن، قابل ردیابی و قابل فهم مدیریت شوند.

Notification فقط یک پیام ساده نیست.

Notification بخشی از رفتار رسمی سیستم است.

---

# Constitution

## Rule 1 — Every Important Event Must Notify The Right Person

هر رویداد مهم باید به شخص یا نقش درست اطلاع داده شود.

اعلان نباید عمومی، تصادفی یا بدون هدف ارسال شود.

هر Notification باید پاسخ این سؤال‌ها را داشته باشد:

```text
چه اتفاقی افتاده؟
برای چه کسی مهم است؟
کاربر باید بعد از دیدن آن چه کاری انجام دهد؟
لینک مقصد کجاست؟
```

---

## Rule 2 — Private Events Must Stay Private

هیچ اعلان شخصی نباید در کانال عمومی منتشر شود.

موارد خصوصی:

```text
تأیید پرداخت مشتری
رد پرداخت مشتری
تأیید مدرک هویتی
رد مدرک هویتی
وضعیت پرونده حقوقی
جزئیات قرارداد
جزئیات اقساط
مبلغ بدهی
مبلغ جریمه
اطلاعات ضامن
اطلاعات مدارک هویتی
```

این موارد فقط باید برای کاربر مرتبط یا نقش‌های مجاز ارسال شوند.

---

## Rule 3 — Notifications Must Be Actionable

هر اعلان باید در صورت امکان لینک مقصد داشته باشد.

نمونه:

```text
رسید پرداخت جدید ارسال شد.
```

باید لینک دهد به:

```text
Review Center > Payment Receipts
```

نمونه غلط:

```text
یک اتفاق افتاد.
```

---

## Rule 4 — Notification Must Not Replace Logs

Notification برای اطلاع‌رسانی است.

Log برای ردیابی و اثبات است.

هر رویداد مهم ممکن است هم Notification داشته باشد و هم Log.

اما Notification جای Log را نمی‌گیرد.

---

## Rule 5 — No Duplicate Notifications

سیستم نباید برای یک رویداد واحد چند اعلان تکراری بسازد.

برای جلوگیری از تکرار باید از کلید یکتا استفاده شود.

نمونه:

```text
event_type + entity_type + entity_id + recipient_id
```

---

## Rule 6 — Notification Must Respect RBAC

کاربر فقط اعلان‌هایی را می‌بیند که اجازه مشاهده مقصد آن را دارد.

اگر کاربر اجازه دیدن قرارداد، پرداخت یا پرونده را ندارد، نباید اعلان آن را ببیند.

---

# Notification Types

انواع اعلان‌های سیستم:

```text
system
payment
receipt_review
identity_review
contract
installment
overdue
legal
calendar
chat
backup
update
security
report
```

---

# Notification Channels

سیستم می‌تواند از کانال‌های زیر استفاده کند:

```text
In-App Notification
System Bot Message
Sound Alert
Public Announcement Channel
Email
SMS
Telegram
```

در نسخه فعلی تمرکز اصلی روی موارد زیر است:

```text
In-App Notification
System Bot Message
Sound Alert
Public Announcement Channel
```

---

# In-App Notification

اعلان‌های درون‌برنامه‌ای باید شامل موارد زیر باشند:

```text
id
recipient_user_id
recipient_customer_id
recipient_role
type
title
message
link
entity_type
entity_id
is_read
read_at
created_at
metadata
```

---

# Required Notification Fields

هر Notification باید تا حد امکان شامل موارد زیر باشد:

```text
گیرنده
نوع اعلان
عنوان
متن کوتاه
لینک مقصد
موجودیت مرتبط
شناسه موجودیت
وضعیت خوانده‌شده
زمان ایجاد
داده تکمیلی
```

---

# Notification Display Rules

اعلان‌ها باید:

```text
Badge Count
Read / Unread State
Relative Time
Icon
Type Color
Click Destination
Mark As Read
Mark All As Read
```

داشته باشند.

نمونه زمان نسبی:

```text
همین الان
۳ دقیقه پیش
۲ ساعت پیش
دیروز
۵ روز پیش
```

---

# Notification Sound Rules

اگر صدای اعلان فعال باشد:

```text
فقط برای اعلان جدید پخش شود.
اگر اعلان قبلاً دیده شده، صدا پخش نشود.
برای اعلان‌های تکراری صدا پخش نشود.
کاربر بتواند صدا را خاموش کند.
کاربر بتواند حجم صدا را تنظیم کند.
```

تنظیمات صدا باید از Settings خوانده شود.

---

# Notification Preferences

کاربر باید بتواند برخی اعلان‌ها را کنترل کند.

تنظیمات قابل پشتیبانی:

```text
فعال/غیرفعال بودن صدای اعلان
انتخاب صدای اعلان
حجم صدا
اعلان‌های چت
اعلان‌های تقویم
اعلان‌های پرداخت
اعلان‌های سیستمی
```

اعلان‌های حیاتی امنیتی یا مالی نباید کاملاً قابل غیرفعال‌سازی باشند، مگر فقط صدای آن‌ها.

---

# System Bot

ربات سیستم برای ارسال پیام‌های شخصی و سیستمی استفاده می‌شود.

ربات باید ظاهر مشخص داشته باشد:

```text
نام: ربات سیستم
آواتار اختصاصی
استایل متفاوت
غیرقابل پاسخ مستقیم در پیام‌های سیستمی حساس
```

---

# Bot Message Rules

ربات می‌تواند پیام‌های زیر را ارسال کند:

```text
پرداخت شما تأیید شد.
پرداخت شما رد شد.
مدرک هویتی شما تأیید شد.
مدرک هویتی شما رد شد.
پرونده شما به واحد حقوقی ارجاع شد.
جلسه دادگاه برای شما ثبت شد.
قسط شما نزدیک به سررسید است.
قسط شما معوق شده است.
```

ربات نباید اطلاعات شخصی دیگران را ارسال کند.

---

# Public Announcement Channel

کانال اطلاع‌رسانی عمومی فقط برای اخبار عمومی سیستم است.

موارد مجاز:

```text
اطلاعیه عمومی
خبر بروزرسانی
تغییر ساعت کاری
راهنمای استفاده
اعلام ویژگی جدید
اطلاع‌رسانی عمومی فروشگاه
```

موارد ممنوع:

```text
اطلاعات پرداخت شخصی
اطلاعات قرارداد شخصی
اطلاعات حقوقی شخصی
اطلاعات مدرک هویتی
اعلان تأیید یا رد رسید
اعلان تأیید یا رد مدارک
```

---

# Event To Notification Mapping

هر رویداد مهم باید مشخص کند چه اعلان‌هایی ایجاد می‌شود.

## Payment Receipt Uploaded

گیرنده:

```text
Admin
Payment Manager
```

اعلان:

```text
رسید پرداخت جدید برای بررسی ارسال شد.
```

لینک:

```text
Review Center > Payment Receipts
```

---

## Payment Receipt Approved

گیرنده:

```text
Customer
```

اعلان:

```text
پرداخت شما تأیید شد.
```

اثرها:

```text
Notification
Bot Message
Timeline
Log
```

---

## Payment Receipt Rejected

گیرنده:

```text
Customer
```

اعلان:

```text
رسید پرداخت شما رد شد. لطفاً مجدداً اقدام کنید.
```

اثرها:

```text
Notification
Bot Message
Timeline
Log
```

---

## Identity Document Uploaded

گیرنده:

```text
Admin
Review Manager
```

اعلان:

```text
مدرک هویتی جدید برای بررسی ارسال شد.
```

لینک:

```text
Review Center > Identity Documents
```

---

## Identity Document Approved

گیرنده:

```text
Customer
```

اعلان:

```text
مدرک هویتی شما تأیید شد.
```

اثرها:

```text
Blue Tick فعال شود
Notification
Bot Message
Timeline
Log
```

---

## Identity Document Rejected

گیرنده:

```text
Customer
```

اعلان:

```text
مدرک هویتی شما رد شد.
```

اثرها:

```text
Notification
Bot Message
Timeline
Log
```

---

## Installment Due Soon

گیرنده:

```text
Customer
```

اعلان:

```text
قسط شما به‌زودی سررسید می‌شود.
```

زمان ارسال باید از Settings قابل تنظیم باشد.

---

## Installment Overdue

گیرنده:

```text
Customer
Operator
Admin
```

اعلان:

```text
قسط وارد وضعیت معوق شد.
```

اپراتور فقط اقساطی را ببیند که مجاز به پیگیری آن‌هاست.

---

## Legal Referral

گیرنده:

```text
Lawyer
Admin
Customer در صورت نیاز
```

اعلان:

```text
پرونده به واحد حقوقی ارجاع شد.
```

---

## Court Date Created

گیرنده:

```text
Assigned Lawyer
Admin
Customer در صورت نیاز
```

اعلان:

```text
جلسه دادگاه در تقویم ثبت شد.
```

همزمان باید Calendar Event هم ایجاد شود.

---

## Backup Completed

گیرنده:

```text
Admin
```

اعلان:

```text
بکاپ با موفقیت انجام شد.
```

---

## Backup Failed

گیرنده:

```text
Admin
```

اعلان:

```text
بکاپ با خطا مواجه شد.
```

سطح اعلان:

```text
critical
```

---

## Update Completed

گیرنده:

```text
Admin
```

اعلان:

```text
بروزرسانی سیستم با موفقیت انجام شد.
```

---

## Update Failed

گیرنده:

```text
Admin
```

اعلان:

```text
بروزرسانی سیستم با خطا مواجه شد.
```

سطح اعلان:

```text
critical
```

---

# Notification Priority

سطوح اهمیت اعلان:

```text
low
normal
high
critical
```

---

## Low

نمونه:

```text
اعلان عمومی
خبر جدید
```

---

## Normal

نمونه:

```text
پیام جدید
رویداد جدید
```

---

## High

نمونه:

```text
رسید پرداخت جدید
مدرک هویتی جدید
قسط نزدیک سررسید
```

---

## Critical

نمونه:

```text
خطای بکاپ
خطای بروزرسانی
رخداد امنیتی
قسط بسیار معوق
```

---

# Notification Storage Rules

اعلان‌ها باید در دیتابیس ذخیره شوند.

اعلان‌های خوانده‌شده نباید فوراً حذف شوند.

پیشنهاد نگهداری:

```text
Unread Notifications: تا زمان خواندن
Read Notifications: حداقل 90 روز
Critical Notifications: حداقل 1 سال
Financial/Legal Notifications: حداقل 1 سال
```

---

# Notification Cleanup

پاکسازی اعلان‌ها باید با Job انجام شود.

حذف مستقیم از Controller ممنوع است.

---

# Notification Creation Rules

ایجاد اعلان نباید در View انجام شود.

ایجاد اعلان باید از طریق Service انجام شود.

سرویس پیشنهادی:

```text
NotificationService
BotMessageService
NotificationPreferenceService
```

---

# Notification Service Responsibilities

NotificationService مسئول موارد زیر است:

```text
ایجاد اعلان
جلوگیری از تکرار اعلان
بررسی گیرنده مجاز
ثبت لینک مقصد
ثبت entity_type و entity_id
ثبت priority
ثبت metadata
```

---

# BotMessageService Responsibilities

BotMessageService مسئول موارد زیر است:

```text
ارسال پیام ربات
تشخیص گیرنده
اتصال پیام به چت مناسب
جلوگیری از انتشار عمومی اطلاعات خصوصی
```

---

# Notification Preference Service Responsibilities

NotificationPreferenceService مسئول موارد زیر است:

```text
خواندن تنظیمات اعلان کاربر
تشخیص فعال بودن صدا
تشخیص نوع اعلان مجاز
مدیریت تنظیمات اعلان
```

---

# Duplicate Prevention

برای جلوگیری از اعلان تکراری باید از کلید یکتا استفاده شود.

نمونه:

```text
installment_due_soon:installment_id:user_id:date
payment_receipt_uploaded:receipt_id:admin_group
identity_document_uploaded:document_id:admin_group
```

اگر اعلان مشابه قبلاً ایجاد شده باشد، نباید دوباره ایجاد شود مگر وضعیت تغییر کرده باشد.

---

# Read State Rules

اعلان‌ها باید وضعیت خوانده‌شده داشته باشند.

رفتارها:

```text
کلیک روی اعلان = خوانده‌شده
Mark All As Read = همه خوانده شوند
اعلان جدید = unread
اعلان critical = تا زمان کلیک مستقیم unread بماند
```

---

# UI Rules

مرکز اعلان‌ها باید شامل موارد زیر باشد:

```text
لیست اعلان‌ها
فیلتر خوانده‌نشده
فیلتر نوع اعلان
Badge تعداد اعلان‌های خوانده‌نشده
آیکون متناسب با نوع اعلان
زمان نسبی
لینک مقصد
دکمه Mark All As Read
```

---

# Security Rules

اعلان‌ها باید Security-Aware باشند.

موارد الزامی:

```text
بررسی مالکیت اعلان
بررسی Permission مقصد
عدم نمایش اطلاعات حساس در متن اعلان
عدم افشای IDهای داخلی غیرضروری
```

نمونه متن امن:

```text
یک رسید پرداخت جدید برای بررسی ارسال شد.
```

نمونه متن ناامن:

```text
رسید پرداخت مشتری با کد ملی 1234567890 برای قرارداد 246 و مبلغ 58,750,000 ریال ارسال شد.
```

---

# Performance Rules

اعلان‌ها باید با Pagination نمایش داده شوند.

Badge Count باید سبک و بهینه باشد.

Polling سنگین ممنوع است.

ترجیح:

```text
WebSocket
Server-Sent Events
Lightweight Polling فقط در صورت نبود راهکار بهتر
```

---

# Calendar Reminder Rules

یادآوری‌های تقویم باید اعلان ایجاد کنند.

برای جلوگیری از تکرار، هر Reminder باید ثبت شود.

فیلد پیشنهادی:

```text
reminder_sent_at
```

یا جدول:

```text
calendar_reminder_logs
```

---

# Job And Retry Rules

ارسال اعلان‌هایی که ممکن است شکست بخورند باید قابل Retry باشند.

نمونه:

```text
Bot Message
Email
SMS
Telegram
```

اگر ارسال خارجی شکست خورد، In-App Notification همچنان باید ثبت شود.

---

# Implementation Rules

Codex هنگام پیاده‌سازی هر Feature باید بررسی کند:

```text
آیا این عملیات نیاز به Notification دارد؟
گیرنده دقیق کیست؟
آیا اعلان خصوصی است یا عمومی؟
آیا Bot Message لازم است؟
آیا لینک مقصد لازم است؟
آیا Calendar Reminder لازم است؟
آیا اعلان ممکن است تکراری شود؟
آیا Permission مقصد بررسی شده؟
آیا متن اعلان اطلاعات حساس ندارد؟
آیا اعلان باید صدا داشته باشد؟
آیا priority مشخص شده؟
```

---

# Review Checklist

قبل از Merge هر Feature:

```text
□ رویدادهای مهم Notification دارند.
□ اعلان‌ها گیرنده درست دارند.
□ اعلان خصوصی در کانال عمومی منتشر نمی‌شود.
□ متن اعلان اطلاعات حساس ندارد.
□ لینک مقصد درست است.
□ Notification با Permission مقصد سازگار است.
□ اعلان تکراری ساخته نمی‌شود.
□ Badge Count درست کار می‌کند.
□ Read/Unread درست کار می‌کند.
□ Sound طبق تنظیمات کاربر اجرا می‌شود.
□ Bot Message در صورت نیاز ایجاد می‌شود.
□ Calendar Reminder در صورت نیاز ایجاد می‌شود.
□ Notification Log یا Audit لازم ثبت شده است.
```

---

# Definition of Done

یک Feature از نظر Notification زمانی کامل است که:

```text
✔ رویدادهای مهم آن اعلان مناسب ایجاد کنند.
✔ اعلان‌ها به گیرنده درست ارسال شوند.
✔ اعلان خصوصی عمومی نشود.
✔ اعلان لینک مقصد داشته باشد.
✔ اعلان با RBAC سازگار باشد.
✔ اعلان تکراری ایجاد نشود.
✔ Badge و Read State درست باشد.
✔ Bot Message در صورت نیاز ارسال شود.
✔ Sound طبق تنظیمات اجرا شود.
✔ متن اعلان فارسی، کوتاه و قابل فهم باشد.
```

---

# Future Considerations

سیستم باید در آینده آماده موارد زیر باشد:

```text
SMS Notification
Email Notification
Telegram Notification
Push Notification
Mobile App Notification
Notification Templates
Notification Rules Engine
User Notification Preferences
Notification Analytics
Notification Delivery Status
Queued Notifications
WebSocket Notification Server
```

---

# End of File
````
