# Error Page and Exception System Audit

## وضعیت قبل از V1.3.6

- 404، CSRF، authorization و view failure مسیرهای مستقلی داشتند.
- خطاهای افزونه می‌توانستند dispatch مسیر اصلی را متوقف کنند.
- نمایش تولیدی خطا دارای قرارداد واحد برای HTML/JSON و request id نبود.

## وضعیت V1.3.6

- `core/ErrorHandler.php` تنها نقطه‌ی پاسخ خطا است.
- `core/HttpException.php` برای کدهای مورد انتظار HTTP استفاده می‌شود.
- `Router`, `Controller`, `Auth`, `Csrf` و `PluginManager` به این مسیر متصل شده‌اند.
- bootstrap افزونه و dispatch آن در catch محدود قرار دارد و خطای آن log می‌شود.
- صفحه‌ی `views/errors/system.php` وابستگی به layout، session یا DB ندارد.
- فایل‌های `static-errors/500.html` و `static-errors/503.html` در خرابی bootstrap قابل استفاده‌اند.

## حریم خصوصی

در پاسخ کاربر هیچ trace، SQL، مسیر کامل فایل یا secret نمایش داده نمی‌شود. لاگ برنامه تنها basename فایل را در کنار شناسه پیگیری ذخیره می‌کند.
