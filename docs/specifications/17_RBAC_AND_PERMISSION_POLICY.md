# Proma Pay — RBAC & Permission Policy

Version: 1.0  
Status: Master Specification  
Priority: CRITICAL

---

# Introduction

این فایل استاندارد رسمی مدیریت نقش‌ها، سطح دسترسی‌ها و Permissionها در پروژه Proma Pay است.

هدف این سند این است که هیچ کاربر، مشتری، اپراتور، وکیل، مدیر واحد یا مدیر اصلی نتواند خارج از مجوزهای تعریف‌شده به داده‌ها یا عملیات سیستم دسترسی داشته باشد.

مخفی کردن دکمه‌ها در UI کافی نیست.

تمام دسترسی‌ها باید سمت سرور بررسی شوند.

---

# Constitution

## Rule 1 — Server-Side Permission Is Mandatory

تمام Permissionها باید سمت سرور بررسی شوند.

هیچ Controller، Route، Service یا Action حساسی نباید بدون بررسی Permission اجرا شود.

ممنوع:

```text
فقط مخفی کردن دکمه در View
فقط بررسی Role در JavaScript
فقط غیرفعال کردن لینک در Sidebar
```

صحیح:

```text
Route Middleware
Controller Authorization
Service-Level Permission Check
Repository Data Scope
```

---

## Rule 2 — UI Permission Is Only For User Experience

نمایش یا عدم نمایش دکمه‌ها در UI فقط برای تجربه کاربری است.

امنیت واقعی باید در Backend باشد.

---

## Rule 3 — Customer Is Not Internal User

مشتری نباید با کاربر داخلی یکی در نظر گرفته شود.

مشتری فقط به پنل مشتری، قراردادها، اقساط، پرداخت‌ها، چت واحدها، اعلان‌ها و مدارک خودش دسترسی دارد.

کاربران داخلی شامل:

```text
admin
department_manager
staff
operator
lawyer
accountant
support
```

مشتریان شامل:

```text
customer
```

---

## Rule 4 — Every Action Needs A Permission

هر عملیات مهم باید Permission مشخص داشته باشد.

نمونه:

```text
customers.view
customers.create
customers.update
customers.delete
contracts.view
contracts.create
contracts.update
contracts.delete
payments.approve
legal.manage
settings.update
```

---

## Rule 5 — Role Is A Group Of Permissions

Role فقط یک نام نیست.

هر Role باید مجموعه‌ای از Permissionها باشد.

---

## Rule 6 — Data Scope Is Part Of Permission

Permission فقط اجازه انجام عملیات نیست.

باید مشخص کند کاربر روی کدام داده‌ها اجازه عملیات دارد.

نمونه:

```text
all
own
department
assigned
customer_self
```

---

## Rule 7 — No Direct Access By ID

هیچ کاربر نباید فقط با تغییر ID در URL به اطلاعات دیگران دسترسی پیدا کند.

تمام درخواست‌های مبتنی بر ID باید مالکیت و Permission را بررسی کنند.

نمونه خطرناک:

```text
/contracts/show/25
/contracts/show/26
```

اگر کاربر مجاز نباشد، باید 403 یا 404 امن دریافت کند.

---

## Rule 8 — Admin Is Powerful But Still Audited

مدیر اصلی دسترسی کامل دارد، اما عملیات مدیر هم باید Log و Audit شود.

دسترسی کامل به معنی بدون ردیابی بودن نیست.

---

## Rule 9 — Permission Must Be Centralized

بررسی Permission نباید در فایل‌های مختلف پراکنده شود.

باید از Service مرکزی استفاده شود.

سرویس پیشنهادی:

```text
PermissionService
```

---

## Rule 10 — Deny By Default

اگر Permission مشخص نبود، دسترسی باید رد شود.

اصل پیش‌فرض:

```text
deny
```

نه:

```text
allow
```

---

# Core Roles

نقش‌های اصلی سیستم:

```text
super_admin
admin
department_manager
staff
operator
lawyer
accountant
support
customer
system
```

---

# Role Definitions

## super_admin

مدیر اصلی سیستم.

دسترسی کامل به تمام بخش‌ها دارد.

می‌تواند:

```text
مدیریت کاربران
مدیریت نقش‌ها
مدیریت تنظیمات
مشاهده همه قراردادها
مشاهده همه مشتریان
مشاهده همه پرداخت‌ها
مدیریت بکاپ
مدیریت بروزرسانی
مشاهده لاگ‌ها
مدیریت دسترسی‌ها
```

تمام عملیات حساس super_admin باید Audit شود.

---

## admin

مدیر سامانه یا فروشگاه.

دسترسی گسترده دارد اما ممکن است بعضی عملیات بسیار حساس فقط برای super_admin باشد.

نمونه محدودیت احتمالی:

```text
حذف کامل لاگ‌ها
اجرای بروزرسانی
بازیابی بکاپ
تغییر Permissionهای اصلی
```

---

## department_manager

مدیر واحد.

فقط به کاربران، وظایف و داده‌های مربوط به واحد خودش دسترسی دارد.

می‌تواند:

```text
مشاهده کارکنان همان واحد
مدیریت وظایف همان واحد
مشاهده گزارش‌های همان واحد
پیگیری کارهای مربوط به همان واحد
```

نمی‌تواند:

```text
کاربران واحدهای دیگر را ببیند.
کاربران خارج از واحد خود را ویرایش کند.
تنظیمات کل سیستم را تغییر دهد.
```

---

## staff

کارمند داخلی.

دسترسی محدود بر اساس واحد و Permissionهای اختصاصی دارد.

---

## operator

اپراتور پیگیری اقساط.

اپراتور دسترسی محدود دارد.

اپراتور می‌تواند:

```text
مشاهده اقساط سررسید گذشته مجاز
تماس با مشتری
مشاهده شماره تماس مشتری و ضامن در حد نیاز
ثبت نتیجه پیگیری
ثبت وعده پرداخت
ارجاع پرونده به حقوقی در صورت مجاز بودن
شروع گفتگو با مشتریان مجاز
```

اپراتور نمی‌تواند:

```text
کاربران سیستم را ببیند.
قرارداد را حذف کند.
پرداخت را تأیید کند.
رسید پرداخت را تأیید یا رد کند.
تنظیمات را تغییر دهد.
مدارک هویتی حساس را بدون Permission ببیند.
گزارش مالی کامل ببیند.
```

---

## lawyer

کاربر واحد حقوقی.

وکیل یا مسئول حقوقی می‌تواند:

```text
مشاهده پرونده‌های ارجاع‌شده
مشاهده پرونده‌های آماده شکایت
مدیریت پرونده‌های شکایت‌شده
ثبت مرحله حقوقی
ثبت هزینه حقوقی
آپلود فایل حقوقی
ثبت تاریخ جلسه دادگاه
مشاهده قرارداد و مشتری مرتبط با پرونده مجاز
```

وکیل نمی‌تواند:

```text
همه مشتریان را بدون ارتباط حقوقی ببیند.
تنظیمات مالی را تغییر دهد.
پرداخت را تأیید کند.
کاربران را مدیریت کند مگر Permission داشته باشد.
```

---

## accountant

مسئول مالی.

می‌تواند:

```text
مشاهده پرداخت‌ها
مشاهده گزارش‌های مالی مجاز
بررسی وضعیت اقساط
مشاهده تسویه‌ها
مشاهده هزینه‌های حقوقی
```

فقط در صورت داشتن Permission می‌تواند پرداخت را تأیید کند.

---

## support

پشتیبانی.

می‌تواند:

```text
مشاهده گفتگوهای مجاز
پاسخ به مشتریان
مشاهده اطلاعات پایه مشتری در حد نیاز
ثبت یادداشت پشتیبانی
```

نمی‌تواند:

```text
اطلاعات مالی حساس را ببیند مگر Permission داشته باشد.
پرداخت را تأیید کند.
تنظیمات را تغییر دهد.
```

---

## customer

مشتری.

مشتری فقط به داده‌های خودش دسترسی دارد.

می‌تواند:

```text
مشاهده پروفایل خود
ویرایش اطلاعات مجاز پروفایل
آپلود مدرک هویتی
مشاهده قراردادهای خود
مشاهده اقساط خود
پرداخت اقساط خود
ارسال رسید کارت‌به‌کارت
مشاهده Timeline ساده‌شده خود
مشاهده اعلان‌های خود
گفتگو با واحدهای مجاز
مشاهده تقویم اقساط خود
```

مشتری نمی‌تواند:

```text
مشتریان دیگر را ببیند.
کاربران داخلی را ببیند.
فرمول سود را ببیند.
درصد سود را ببیند.
نوع سود داخلی را ببیند.
لاگ فنی ببیند.
پرونده حقوقی دیگران را ببیند.
مدارک دیگران را ببیند.
```

---

## system

نقش داخلی برای عملیات خودکار سیستم.

نمونه:

```text
Jobها
Cronها
Reminderها
Cleanupها
Update System
Backup System
Notification Dispatcher
```

عملیات system باید Log شود و actor آن مشخص باشد:

```text
actor = system
```

---

# Permission Naming Convention

Permissionها باید با الگوی زیر نام‌گذاری شوند:

```text
module.action
```

نمونه:

```text
customers.view
customers.create
customers.update
customers.delete

contracts.view
contracts.create
contracts.update
contracts.delete

installments.view
installments.pay
installments.create_custom
installments.update_custom
installments.delete_custom

payments.view
payments.create
payments.approve
payments.reject

legal.view
legal.manage
legal.costs.create
legal.documents.upload

settings.view
settings.update

users.view
users.create
users.update
users.delete

reports.view
reports.export

logs.view
backups.create
backups.restore
updates.apply
```

---

# Permission Scopes

هر Permission می‌تواند Scope داشته باشد.

Scopeهای پیشنهادی:

```text
all
own
assigned
department
customer_self
legal_assigned
readonly
none
```

---

## all

دسترسی به همه داده‌های آن بخش.

معمولاً برای super_admin و admin.

---

## own

فقط داده‌هایی که خود کاربر ایجاد کرده یا مالک آن است.

---

## assigned

فقط داده‌هایی که به کاربر ارجاع شده‌اند.

مثلاً اپراتور یا وکیل.

---

## department

فقط داده‌های مربوط به واحد کاربر.

---

## customer_self

فقط داده‌های خود مشتری.

---

## legal_assigned

فقط پرونده‌های حقوقی ارجاع‌شده به وکیل یا واحد حقوقی.

---

## readonly

فقط مشاهده، بدون تغییر.

---

## none

بدون دسترسی.

---

# Suggested Permission List

## Dashboard

```text
dashboard.view
dashboard.financial_summary
dashboard.legal_summary
dashboard.operator_summary
dashboard.system_health
```

---

## Customers

```text
customers.view
customers.create
customers.update
customers.delete
customers.view_sensitive
customers.view_identity_documents
customers.approve_identity_documents
customers.reject_identity_documents
customers.view_medals
customers.manage_medals
```

---

## Users

```text
users.view
users.create
users.update
users.delete
users.assign_roles
users.manage_permissions
users.view_activity
```

---

## Contracts

```text
contracts.view
contracts.create
contracts.update
contracts.delete
contracts.print
contracts.generate_pdf
contracts.view_financial_summary
contracts.view_internal_profit
contracts.view_legal_status
contracts.manage_templates
```

---

## Installments

```text
installments.view
installments.create
installments.update
installments.delete
installments.create_custom
installments.update_custom
installments.delete_custom
installments.mark_paid
installments.view_penalty
installments.view_reward
installments.print_booklet
```

---

## Payments

```text
payments.view
payments.create
payments.pay_gateway
payments.pay_card_to_card
payments.upload_receipt
payments.approve
payments.reject
payments.view_receipts
payments.view_gateway_logs
```

---

## Review Center

```text
review_center.view
review_center.identity_documents.view
review_center.identity_documents.approve
review_center.identity_documents.reject
review_center.payment_receipts.view
review_center.payment_receipts.approve
review_center.payment_receipts.reject
```

---

## Legal

```text
legal.view
legal.view_ready_cases
legal.view_filed_cases
legal.view_referred_cases
legal.create_case
legal.update_case
legal.refer_case
legal.add_log
legal.add_cost
legal.upload_document
legal.delete_document
legal.create_court_date
legal.export
```

---

## Calendar

```text
calendar.view
calendar.create
calendar.update
calendar.delete
calendar.view_assigned
calendar.manage_reminders
```

---

## Chat

```text
chat.view
chat.send
chat.upload_image
chat.view_internal_users
chat.start_customer_chat
chat.start_unit_chat
chat.manage_public_channel
chat.post_public_announcement
```

---

## Notifications

```text
notifications.view
notifications.mark_read
notifications.manage_templates
notifications.manage_preferences
notifications.send_system
```

---

## Reports

```text
reports.view
reports.financial
reports.sales
reports.collections
reports.overdue
reports.legal
reports.operators
reports.customers
reports.export
```

---

## Settings

```text
settings.view
settings.update_general
settings.update_financial
settings.update_payment
settings.update_contract_template
settings.update_notification
settings.update_chat
settings.update_security
settings.update_backup
settings.update_update_system
settings.update_branding
```

---

## Files

```text
files.view
files.download
files.preview
files.upload
files.delete
files.view_private
files.view_identity
files.view_legal
files.view_backup
```

---

## Logs

```text
logs.view
logs.view_application
logs.view_security
logs.view_audit
logs.view_financial
logs.view_legal
logs.export
```

---

## Backup

```text
backups.view
backups.create
backups.download
backups.restore
backups.delete
```

---

## Update

```text
updates.view
updates.upload
updates.validate
updates.apply
updates.rollback
updates.view_logs
```

---

# Data Ownership Rules

## Customer Data

مشتری فقط داده‌های خودش را می‌بیند.

قانون:

```text
customer_id = current_customer_id
```

---

## Operator Data

اپراتور فقط موارد زیر را می‌بیند:

```text
اقساط معوق مجاز
پرونده‌های پیگیری اختصاص‌داده‌شده
گفتگوهای مجاز
پیگیری‌های خودش
```

---

## Lawyer Data

وکیل فقط پرونده‌های حقوقی مجاز را می‌بیند:

```text
assigned_lawyer_id = current_user_id
یا
legal_department_id = current_user_department_id
یا
permission scope = all
```

---

## Department Manager Data

مدیر واحد فقط داده‌های مربوط به واحد خودش را می‌بیند.

---

# Users Page Rules

صفحه کاربران فقط برای کاربران داخلی است.

مشتریان نباید در صفحه Users نمایش داده شوند.

قوانین:

```text
admin و super_admin همه کاربران داخلی را می‌بینند.
department_manager فقط کاربران واحد خودش را می‌بیند.
operator صفحه Users را نمی‌بیند.
lawyer صفحه Users را نمی‌بیند مگر Permission داشته باشد.
customer هرگز صفحه Users را نمی‌بیند.
```

---

# Customers Page Rules

صفحه Customers فقط برای مشتریان است.

کاربران داخلی نباید به عنوان مشتری در این صفحه نمایش داده شوند.

---

# Sidebar And Menu Rules

منوها باید بر اساس Permission نمایش داده شوند.

اما Controller هم باید Permission را بررسی کند.

قانون:

```text
اگر کاربر Permission ندارد:
    منو نمایش داده نشود.
    اگر URL را مستقیم باز کرد، دسترسی رد شود.
```

---

# Route Protection Rules

هر Route باید یکی از این وضعیت‌ها را داشته باشد:

```text
public
auth_required
permission_required
role_required
customer_self
system_only
```

هیچ Route نباید بدون وضعیت امنیتی مشخص باقی بماند.

---

# Controller Authorization Rules

در ابتدای هر Action حساس باید Permission بررسی شود.

نمونه:

```php
PermissionService::authorize($currentUser, 'payments.approve');
```

برای داده خاص:

```php
PermissionService::authorizeEntity($currentUser, 'contracts.view', $contract);
```

---

# Service Authorization Rules

برای عملیات حساس، فقط Controller کافی نیست.

Service هم باید دسترسی را بررسی کند یا فرضیات امنیتی مشخص داشته باشد.

نمونه:

```php
PaymentService::approveReceipt($receiptId, $actorId);
```

داخل Service باید بررسی شود:

```text
actor اجازه approve دارد؟
receipt وجود دارد؟
actor اجازه مشاهده contract/payment مرتبط را دارد؟
```

---

# Repository Scope Rules

Repositoryها باید امکان Query بر اساس Scope داشته باشند.

نمونه:

```php
ContractRepository::visibleTo($currentUser)
CustomerRepository::visibleTo($currentUser)
LegalCaseRepository::visibleTo($currentUser)
```

هیچ لیست مدیریتی نباید بدون Scope داده‌ها را برگرداند.

---

# View Authorization Rules

View نباید منطق اصلی Permission را تعیین کند.

اما می‌تواند برای نمایش دکمه‌ها از Helper استفاده کند.

نمونه:

```php
<?php if (can('payments.approve')): ?>
    <button>...</button>
<?php endif; ?>
```

این فقط برای نمایش است، نه امنیت اصلی.

---

# Customer Portal Rules

پنل مشتری باید جدا از پنل داخلی باشد یا حداقل با Permission کاملاً جدا مدیریت شود.

مشتری نباید به Routeهای داخلی دسترسی داشته باشد.

---

# Sensitive Financial Data Rules

اطلاعات زیر فقط برای نقش‌های مجاز قابل مشاهده است:

```text
درصد سود
نوع سود
فرمول محاسبه
مانده تأمین مالی‌شده داخلی
محاسبات داخلی سود
گزارش کامل سود
```

مشتری نباید این موارد را ببیند.

مشتری فقط موارد زیر را می‌بیند:

```text
مبلغ قسط
مبلغ پرداخت‌شده
جریمه قابل پرداخت
تخفیف قابل اعمال
مبلغ نهایی پرداخت
تاریخ سررسید
وضعیت قسط
```

---

# Delete Permission Rules

حذف همیشه حساس است.

حذف باید:

```text
Permission داشته باشد.
Confirmation داشته باشد.
برای موارد حساس عبارت تأیید داشته باشد.
Audit Log داشته باشد.
در صورت وجود وابستگی محدود شود.
```

قوانین مهم:

```text
اگر مشتری قرارداد فعال دارد، حذف مشتری ممنوع است.
اگر قرارداد اقساط فعال دارد، حذف قرارداد ممنوع است.
پرداخت‌ها و لاگ‌های مالی حذف نشوند.
Timeline حذف نشود.
```

---

# File Permission Rules

فایل‌های خصوصی باید Permission داشته باشند.

قانون:

```text
هیچ فایل private بدون Permission دانلود یا preview نشود.
```

مشتری فقط فایل‌های خودش را می‌بیند.

وکیل فقط فایل‌های حقوقی پرونده‌های مجاز را می‌بیند.

اپراتور فقط فایل‌های لازم برای پیگیری را می‌بیند.

---

# Chat Permission Rules

مشتری فقط می‌تواند با واحدها گفتگو کند، نه با کاربران داخلی به شکل مستقیم.

کاربر داخلی با Permission می‌تواند با مشتری یا کاربر داخلی دیگر گفتگو کند.

کانال عمومی فقط برای اطلاع‌رسانی عمومی است.

اطلاعات خصوصی در کانال عمومی ممنوع است.

---

# Calendar Permission Rules

تقویم باید بر اساس نقش و Scope فیلتر شود.

مشتری فقط رویدادهای خودش را می‌بیند.

وکیل فقط جلسات و رویدادهای حقوقی مرتبط را می‌بیند.

اپراتور فقط پیگیری‌های مجاز خود را می‌بیند.

مدیر همه را طبق Permission می‌بیند.

---

# Notification Permission Rules

کاربر فقط اعلان‌های خودش را می‌بیند.

اگر لینک اعلان به موجودیتی است که کاربر Permission ندارد، نباید قابل مشاهده باشد.

---

# Logs Permission Rules

Logها سطح دسترسی جداگانه دارند.

مشتری Log فنی نمی‌بیند.

اپراتور Logهای محدود خودش را می‌بیند.

وکیل Logهای حقوقی پرونده‌های مجاز را می‌بیند.

مدیر Logهای مدیریتی را می‌بیند.

Security Log فقط برای مدیران مجاز است.

---

# Settings Permission Rules

تنظیمات باید Permissionهای جزئی داشته باشند.

مثلاً کسی که اجازه تغییر لوگو دارد، الزاماً نباید اجازه تغییر تنظیمات مالی داشته باشد.

تنظیمات حساس:

```text
نرخ جریمه
نرخ پاداش
درگاه پرداخت
شماره کارت
قالب قرارداد
بکاپ
بروزرسانی
امنیت
RBAC
```

---

# Permission Storage

پیشنهاد ساختار دیتابیس:

## roles

```text
id
name
display_name
description
is_system
created_at
updated_at
```

## permissions

```text
id
name
display_name
description
module
action
created_at
updated_at
```

## role_permissions

```text
id
role_id
permission_id
scope
created_at
updated_at
```

## user_roles

```text
id
user_id
role_id
created_at
updated_at
```

## user_permissions

برای Permissionهای خاص و استثنایی:

```text
id
user_id
permission_id
scope
allowed
created_at
updated_at
```

---

# Customer Permission Storage

اگر مشتری‌ها جدول جدا دارند، دسترسی مشتری باید جدا مدیریت شود.

مشتری معمولاً Role داخلی ندارد.

پنل مشتری باید با Policyهای ثابت کنترل شود:

```text
CustomerSelfPolicy
CustomerContractPolicy
CustomerPaymentPolicy
CustomerChatPolicy
```

---

# Permission Cache

Permissionها می‌توانند Cache شوند.

اما Cache باید بعد از تغییر موارد زیر Invalid شود:

```text
Role
Permission
User Role
User Permission
Department
User Status
```

---

# Inactive User Rules

اگر کاربر غیرفعال شد:

```text
امکان ورود نداشته باشد.
Sessionهای فعال او باطل شوند.
Job یا Action جدید از طرف او اجرا نشود.
```

---

# Session Permission Refresh

اگر نقش یا Permission کاربر تغییر کرد، سیستم باید یکی از این کارها را انجام دهد:

```text
Invalidate Session
یا
Refresh Permission Cache
```

---

# Permission Denied Response

برای صفحات HTML:

```text
شما مجوز دسترسی به این بخش را ندارید.
```

برای Ajax/API:

```json
{
  "ok": false,
  "message": "شما مجوز انجام این عملیات را ندارید.",
  "code": "PERMISSION_DENIED"
}
```

---

# 403 vs 404

برای داده‌های حساس، اگر کاربر اجازه مشاهده ندارد، می‌توان به جای 403 از 404 امن استفاده کرد تا وجود داده افشا نشود.

نمونه:

```text
قرارداد دیگران
مدرک هویتی دیگران
پرونده حقوقی دیگران
فایل خصوصی
```

---

# Audit Rules

عملیات زیر باید Audit Log داشته باشند:

```text
تغییر نقش کاربر
تغییر Permission
ایجاد کاربر داخلی
حذف یا غیرفعال کردن کاربر
تغییر وضعیت مشتری
دسترسی غیرمجاز
تلاش برای مشاهده داده دیگران
تغییر تنظیمات حساس
تأیید پرداخت
رد پرداخت
تأیید مدرک هویتی
رد مدرک هویتی
بازیابی بکاپ
اجرای بروزرسانی
```

---

# Forbidden Patterns

موارد زیر ممنوع هستند:

```php
if ($_SESSION['role'] === 'admin') {
    // allow everything
}
```

```php
if ($user->role == 'operator') {
    // custom logic everywhere
}
```

```php
// only hide button
<button style="display:none">Delete</button>
```

```php
// no ownership check
$contract = Contract::find($_GET['id']);
```

```php
// raw query without scope
SELECT * FROM contracts
```

---

# Correct Patterns

## Route Middleware

```php
Route::get('/payments/review', [PaymentController::class, 'review'])
    ->middleware('permission:payments.approve');
```

---

## Controller

```php
PermissionService::authorize($currentUser, 'payments.approve');
```

---

## Entity Authorization

```php
$contract = ContractRepository::find($contractId);

PermissionService::authorizeEntity(
    user: $currentUser,
    permission: 'contracts.view',
    entity: $contract
);
```

---

## Scoped Repository

```php
$contracts = ContractRepository::visibleTo($currentUser)
    ->paginate($page, $perPage);
```

---

# Implementation Rules

Codex هنگام پیاده‌سازی هر Feature باید بررسی کند:

```text
این Feature چه Permissionهایی نیاز دارد؟
چه نقش‌هایی مجاز هستند؟
Scope داده چیست؟
آیا مشتری هم به این بخش دسترسی دارد؟
آیا Controller Permission را بررسی می‌کند؟
آیا Service Permission را بررسی می‌کند؟
آیا Repository داده‌ها را Scope کرده؟
آیا UI فقط دکمه‌های مجاز را نشان می‌دهد؟
آیا تغییر ID در URL قابل سوءاستفاده نیست؟
آیا عملیات حساس Audit می‌شود؟
آیا اطلاعات حساس به نقش نامجاز نمایش داده نمی‌شود؟
```

---

# Review Checklist

قبل از Merge هر Feature:

```text
□ Permissionهای لازم تعریف شده‌اند.
□ Routeها محافظت شده‌اند.
□ Controllerها authorize دارند.
□ Serviceها برای عملیات حساس authorize دارند.
□ Queryها بر اساس Scope محدود شده‌اند.
□ مشتری فقط داده خودش را می‌بیند.
□ اپراتور به Users دسترسی ندارد.
□ وکیل فقط پرونده‌های مجاز را می‌بیند.
□ مدیر واحد فقط داده‌های واحد خودش را می‌بیند.
□ اطلاعات مالی حساس از مشتری مخفی است.
□ فایل‌های خصوصی Permission دارند.
□ اعلان‌ها و تقویم با RBAC سازگار هستند.
□ حذف‌ها Permission و Audit دارند.
□ تغییر Role و Permission Audit می‌شود.
□ دسترسی غیرمجاز Security Log دارد.
```

---

# Definition of Done

یک Feature از نظر RBAC زمانی کامل است که:

```text
✔ Permissionهای آن مشخص باشند.
✔ Roleهای مجاز مشخص باشند.
✔ Scope داده مشخص باشد.
✔ دسترسی سمت سرور بررسی شود.
✔ UI با Permission هماهنگ باشد.
✔ Repository داده‌های غیرمجاز برنگرداند.
✔ تغییر ID باعث دسترسی غیرمجاز نشود.
✔ مشتری فقط داده خودش را ببیند.
✔ اپراتور، وکیل و مدیر واحد محدودیت‌های خود را داشته باشند.
✔ عملیات حساس Audit و Security Log داشته باشد.
```

---

# Future Considerations

سیستم باید در آینده آماده موارد زیر باشد:

```text
Permission UI Builder
Custom Roles
Role Templates
Department-Based Permissions
Branch-Based Permissions
Multi-Tenant Permissions
Temporary Permissions
Permission Expiration
Two-Person Approval For Sensitive Actions
Advanced Audit Dashboard
Policy Classes
Attribute-Based Access Control
Row-Level Security Pattern
API Token Permissions
Mobile App Permissions
```

---

# End of File