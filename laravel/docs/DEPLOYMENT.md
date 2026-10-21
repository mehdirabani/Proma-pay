# استقرار

استقرار انجام نشده؛ این سند الگوی آماده‌سازی است و باید برای میزبان واقعی تکمیل شود.

## پیش‌انتشار

- PHP و افزونه‌ها، Composer، Node build و موتور DB را با `composer.json`, lockfile و نسخهٔ پشتیبانی‌شدهٔ Laravel تطبیق دهید. PHP 8.2 در توسعه استفاده شده؛ مسیر ارتقای production به PHP 8.3/Laravel 13 را برنامه‌ریزی کنید.
- یک MySQL/MariaDB دقیق و پشتیبانی‌شده انتخاب کنید؛ migration، charset/collation، timezone، transaction, `lockForUpdate`, unique constraint و deadlock را روی همان نسخه بیازمایید.
- secrets را از secret manager/environment بگیرید؛ `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL=https://…`, `APP_KEY`, DB, mail, queue, cache, session, filesystem, trusted proxy/host را تنظیم کنید.
- TLS termination، HTTP→HTTPS، secure/HTTP-only/SameSite cookies، firewall و least-privilege DB account تنظیم شوند.
- document root فقط `laravel/public`; اجرای PHP یا static file access به `.env`, source, vendor, database, storage/private مسدود باشد.

## انتشار

1. backup سازگار و قابل بازیابی بگیرید؛ نسخهٔ artifact و migration را ثبت کنید.
2. `composer install --no-dev --prefer-dist --no-interaction --optimize-autoloader` و `npm ci && npm run build`.
3. `php artisan migrate --force` را در گام کنترل‌شده و تنها پس از بررسی backup اجرا کنید؛ migration مخرب تا طرح expand/contract ممنوع.
4. cacheهای config/route/view را بسازید، PHP-FPM/opcache را reload کنید و workerها را restart کنید.
5. health endpoint/readiness، login، branch access، KYC, idempotent request و log redaction را smoke-check کنید.
6. مدیر اولیه را با `php artisan proma:create-admin` از ترمینال امن بسازید؛ secret را در فرمان یا transcript قرار ندهید.

## عملیات و rollback

backup رمزگذاری‌شدهٔ DB و فایل‌ها، RPO/RTO، restore drill، retention، alert خرابی job/DB، log rotation و مسئول on-call هنوز تعیین نشده‌اند. rollback release کد را به عقب می‌برد اما لزوماً schema/data را برنمی‌گرداند؛ plan هر migration باید forward-fix یا restore با دادهٔ سازگار داشته باشد. تا آماده‌شدن این موارد، انتشار به production توصیه نمی‌شود.
