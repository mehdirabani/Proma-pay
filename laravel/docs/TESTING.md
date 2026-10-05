# آزمون و کنترل کیفیت

## فرمان‌ها

از `laravel/`:

```sh
php artisan test --compact
npm run build
composer validate --no-check-publish
composer audit
vendor/bin/pint --test
```

برای محیط میزبان این repo، PHP/Composer باید از مسیر نصب‌شده فراخوانی شوند؛ PHP در PATH نیست. نتیجه‌ها را با version ابزار ثبت کنید.

## شواهد اجراشده

در اجرای اولیهٔ ۲۰۲۶-۱۰-۰۵، `migrate:fresh --seed` روی SQLite موفق شد؛ ۱۳ تست با ۶۳ assertion، `npm run build`، `composer validate --no-check-publish`، `composer audit` بدون advisory، `vendor/bin/pint --test` و syntax check روی ۶۷ فایل موفق شدند. داشبورد و جزئیات مشتری در مرورگر با اندازه‌های دسکتاپ ۱۴۴۰px و موبایل ۳۷۵×۸۱۲ بازبینی چشمی شدند.

در Phase B همان روز، یک datadir دورریختنی و مستقل MariaDB 10.4.32 روی loopback پورت 3307 ساخته شد. روی آن `migrate:fresh --seed --force` و PHPUnit با `phpunit.mariadb.xml` اجرا شدند؛ نسخهٔ فعلی suite شامل ۲۵ تست و ۹۱ assertion است و روی هر دو تنظیم SQLite و MariaDB موفق شده است. جزئیات اتصال و محدودیت این شواهد در [MYSQL-TESTING.md](MYSQL-TESTING.md) ثبت شده است.

این نتایج فقط نسخهٔ محلی MariaDB 10.4.32 را پوشش می‌دهند. نسخهٔ دیتابیس production نامعلوم است؛ تست race/concurrency مالی، MySQL 8، browser automation تکرارپذیر در CI، accessibility audit، load test، API contract/OpenAPI و migration-data reconciliation هنوز اجرا نشده‌اند.

## پوشش لازم پیش از تولید

- Unit: تبدیل رقم، مرز مبلغ integer/overflow، قالب‌گذاری و قواعد KYC.
- Feature: roles/permissions، شعبه و cross-branch denial، inactive user، CSRF/login rate، token lifecycle، idempotency payload conflict و KYC transition.
- مالی: حفظ invariants اعتبار/رزرو، چند درخواست هم‌زمان، پرداخت تکراری، تخصیص قسط، rounding، refund/correction و golden case با خروجی Legacy.
- DB: تمام migrationها و تست تراکنش روی موتور/version واقعی؛ backup/restore و migration forward/backward policy.
- UI/API: فارسی/RTL در mobile/desktop، keyboard/screen reader، CSRF، pagination/error format و API compatibility.
- Integrations: provider sandbox و امضای webhook، replay/timeout، WordPress/WooCommerce نسخه‌بندی‌شده.
