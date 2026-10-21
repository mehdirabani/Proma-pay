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

در اجرای فعلی، suite SQLite شامل ۸۵ تست و ۵۹۳ assertion است: ۸۲ تست موفق و سه تست row-lock عمداً skip شده‌اند. `migrate:fresh --seed --force --database=sqlite` نیز پس از آخرین migration موفق شد. هفت تست جدید receipt، upload خصوصی، MIME/extension/size، idempotency/conflict، branch denial، لینک امضاشده/انقضا، audit، رد بدون پرداخت، تأیید با quote تازه و نمایش RTL را پوشش می‌دهند.

آخرین شواهد MariaDB 10.4.32 مربوط به commit `27be765` و قبل از migration رسید است: ۷۸ تست/۴۹۶ assertion، به‌همراه سه race دوفرایندی و rollback migration. در این برش راه‌اندازی مجدد MariaDB ایزوله با assertion داخلی InnoDB در `os0file.cc` (`slot`) crash کرد؛ بنابراین migration و suite رسید روی MariaDB/MySQL اجرا نشده‌اند و این محدودیت را نباید با شواهد قبلی جایگزین کرد.

`npm run build` و Pint برای PHPهای تغییرکرده موفق‌اند. UI جدید با feature test در RTL ارزیابی شده، اما screenshot و browser accessibility capture تهیه نشده است.

این نتایج SQLite فعلی و MariaDB 10.4.32 در commit قبلی را پوشش می‌دهند؛ هیچ‌کدام موتور production را مشخص نمی‌کنند. raceهای جدید رسید، درگاه/refund/settlement، MySQL 8، browser automation تکرارپذیر در CI، accessibility audit، load test، API contract/OpenAPI و migration-data reconciliation هنوز اجرا نشده‌اند.

## پوشش لازم پیش از تولید

- Unit: تبدیل رقم، مرز مبلغ integer/overflow، قالب‌گذاری و قواعد KYC.
- Feature: roles/permissions، شعبه و cross-branch denial، inactive user، CSRF/login rate، token lifecycle، idempotency payload conflict و KYC transition.
- مالی: حفظ invariants اعتبار/رزرو، چند درخواست هم‌زمان، پرداخت تکراری، تخصیص قسط، rounding، refund/correction و golden case با خروجی Legacy.
- DB: تمام migrationها و تست تراکنش روی موتور/version واقعی؛ backup/restore و migration forward/backward policy.
- UI/API: فارسی/RTL در mobile/desktop، keyboard/screen reader، CSRF، pagination/error format و API compatibility.
- Integrations: provider sandbox و امضای webhook، replay/timeout، WordPress/WooCommerce نسخه‌بندی‌شده.
