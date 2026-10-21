# گزارش آزمون V1.5.1

## دامنه اصلاح

- اجرای migration تسویه روی ساختار V1.4.9 و اجرای دوبارهٔ migration ناموفق V1.5.0.
- پرداخت باقی‌ماندهٔ قسطی که سابقه پرداخت جزئی دارد.
- تراز دکمه‌های جدول گزارش پرداخت در RTL.

## معیار پذیرش

- تعریف `settlement_quotes` دقیقاً یک `PRIMARY KEY` دارد و MariaDB آن را بدون خطای 1068 ایجاد می‌کند.
- migration شکست‌خورده با وضعیت `failed` در جدول `migrations` دوباره اجرا و به `success` تبدیل می‌شود.
- آزمون موتور تسویه، پرداخت گروهی، پرداخت تکی و کنترل هم‌زمانی بدون خطا اجرا می‌شوند.
- سلول جدول پرداخت layout بومی `td` خود را نگه می‌دارد و wrapper داخلی عملیات را flex می‌کند.

## اجرا و نتیجه

- `tests/integration_settlement_migration_v151.php` روی MariaDB 10.4 اجرا شد. ابتدا SQL استخراج‌شده از migration در یک جدول موقت اجرا و تعداد کلیدهای `PRIMARY` از `information_schema` کنترل شد. نتیجه: یک کلید اصلی و بدون خطای SQLSTATE 1068.
- همان آزمون، رکورد migration را با وضعیت `failed` و متن خطای واقعی `Multiple primary key defined` ثبت کرد. سپس متد واقعی `ScriptUpdateService::runUpdateMigration` فراخوانی شد؛ وضعیت نهایی در جدول `migrations` برابر `success` بود. این سناریو معادل نصب V1.5.1 روی میزبان دارای نصب ناقص V1.5.0 است.
- `tests/installment_settlement_engine_v150.php`، `tests/integration_installment_settlement_v150.php` و `tests/integration_installment_settlement_concurrency_v150.php` همگی پاس شدند؛ بنابراین پرداخت باقی‌مانده پس از پرداخت جزئی، تخصیص قطعی و جلوگیری از ثبت دوباره حفظ شده‌اند.
- گیت انتشار محلی شامل lint تمام فایل‌های PHP، آزمون‌های static/unit، مسیرهای HTTP همه نقش‌ها، محافظ درخواست‌ها و افزونه حسابداری با موفقیت کامل شد.
