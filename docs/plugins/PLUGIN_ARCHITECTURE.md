# معماری افزونه Proma Pay

افزونه‌ها در پوشه‌ی ریشه‌ی `plugins/` قرار می‌گیرند و هر افزونه باید `plugin.json` معتبر داشته باشد. هسته فقط Manifestهای معتبر و افزونه‌های ثبت‌شده را می‌شناسد و منطق حسابداری یا دامنه‌های تجاری افزونه را داخل `models/` و `controllers/` هسته قرار نمی‌دهد.

چرخه‌ی فعال افزونه در هر درخواست از این مسیر عبور می‌کند:

1. کشف پوشه و اعتبارسنجی Manifest.
2. بررسی رجیستری `system_plugins`.
3. بارگذاری provider فقط برای افزونه‌ی active.
4. ثبت route، menu و listener از طریق `PluginManager`.
5. اجرای route با احراز هویت و permission سمت سرور.

Provider باید `PluginServiceProviderInterface` را پیاده کند. غیرفعال‌سازی داده‌ها و migration history را حذف نمی‌کند.
