# راهنمای توسعه پلاگین

هر پلاگین باید در `plugins/<PluginDirectory>/` قرار بگیرد و `plugin.json` معتبر داشته باشد. provider فقط از مسیر اعتبارسنجی‌شده‌ی همان پلاگین بارگذاری می‌شود و نباید فایل‌های هسته را تغییر دهد.

## قواعد

- routeها با پیشوند اختصاصی پلاگین ثبت می‌شوند.
- migrationها داخل `migrations/` خود پلاگین هستند.
- تمام عملیات تغییر‌دهنده‌ی داده باید POST، CSRF، permission، validation و Audit داشته باشد.
- payload رویدادها نباید رمز عبور، token، secret یا اطلاعات کامل خصوصی را حمل کنند.
- غیرفعال‌سازی داده‌ها را حذف نمی‌کند و uninstall معمولی نیز داده‌های مالی را purge نمی‌کند.
- provider برای عملیات مالی بحرانی باید listener را با `critical=true` و در همان transaction ثبت کند.

## چرخه توسعه

1. manifest و permissionها را اضافه کنید.
2. migration idempotent و rollback مستند بنویسید.
3. provider و routeهای namespaced را اضافه کنید.
4. تست discovery، rejection، install، activate و deactivate را اجرا کنید.
5. بسته را فقط از پوشه‌ی پلاگین zip کنید.
