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
- افزونه‌های اختیاری: zip, gd
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

نسخه‌های سامانه با قالب `vMAJOR.MINOR.PATCH` نمایش داده می‌شوند؛ برای نمونه `v1.0.26`. با رسیدن بخش PATCH به ۹، بخش MINOR یک واحد افزایش پیدا می‌کند و PATCH صفر می‌شود. پس از `v1.9.9`، نسخه بعدی `v2.0.0` است. نام بسته کامل با جداکننده خط تیره تولید می‌شود؛ مانند `proma-pay_v1-0-26.zip`.
