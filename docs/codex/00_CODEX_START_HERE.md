نام فایل:

`00_CODEX_START_HERE.md`

پوشه هدف:

`docs/codex/`

نگارش شده محتویات فایل با زبان README.md:

````markdown
# 00 — Codex Start Here

راهنمای شروع سریع Codex برای همگام‌سازی اولیه با پروژه **Proma Pay / پروما**

---

## هدف این فایل

این فایل اولین فایلی است که Codex باید قبل از هرگونه کدنویسی، تحلیل، اصلاح یا ساخت Feature در پروژه بخواند.

هدف این فایل این است که Codex:

- پروژه را سریع و درست بفهمد.
- وارد خواندن بی‌هدف همه مستندات نشود.
- طبق ترتیب مشخص جلو برود.
- قوانین غیرقابل مذاکره پروژه را رعایت کند.
- بدون نیاز، ساختار پروژه را تغییر ندهد.
- از حدس زدن درباره منطق مالی، حقوقی و امنیتی خودداری کند.
- فقط مستندات مرتبط با Task فعلی را بخواند.

---

## معرفی کوتاه پروژه

**Proma Pay** یک سیستم مدیریت فروش اقساطی، قرارداد، مشتری، پرداخت، پیگیری اقساط، معوقات، پرونده حقوقی، فایل‌ها، گزارش‌ها، اعلان‌ها، بکاپ، پلاگین و عملیات مالی است.

سیستم باید برای محیط فارسی، راست‌به‌چپ و استفاده تجاری واقعی آماده باشد.

---

## Tech Stack قطعی پروژه

Codex باید پروژه را بر اساس این Stack بسازد:

```text
PHP 7.4+
MySQL / MariaDB
Lightweight MVC Architecture
PDO Prepared Statements
Session Authentication
CSRF Protection
RBAC Permission System
Scope / Ownership Control
Vanilla JavaScript
Chart.js
PWA Support
Electron Support
Shared Hosting Compatible
RTL Persian UI
```

---

## معماری کلی پروژه

پروژه باید معماری سبک، قابل نگهداری و بدون Framework سنگین داشته باشد.

ساختار کلی باید بر پایه این لایه‌ها باشد:

```text
Entry Point
Router
Middleware
Controller
Service
Repository
Model / Entity
View
Database
Event
Job
Logger
```

قانون مهم:

> Controller نباید منطق مالی، امنیتی، حقوقی یا محاسباتی سنگین داشته باشد.

---

## فولدرهای مستندات موجود

در حال حاضر مستندات اصلی پروژه در این فولدرها قرار دارند:

```text
docs/
├── specifications/
├── workflows/
├── domains/
├── database/
└── codex/
```

معنی هر فولدر:

| فولدر | کاربرد |
|---|---|
| `specifications/` | نیازمندی‌ها و مشخصات کلی پروژه |
| `workflows/` | فرایندهای عملیاتی سیستم |
| `domains/` | تعریف دامنه‌ها و منطق مفهومی |
| `database/` | طراحی جدول‌ها، Indexها و Migrationها |
| `codex/` | راهنمای اجرایی کوتاه مخصوص Codex |

---

## قانون اصلی خواندن مستندات

Codex نباید در شروع کار همه فایل‌های مستندات را بخواند.

روش درست:

```text
1. اول فقط docs/codex/ را بخوان.
2. بعد بر اساس Task فعلی، فقط مستندات مرتبط را بخوان.
3. اگر Task مربوط به دیتابیس است، فقط فایل‌های لازم از docs/database/ را بخوان.
4. اگر Task مربوط به Workflow است، فقط Workflow همان Feature را بخوان.
5. اگر Task مربوط به Domain است، فقط Domain همان بخش را بخوان.
6. هیچ‌وقت بدون نیاز کل docs را مبنای تصمیم‌گیری همزمان قرار نده.
```

---

## ترتیب پیشنهادی برای شروع Codex

در اولین همگام‌سازی، Codex فقط این فایل‌ها را بخواند:

```text
docs/codex/00_CODEX_START_HERE.md
docs/codex/01_PROJECT_MAP.md
docs/codex/02_NON_NEGOTIABLE_RULES.md
docs/codex/03_IMPLEMENTATION_ORDER.md
```

اگر فایل‌های بالا هنوز ساخته نشده‌اند، Codex باید فقط همین فایل فعلی را مبنا قرار دهد و منتظر ساخت فایل‌های بعدی بماند.

---

## قوانین غیرقابل مذاکره

این قوانین در کل پروژه قطعی هستند:

```text
1. همه Queryهای دیتابیس باید با PDO Prepared Statements اجرا شوند.
2. هیچ SQL خام با ورودی کاربر مجاز نیست.
3. همه فرم‌های حساس باید CSRF Protection داشته باشند.
4. همه عملیات حساس باید Permission Check داشته باشند.
5. همه داده‌های وابسته به مشتری، قرارداد، پرداخت و پرونده باید Scope Check داشته باشند.
6. محاسبات مالی فقط سمت سرور انجام می‌شود.
7. مبلغ‌ها فقط با DECIMAL ذخیره می‌شوند، نه FLOAT یا DOUBLE.
8. پرداخت فقط بعد از Approved شدن روی بدهی اثر می‌گذارد.
9. تغییرات مالی حساس باید Financial Log داشته باشند.
10. عملیات حساس باید Audit Log داشته باشند.
11. تلاش‌های غیرمجاز باید Security Log داشته باشند.
12. فایل‌های حساس باید Private Storage داشته باشند.
13. مسیر واقعی فایل Private نباید به کاربر نمایش داده شود.
14. Secret، Token، API Key و Password خام نباید در دیتابیس یا Log ذخیره شوند.
15. پروژه باید با Shared Hosting سازگار بماند.
```

---

## کارهایی که Codex نباید انجام دهد

Codex نباید:

```text
از Framework سنگین استفاده کند.
Laravel یا Symfony نصب کند، مگر صراحتاً درخواست شود.
Composer dependency سنگین اضافه کند، مگر ضروری باشد.
ساختار پروژه را بدون دلیل تغییر دهد.
فایل‌های حساس را Public کند.
منطق مالی را در JavaScript قرار دهد.
Permission را فقط در UI کنترل کند.
CSRF را نادیده بگیرد.
پرداخت pending را approved فرض کند.
رسید کارت‌به‌کارت را بدون بررسی تایید کند.
قرارداد یا قسط را بدون Transaction بسازد.
Migration مخرب بدون Backup بسازد.
داده مالی را بدون Financial Log تغییر دهد.
Secret را داخل فایل config عمومی ذخیره کند.
SELECT * در لیست‌های بزرگ استفاده کند.
Query بدون LIMIT روی جدول‌های حجیم اجرا کند.
همه مستندات را یک‌جا بخواند و تصمیم کلی بگیرد.
```

---

## روش درست اجرای هر Task

برای هر Task، Codex باید این روند را رعایت کند:

```text
1. Task را دقیق تشخیص بده.
2. Domain مربوطه را مشخص کن.
3. فقط مستندات مرتبط با همان Task را بخوان.
4. قبل از کدنویسی، فایل‌های موجود پروژه را بررسی کن.
5. ساختار فعلی پروژه را حفظ کن.
6. اگر Feature به دیتابیس نیاز دارد، Migration استاندارد بساز.
7. اگر Feature حساس است، Permission، CSRF، Audit و Security Log را اضافه کن.
8. اگر Feature مالی است، Transaction و Financial Log را اضافه کن.
9. اگر Feature فایل دارد، از Files Domain و Private Storage استفاده کن.
10. بعد از پیاده‌سازی، تست‌های حداقلی و چک‌لیست Acceptance را بررسی کن.
```

---

## نقشه سریع Domainهای حساس

این بخش‌ها حساس محسوب می‌شوند و نباید ساده‌سازی شوند:

| بخش | حساسیت |
|---|---|
| Customers | اطلاعات هویتی مشتری |
| Contracts | قرارداد و تعهد مالی |
| Installments | بدهی، سررسید و معوقات |
| Payments | پرداخت، رسید و تأیید مالی |
| Financial | لاگ مالی و تسویه |
| Legal | پرونده حقوقی و مدارک |
| Files | مدارک، رسیدها، قراردادها |
| Reports | خروجی داده‌های حساس |
| Settings | تنظیمات سیستم و Secretها |
| Backup & Update | تغییرات سیستمی پرریسک |
| Plugins | توسعه‌پذیری با ریسک امنیتی |
| Logs / Audit / Security | ردیابی عملیات حساس |

---

## قوانین مالی بسیار مهم

در Proma Pay، هیچ عملیات مالی نباید بدون کنترل انجام شود.

قوانین:

```text
Payment pending هیچ اثری روی بدهی ندارد.
Payment rejected هیچ اثری روی بدهی ندارد.
Payment failed هیچ اثری روی بدهی ندارد.
فقط Payment approved می‌تواند روی paid_amount و remaining_amount اثر بگذارد.
هر تغییر مالی باید در Transaction انجام شود.
هر تغییر مالی مهم باید Financial Log داشته باشد.
هر اصلاح دستی مبلغ باید دلیل، Permission و Audit Log داشته باشد.
Frontend فقط نمایش می‌دهد؛ منبع حقیقت مالی Backend است.
```

---

## قوانین حقوقی بسیار مهم

برای Legal Domain:

```text
پرونده حقوقی باید بر اساس Snapshot مالی معتبر ساخته شود.
پرونده حقوقی نباید بر اساس محاسبه زنده و بی‌ردپا ساخته شود.
مدارک حقوقی باید Private باشند.
تغییر وضعیت پرونده باید History داشته باشد.
ارجاع حقوقی باید Audit Log داشته باشد.
مبالغ ادعایی باید قابل ردیابی باشند.
```

---

## قوانین فایل بسیار مهم

برای Files Domain:

```text
فایل واقعی داخل دیتابیس ذخیره نمی‌شود.
دیتابیس فقط Metadata و مسیر داخلی را نگه می‌دارد.
فایل‌های حساس باید Private Storage داشته باشند.
دانلود فایل حساس باید از Controller امن انجام شود.
دانلود فایل حساس باید Permission و Scope Check داشته باشد.
دانلود فایل حساس باید Audit/File Access Log داشته باشد.
توکن دانلود باید موقت باشد.
توکن خام نباید ذخیره شود.
```

---

## قوانین دیتابیس بسیار مهم

Codex باید این استانداردها را رعایت کند:

```text
Table names: plural + snake_case
Column names: snake_case
Primary key: id BIGINT UNSIGNED AUTO_INCREMENT
Foreign key columns: {entity}_id
Money: DECIMAL(15,2)
Datetime columns: *_at
Date columns: *_date
Boolean columns: is_ / has_ / can_ / should_ / requires_
Charset: utf8mb4
Collation: utf8mb4_unicode_ci
```

شماره‌های رسمی مثل موارد زیر باید Unique باشند:

```text
customer_number
contract_number
installment_number
payment_number
legal_case_number
file_number
report_number
backup_number
plugin_number
```

---

## قوانین Migration

Codex هنگام ساخت یا تغییر دیتابیس باید این قوانین را رعایت کند:

```text
Migration باید idempotent باشد.
قبل از ساخت جدول، وجود جدول بررسی شود.
قبل از افزودن ستون، وجود ستون بررسی شود.
قبل از افزودن Index، وجود Index بررسی شود.
Migration مخرب بدون Backup ممنوع است.
حذف جدول یا ستون پیش‌فرض ممنوع است.
تغییر ستون مالی بدون Backup و Log ممنوع است.
Seedها باید قابل اجرای چندباره باشند.
Secret خام نباید Seed شود.
```

مرجع اصلی Migration:

```text
docs/database/20_MIGRATION_RULES.md
```

---

## قوانین UI و زبان

سیستم باید برای زبان فارسی طراحی شود.

قوانین:

```text
RTL کامل
متن‌های فارسی
فرمت عددی و پولی مناسب ایران
تاریخ شمسی در UI در صورت نیاز
فونت YekanBakh از assets/fonts
عدم استفاده از UI چپ‌به‌راست برای صفحات اصلی
عدم نمایش پیام‌های فنی خام به کاربر نهایی
```

---

## نحوه تصمیم‌گیری هنگام ابهام

اگر Codex در پروژه با ابهام روبه‌رو شد:

```text
1. حدس خطرناک نزند.
2. اول مستند مرتبط را پیدا کند.
3. اگر مستند مرتبط کافی نبود، از ساختار فعلی پروژه الگو بگیرد.
4. اگر موضوع مالی، حقوقی یا امنیتی بود، محافظه‌کارانه‌ترین حالت را انتخاب کند.
5. اگر هنوز ابهام باقی بود، سؤال بپرسد یا TODO فنی واضح ثبت کند.
```

اما Codex نباید Feature ناقص یا Placeholder تحویل دهد، مگر صراحتاً Task فقط طراحی اسکلت باشد.

---

## دستور شروع برای Codex

وقتی Codex برای اولین بار وارد پروژه شد، باید این کارها را انجام دهد:

```text
1. این فایل را بخوان.
2. ساختار Root پروژه را بررسی کن.
3. فولدر docs را بررسی کن، اما همه فایل‌ها را یک‌جا نخوان.
4. فایل‌های codex بعدی را بخوان، اگر وجود دارند.
5. فایل‌های config، public entrypoint، routes، app، database و assets را شناسایی کن.
6. Technology Stack واقعی پروژه را با این مستند تطبیق بده.
7. هیچ تغییری اعمال نکن تا Task مشخص شود.
8. بعد از دریافت Task، فقط مستندات مرتبط با همان Task را بخوان.
```

---

## قالب پاسخ داخلی Codex قبل از شروع پیاده‌سازی

قبل از کدنویسی، Codex باید برای خودش این موارد را مشخص کند:

```text
Task:
Domain:
Files to read:
Files to modify:
Database changes:
Security requirements:
Financial impact:
Audit requirements:
Testing checklist:
```

اگر Task مالی یا حقوقی است، این دو خط هم باید اضافه شود:

```text
Financial Log required: yes/no
Legal/Audit sensitivity: low/medium/high/critical
```

---

## حداقل Acceptance برای هر Feature

هر Feature زمانی قابل قبول است که:

```text
با ساختار پروژه هماهنگ باشد.
Permission لازم داشته باشد.
Scope لازم داشته باشد.
CSRF لازم داشته باشد، اگر فرم یا عملیات state-changing دارد.
Validation سمت سرور داشته باشد.
Queryها Prepared باشند.
خطاها کنترل‌شده باشند.
Log مناسب داشته باشد.
در UI فارسی و RTL درست نمایش داده شود.
با Shared Hosting ناسازگار نباشد.
```

---

## پایان فایل

این فایل نقطه شروع Codex است.

Codex باید این فایل را به‌عنوان نقشه اولیه در نظر بگیرد، اما برای اجرای هر Task فقط به مستندات مرتبط همان Task مراجعه کند.

قانون نهایی:

> کم بخوان، درست بخوان، مرحله‌ای اجرا کن، و در بخش‌های مالی، حقوقی و امنیتی هرگز حدس خطرناک نزن.
````
