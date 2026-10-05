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

## شواهد این برش

نتیجهٔ نهایی این برش در روز ۲۰۲۶-۱۰-۰۵: `php artisan migrate:fresh --seed --force` روی SQLite محلی موفق بود؛ `php artisan test --compact` با ۱۳ تست و ۶۳ assertion موفق شد؛ `npm run build` موفق بود؛ `composer validate --no-check-publish` معتبر و `composer audit` بدون advisory گزارش شد؛ `vendor/bin/pint --test` پس از قالب‌بندی ۱۰ فایل موفق شد. PHP syntax check روی ۶۷ فایل موفق بود. داشبورد و جزئیات مشتری روی مرورگر در اندازهٔ دسکتاپ ۱۴۴۰px و اندازهٔ موبایل ۳۷۵×۸۱۲ بازبینی چشمی شدند.

این شواهد فقط تا همین برش و SQLite معتبر است. تست MySQL، concurrency، browser automation تکرارپذیر در CI، accessibility audit، load test، API contract test/OpenAPI و migration-data reconciliation اجرا نشده‌اند.

## پوشش لازم پیش از تولید

- Unit: تبدیل رقم، مرز مبلغ integer/overflow، قالب‌گذاری و قواعد KYC.
- Feature: roles/permissions، شعبه و cross-branch denial، inactive user، CSRF/login rate، token lifecycle، idempotency payload conflict و KYC transition.
- مالی: حفظ invariants اعتبار/رزرو، چند درخواست هم‌زمان، پرداخت تکراری، تخصیص قسط، rounding، refund/correction و golden case با خروجی Legacy.
- DB: تمام migrationها و تست تراکنش روی موتور/version واقعی؛ backup/restore و migration forward/backward policy.
- UI/API: فارسی/RTL در mobile/desktop، keyboard/screen reader، CSRF، pagination/error format و API compatibility.
- Integrations: provider sandbox و امضای webhook، replay/timeout، WordPress/WooCommerce نسخه‌بندی‌شده.
