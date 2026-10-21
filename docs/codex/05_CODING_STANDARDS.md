# 05 — Coding Standards

استانداردهای کدنویسی پروژه **Proma Pay / پروما** برای Codex

---

## هدف این فایل

این فایل مشخص می‌کند Codex هنگام نوشتن، اصلاح یا بازسازی کدهای پروژه باید چه استانداردهایی را رعایت کند.

هدف این فایل این است که خروجی Codex:

- خوانا باشد.
- قابل نگهداری باشد.
- با PHP 7.4+ سازگار باشد.
- با معماری سبک MVC هماهنگ باشد.
- برای Shared Hosting مناسب باشد.
- امنیت پایه را رعایت کند.
- از کدنویسی پراکنده و غیرقابل کنترل جلوگیری کند.
- منطق مالی، حقوقی و امنیتی را در جای درست قرار دهد.

---

## اصل اصلی کدنویسی

اصل اصلی:

> کد باید ساده، قابل فهم، قابل تست و قابل ردیابی باشد.

Codex نباید برای ساده‌ترین نیازها، ساختار پیچیده یا وابستگی سنگین ایجاد کند.

---

## Stack قطعی

کدها باید با این Stack هماهنگ باشند:

```text
PHP 7.4+
MySQL / MariaDB
PDO Prepared Statements
Lightweight MVC
Session Authentication
CSRF Protection
Vanilla JavaScript
Chart.js
RTL Persian UI
Shared Hosting Compatible
```

---

## قوانین عمومی

Codex باید همیشه این قوانین را رعایت کند:

```text
کد تمیز و قابل خواندن بنویس.
نام‌ها واضح و معنی‌دار باشند.
از منطق تکراری جلوگیری کن.
منطق حساس را در جای درست قرار بده.
قبل از ساخت فایل جدید، فایل مشابه را بررسی کن.
ساختار پروژه را بی‌دلیل تغییر نده.
کد Placeholder تولید نکن، مگر Task فقط اسکلت بخواهد.
TODO مبهم ننویس.
در بخش مالی، حقوقی و امنیتی حدس خطرناک نزن.
```

---

## استاندارد نام‌گذاری فایل‌ها

### Controller

```text
CustomerController.php
ContractController.php
PaymentController.php
LegalCaseController.php
ReportController.php
```

### Service

```text
CustomerService.php
ContractService.php
PaymentService.php
FinancialLogService.php
FileStorageService.php
```

### Repository

```text
CustomerRepository.php
ContractRepository.php
PaymentRepository.php
InstallmentRepository.php
AuditLogRepository.php
```

### Middleware

```text
AuthMiddleware.php
CsrfMiddleware.php
PermissionMiddleware.php
ScopeMiddleware.php
```

### Validator

```text
CustomerValidator.php
ContractValidator.php
PaymentValidator.php
```

قانون:

```text
نام فایل باید با مسئولیت همان فایل هماهنگ باشد.
```

---

## استاندارد نام‌گذاری کلاس‌ها

کلاس‌ها باید PascalCase باشند.

مجاز:

```php
class PaymentService
{
}
```

ممنوع:

```php
class payment_service
{
}
```

---

## استاندارد نام‌گذاری متدها

متدها باید camelCase باشند.

مجاز:

```php
public function approvePayment(int $paymentId): bool
{
}
```

ممنوع:

```php
public function approve_payment($payment_id)
{
}
```

---

## استاندارد نام‌گذاری متغیرها

متغیرها باید camelCase باشند.

مجاز:

```php
$customerId = 15;
$paymentAmount = '1500000.00';
```

ممنوع:

```php
$customer_id = 15;
$PaymentAmount = 1500000;
```

استثنا:

```text
نام ستون‌های دیتابیس snake_case هستند.
آرایه‌هایی که مستقیم از دیتابیس می‌آیند می‌توانند keyهای snake_case داشته باشند.
```

---

## استاندارد نام‌گذاری دیتابیس

نام جدول‌ها و ستون‌ها باید طبق مستند دیتابیس باشد:

```text
tables: plural snake_case
columns: snake_case
foreign keys: *_id
dates: *_date
datetimes: *_at
money: DECIMAL(15,2)
boolean: is_ / has_ / can_ / should_ / requires_
```

نمونه:

```text
contracts
installments
payments
financial_logs
legal_cases
```

---

## معماری لایه‌ای

ساختار استاندارد:

```text
Controller → Service → Repository → Database
```

قانون:

- Controller نباید SQL مستقیم داشته باشد.
- Controller نباید منطق مالی داشته باشد.
- Controller نباید منطق حقوقی داشته باشد.
- Service محل منطق اصلی Business است.
- Repository محل Queryهای دیتابیس است.
- View فقط داده آماده‌شده را نمایش می‌دهد.
- JavaScript فقط برای تعامل UI است، نه منبع حقیقت مالی.

---

## مسئولیت Controller

Controller باید فقط این کارها را انجام دهد:

```text
دریافت Request
فراخوانی Validator
فراخوانی Service
ارسال Response
انتخاب View
مدیریت Redirect
```

Controller نباید این کارها را انجام دهد:

```text
محاسبه مبلغ قرارداد
تایید مستقیم پرداخت
تغییر مستقیم بدهی
ساخت مستقیم پرونده حقوقی
اجرای SQL خام
آپلود فایل بدون File Service
ثبت دستی Log بدون Service مربوطه
```

---

## مسئولیت Service

Service محل منطق اصلی است.

Service باید انجام دهد:

```text
اجرای قوانین Business
هماهنگی Repositoryها
اجرای Transaction
ثبت Financial Log
ثبت Audit Log
ثبت Event
کنترل وضعیت‌های حساس
هماهنگی File Storage
```

نمونه مسئولیت Service:

```text
PaymentService:
- بررسی وضعیت پرداخت
- تایید یا رد پرداخت
- کاهش بدهی فقط در approved
- ثبت Financial Log
- ثبت Audit Log
- اجرای Transaction
```

---

## مسئولیت Repository

Repository فقط مسئول ارتباط با دیتابیس است.

Repository باید:

```text
Queryهای امن با PDO اجرا کند.
Prepared Statement استفاده کند.
داده خام دیتابیس را برگرداند.
Queryها را محدود، قابل فهم و Index-friendly بنویسد.
```

Repository نباید:

```text
HTML تولید کند.
Permission تصمیم بگیرد.
CSRF بررسی کند.
محاسبات مالی حساس را به تنهایی انجام دهد.
فایل آپلود کند.
Response تولید کند.
```

---

## استاندارد Query

همه Queryها باید Prepared باشند.

مجاز:

```php
$stmt = $this->pdo->prepare(
    'SELECT id, customer_number, full_name FROM customers WHERE id = :id AND deleted_at IS NULL'
);

$stmt->execute([
    'id' => $customerId,
]);
```

ممنوع:

```php
$sql = "SELECT * FROM customers WHERE id = " . $_GET['id'];
```

قوانین:

```text
ورودی کاربر مستقیم داخل SQL قرار نگیرد.
Sort column باید whitelist شود.
Filterها باید whitelist شوند.
Queryهای لیستی LIMIT داشته باشند.
Queryهای حجیم Pagination داشته باشند.
SELECT * در لیست‌های بزرگ ممنوع است.
```

---

## استاندارد Transaction

برای عملیات حساس باید Transaction استفاده شود.

الگوی استاندارد:

```php
$this->pdo->beginTransaction();

try {
    // Sensitive operations

    $this->pdo->commit();
} catch (Throwable $exception) {
    $this->pdo->rollBack();

    throw $exception;
}
```

Transaction الزامی است برای:

```text
ساخت قرارداد
ساخت اقساط
تایید پرداخت
Refund
Settlement
Adjustment
Legal Referral
Restore
Update
Plugin Installation
Migration حساس
```

---

## استاندارد مبلغ‌ها

مبلغ‌ها نباید با float یا double مدیریت شوند.

ممنوع:

```php
$total = 1500000.75;
```

مجاز:

```php
$totalAmount = '1500000.75';
```

قوانین:

```text
در دیتابیس DECIMAL(15,2)
در PHP ترجیحاً string برای مقدار پولی
محاسبات حساس سمت سرور
عدم اعتماد به مبلغ ارسال‌شده از Frontend
```

---

## استاندارد Validation

همه ورودی‌ها باید سمت سرور Validate شوند.

Validation باید بررسی کند:

```text
required بودن
نوع داده
طول مجاز
فرمت موبایل
فرمت کد ملی
مبلغ مجاز
تاریخ معتبر
وضعیت مجاز
فایل مجاز
مالکیت داده
```

قانون:

```text
Validation در Frontend کافی نیست.
```

---

## استاندارد CSRF

هر عملیات state-changing باید CSRF داشته باشد.

شامل:

```text
create
update
delete
approve
reject
upload
download حساس
restore
install plugin
change setting
change permission
```

قانون:

```text
درخواست POST/PUT/PATCH/DELETE بدون CSRF معتبر باید رد شود.
```

---

## استاندارد Permission و Scope

برای عملیات حساس:

```text
Permission Check الزامی است.
Scope Check الزامی است.
```

Permission مشخص می‌کند کاربر حق انجام عملیات را دارد یا نه.

Scope مشخص می‌کند کاربر به این رکورد خاص دسترسی دارد یا نه.

نمونه:

```text
کاربر ممکن است permission مشاهده قرارداد داشته باشد،
اما به قرارداد شعبه یا مشتری دیگر scope نداشته باشد.
```

---

## استاندارد Log

برای عملیات حساس باید Log مناسب ثبت شود.

| نوع Log | کاربرد |
|---|---|
| Audit Log | عملیات مهم و قابل پیگیری |
| Security Log | تلاش غیرمجاز یا خطرناک |
| Financial Log | اثر مالی قطعی |
| Activity Log | فعالیت عمومی و Timeline |
| Error Log | خطاهای فنی |
| Job Log | اجرای Jobها |

قانون:

```text
Secret، Password، Token، API Key و مسیر واقعی فایل در Log ذخیره نشود.
```

---

## استاندارد Error Handling

خطاها باید کنترل‌شده باشند.

قوانین:

```text
خطای فنی خام به کاربر نمایش داده نشود.
Stack Trace در UI نمایش داده نشود.
خطای مهم Log شود.
پیام کاربر باید ساده و قابل فهم باشد.
در عملیات مالی، خطا باید باعث Rollback شود.
```

ممنوع:

```php
die($exception->getMessage());
```

---

## استاندارد Response

Response باید قابل پیش‌بینی باشد.

برای Responseهای JSON:

```php
[
    'success' => true,
    'message' => 'عملیات با موفقیت انجام شد.',
    'data' => [],
]
```

برای خطا:

```php
[
    'success' => false,
    'message' => 'امکان انجام عملیات وجود ندارد.',
    'errors' => [],
]
```

قانون:

```text
پیام‌های فنی خام در Response عمومی قرار نگیرند.
```

---

## استاندارد View

View باید فقط داده آماده‌شده را نمایش دهد.

قوانین:

```text
View نباید Query اجرا کند.
View نباید محاسبه مالی قطعی انجام دهد.
View نباید Permission اصلی را کنترل کند.
View باید خروجی را Escape کند.
View باید RTL باشد.
View باید فارسی باشد.
```

ممنوع:

```php
<?php $pdo->query('SELECT * FROM payments'); ?>
```

---

## استاندارد JavaScript

JavaScript فقط برای تعامل UI است.

مجاز:

```text
نمایش/مخفی کردن بخش‌ها
اعتبارسنجی اولیه فرم
ارسال Ajax
نمایش Chart
بهبود تجربه کاربری
```

ممنوع:

```text
محاسبه نهایی بدهی
تایید پرداخت
تعیین وضعیت قسط
دور زدن Permission
ذخیره Token حساس در LocalStorage
```

قانون:

```text
هر چیزی که اثر مالی یا امنیتی دارد باید در Backend تایید شود.
```

---

## استاندارد CSS و RTL

UI باید فارسی و راست‌به‌چپ باشد.

قوانین:

```text
direction: rtl
فونت YekanBakh از assets/fonts
طراحی خوانا
Spacing منظم
عدم شکستن Layout در موبایل
عدم استفاده از متن انگلیسی برای پیام‌های کاربر
```

قانون:

```text
CSS نباید با نام‌های تصادفی و نامفهوم نوشته شود.
```

---

## استاندارد فایل‌ها

فایل‌های حساس باید از File Service عبور کنند.

قوانین:

```text
فایل حساس در public ذخیره نشود.
مسیر واقعی فایل به کاربر نمایش داده نشود.
دانلود فایل حساس از Controller امن انجام شود.
Permission و Scope در دانلود بررسی شود.
File Access Log ثبت شود.
```

---

## استاندارد Migration

Migrationها باید امن باشند.

قوانین:

```text
idempotent
قابل ردیابی
ثبت‌شده در migrations
بدون تخریب ناگهانی
بدون Secret خام
با Backup برای تغییر حساس
```

ممنوع:

```text
DROP TABLE حساس
حذف ستون مالی بدون Backup
تغییر مبلغ‌ها بدون Log
Seed کردن Password خام
```

---

## استاندارد کامنت‌گذاری

کامنت باید برای توضیح چرایی باشد، نه توضیح چیز واضح.

مجاز:

```php
// Payment must affect balance only after approval.
```

غیرضروری:

```php
// Set customer id
$customerId = 1;
```

قانون:

```text
کد خوب نیاز به کامنت زیاد ندارد.
اما منطق حساس باید توضیح کوتاه داشته باشد.
```

---

## استاندارد وابستگی‌ها

قوانین:

```text
Dependency سنگین اضافه نشود.
Framework سنگین اضافه نشود.
کتابخانه فقط در صورت نیاز واقعی اضافه شود.
با PHP 7.4 سازگار باشد.
با Shared Hosting سازگار باشد.
```

ممنوع مگر با درخواست صریح:

```text
Laravel
Symfony
Doctrine
Heavy Queue Systems
Complex Build Tools
```

---

## استاندارد امنیت خروجی

هر داده‌ای که به UI می‌رود باید Escape شود.

قوانین:

```text
HTML خروجی Escape شود.
متن کاربر مستقیم Render نشود.
پیام‌ها Sanitized باشند.
نام فایل کاربر مستقیم نمایش داده نشود، مگر پاک‌سازی شده باشد.
```

---

## استاندارد تاریخ و زمان

قوانین:

```text
در دیتابیس DATETIME استاندارد ذخیره شود.
در UI می‌توان تاریخ شمسی نمایش داد.
نام ستون DATETIME با *_at تمام شود.
نام ستون DATE با *_date تمام شود.
Timezone باید کنترل‌شده باشد.
```

---

## استاندارد Status

Statusها باید مقدارهای کنترل‌شده داشته باشند.

ممنوع:

```php
$status = $_POST['status'];
```

مجاز:

```php
$allowedStatuses = ['pending_review', 'approved', 'rejected'];

if (!in_array($status, $allowedStatuses, true)) {
    throw new InvalidArgumentException('Invalid payment status.');
}
```

---

## استاندارد حذف داده

حذف فیزیکی پیش‌فرض ممنوع است.

قانون:

```text
برای داده‌های اصلی از Soft Delete استفاده شود.
```

شامل:

```text
customers
contracts
installments
payments
files
legal_cases
reports
settings
plugins
```

حذف فیزیکی برای این جدول‌ها ممنوع است:

```text
audit_logs
security_logs
financial_logs
payment_status_histories
legal_status_histories
```

---

## استاندارد سازگاری با Shared Hosting

قوانین:

```text
کد باید بدون نیاز به دسترسی root اجرا شود.
نباید به سرویس‌های سیستم‌عامل وابسته باشد.
Jobها باید با Cron ساده قابل اجرا باشند.
Upload و Export نباید Memory را پر کند.
Migration سنگین باید مرحله‌ای باشد.
```

---

## چک‌لیست Codex قبل از تحویل کد

Codex باید قبل از تحویل بررسی کند:

```text
آیا کد با PHP 7.4+ سازگار است؟
آیا Queryها Prepared هستند؟
آیا Validation سمت سرور وجود دارد؟
آیا CSRF برای عملیات تغییر وضعیت وجود دارد؟
آیا Permission بررسی شده است؟
آیا Scope بررسی شده است؟
آیا Log لازم ثبت شده است؟
آیا خطاها کنترل شده‌اند؟
آیا منطق مالی سمت سرور است؟
آیا فایل حساس Private است؟
آیا Secret خام ذخیره نشده است؟
آیا UI فارسی و RTL خراب نشده است؟
آیا ساختار پروژه بی‌دلیل تغییر نکرده است؟
```

---

## قانون نهایی

Codex باید همیشه این اصل را رعایت کند:

> کد کمتر، تمیزتر، امن‌تر و قابل تست بهتر از کد زیاد، پراکنده و پرریسک است.

---

## پایان فایل
````
