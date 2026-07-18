# وضعیت نصب و ارتقای V1.3.7

## PASSED

- `installer.php` فقط launcher سازگار است و در نصب‌نشده با HTTP 302 به `install.php` می‌رود.
- schema نصب تازه و repair سازگاری installer، ستون‌های `previous_status` و `cancellation_metadata_json` چرخه عمر قرارداد را ایجاد می‌کنند.
- `/health/live` بدون session، دیتابیس و boot افزونه پاسخ JSON با 200 می‌دهد.
- `/health/ready` در نبود تنظیمات دیتابیس پاسخ کنترل‌شده 503 می‌دهد و exception یا credential افشا نمی‌کند.
- migration ترمیمی چرخه قرارداد با splitter updater به 72 statement معتبر تقسیم شد.
- نصب واقعی `install.php` روی MariaDB 10.4.32 مستقل انجام شد: 44 جدول، مدیر نخست و 56 تنظیم اولیه ایجاد شدند.
- migration چرخه عمر با executor واقعی updater اجرا و اجرای تکراری آن بدون خطا رد شد؛ وضعیت migration `success` و هفت جدول lifecycle ثبت شدند.
- snapshot واقعی سورس `V1.3.6` از commit `05fb9fd` نصب شد؛ `proma-update_v1-3-7.zip` روی آن اجرا، backup پیش از ارتقا ساخته و نسخه هسته به `1.3.7` تغییر کرد.

## BLOCKED

- rollback از backup ایزوله.

PHP CLI با فعال‌سازی صریح `ZipArchive` برای ساخت archive استفاده شد. archive کامل، update manifest و archiveهای افزونه باز و با هش فایل‌های manifest تطبیق داده شدند.
