# کشف و نصب مجدد پلاگین

چرخه canonical پلاگین‌ها شامل `discovered`، `uploaded`، `installed`، `active`، `inactive`، `failed`، `update_available` و `removed` است. کلاس `PluginStatus` مرجع واحد نام‌ها، برچسب‌ها و قابلیت نصب است.

`PluginManager::discover()` پوشه‌های سطح اول `plugins/` را بدون اجرای bootstrap بررسی می‌کند. manifest معتبر با registry همگام می‌شود و مسیر نمایشی فقط به‌شکل `plugins/PluginDirectory` در جدول عادی نشان داده می‌شود. مسیر مطلق فقط داخل بخش بسته «اطلاعات فنی» مدیر قرار دارد.

قواعد اصلی reconciliation:

- پوشه معتبر بدون رکورد: `discovered`
- رکورد `removed` با پوشه معتبر: `discovered`
- ZIP معتبر برای رکورد حذف‌شده: `uploaded`
- manifest تعمیرشده پس از خطا: `discovered`
- رکورد نصب‌شده با فایل مفقود: `failed`
- رکورد حذف‌شده بدون فایل: `removed`

بارگذاری جایگزین برای پلاگین فعال، نصب‌شده یا غیرفعال مسدود است و باید از مسیر بروزرسانی انجام شود. برای وضعیت حذف‌شده یا خراب، پوشه قبلی ابتدا به نام موقت منتقل می‌شود، ZIP جدید اعتبارسنجی و منتقل می‌شود و سپس registry با upsert امن به `uploaded` می‌رود.

migration موفق بر اساس `system_plugin_migrations` دوباره اجرا نمی‌شود. حذف عادی داده‌های مالی پلاگین را نگه می‌دارد و حذف کامل نیازمند تأیید جداگانه است.
