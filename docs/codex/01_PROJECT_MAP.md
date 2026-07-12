# 01 — Project Map

نقشه سریع پروژه **Proma Pay / پروما** برای Codex

---

## هدف این فایل

این فایل به Codex کمک می‌کند پروژه را سریع بفهمد، بدون اینکه مجبور شود همه مستندات را یک‌جا بخواند.

Codex باید از این فایل برای تشخیص موارد زیر استفاده کند:

- هر فولدر چه کاربردی دارد.
- هر نوع Task به کدام مستندات مربوط می‌شود.
- ترتیب منطقی پیاده‌سازی چیست.
- کدام بخش‌ها حساس هستند.
- برای هر Feature باید سراغ کدام فایل‌ها رفت.
- کدام فایل‌ها مرجع هستند و کدام فایل‌ها اجرایی‌تر هستند.

---

## ساختار فعلی مستندات

ساختار مستندات پروژه:

```text
docs/
├── specifications/
├── workflows/
├── domains/
├── database/
└── codex/
```

---

## نقش هر فولدر

| فولدر | نقش |
|---|---|
| `specifications/` | تعریف کلی نیازمندی‌ها، هدف پروژه و مشخصات محصول |
| `workflows/` | فرایندهای عملیاتی و سناریوهای کاری |
| `domains/` | منطق دامنه‌ها و مرزبندی بخش‌های سیستم |
| `database/` | طراحی جدول‌ها، ستون‌ها، Indexها و Migrationها |
| `codex/` | راهنمای کوتاه و اجرایی مخصوص Codex |

---

## قانون استفاده از مستندات

Codex نباید همه فایل‌ها را همزمان بخواند.

روش درست:

```text
Task را تشخیص بده.
Domain مربوطه را مشخص کن.
فقط فایل‌های مرتبط با همان Task را بخوان.
بعد پیاده‌سازی کن.
```

روش اشتباه:

```text
همه docs را بخوان.
همه چیز را همزمان تحلیل کن.
بعد بر اساس برداشت کلی کد بزن.
```

---

## نقشه سریع Domainهای اصلی

| Domain | کاربرد |
|---|---|
| Core | تنظیمات پایه، Bootstrap، شماره‌گذاری، ساختار مرکزی |
| Users / Roles / Permissions | کاربران، نقش‌ها، دسترسی‌ها و Scope |
| Customers | مشتریان، اطلاعات هویتی و اعتبارسنجی |
| Contracts | قراردادهای فروش اقساطی |
| Contract Templates & Print | قالب مؤثر، نسخه‌بندی قالب، renderer امن، profile چاپ و بازسازی اسناد |
| Installments | اقساط، سررسیدها، معوقات |
| Payments | پرداخت‌ها، رسیدها، کارت‌به‌کارت، درگاه |
| Financial | لاگ مالی، Ledger، Settlement، Adjustment |
| Legal | پرونده حقوقی، مطالبات، مدارک، Deadlines |
| Chat | پیام‌ها، پیوست‌ها، Bot و ارتباطات |
| Notifications | اعلان‌ها، کانال‌ها، Templateها |
| Calendar | رویدادها، Reminderها، Taskها |
| Files | فایل‌ها، مدارک، Private Storage |
| Settings | تنظیمات عمومی و حساس سیستم |
| Reports | گزارش‌ها، خروجی‌ها، Dashboard |
| Backup & Update | بکاپ، Restore، Update، Maintenance |
| Plugins | پلاگین‌ها، Hookها، Routeها، Migrationها |
| Logs / Audit / Security | Audit، Security، Activity، Error و Job Logs |

---

## نقشه سریع Workflowها

| Workflow | کاربرد |
|---|---|
| Customer Onboarding | ثبت و تکمیل اطلاعات مشتری |
| Contract Creation | ساخت قرارداد و اقساط |
| Installment Payment | پرداخت قسط |
| Card-to-Card Review | بررسی رسید کارت‌به‌کارت |
| Overdue Follow-up | پیگیری معوقات |
| Legal Referral | ارجاع حقوقی |
| Settlement | تسویه کامل |
| Calendar & Reminders | یادآوری‌ها و تقویم |
| Chat & Bot Messages | پیام‌ها و Bot |
| Backup & Update | بکاپ و بروزرسانی |
| Plugin Installation | نصب پلاگین |
| Report Export | خروجی گزارش |

---

## نقشه سریع Database

فولدر `docs/database/` شامل مستندات کامل دیتابیس است.

فایل‌های مهم عمومی:

```text
docs/database/00_DATABASE_OVERVIEW.md
docs/database/01_NAMING_CONVENTIONS.md
docs/database/19_INDEXES_AND_PERFORMANCE.md
docs/database/20_MIGRATION_RULES.md
```

این چهار فایل مرجع عمومی هستند و در بیشتر Taskهای دیتابیسی لازم می‌شوند.

---

## فایل‌های دیتابیس بر اساس Domain

| Domain | فایل دیتابیس مرتبط |
|---|---|
| Core | `02_CORE_TABLES.md` |
| Customers | `03_CUSTOMERS_TABLES.md` |
| Users / Roles / Permissions | `04_USERS_ROLES_PERMISSIONS_TABLES.md` |
| Contracts | `05_CONTRACTS_TABLES.md` |
| Installments | `06_INSTALLMENTS_TABLES.md` |
| Payments | `07_PAYMENTS_TABLES.md` |
| Financial | `08_FINANCIAL_TABLES.md` |
| Legal | `09_LEGAL_TABLES.md` |
| Chat | `10_CHAT_TABLES.md` |
| Notifications | `11_NOTIFICATIONS_TABLES.md` |
| Calendar | `12_CALENDAR_TABLES.md` |
| Files | `13_FILES_TABLES.md` |
| Settings | `14_SETTINGS_TABLES.md` |
| Reports | `15_REPORTS_TABLES.md` |
| Backup & Update | `16_BACKUP_UPDATE_TABLES.md` |
| Plugins | `17_PLUGINS_TABLES.md` |
| Logs / Audit / Security | `18_LOGS_AUDIT_SECURITY_TABLES.md` |

---

## تشخیص فایل‌های لازم بر اساس نوع Task

### اگر Task مربوط به مشتری است

Codex باید بخواند:

```text
docs/database/03_CUSTOMERS_TABLES.md
docs/database/04_USERS_ROLES_PERMISSIONS_TABLES.md
docs/database/18_LOGS_AUDIT_SECURITY_TABLES.md
docs/codex/02_NON_NEGOTIABLE_RULES.md
```

در صورت وجود Workflow مرتبط:

```text
docs/workflows/01_CUSTOMER_ONBOARDING.md
```

---

### اگر Task مربوط به قرارداد است

Codex باید بخواند:

```text
docs/workflows/02_CONTRACT_CREATION.md
docs/database/05_CONTRACTS_TABLES.md
docs/database/06_INSTALLMENTS_TABLES.md
docs/database/08_FINANCIAL_TABLES.md
docs/database/18_LOGS_AUDIT_SECURITY_TABLES.md
```

قانون مهم:

```text
Contract Creation باید Transaction داشته باشد.
ساخت قرارداد و اقساط باید Atomic باشد.
```

---

### اگر Task مربوط به پرداخت است

Codex باید بخواند:

```text
docs/workflows/03_INSTALLMENT_PAYMENT.md
docs/workflows/04_CARD_TO_CARD_REVIEW.md
docs/database/06_INSTALLMENTS_TABLES.md
docs/database/07_PAYMENTS_TABLES.md
docs/database/08_FINANCIAL_TABLES.md
docs/database/18_LOGS_AUDIT_SECURITY_TABLES.md
```

قانون مهم:

```text
فقط payment با status = approved روی بدهی اثر می‌گذارد.
```

---

### اگر Task مربوط به معوقات است

Codex باید بخواند:

```text
docs/workflows/05_OVERDUE_FOLLOWUP.md
docs/database/06_INSTALLMENTS_TABLES.md
docs/database/11_NOTIFICATIONS_TABLES.md
docs/database/12_CALENDAR_TABLES.md
docs/database/18_LOGS_AUDIT_SECURITY_TABLES.md
```

---

### اگر Task مربوط به پرونده حقوقی است

Codex باید بخواند:

```text
docs/workflows/06_LEGAL_REFERRAL.md
docs/database/09_LEGAL_TABLES.md
docs/database/08_FINANCIAL_TABLES.md
docs/database/13_FILES_TABLES.md
docs/database/18_LOGS_AUDIT_SECURITY_TABLES.md
```

قانون مهم:

```text
پرونده حقوقی باید از Snapshot معتبر ساخته شود.
```

---

### اگر Task مربوط به تسویه است

Codex باید بخواند:

```text
docs/workflows/07_SETTLEMENT.md
docs/database/05_CONTRACTS_TABLES.md
docs/database/06_INSTALLMENTS_TABLES.md
docs/database/07_PAYMENTS_TABLES.md
docs/database/08_FINANCIAL_TABLES.md
docs/database/18_LOGS_AUDIT_SECURITY_TABLES.md
```

---

### اگر Task مربوط به فایل است

Codex باید بخواند:

```text
docs/database/13_FILES_TABLES.md
docs/database/18_LOGS_AUDIT_SECURITY_TABLES.md
docs/codex/02_NON_NEGOTIABLE_RULES.md
```

قانون مهم:

```text
فایل حساس همیشه Private است.
مسیر واقعی فایل نباید به کاربر نمایش داده شود.
```

---

### اگر Task مربوط به گزارش است

Codex باید بخواند:

```text
docs/workflows/12_REPORT_EXPORT.md
docs/database/15_REPORTS_TABLES.md
docs/database/13_FILES_TABLES.md
docs/database/18_LOGS_AUDIT_SECURITY_TABLES.md
docs/database/19_INDEXES_AND_PERFORMANCE.md
```

قانون مهم:

```text
Export حساس باید Private، محدود، قابل Audit و دارای Expiration باشد.
```

---

### اگر Task مربوط به تنظیمات است

Codex باید بخواند:

```text
docs/database/14_SETTINGS_TABLES.md
docs/database/18_LOGS_AUDIT_SECURITY_TABLES.md
docs/codex/02_NON_NEGOTIABLE_RULES.md
```

قانون مهم:

```text
Secret خام نباید در settings یا logs ذخیره شود.
```

---

### اگر Task مربوط به Backup یا Update است

Codex باید بخواند:

```text
docs/workflows/10_BACKUP_AND_UPDATE.md
docs/database/16_BACKUP_UPDATE_TABLES.md
docs/database/13_FILES_TABLES.md
docs/database/18_LOGS_AUDIT_SECURITY_TABLES.md
docs/database/20_MIGRATION_RULES.md
```

قانون مهم:

```text
Restore و Update بدون Backup معتبر ممنوع است.
```

---

### اگر Task مربوط به پلاگین است

Codex باید بخواند:

```text
docs/workflows/11_PLUGIN_INSTALLATION.md
docs/database/17_PLUGINS_TABLES.md
docs/database/13_FILES_TABLES.md
docs/database/14_SETTINGS_TABLES.md
docs/database/16_BACKUP_UPDATE_TABLES.md
docs/database/18_LOGS_AUDIT_SECURITY_TABLES.md
docs/database/20_MIGRATION_RULES.md
```

قانون مهم:

```text
پلاگین نباید Permission، CSRF، Financial Log یا Audit را دور بزند.
```

---

## سطح حساسیت Domainها

| Domain | حساسیت |
|---|---|
| Customers | High |
| Contracts | Critical |
| Installments | Critical |
| Payments | Critical |
| Financial | Critical |
| Legal | Critical |
| Files | Critical |
| Settings | High |
| Reports | High |
| Backup & Update | Critical |
| Plugins | Critical |
| Logs / Audit / Security | Critical |
| Notifications | Medium |
| Calendar | Medium |
| Chat | Medium |

---

## قانون تصمیم‌گیری بر اساس حساسیت

اگر حساسیت `Critical` است:

```text
Permission Check الزامی است.
Scope Check الزامی است.
CSRF برای عملیات تغییر وضعیت الزامی است.
Transaction در عملیات داده‌ای مهم الزامی است.
Audit Log الزامی است.
Security Log برای تلاش ناموفق الزامی است.
اگر اثر مالی دارد، Financial Log الزامی است.
```

اگر حساسیت `High` است:

```text
Permission Check الزامی است.
Scope Check در صورت ارتباط با مشتری یا قرارداد الزامی است.
Audit Log برای عملیات حساس الزامی است.
Security Log برای تلاش غیرمجاز الزامی است.
```

اگر حساسیت `Medium` است:

```text
Permission Check لازم است.
Scope Check در داده‌های مشتری لازم است.
Audit Log برای تغییرات مهم لازم است.
```

---

## نقشه پیشنهادی Source Code

اگر پروژه هنوز ساختار کامل ندارد، Codex باید به این ساختار نزدیک شود، مگر اینکه ساختار موجود متفاوت و قابل قبول باشد.

```text
/
├── app/
│   ├── Controllers/
│   ├── Services/
│   ├── Repositories/
│   ├── Models/
│   ├── Middleware/
│   ├── Events/
│   ├── Jobs/
│   ├── Validators/
│   ├── Support/
│   └── Views/
├── config/
├── database/
│   ├── migrations/
│   └── seeds/
├── public/
│   ├── index.php
│   └── assets/
├── storage/
│   ├── private/
│   ├── public/
│   ├── cache/
│   ├── logs/
│   └── backups/
├── routes/
├── resources/
├── docs/
└── tests/
```

---

## نقش فولدرهای Source Code

| فولدر | کاربرد |
|---|---|
| `app/Controllers/` | دریافت Request و ارسال Response |
| `app/Services/` | منطق اصلی Business |
| `app/Repositories/` | Queryهای دیتابیس |
| `app/Models/` | Entity/Modelهای ساده |
| `app/Middleware/` | Auth، CSRF، Permission، Scope |
| `app/Events/` | Eventهای سیستم |
| `app/Jobs/` | Jobهای پس‌زمینه |
| `app/Validators/` | Validation سمت سرور |
| `app/Support/` | Helperهای کنترل‌شده |
| `config/` | تنظیمات غیرحساس |
| `database/migrations/` | Migrationهای دیتابیس |
| `database/seeds/` | Seedهای اولیه |
| `public/` | Entry Point و Assetهای عمومی |
| `storage/private/` | فایل‌های حساس و غیرعمومی |
| `storage/logs/` | لاگ‌های فایلی سیستم |
| `routes/` | تعریف Routeها |

---

## قانون وابستگی لایه‌ها

وابستگی مجاز:

```text
Controller → Service
Service → Repository
Repository → Database
Service → Event / Job / Logger
View → فقط داده آماده‌شده
```

وابستگی ممنوع:

```text
View → Database
Controller → SQL مستقیم
JavaScript → محاسبات مالی قطعی
Repository → HTML
Plugin → جدول‌های Core بدون API رسمی
```

---

## مسیر تصمیم برای هر Feature

Codex باید برای هر Feature این مسیر را طی کند:

```text
1. Feature مربوط به کدام Domain است؟
2. آیا Workflow مرتبط دارد؟
3. آیا نیاز به جدول جدید یا Migration دارد؟
4. آیا اثر مالی دارد؟
5. آیا داده مشتری/قرارداد/پرونده را تغییر می‌دهد؟
6. آیا فایل حساس تولید یا دریافت می‌کند؟
7. آیا نیاز به Notification دارد؟
8. آیا نیاز به Audit Log دارد؟
9. آیا تلاش ناموفق باید Security Log شود؟
10. چه تست حداقلی لازم است؟
```

---

## جدول ارجاع سریع

| نوع کار | مستندات اصلی |
|---|---|
| ساخت جدول | `database/01`, `database/{domain}`, `database/20` |
| بهینه‌سازی Query | `database/19` |
| ساخت Migration | `database/20` |
| ساخت Workflow | `workflows/{workflow}` |
| ساخت Feature مالی | `database/07`, `database/08`, `database/18` |
| ساخت Feature حقوقی | `database/09`, `database/13`, `database/18` |
| ساخت Upload/Download | `database/13`, `database/18` |
| ساخت Export | `database/15`, `database/13`, `database/18` |
| ساخت Plugin | `database/17`, `database/20` |
| ساخت Backup/Restore | `database/16`, `database/20` |
| ساخت Security | `database/18`, `codex/02` |

---

## قوانین محدودکننده برای جلوگیری از خطای Codex

Codex باید این محدودیت‌ها را رعایت کند:

```text
هیچ Feature مالی بدون Transaction پیاده‌سازی نشود.
هیچ پرداخت pending به عنوان پرداخت قطعی ثبت نشود.
هیچ فایل حساس در public قرار نگیرد.
هیچ Secret خام در config، settings، logs یا database ذخیره نشود.
هیچ Migration مخرب بدون Backup طراحی نشود.
هیچ Route حساس بدون Auth و Permission ساخته نشود.
هیچ فرم state-changing بدون CSRF ساخته نشود.
هیچ Query با ورودی کاربر بدون Prepared Statement اجرا نشود.
هیچ گزارش حساس بدون Scope و Permission ساخته نشود.
هیچ پلاگین بدون Security Scan و Permission نصب نشود.
```

---

## ترتیب پیشنهادی پیاده‌سازی پروژه

ترتیب کلی:

```text
1. Bootstrap / Core
2. Database Connection
3. Routing
4. Session Auth
5. CSRF
6. Users / Roles / Permissions
7. Customers
8. Contracts
9. Installments
10. Payments
11. Financial Logs
12. Files
13. Notifications
14. Calendar
15. Legal
16. Reports
17. Backup & Update
18. Plugins
19. Logs / Audit / Security تکمیلی
20. UI Polish / PWA / Electron
```

---

## پایان فایل

این فایل نقشه سریع پروژه است.

Codex باید با کمک این فایل تشخیص دهد برای هر Task دقیقاً کدام مستندات را بخواند و از خواندن بی‌هدف کل پروژه خودداری کند.

قانون نهایی:

> برای هر Task، فقط همان مسیر مستنداتی را بخوان که به همان Domain و همان Workflow مربوط است.
````
