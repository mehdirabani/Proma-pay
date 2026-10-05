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
```

اگر سرویس از مقادیر پیش‌فرض test config استفاده نمی‌کند، متغیرهای `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` را فقط به سرویس آزمایشی اشاره دهید. `DB_DATABASE` باید یک پایگاه disposable با پسوند/نام test باشد.

## شواهد این محیط

در اجرای Phase B، MariaDB 10.4.32 روی پورت 3307 با datadir تازه در `%TEMP%` بالا آمد؛ `migrate:fresh --seed --force` روی MySQL connection موفق شد؛ suite فعلی با ۲۵ تست و ۹۱ assertion هم با `DB_CONNECTION=mysql` موفق است. این نتیجه صحت مهاجرت و رفتار همین تست‌ها روی MariaDB را نشان می‌دهد، نه صحت concurrency مالی یا سازگاری با نسخهٔ production نامعلوم. هنوز آزمون race واقعی روی مسیرهای مالی Laravel، MySQL 8، یا اتصال به نسخهٔ production اجرا نشده است.

برای موتور دیگری، نسخهٔ آن را جدا ثبت کنید. از `migrate:fresh` فقط روی پایگاه دورریختنی استفاده کنید.
