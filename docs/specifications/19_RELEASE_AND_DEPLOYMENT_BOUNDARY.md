# Proma Pay — Release & Deployment Boundary

Version: 1.0  
Status: Master Specification  
Priority: CRITICAL

---

# Introduction

این فایل استاندارد رسمی آماده‌سازی، انتشار، بسته‌بندی و Deploy پروژه Proma Pay است.

هدف این سند مشخص کردن مرز بین نسخه توسعه، نسخه تست، نسخه نصب‌شونده و نسخه تجاری قابل انتشار است.

Proma Pay قرار است به‌عنوان یک اسکریپت حرفه‌ای قابل نصب و فروش منتشر شود؛ بنابراین خروجی نهایی باید تمیز، امن، سبک، قابل نصب، قابل بروزرسانی و بدون فایل‌های اضافی باشد.

هیچ نسخه‌ای نباید بدون رعایت قوانین این سند منتشر شود.

---

# Constitution

## Rule 1 — Development Files Must Never Be Released

فایل‌های مربوط به توسعه نباید وارد نسخه نهایی شوند.

موارد ممنوع در Release ZIP:

```text
.git/
.github/
.vscode/
.idea/
node_modules/
vendor-dev/
tests/
.env
.env.local
.env.example با اطلاعات واقعی
debug.log
error.log
*.sql واقعی
*.zip بکاپ واقعی
*.bak
*.tmp
*.cache
installed.lock
config/database.php با اطلاعات واقعی
storage/logs/*
storage/cache/*
storage/temp/*
storage/private/backups/*
```

---

## Rule 2 — No Real Data In Release

نسخه نهایی نباید شامل اطلاعات واقعی باشد.

موارد ممنوع:

```text
اطلاعات مشتری واقعی
قرارداد واقعی
پرداخت واقعی
رسید واقعی
مدرک هویتی واقعی
فایل حقوقی واقعی
اطلاعات دیتابیس واقعی
شماره کارت واقعی مگر به‌صورت نمونه
کلید API واقعی
توکن واقعی
Session واقعی
Backup واقعی
```

---

## Rule 3 — Release Must Be Installable

هر نسخه نهایی باید بدون نیاز به دخالت توسعه‌دهنده قابل نصب باشد.

نصب باید از طریق:

```text
Installer Web UI
```

یا راهنمای نصب واضح انجام شود.

---

## Rule 4 — Release Must Be Update-Safe

نسخه منتشرشده باید قابلیت بروزرسانی نسخه‌های قبلی را داشته باشد.

هیچ بروزرسانی نباید باعث حذف داده مشتریان، قراردادها، اقساط، پرداخت‌ها، فایل‌ها یا تنظیمات شود.

---

## Rule 5 — Release Must Be Shared-Hosting Compatible

Proma Pay باید روی هاست اشتراکی معمولی قابل اجرا باشد.

نباید نیاز اجباری به موارد زیر داشته باشد:

```text
SSH
Root Access
Supervisor
Redis اجباری
Queue Worker اجباری
Composer در سرور مقصد
Node.js در سرور مقصد
دسترسی مستقیم به Cron پیشرفته
```

اگر قابلیتی نیاز به این موارد دارد، باید اختیاری باشد.

---

## Rule 6 — Release Must Be Secure By Default

پس از نصب اولیه، سیستم باید در حالت امن اجرا شود.

نمونه:

```text
Debug Mode خاموش باشد.
نمایش خطای خام غیرفعال باشد.
Installer بعد از نصب قفل شود.
فایل‌های Private مستقیم قابل دسترسی نباشند.
تنظیمات دیتابیس عمومی نباشد.
```

---

## Rule 7 — Release Must Have Version

هر نسخه باید شماره نسخه مشخص داشته باشد.

الگوی پیشنهادی:

```text
MAJOR.MINOR.PATCH
```

نمونه:

```text
1.0.0
1.1.0
1.1.1
```

---

# Release Types

انواع نسخه‌ها:

```text
development
staging
beta
release_candidate
production
hotfix
```

---

## development

نسخه مخصوص توسعه.

ویژگی‌ها:

```text
Debug فعال است.
Log کامل فعال است.
داده تست وجود دارد.
فایل‌های توسعه وجود دارند.
برای انتشار ممنوع است.
```

---

## staging

نسخه تست قبل از انتشار.

ویژگی‌ها:

```text
شبیه Production است.
داده تست کنترل‌شده دارد.
Debug خاموش است یا محدود است.
برای تست نهایی استفاده می‌شود.
```

---

## beta

نسخه آزمایشی برای تست محدود.

ویژگی‌ها:

```text
ممکن است باگ داشته باشد.
نباید برای مشتری نهایی بدون هشدار استفاده شود.
باید برچسب Beta داشته باشد.
```

---

## release_candidate

نسخه نامزد انتشار.

ویژگی‌ها:

```text
تقریباً نهایی است.
فقط باگ‌فیکس مجاز است.
Feature جدید اضافه نمی‌شود.
```

---

## production

نسخه نهایی قابل نصب و فروش.

ویژگی‌ها:

```text
Debug خاموش است.
فایل اضافی ندارد.
امن است.
نصب‌پذیر است.
مستندات دارد.
قابل بروزرسانی است.
```

---

## hotfix

نسخه اضطراری برای رفع مشکل مهم.

ویژگی‌ها:

```text
کمترین تغییر ممکن
تمرکز روی رفع باگ
بدون Feature جدید
دارای توضیح دقیق مشکل
```

---

# Versioning Rules

## MAJOR

زمانی تغییر می‌کند که:

```text
ساختار اصلی سیستم تغییر کند.
Backward Compatibility شکسته شود.
نیاز به Migration بزرگ باشد.
```

نمونه:

```text
1.x.x → 2.0.0
```

---

## MINOR

زمانی تغییر می‌کند که:

```text
Feature جدید اضافه شود.
قابلیت موجود توسعه پیدا کند.
Backward Compatibility حفظ شود.
```

نمونه:

```text
1.0.0 → 1.1.0
```

---

## PATCH

زمانی تغییر می‌کند که:

```text
Bug Fix انجام شود.
Security Fix انجام شود.
Performance Fix انجام شود.
تغییر کوچک بدون Feature جدید باشد.
```

نمونه:

```text
1.0.0 → 1.0.1
```

---

# Required Release Files

هر نسخه نهایی باید شامل فایل‌های زیر باشد:

```text
README.md
INSTALL.md
CHANGELOG.md
LICENSE.md
REQUIREMENTS.md
UPDATE_GUIDE.md
config/database.sample.php
install/
public/
app/
assets/
routes/
storage/.gitkeep
```

---

# Optional Release Files

فایل‌های اختیاری:

```text
FAQ.md
TROUBLESHOOTING.md
SECURITY.md
MIGRATION_GUIDE.md
DEMO_DATA_GUIDE.md
```

---

# Required Documentation

نسخه نهایی باید حداقل این مستندات را داشته باشد:

```text
راهنمای نصب
راهنمای بروزرسانی
نیازمندی‌های سرور
راهنمای دسترسی‌ها
راهنمای بکاپ
راهنمای Restore
راهنمای خطاهای رایج
```

---

# Server Requirements

حداقل نیازمندی‌ها:

```text
PHP 7.4+
MySQL 5.7+ یا MariaDB 10.3+
PDO
pdo_mysql
mbstring
json
openssl
fileinfo
curl
```

پیشنهادی:

```text
PHP 8.1
MySQL 8 یا MariaDB 10.6+
OPcache
ZipArchive
GD
mod_rewrite
HTTPS
```

---

# Optional Extensions

موارد اختیاری:

```text
ZipArchive
GD
Imagick
intl
redis
```

اگر Extension اختیاری وجود نداشت، سیستم نباید Fatal Error بدهد.

نمونه:

```text
اگر ZipArchive نبود، Backup باید fallback داشته باشد.
اگر GD نبود، Image Optimization باید غیرفعال شود نه اینکه کل Upload خراب شود.
```

---

# PHP Compatibility

پروژه باید با PHP 7.4 سازگار باشد.

استفاده از قابلیت‌هایی که فقط در نسخه‌های جدیدتر PHP وجود دارند، بدون Polyfill ممنوع است.

نمونه:

```text
array_is_list
```

اگر استفاده شد، باید Polyfill داشته باشد.

---

# Database Release Rules

نسخه نهایی نباید شامل دیتابیس واقعی باشد.

مجاز:

```text
schema.sql
sample_data.sql اختیاری
migrations/
seeds/demo اختیاری
```

ممنوع:

```text
dump واقعی دیتابیس
اطلاعات مشتری واقعی
اطلاعات پرداخت واقعی
اطلاعات قرارداد واقعی
backup واقعی
```

---

# Configuration Rules

فایل کانفیگ واقعی نباید در Release باشد.

مجاز:

```text
config/database.sample.php
config/app.sample.php
```

ممنوع:

```text
config/database.php با اطلاعات واقعی
.env با اطلاعات واقعی
```

Installer باید فایل تنظیمات واقعی را هنگام نصب بسازد.

---

# Installer Rules

Installer باید:

```text
نیازمندی‌های سرور را بررسی کند.
اتصال دیتابیس را تست کند.
جدول‌ها را بسازد.
کاربر مدیر اصلی ایجاد کند.
تنظیمات اولیه را ذخیره کند.
فایل نصب را قفل کند.
```

---

# Installer Security

بعد از نصب:

```text
Installer باید غیرفعال شود.
installed.lock ساخته شود.
اجرای دوباره نصب ممنوع شود.
```

اگر کاربر بخواهد نصب را دوباره اجرا کند، باید صریحاً فایل قفل را حذف کند و هشدار ببیند.

---

# Update Package Rules

هر بسته بروزرسانی باید شامل موارد زیر باشد:

```text
update.json
files/
migrations/
scripts/
CHANGELOG.md
```

---

# update.json Required Fields

فایل update.json باید شامل موارد زیر باشد:

```json
{
  "version": "1.1.0",
  "min_supported_version": "1.0.0",
  "type": "minor",
  "requires_backup": true,
  "migrations": [],
  "files": [],
  "checksum": "",
  "notes": ""
}
```

---

# Update Safety Rules

قبل از اجرای Update:

```text
اعتبار فایل ZIP بررسی شود.
update.json بررسی شود.
نسخه فعلی بررسی شود.
سازگاری نسخه بررسی شود.
Backup گرفته شود.
Maintenance Mode فعال شود.
فایل‌های خطرناک بررسی شوند.
Migrationها بررسی شوند.
```

---

# Dangerous Update Actions

موارد زیر در Update ممنوع یا بسیار محدود هستند:

```text
DROP DATABASE
TRUNCATE
DROP TABLE بدون کنترل
DELETE بدون شرط
Overwrite فایل config واقعی
Overwrite فایل storage خصوصی
حذف فایل‌های آپلودی کاربران
حذف Backupها
حذف Logهای مالی
حذف Timeline
```

---

# Migration Release Rules

Migrationها باید:

```text
Idempotent باشند.
چندبار اجرا شدنشان خطرناک نباشد.
قبل از تغییرات حساس Backup داشته باشند.
داده موجود را حذف نکنند.
در صورت خطا Rollback شوند.
در جدول executed_migrations ثبت شوند.
```

---

# Backup Before Release Update

قبل از هر Update باید Backup گرفته شود.

Backup شامل:

```text
Database
Settings
Contract Templates
Uploaded Files
Private File Metadata
```

اگر Backup شکست خورد، Update نباید ادامه پیدا کند.

---

# Maintenance Mode

در هنگام عملیات حساس:

```text
Update
Restore
Migration بزرگ
Repair دیتابیس
```

سیستم باید وارد Maintenance Mode شود.

در Maintenance Mode:

```text
کاربران عادی وارد نشوند.
مدیر وضعیت عملیات را ببیند.
پس از پایان عملیات سیستم برگردد.
```

---

# Release ZIP Structure

ساختار پیشنهادی ZIP نهایی:

```text
proma-pay-v1.0.0.zip
│
├── app/
├── assets/
├── config/
│   ├── app.sample.php
│   └── database.sample.php
├── install/
├── public/
├── routes/
├── storage/
│   ├── private/.gitkeep
│   ├── public/.gitkeep
│   ├── logs/.gitkeep
│   └── temp/.gitkeep
├── migrations/
├── README.md
├── INSTALL.md
├── UPDATE_GUIDE.md
├── CHANGELOG.md
├── LICENSE.md
└── REQUIREMENTS.md
```

---

# Files To Exclude From ZIP

موارد زیر نباید وارد ZIP شوند:

```text
.git/
.github/
.vscode/
.idea/
node_modules/
tests/
docs/internal/
storage/private/*
storage/logs/*
storage/temp/*
storage/cache/*
backups/
*.log
*.tmp
*.bak
*.sql واقعی
.env
installed.lock
composer.lock در صورت ناسازگاری با نصب هدف
package-lock.json در صورت عدم نیاز
```

---

# Storage In Release

پوشه‌های Storage باید وجود داشته باشند اما خالی باشند.

برای حفظ پوشه‌ها از `.gitkeep` استفاده شود.

مجاز:

```text
storage/private/.gitkeep
storage/logs/.gitkeep
storage/temp/.gitkeep
```

ممنوع:

```text
storage/private/identity-documents/*
storage/private/payment-receipts/*
storage/private/backups/*
storage/logs/*.log
```

---

# Branding Release Rules

اطلاعات برند پیش‌فرض باید قابل تغییر باشد.

موارد قابل تنظیم:

```text
نام سامانه
لوگو
متن فوتر
اطلاعات توسعه‌دهنده
رنگ اصلی
```

اطلاعات توسعه‌دهنده پروژه:

```text
Developer: مهدی ربانی
Email: pgm.mehdirabani@gmail.com
GitHub: https://github.com/mehdirabani/
```

این اطلاعات می‌تواند در Footer، About یا Documentation استفاده شود.

---

# Debug Rules

در نسخه Production:

```text
Debug Mode = Off
Display Errors = Off
Log Errors = On
Detailed Errors = Admin Only
```

کاربر نهایی نباید خطای فنی ببیند.

---

# Environment Boundary

پروژه باید تفاوت محیط‌ها را بشناسد:

```text
local
staging
production
```

در Production:

```text
Debug خاموش
Demo Data خاموش
Error Details مخفی
Installer قفل
Update امن
```

---

# Demo Data Rules

Demo Data فقط اختیاری است.

در نسخه تجاری:

```text
کاربر بتواند هنگام نصب انتخاب کند Demo Data نصب شود یا نه.
Demo Data نباید شامل اطلاعات واقعی باشد.
تمام نام‌ها، شماره‌ها و قراردادها باید ساختگی باشند.
```

---

# Asset Build Rules

فایل‌های CSS و JS نسخه نهایی باید:

```text
Minified
Versioned
Cacheable
بدون Source Map حساس
```

Source Map در Production نباید شامل مسیرها یا کد حساس باشد.

---

# Frontend Release Rules

UI نسخه نهایی باید بررسی شود:

```text
RTL کامل
فونت Yekan Bakh
Responsive
بدون متن Placeholder انگلیسی
بدون TODO
بدون متن تست
بدون Console Log
بدون Alert/Confirm مرورگر
```

---

# Security Release Checklist

قبل از انتشار:

```text
Debug خاموش است.
Installer قفل شده است.
فایل‌های Private عمومی نیستند.
Config واقعی در ZIP نیست.
Error خام نمایش داده نمی‌شود.
CSRF فعال است.
Permissionها سمت سرور بررسی می‌شوند.
Uploadها امن هستند.
Backupها قابل دانلود مستقیم نیستند.
Update خطرناک نیست.
```

---

# Performance Release Checklist

قبل از انتشار:

```text
SELECT * حذف شده است.
Pagination در لیست‌ها وجود دارد.
N+1 بررسی شده است.
Assetها Minify شده‌اند.
تصاویر بهینه هستند.
Queryهای پرتکرار Index دارند.
Cache تنظیمات فعال است.
Logهای Debug غیرفعال هستند.
```

---

# Testing Before Release

قبل از انتشار Production باید تست‌های زیر انجام شوند:

```text
Fresh Install Test
Update From Previous Version Test
Login Test
RBAC Test
Customer Portal Test
Contract Creation Test
Installment Payment Test
Card To Card Receipt Test
Identity Document Test
Legal Case Test
Calendar Test
Chat Test
Notification Test
Backup Test
Restore Test
Update Test
Responsive Test
Security Test
Performance Smoke Test
```

---

# Release Report

هر انتشار باید گزارش داشته باشد.

قالب پیشنهادی:

```text
Release Version:
Release Date:
Package Name:
PHP Compatibility:
Database Compatibility:
New Features:
Bug Fixes:
Security Fixes:
Database Changes:
Files Changed:
Excluded Files:
Known Issues:
Test Summary:
Backup Required:
Update Supported From:
```

---

# Changelog Rules

CHANGELOG باید برای هر نسخه به‌روزرسانی شود.

ساختار پیشنهادی:

```text
Added
Changed
Fixed
Security
Performance
Deprecated
Removed
Migration
```

---

# License Rules

نسخه تجاری باید LICENSE مشخص داشته باشد.

لایسنس باید تعیین کند:

```text
حق نصب
حق استفاده
حق ویرایش
حق فروش مجدد
محدودیت پشتیبانی
مسئولیت استفاده
```

---

# Commercial Release Rules

برای انتشار تجاری، نسخه باید:

```text
قابل نصب باشد.
ظاهر حرفه‌ای داشته باشد.
مستندات داشته باشد.
بدون داده واقعی باشد.
بدون فایل اضافی باشد.
دارای نسخه مشخص باشد.
دارای راهنمای بروزرسانی باشد.
دارای پشتیبانی از هاست اشتراکی باشد.
```

---

# Local XAMPP Deployment Rules

برای تست محلی، پروژه می‌تواند در مسیر زیر قرار گیرد:

```text
C:/xampp/htdocs/pay
```

یا:

```text
/xampp/htdocs/pay
```

قبل از جایگزینی نسخه قبلی:

```text
از پوشه قبلی Backup گرفته شود.
فقط فایل‌های Core کپی شوند.
config/database.php محلی حفظ شود مگر نیاز به تغییر باشد.
storage/private حفظ شود.
```

آدرس تست:

```text
http://localhost/pay
```

---

# Production Deployment Rules

در Deploy روی هاست:

```text
فایل ZIP آپلود شود.
Extract انجام شود.
Permission پوشه‌های storage تنظیم شود.
Installer اجرا شود.
اطلاعات دیتابیس وارد شود.
مدیر اصلی ساخته شود.
Installer قفل شود.
سیستم تست اولیه شود.
```

---

# File Permission Rules

پوشه‌های زیر باید قابل نوشتن باشند:

```text
storage/
storage/private/
storage/public/
storage/logs/
storage/temp/
```

فایل‌های Config باید بعد از نصب تا حد امکان محافظت شوند.

---

# Rollback Rules

هر Update باید برنامه Rollback داشته باشد.

Rollback می‌تواند شامل موارد زیر باشد:

```text
بازگردانی فایل‌ها
بازگردانی دیتابیس از Backup
غیرفعال کردن Migration ناموفق
بازگردانی نسخه قبلی
خروج از Maintenance Mode
```

اگر Rollback کامل ممکن نیست، باید در گزارش Update صریحاً اعلام شود.

---

# Hotfix Rules

Hotfix فقط برای موارد مهم استفاده شود:

```text
Security Bug
Payment Bug
Data Loss Bug
Fatal Error
Broken Login
Broken Install
Broken Update
```

Hotfix نباید شامل Feature جدید باشد.

---

# Forbidden Release Patterns

موارد زیر ممنوع هستند:

```text
انتشار ZIP با .git
انتشار ZIP با اطلاعات دیتابیس واقعی
انتشار ZIP با Backup واقعی
انتشار ZIP با Debug فعال
انتشار بدون تست نصب
انتشار بدون CHANGELOG
انتشار بدون Version
انتشار با TODO
انتشار با Placeholderهای ناتمام
انتشار با خطای PHP قابل مشاهده
انتشار با فایل‌های تستی
```

---

# Correct Release Process

فرآیند صحیح انتشار:

```text
1. بررسی تغییرات
2. اجرای تست‌ها
3. به‌روزرسانی Version
4. به‌روزرسانی CHANGELOG
5. بررسی Migrationها
6. بررسی امنیت
7. حذف فایل‌های اضافی
8. ساخت ZIP نهایی
9. تست نصب ZIP روی محیط تمیز
10. تست Update از نسخه قبلی
11. ثبت Release Report
12. انتشار نسخه
```

---

# Implementation Rules

Codex هنگام آماده‌سازی Release باید بررسی کند:

```text
آیا فایل‌های توسعه حذف شده‌اند؟
آیا اطلاعات واقعی حذف شده‌اند؟
آیا config واقعی داخل ZIP نیست؟
آیا Installer کار می‌کند؟
آیا Update کار می‌کند؟
آیا Backup قبل از Update انجام می‌شود؟
آیا Migration امن است؟
آیا Debug خاموش است؟
آیا Storage خالی اما ساختارمند است؟
آیا مستندات همراه نسخه هستند؟
آیا نسخه و CHANGELOG به‌روز شده‌اند؟
```

---

# Review Checklist

قبل از انتشار نهایی:

```text
□ شماره نسخه مشخص است.
□ CHANGELOG به‌روز است.
□ README و INSTALL وجود دارند.
□ فایل‌های توسعه حذف شده‌اند.
□ .git داخل ZIP نیست.
□ .env داخل ZIP نیست.
□ config واقعی داخل ZIP نیست.
□ داده واقعی داخل ZIP نیست.
□ storage/private خالی است.
□ storage/logs خالی است.
□ Installer تست شده است.
□ Update تست شده است.
□ Backup تست شده است.
□ Debug خاموش است.
□ Error خام نمایش داده نمی‌شود.
□ Permission فایل‌ها درست است.
□ UI نهایی و RTL تست شده است.
□ نسخه روی XAMPP تست شده است.
□ نسخه روی ساختار مشابه هاست تست شده است.
```

---

# Definition of Done

یک Release زمانی کامل است که:

```text
✔ قابل نصب باشد.
✔ قابل بروزرسانی باشد.
✔ امن باشد.
✔ بدون داده واقعی باشد.
✔ بدون فایل توسعه باشد.
✔ مستندات کامل داشته باشد.
✔ Version مشخص داشته باشد.
✔ CHANGELOG داشته باشد.
✔ روی محیط تمیز تست شده باشد.
✔ روی نسخه قبلی تست Update شده باشد.
✔ Debug خاموش باشد.
✔ فایل‌های Private عمومی نباشند.
✔ برای انتشار تجاری آماده باشد.
```

---

# Future Considerations

سیستم باید در آینده آماده موارد زیر باشد:

```text
License Manager
Auto Updater
Remote Update Server
Marketplace Packaging
Rastchin Release Format
One-Click Installer
One-Click Backup Before Update
Online Activation
Offline Activation
Release Signing
Checksum Verification
Automatic Compatibility Check
Cloud Backup
Docker Deployment
CI/CD Pipeline
```

---

# Final Boundary

پوشه specifications در این نقطه تکمیل می‌شود.

از این مرحله به بعد، مستندات جدید نباید بدون دلیل وارد specifications شوند.

مستندات بعدی باید بر اساس نوع محتوا در پوشه‌های زیر نوشته شوند:

```text
docs/domains/
docs/modules/
docs/ui/
docs/database/
docs/deployment/
docs/testing/
docs/codex/
```

---

# End of File