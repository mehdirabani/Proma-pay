# 02 — Non-Negotiable Rules

قوانین غیرقابل مذاکره پروژه **Proma Pay / پروما** برای Codex

---

## هدف این فایل

این فایل شامل قوانینی است که Codex در تمام بخش‌های پروژه باید بدون استثنا رعایت کند.

این قوانین برای جلوگیری از خطاهای جدی در بخش‌های زیر نوشته شده‌اند:

- امنیت
- پرداخت
- اقساط
- قرارداد
- محاسبات مالی
- پرونده حقوقی
- فایل‌های حساس
- تنظیمات محرمانه
- Migration
- گزارش‌گیری
- پلاگین‌ها
- Shared Hosting

---

## قانون اصلی

قانون اصلی پروژه:

> اگر یک تصمیم می‌تواند امنیت، پول، قرارداد، فایل حساس یا پرونده حقوقی را تحت تأثیر قرار دهد، Codex حق حدس زدن ندارد.

در این موارد Codex باید محافظه‌کارانه‌ترین حالت را انتخاب کند.

---

## قوانین معماری

Codex باید این معماری را رعایت کند:

```text
Controller → Service → Repository → Database
```

قوانین:

- Controller فقط Request را دریافت و Response را آماده می‌کند.
- منطق اصلی باید در Service باشد.
- Queryها باید در Repository باشند.
- View نباید مستقیم به Database وصل شود.
- JavaScript نباید منبع حقیقت مالی باشد.
- Plugin نباید مستقیم Core را تغییر دهد.
- عملیات حساس باید از Service رسمی همان Domain عبور کند.

ممنوع:

```text
Controller → SQL مستقیم
View → Database
JavaScript → محاسبه قطعی بدهی
Plugin → تغییر مستقیم جدول‌های Core
```

---

## قوانین امنیت عمومی

این قوانین همیشه الزامی هستند:

```text
Authentication برای صفحات داخلی الزامی است.
Permission Check برای عملیات حساس الزامی است.
Scope Check برای داده‌های مشتری، قرارداد، پرداخت و پرونده الزامی است.
CSRF برای تمام فرم‌ها و عملیات state-changing الزامی است.
Validation سمت سرور الزامی است.
Sanitization خروجی در View الزامی است.
Prepared Statement برای همه Queryها الزامی است.
```

Codex نباید فقط به کنترل UI اعتماد کند.

---

## قوانین دیتابیس

استانداردهای دیتابیس:

```text
Database: MySQL / MariaDB
Charset: utf8mb4
Collation: utf8mb4_unicode_ci
Primary Key: id BIGINT UNSIGNED AUTO_INCREMENT
Money: DECIMAL(15,2)
Datetime: *_at
Date: *_date
Foreign Key Column: *_id
Boolean: is_ / has_ / can_ / should_ / requires_
Table Name: plural snake_case
Column Name: snake_case
```

ممنوع:

```text
FLOAT برای مبلغ
DOUBLE برای مبلغ
ذخیره فایل واقعی داخل دیتابیس
ذخیره Secret خام داخل دیتابیس
Query با ورودی خام کاربر
Migration مخرب بدون Backup
```

---

## قوانین Query

همه Queryها باید با PDO Prepared Statements اجرا شوند.

مجاز:

```php
$stmt = $pdo->prepare('SELECT * FROM customers WHERE id = :id');
$stmt->execute(['id' => $customerId]);
```

ممنوع:

```php
$sql = "SELECT * FROM customers WHERE id = " . $_GET['id'];
```

قوانین:

- ورودی کاربر مستقیم داخل SQL قرار نگیرد.
- ستون Sort باید whitelist شود.
- Filterها باید whitelist شوند.
- Queryهای لیستی باید LIMIT داشته باشند.
- Query روی جدول‌های حجیم باید Pagination داشته باشد.
- `SELECT *` در لیست‌های بزرگ ممنوع است.
- Queryهای گزارش باید Scope داشته باشند.

---

## قوانین مالی

این بخش غیرقابل مذاکره است.

```text
Frontend منبع حقیقت مالی نیست.
Backend منبع حقیقت مالی است.
همه محاسبات مالی باید سمت سرور انجام شود.
همه مبلغ‌ها باید DECIMAL باشند.
هر تغییر مالی مهم باید Transaction داشته باشد.
هر تغییر مالی قطعی باید Financial Log داشته باشد.
هر اصلاح دستی مالی باید دلیل، Permission و Audit Log داشته باشد.
```

---

## قوانین پرداخت

وضعیت پرداخت‌ها باید دقیق رعایت شود.

پرداخت‌های زیر نباید روی بدهی اثر بگذارند:

```text
initiated
waiting
pending_review
failed
rejected
cancelled
expired
```

فقط این وضعیت می‌تواند روی بدهی اثر بگذارد:

```text
approved
```

قوانین:

- رسید کارت‌به‌کارت فقط بعد از بررسی دستی یا Service معتبر approved می‌شود.
- Callback درگاه پرداخت به‌تنهایی کافی نیست.
- پرداخت Gateway باید Verify شود.
- مبلغ پرداخت باید با مبلغ مورد انتظار تطبیق داده شود.
- Payment approved باید Financial Log داشته باشد.
- Payment rejected باید دلیل داشته باشد.
- Refund باید Transaction و Financial Log داشته باشد.

---

## قوانین قرارداد

ساخت قرارداد باید Atomic باشد.

یعنی این عملیات باید در یک Transaction انجام شود:

```text
ایجاد قرارداد
ایجاد اقساط
ثبت پیش‌پرداخت در صورت وجود
ثبت لاگ مالی
ثبت Audit
ثبت Timeline/Event
```

قوانین:

- قرارداد بدون مشتری معتبر ساخته نشود.
- قرارداد بدون اقساط معتبر ساخته نشود.
- مبلغ قرارداد و جمع اقساط باید سمت سرور Validate شود.
- تغییر مبلغ قرارداد بعد از ثبت رسمی باید محدود، قابل ردیابی و Audit شده باشد.
- قرارداد حذف فیزیکی نشود.
- قرارداد حساس است و Scope Check الزامی دارد.

---

## قوانین اقساط

قوانین:

```text
قسط باید به قرارداد معتبر وصل باشد.
قسط باید due_date داشته باشد.
قسط باید amount دقیق DECIMAL داشته باشد.
paid_amount فقط با payment approved تغییر می‌کند.
remaining_amount باید سمت سرور محاسبه یا کنترل شود.
قسط معوق باید بر اساس due_date و status تشخیص داده شود.
```

ممنوع:

```text
تغییر وضعیت قسط از Frontend بدون Service
تغییر paid_amount بدون Payment approved
حذف فیزیکی قسط رسمی
محاسبه نهایی بدهی در JavaScript
```

---

## قوانین حقوقی

پرونده حقوقی بسیار حساس است.

قوانین:

```text
پرونده حقوقی باید بر اساس Snapshot مالی معتبر ساخته شود.
پرونده حقوقی نباید بر اساس محاسبه زنده و بی‌ردپا ساخته شود.
مدارک حقوقی باید Private باشند.
تغییر وضعیت پرونده باید History داشته باشد.
ارجاع حقوقی باید Audit Log داشته باشد.
مبالغ مطالبه‌شده باید قابل ردیابی باشند.
```

ممنوع:

```text
ساخت پرونده حقوقی بدون Snapshot
Public کردن مدارک حقوقی
حذف History پرونده حقوقی
تغییر مبلغ ادعا بدون Audit
```

---

## قوانین فایل‌ها

فایل‌های حساس باید همیشه امن باشند.

فایل‌های حساس:

```text
مدارک هویتی مشتری
قرارداد
چک
سفته
رسید پرداخت
مدارک حقوقی
گزارش مالی
بکاپ
بسته پلاگین
مهر و امضا
```

قوانین:

```text
فایل واقعی داخل دیتابیس ذخیره نمی‌شود.
فایل حساس باید در Private Storage باشد.
مسیر واقعی فایل نباید به کاربر نمایش داده شود.
دانلود فایل حساس باید از Controller امن انجام شود.
دانلود فایل حساس باید Permission Check داشته باشد.
دانلود فایل حساس باید Scope Check داشته باشد.
دانلود فایل حساس باید Audit یا File Access Log داشته باشد.
توکن دانلود باید موقت باشد.
توکن خام نباید ذخیره شود.
```

ممنوع:

```text
ذخیره فایل حساس در public/
دادن لینک مستقیم فایل Private
ذخیره storage_path واقعی در Response عمومی
دانلود فایل بدون Permission
```

---

## قوانین تنظیمات و Secretها

Secretها شامل موارد زیر هستند:

```text
Password
API Key
Token
Private Key
Webhook Secret
Gateway Secret
SMS Provider Key
Telegram Bot Token
Backup Storage Secret
```

قوانین:

```text
Secret خام نباید در دیتابیس ذخیره شود.
Secret خام نباید در Log ذخیره شود.
Secret خام نباید در Response نمایش داده شود.
Secret باید encrypted یا خارج از دیتابیس مدیریت شود.
UI فقط مقدار Mask شده را نمایش دهد.
تغییر Secret باید Audit و Security Log داشته باشد.
```

ممنوع:

```text
ذخیره token در settings.value
نمایش API Key در UI
ثبت Secret در system_logs
قرار دادن Secret در Migration
```

---

## قوانین Audit Log

Audit Log برای عملیات حساس الزامی است.

نمونه عملیات حساس:

```text
ایجاد یا تغییر قرارداد
تأیید یا رد پرداخت
اصلاح مالی
تسویه
ارجاع حقوقی
تغییر وضعیت پرونده حقوقی
آپلود یا دانلود فایل حساس
خروجی گرفتن از گزارش حساس
تغییر تنظیمات حساس
تغییر Secret
Backup
Restore
Update
نصب یا حذف پلاگین
تغییر Permission یا Role
```

قوانین:

- Audit Log نباید حذف یا ویرایش شود.
- Audit Log نباید Secret خام داشته باشد.
- Audit Log باید actor، action، related entity و زمان را مشخص کند.

---

## قوانین Security Log

Security Log برای تلاش‌های غیرمجاز یا خطرناک الزامی است.

موارد الزامی:

```text
ورود ناموفق تکراری
CSRF نامعتبر
Permission Denied در عملیات حساس
Scope Violation
تلاش دانلود فایل Private بدون Permission
تلاش مشاهده پرونده حقوقی بدون Permission
تلاش Export بدون Permission
تلاش تغییر مبلغ از Frontend
تلاش دستکاری IDها
تلاش نصب پلاگین خطرناک
تلاش Restore یا Update بدون Permission
تلاش مشاهده Secret
API Token نامعتبر
```

قوانین:

- Security Log نباید حذف یا ویرایش شود.
- داده حساس خام در Security Log ذخیره نشود.
- رویدادهای critical باید Notification مدیریتی ایجاد کنند.

---

## قوانین Migration

Migration باید امن، قابل تکرار و قابل ردیابی باشد.

قوانین:

```text
Migration باید در جدول migrations ثبت شود.
Migration باید تا حد امکان idempotent باشد.
قبل از ساخت جدول، وجود جدول بررسی شود.
قبل از افزودن ستون، وجود ستون بررسی شود.
قبل از افزودن Index، وجود Index بررسی شود.
Migration مخرب بدون Backup ممنوع است.
حذف ستون یا جدول پیش‌فرض ممنوع است.
Seed باید idempotent باشد.
Secret خام نباید Seed شود.
```

Migrationهای حساس نیاز به Backup دارند:

```text
مالی
پرداخت
قرارداد
اقساط
حقوقی
فایل‌ها
Backup / Restore / Update
Plugins
Permissions
Settings حساس
```

---

## قوانین گزارش و Export

گزارش حساس باید مثل فایل حساس مدیریت شود.

قوانین:

```text
گزارش باید Permission Check داشته باشد.
گزارش باید Scope Check داشته باشد.
Export حساس باید Private Storage داشته باشد.
Export حساس باید Expiration داشته باشد.
Export حساس باید Audit Log داشته باشد.
دانلود Export حساس باید File Access Log داشته باشد.
Export نباید داده خارج از Scope داشته باشد.
```

ممنوع:

```text
Export کل داده‌ها بدون Scope
ساخت گزارش مالی بدون Permission
ساخت فایل خروجی حساس در public/
ارسال گزارش حساس به ایمیل خارجی بدون Policy
```

---

## قوانین پلاگین

پلاگین اختیاری است، امنیت اختیاری نیست.

قوانین:

```text
پلاگین باید Security Scan شود.
پلاگین باید Permission namespace داشته باشد.
Route پلاگین باید Auth، CSRF و Permission داشته باشد.
Hook پلاگین نباید Core Workflow را خراب کند.
Migration پلاگین باید ثبت شود.
Secret پلاگین باید در Secure Settings باشد.
نصب پلاگین حساس باید Backup داشته باشد.
```

ممنوع:

```text
پلاگین بدون Scan
پلاگین با مسیر ../
پلاگین دارای .env
پلاگین با فایل اجرایی خطرناک
پلاگین با دسترسی مستقیم به جدول‌های Core
پلاگین با Secret خام در Log
```

---

## قوانین Backup و Update

Restore و Update جزو عملیات Critical هستند.

قوانین:

```text
Update حساس بدون Backup ممنوع است.
Restore بدون Backup قبل از Restore ممنوع است.
Restore باید Approval و reason داشته باشد.
Update باید مرحله‌به‌مرحله Log شود.
Restore باید Maintenance Mode داشته باشد، مگر test_restore باشد.
فایل Backup باید Private باشد.
دانلود Backup باید Permission ویژه داشته باشد.
```

ممنوع:

```text
Restore بدون Permission
Update بدون Backup
دانلود Backup با لینک عمومی
حذف Backup رسمی بدون Audit
```

---

## قوانین UI

UI باید فارسی و راست‌به‌چپ باشد.

قوانین:

```text
RTL کامل
متن فارسی
فونت YekanBakh از assets/fonts
پیام خطای قابل فهم برای کاربر
عدم نمایش خطای فنی خام
عدم نمایش Secret
عدم نمایش مسیر واقعی فایل
عدم انجام محاسبات مالی قطعی در UI
```

ممنوع:

```text
پیام خطای SQL در UI
نمایش Stack Trace به کاربر
نمایش API Key
نمایش storage_path
فرم حساس بدون CSRF
```

---

## قوانین Performance

قوانین:

```text
لیست‌های بزرگ باید LIMIT داشته باشند.
جدول‌های حجیم باید Pagination داشته باشند.
گزارش‌های سنگین باید Queue شوند.
Export بزرگ باید Job شود.
Dashboard نباید Query سنگین بدون Cache اجرا کند.
Queryهای حساس باید Index مناسب داشته باشند.
SELECT * در لیست‌های بزرگ ممنوع است.
COUNT بدون فیلتر روی جدول بزرگ ممنوع است.
```

مخصوص Shared Hosting:

```text
Jobهای سنگین باید Chunk شوند.
Migration سنگین باید مرحله‌ای باشد.
Backup نباید Memory را پر کند.
Export نباید کل داده را یک‌جا در Memory نگه دارد.
```

---

## قوانین خطاها

قوانین:

```text
خطاهای کاربر باید پیام ساده داشته باشند.
خطاهای فنی باید Log شوند.
خطاهای critical باید Notification مدیریتی ایجاد کنند.
Exception نباید اطلاعات حساس را در UI نمایش دهد.
```

ممنوع:

```text
نمایش Stack Trace به کاربر
ذخیره Password یا Token در error_logs
نادیده گرفتن خطای پرداخت
نادیده گرفتن خطای Transaction
```

---

## قوانین تست حداقلی

هر Feature باید حداقل این موارد را بررسی کند:

```text
ورودی نامعتبر
دسترسی بدون Login
دسترسی بدون Permission
دسترسی خارج از Scope
CSRF نامعتبر
عملیات موفق
عملیات شکست‌خورده
ثبت Audit در عملیات حساس
ثبت Security Log در تلاش غیرمجاز
```

برای Feature مالی:

```text
Payment pending اثر مالی ندارد.
Payment approved اثر مالی دارد.
Financial Log ثبت می‌شود.
Transaction درست Rollback می‌شود.
```

---

## لیست ممنوعیت‌های قطعی

Codex هرگز نباید این کارها را انجام دهد:

```text
استفاده از SQL خام با ورودی کاربر
ذخیره مبلغ با FLOAT یا DOUBLE
تایید پرداخت بدون Verify
اثر دادن پرداخت pending روی بدهی
ذخیره فایل حساس در public
نمایش مسیر واقعی فایل
ذخیره Secret خام در settings
ذخیره Secret خام در logs
ساخت Route حساس بدون Auth
ساخت فرم حساس بدون CSRF
ساخت Feature مالی بدون Transaction
تغییر مالی بدون Financial Log
ساخت پرونده حقوقی بدون Snapshot
Migration مخرب بدون Backup
حذف audit_logs
حذف financial_logs
حذف security_logs
نصب پلاگین بدون Security Scan
خواندن همه docs برای یک Task کوچک
```

---

## قانون تصمیم در شرایط ابهام

اگر Codex مطمئن نبود:

```text
در بخش مالی: عملیات را متوقف کند یا محافظه‌کارانه‌ترین حالت را انتخاب کند.
در بخش حقوقی: بدون Snapshot یا Audit ادامه ندهد.
در بخش فایل: Private بودن را پیش‌فرض بگیرد.
در بخش پرداخت: pending را هرگز approved فرض نکند.
در بخش Permission: deny را پیش‌فرض بگیرد.
در بخش Migration: بدون Backup عملیات مخرب انجام ندهد.
در بخش Plugin: blocked را پیش‌فرض بگیرد تا Scan کامل شود.
```

---

## Acceptance نهایی برای هر کد

هر کدی که Codex تولید می‌کند باید این شرایط را داشته باشد:

```text
با PHP 7.4+ سازگار باشد.
با MySQL/MariaDB سازگار باشد.
با Shared Hosting ناسازگار نباشد.
Prepared Statement داشته باشد.
Validation سمت سرور داشته باشد.
Permission و Scope را رعایت کند.
CSRF را برای عملیات تغییر وضعیت رعایت کند.
خطاها را کنترل کند.
Log مناسب داشته باشد.
UI فارسی و RTL را خراب نکند.
منطق مالی را سمت Frontend نبرد.
ساختار پروژه را بی‌دلیل تغییر ندهد.
```

---

## پایان فایل

این فایل مرجع قوانین قطعی Codex است.

قانون نهایی:

> اگر بین سرعت پیاده‌سازی و امنیت/دقت مالی/دقت حقوقی تعارض وجود داشت، همیشه امنیت و دقت مقدم است.
````
