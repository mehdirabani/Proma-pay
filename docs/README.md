# Proma Pay Documentation

مستندات رسمی پروژه **Proma Pay / پروما**

---

## معرفی

این پوشه شامل مستندات اصلی پروژه **Proma Pay** است.

Proma Pay یک سیستم مدیریت فروش اقساطی، قراردادها، مشتریان، پرداخت‌ها، اقساط، معوقات، پرونده‌های حقوقی، فایل‌های حساس، گزارش‌ها، بکاپ، پلاگین‌ها و عملیات مالی است.

هدف این مستندات این است که پروژه به‌صورت مرحله‌ای، امن، قابل توسعه و قابل ارجاع برای Codex پیاده‌سازی شود.

---

## نکته بسیار مهم برای Codex

Codex نباید همه مستندات را یک‌جا بخواند.

روش درست استفاده از مستندات:

```text
1. ابتدا فقط docs/codex/ را بخوان.
2. پروژه را بررسی کن.
3. هیچ تغییری انجام نده تا Task مشخص شود.
4. بعد از مشخص شدن Task، فقط مستندات مرتبط با همان Task را بخوان.
5. از خواندن بی‌هدف کل docs خودداری کن.
```

قانون اصلی:

```text
Read less.
Read relevant.
Implement step by step.
Do not guess in financial, legal, security or file-related tasks.
```

---

## ساختار مستندات

```text
docs/
├── README.md
├── specifications/
├── workflows/
├── domains/
├── database/
└── codex/
```

---

## نقش هر فولدر

| فولدر | کاربرد |
|---|---|
| `specifications/` | مشخصات کلی پروژه، نیازمندی‌ها، قواعد محصول و تصویر کلی سیستم |
| `workflows/` | فرایندهای عملیاتی مثل ثبت مشتری، ساخت قرارداد، پرداخت قسط، پیگیری معوقات و ارجاع حقوقی |
| `domains/` | تعریف دامنه‌های اصلی سیستم و مرزبندی منطق هر بخش |
| `database/` | طراحی دیتابیس، جدول‌ها، ستون‌ها، Indexها، Migrationها و قوانین Performance |
| `codex/` | راهنمای کوتاه و اجرایی مخصوص Codex برای جلوگیری از خطا و خواندن بی‌هدف مستندات |

---

## ترتیب پیشنهادی مطالعه برای انسان

اگر توسعه‌دهنده انسانی هستید، ترتیب مطالعه پیشنهادی این است:

```text
1. specifications/
2. workflows/
3. domains/
4. database/
5. codex/
```

اما اگر با Codex کار می‌کنید، ترتیب متفاوت است:

```text
1. codex/
2. فقط فایل‌های مرتبط با Task
```

---

## ترتیب شروع Codex

در اولین همگام‌سازی Codex فقط این فایل‌ها باید خوانده شوند:

```text
docs/codex/00_CODEX_START_HERE.md
docs/codex/01_PROJECT_MAP.md
docs/codex/02_NON_NEGOTIABLE_RULES.md
docs/codex/03_IMPLEMENTATION_ORDER.md
```

بعد از خواندن این فایل‌ها، Codex باید پروژه را بررسی کند و هیچ تغییری ندهد تا Task مشخص شود.

---

## فایل‌های مهم Codex

فولدر `docs/codex/` شامل فایل‌های زیر است:

```text
docs/codex/
├── 00_CODEX_START_HERE.md
├── 01_PROJECT_MAP.md
├── 02_NON_NEGOTIABLE_RULES.md
├── 03_IMPLEMENTATION_ORDER.md
├── 04_CURRENT_TASK_CONTEXT.md
├── 05_CODING_STANDARDS.md
├── 06_DATABASE_USAGE_GUIDE.md
├── 07_SECURITY_CHECKLIST.md
└── 08_ACCEPTANCE_CHECKLIST.md
```

کاربرد سریع هر فایل:

| فایل | کاربرد |
|---|---|
| `00_CODEX_START_HERE.md` | نقطه شروع Codex |
| `01_PROJECT_MAP.md` | نقشه سریع پروژه و ارجاع به مستندات |
| `02_NON_NEGOTIABLE_RULES.md` | قوانین غیرقابل مذاکره امنیتی، مالی و فنی |
| `03_IMPLEMENTATION_ORDER.md` | ترتیب اجرای پروژه |
| `04_CURRENT_TASK_CONTEXT.md` | قالب Context برای هر Task |
| `05_CODING_STANDARDS.md` | استانداردهای کدنویسی |
| `06_DATABASE_USAGE_GUIDE.md` | راهنمای استفاده از مستندات دیتابیس |
| `07_SECURITY_CHECKLIST.md` | چک‌لیست امنیتی |
| `08_ACCEPTANCE_CHECKLIST.md` | چک‌لیست پذیرش خروجی Codex |

---

## مستندات Workflow

فولدر `docs/workflows/` شامل فرایندهای اجرایی سیستم است.

نمونه Workflowها:

```text
Customer Onboarding
Contract Creation
Installment Payment
Card-to-Card Review
Overdue Follow-up
Legal Referral
Settlement
Calendar & Reminders
Chat & Bot Messages
Backup & Update
Plugin Installation
Report Export
```

Codex فقط زمانی باید فایل Workflow را بخواند که Task فعلی مستقیماً به آن فرایند مربوط باشد.

---

## مستندات Database

فولدر `docs/database/` شامل طراحی کامل دیتابیس است.

فایل‌های عمومی و مهم دیتابیس:

```text
docs/database/00_DATABASE_OVERVIEW.md
docs/database/01_NAMING_CONVENTIONS.md
docs/database/19_INDEXES_AND_PERFORMANCE.md
docs/database/20_MIGRATION_RULES.md
```

قانون مهم:

```text
برای ساخت یا تغییر جدول، فقط فایل دیتابیس Domain مربوطه را بخوان.
برای Migration، حتماً 20_MIGRATION_RULES.md را بخوان.
برای Queryهای سنگین، حتماً 19_INDEXES_AND_PERFORMANCE.md را بخوان.
```

---

## قوانین غیرقابل مذاکره پروژه

این قوانین در تمام پروژه قطعی هستند:

```text
همه Queryها باید با PDO Prepared Statements باشند.
هیچ SQL خام با ورودی کاربر مجاز نیست.
همه فرم‌های حساس باید CSRF داشته باشند.
همه عملیات حساس باید Permission Check داشته باشند.
داده‌های مشتری، قرارداد، پرداخت، فایل و پرونده باید Scope Check داشته باشند.
محاسبات مالی فقط سمت سرور انجام می‌شود.
مبلغ‌ها باید DECIMAL باشند، نه FLOAT یا DOUBLE.
فقط Payment approved روی بدهی اثر می‌گذارد.
تغییرات مالی باید Financial Log داشته باشند.
عملیات حساس باید Audit Log داشته باشند.
تلاش غیرمجاز باید Security Log داشته باشد.
فایل‌های حساس باید در Private Storage باشند.
Secret، Token، API Key و Password خام نباید در DB یا Log ذخیره شوند.
پروژه باید با Shared Hosting سازگار بماند.
```

---

## روش درست ارجاع Task به Codex

برای هر Task، به Codex این‌طور دستور داده شود:

```text
First read:

docs/codex/00_CODEX_START_HERE.md
docs/codex/01_PROJECT_MAP.md
docs/codex/02_NON_NEGOTIABLE_RULES.md
docs/codex/03_IMPLEMENTATION_ORDER.md

Then inspect the project structure.

Do not modify anything until the current task is clear.

For this task, read only the related docs.
```

نمونه برای Task پرداخت کارت‌به‌کارت:

```text
Read only:

docs/workflows/04_CARD_TO_CARD_REVIEW.md
docs/database/07_PAYMENTS_TABLES.md
docs/database/08_FINANCIAL_TABLES.md
docs/database/13_FILES_TABLES.md
docs/database/18_LOGS_AUDIT_SECURITY_TABLES.md
docs/codex/07_SECURITY_CHECKLIST.md
docs/codex/08_ACCEPTANCE_CHECKLIST.md
```

---

## ترتیب پیشنهادی پیاده‌سازی پروژه

ترتیب کلی پیاده‌سازی:

```text
00. Project Inspection
01. Core Bootstrap
02. Database Foundation
03. Security Foundation
04. Users / Roles / Permissions
05. Customers
06. Contracts
07. Installments
08. Payments
09. Financial Logs
10. Files
11. Notifications / Events / Jobs
12. Calendar / Reminders / Tasks
13. Legal
14. Reports / Exports
15. Backup & Update
16. Plugins
17. PWA / Electron Support
18. Final Hardening
```

قانون مهم:

```text
Featureهای مالی، حقوقی، فایل، گزارش، بکاپ و پلاگین نباید قبل از Security Foundation ساخته شوند.
```

---

## وضعیت فعلی مستندات

مستندات اصلی آماده شده‌اند و برای شروع پیاده‌سازی مرحله‌ای کافی هستند.

```text
specifications/   آماده
workflows/        آماده
domains/          آماده
database/         آماده
codex/            آماده
```

در این مرحله نیازی به ساخت فولدرهای سنگین جدید مثل `backend/`, `frontend/`, `api/` یا `security/` نیست.

در صورت نیاز، این موارد بعداً و فقط بر اساس نیاز واقعی پروژه اضافه می‌شوند.

---

## توصیه مهم

این مستندات برای این ساخته نشده‌اند که Codex همه آن‌ها را یک‌جا بخواند.

این مستندات برای این ساخته شده‌اند که:

```text
هر Task فقط به مستندات مرتبط خودش وصل شود.
```

به این شکل احتمال خطای Codex بسیار کمتر می‌شود و پروژه مرحله‌ای، تمیز و قابل کنترل جلو می‌رود.

---

## قانون نهایی

```text
Do not over-read.
Do not over-engineer.
Do not guess.
Do not bypass security.
Do not change money without logs.
Do not expose private files.
Implement one step at a time.
```

---

## پایان فایل
````
