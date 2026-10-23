# Proma Pay

سامانه فارسی RTL برای مدیریت قرارداد، اقساط، پرداخت‌ها، مشتریان، پرونده‌های حقوقی و گفت‌وگوی داخلی.

## راه‌اندازی سریع

1. فایل‌ها را در مسیر وب‌سرور قرار دهید؛ برای XAMPP مسیر پیشنهادی `C:/xampp/htdocs/pay` است.
2. دیتابیس خالی MySQL/MariaDB بسازید.
3. برای بسته انتقالی، آدرس `http://localhost/pay/installer.php` را باز کنید تا دیتابیس همراه بسته import شود.
4. اگر نصب کاملاً تازه و بدون دیتای انتقالی می‌خواهید، آدرس `http://localhost/pay/install.php` را باز کنید.
5. برای درگاه پرداخت، در تنظیمات فقط آدرس پایه سایت را وارد کنید؛ سامانه مسیر `index.php?route=payments/callback` را خودش اضافه می‌کند.

## نیازمندی‌ها

- PHP >= 8.1
- Recommended PHP: 8.2+
- Tested up to: PHP 8.4
- MySQL >= 5.7 یا MariaDB >= 10.3
- افزونه‌های PHP: PDO, pdo_mysql, mbstring, openssl, json, fileinfo, curl
- افزونه اختیاری: gd
- Apache mod_rewrite برای مسیرهای تمیزتر پیشنهاد می‌شود.

## نکته امنیتی

فایل `config/database.php` و `installed.lock` مخصوص هر نصب هستند و نباید در نسخه انتشار یا فایل ZIP مارکت قرار بگیرند. فایل نمونه در `config/database.sample.php` قرار دارد.

بعد از نصب بسته انتقالی روی هاست، فایل `installer.php` و فایل `database/proma-pay-install.sql` را حذف کنید یا خارج از دسترس وب قرار دهید.

## نکته SSL و دامنه

برنامه لینک‌های داخلی و assetها را نسبی می‌سازد تا روی HTTP/HTTPS و پشت Cloudflare یا reverse proxy خطای mixed content ایجاد نشود. اگر درگاه پرداخت فعال است، در تنظیمات > درگاه پرداخت، مقدار «نشانی پایه بازگشت» را با `https://` دامنه اصلی وارد کنید.

اگر مرورگر خطای `ERR_CONNECTION_TIMED_OUT` نشان می‌دهد، این معمولاً مشکل کد PHP یا گواهی SSL نیست و باید DNS، اتصال هاست، firewall، وضعیت وب‌سرور و تنظیمات SSL/CDN هاست بررسی شود.

## اطلاعات توسعه‌دهنده

- نام توسعه‌دهنده: مهدی ربانی
- ایمیل: pgm.mehdirabani@gmail.com
- گیت‌هاب: https://github.com/mehdirabani/

## اعلان خودکار رویدادهای تقویم

برای ارسال خودکار یادآوری رویدادهای تقویم، یکی از روش‌های زیر را هر ۵ یا ۱۵ دقیقه یک‌بار در Cron Job هاست اجرا کنید:

```bash
php /path/to/project/cron/calendar_reminders.php
```

یا از route امن وب استفاده کنید. مقدار `SECRET_TOKEN` همان «توکن امن Cron» در بخش تنظیمات > اعلان‌های تقویم است:

```text
https://example.com/index.php?route=cron/calendar-reminders&token=SECRET_TOKEN
```

## پنل پیامکی و بازیابی رمز

برای بازیابی رمز عبور، از تنظیمات > پنل پیامکی، کلید API و شماره فرستنده IPPanel را وارد کنید. سامانه از Edge API با آدرس `https://edge.ippanel.com/v1/api/send` استفاده می‌کند. اگر کد پترن بازیابی رمز وارد شود پیام با روش `pattern` ارسال می‌شود؛ در غیر این صورت روش `webservice` استفاده می‌شود.

## نسخه‌گذاری

نسخه‌های سامانه با قالب `vMAJOR.MINOR.PATCH` نمایش داده می‌شوند؛ برای نمونه `v7.0.3`. نام فایل ZIP با جداکننده خط تیره تولید می‌شود؛ مانند `proma-pay_v7-0-3.zip`.

## نسخه و وضعیت انتشار

سورس هسته در این شاخه نسخهٔ `7.0.4` است. مقدار نسخه در `config/version.php`، `manifest.json` و `package.json` نگه‌داری می‌شود. کانال فعلی در `config/version.php` برابر `candidate` است؛ بنابراین انتشار GitHub نیز باید به‌صورت Pre-release شناخته شود. گیت کامل دیتابیس و HTTP روی پایگاه QA دورریختنی برای تأیید انتشار لازم است.

برنامهٔ Proma Pay یک MVC سفارشی PHP است که از Router، مدل‌ها، سرویس‌ها و چرخهٔ افزونهٔ همین مخزن استفاده می‌کند.

## نقشهٔ سورس

| مسیر | محتوا |
|---|---|
| `app`-سطح ریشه، `controllers/`, `models/`, `core/`, `views/` | برنامهٔ اصلی، مسیریاب، کنترل دسترسی، منطق دامنه و صفحات |
| `config/`, `database/` | تنظیمات نمونه و migrationهای Core؛ تنظیمات واقعی نصب محلی است |
| `plugins/` | سورس شش افزونهٔ رسمی Proma Pay، manifest، migration، asset، تست و مستندات |
| `assets/`, `html/RTL/` | CSS، JavaScript، فونت و assetهای رابط |
| `tests/` | آزمون‌های ایستا، واحد، امنیتی و یکپارچه‌سازی |
| `docs/` | راهنماهای نصب، معماری، گردش‌کار و دامنه‌های محصول |

راهنمای هر افزونه در [docs/PLUGINS.md](docs/PLUGINS.md)، معماری در [docs/ARCHITECTURE.md](docs/ARCHITECTURE.md) و منطق مالی در [docs/FINANCIAL-MODULES.md](docs/FINANCIAL-MODULES.md) است. راهنمای اجرای نسخه دسکتاپ در [docs/desktop/quick-start.md](docs/desktop/quick-start.md) و نقشه مستندات در [docs/README.md](docs/README.md) هستند. فایل‌های `plugin.json` منبع نسخه، وابستگی‌ها، مجوزها، routeها و migrationهای هر افزونه هستند.

## توسعه و آزمون

نیازمندی‌های Core همان PHP 8.1 یا جدیدتر و MySQL 5.7 یا MariaDB 10.3 یا جدیدتر است. تنظیمات اتصال Core را از `config/database.sample.php` بردارید و در `config/database.php` مخصوص همان نصب قرار دهید؛ این فایل و `installed.lock` در Git نادیده گرفته می‌شوند. برای نصب تازه از `install.php` و برای واردکردن بستهٔ انتقالی از `installer.php` استفاده کنید. جزئیات در [INSTALL.md](INSTALL.md) آمده است.

گیت ایستا و واحد انتشار:

```bash
php tools/release-gate.php --static-only
```

گیت کامل نیازمند `PROMA_TEST_DB_DSN` با نام دقیق دیتابیس دورریختنی `proma_qa_v204`، مقدار `PROMA_TEST_ALLOW_DESTRUCTIVE=1` و `PROMA_QA_BASE_URL` روی loopback است. آن را فقط روی پایگاه آزمایش تازه و قابل حذف اجرا کنید. تست‌های وابسته به دیتابیس یا HTTP بدون این محیط نتیجهٔ موفق محسوب نمی‌شوند.

### ارتقا از نسخهٔ 7.0.3

Updater نسخهٔ 7.0.3 فیلد حذف فایل‌های بازنشسته را نمی‌شناسد. پس از نصب موفق بستهٔ Update نسخهٔ 7.0.4 از داخل پنل، فایل ZIP را در `storage/updates/` نگه دارید و از ریشهٔ نصب، با PHP CLI، فرمان زیر را اجرا کنید:

```bash
php retire-core-files.php --package=proma-update-YYYY-MM-DD-HH-MM-SS-name.zip --confirm=RETIRE-CORE-FILES
```

نام ZIP را با نام دقیق بستهٔ بارگذاری‌شده جایگزین کنید. این مرحله نسخهٔ نصب‌شده و checksum تمام فایل‌های بسته را بررسی می‌کند، یک بکاپ پایگاه‌داده می‌سازد و فقط فایل‌های allowlist‌شده با checksum منطبق را حذف می‌کند. فایل تغییریافته، symlink یا مسیر خارج از allowlist باعث توقف عملیات می‌شود. نصب تازهٔ 7.0.4 این مرحله را لازم ندارد. جداول و ردیف‌های قدیمی Ecommerce در دیتابیس دست‌نخورده می‌مانند.

پیش از ارتقا، بستهٔ Core نسخهٔ 7.0.3 را جداگانه نگه دارید؛ بکاپ خودکار Update نسخهٔ قبلی کد Core را ذخیره نمی‌کند. اگر فرمان پاک‌سازی شکست بخورد، فایل‌هایی که همان فرمان حذف کرده است از snapshot برمی‌گردند و می‌توانید پس از بررسی علت دوباره اجرا کنید. برای بازگشت کامل کد، نسخهٔ Core نگه‌داری‌شدهٔ 7.0.3 را دوباره deploy کنید و `config/database.php`، افزونه‌ها، `storage/` و `uploads/` نصب را حفظ کنید. این پاک‌سازی migration دیتابیس ندارد؛ Restore پایگاه‌داده فقط وقتی لازم است که هم‌زمان تغییر دیتابیس دیگری انجام شده باشد.

فایل `database/proma-pay-install.sql` اسکیمای نصب و داده‌های پایهٔ سامانه است؛ dump دادهٔ مشتریان نیست. بکاپ‌ها، فایل‌های `.env` واقعی، تنظیمات پایگاه، logها، cache، `storage/` و `backups/` محلی نباید به مخزن افزوده شوند.
