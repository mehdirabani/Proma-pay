# 04 — Current Task Context

قالب استاندارد تعریف Context برای هر Task در پروژه **Proma Pay / پروما**

---

## هدف این فایل

این فایل مشخص می‌کند Codex قبل از شروع هر Task باید چه اطلاعاتی را جمع‌آوری کند و چطور محدوده کار را محدود نگه دارد.

هدف این است که Codex:

- بی‌هدف کل پروژه را بررسی نکند.
- فقط فایل‌های مرتبط با Task فعلی را بخواند.
- قبل از تغییر کد، اثر Task را تشخیص دهد.
- در بخش‌های مالی، حقوقی، امنیتی و فایل‌ها حدس خطرناک نزند.
- خروجی قابل بررسی و قابل تست تحویل دهد.

---

## قانون اصلی

Codex برای هر Task باید اول Context بسازد.

بدون Context، کدنویسی ممنوع است.

```text
No context, no code.
```

---

## قالب Context هر Task

قبل از شروع پیاده‌سازی، Codex باید این قالب را برای خودش تکمیل کند:

```text
Task Title:
Task Type:
Primary Domain:
Related Workflow:
Sensitivity Level:
Files To Read:
Files To Inspect:
Files To Modify:
Database Impact:
Security Impact:
Financial Impact:
Legal Impact:
File Storage Impact:
Audit Log Required:
Security Log Required:
Financial Log Required:
Migration Required:
Testing Checklist:
Stop Conditions:
```

---

## توضیح فیلدها

### Task Title

عنوان کوتاه Task.

مثال:

```text
Implement card-to-card payment review
```

---

### Task Type

نوع کار باید یکی از موارد زیر باشد:

```text
new_feature
bug_fix
refactor
database_migration
security_fix
ui_update
report
integration
plugin
backup_update
test
documentation
```

---

### Primary Domain

دامنه اصلی Task.

نمونه‌ها:

```text
customers
contracts
installments
payments
financial
legal
files
reports
settings
backup_update
plugins
security
```

---

### Related Workflow

اگر Task به Workflow خاصی مربوط است، باید مشخص شود.

مثال:

```text
docs/workflows/04_CARD_TO_CARD_REVIEW.md
```

اگر Workflow مرتبط ندارد:

```text
none
```

---

### Sensitivity Level

سطح حساسیت باید یکی از این موارد باشد:

```text
low
medium
high
critical
```

قانون:

```text
payments, financial, legal, files, backup, plugins = critical
contracts, installments, settings, reports = high or critical
customers = high
notifications, calendar, chat = medium
```

---

## انتخاب مستندات مرتبط

Codex باید فقط مستندات مرتبط با Task را بخواند.

### Task مربوط به پرداخت

```text
docs/workflows/03_INSTALLMENT_PAYMENT.md
docs/workflows/04_CARD_TO_CARD_REVIEW.md
docs/database/06_INSTALLMENTS_TABLES.md
docs/database/07_PAYMENTS_TABLES.md
docs/database/08_FINANCIAL_TABLES.md
docs/database/18_LOGS_AUDIT_SECURITY_TABLES.md
```

---

### Task مربوط به قرارداد

```text
docs/workflows/02_CONTRACT_CREATION.md
docs/database/05_CONTRACTS_TABLES.md
docs/database/06_INSTALLMENTS_TABLES.md
docs/database/08_FINANCIAL_TABLES.md
docs/database/18_LOGS_AUDIT_SECURITY_TABLES.md
```

---

### Task مربوط به مشتری

```text
docs/workflows/01_CUSTOMER_ONBOARDING.md
docs/database/03_CUSTOMERS_TABLES.md
docs/database/04_USERS_ROLES_PERMISSIONS_TABLES.md
docs/database/18_LOGS_AUDIT_SECURITY_TABLES.md
```

---

### Task مربوط به فایل

```text
docs/database/13_FILES_TABLES.md
docs/database/18_LOGS_AUDIT_SECURITY_TABLES.md
docs/codex/02_NON_NEGOTIABLE_RULES.md
```

---

### Task مربوط به پرونده حقوقی

```text
docs/workflows/06_LEGAL_REFERRAL.md
docs/database/09_LEGAL_TABLES.md
docs/database/08_FINANCIAL_TABLES.md
docs/database/13_FILES_TABLES.md
docs/database/18_LOGS_AUDIT_SECURITY_TABLES.md
```

---

### Task مربوط به گزارش و Export

```text
docs/workflows/12_REPORT_EXPORT.md
docs/database/15_REPORTS_TABLES.md
docs/database/13_FILES_TABLES.md
docs/database/18_LOGS_AUDIT_SECURITY_TABLES.md
docs/database/19_INDEXES_AND_PERFORMANCE.md
```

---

### Task مربوط به Migration

```text
docs/database/00_DATABASE_OVERVIEW.md
docs/database/01_NAMING_CONVENTIONS.md
docs/database/19_INDEXES_AND_PERFORMANCE.md
docs/database/20_MIGRATION_RULES.md
```

به‌علاوه فایل دیتابیس Domain مربوطه.

---

## Files To Inspect

Codex باید قبل از تغییر کد، فایل‌های واقعی پروژه را بررسی کند.

نمونه:

```text
routes/web.php
public/index.php
app/Controllers/PaymentController.php
app/Services/PaymentService.php
app/Repositories/PaymentRepository.php
database/migrations/
```

قانون:

```text
قبل از ساخت فایل جدید، بررسی کن آیا فایل مشابه وجود دارد یا نه.
```

---

## Files To Modify

Codex باید فایل‌هایی را که قرار است تغییر دهد مشخص کند.

مثال:

```text
app/Services/PaymentService.php
app/Repositories/PaymentRepository.php
app/Controllers/PaymentController.php
database/migrations/2026_01_10_120000_create_payments_tables.php
```

قانون:

```text
فایل نامرتبط را تغییر نده.
ساختار پروژه را بی‌دلیل جابه‌جا نکن.
```

---

## Database Impact

Codex باید مشخص کند Task روی دیتابیس اثر دارد یا نه.

گزینه‌ها:

```text
none
read_only
new_table
new_column
new_index
data_change
dangerous_change
```

اگر مقدار `dangerous_change` باشد:

```text
Backup required: yes
Audit required: yes
Stop and ask before execution: yes
```

---

## Security Impact

Codex باید بررسی کند آیا Task نیاز دارد به:

```text
Authentication
Permission Check
Scope Check
CSRF Protection
Validation
Audit Log
Security Log
Rate Limit
Private Storage
```

قانون:

```text
اگر عملیات state-changing است، CSRF لازم است.
اگر داده حساس است، Permission و Scope لازم است.
```

---

## Financial Impact

اگر Task روی مبلغ، بدهی، پرداخت، قسط، تسویه یا لاگ مالی اثر دارد، Financial Impact برابر yes است.

در این حالت الزامی است:

```text
Server-side calculation
Transaction
Financial Log
Audit Log
Validation
Rollback on failure
```

قانون:

```text
Payment pending نباید اثر مالی داشته باشد.
فقط Payment approved اثر مالی دارد.
```

---

## Legal Impact

اگر Task روی پرونده حقوقی، مطالبه، مدارک حقوقی یا مهلت حقوقی اثر دارد، Legal Impact برابر yes است.

در این حالت الزامی است:

```text
Permission Check
Scope Check
Audit Log
Status History
Private Files
Financial Snapshot when needed
```

---

## File Storage Impact

اگر Task فایل آپلود، دانلود، نمایش یا Export دارد، File Storage Impact برابر yes است.

در این حالت الزامی است:

```text
Files Domain
Private Storage for sensitive files
No direct public path
Secure Download Controller
File Access Log
Permission Check
Scope Check
```

---

## Migration Required

اگر Task نیاز به Migration دارد، Codex باید مشخص کند:

```text
Migration Name:
Tables Affected:
Columns Added:
Indexes Added:
Seed Required:
Rollback Safe:
Backup Required:
```

قانون:

```text
Migration باید idempotent باشد.
Migration مخرب بدون Backup ممنوع است.
```

---

## Testing Checklist

برای هر Task حداقل این موارد بررسی شود:

```text
Valid input
Invalid input
Unauthenticated access
Unauthorized access
Out-of-scope access
CSRF failure
Successful operation
Failed operation
Audit log when required
Security log when required
```

برای Task مالی:

```text
Pending payment does not affect balance
Approved payment affects balance
Rejected payment does not affect balance
Transaction rollback works
Financial log is created
```

برای Task فایل:

```text
Sensitive file is private
Direct public access is impossible
Download requires permission
Download creates access log
Invalid token is rejected
```

---

## Stop Conditions

Codex باید در این شرایط متوقف شود:

```text
ابهام در محاسبه مالی
ابهام در وضعیت پرداخت
ابهام در مالکیت داده
ابهام در Permission
ابهام در Scope
ابهام در Public یا Private بودن فایل
ابهام در Migration مخرب
ابهام در Secret یا Token
ابهام در اثر حقوقی
```

قانون:

```text
در ابهام‌های مالی، حقوقی و امنیتی، حدس نزن.
```

---

## نمونه Context برای Task پرداخت کارت‌به‌کارت

```text
Task Title:
Review card-to-card payment receipt

Task Type:
new_feature

Primary Domain:
payments

Related Workflow:
docs/workflows/04_CARD_TO_CARD_REVIEW.md

Sensitivity Level:
critical

Files To Read:
docs/database/07_PAYMENTS_TABLES.md
docs/database/08_FINANCIAL_TABLES.md
docs/database/13_FILES_TABLES.md
docs/database/18_LOGS_AUDIT_SECURITY_TABLES.md

Database Impact:
data_change

Security Impact:
Authentication, Permission Check, Scope Check, CSRF, Audit Log, Security Log

Financial Impact:
yes

Legal Impact:
no

File Storage Impact:
yes

Audit Log Required:
yes

Security Log Required:
yes, for denied access or invalid receipt action

Financial Log Required:
yes, only when payment becomes approved

Migration Required:
maybe

Testing Checklist:
pending receipt does not affect debt
approved receipt affects debt
rejected receipt does not affect debt
receipt file remains private
invalid permission is denied
```

---

## نمونه Context برای Task ساخت مشتری

```text
Task Title:
Create customer profile

Task Type:
new_feature

Primary Domain:
customers

Related Workflow:
docs/workflows/01_CUSTOMER_ONBOARDING.md

Sensitivity Level:
high

Files To Read:
docs/database/03_CUSTOMERS_TABLES.md
docs/database/04_USERS_ROLES_PERMISSIONS_TABLES.md
docs/database/18_LOGS_AUDIT_SECURITY_TABLES.md

Database Impact:
new_table or data_change

Security Impact:
Authentication, Permission Check, Scope Check, CSRF, Validation, Audit Log

Financial Impact:
no

Legal Impact:
no

File Storage Impact:
maybe

Audit Log Required:
yes, for sensitive identity changes

Security Log Required:
yes, for denied access

Financial Log Required:
no

Migration Required:
yes, if tables do not exist
```

---

## خروجی مورد انتظار از Codex قبل از کدنویسی

Codex باید قبل از شروع کدنویسی، خلاصه کوتاه Context را برای خودش آماده کند.

فرمت پیشنهادی:

```text
I will work on: {task}
Primary domain: {domain}
Sensitivity: {level}
I need to read: {docs}
I will inspect: {files}
I may modify: {files}
Security required: {items}
Database impact: {impact}
Stop if: {conditions}
```

---

## قانون نهایی

Codex باید هر Task را کوچک، محدود و قابل تست نگه دارد.

```text
Small task.
Relevant docs only.
Inspect before modify.
No risky guess.
No unrelated changes.
```

---

## پایان فایل