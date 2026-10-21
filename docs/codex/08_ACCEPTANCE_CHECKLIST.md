نام فایل:

`08_ACCEPTANCE_CHECKLIST.md`

پوشه هدف:

`docs/codex/`

نگارش شده محتویات فایل با زبان README.md:

````markdown
# 08 — Acceptance Checklist

چک‌لیست نهایی پذیرش خروجی‌های Codex در پروژه **Proma Pay / پروما**

---

## هدف این فایل

این فایل مشخص می‌کند هر خروجی Codex قبل از پذیرفته شدن باید چه شرایطی داشته باشد.

هدف این است که هیچ Feature، Bug Fix، Migration، Refactor یا تغییر UI بدون بررسی حداقلی وارد پروژه نشود.

این فایل باید بعد از هر Task توسط Codex بررسی شود.

---

## قانون اصلی پذیرش

قانون اصلی:

> هیچ خروجی فقط به دلیل اینکه اجرا می‌شود قابل قبول نیست؛ خروجی باید امن، قابل ردیابی، قابل تست، هماهنگ با معماری و سازگار با مستندات پروژه باشد.

---

## چک‌لیست عمومی هر Task

هر Task باید این موارد را پاس کند:

```text
[ ] Task دقیقاً مطابق درخواست انجام شده است.
[ ] تغییرات خارج از محدوده Task انجام نشده است.
[ ] ساختار پروژه بی‌دلیل تغییر نکرده است.
[ ] فایل‌های نامرتبط ویرایش نشده‌اند.
[ ] کد با PHP 7.4+ سازگار است.
[ ] پروژه با MySQL / MariaDB سازگار است.
[ ] پروژه با Shared Hosting ناسازگار نشده است.
[ ] نام فایل‌ها و کلاس‌ها استاندارد است.
[ ] کد خوانا و قابل نگهداری است.
[ ] Placeholder یا TODO مبهم وجود ندارد.
[ ] خطای فنی خام به کاربر نمایش داده نمی‌شود.
```

---

## چک‌لیست معماری

```text
[ ] Controller فقط Request و Response را مدیریت می‌کند.
[ ] منطق اصلی در Service قرار دارد.
[ ] Queryها در Repository قرار دارند.
[ ] View مستقیم به Database وصل نیست.
[ ] JavaScript منبع حقیقت مالی نیست.
[ ] Plugin مستقیم Core را دور نمی‌زند.
[ ] عملیات حساس از Service رسمی Domain عبور می‌کند.
```

وابستگی مجاز:

```text
Controller → Service
Service → Repository
Repository → Database
Service → Event / Job / Logger
View → داده آماده‌شده
```

وابستگی ممنوع:

```text
Controller → SQL مستقیم
View → Database
JavaScript → محاسبه قطعی بدهی
Plugin → تغییر مستقیم جدول‌های Core بدون API رسمی
```

---

## چک‌لیست امنیتی

هر Feature حساس باید این موارد را پاس کند:

```text
[ ] Route داخلی پشت Authentication است.
[ ] عملیات حساس Permission Check دارد.
[ ] داده وابسته به مشتری، قرارداد، پرداخت، فایل یا پرونده Scope Check دارد.
[ ] عملیات state-changing CSRF Protection دارد.
[ ] Validation سمت سرور انجام می‌شود.
[ ] ورودی‌ها مستقیم وارد SQL نمی‌شوند.
[ ] خروجی‌های UI Escape می‌شوند.
[ ] Secret، Token، API Key یا Password خام نمایش داده نمی‌شود.
[ ] مسیر واقعی فایل Private نمایش داده نمی‌شود.
[ ] تلاش غیرمجاز Security Log ایجاد می‌کند.
```

---

## چک‌لیست دیتابیس

```text
[ ] نام جدول‌ها plural و snake_case است.
[ ] نام ستون‌ها snake_case است.
[ ] Primary Key با id BIGINT UNSIGNED AUTO_INCREMENT ساخته شده است.
[ ] Foreign Keyها با پسوند _id نام‌گذاری شده‌اند.
[ ] ستون‌های پولی DECIMAL(15,2) هستند.
[ ] از FLOAT یا DOUBLE برای مبلغ استفاده نشده است.
[ ] ستون‌های تاریخ و زمان طبق *_date و *_at نام‌گذاری شده‌اند.
[ ] Booleanها با is_ / has_ / can_ / should_ / requires_ شروع می‌شوند.
[ ] جدول‌های اصلی Soft Delete دارند، اگر لازم است.
[ ] Queryهای Soft Delete شرط deleted_at IS NULL دارند.
[ ] شماره‌های رسمی Unique Index دارند.
[ ] Indexهای ضروری ساخته شده‌اند.
[ ] Indexهای اضافی و بی‌دلیل ساخته نشده‌اند.
```

---

## چک‌لیست Query

```text
[ ] همه Queryها با PDO Prepared Statements نوشته شده‌اند.
[ ] ورودی کاربر مستقیم داخل SQL نیست.
[ ] Sort column از whitelist می‌آید.
[ ] Filterها کنترل‌شده و whitelist شده‌اند.
[ ] Queryهای لیستی LIMIT دارند.
[ ] جدول‌های حجیم Pagination دارند.
[ ] SELECT * در لیست‌های بزرگ استفاده نشده است.
[ ] Queryهای گزارش بازه زمانی یا محدودیت مناسب دارند.
[ ] Queryهای حساس Scope را رعایت می‌کنند.
[ ] Queryهای پرتکرار با Indexهای مستند هماهنگ هستند.
```

---

## چک‌لیست Migration

اگر Task شامل Migration است:

```text
[ ] نام Migration استاندارد و یکتا است.
[ ] Migration در جدول migrations ثبت می‌شود.
[ ] Migration idempotent است.
[ ] قبل از ساخت جدول، وجود جدول بررسی می‌شود.
[ ] قبل از افزودن ستون، وجود ستون بررسی می‌شود.
[ ] قبل از افزودن Index، وجود Index بررسی می‌شود.
[ ] Migration مخرب نیست.
[ ] اگر Migration مخرب یا حساس است، Backup لازم مشخص شده است.
[ ] Seedها idempotent هستند.
[ ] Secret خام داخل Seed یا Migration نیست.
[ ] Rollback فقط اگر امن است تعریف شده است.
[ ] Migration با PHP 7.4+ و MySQL/MariaDB سازگار است.
```

Migrationهای حساس:

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

## چک‌لیست مالی

اگر Task اثر مالی دارد:

```text
[ ] محاسبات مالی سمت سرور انجام می‌شود.
[ ] Frontend فقط نمایش یا کمک UI انجام می‌دهد.
[ ] مبلغ‌ها DECIMAL هستند.
[ ] عملیات مالی داخل Transaction انجام می‌شود.
[ ] خطا باعث Rollback می‌شود.
[ ] فقط Payment approved روی بدهی اثر می‌گذارد.
[ ] Payment pending_review اثر مالی ندارد.
[ ] Payment rejected اثر مالی ندارد.
[ ] Payment failed اثر مالی ندارد.
[ ] Financial Log ثبت می‌شود.
[ ] Audit Log ثبت می‌شود.
[ ] اصلاح دستی مالی reason دارد.
[ ] اصلاح دستی مالی Permission دارد.
```

قانون قطعی:

```text
هیچ تغییر مالی بدون Financial Log قابل قبول نیست.
```

---

## چک‌لیست پرداخت

اگر Task مربوط به Payment است:

```text
[ ] Payment statusها کنترل‌شده هستند.
[ ] رسید کارت‌به‌کارت ابتدا pending_review است.
[ ] رسید بدون بررسی approved نمی‌شود.
[ ] Gateway callback به‌تنهایی پرداخت را قطعی نمی‌کند.
[ ] Gateway payment باید Verify شود.
[ ] مبلغ پرداخت با مبلغ مورد انتظار تطبیق داده می‌شود.
[ ] Approval داخل Transaction است.
[ ] Rejection دلیل دارد.
[ ] Payment Status History ثبت می‌شود.
[ ] Payment approved بدهی را درست تغییر می‌دهد.
[ ] Payment غیر approved بدهی را تغییر نمی‌دهد.
[ ] Financial Log برای approved ثبت می‌شود.
[ ] Audit Log برای approve/reject ثبت می‌شود.
```

---

## چک‌لیست قرارداد

اگر Task مربوط به Contract است:

```text
[ ] قرارداد به مشتری معتبر وصل است.
[ ] Contract Number رسمی و Unique تولید می‌شود.
[ ] ساخت قرارداد داخل Transaction است.
[ ] اقساط همراه قرارداد ساخته می‌شوند.
[ ] مبلغ قرارداد سمت سرور محاسبه یا Validate می‌شود.
[ ] جمع اقساط با قرارداد سازگار است.
[ ] تغییر مبلغ قرارداد رسمی Audit دارد.
[ ] تغییر مالی قرارداد Financial Log دارد.
[ ] قرارداد حذف فیزیکی نمی‌شود.
[ ] مشاهده و تغییر قرارداد Scope Check دارد.
```

---

## چک‌لیست اقساط

اگر Task مربوط به Installments است:

```text
[ ] هر قسط به قرارداد معتبر وصل است.
[ ] هر قسط due_date معتبر دارد.
[ ] مبلغ قسط DECIMAL است.
[ ] paid_amount فقط با Payment approved تغییر می‌کند.
[ ] remaining_amount سمت سرور محاسبه یا کنترل می‌شود.
[ ] وضعیت قسط whitelist دارد.
[ ] تغییر وضعیت قسط History دارد.
[ ] تشخیص معوقه سمت سرور انجام می‌شود.
[ ] تغییر دستی مبلغ قسط Audit و Financial Log دارد.
```

---

## چک‌لیست مشتری

اگر Task مربوط به Customers است:

```text
[ ] اطلاعات مشتری Validation دارد.
[ ] شماره موبایل و کد ملی فرمت معتبر دارند.
[ ] Customer Number رسمی و Unique تولید می‌شود.
[ ] اطلاعات هویتی حساس بدون Permission نمایش داده نمی‌شود.
[ ] مشاهده مشتری Scope Check دارد.
[ ] تغییر اطلاعات حساس Audit Log دارد.
[ ] حذف مشتری Soft Delete است.
[ ] جستجوی مشتری Query امن و محدود دارد.
```

---

## چک‌لیست حقوقی

اگر Task مربوط به Legal Domain است:

```text
[ ] پرونده حقوقی از Snapshot مالی معتبر ساخته می‌شود.
[ ] Legal Case Number رسمی و Unique تولید می‌شود.
[ ] دسترسی به پرونده Permission دارد.
[ ] دسترسی به پرونده Scope دارد.
[ ] تغییر وضعیت پرونده History دارد.
[ ] مدارک حقوقی Private Storage دارند.
[ ] claim_amount قابل ردیابی است.
[ ] Legal Referral Audit Log دارد.
[ ] فایل‌های حقوقی با Secure Download دریافت می‌شوند.
```

قانون قطعی:

```text
پرونده حقوقی بدون Snapshot مالی معتبر قابل قبول نیست.
```

---

## چک‌لیست فایل‌ها

اگر Task شامل Upload، Download یا File Metadata است:

```text
[ ] فایل واقعی داخل دیتابیس ذخیره نشده است.
[ ] فایل حساس داخل public ذخیره نشده است.
[ ] فایل حساس در Private Storage قرار دارد.
[ ] مسیر واقعی فایل به کاربر نمایش داده نمی‌شود.
[ ] Metadata فایل در DB ثبت می‌شود.
[ ] نوع فایل Validate می‌شود.
[ ] حجم فایل Validate می‌شود.
[ ] فایل blocked یا quarantined دانلود نمی‌شود.
[ ] دانلود فایل حساس Permission Check دارد.
[ ] دانلود فایل حساس Scope Check دارد.
[ ] دانلود فایل حساس File Access Log دارد.
[ ] Download Token خام ذخیره نمی‌شود.
[ ] Download Token زمان انقضا دارد.
```

---

## چک‌لیست گزارش و Export

اگر Task مربوط به Reports یا Export است:

```text
[ ] گزارش Permission Check دارد.
[ ] گزارش Scope Check دارد.
[ ] Query گزارش محدود یا دارای بازه زمانی است.
[ ] گزارش بزرگ به Job منتقل شده است.
[ ] Export حساس در Private Storage ذخیره می‌شود.
[ ] Export Expiration دارد.
[ ] دانلود Export Permission دارد.
[ ] دانلود Export File Access Log دارد.
[ ] Export حساس Audit Log دارد.
[ ] داده خارج از Scope در خروجی نیست.
```

---

## چک‌لیست Settings و Secretها

اگر Task مربوط به تنظیمات است:

```text
[ ] Secret خام در settings ذخیره نشده است.
[ ] Secret خام در logs ثبت نشده است.
[ ] Secret خام در Response نمایش داده نشده است.
[ ] مقدار حساس در UI Mask شده است.
[ ] تغییر تنظیمات حساس Audit Log دارد.
[ ] تلاش مشاهده یا تغییر غیرمجاز Security Log دارد.
[ ] Settingهای حساس فقط برای نقش مجاز قابل تغییر هستند.
```

Secretها شامل:

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

## چک‌لیست Backup / Restore / Update

اگر Task مربوط به Backup، Restore یا Update است:

```text
[ ] Backup در Private Storage ذخیره می‌شود.
[ ] Backup Metadata در DB ثبت می‌شود.
[ ] دانلود Backup Permission ویژه دارد.
[ ] Restore بدون Permission رد می‌شود.
[ ] Restore reason دارد.
[ ] Restore واقعی pre_restore_backup دارد.
[ ] Update حساس pre_update_backup دارد.
[ ] مراحل Update یا Restore Log می‌شوند.
[ ] Restore و Update Audit Log دارند.
[ ] تلاش غیرمجاز Security Log دارد.
[ ] Maintenance Mode در عملیات حساس بررسی شده است.
```

---

## چک‌لیست پلاگین

اگر Task مربوط به Plugins است:

```text
[ ] Plugin Package در Private Storage ذخیره می‌شود.
[ ] Plugin Security Scan اجرا می‌شود.
[ ] مسیرهای خطرناک مثل ../ رد می‌شوند.
[ ] فایل .env داخل پلاگین Block می‌شود.
[ ] فایل‌های خطرناک Block می‌شوند.
[ ] Plugin Permission namespace دارد.
[ ] Plugin Routeها Auth دارند.
[ ] Plugin Routeهای state-changing CSRF دارند.
[ ] Plugin Routeها Permission دارند.
[ ] Plugin Migration ثبت می‌شود.
[ ] Plugin Secretها در Secure Settings هستند.
[ ] نصب یا فعال‌سازی پلاگین Audit Log دارد.
```

---

## چک‌لیست UI و RTL

```text
[ ] صفحه راست‌به‌چپ است.
[ ] متن‌های کاربری فارسی هستند.
[ ] فونت YekanBakh از assets/fonts استفاده می‌شود، اگر UI درگیر است.
[ ] پیام خطای خام فنی نمایش داده نمی‌شود.
[ ] Stack Trace نمایش داده نمی‌شود.
[ ] مسیر واقعی فایل نمایش داده نمی‌شود.
[ ] Secret نمایش داده نمی‌شود.
[ ] فرم‌های حساس CSRF دارند.
[ ] UI محاسبه مالی قطعی انجام نمی‌دهد.
[ ] Layout در موبایل خراب نمی‌شود.
```

---

## چک‌لیست Performance

```text
[ ] لیست‌های بزرگ LIMIT دارند.
[ ] جدول‌های حجیم Pagination دارند.
[ ] گزارش‌های سنگین Job می‌شوند.
[ ] Export بزرگ Chunk یا Job دارد.
[ ] Dashboard Query سنگین بدون Cache ندارد.
[ ] Queryهای مهم Index مناسب دارند.
[ ] SELECT * در لیست‌های بزرگ نیست.
[ ] COUNT بدون فیلتر روی جدول بزرگ نیست.
[ ] Logهای حجیم قابلیت Archive یا Retention دارند.
[ ] کد با Shared Hosting ناسازگار نشده است.
```

---

## چک‌لیست Error Handling

```text
[ ] خطاهای کاربر پیام ساده و فارسی دارند.
[ ] خطاهای فنی Log می‌شوند.
[ ] خطاهای فنی خام در UI نیستند.
[ ] Stack Trace نمایش داده نمی‌شود.
[ ] Secret در Error Log ذخیره نمی‌شود.
[ ] خطای عملیات مالی باعث Rollback می‌شود.
[ ] خطای پرداخت نادیده گرفته نمی‌شود.
[ ] خطای فایل، مسیر واقعی فایل را فاش نمی‌کند.
```

---

## چک‌لیست Logها

```text
[ ] Audit Log برای عملیات حساس ثبت می‌شود.
[ ] Security Log برای تلاش غیرمجاز ثبت می‌شود.
[ ] Financial Log برای اثر مالی قطعی ثبت می‌شود.
[ ] Activity Log برای Timeline در صورت نیاز ثبت می‌شود.
[ ] Error Log برای خطای فنی ثبت می‌شود.
[ ] Job Log برای Jobهای مهم ثبت می‌شود.
[ ] هیچ Log شامل Password، Token، API Key یا Secret خام نیست.
[ ] هیچ Log شامل مسیر واقعی فایل Private نیست.
```

---

## چک‌لیست تست حداقلی

برای هر Feature:

```text
[ ] ورودی معتبر تست شده است.
[ ] ورودی نامعتبر تست شده است.
[ ] دسترسی بدون Login رد می‌شود.
[ ] دسترسی بدون Permission رد می‌شود.
[ ] دسترسی خارج از Scope رد می‌شود.
[ ] CSRF نامعتبر رد می‌شود.
[ ] عملیات موفق نتیجه درست دارد.
[ ] عملیات شکست‌خورده داده را خراب نمی‌کند.
[ ] Audit Log در عملیات حساس ثبت می‌شود.
[ ] Security Log در تلاش غیرمجاز ثبت می‌شود.
```

برای Feature مالی:

```text
[ ] Payment pending اثر مالی ندارد.
[ ] Payment approved اثر مالی دارد.
[ ] Payment rejected اثر مالی ندارد.
[ ] Transaction rollback درست کار می‌کند.
[ ] Financial Log ثبت می‌شود.
```

برای Feature فایل:

```text
[ ] فایل حساس Private است.
[ ] دسترسی مستقیم Public ممکن نیست.
[ ] دانلود بدون Permission رد می‌شود.
[ ] دانلود مجاز Access Log دارد.
[ ] Token نامعتبر رد می‌شود.
```

---

## چک‌لیست نهایی قبل از تحویل Codex

قبل از اعلام پایان Task:

```text
[ ] خلاصه تغییرات آماده شده است.
[ ] فایل‌های تغییرکرده مشخص هستند.
[ ] Migrationهای جدید مشخص هستند.
[ ] اثر امنیتی توضیح داده شده است.
[ ] اثر دیتابیس توضیح داده شده است.
[ ] اثر مالی، اگر وجود دارد، توضیح داده شده است.
[ ] تست‌های انجام‌شده یا قابل انجام مشخص شده‌اند.
[ ] محدودیت‌ها یا ابهام‌ها صادقانه ذکر شده‌اند.
[ ] هیچ تغییر خارج از محدوده انجام نشده است.
```

---

## شرایط رد خروجی

خروجی Codex باید رد شود اگر:

```text
SQL خام با ورودی کاربر دارد.
مبلغ را FLOAT یا DOUBLE ذخیره می‌کند.
پرداخت pending را روی بدهی اثر می‌دهد.
Feature مالی Transaction ندارد.
تغییر مالی Financial Log ندارد.
Route حساس Auth ندارد.
عملیات حساس Permission ندارد.
داده مشتری/قرارداد/پرداخت Scope ندارد.
فرم حساس CSRF ندارد.
فایل حساس در public ذخیره شده است.
Secret خام در DB، Log یا Response وجود دارد.
Migration مخرب بدون Backup دارد.
پرونده حقوقی بدون Snapshot ساخته می‌شود.
Export حساس در public ذخیره می‌شود.
ساختار پروژه بی‌دلیل تغییر کرده است.
```

---

## Definition of Done

یک Task زمانی Done است که:

```text
در محدوده درخواست انجام شده باشد.
با مستندات مرتبط هماهنگ باشد.
با معماری پروژه هماهنگ باشد.
امنیت پایه را رعایت کرده باشد.
Queryهای امن داشته باشد.
Validation سمت سرور داشته باشد.
Permission و Scope را رعایت کرده باشد.
CSRF را در عملیات تغییر وضعیت رعایت کرده باشد.
Logهای لازم را ثبت کرده باشد.
در صورت اثر مالی، Transaction و Financial Log داشته باشد.
در صورت فایل حساس، Private Storage و Secure Download داشته باشد.
در صورت Migration، idempotent و امن باشد.
با PHP 7.4+ و MySQL/MariaDB سازگار باشد.
با Shared Hosting ناسازگار نباشد.
خروجی قابل تست و قابل بررسی باشد.
```

---

## قانون نهایی

قانون نهایی پذیرش:

> Done یعنی فقط کار نمی‌کند؛ درست، امن، قابل ردیابی، قابل تست و هماهنگ با کل پروژه کار می‌کند.

---

## پایان فایل
````
