# راه‌اندازی بستهٔ آزمایشی پرماپی

این بسته برای اجرای محلی و ارزیابی است. کد PHP، وابستگی‌های نصب‌شدهٔ Composer و assetهای ساخته‌شدهٔ رابط را دارد؛ برای اجرای اولیه به Composer یا Node.js نیازی نیست. فایل تنظیمات محیط (`.env`) و پایگاه‌داده در بسته نیستند و باید روی دستگاه خودتان ساخته شوند.

## پیش‌نیازها

- PHP 8.2 یا بالاتر با افزونه‌های `bcmath`, `ctype`, `curl`, `fileinfo`, `mbstring`, `openssl`, `pdo_sqlite`, `tokenizer` و `xml` فعال.
- Windows PowerShell یا ترمینال Linux/macOS.
- حدود ۲۵۰ مگابایت فضای آزاد برای استخراج برنامه، vendor و پایگاه‌دادهٔ آزمایشی.

## Windows

ZIP را در پوشه‌ای استخراج کنید، سپس PowerShell را در پوشهٔ برنامه باز کنید؛ پوشه‌ای که فایل‌های `artisan` و `.env.example` در آن هستند. اگر PHP در `PATH` نیست، مسیر کامل `php.exe` را جایگزین `php` کنید.

```powershell
Copy-Item .env.example .env
if (-not (Test-Path database\database.sqlite)) { New-Item -ItemType File database\database.sqlite | Out-Null }
php artisan key:generate
php artisan migrate --seed
php artisan proma:create-admin
php artisan serve
```

فرمان ساخت مدیر تعاملی است: نام، ایمیل، گذرواژهٔ حداقل ۱۲ نویسه و تکرار گذرواژه را وارد کنید. سپس نشانی‌ای را که `artisan serve` نشان می‌دهد در مرورگر باز کنید؛ معمولاً `http://127.0.0.1:8000` است.

## Linux یا macOS

```sh
cp .env.example .env
touch database/database.sqlite
php artisan key:generate
php artisan migrate --seed
php artisan proma:create-admin
php artisan serve
```

دستور مدیر، همان اطلاعات تعاملی بالا را می‌گیرد. پوشه‌های `storage` و `bootstrap/cache` باید برای کاربر PHP قابل‌نوشتن باشند.

## نکات آزمایش

- `npm run build` از قبل اجرا شده و فایل‌های `public/build` در ZIP قرار دارند.
- پایگاه‌دادهٔ SQLite پس از استخراج خالی است؛ Seeder فقط نقش‌ها و داده‌های پایه را آماده می‌کند. مدیر را خودتان بسازید.
- قواعد مالی عمداً مقدار پیش‌فرض ندارند. برای آزمایش قرارداد و تسویه، فقط مقادیر آزمایشی مشخص و غیرواقعی را از بخش تنظیمات مالی وارد کنید.
- برای ساخت دوبارهٔ پایگاه‌دادهٔ محلی و حذف تمام داده‌های آزمایشی، از `php artisan migrate:fresh --seed` استفاده کنید.
- سند انتقال بانکی در فضای خصوصی `storage/app/private/payment-receipts` ذخیره می‌شود. این پوشه را مستقیماً از وب منتشر نکنید و دسترسی نوشتن PHP به `storage` را نگه دارید.
- از این بسته برای دادهٔ واقعی یا میزبانی عمومی استفاده نکنید. نسخهٔ مقصد دیتابیس، کنترل عملیاتی، backup/restore، درگاه، refund، تطبیق مالی و اسکن بدافزار هنوز تعیین یا تکمیل نشده‌اند. وضعیت دقیق در `docs/PRODUCTION-GAP-MATRIX.md` ثبت شده است.
