# Proma Pay — Testing Standard

Version: 1.0  
Status: Master Specification  
Priority: CRITICAL

---

# Introduction

این فایل استاندارد رسمی تست در پروژه Proma Pay است.

هدف این سند این است که هیچ Feature، اصلاح، Refactor، Migration، Update یا تغییر UI بدون بررسی و تست وارد پروژه نشود.

تست فقط برای پیدا کردن باگ نیست.

تست یعنی اطمینان از اینکه سیستم بعد از هر تغییر همچنان درست، امن، سریع و قابل اعتماد کار می‌کند.

---

# Constitution

## Rule 1 — No Feature Is Complete Without Testing

هیچ Feature بدون تست کامل محسوب نمی‌شود.

حتی اگر کد اجرا شود، تا زمانی که سناریوهای اصلی، خطاها، دسترسی‌ها، UI و اثرهای جانبی بررسی نشده باشند، Feature کامل نیست.

---

## Rule 2 — Test The Business Flow, Not Just The Button

هدف تست فقط کلیک روی دکمه نیست.

باید بررسی شود که عملیات چه اثری روی کل سیستم دارد.

نمونه:

وقتی رسید پرداخت تأیید می‌شود، فقط پیام موفقیت کافی نیست.

باید بررسی شود:

```text
وضعیت پرداخت تغییر کرده؟
وضعیت قسط تغییر کرده؟
Timeline ثبت شده؟
Notification ارسال شده؟
Bot Message ایجاد شده؟
رسید از سرور حذف شده؟
Log ثبت شده؟
گزارش مالی درست شده؟
```

---

## Rule 3 — Regression Testing Is Mandatory

هر تغییر جدید نباید قابلیت قبلی را خراب کند.

بعد از هر تغییر باید بخش‌های مرتبط دوباره تست شوند.

---

## Rule 4 — Security Testing Is Mandatory

هر Feature باید از نظر امنیتی بررسی شود.

به‌خصوص:

```text
Permission
CSRF
XSS
SQL Injection
File Upload
Direct ID Access
Private File Access
Sensitive Data Exposure
```

---

## Rule 5 — Customer View Must Be Tested Separately

نمای مشتری باید جدا از نمای مدیر تست شود.

مشتری نباید اطلاعات داخلی، سود، فرمول محاسبه، لاگ فنی، فایل‌های دیگران یا داده‌های مدیریتی را ببیند.

---

## Rule 6 — Role-Based Testing Is Mandatory

هر Feature باید با نقش‌های مختلف تست شود.

حداقل نقش‌ها:

```text
super_admin
admin
operator
lawyer
department_manager
customer
```

---

## Rule 7 — Error States Must Be Tested

فقط حالت موفق تست نشود.

تمام حالت‌های خطا باید تست شوند.

نمونه:

```text
فرم ناقص
مبلغ نامعتبر
فایل نامعتبر
Permission نامعتبر
CSRF نامعتبر
پرداخت ناموفق
فایل حذف‌شده
قرارداد ناموجود
قسط پرداخت‌شده
```

---

## Rule 8 — Data Integrity Must Be Protected

بعد از هر عملیات باید بررسی شود که دیتابیس در وضعیت ناقص قرار نگرفته باشد.

عملیات حساس باید Transaction داشته باشند.

---

## Rule 9 — UI Must Be Tested On Desktop, Tablet And Mobile

تمام صفحات، فرم‌ها، جداول و مودال‌ها باید Responsive تست شوند.

---

## Rule 10 — Testing Must Be Documented

نتیجه تست‌های مهم باید در Pull Request، گزارش تغییرات یا مستندات مربوطه ثبت شود.

---

# Testing Types

انواع تست‌های موردنیاز پروژه:

```text
Manual Testing
Functional Testing
Regression Testing
Security Testing
Permission Testing
UI Testing
Responsive Testing
Database Testing
Migration Testing
Payment Testing
File Upload Testing
Notification Testing
Calendar Testing
Chat Testing
Performance Testing
Release Testing
```

---

# Manual Testing

تست دستی برای بررسی رفتار واقعی سیستم انجام می‌شود.

هر Feature باید حداقل یک سناریوی دستی موفق و چند سناریوی خطا داشته باشد.

---

# Functional Testing

تست عملکردی بررسی می‌کند که Feature دقیقاً مطابق نیاز کار می‌کند یا نه.

نمونه:

```text
ثبت مشتری
ثبت قرارداد
تولید اقساط
پرداخت قسط
ارسال رسید
تأیید رسید
ارجاع به حقوقی
ثبت هزینه حقوقی
ثبت رویداد تقویم
```

---

# Regression Testing

هر تغییر باید بخش‌های مرتبط قبلی را هم تست کند.

نمونه:

اگر Payment تغییر کرد، باید این بخش‌ها هم بررسی شوند:

```text
Installments
Contract Financial Summary
Timeline
Notification
Reports
Customer Portal
Legal Referral
```

---

# Security Testing

هر Feature باید از نظر امنیتی تست شود.

موارد الزامی:

```text
CSRF Token
Permission
Role Access
Direct URL Access
Direct ID Access
File Access
XSS
SQL Injection
Session
Sensitive Data
```

---

# Permission Testing

برای هر Feature باید تست شود:

```text
نقش مجاز می‌تواند عملیات را انجام دهد.
نقش غیرمجاز نمی‌تواند عملیات را انجام دهد.
مشتری فقط داده خودش را می‌بیند.
اپراتور فقط داده مجاز خودش را می‌بیند.
وکیل فقط پرونده‌های مجاز را می‌بیند.
مدیر واحد فقط داده واحد خودش را می‌بیند.
```

---

# UI Testing

تمام UI باید با قوانین UI Constitution تست شود.

موارد الزامی:

```text
RTL
Yekan Bakh
Required *
Validation
Toast
Modal
Confirmation
Skeleton Loading
Empty State
Icon Buttons
Async Search Input
Date Picker Icon Inside Input
Responsive Layout
```

---

# Responsive Testing

حداقل اندازه‌های تست:

```text
Desktop: 1366px and above
Laptop: 1024px
Tablet: 768px
Mobile: 375px
Small Mobile: 320px
```

در موبایل نباید:

```text
متن از صفحه بیرون بزند.
جدول صفحه را خراب کند.
مودال غیرقابل استفاده شود.
دکمه‌ها روی هم بیفتند.
Sidebar باعث شکست Layout شود.
Inputها بیش از حد کوچک شوند.
```

---

# Database Testing

بعد از هر تغییر دیتابیس باید بررسی شود:

```text
Migration اجرا می‌شود.
Rollback در صورت نیاز ممکن است.
Indexهای لازم وجود دارند.
Foreign Keyها در صورت استفاده درست هستند.
Data Typeها درست هستند.
Money با DECIMAL ذخیره می‌شود.
تاریخ‌ها درست ذخیره می‌شوند.
Soft Delete درست کار می‌کند.
Queryها SELECT * ندارند.
```

---

# Migration Testing

هر Migration باید روی دیتابیس خالی و دیتابیس دارای داده تست شود.

سناریوهای الزامی:

```text
Fresh Install
Existing Install
Update From Previous Version
Migration Re-run Safety
Backup Before Migration
No Data Loss
```

Migration نباید شامل موارد خطرناک باشد:

```text
DROP DATABASE
TRUNCATE
DROP TABLE uncontrolled
DELETE without condition
Unsafe ALTER without backup
```

---

# Payment Testing

پرداخت از حساس‌ترین بخش‌های سیستم است.

تمام تغییرات مربوط به پرداخت باید دقیق تست شوند.

## Gateway Payment Tests

باید بررسی شود:

```text
درخواست پرداخت ساخته می‌شود.
Callback معتبر بررسی می‌شود.
Callback نامعتبر رد می‌شود.
پرداخت بدون تأیید Gateway موفق نمی‌شود.
وضعیت قسط فقط بعد از تأیید تغییر می‌کند.
Timeline ثبت می‌شود.
Notification ارسال می‌شود.
Log مالی ثبت می‌شود.
```

---

## Card To Card Payment Tests

باید بررسی شود:

```text
اگر کارت‌به‌کارت غیرفعال است نمایش داده نشود.
اطلاعات کارت از Settings خوانده شود.
رسید با فرمت مجاز آپلود شود.
رسید بیشتر از 1MB رد شود.
پرداخت در وضعیت در حال بررسی قرار گیرد.
مدیر بتواند رسید را ببیند.
تأیید رسید وضعیت پرداخت را تغییر دهد.
رد رسید وضعیت را برگرداند.
فایل رسید بعد از تأیید یا رد حذف شود.
Customer Notification ارسال شود.
Bot Message ارسال شود.
Financial Log ثبت شود.
```

---

# Installment Testing

برای اقساط باید بررسی شود:

```text
اقساط درست تولید می‌شوند.
مبلغ‌ها درست هستند.
تاریخ سررسید درست است.
قسط پرداخت‌شده دوباره پرداخت نمی‌شود.
قسط معوق درست تشخیص داده می‌شود.
جریمه فقط روی اقساط معوق محاسبه می‌شود.
تخفیف تسویه فقط در تسویه کامل اعمال می‌شود.
اقساط سفارشی با توضیح اجباری ثبت می‌شوند.
اقساط سفارشی در چاپ دفترچه می‌آیند.
```

---

# Contract Testing

برای قرارداد باید بررسی شود:

```text
ثبت قرارداد موفق است.
چند کالا قابل ثبت است.
IMEI 1 و IMEI 2 ذخیره می‌شوند.
ضمانت‌ها ذخیره می‌شوند.
ضامن‌ها ذخیره می‌شوند.
اقساط ساخته می‌شوند.
قالب قرارداد متغیرها را درست جایگزین می‌کند.
PDF قرارداد درست تولید می‌شود.
امضای ضامن‌ها در صورت وجود نمایش داده می‌شود.
مشتری فقط قرارداد خودش را می‌بیند.
اطلاعات داخلی سود به مشتری نمایش داده نمی‌شود.
```

---

# Customer Testing

برای مشتری باید بررسی شود:

```text
ثبت مشتری
ویرایش مشتری
آپلود مدرک هویتی
تأیید مدرک
رد مدرک
نمایش تیک آبی
حذف نشدن مشتری دارای قرارداد فعال
نمایش مدال‌ها
نمایش Timeline ساده‌شده
```

---

# Identity Document Testing

باید بررسی شود:

```text
فقط jpg/png/webp مجاز است.
حجم بیش از 1MB رد می‌شود.
مدرک جدید pending می‌شود.
مدرک قبلی تا زمان تأیید باقی می‌ماند.
در تأیید، مدرک قبلی حذف می‌شود.
در رد، مدرک جدید حذف می‌شود.
تیک آبی فقط بعد از تأیید فعال می‌شود.
Notification و Bot Message ارسال می‌شود.
Audit Log ثبت می‌شود.
```

---

# File Upload Testing

برای هر آپلود باید بررسی شود:

```text
Extension
MIME Type
Size
Safe File Name
Private Storage
Permission
Metadata
Preview
Download
Delete Lifecycle
```

تست‌های منفی:

```text
آپلود php
آپلود فایل با پسوند جعلی
آپلود فایل بزرگ
آپلود فایل خراب
دسترسی مستقیم به مسیر فایل
دانلود فایل دیگران
```

---

# Chat Testing

برای چت باید بررسی شود:

```text
مشتری فقط با واحدها گفتگو کند.
کاربران داخلی بتوانند طبق Permission گفتگو کنند.
کانال عمومی فقط برای اطلاع‌رسانی عمومی باشد.
اطلاعات خصوصی در کانال عمومی ارسال نشود.
تصویر چت max 1MB باشد.
تصویر چت بعد از 7 روز حذف شود.
پیام بعد از حذف تصویر باقی بماند.
Bot Message درست نمایش داده شود.
```

---

# Notification Testing

باید بررسی شود:

```text
اعلان برای گیرنده درست ارسال می‌شود.
اعلان خصوصی عمومی نمی‌شود.
Badge Count درست است.
Read/Unread درست است.
Sound طبق Settings پخش می‌شود.
اعلان تکراری ساخته نمی‌شود.
لینک مقصد درست است.
Permission مقصد بررسی می‌شود.
```

---

# Calendar Testing

باید بررسی شود:

```text
سررسید اقساط در تقویم نمایش داده شود.
مشتری فقط اقساط خودش را ببیند.
وکیل جلسات حقوقی مجاز را ببیند.
اپراتور پیگیری‌های خودش را ببیند.
یادآوری‌ها تکراری ارسال نشوند.
دکمه‌های ماه قبل/بعد درست کار کنند.
Date Picker داخل Input درست نمایش داده شود.
```

---

# Legal Testing

برای بخش حقوقی باید بررسی شود:

```text
پرونده آماده شکایت نمایش داده شود.
پرونده ارجاع‌شده نمایش داده شود.
پرونده شکایت‌شده نمایش داده شود.
ثبت اقدام حقوقی
ثبت هزینه حقوقی
آپلود فایل حقوقی
ثبت جلسه دادگاه
ایجاد Calendar Event
ارسال Notification
ثبت Legal Log
محدودیت دسترسی وکیل
```

---

# Settings Testing

برای تنظیمات باید بررسی شود:

```text
تب‌ها درست کار می‌کنند.
تنظیمات عمومی ذخیره می‌شود.
لوگو آپلود می‌شود.
شماره کارت ذخیره می‌شود.
قالب قرارداد ذخیره می‌شود.
تنظیمات مالی ذخیره می‌شود.
تنظیمات چت ذخیره می‌شود.
تنظیمات اعلان ذخیره می‌شود.
تغییر تنظیمات حساس Audit Log دارد.
```

---

# Backup Testing

باید بررسی شود:

```text
Backup دیتابیس ساخته می‌شود.
Backup فایل‌ها ساخته می‌شود.
Backup تنظیمات ساخته می‌شود.
اگر ZipArchive نبود سیستم Fatal ندهد.
Fallback Backup کار کند.
دانلود Backup فقط برای مدیر مجاز باشد.
دانلود Backup Audit شود.
Restore بدون بکاپ معتبر انجام نشود.
```

---

# Update Testing

باید بررسی شود:

```text
Update ZIP آپلود می‌شود.
update.json وجود دارد.
نسخه بررسی می‌شود.
فایل‌های خطرناک رد می‌شوند.
SQL خطرناک رد می‌شود.
قبل از Update بکاپ گرفته می‌شود.
Maintenance Mode فعال می‌شود.
Migration اجرا می‌شود.
در خطا Rollback انجام می‌شود.
Maintenance Mode خاموش می‌شود.
Update Log ثبت می‌شود.
```

---

# Report Testing

باید بررسی شود:

```text
گزارش‌ها بر اساس داده واقعی هستند.
فیلترها درست کار می‌کنند.
Pagination وجود دارد.
Export درست است.
Permission رعایت می‌شود.
مشتری گزارش داخلی نمی‌بیند.
گزارش مالی با پرداخت‌ها هماهنگ است.
```

---

# Performance Testing

حداقل موارد:

```text
صفحات لیستی Pagination دارند.
Query تکراری وجود ندارد.
N+1 وجود ندارد.
SELECT * وجود ندارد.
جستجو سریع است.
Assetها سنگین نیستند.
تصاویر Optimize شده‌اند.
```

صفحات مهم:

```text
Dashboard
Customers
Contracts
Installments
Payments
Legal
Chat
Calendar
Reports
Settings
```

---

# Browser Testing

حداقل مرورگرهای هدف:

```text
Chrome
Edge
Firefox
Mobile Chrome
Mobile Safari در صورت امکان
```

---

# PHP Version Testing

پروژه باید حداقل با PHP 7.4 سازگار باشد.

اگر از تابعی استفاده می‌شود که در PHP 7.4 وجود ندارد، باید Polyfill یا جایگزین امن داشته باشد.

نمونه:

```text
array_is_list
```

---

# Shared Hosting Testing

چون پروژه باید روی هاست اشتراکی قابل اجرا باشد، باید بررسی شود:

```text
بدون دسترسی SSH هم قابل نصب باشد.
وابستگی سنگین اجباری نداشته باشد.
ZipArchive نبود Fatal ندهد.
Queue سنگین اجباری نباشد.
Cron قابل تنظیم باشد.
Memory مصرفی کنترل شده باشد.
```

---

# Test Data

برای تست باید داده نمونه وجود داشته باشد:

```text
مشتری بدون قرارداد
مشتری دارای قرارداد فعال
مشتری دارای قسط معوق
مشتری دارای مدرک تأییدشده
مشتری دارای مدرک pending
قرارداد با چند کالا
قرارداد با ضامن
قرارداد با چک
قرارداد با سفته
قسط پرداخت‌شده
قسط معوق
پرونده حقوقی
کاربر اپراتور
کاربر وکیل
کاربر مدیر واحد
```

---

# Bug Fix Testing

برای هر Bug Fix باید:

```text
علت اصلی مشخص شود.
سناریوی تکرار باگ ثبت شود.
اصلاح انجام شود.
همان سناریو دوباره تست شود.
Regression بخش مرتبط انجام شود.
```

---

# Refactor Testing

Refactor نباید رفتار سیستم را تغییر دهد مگر هدف آن مشخص باشد.

بعد از Refactor باید تست شود:

```text
رفتار قبلی حفظ شده؟
Permissionها حفظ شده؟
Queryها تغییر خطرناک نداشته؟
UI نشکسته؟
Performance بدتر نشده؟
```

---

# Acceptance Checklist Template

برای هر Feature این چک‌لیست تکمیل شود:

```text
Feature Name:
Changed Files:
Affected Modules:
Affected Tables:
Affected Permissions:
Affected Events:
Affected Notifications:
Affected Logs:
Affected Reports:

Manual Test:
Security Test:
Permission Test:
Responsive Test:
Regression Test:
Result:
Known Issues:
```

---

# Forbidden Patterns

موارد زیر ممنوع هستند:

```text
تغییر مستقیم کد بدون تست
تست فقط با admin
نادیده گرفتن نقش customer
نادیده گرفتن Permission
نادیده گرفتن حالت خطا
نادیده گرفتن موبایل
نادیده گرفتن Migration
نادیده گرفتن فایل‌های Private
نادیده گرفتن Notification و Log
```

---

# Implementation Rules

Codex هنگام پیاده‌سازی هر Feature باید بررسی کند:

```text
سناریوی موفق چیست؟
سناریوهای خطا چیست؟
چه نقش‌هایی باید تست شوند؟
چه داده‌هایی تحت تأثیر هستند؟
آیا Migration نیاز به تست دارد؟
آیا فایل Upload دارد؟
آیا پرداخت دارد؟
آیا Notification دارد؟
آیا Calendar دارد؟
آیا Log دارد؟
آیا Report تحت تأثیر است؟
آیا Customer View تحت تأثیر است؟
```

---

# Review Checklist

قبل از Merge هر Feature:

```text
□ سناریوی موفق تست شد.
□ سناریوهای خطا تست شدند.
□ Permission تست شد.
□ Customer View تست شد.
□ Admin View تست شد.
□ Operator/Lawyer در صورت ارتباط تست شد.
□ Responsive تست شد.
□ Security تست شد.
□ Regression تست شد.
□ Migration تست شد.
□ Notification/Timeline/Log بررسی شد.
□ Performance افت نکرده است.
□ نتیجه تست ثبت شده است.
```

---

# Definition of Done

یک Feature از نظر Testing زمانی کامل است که:

```text
✔ سناریوی اصلی آن کار کند.
✔ سناریوهای خطای آن کنترل شوند.
✔ نقش‌های مجاز و غیرمجاز تست شوند.
✔ مشتری فقط داده خودش را ببیند.
✔ UI در موبایل و دسکتاپ درست باشد.
✔ دیتابیس وضعیت ناقص نداشته باشد.
✔ Notification، Timeline و Log در صورت نیاز درست باشند.
✔ Regression بخش‌های مرتبط انجام شده باشد.
✔ خطای امنیتی واضح وجود نداشته باشد.
✔ نتیجه تست مستند شده باشد.
```

---

# Future Considerations

سیستم باید در آینده آماده موارد زیر باشد:

```text
Automated Unit Tests
Integration Tests
Browser Tests
Role-Based Test Suite
Payment Gateway Sandbox Tests
Migration Test Runner
Security Test Suite
Performance Benchmarking
Visual Regression Testing
CI Pipeline
Release Test Automation
Demo Data Generator
Test Report Dashboard
```

---

# End of File