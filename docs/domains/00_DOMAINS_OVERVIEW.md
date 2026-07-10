# Proma Pay — Domains Overview

Version: 1.0  
Status: Domain Documentation  
Priority: CRITICAL

---

# Introduction

این فایل نقشه کلی دامنه‌های اصلی پروژه Proma Pay را تعریف می‌کند.

هدف این سند این است که Codex و توسعه‌دهندگان بدانند هر بخش از سیستم متعلق به کدام Domain است، چه مسئولیتی دارد، با چه Domainهای دیگری ارتباط دارد، و مرز آن کجاست.

از این مرحله به بعد، مستندات وارد سطح Domain می‌شوند.

فایل‌های داخل پوشه `domains` قوانین جزئی هر بخش واقعی سیستم را تعریف می‌کنند.

---

# Difference Between Specifications And Domains

## Specifications

فایل‌های داخل مسیر زیر:

```text
docs/specifications/
```

قوانین سراسری سیستم هستند.

نمونه:

```text
Security
Performance
RBAC
UI
Database
Event Architecture
Plugin Architecture
Release
```

این قوانین روی کل پروژه اعمال می‌شوند.

---

## Domains

فایل‌های داخل مسیر زیر:

```text
docs/domains/
```

رفتار هر بخش اصلی کسب‌وکار را تعریف می‌کنند.

نمونه:

```text
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

هر Domain باید از قوانین `specifications` تبعیت کند.

---

# Core Rule

هیچ Domain نباید قوانین سراسری پروژه را نقض کند.

هر Domain باید با اسناد زیر سازگار باشد:

```text
docs/specifications/00_PROJECT_OVERVIEW.md
docs/specifications/04_BUSINESS_RULES.md
docs/specifications/07_UI_CONSTITUTION.md
docs/specifications/08_SECURITY_CONSTITUTION.md
docs/specifications/11_SYSTEM_CONSTITUTION.md
docs/specifications/15_EVENT_ARCHITECTURE.md
docs/specifications/17_RBAC_AND_PERMISSION_POLICY.md
docs/specifications/20_PLUGIN_AND_MODULE_ARCHITECTURE.md
```

---

# Domain Design Philosophy

هر Domain باید:

```text
مسئولیت مشخص داشته باشد.
Business Logic خودش را داشته باشد.
Serviceهای خودش را داشته باشد.
Repositoryهای خودش را داشته باشد.
Eventهای خودش را تعریف کند.
Permissionهای خودش را مشخص کند.
UI و Module مربوط به خودش را تغذیه کند.
با سایر Domainها از طریق Service، Event یا Contract ارتباط بگیرد.
```

---

# Forbidden Domain Design

موارد زیر ممنوع هستند:

```text
Business Logic داخل Controller
Business Logic داخل View
Query مستقیم داخل View
محاسبات مالی پراکنده در چند فایل
Permission پراکنده در فایل‌های مختلف
وابستگی مستقیم و سنگین بین Domainها
کپی کردن منطق یک Domain در Domain دیگر
تغییر وضعیت Domain دیگر بدون Service یا Event
```

---

# Correct Domain Design

الگوی صحیح:

```text
Controller
↓
Request Validation
↓
Permission Check
↓
Domain Service
↓
Repository
↓
Event Dispatch
↓
Listeners
↓
Notification / Timeline / Log / Calendar / Report
```

---

# Official Domains

دامنه‌های رسمی پروژه Proma Pay:

```text
01_CUSTOMERS.md
02_USERS_AND_ROLES.md
03_CONTRACTS.md
04_INSTALLMENTS.md
05_PAYMENTS.md
06_FINANCIAL_CALCULATIONS.md
07_LEGAL.md
08_CHAT.md
09_NOTIFICATIONS.md
10_CALENDAR.md
11_FILES.md
12_SETTINGS.md
13_REPORTS.md
14_BACKUP_AND_UPDATE.md
15_PLUGINS.md
```

---

# Domain List And Responsibility

## Customers Domain

مسئول مدیریت مشتریان است.

شامل:

```text
ثبت مشتری
ویرایش مشتری
پروفایل مشتری
مدارک هویتی مشتری
تیک آبی
مدال‌ها
وضعیت مشتری
ارتباط مشتری با قراردادها
Timeline مشتری
حذف یا غیرفعال‌سازی مشتری
```

فایل مربوط:

```text
01_CUSTOMERS.md
```

---

## Users And Roles Domain

مسئول کاربران داخلی و نقش‌های سازمانی است.

شامل:

```text
مدیر
مدیر واحد
اپراتور
وکیل
پشتیبانی
حسابدار
کارمند
سطوح دسترسی
واحدها
فعال یا غیرفعال بودن کاربر
```

فایل مربوط:

```text
02_USERS_AND_ROLES.md
```

---

## Contracts Domain

مسئول قراردادهای فروش اقساطی یا اجاره به شرط تملیک است.

شامل:

```text
ثبت قرارداد
چند کالا در قرارداد
IMEI 1
IMEI 2
ضمانت‌ها
ضامن‌ها
قالب قرارداد
متغیرهای قرارداد
PDF قرارداد
چاپ قرارداد
وضعیت قرارداد
Timeline قرارداد
```

فایل مربوط:

```text
03_CONTRACTS.md
```

---

## Installments Domain

مسئول اقساط قرارداد است.

شامل:

```text
تولید اقساط
سررسید اقساط
اقساط معوق
اقساط پرداخت‌شده
اقساط سفارشی
دفترچه اقساط
وضعیت قسط
جریمه قسط
پاداش تسویه
```

فایل مربوط:

```text
04_INSTALLMENTS.md
```

---

## Payments Domain

مسئول پرداخت‌ها است.

شامل:

```text
پرداخت آنلاین
پرداخت کارت‌به‌کارت
آپلود رسید
بررسی رسید
تأیید پرداخت
رد پرداخت
Callback درگاه
وضعیت پرداخت
Log مالی پرداخت
```

فایل مربوط:

```text
05_PAYMENTS.md
```

---

## Financial Calculations Domain

مسئول محاسبات مالی است.

شامل:

```text
مبلغ کل قرارداد
پیش‌پرداخت
مانده
جریمه دیرکرد
تخفیف تسویه
مانده قابل پرداخت
خلاصه مالی قرارداد
محاسبات مدیریتی
مخفی‌سازی سود از مشتری
```

فایل مربوط:

```text
06_FINANCIAL_CALCULATIONS.md
```

---

## Legal Domain

مسئول فرآیند حقوقی است.

شامل:

```text
پرونده آماده شکایت
پرونده ارجاع‌شده
پرونده شکایت‌شده
ثبت اقدام حقوقی
ثبت هزینه حقوقی
فایل حقوقی
جلسه دادگاه
ارجاع به وکیل
Timeline حقوقی
```

فایل مربوط:

```text
07_LEGAL.md
```

---

## Chat Domain

مسئول گفت‌وگوها است.

شامل:

```text
چت مشتری با واحدها
چت داخلی کاربران
کانال اطلاع‌رسانی عمومی
ربات سیستم
تصاویر چت
حذف خودکار فایل‌های چت
Permission چت
```

فایل مربوط:

```text
08_CHAT.md
```

---

## Notifications Domain

مسئول اعلان‌ها است.

شامل:

```text
اعلان درون‌برنامه‌ای
Badge
Sound
Read / Unread
اعلان‌های مالی
اعلان‌های حقوقی
اعلان‌های تقویم
اعلان‌های سیستمی
Bot Message
```

فایل مربوط:

```text
09_NOTIFICATIONS.md
```

---

## Calendar Domain

مسئول تقویم و یادآوری‌ها است.

شامل:

```text
سررسید اقساط
پیگیری اپراتور
وعده پرداخت
جلسه دادگاه
رویداد دستی
Reminder
تقویم مشتری
تقویم وکیل
تقویم مدیر
```

فایل مربوط:

```text
10_CALENDAR.md
```

---

## Files Domain

مسئول فایل‌های متصل به موجودیت‌های سیستم است.

شامل:

```text
مدارک هویتی
رسید پرداخت
فایل حقوقی
قرارداد PDF
تصاویر چت
فایل بکاپ
فایل آپدیت
Private Storage
File Lifecycle
```

فایل مربوط:

```text
11_FILES.md
```

---

## Settings Domain

مسئول تنظیمات سیستم است.

شامل:

```text
تنظیمات عمومی
تنظیمات مالی
تنظیمات پرداخت
تنظیمات کارت‌به‌کارت
تنظیمات قرارداد
قالب قرارداد
تنظیمات اعلان
تنظیمات چت
تنظیمات بکاپ
تنظیمات آپدیت
تنظیمات پلاگین‌ها
```

فایل مربوط:

```text
12_SETTINGS.md
```

---

## Reports Domain

مسئول گزارش‌ها است.

شامل:

```text
گزارش مالی
گزارش فروش
گزارش وصولی
گزارش معوقات
گزارش حقوقی
گزارش اپراتورها
گزارش مشتریان
گزارش پرداخت‌ها
خروجی Excel / PDF / Print
```

فایل مربوط:

```text
13_REPORTS.md
```

---

## Backup And Update Domain

مسئول بکاپ، بازیابی و بروزرسانی است.

شامل:

```text
Backup
Restore
Update Package
update.json
Migration
Rollback
Maintenance Mode
Update Logs
```

فایل مربوط:

```text
14_BACKUP_AND_UPDATE.md
```

---

## Plugins Domain

مسئول پلاگین‌ها و ماژول‌های توسعه‌پذیر است.

شامل:

```text
Plugin Manager
Plugin Install
Plugin Enable
Plugin Disable
Plugin Update
Plugin Permissions
Plugin Migrations
Plugin Settings
Offline Sync Plugin
Plugin Marketplace Readiness
```

فایل مربوط:

```text
15_PLUGINS.md
```

---

# Domain Dependencies

Domainها نباید بدون کنترل به هم وابسته شوند.

وابستگی‌ها باید مشخص باشند.

---

## Customers Dependencies

```text
Contracts
Identity Documents
Medals
Chat
Notifications
Calendar
Legal
```

---

## Contracts Dependencies

```text
Customers
Installments
Payments
Financial Calculations
Legal
Files
Calendar
Timeline
Notifications
```

---

## Installments Dependencies

```text
Contracts
Payments
Calendar
Notifications
Operators
Financial Calculations
Legal
```

---

## Payments Dependencies

```text
Installments
Contracts
Customers
Files
Notifications
Timeline
Financial Logs
Gateway
Settings
```

---

## Legal Dependencies

```text
Customers
Contracts
Installments
Payments
Files
Calendar
Notifications
Logs
```

---

## Chat Dependencies

```text
Users
Customers
Notifications
Files
Bot
Settings
```

---

## Calendar Dependencies

```text
Installments
Legal
Operators
Customers
Notifications
Settings
```

---

## Reports Dependencies

```text
Customers
Contracts
Installments
Payments
Legal
Operators
Logs
```

---

# Communication Between Domains

Domainها باید با روش‌های زیر ارتباط بگیرند:

```text
Domain Service
Event
Listener
Repository Interface
DTO
Read Model
```

ارتباط مستقیم و پراکنده ممنوع است.

---

# Domain Event Rules

هر Domain باید Eventهای اصلی خود را تعریف کند.

نمونه:

```text
CustomerCreated
CustomerVerified
ContractCreated
InstallmentOverdue
PaymentApproved
LegalCaseReferred
ChatMessageSent
CalendarReminderDue
```

Eventها باید مطابق فایل زیر باشند:

```text
docs/specifications/15_EVENT_ARCHITECTURE.md
```

---

# Domain Permission Rules

هر Domain باید Permissionهای خودش را مشخص کند.

نمونه:

```text
customers.view
customers.create
contracts.view
contracts.create
payments.approve
legal.manage
```

Permissionها باید مطابق فایل زیر باشند:

```text
docs/specifications/17_RBAC_AND_PERMISSION_POLICY.md
```

---

# Domain Logging Rules

هر Domain باید مشخص کند چه عملیاتی Log، Audit، Financial Log یا Legal Log دارد.

قانون کلی:

```text
هر عملیات حساس باید Log داشته باشد.
هر عملیات مالی باید Financial Log داشته باشد.
هر عملیات حقوقی باید Legal Log داشته باشد.
هر عملیات مدیریتی حساس باید Audit Log داشته باشد.
```

---

# Domain UI Rules

هر Domain ممکن است Module یا صفحه داشته باشد.

تمام UIهای Domain باید مطابق سند زیر باشند:

```text
docs/specifications/07_UI_CONSTITUTION.md
```

قوانین مهم:

```text
RTL
Yekan Bakh
Async Search
Date Picker داخل Input
Icon Button
Toast
Confirmation Modal
Skeleton Loading
Empty State
Responsive
```

---

# Domain Security Rules

هر Domain باید امنیت را در طراحی خود لحاظ کند.

موارد الزامی:

```text
CSRF
Server-Side Permission
Ownership Check
Prepared Statements
Output Escaping
Private File Access
No Sensitive Data Exposure
```

---

# Domain Database Rules

هر Domain باید با قوانین دیتابیس سازگار باشد.

موارد مهم:

```text
BIGINT id
DECIMAL برای money
created_at
updated_at
deleted_at در صورت نیاز
indexes
foreign keys در صورت امکان
safe migrations
no DROP unsafe
no TRUNCATE
```

---

# Domain Plugin Readiness

هر Domain باید بررسی کند کدام بخش‌های آن قابلیت توسعه پلاگینی دارند.

نمونه:

```text
Payments → Payment Gateway Plugins
Notifications → SMS / Email / Telegram Plugins
Reports → Advanced Report Plugins
Files → Storage Driver Plugins
Calendar → External Calendar Plugins
Chat → AI Assistant Plugin
Installments → Advanced Penalty Rule Plugin
```

---

# Domain Documentation Template

هر فایل Domain باید ساختار زیر را داشته باشد:

```text
Introduction
Scope
Out Of Scope
Business Rules
Main Entities
Statuses
Workflows
Permissions
Events
Notifications
Timeline Rules
Logging Rules
Calendar Rules
File Rules
UI Rules
Security Rules
Database Notes
Plugin Extension Points
Implementation Rules
Review Checklist
Definition of Done
Future Considerations
```

---

# Domain Boundary Rules

هر Domain باید مرز خودش را مشخص کند.

مثلاً:

```text
Payments مسئول دریافت و تأیید پرداخت است.
اما محاسبه کامل خلاصه مالی قرارداد باید در Financial Calculations Domain انجام شود.
```

یا:

```text
Legal مسئول پرونده حقوقی است.
اما اصل اطلاعات قرارداد در Contracts Domain باقی می‌ماند.
```

---

# Cross-Domain Changes

اگر تغییر در یک Domain روی Domain دیگر اثر دارد، باید صریحاً بررسی شود.

نمونه:

تغییر در Payment ممکن است روی موارد زیر اثر بگذارد:

```text
Installments
Contracts
Financial Summary
Reports
Notifications
Timeline
Legal
Customer Portal
```

هیچ تغییر Cross-Domain نباید بدون بررسی اثرهای جانبی انجام شود.

---

# Domain Review Checklist

قبل از تکمیل هر Domain File:

```text
□ Scope مشخص شده است.
□ Out Of Scope مشخص شده است.
□ Entityها مشخص شده‌اند.
□ Statusها مشخص شده‌اند.
□ Workflowها مشخص شده‌اند.
□ Permissionها مشخص شده‌اند.
□ Eventها مشخص شده‌اند.
□ Notificationها مشخص شده‌اند.
□ Timeline مشخص شده است.
□ Log مشخص شده است.
□ Calendar اثرها مشخص شده‌اند.
□ File Lifecycle در صورت نیاز مشخص شده است.
□ Security بررسی شده است.
□ UI بررسی شده است.
□ Database Notes نوشته شده است.
□ Plugin Extension Points مشخص شده‌اند.
```

---

# Codex Instructions

Codex هنگام کار روی هر Feature باید:

```text
ابتدا فایل Domain مربوطه را پیدا کند.
سپس specifications مرتبط را بخواند.
بعد وابستگی Cross-Domain را بررسی کند.
سپس طراحی انجام دهد.
بعد پیاده‌سازی کند.
در پایان Checklist همان Domain را بررسی کند.
```

---

# Domain Work Order

ترتیب پیشنهادی نوشتن فایل‌های Domain:

```text
01_CUSTOMERS.md
02_USERS_AND_ROLES.md
03_CONTRACTS.md
04_INSTALLMENTS.md
05_PAYMENTS.md
06_FINANCIAL_CALCULATIONS.md
07_LEGAL.md
08_CHAT.md
09_NOTIFICATIONS.md
10_CALENDAR.md
11_FILES.md
12_SETTINGS.md
13_REPORTS.md
14_BACKUP_AND_UPDATE.md
15_PLUGINS.md
```

---

# Final Principle

Domainها قلب واقعی Proma Pay هستند.

اگر Specifications قانون اساسی سیستم باشند، Domainها نقشه دقیق کسب‌وکار هستند.

هرچه Domainها دقیق‌تر نوشته شوند، Codex کمتر اشتباه می‌کند، کمتر کد تکراری می‌نویسد و سیستم پایدارتر توسعه پیدا می‌کند.

---

# End of File