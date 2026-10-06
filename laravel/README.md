# پرماپی — برنامهٔ Laravel

این پوشه نسخهٔ مستقل Laravel پرماپی است. رابط‌های فعلی فارسی و راست‌چین هستند. این برنامه به‌تدریج از Legacy جدا شده و هنوز جایگزین کامل سامانهٔ قدیمی یا آمادهٔ استقرار مالی در محیط واقعی نیست.

## نصب و اجرای محلی

نیاز است PHP 8.2 یا بالاتر با افزونه‌های `bcmath`, `ctype`, `curl`, `fileinfo`, `mbstring`, `openssl`, `pdo`, `pdo_sqlite`, `tokenizer` و `xml` نصب باشد. برای ساخت از سورس، Composer 2 و Node.js/npm سازگار با Vite 7 هم لازم‌اند. روش نصب بستهٔ آمادهٔ آزمایش در [INSTALL-FA.md](INSTALL-FA.md) آمده است.

از همین پوشه (`laravel/`) اجرا کنید:

```sh
composer install
cp .env.example .env
```

فایل SQLite را بسازید، سپس ادامه دهید:

```sh
touch database/database.sqlite
php artisan key:generate
npm ci
npm run build
php artisan migrate --seed
php artisan proma:create-admin
php artisan serve
```

در PowerShell، به‌جای `cp` از `Copy-Item .env.example .env` و به‌جای `touch` از `New-Item -ItemType File database\database.sqlite` استفاده کنید. ساخت مدیر تعاملی است و نام، ایمیل و گذرواژهٔ حداقل ۱۲ نویسه می‌خواهد؛ هیچ حساب یا گذرواژهٔ پیش‌فرضی وجود ندارد.

## قابلیت‌های این برش

ورود کارکنان، نقش و مجوز، محدودسازی دسترسی شعبه، مشتری و KYC، دفترکل و رزرو اعتبار، گردش پیش‌نویس تا فعال‌سازی قرارداد، برنامهٔ اقساط، quote و ثبت دستی پرداخت قسط، اصلاح محدود و ممیزی‌شدهٔ پرداخت، و ثبت/بازبینی کارمند-محور مدرک انتقال بانکی در فضای خصوصی وجود دارند. قابلیت مدرک انتقال در این نسخه پرتال مشتری نیست؛ کارمند آن را به نمایندگی از مشتری ثبت می‌کند.

مواردی مانند پرتال مشتری، انتقال داده از Legacy، درگاه و webhook، refund بانکی، تطبیق و تسویهٔ پذیرنده، قواعد حقوقی، اسکن بدافزار و عملیات backup/restore هنوز کامل نشده‌اند. برای جزئیات و محدودیت‌های فعلی به [گزارش تکمیل](docs/COMPLETION-REPORT-fa.md)، [نقشهٔ قابلیت‌ها](docs/FEATURE-MAP.md) و [فهرست شکاف‌های تولید](docs/PRODUCTION-GAP-MATRIX.md) مراجعه کنید. این نسخه را با دادهٔ واقعی یا برای عملیات تولیدی استفاده نکنید.

## اجرای تولیدی

برای میزبانی، ریشهٔ وب‌سرور باید دقیقاً `laravel/public` باشد؛ خود پوشهٔ برنامه یا ریشهٔ repository را منتشر نکنید. مقدارهای محیط production، TLS، پایگاه داده، backup و نظارت باید جداگانه پیکربندی شوند. موتور/نسخهٔ پایگاه دادهٔ مقصد هنوز تأیید نشده و آخرین migration رسید در این محیط فقط روی SQLite اعتبارسنجی شده است. راهنمای فعلی در [DEPLOYMENT](docs/DEPLOYMENT.md) و [SECURITY](docs/SECURITY.md) است.
