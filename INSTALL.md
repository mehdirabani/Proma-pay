# راهنمای نصب Proma Pay

1. فایل ZIP را در مسیر وب‌سرور extract کنید.
2. برای XAMPP مسیر پیشنهادی `C:/xampp/htdocs/pay` است.
3. یک دیتابیس خالی با charset `utf8mb4` بسازید.
4. برای انتقال دیتای بسته، مسیر `http://localhost/pay/installer.php` را باز کنید.
5. مشخصات دیتابیس را وارد کنید. installer فایل `database/proma-pay-install.sql` را import می‌کند و migrationها را اجرا می‌کند.
6. اگر نصب تازه بدون دیتای انتقالی می‌خواهید، به جای installer از `http://localhost/pay/install.php` استفاده کنید.
7. بعد از نصب، فایل `installed.lock` ساخته می‌شود و ورود از مسیر `http://localhost/pay` انجام می‌شود.
8. پس از تست ورود، برای امنیت فایل `installer.php` و `database/proma-pay-install.sql` را از هاست حذف کنید.
9. برای SSL، نشانی پایه بازگشت درگاه پرداخت را با `https://` دامنه اصلی تنظیم کنید.
10. برای فعال شدن فراموشی رمز، در پنل مدیر به تنظیمات > پنل پیامکی بروید و کلید API، شماره فرستنده IPPanel و در صورت نیاز کد پترن را ثبت کنید.
