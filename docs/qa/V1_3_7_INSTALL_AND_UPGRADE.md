# وضعیت نصب و ارتقای V1.3.7-RC.1

## PASSED

- `installer.php` فقط launcher سازگار است و در نصب‌نشده با HTTP 302 به `install.php` می‌رود.
- schema نصب تازه و repair سازگاری installer، ستون‌های `previous_status` و `cancellation_metadata_json` چرخه عمر قرارداد را ایجاد می‌کنند.
- `/health/live` بدون session، دیتابیس و boot افزونه پاسخ JSON با 200 می‌دهد.
- `/health/ready` در نبود تنظیمات دیتابیس پاسخ کنترل‌شده 503 می‌دهد و exception یا credential افشا نمی‌کند.
- migration ترمیمی چرخه قرارداد با splitter updater به 72 statement معتبر تقسیم شد.

## BLOCKED

- نصب تازه با MySQL/MariaDB مستقل.
- ارتقا از snapshot واقعی V1.3.6 و اجرای migrationهای lifecycle.
- rollback از backup ایزوله.
- ساخت archive: PHP CLI محلی افزونه `ZipArchive` ندارد.

سازنده archive برای جلوگیری از نام‌گذاری اشتباه عمداً نامزد V1.3.7 را تا زمان ارتقای نسخه و قبولی staging رد می‌کند.
