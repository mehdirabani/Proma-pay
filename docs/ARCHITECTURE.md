# معماری Proma Pay 7.0.3

## محدودهٔ این سند

برنامهٔ اصلی Proma Pay در ریشهٔ مخزن یک برنامهٔ PHP سفارشی است. `laravel/` پروژه‌ای جدا و در حال توسعه است؛ مسیر اجرا و چرخهٔ افزونهٔ برنامهٔ اصلی به آن وابسته نیست. مستند حاضر معماری همین برنامهٔ ریشه را توصیف می‌کند.

## مسیر درخواست

```text
مرورگر / وب‌سرور
  → index.php
  → bootstrap.php
  → core/Router.php
  → Controller و کنترل دسترسی
  → Model / سرویس دامنه
  → MySQL یا MariaDB
  → view و assetهای assets/
```

- `index.php` نقطهٔ ورود HTTP است و `bootstrap.php` بارگذاری کد، تنظیمات و مؤلفه‌های مشترک را انجام می‌دهد.
- `core/Router.php` route را انتخاب می‌کند؛ Controller ورودی را اعتبارسنجی می‌کند و عملیات دامنه را به Model یا سرویس مربوط می‌سپارد.
- `core/Auth.php`, `core/Csrf.php` و permissionهای کاربر برای نشست، درخواست‌های تغییردهنده و دسترسی به قابلیت‌ها استفاده می‌شوند.
- `core/Model.php` دسترسی PDO به دیتابیس را فراهم می‌کند. اتصال واقعی نصب در `config/database.php` است؛ نمونهٔ بدون secret در `config/database.sample.php` نگه‌داری می‌شود.
- صفحات HTML/PHP زیر `views/` و assetهای عمومی زیر `assets/` هستند. `html/RTL/` مجموعهٔ قالب و assetهای جانبی رابط است و نقطهٔ ورود منطق مالی Core محسوب نمی‌شود.

## ماژول‌ها و مرزها

| بخش | مسیر | مسئولیت |
|---|---|---|
| HTTP و نشست | `index.php`, `bootstrap.php`, `core/` | راه‌اندازی، route، احراز هویت، CSRF، خطا و permission |
| دامنهٔ اصلی | `controllers/`, `models/`, `helpers/`, `cron/` | مشتری، قرارداد، قسط، دریافت، وصول، حقوقی، فایل و گزارش |
| پایداری داده | `database/migrations/` | ساخت و تکامل schema هسته |
| افزونه‌ها | `plugins/<plugin>/` | قابلیت اختیاری با manifest، provider، route، permission و migration مستقل |
| رابط | `views/`, `assets/`, `html/RTL/` | صفحات PHP، CSS، JavaScript، فونت و asset |
| آزمون‌ها | `tests/`, `plugins/*/tests/` | آزمون‌های static، واحد، smoke، امنیتی و DB/HTTP |
| پروژهٔ موازی | `laravel/` | نمونهٔ مستقل Laravel و آزمون‌های خودش؛ خارج از بستهٔ Core |

## چرخهٔ افزونه

1. `core/PluginManager.php` فهرست `plugins/*/plugin.json` را کشف می‌کند.
2. `core/PluginManifest.php` شکل manifest و نیازمندی‌ها را می‌خواند؛ `core/PluginStatus.php` وضعیت چرخهٔ نصب را تعریف می‌کند.
3. افزونهٔ معتبر provider کلاس خود را از `core/PluginServiceProviderInterface.php` متصل می‌کند. provider route، منو، hook، listener، permission و سرویس افزونه را ثبت می‌کند.
4. route و مجوز افزونه از manifest و provider می‌آیند. Controller افزونه عملیات را انجام می‌دهد؛ دادهٔ افزونه در جدول‌های خودش و دادهٔ مرجع Core در جدول‌های اصلی باقی می‌ماند.
5. فایل‌های migration در `plugin.json` فهرست می‌شوند و Plugin Manager وضعیت اجرای آن‌ها را ثبت می‌کند. ارتقا باید migration افزایشی و سازگار با نصب موجود داشته باشد.

جزئیات و نسخهٔ هر شش افزونه در [PLUGINS.md](PLUGINS.md) آمده است. Extension API در [PLUGIN_EXTENSION_API_1_1.md](PLUGIN_EXTENSION_API_1_1.md) مستند شده است.

## پایگاه‌داده و نصب

- Core به MySQL 5.7+ یا MariaDB 10.3+ متصل می‌شود؛ نیازمندی PHP در README و `config/version.php` ثبت شده است.
- schema پایه در `database/proma-pay-install.sql` و تغییرات بعدی در `database/migrations/` قرار دارد. فایل پایهٔ نصب شامل تعریف جدول‌ها و seedهای پایه است و dump دادهٔ نصب مشتری نیست.
- migration افزونه در پوشهٔ همان افزونه قرار دارد؛ نسخه و ترتیب مورد انتظار در manifest اعلام می‌شود.
- `install.php` برای نصب تازه و `installer.php` برای بستهٔ انتقالی است. پس از راه‌اندازی، پیکربندی واقعی پایگاه و `installed.lock` مخصوص محیط‌اند و در Git ignore می‌شوند.
- داده‌های آپلودشده، backup، log، cache و runtime در `storage/` یا محل نصب نگه‌داری می‌شوند و جزو سورس نیستند.

## امنیت و مرزهای اعتماد

- مرورگر، فرم، callback و webhook ورودی غیرقابل‌اعتمادند؛ اعتبارسنجی مجوز، CSRF، وضعیت قرارداد/پرداخت و idempotency باید در مسیر سرور اعمال شود.
- افزونه می‌تواند route و permission اضافه کند اما schema و دادهٔ عملیاتی‌اش را در مرز افزونه نگه می‌دارد؛ عملیات cross-module از provider/service و API تعریف‌شده عبور می‌کند.
- کلید API و رمزهای محیط در config واقعی نصب یا تنظیمات رمزگذاری‌شده نگه‌داری می‌شوند. `.env` واقعی، `config/database.php`, کلید خصوصی، backup و runtime telemetry در انتشار نیستند.
- اتصال به ارائه‌دهندهٔ خارجی فقط با تنظیم مدیر و کلید معتبر انجام می‌شود؛ تست ایستای adapter به معنی تأیید سرویس زنده نیست.

## اجرای محلی و بررسی

برای نصب و پیش‌نیازها به [README.md](../README.md) و [INSTALL.md](../INSTALL.md) مراجعه کنید. گیت ایستا و واحد با `php tools/release-gate.php --static-only` اجرا می‌شود. گیت کامل فقط در محیط QA دورریختنی با دیتابیس `proma_qa_v204` و URL روی loopback قابل اجراست؛ اجرای گیت کامل روی دیتابیس واقعی مجاز نیست.
