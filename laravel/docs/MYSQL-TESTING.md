# اجرای تست‌ها روی MariaDB/MySQL

این تنظیم جدا از `phpunit.xml` است؛ تست پیش‌فرض همچنان روی SQLite اجرا می‌شود. پیکربندی MariaDB به `127.0.0.1:3307` و پایگاه `proma_pay_laravel_test` اشاره می‌کند. PHPUnit با `RefreshDatabase` جدول‌های همین DB تستی را drop/recreate می‌کند؛ آن را به محیط یا دادهٔ غیرآزمایشی وصل نکنید.

## محیط ثبت‌شده

- PHP 8.2.12 و افزونهٔ `pdo_mysql`
- MariaDB 10.4.32 از توزیع XAMPP
- datadir جدید و جدا در `%TEMP%`، بدون اتصال به datadir موجود XAMPP
- bind فقط `127.0.0.1`, port `3307`, database `proma_pay_laravel_test`, charset/collation `utf8mb4`

## اجرا

با MySQL/MariaDB disposable و پایگاه خالی به همین نام:

```powershell
php artisan migrate:fresh --seed --force --database=mysql
vendor/bin/phpunit --configuration=phpunit.mariadb.xml
vendor/bin/phpunit --configuration=phpunit.mariadb.xml --filter=concurrent_contract_submissions_cannot_over_reserve_credit
```

آزمون هم‌زمانی فقط وقتی اجرا می‌شود که اتصال دقیقاً به `127.0.0.1:3307/proma_pay_laravel_test` اشاره کند. آزمون schema همین DB دورریختنی را تازه‌سازی می‌کند، دو PHP worker مستقل را پشت barrier هم‌زمان می‌کند، و در پایان migrationها را rollback می‌کند. از این آزمون روی دیتابیس دیگری استفاده نکنید.

اگر سرویس از مقادیر پیش‌فرض test config استفاده نمی‌کند، متغیرهای `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` را فقط به سرویس آزمایشی اشاره دهید. `DB_DATABASE` باید یک پایگاه disposable با پسوند/نام test باشد.

## شواهد این محیط

در اجرای Phase B، MariaDB 10.4.32 روی پورت 3307 با datadir تازه در `%TEMP%` بالا آمد و `migrate:fresh --seed --force` اولیه موفق شد. suite فعلی روی MariaDB شامل ۵۱ تست/۲۴۸ assertion است؛ SQLite نیز ۵۰ تست/۲۳۸ assertion موفق دارد و آزمون row-lock را skip می‌کند. آزمون دو فرایند رزرو دو قرارداد ۷۰۰٬۰۰۰ تومانی از سقف ۱٬۰۰۰٬۰۰۰ را ثابت می‌کند و full migration rollback نیز ایراد FK ستون `users.branch_id` را آشکار کرد که اصلاح شد. ۱۱ تست pure financial state/allocation نیز در هر دو driver اجرا می‌شوند. raceهای ثبت پرداخت/درگاه/refund/settlement، نسخهٔ production و MySQL 8 هنوز باز هستند.

برای موتور دیگری، نسخهٔ آن را جدا ثبت کنید. از `migrate:fresh` فقط روی پایگاه دورریختنی استفاده کنید.
