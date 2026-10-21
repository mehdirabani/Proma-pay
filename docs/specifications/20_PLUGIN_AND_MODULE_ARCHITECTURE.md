# Proma Pay — Plugin & Module Architecture

Version: 1.0  
Status: Master Specification  
Priority: CRITICAL

---

# Introduction

این فایل معماری رسمی ماژولار بودن و پلاگین‌پذیری پروژه Proma Pay را تعریف می‌کند.

هدف این سند این است که Proma Pay فقط یک اسکریپت ثابت نباشد، بلکه به یک پلتفرم قابل توسعه تبدیل شود.

سیستم باید بتواند در آینده قابلیت‌های جدید را از طریق Module یا Plugin دریافت کند، بدون اینکه نیاز باشد هسته اصلی پروژه برای هر قابلیت جدید تغییر کند.

نمونه قابلیت‌هایی که می‌توانند به صورت Plugin توسعه داده شوند:

```text
Offline Mode
Sync بعد از اتصال اینترنت
اتصال به پیامک‌دهنده جدید
اتصال به درگاه پرداخت جدید
سیستم گزارش‌گیری پیشرفته
سیستم حسابداری
هوش مصنوعی
خروجی اکسل پیشرفته
پلاگین امضای دیجیتال
پلاگین باشگاه مشتریان
پلاگین پیامک اقساط
پلاگین نسخه موبایل
پلاگین اتصال به API خارجی
```

---

# Core Principle

هسته Proma Pay باید کوچک، پایدار و قابل اعتماد بماند.

قابلیت‌های توسعه‌ای، اختیاری، جانبی یا وابسته به سرویس‌های بیرونی باید تا حد امکان به صورت Plugin پیاده‌سازی شوند.

---

# Core vs Module vs Plugin

## Core

Core بخش اصلی و غیرقابل حذف سیستم است.

موارد Core:

```text
Authentication
Authorization / RBAC
Database Connection
Router
Controller Base
Service Layer
Event Dispatcher
Plugin Manager
Settings System
File Storage
Logging
Audit
Notification Base
Installer
Updater
Backup System
Security Layer
```

---

## Module

Module بخش داخلی و رسمی سیستم است که همراه محصول نصب می‌شود.

نمونه Moduleهای داخلی:

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
Users
Notifications
Files
```

Moduleها بخشی از محصول اصلی هستند اما باید تا حد امکان مستقل طراحی شوند.

---

## Plugin

Plugin قابلیت اختیاری است که بعداً نصب، فعال، غیرفعال یا حذف می‌شود.

Plugin نباید باعث وابستگی خطرناک در Core شود.

Plugin می‌تواند قابلیت جدید اضافه کند، اما نباید امنیت، دیتابیس، Permission یا عملکرد سیستم را خراب کند.

---

# Constitution

## Rule 1 — Core Must Not Depend On Plugins

هسته سیستم نباید به هیچ پلاگین خاصی وابسته باشد.

ممنوع:

```text
Core مستقیماً کلاس پلاگین OfflineSync را صدا بزند.
Core بدون وجود یک پلاگین خاص خطا بدهد.
حذف پلاگین باعث خرابی پنل اصلی شود.
```

صحیح:

```text
Core فقط Event Dispatch کند.
Plugin در صورت فعال بودن به Event گوش دهد.
اگر Plugin وجود نداشت، Core همچنان کار کند.
```

---

## Rule 2 — Plugins Must Use Public Extension Points

پلاگین‌ها فقط از نقاط توسعه رسمی استفاده می‌کنند.

Extension Pointهای مجاز:

```text
Events
Hooks
Filters
Service Contracts
Plugin Routes
Plugin Views
Plugin Settings
Plugin Permissions
Plugin Migrations
Sidebar Registry
Dashboard Widget Registry
Report Registry
Payment Gateway Registry
Notification Channel Registry
```

---

## Rule 3 — Plugin Must Be Installable And Removable

هر پلاگین باید بتواند:

```text
نصب شود.
فعال شود.
غیرفعال شود.
حذف شود.
بروزرسانی شود.
```

بدون اینکه داده‌های اصلی سیستم خراب شود.

---

## Rule 4 — Plugin Must Declare Everything

پلاگین نباید چیزی را مخفیانه به سیستم اضافه کند.

هر پلاگین باید در Manifest خود اعلام کند:

```text
نام پلاگین
نسخه
سازنده
حداقل نسخه Proma Pay
Permissionها
Routeها
Migrationها
Event Listenerها
Settings
Assetها
Menuها
Widgetها
Dependencies
```

---

## Rule 5 — Plugin Must Respect RBAC

هیچ پلاگینی نباید Permission سیستم را دور بزند.

تمام Routeها، Actionها، Viewها و عملیات پلاگین باید با PermissionService بررسی شوند.

---

## Rule 6 — Plugin Must Respect Security Constitution

پلاگین‌ها باید تمام قوانین امنیتی پروژه را رعایت کنند.

مخصوصاً:

```text
CSRF
XSS
SQL Injection
File Upload
Private Storage
RBAC
Audit Log
No Raw Error
No Sensitive Data Exposure
```

---

## Rule 7 — Plugin Database Changes Must Be Safe

پلاگین‌ها می‌توانند Migration داشته باشند، اما Migrationها باید امن، قابل تکرار و قابل Rollback باشند.

پلاگین نباید جدول‌های Core را بدون قرارداد مشخص تغییر دهد.

---

## Rule 8 — Plugin Failure Must Not Break Core

اگر پلاگین خطا داد، Core نباید کامل از کار بیفتد.

خطای پلاگین باید:

```text
Log شود.
به مدیر نمایش داده شود.
پلاگین در صورت نیاز غیرفعال شود.
Core به کار خود ادامه دهد.
```

---

# Plugin Package Structure

ساختار پیشنهادی هر پلاگین:

```text
plugins/
└── offline-sync/
    ├── plugin.json
    ├── src/
    │   ├── OfflineSyncPlugin.php
    │   ├── Services/
    │   ├── Controllers/
    │   ├── Repositories/
    │   ├── Listeners/
    │   └── Jobs/
    ├── migrations/
    ├── views/
    ├── assets/
    │   ├── css/
    │   └── js/
    ├── routes.php
    ├── permissions.php
    ├── events.php
    ├── settings.php
    └── README.md
```

---

# Plugin Manifest

هر پلاگین باید فایل زیر را داشته باشد:

```text
plugin.json
```

نمونه:

```json
{
  "id": "offline-sync",
  "name": "Offline Sync",
  "display_name": "حالت آفلاین و همگام‌سازی",
  "version": "1.0.0",
  "type": "plugin",
  "author": "Mehdi Rabani",
  "description": "امکان استفاده محدود از پنل در حالت قطع اینترنت و همگام‌سازی پس از اتصال.",
  "min_proma_version": "1.0.0",
  "max_proma_version": "2.0.0",
  "requires": [],
  "permissions": [
    "offline_sync.view",
    "offline_sync.manage"
  ],
  "routes": "routes.php",
  "migrations": "migrations/",
  "settings": "settings.php",
  "events": "events.php",
  "assets": {
    "css": [],
    "js": []
  }
}
```

---

# Plugin Lifecycle

هر پلاگین باید چرخه عمر مشخص داشته باشد.

```text
Uploaded
↓
Validated
↓
Installed
↓
Enabled
↓
Running
↓
Disabled
↓
Uninstalled
```

---

## Install

در زمان نصب پلاگین:

```text
plugin.json بررسی شود.
سازگاری نسخه بررسی شود.
Permissionها ثبت شوند.
Migrationها اجرا شوند.
Settings پیش‌فرض ثبت شوند.
Assetها ثبت شوند.
Log نصب ثبت شود.
```

---

## Enable

در زمان فعال‌سازی:

```text
وضعیت پلاگین active شود.
Event Listenerها فعال شوند.
Routeها ثبت شوند.
Menuها فعال شوند.
Widgetها فعال شوند.
Notification ایجاد شود.
Audit Log ثبت شود.
```

---

## Disable

در زمان غیرفعال‌سازی:

```text
Routeهای پلاگین غیرفعال شوند.
Listenerهای پلاگین غیرفعال شوند.
Menuهای پلاگین مخفی شوند.
Jobهای پلاگین متوقف شوند.
داده‌های پلاگین حذف نشوند.
Audit Log ثبت شود.
```

---

## Uninstall

در زمان حذف پلاگین:

```text
ابتدا پلاگین Disable شود.
در صورت تأیید مدیر، داده‌های پلاگین حذف یا Archive شوند.
Migration حذف فقط با احتیاط اجرا شود.
فایل‌های پلاگین حذف شوند.
Permissionهای پلاگین حذف یا غیرفعال شوند.
Audit Log ثبت شود.
```

حذف پلاگین نباید داده‌های Core را حذف کند.

---

# Plugin Manager

سیستم باید یک Plugin Manager مرکزی داشته باشد.

سرویس پیشنهادی:

```text
PluginManager
```

مسئولیت‌ها:

```text
کشف پلاگین‌ها
خواندن plugin.json
اعتبارسنجی پلاگین
نصب پلاگین
فعال‌سازی پلاگین
غیرفعال‌سازی پلاگین
حذف پلاگین
اجرای Migration پلاگین
ثبت Routeهای پلاگین
ثبت Listenerهای پلاگین
ثبت Permissionهای پلاگین
ثبت Settings پلاگین
مدیریت نسخه پلاگین
بررسی سازگاری
```

---

# Plugin Registry

سیستم باید پلاگین‌های نصب‌شده را در دیتابیس ثبت کند.

جدول پیشنهادی:

```text
plugins
```

فیلدهای پیشنهادی:

```text
id
plugin_id
name
display_name
version
status
installed_version
min_proma_version
author
description
path
installed_at
enabled_at
disabled_at
updated_at
metadata
```

---

# Plugin Migration Table

برای Migrationهای پلاگین جدول جداگانه یا فیلد مشخص لازم است.

نام پیشنهادی:

```text
plugin_migrations
```

فیلدهای پیشنهادی:

```text
id
plugin_id
migration_name
batch
executed_at
status
error_message
```

---

# Plugin Settings

هر پلاگین می‌تواند Settings مخصوص خود داشته باشد.

نام پیشنهادی جدول:

```text
plugin_settings
```

فیلدهای پیشنهادی:

```text
id
plugin_id
setting_key
setting_value
setting_type
is_encrypted
created_at
updated_at
```

اطلاعات حساس پلاگین باید encrypted ذخیره شود.

---

# Plugin Permissions

پلاگین‌ها می‌توانند Permission جدید اضافه کنند.

مثال:

```text
offline_sync.view
offline_sync.manage
offline_sync.force_sync
offline_sync.resolve_conflicts
```

Permissionهای پلاگین باید در RBAC سیستم ثبت شوند.

---

# Plugin Routes

Routeهای پلاگین باید Prefix داشته باشند.

الگو:

```text
/plugins/{plugin_id}/...
```

نمونه:

```text
/plugins/offline-sync/settings
/plugins/offline-sync/status
/plugins/offline-sync/sync-now
```

تمام Routeهای پلاگین باید Permission داشته باشند.

---

# Plugin Views

Viewهای پلاگین نباید Layout مستقل و ناسازگار داشته باشند.

پلاگین باید از Layout اصلی Proma Pay استفاده کند.

قوانین:

```text
RTL
Yekan Bakh
Design System
Toast
Modal
Card
Table
Responsive
```

---

# Plugin Assets

Assetهای پلاگین باید فقط زمانی Load شوند که لازم هستند.

ممنوع:

```text
لود کردن CSS/JS پلاگین در تمام صفحات بدون نیاز
```

صحیح:

```text
لود Asset فقط در صفحه پلاگین یا Extension Point مربوطه
```

---

# Hooks

Hookها نقاط توسعه مستقیم هستند.

نمونه Hookها:

```text
before_contract_create
after_contract_created
before_payment_approve
after_payment_approved
after_receipt_uploaded
after_identity_document_approved
before_report_export
dashboard_widgets
sidebar_menu_items
settings_tabs
```

Hook نباید جایگزین Eventهای اصلی شود.

---

# Events

پلاگین‌ها باید برای واکنش به عملیات سیستم از Event استفاده کنند.

نمونه:

```text
PaymentApproved
ContractCreated
InstallmentOverdue
CustomerCreated
LegalCaseReferred
BackupCompleted
UpdateCompleted
```

پلاگین باید Listener خود را ثبت کند.

---

# Service Contracts

برای توسعه حرفه‌ای، Core باید Interface یا Contract برای سرویس‌های قابل جایگزینی داشته باشد.

نمونه:

```text
PaymentGatewayInterface
SmsProviderInterface
NotificationChannelInterface
ReportExporterInterface
StorageDriverInterface
SyncProviderInterface
```

پلاگین می‌تواند یک پیاده‌سازی جدید از این Interfaceها ارائه دهد.

---

# Module System

Moduleهای داخلی نیز باید تا حد امکان مثل Pluginها طراحی شوند.

هر Module بهتر است ساختار مشخص داشته باشد:

```text
modules/
└── payments/
    ├── Controllers/
    ├── Services/
    ├── Repositories/
    ├── Views/
    ├── Events/
    ├── Listeners/
    ├── migrations/
    └── module.json
```

اما Moduleهای اصلی همراه Core نصب می‌شوند و حذف آن‌ها معمولاً مجاز نیست.

---

# Built-In Modules

Moduleهای رسمی سیستم:

```text
customers
users
contracts
installments
payments
legal
chat
calendar
notifications
reports
settings
files
backup
update
```

---

# Module Manifest

هر Module داخلی می‌تواند فایل زیر داشته باشد:

```text
module.json
```

نمونه:

```json
{
  "id": "payments",
  "name": "Payments",
  "display_name": "پرداخت‌ها",
  "version": "1.0.0",
  "core": true,
  "enabled": true,
  "permissions": [],
  "events": [],
  "migrations": []
}
```

---

# Extension Points

سیستم باید Extension Pointهای رسمی داشته باشد.

## Sidebar Extension

پلاگین بتواند آیتم منو اضافه کند.

```text
sidebar_menu_items
```

---

## Settings Extension

پلاگین بتواند تب تنظیمات اضافه کند.

```text
settings_tabs
```

---

## Dashboard Extension

پلاگین بتواند Widget داشبورد اضافه کند.

```text
dashboard_widgets
```

---

## Report Extension

پلاگین بتواند گزارش جدید اضافه کند.

```text
reports_registry
```

---

## Payment Gateway Extension

پلاگین بتواند درگاه پرداخت جدید اضافه کند.

```text
payment_gateways
```

---

## Notification Channel Extension

پلاگین بتواند کانال اعلان جدید اضافه کند.

```text
notification_channels
```

---

## Calendar Extension

پلاگین بتواند Event Source جدید برای تقویم اضافه کند.

```text
calendar_event_sources
```

---

## File Storage Extension

پلاگین بتواند Storage Driver جدید اضافه کند.

```text
storage_drivers
```

---

# Offline Plugin Example

قابلیت Offline Mode باید به صورت پلاگین قابل توسعه باشد.

نام پیشنهادی:

```text
offline-sync
```

هدف:

```text
اگر اینترنت قطع بود، بخش‌هایی از پنل همچنان قابل استفاده باشند.
بعد از اتصال اینترنت، داده‌ها با سرور اصلی Sync شوند.
```

---

# Offline Plugin Scope

Offline Mode نباید تمام سیستم را بدون کنترل آفلاین کند.

بخش‌های قابل آفلاین شدن باید محدود و مشخص باشند.

نمونه مجاز:

```text
مشاهده مشتریان Cache شده
ثبت یادداشت پیگیری
ثبت وعده پرداخت
مشاهده اقساط Cache شده
ثبت عملیات اپراتور به صورت Pending
مشاهده قراردادهای قبلاً Sync شده
```

نمونه پرریسک و نیازمند طراحی دقیق:

```text
تأیید پرداخت
حذف قرارداد
تغییر تنظیمات مالی
اجرای عملیات حقوقی حساس
بروزرسانی سیستم
Restore Backup
```

---

# Offline Data Storage

برای نسخه وب، داده آفلاین می‌تواند در مرورگر ذخیره شود.

گزینه‌های احتمالی:

```text
IndexedDB
Service Worker Cache
Local Storage فقط برای داده غیرحساس
```

داده حساس نباید بدون رمزنگاری مناسب در مرورگر ذخیره شود.

---

# Offline Security Rules

در حالت Offline:

```text
Session باید معتبر باشد.
دسترسی‌ها باید از آخرین Permission Cache خوانده شوند.
اطلاعات حساس محدود شود.
داده مالی حساس تا حد امکان ذخیره نشود.
عملیات پرریسک فقط Online انجام شود.
تمام عملیات آفلاین باید Pending Queue داشته باشند.
```

---

# Offline Sync Queue

پلاگین Offline باید Queue داشته باشد.

جدول یا Storage پیشنهادی:

```text
offline_sync_queue
```

فیلدهای پیشنهادی:

```text
id
operation_type
entity_type
entity_id
local_id
payload
status
attempts
last_error
created_at
synced_at
conflict_status
```

---

# Sync States

وضعیت‌های Sync:

```text
pending
syncing
synced
failed
conflict
discarded
```

---

# Conflict Resolution

اگر داده آفلاین با داده آنلاین تضاد داشت، سیستم باید Conflict را تشخیص دهد.

نمونه Conflict:

```text
اپراتور در حالت آفلاین وعده پرداخت ثبت کرده.
در همین زمان مدیر همان پرونده را به حقوقی ارجاع داده.
```

رفتار صحیح:

```text
داده آفلاین مستقیم overwrite نکند.
Conflict ثبت شود.
مدیر یا نقش مجاز تصمیم بگیرد.
Timeline و Log ثبت شود.
```

---

# Offline Sync Rules

قوانین Sync:

```text
هر عملیات آفلاین باید actor داشته باشد.
هر عملیات آفلاین باید زمان محلی و زمان Sync داشته باشد.
داده‌ها نباید بدون بررسی Permission Sync شوند.
اگر Permission کاربر بعداً حذف شده، عملیات آفلاین باید بررسی مجدد شود.
هر Sync باید Log داشته باشد.
Conflict باید قابل مشاهده باشد.
```

---

# Offline Plugin Limitations

Offline Plugin نباید وعده دهد که تمام سیستم بدون اینترنت کامل کار می‌کند.

قابلیت آفلاین باید محدود، شفاف و امن باشد.

در UI باید وضعیت اتصال نمایش داده شود:

```text
آنلاین
آفلاین
در حال همگام‌سازی
نیازمند بررسی تداخل
```

---

# Plugin Installation From ZIP

سیستم باید امکان نصب پلاگین از ZIP داشته باشد.

فرآیند:

```text
Upload Plugin ZIP
Validate ZIP
Read plugin.json
Check Compatibility
Check Required Permissions
Check Dangerous Files
Run Install Migrations
Register Plugin
Enable Plugin
```

---

# Plugin ZIP Validation

موارد ممنوع در پلاگین ZIP:

```text
.php خارج از ساختار مجاز
فایل اجرایی ناشناس
فایل .env
فایل config واقعی
کد obfuscated مشکوک
SQL خطرناک
دسترسی مستقیم به فایل‌های Core
فایل با مسیر ../
```

---

# Plugin Update

پلاگین باید قابل بروزرسانی باشد.

فرآیند:

```text
Upload New Plugin Version
Check plugin_id
Check Current Version
Check Compatibility
Backup Plugin Data
Run Plugin Migrations
Replace Plugin Files
Update Registry
Log Update
```

---

# Plugin Disable Safety

قبل از Disable پلاگین باید بررسی شود:

```text
آیا پلاگین Job فعال دارد؟
آیا پلاگین داده Pending دارد؟
آیا پلاگین عملیات Sync ناتمام دارد؟
آیا پلاگین توسط پلاگین دیگری نیاز است؟
```

اگر خطر وجود داشت، مدیر باید هشدار ببیند.

---

# Plugin Dependencies

پلاگین می‌تواند وابستگی داشته باشد.

نمونه:

```json
"requires": [
  {
    "plugin_id": "advanced-reports",
    "min_version": "1.0.0"
  }
]
```

اگر Dependency نصب یا فعال نبود، پلاگین نباید فعال شود.

---

# Plugin Database Rules

پلاگین‌ها باید جدول‌های خود را با Prefix مشخص بسازند.

الگو:

```text
plugin_{plugin_id}_{table_name}
```

نمونه:

```text
plugin_offline_sync_queue
plugin_offline_sync_conflicts
```

پلاگین نباید بدون دلیل جدول Core را تغییر دهد.

---

# Plugin File Storage Rules

فایل‌های پلاگین باید مسیر جدا داشته باشند.

```text
storage/private/plugins/{plugin_id}/
storage/public/plugins/{plugin_id}/
```

فایل‌های خصوصی پلاگین همچنان باید از FileStorageService استفاده کنند.

---

# Plugin Logging Rules

تمام عملیات مهم پلاگین باید Log داشته باشد.

Log باید شامل plugin_id باشد.

فیلد پیشنهادی:

```text
plugin_id
```

نمونه Logها:

```text
Plugin Installed
Plugin Enabled
Plugin Disabled
Plugin Updated
Plugin Failed
Offline Operation Queued
Offline Sync Completed
Offline Sync Conflict Detected
```

---

# Plugin Error Handling

اگر پلاگین خطا داد:

```text
خطا Log شود.
کاربر پیام فارسی مناسب ببیند.
اگر خطا Critical بود، پلاگین غیرفعال شود.
Core نباید Fatal شود.
```

---

# Plugin Security Review

قبل از نصب پلاگین باید هشدار امنیتی نمایش داده شود.

مدیر باید بداند پلاگین ممکن است به چه چیزهایی دسترسی داشته باشد:

```text
Permissionهای درخواستی
Routeهای جدید
Migrationهای جدید
دسترسی به فایل‌ها
دسترسی به Eventها
Jobهای پس‌زمینه
```

---

# Plugin Marketplace Readiness

معماری باید در آینده آماده فروش یا نصب پلاگین از Marketplace باشد.

قابلیت‌های آینده:

```text
Plugin License
Plugin Activation
Plugin Updates
Plugin Signature
Plugin Checksum
Remote Plugin Repository
Developer Documentation
Plugin Review Process
```

---

# Plugin UI Rules

صفحه مدیریت پلاگین‌ها باید شامل موارد زیر باشد:

```text
لیست پلاگین‌ها
وضعیت فعال/غیرفعال
نسخه
سازنده
توضیحات
دکمه نصب
دکمه فعال‌سازی
دکمه غیرفعال‌سازی
دکمه بروزرسانی
دکمه حذف
نمایش Permissionهای پلاگین
نمایش خطاهای پلاگین
نمایش Log پلاگین
```

---

# Plugin Admin Routes

Routeهای پیشنهادی مدیریت پلاگین:

```text
/settings/plugins
/settings/plugins/upload
/settings/plugins/{plugin_id}/install
/settings/plugins/{plugin_id}/enable
/settings/plugins/{plugin_id}/disable
/settings/plugins/{plugin_id}/update
/settings/plugins/{plugin_id}/uninstall
/settings/plugins/{plugin_id}/logs
```

این Routeها فقط برای مدیران مجاز قابل دسترسی هستند.

---

# Plugin Permissions

Permissionهای پایه:

```text
plugins.view
plugins.upload
plugins.install
plugins.enable
plugins.disable
plugins.update
plugins.uninstall
plugins.view_logs
plugins.manage_settings
```

---

# Plugin Events

Eventهای مربوط به پلاگین:

```text
PluginUploaded
PluginValidated
PluginInstalled
PluginEnabled
PluginDisabled
PluginUpdated
PluginUninstalled
PluginFailed
```

---

# Plugin Hooks For Core

Core باید Hookهای عمومی زیر را پشتیبانی کند:

```text
app_booting
app_booted
before_route_dispatch
after_route_dispatch
before_view_render
after_view_render
before_settings_save
after_settings_save
before_backup_create
after_backup_created
before_update_apply
after_update_applied
```

استفاده از Hookهای حساس باید محدود و امن باشد.

---

# Plugin Sandbox Limitations

در PHP کامل Sandbox واقعی ساده نیست.

بنابراین امنیت پلاگین باید با این روش‌ها کنترل شود:

```text
اعتبارسنجی ساختار پلاگین
Permissionهای واضح
محدودیت Extension Points
عدم اجازه ویرایش مستقیم Core
عدم اجازه اجرای فایل‌های ناشناس
Review قبل از نصب
Log کامل
امکان Disable فوری
Backup قبل از نصب یا Update
```

---

# Backup Rules For Plugins

Backup باید شامل موارد زیر باشد:

```text
فایل‌های پلاگین‌های نصب‌شده
تنظیمات پلاگین‌ها
داده‌های دیتابیس پلاگین‌ها
Metadata پلاگین‌ها
```

قبل از نصب یا بروزرسانی پلاگین، Backup پیشنهاد یا اجبار شود.

---

# Update System Integration

سیستم Update باید با Pluginها سازگار باشد.

در زمان آپدیت Core:

```text
سازگاری پلاگین‌ها بررسی شود.
پلاگین ناسازگار هشدار بگیرد.
در صورت خطر، پلاگین موقتاً Disable شود.
Migrationهای Core و Plugin تداخل نداشته باشند.
```

---

# Release Rules For Plugin System

نسخه تجاری Proma Pay باید حداقل شامل موارد زیر باشد:

```text
Plugin Manager
Plugin Registry
Plugin Manifest Reader
Plugin Permission Registration
Plugin Migration Runner
Plugin Enable/Disable
Plugin Settings Registry
Plugin Log Viewer
```

اما لازم نیست از نسخه اول Marketplace کامل داشته باشد.

---

# Forbidden Patterns

موارد زیر ممنوع هستند:

```php
// Core directly calling plugin class
OfflineSyncPlugin::sync();
```

```php
// Plugin editing core file directly
file_put_contents('app/Core/Auth.php', $code);
```

```php
// Plugin route without permission
Route::get('/offline-sync/admin', '...');
```

```php
// Plugin raw SQL without migration safety
DROP TABLE contracts;
```

```php
// Plugin showing private data without RBAC
echo $customerNationalId;
```

---

# Correct Patterns

## Event Listener Pattern

```php
EventDispatcher::listen(PaymentApproved::class, [
    OfflineSyncPaymentListener::class,
    'handle'
]);
```

---

## Service Contract Pattern

```php
interface SmsProviderInterface
{
    public function send(string $mobile, string $message): bool;
}
```

پلاگین پیامک می‌تواند این Interface را پیاده‌سازی کند.

---

## Plugin Registration Pattern

```php
class OfflineSyncPlugin extends BasePlugin
{
    public function register(): void
    {
        $this->registerRoutes(__DIR__ . '/../routes.php');
        $this->registerPermissions(__DIR__ . '/../permissions.php');
        $this->listen(ContractCreated::class, SyncContractListener::class);
    }

    public function boot(): void
    {
        $this->loadViews(__DIR__ . '/../views');
        $this->loadAssets(__DIR__ . '/../assets');
    }
}
```

---

# Implementation Rules

Codex هنگام پیاده‌سازی Plugin System باید بررسی کند:

```text
Core به پلاگین وابسته نشده باشد.
Plugin Manager مرکزی وجود داشته باشد.
plugin.json اعتبارسنجی شود.
Permissionهای پلاگین ثبت شوند.
Routeهای پلاگین محافظت شوند.
Migrationهای پلاگین امن باشند.
پلاگین قابل Enable/Disable باشد.
خطای پلاگین Core را خراب نکند.
Log و Audit برای نصب و تغییرات ثبت شود.
Backup قبل از نصب/آپدیت پلاگین در نظر گرفته شود.
Extension Pointها مستند باشند.
```

---

# Review Checklist

قبل از Merge قابلیت Plugin System:

```text
□ Plugin Manager وجود دارد.
□ Plugin Registry در دیتابیس وجود دارد.
□ plugin.json خوانده و Validate می‌شود.
□ نصب پلاگین از ZIP کنترل می‌شود.
□ فعال/غیرفعال‌سازی پلاگین امن است.
□ Permissionهای پلاگین ثبت می‌شوند.
□ Routeهای پلاگین Permission دارند.
□ Migrationهای پلاگین امن هستند.
□ پلاگین نمی‌تواند Core را مستقیم خراب کند.
□ Log و Audit ثبت می‌شود.
□ خطای پلاگین مدیریت می‌شود.
□ UI مدیریت پلاگین‌ها وجود دارد.
□ Backup قبل از تغییرات مهم بررسی می‌شود.
□ Offline Plugin به عنوان نمونه قابل توسعه در نظر گرفته شده است.
```

---

# Definition of Done

قابلیت Plugin & Module Architecture زمانی کامل است که:

```text
✔ سیستم بتواند پلاگین را شناسایی کند.
✔ پلاگین بتواند نصب شود.
✔ پلاگین بتواند فعال و غیرفعال شود.
✔ پلاگین Permission ثبت کند.
✔ پلاگین Route امن داشته باشد.
✔ پلاگین Settings داشته باشد.
✔ پلاگین Migration امن داشته باشد.
✔ پلاگین Event Listener ثبت کند.
✔ خطای پلاگین Core را از کار نیندازد.
✔ عملیات پلاگین Log و Audit داشته باشد.
✔ سیستم آماده توسعه پلاگین‌های آینده باشد.
```

---

# Future Considerations

سیستم باید در آینده آماده موارد زیر باشد:

```text
Plugin Marketplace
Remote Plugin Repository
Plugin License Manager
Plugin Auto Update
Plugin Signature Verification
Plugin Developer SDK
Plugin CLI Generator
Plugin Dependency Resolver
Plugin Compatibility Scanner
Plugin Security Scanner
Offline Sync Plugin
Accounting Plugin
SMS Provider Plugins
Payment Gateway Plugins
AI Assistant Plugin
Mobile App Sync Plugin
```

---

# Final Note

از این به بعد Proma Pay باید به عنوان یک Platform طراحی شود، نه فقط یک Script.

هر قابلیت جدید باید بررسی شود:

```text
آیا این قابلیت باید داخل Core باشد؟
آیا باید Module باشد؟
آیا بهتر است Plugin باشد؟
```

اگر قابلیت برای همه کاربران ضروری نیست، وابسته به سرویس خارجی است، یا ممکن است نسخه‌های مختلف داشته باشد، بهتر است به صورت Plugin طراحی شود.

---

# End of File