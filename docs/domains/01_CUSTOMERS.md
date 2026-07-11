# فایل بعدی پوشه `domains`

## نام فایل

```text
01_CUSTOMERS.md
```

مسیر کامل:

```text
docs/domains/01_CUSTOMERS.md
```

````md
# Proma Pay — Customers Domain

Version: 1.0  
Status: Domain Documentation  
Priority: CRITICAL

---

# Introduction

این فایل دامنه رسمی مشتریان در پروژه Proma Pay را تعریف می‌کند.

Customers Domain مسئول مدیریت تمام اطلاعات، وضعیت‌ها، مدارک، اعتبار، مدال‌ها، ارتباطات، قراردادها و نمای مشتری در سیستم است.

مشتری در Proma Pay با کاربر داخلی تفاوت دارد.

کاربران داخلی شامل مدیر، اپراتور، وکیل، حسابدار، پشتیبان و کارمند هستند.

مشتری شخصی است که قرارداد، قسط، پرداخت، مدرک هویتی، چت، اعلان، تقویم و سابقه مالی مربوط به خودش را دارد.

---

# Scope

این Domain شامل موارد زیر است:

```text
ثبت مشتری
ویرایش مشتری
مشاهده مشتری
جستجوی مشتری
حذف یا غیرفعال‌سازی مشتری
مدیریت اطلاعات هویتی
مدیریت شماره تماس‌ها
مدیریت آدرس‌ها
مدیریت مدارک هویتی
تأیید یا رد مدارک هویتی
تیک آبی مشتری
مدال‌های مشتری
وضعیت مشتری
کارت مشتری
Timeline مشتری
ارتباط مشتری با قراردادها
ارتباط مشتری با اقساط
ارتباط مشتری با پرداخت‌ها
ارتباط مشتری با چت
ارتباط مشتری با اعلان‌ها
ارتباط مشتری با تقویم
```

---

# Out Of Scope

این Domain مسئول موارد زیر نیست:

```text
محاسبه اقساط
محاسبه جریمه
محاسبه سود
تولید قرارداد
تأیید پرداخت
مدیریت کاربران داخلی
مدیریت نقش‌ها
مدیریت پرونده حقوقی
مدیریت تنظیمات سیستم
مدیریت پلاگین‌ها
```

این موارد در Domainهای مربوطه مدیریت می‌شوند.

---

# Core Principles

## Rule 1 — Customer Is Not User

مشتری نباید با کاربر داخلی یکی در نظر گرفته شود.

مشتری در صفحه Users نمایش داده نمی‌شود.

کاربران داخلی در صفحه Customers نمایش داده نمی‌شوند.

---

## Rule 2 — Customer Data Is Sensitive

اطلاعات مشتری محرمانه است.

موارد حساس:

```text
کد ملی
شماره تماس
آدرس
مدارک هویتی
قراردادها
اقساط
پرداخت‌ها
ضامن‌ها
پرونده حقوقی
Timeline داخلی
```

نمایش این اطلاعات باید با Permission کنترل شود.

---

## Rule 3 — Customer Can Only See Own Data

در پنل مشتری، مشتری فقط داده‌های خودش را می‌بیند.

مشتری نباید بتواند با تغییر ID در URL اطلاعات مشتری دیگر را مشاهده کند.

---

## Rule 4 — Customer With Active Contract Cannot Be Deleted

اگر مشتری قرارداد فعال داشته باشد، حذف مشتری ممنوع است.

در این حالت فقط می‌توان مشتری را غیرفعال یا آرشیو کرد، آن هم با Permission و Audit Log.

---

## Rule 5 — Identity Verification Must Be Workflow-Based

مدارک هویتی مشتری نباید مستقیم جایگزین مدارک قبلی شوند.

مدرک جدید ابتدا Pending می‌شود.

پس از تأیید، مدرک قبلی حذف و مدرک جدید فعال می‌شود.

اگر رد شود، مدرک جدید حذف و مدرک قبلی حفظ می‌شود.

---

# Main Entities

## Customer

موجودیت اصلی دامنه.

فیلدهای پیشنهادی:

```text
id
customer_code
first_name
last_name
father_name
national_id
birth_date
mobile
secondary_mobile
phone
email
address
postal_code
city
province
status
verification_status
blue_tick
notes
created_by
updated_by
created_at
updated_at
deleted_at
metadata
```

---

## Customer Identity Document

برای مدارک هویتی.

فیلدهای پیشنهادی:

```text
id
customer_id
file_id
document_type
status
submitted_at
reviewed_by
reviewed_at
reject_reason
created_at
updated_at
deleted_at
```

---

## Customer Medal

برای مدال‌های مشتری.

فیلدهای پیشنهادی:

```text
id
customer_id
medal_id
given_by
given_at
reason
is_manual
is_active
created_at
updated_at
```

---

## Medal

تعریف خود مدال‌ها.

فیلدهای پیشنهادی:

```text
id
code
title
description
icon
color
rule_type
is_automatic
is_active
created_at
updated_at
```

---

## Customer Timeline

Timeline قابل نمایش برای مشتری یا مدیر.

فیلدهای پیشنهادی:

```text
id
customer_id
contract_id
event_type
title
description
visibility
created_by
created_at
metadata
```

---

# Customer Statuses

وضعیت‌های پیشنهادی مشتری:

```text
active
inactive
blocked
archived
deleted
```

---

## active

مشتری فعال است و می‌تواند عملیات مجاز انجام دهد.

---

## inactive

مشتری موقتاً غیرفعال است.

ممکن است نتواند وارد پنل شود یا عملیات جدید انجام دهد.

---

## blocked

مشتری مسدود شده است.

معمولاً به دلیل مشکل حقوقی، امنیتی یا مدیریتی.

---

## archived

مشتری آرشیو شده است.

در لیست‌های عادی کمتر نمایش داده می‌شود اما اطلاعات او حفظ می‌شود.

---

## deleted

حذف نرم‌افزاری شده است.

اطلاعات مالی، قراردادها، پرداخت‌ها و Timeline نباید از بین بروند.

---

# Verification Statuses

وضعیت احراز هویت مشتری:

```text
not_submitted
pending
verified
rejected
expired
```

---

## not_submitted

مشتری هنوز مدرک هویتی ارسال نکرده است.

---

## pending

مدرک هویتی ارسال شده و منتظر بررسی است.

---

## verified

مدرک تأیید شده است و مشتری می‌تواند تیک آبی داشته باشد.

---

## rejected

مدرک رد شده است.

دلیل رد باید ثبت شود.

---

## expired

مدرک نیاز به بررسی مجدد دارد.

---

# Blue Tick Rules

تیک آبی نشانه تأیید هویت مشتری است.

قوانین:

```text
فقط پس از تأیید مدرک هویتی فعال شود.
با رد مدرک جدید، تیک آبی قبلی حذف نشود مگر مدرک فعال قبلی وجود نداشته باشد.
اگر مدرک فعال حذف یا منقضی شد، تیک آبی غیرفعال شود.
تیک آبی در کارت مشتری، جزئیات مشتری و انتخاب مشتری نمایش داده شود.
```

---

# Customer Creation Workflow

فرآیند ثبت مشتری:

```text
Open Customer Form
↓
Validate Required Fields
↓
Check Duplicate National ID / Mobile
↓
Create Customer
↓
Create Timeline
↓
Write Audit Log
↓
Show Success Toast
```

---

# Required Customer Fields

حداقل فیلدهای ضروری:

```text
نام
نام خانوادگی
شماره موبایل
کد ملی در صورت نیاز قراردادی
```

برای قرارداد رسمی، اطلاعات بیشتری ممکن است ضروری شود:

```text
نام پدر
کد ملی
آدرس
شماره تماس
```

---

# Duplicate Detection

سیستم باید قبل از ثبت مشتری موارد زیر را بررسی کند:

```text
کد ملی تکراری
شماره موبایل تکراری
ایمیل تکراری در صورت استفاده
```

اگر مشتری مشابه وجود داشت، سیستم باید هشدار دهد، نه اینکه مشتری تکراری بسازد.

---

# Customer Update Workflow

فرآیند ویرایش مشتری:

```text
Open Edit Form
↓
Check Permission
↓
Validate Input
↓
Check Duplicate Fields
↓
Update Customer
↓
Create Timeline
↓
Write Audit Log With Old/New Values
↓
Show Success Toast
```

---

# Customer Delete Workflow

حذف مشتری باید محدود باشد.

فرآیند:

```text
Request Delete
↓
Check Permission
↓
Check Active Contracts
↓
Check Financial Records
↓
Show Confirmation Modal
↓
Require Confirm Text If Sensitive
↓
Soft Delete Or Block
↓
Write Audit Log
↓
Create Timeline
```

عبارت تأیید برای حذف حساس:

```text
حذف را تایید می‌کنم
```

---

# Delete Restrictions

حذف مشتری ممنوع است اگر:

```text
قرارداد فعال دارد.
قسط فعال دارد.
پرداخت ثبت‌شده دارد.
پرونده حقوقی فعال دارد.
مدرک هویتی فعال دارد.
```

در این شرایط، فقط وضعیت مشتری می‌تواند تغییر کند.

---

# Customer Identity Document Workflow

فرآیند مدارک هویتی:

```text
Customer Uploads Document
↓
Validate File
↓
Store In Private Storage
↓
Create File Metadata
↓
Create Identity Document With pending Status
↓
Notify Admin
↓
Create Review Item
↓
Create Timeline
↓
Write Log
```

---

# Identity Document Approval Workflow

فرآیند تأیید مدرک:

```text
Admin Reviews Document
↓
Approve
↓
Set New Document Active
↓
Delete Old Active Document File
↓
Set Customer verification_status = verified
↓
Set blue_tick = true
↓
Notify Customer
↓
Send Bot Message
↓
Create Timeline
↓
Write Audit Log
```

---

# Identity Document Rejection Workflow

فرآیند رد مدرک:

```text
Admin Reviews Document
↓
Reject With Reason
↓
Delete New Pending Document File
↓
Keep Old Active Document If Exists
↓
If No Active Document, verification_status = rejected
↓
Notify Customer
↓
Send Bot Message
↓
Create Timeline
↓
Write Audit Log
```

---

# Medal Rules

مدال‌ها به مشتری تعلق دارند، نه به کاربران داخلی.

مدال‌ها باید در کارت مشتری و صفحه جزئیات مشتری نمایش داده شوند.

انواع مدال:

```text
automatic
manual
```

---

# Automatic Medals

مدال‌های خودکار پیشنهادی:

```text
خوش حساب
۵ قرارداد موفق
۱۰ پرداخت بدون تأخیر
پرداخت زودتر از موعد
مدارک هویتی تأیید شده
VIP
همکاری بلندمدت
```

---

# Manual Medals

مدیر می‌تواند به صورت دستی مدال بدهد یا حذف کند.

عملیات مدال دستی باید Audit Log داشته باشد.

---

# Medal Display Rules

مدال‌ها باید:

```text
گرافیکی باشند.
Tooltip داشته باشند.
در کارت مشتری نمایش داده شوند.
در جزئیات مشتری نمایش داده شوند.
در انتخاب مشتری قابل مشاهده باشند.
رنگ و آیکون استاندارد داشته باشند.
```

---

# Customer Card Rules

کارت مشتری باید حداقل این موارد را نمایش دهد:

```text
نام کامل
شماره موبایل
کد مشتری
وضعیت
تیک آبی
مدال‌ها
تعداد قراردادها
وضعیت بدهی کلی در صورت Permission
آخرین فعالیت
دکمه‌های عملیات به صورت آیکونی
```

دکمه‌ها:

```text
مشاهده
ویرایش
چت
حذف
```

حذف باید فقط آیکون Trash باشد.

ویرایش باید فقط آیکون Pencil باشد.

---

# Customer Search Rules

هرجا نیاز به انتخاب مشتری باشد، Select ساده ممنوع است.

باید از Async Search Input استفاده شود.

موارد استفاده:

```text
ثبت قرارداد
ثبت قسط سفارشی
ثبت رویداد تقویم
شروع چت
ارجاع پرونده
گزارش‌ها
فیلترها
```

نتیجه جستجو باید نشان دهد:

```text
نام کامل
شماره موبایل
کد ملی در صورت Permission
تیک آبی
وضعیت
مدال مهم
```

---

# Customer Profile

پروفایل مشتری شامل:

```text
اطلاعات فردی
اطلاعات تماس
آدرس
مدارک هویتی
قراردادها
اقساط
پرداخت‌ها
اعلان‌ها
Timeline
چت
مدال‌ها
تقویم
```

نمای مشتری و نمای مدیر باید متفاوت باشند.

---

# Customer Portal Rules

در پنل مشتری، مشتری می‌تواند:

```text
پروفایل خود را ببیند.
اطلاعات مجاز را ویرایش کند.
مدرک هویتی آپلود کند.
قراردادهای خود را ببیند.
اقساط خود را ببیند.
قسط پرداخت کند.
رسید کارت‌به‌کارت ارسال کند.
اعلان‌های خود را ببیند.
تقویم اقساط خود را ببیند.
با واحدهای مجاز گفتگو کند.
```

مشتری نباید ببیند:

```text
درصد سود
نوع سود
فرمول سود
گزارش داخلی سود
Log فنی
اطلاعات مشتریان دیگر
اطلاعات کاربران داخلی
پرونده حقوقی دیگران
مدارک دیگران
```

---

# Permissions

Permissionهای مربوط به مشتریان:

```text
customers.view
customers.create
customers.update
customers.delete
customers.archive
customers.block
customers.view_sensitive
customers.view_identity_documents
customers.approve_identity_documents
customers.reject_identity_documents
customers.view_medals
customers.manage_medals
customers.view_timeline
customers.export
```

---

# Customer Self Permissions

برای مشتری:

```text
customer.profile.view
customer.profile.update
customer.identity.upload
customer.contracts.view
customer.installments.view
customer.payments.create
customer.notifications.view
customer.chat.send
customer.calendar.view
```

---

# Role Access Rules

## Admin / Super Admin

می‌تواند همه مشتریان را طبق Permission ببیند و مدیریت کند.

---

## Operator

اپراتور فقط مشتریانی را می‌بیند که برای پیگیری به او مرتبط هستند.

اپراتور نباید بدون Permission اطلاعات حساس هویتی یا مالی کامل ببیند.

---

## Lawyer

وکیل فقط مشتریان مرتبط با پرونده‌های حقوقی مجاز را می‌بیند.

---

## Department Manager

مدیر واحد فقط مشتریان مرتبط با واحد خودش را می‌بیند، اگر Scope او محدود باشد.

---

## Customer

فقط اطلاعات خودش را می‌بیند.

---

# Events

Eventهای اصلی Customers Domain:

```text
CustomerCreated
CustomerUpdated
CustomerDeleted
CustomerArchived
CustomerBlocked
CustomerActivated
CustomerIdentityDocumentUploaded
CustomerIdentityDocumentApproved
CustomerIdentityDocumentRejected
CustomerVerified
CustomerBlueTickEnabled
CustomerBlueTickDisabled
CustomerMedalGranted
CustomerMedalRevoked
```

---

# Notifications

رویدادهایی که Notification نیاز دارند:

## CustomerIdentityDocumentUploaded

گیرنده:

```text
Admin
Review Manager
```

پیام:

```text
مدرک هویتی جدید برای بررسی ارسال شد.
```

---

## CustomerIdentityDocumentApproved

گیرنده:

```text
Customer
```

پیام:

```text
مدرک هویتی شما تأیید شد.
```

---

## CustomerIdentityDocumentRejected

گیرنده:

```text
Customer
```

پیام:

```text
مدرک هویتی شما رد شد.
```

---

## CustomerBlocked

گیرنده:

```text
Admin
Customer در صورت نیاز
```

---

# Bot Messages

ربات سیستم برای مشتری پیام‌های زیر را ارسال می‌کند:

```text
مدرک هویتی شما تأیید شد.
مدرک هویتی شما رد شد.
وضعیت حساب شما تغییر کرد.
مدال جدیدی برای شما ثبت شد.
```

پیام‌های خصوصی نباید در کانال عمومی منتشر شوند.

---

# Timeline Rules

Timeline مشتری باید برای موارد زیر ایجاد شود:

```text
ثبت مشتری
ویرایش اطلاعات مهم
آپلود مدرک هویتی
تأیید مدرک هویتی
رد مدرک هویتی
فعال شدن تیک آبی
غیرفعال شدن تیک آبی
ثبت قرارداد جدید
پرداخت قسط
معوق شدن قسط
ارجاع به حقوقی
اعطای مدال
حذف مدال
تغییر وضعیت مشتری
```

---

# Timeline Visibility

مقادیر visibility:

```text
internal
customer
both
```

موارد فنی و مدیریتی باید internal باشند.

موارد قابل نمایش برای مشتری باید ساده و بدون اطلاعات حساس باشند.

---

# Logging Rules

عملیات زیر باید Log داشته باشند:

```text
ثبت مشتری
ویرایش مشتری
حذف مشتری
آرشیو مشتری
مسدود کردن مشتری
آپلود مدرک
مشاهده مدرک حساس
تأیید مدرک
رد مدرک
اعطای مدال
حذف مدال
تغییر وضعیت مشتری
```

---

# Audit Rules

عملیات زیر باید Audit Log داشته باشند:

```text
حذف مشتری
مسدود کردن مشتری
تأیید مدرک هویتی
رد مدرک هویتی
ویرایش کد ملی
ویرایش شماره موبایل اصلی
اعطای مدال دستی
حذف مدال دستی
تغییر وضعیت مشتری
```

---

# Calendar Rules

Customers Domain به صورت مستقیم رویداد تقویم ایجاد نمی‌کند مگر برای موارد خاص.

رویدادهای تقویم مرتبط با مشتری معمولاً از Domainهای زیر ایجاد می‌شوند:

```text
Installments
Legal
Calendar
Operator Follow-up
```

اما در صفحه مشتری باید رویدادهای مرتبط قابل مشاهده باشند.

---

# File Rules

Customers Domain با فایل‌های زیر مرتبط است:

```text
مدارک هویتی
آواتار مشتری
فایل‌های مرتبط با مشتری در صورت نیاز
```

مدارک هویتی همیشه Private هستند.

مشتری فقط مدارک خودش را آپلود می‌کند.

مدیر فقط با Permission می‌تواند مدارک را مشاهده، تأیید یا رد کند.

---

# UI Rules

صفحات Customers باید شامل موارد زیر باشند:

```text
لیست مشتریان
جستجو
فیلتر
Pagination
Card View
List View
جزئیات مشتری
فرم ثبت مشتری
فرم ویرایش مشتری
بخش مدارک هویتی
بخش مدال‌ها
بخش Timeline
بخش قراردادها
بخش اقساط
بخش پرداخت‌ها
بخش چت
```

---

# Customer List Filters

فیلترهای پیشنهادی:

```text
نام
شماره موبایل
کد ملی
کد مشتری
وضعیت
وضعیت احراز هویت
دارای تیک آبی
دارای قرارداد فعال
دارای قسط معوق
دارای پرونده حقوقی
دارای مدال
تاریخ ثبت از/تا
```

---

# Customer Detail Page

صفحه جزئیات مشتری باید شامل کارت‌های زیر باشد:

```text
اطلاعات اصلی
وضعیت احراز هویت
مدال‌ها
قراردادها
اقساط
پرداخت‌ها
Timeline
چت
تقویم مرتبط
پرونده حقوقی در صورت وجود
یادداشت‌های داخلی
```

---

# Form Rules

فرم مشتری باید:

```text
Required * داشته باشد.
Validation سمت سرور داشته باشد.
Validation سمت Frontend داشته باشد.
Toast موفقیت/خطا داشته باشد.
Error زیر فیلد نشان دهد.
در خطا داده فرم حفظ شود.
```

---

# Security Rules

موارد امنیتی الزامی:

```text
CSRF برای تمام فرم‌ها
Permission سمت سرور
Ownership Check برای مشتری
Escape خروجی‌ها
Prepared Statement
عدم نمایش داده حساس بدون Permission
عدم نمایش مسیر فایل مدارک
عدم دانلود مستقیم مدرک
Security Log برای دسترسی غیرمجاز
```

---

# Sensitive Data Rules

موارد زیر فقط با Permission نمایش داده شوند:

```text
کد ملی
تصویر مدرک هویتی
آدرس کامل
شماره تماس دوم
یادداشت داخلی
وضعیت حقوقی
گزارش مالی کامل
```

---

# Database Notes

جدول‌های پیشنهادی:

```text
customers
customer_identity_documents
medals
customer_medals
customer_timeline
customer_notes
```

Indexes پیشنهادی:

```text
customers.mobile
customers.national_id
customers.status
customers.verification_status
customers.deleted_at
customer_identity_documents.customer_id
customer_identity_documents.status
customer_medals.customer_id
customer_timeline.customer_id
```

---

# Validation Rules

قوانین Validation:

```text
شماره موبایل الزامی و معتبر باشد.
کد ملی در صورت وارد شدن معتبر باشد.
کد ملی تکراری نباشد.
شماره موبایل تکراری نباشد مگر سیاست سیستم اجازه دهد.
نام و نام خانوادگی خالی نباشد.
حجم مدرک هویتی حداکثر 1MB باشد.
فرمت مدرک jpg/png/webp باشد.
```

---

# Plugin Extension Points

Customers Domain باید آماده توسعه پلاگینی باشد.

Extension Pointهای پیشنهادی:

```text
customer_created
customer_updated
customer_verified
customer_before_delete
customer_after_delete
customer_card_badges
customer_profile_tabs
customer_search_result_extra_fields
customer_medal_rules
customer_identity_verification_provider
```

نمونه پلاگین‌های آینده:

```text
OCR مدارک هویتی
اعتبارسنجی کد ملی از سرویس خارجی
باشگاه مشتریان
امتیازدهی مشتری
تحلیل ریسک مشتری
Sync مشتری با سیستم حسابداری
CRM پیشرفته
```

---

# Cross-Domain Effects

تغییرات مشتری ممکن است روی بخش‌های زیر اثر بگذارد:

```text
Contracts
Installments
Payments
Legal
Chat
Notifications
Calendar
Reports
Files
```

مثلاً:

تغییر شماره موبایل مشتری ممکن است روی موارد زیر اثر بگذارد:

```text
ارسال پیامک
تماس اپراتور
گزارش مشتریان
چت
اطلاعات قرارداد
```

---

# Implementation Rules

Codex هنگام پیاده‌سازی Customers Feature باید بررسی کند:

```text
آیا مشتری با کاربر داخلی اشتباه گرفته نشده؟
آیا Permission سمت سرور بررسی شده؟
آیا Scope داده رعایت شده؟
آیا مشتری فقط داده خودش را می‌بیند؟
آیا فیلدهای حساس محافظت شده‌اند؟
آیا مدارک هویتی در Private Storage هستند؟
آیا Workflow مدرک درست است؟
آیا تیک آبی فقط بعد از تأیید فعال می‌شود؟
آیا حذف مشتری دارای قرارداد فعال ممنوع است؟
آیا Timeline ساخته می‌شود؟
آیا Log و Audit لازم ثبت می‌شود؟
آیا Notification لازم ارسال می‌شود؟
آیا UI مطابق UI Constitution است؟
```

---

# Review Checklist

قبل از Merge تغییرات مربوط به مشتری:

```text
□ Customers و Users جدا هستند.
□ مشتری فقط داده خودش را می‌بیند.
□ Permission سمت سرور وجود دارد.
□ جستجوی مشتری Async است.
□ Select ساده برای مشتری استفاده نشده.
□ فیلدهای ضروری * دارند.
□ مدارک هویتی Private هستند.
□ Upload مدرک max 1MB است.
□ تأیید مدرک تیک آبی را فعال می‌کند.
□ رد مدرک فایل جدید را حذف می‌کند.
□ مدرک قبلی تا تأیید مدرک جدید حفظ می‌شود.
□ حذف مشتری دارای قرارداد فعال ممنوع است.
□ مدال‌ها روی Customer هستند نه User.
□ Timeline ایجاد می‌شود.
□ Audit Log برای عملیات حساس ثبت می‌شود.
□ اطلاعات حساس بدون Permission نمایش داده نمی‌شود.
□ UI در موبایل و دسکتاپ تست شده است.
```

---

# Definition of Done

Customers Domain زمانی کامل است که:

```text
✔ مشتری از کاربر داخلی جدا باشد.
✔ ثبت، ویرایش، مشاهده و جستجوی مشتری درست کار کند.
✔ حذف مشتری با وابستگی‌های فعال کنترل شود.
✔ مدارک هویتی Workflow امن داشته باشند.
✔ تیک آبی فقط بر اساس تأیید هویت فعال شود.
✔ مدال‌ها به مشتری اختصاص داده شوند.
✔ کارت مشتری کامل و خوانا باشد.
✔ Customer Portal فقط داده خود مشتری را نمایش دهد.
✔ Permission، Log، Audit، Timeline و Notification رعایت شده باشند.
✔ UI مطابق Design System باشد.
✔ فایل‌ها در Private Storage ذخیره شوند.
```

---

# Future Considerations

در نسخه‌های آینده Customers Domain باید آماده موارد زیر باشد:

```text
Customer Risk Score
Advanced CRM
Customer Segmentation
Customer Tags
Customer Groups
Customer Loyalty Program
Customer Credit Score
OCR Identity Verification
External Identity Verification API
SMS Verification
Multi-Branch Customer Ownership
Customer Merge Tool
Duplicate Customer Detector
Customer Import / Export
AI Customer Summary
```

---

# End of File
````
