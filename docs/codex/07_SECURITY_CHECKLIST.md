# 07 — Security Checklist

چک‌لیست امنیتی اجرای Taskها در پروژه **Proma Pay / پروما** برای Codex

---

## هدف این فایل

این فایل چک‌لیست امنیتی سریع و اجرایی Codex است.

Codex باید قبل از پیاده‌سازی، هنگام پیاده‌سازی و قبل از تحویل هر Feature این فایل را بررسی کند تا مطمئن شود هیچ بخش حساس پروژه بدون کنترل امنیتی ساخته نشده است.

این فایل جایگزین مستندات کامل امنیتی نیست؛ بلکه یک چک‌لیست عملیاتی برای جلوگیری از خطاهای رایج است.

---

## قانون اصلی امنیت

قانون اصلی:

> هر چیزی که داده، پول، قرارداد، فایل، دسترسی یا وضعیت سیستم را تغییر می‌دهد، باید امن، قابل ردیابی و قابل کنترل باشد.

---

## چک‌لیست سریع برای هر Task

قبل از شروع هر Task، Codex باید این موارد را بررسی کند:

```text
آیا این Task نیاز به Login دارد؟
آیا این Task نیاز به Permission دارد؟
آیا این Task نیاز به Scope دارد؟
آیا این Task عملیات state-changing انجام می‌دهد؟
آیا CSRF لازم است؟
آیا داده مالی تغییر می‌کند؟
آیا فایل حساس آپلود یا دانلود می‌شود؟
آیا داده حقوقی درگیر است؟
آیا Audit Log لازم است؟
آیا Security Log لازم است؟
آیا Financial Log لازم است؟
آیا Migration حساس است؟
```

---

## Authentication Checklist

برای صفحات و عملیات داخلی:

```text
[ ] صفحه داخلی بدون Login قابل مشاهده نیست.
[ ] Route حساس پشت Auth Middleware است.
[ ] کاربر غیرفعال اجازه ورود ندارد.
[ ] Session بعد از Login امن ساخته می‌شود.
[ ] Logout Session را پاک می‌کند.
[ ] Password خام ذخیره نمی‌شود.
[ ] Password با الگوریتم امن Hash می‌شود.
[ ] تلاش ورود ناموفق Log می‌شود.
[ ] ورودهای ناموفق تکراری Security Log ایجاد می‌کنند.
```

قانون:

```text
هیچ صفحه مدیریتی نباید بدون Authentication باز شود.
```

---

## Authorization Checklist

برای عملیات حساس:

```text
[ ] Permission لازم مشخص شده است.
[ ] Permission در Backend بررسی می‌شود.
[ ] Permission فقط در UI کنترل نشده است.
[ ] Roleها به‌درستی Permission دارند.
[ ] کاربر بدون Permission رد می‌شود.
[ ] تلاش بدون Permission در عملیات حساس Security Log می‌شود.
[ ] تغییر Role و Permission Audit Log دارد.
```

قانون:

```text
UI فقط تجربه کاربری را کنترل می‌کند؛ تصمیم امنیتی نهایی با Backend است.
```

---

## Scope / Ownership Checklist

برای داده‌های وابسته به مشتری، قرارداد، پرداخت، فایل یا پرونده:

```text
[ ] رکورد متعلق به Scope مجاز کاربر است.
[ ] customer_id بررسی شده است.
[ ] contract_id بررسی شده است.
[ ] branch_id یا owner_id در صورت وجود بررسی شده است.
[ ] کاربر نمی‌تواند با تغییر ID به داده دیگران برسد.
[ ] Scope Check در Backend انجام می‌شود.
[ ] تلاش دسترسی خارج از Scope Security Log می‌شود.
```

قانون:

```text
داشتن Permission عمومی کافی نیست؛ دسترسی به رکورد خاص باید Scope داشته باشد.
```

---

## CSRF Checklist

برای عملیات state-changing:

```text
[ ] فرم CSRF Token دارد.
[ ] Token در Backend بررسی می‌شود.
[ ] Token منقضی یا نامعتبر رد می‌شود.
[ ] عملیات POST/PUT/PATCH/DELETE بدون CSRF رد می‌شود.
[ ] خطای CSRF پیام امن و ساده دارد.
[ ] CSRF نامعتبر در عملیات حساس Security Log می‌شود.
```

عملیات‌هایی که CSRF لازم دارند:

```text
create
update
delete
approve
reject
upload
restore
install plugin
change setting
change permission
change password
settlement
refund
manual adjustment
```

---

## Validation Checklist

برای همه ورودی‌ها:

```text
[ ] ورودی‌های required بررسی می‌شوند.
[ ] نوع داده بررسی می‌شود.
[ ] طول متن‌ها محدود است.
[ ] statusها whitelist دارند.
[ ] sort و filter whitelist دارند.
[ ] مبلغ‌ها فرمت معتبر دارند.
[ ] تاریخ‌ها معتبر هستند.
[ ] موبایل و کد ملی Validation دارند.
[ ] فایل‌ها نوع و حجم مجاز دارند.
[ ] Validation سمت سرور انجام می‌شود.
```

قانون:

```text
Validation سمت Frontend کافی نیست.
```

---

## SQL Security Checklist

برای همه Queryها:

```text
[ ] همه Queryها با PDO Prepared Statements هستند.
[ ] ورودی کاربر مستقیم داخل SQL نیست.
[ ] Sort column از whitelist می‌آید.
[ ] Filterها کنترل‌شده هستند.
[ ] Queryهای لیستی LIMIT دارند.
[ ] Queryهای حجیم Pagination دارند.
[ ] deleted_at IS NULL در Queryهای Soft Delete رعایت شده است.
[ ] SELECT * در لیست‌های بزرگ استفاده نشده است.
```

ممنوع:

```php
$sql = "SELECT * FROM customers WHERE id = " . $_GET['id'];
```

مجاز:

```php
$stmt = $pdo->prepare(
    'SELECT id, full_name FROM customers WHERE id = :id AND deleted_at IS NULL'
);

$stmt->execute([
    'id' => $customerId,
]);
```

---

## Financial Security Checklist

برای هر عملیات مالی:

```text
[ ] محاسبه مالی سمت سرور انجام می‌شود.
[ ] Frontend منبع حقیقت نیست.
[ ] مبلغ‌ها DECIMAL هستند.
[ ] عملیات حساس داخل Transaction است.
[ ] خطا باعث Rollback می‌شود.
[ ] فقط Payment approved روی بدهی اثر می‌گذارد.
[ ] Payment pending/rejected/failed اثر مالی ندارد.
[ ] Financial Log ثبت می‌شود.
[ ] Audit Log ثبت می‌شود.
[ ] اصلاح دستی مالی reason دارد.
[ ] کاربر برای عملیات مالی Permission دارد.
```

قانون بسیار مهم:

```text
Payment pending_review نباید paid_amount یا remaining_amount را تغییر دهد.
```

---

## Payment Security Checklist

برای پرداخت‌ها:

```text
[ ] Payment status کنترل‌شده است.
[ ] رسید کارت‌به‌کارت فقط pending_review ثبت می‌شود.
[ ] رسید بدون بررسی approved نمی‌شود.
[ ] Gateway callback به‌تنهایی کافی نیست.
[ ] پرداخت درگاه Verify می‌شود.
[ ] مبلغ پرداخت با مبلغ مورد انتظار تطبیق داده می‌شود.
[ ] Approval داخل Transaction است.
[ ] Rejection دلیل دارد.
[ ] Payment Status History ثبت می‌شود.
[ ] Payment approved Financial Log دارد.
[ ] تلاش تغییر مستقیم وضعیت پرداخت Security Log می‌شود.
```

وضعیت‌هایی که اثر مالی ندارند:

```text
initiated
waiting
pending_review
failed
rejected
cancelled
expired
```

تنها وضعیت اثرگذار:

```text
approved
```

---

## Contract Security Checklist

برای قراردادها:

```text
[ ] قرارداد به مشتری معتبر وصل است.
[ ] ساخت قرارداد داخل Transaction است.
[ ] اقساط همراه قرارداد ساخته می‌شوند.
[ ] مبلغ قرارداد سمت سرور محاسبه می‌شود.
[ ] جمع اقساط با قرارداد Validate می‌شود.
[ ] تغییر مبلغ قرارداد رسمی Audit دارد.
[ ] تغییر مالی قرارداد Financial Log دارد.
[ ] قرارداد حذف فیزیکی نمی‌شود.
[ ] دسترسی به قرارداد Scope دارد.
```

---

## Installment Security Checklist

برای اقساط:

```text
[ ] قسط به قرارداد معتبر وصل است.
[ ] due_date معتبر دارد.
[ ] amount از نوع DECIMAL است.
[ ] paid_amount فقط با Payment approved تغییر می‌کند.
[ ] وضعیت قسط کنترل‌شده است.
[ ] تغییر وضعیت History دارد.
[ ] تشخیص معوقه سمت سرور انجام می‌شود.
[ ] تغییر دستی مبلغ قسط Audit و Financial Log دارد.
```

---

## Legal Security Checklist

برای پرونده حقوقی:

```text
[ ] پرونده حقوقی از Snapshot مالی معتبر ساخته می‌شود.
[ ] دسترسی به پرونده Permission دارد.
[ ] دسترسی به پرونده Scope دارد.
[ ] مدارک حقوقی Private هستند.
[ ] تغییر وضعیت پرونده History دارد.
[ ] ارجاع حقوقی Audit Log دارد.
[ ] مبلغ مطالبه‌شده قابل ردیابی است.
[ ] فایل‌های حقوقی از Secure Download عبور می‌کنند.
```

ممنوع:

```text
ساخت پرونده بدون Snapshot
Public کردن مدرک حقوقی
حذف History پرونده
تغییر claim_amount بدون Audit
```

---

## File Security Checklist

برای آپلود و دانلود فایل‌ها:

```text
[ ] فایل حساس در public ذخیره نمی‌شود.
[ ] فایل واقعی داخل دیتابیس ذخیره نمی‌شود.
[ ] فقط metadata و storage_path داخلی ذخیره می‌شود.
[ ] مسیر واقعی فایل به کاربر نمایش داده نمی‌شود.
[ ] دانلود فایل حساس از Controller امن انجام می‌شود.
[ ] دانلود Permission Check دارد.
[ ] دانلود Scope Check دارد.
[ ] File Access Log ثبت می‌شود.
[ ] Download Token موقت است.
[ ] Token خام ذخیره نمی‌شود.
[ ] نوع فایل و حجم فایل Validate می‌شود.
[ ] فایل blocked یا quarantined دانلود نمی‌شود.
```

فایل‌های حساس:

```text
مدارک هویتی
قرارداد
رسید پرداخت
چک
سفته
مدارک حقوقی
گزارش مالی
Backup
بسته پلاگین
مهر و امضا
```

---

## Report / Export Security Checklist

برای گزارش‌ها و خروجی‌ها:

```text
[ ] گزارش Permission Check دارد.
[ ] گزارش Scope Check دارد.
[ ] گزارش مالی/حقوقی Audit Log دارد.
[ ] Export حساس در Private Storage ذخیره می‌شود.
[ ] Export Expiration دارد.
[ ] دانلود Export Permission دارد.
[ ] دانلود Export Access Log دارد.
[ ] Export بزرگ Job می‌شود.
[ ] Query گزارش LIMIT یا بازه زمانی دارد.
[ ] داده خارج از Scope در خروجی نیست.
```

ممنوع:

```text
Export همه داده‌ها بدون Scope
ذخیره خروجی حساس در public
دانلود گزارش مالی با لینک مستقیم
```

---

## Settings / Secrets Checklist

برای تنظیمات و Secretها:

```text
[ ] Secret خام در settings ذخیره نمی‌شود.
[ ] Secret خام در logs ذخیره نمی‌شود.
[ ] Secret خام در response نمایش داده نمی‌شود.
[ ] مقدار حساس در UI mask می‌شود.
[ ] تغییر Secret Audit Log دارد.
[ ] تلاش مشاهده Secret Security Log دارد.
[ ] Secret در Migration یا Seed قرار ندارد.
```

Secretها:

```text
password
api_key
token
private_key
webhook_secret
gateway_secret
sms_provider_key
telegram_bot_token
backup_storage_secret
```

---

## Plugin Security Checklist

برای پلاگین‌ها:

```text
[ ] بسته پلاگین Security Scan می‌شود.
[ ] مسیرهای خطرناک مثل ../ رد می‌شوند.
[ ] فایل .env داخل پلاگین ممنوع است.
[ ] فایل‌های خطرناک Block می‌شوند.
[ ] Route پلاگین Auth دارد.
[ ] Route پلاگین Permission دارد.
[ ] عملیات state-changing پلاگین CSRF دارد.
[ ] Permissionهای پلاگین namespace دارند.
[ ] Migration پلاگین ثبت می‌شود.
[ ] Secret پلاگین در Secure Settings است.
[ ] نصب پلاگین حساس Backup دارد.
[ ] فعال/غیرفعال کردن پلاگین Audit Log دارد.
```

قانون:

```text
Plugin نباید Core Security را دور بزند.
```

---

## Backup / Restore / Update Security Checklist

برای Backup، Restore و Update:

```text
[ ] Backup در Private Storage ذخیره می‌شود.
[ ] دانلود Backup Permission ویژه دارد.
[ ] Restore بدون Permission رد می‌شود.
[ ] Restore reason دارد.
[ ] Restore واقعی pre_restore_backup دارد.
[ ] Update حساس pre_update_backup دارد.
[ ] مراحل Update Log می‌شوند.
[ ] Restore و Update Audit Log دارند.
[ ] تلاش Restore/Update غیرمجاز Security Log دارد.
[ ] Maintenance Mode در Restore/Update حساس بررسی شده است.
```

---

## Audit Log Checklist

Audit Log لازم است برای:

```text
[ ] ایجاد یا تغییر قرارداد
[ ] تأیید یا رد پرداخت
[ ] اصلاح مالی
[ ] تسویه
[ ] Refund
[ ] ارجاع حقوقی
[ ] تغییر پرونده حقوقی
[ ] آپلود یا دانلود فایل حساس
[ ] Export گزارش حساس
[ ] تغییر تنظیمات حساس
[ ] تغییر Secret
[ ] Backup
[ ] Restore
[ ] Update
[ ] نصب یا حذف پلاگین
[ ] تغییر Role یا Permission
```

Audit Log نباید شامل موارد زیر باشد:

```text
password
token
api_key
secret
session_id
private file path
```

---

## Security Log Checklist

Security Log لازم است برای:

```text
[ ] Login ناموفق تکراری
[ ] CSRF نامعتبر
[ ] Permission denied در عملیات حساس
[ ] Scope violation
[ ] تلاش مشاهده داده خارج از Scope
[ ] تلاش دانلود فایل Private بدون Permission
[ ] تلاش Export بدون Permission
[ ] تلاش تغییر مبلغ از Frontend
[ ] تلاش دستکاری ID
[ ] تلاش مشاهده Secret
[ ] تلاش نصب پلاگین خطرناک
[ ] تلاش Restore یا Update بدون Permission
[ ] checksum mismatch در Migration
```

---

## Error Handling Security Checklist

برای خطاها:

```text
[ ] Stack Trace به کاربر نمایش داده نمی‌شود.
[ ] پیام SQL خام به کاربر نمایش داده نمی‌شود.
[ ] خطای فنی در Error Log ثبت می‌شود.
[ ] داده حساس در Error Log ذخیره نمی‌شود.
[ ] خطای مالی باعث Rollback می‌شود.
[ ] خطای پرداخت نادیده گرفته نمی‌شود.
[ ] پیام کاربر ساده و فارسی است.
```

ممنوع:

```php
die($exception->getMessage());
```

---

## UI Security Checklist

برای UI:

```text
[ ] خروجی‌های متنی Escape می‌شوند.
[ ] پیام‌های خطا فنی و خام نیستند.
[ ] مسیر واقعی فایل نمایش داده نمی‌شود.
[ ] Secret نمایش داده نمی‌شود.
[ ] فرم‌های حساس CSRF دارند.
[ ] دکمه مخفی‌شده جای Permission Backend را نمی‌گیرد.
[ ] UI محاسبه مالی قطعی انجام نمی‌دهد.
[ ] داده خارج از Scope در صفحه نمایش داده نمی‌شود.
```

---

## PWA / Electron Security Checklist

برای PWA و Electron:

```text
[ ] فایل‌های حساس Cache نمی‌شوند.
[ ] Responseهای Private در Service Worker ذخیره نمی‌شوند.
[ ] Token حساس در LocalStorage ذخیره نمی‌شود.
[ ] Electron Permission Backend را دور نمی‌زند.
[ ] Offline Mode عملیات مالی قطعی انجام نمی‌دهد.
[ ] داده حساس بدون رمزنگاری محلی ذخیره نمی‌شود.
```

---

## Migration Security Checklist

برای Migrationها:

```text
[ ] Migration idempotent است.
[ ] Migration در جدول migrations ثبت می‌شود.
[ ] Migration مخرب نیست.
[ ] اگر مخرب است Backup دارد.
[ ] Secret خام Seed نمی‌شود.
[ ] Password پیش‌فرض ناامن Seed نمی‌شود.
[ ] تغییر مالی بدون Financial Log نیست.
[ ] تغییر حقوقی بدون Audit نیست.
[ ] Index تکراری ساخته نمی‌شود.
[ ] Unique Index قبل از ساخت duplicate check دارد.
```

---

## Minimum Security Acceptance

هر Feature باید حداقل این موارد را پاس کند:

```text
[ ] Auth برای Route داخلی
[ ] Permission برای عملیات حساس
[ ] Scope برای داده وابسته به مشتری/قرارداد/پرداخت/فایل/پرونده
[ ] CSRF برای عملیات state-changing
[ ] Validation سمت سرور
[ ] Prepared Statements
[ ] Error Handling امن
[ ] Audit Log در عملیات حساس
[ ] Security Log در تلاش غیرمجاز
[ ] عدم نمایش Secret یا مسیر واقعی فایل
```

---

## Stop Conditions

Codex باید در این شرایط متوقف شود و حدس نزند:

```text
ابهام در Permission
ابهام در Scope
ابهام در اثر مالی
ابهام در وضعیت پرداخت
ابهام در Public یا Private بودن فایل
ابهام در Secret
ابهام در Migration مخرب
ابهام در Snapshot حقوقی
ابهام در دسترسی پلاگین
```

---

## قانون نهایی

اگر بین سرعت پیاده‌سازی و امنیت تعارض وجود داشت:

```text
Security wins.
Financial accuracy wins.
Legal traceability wins.
User privacy wins.
```

---

## پایان فایل
````
