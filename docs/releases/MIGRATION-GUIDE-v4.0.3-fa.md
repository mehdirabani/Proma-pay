# راهنمای نصب و ارتقای Proma Pay V4.0.3 — Candidate

> بسته‌های این Candidate برای production تأیید نشده‌اند. روی نصب فعال یا داده‌ی واقعی نصب نکنید؛ فقط روی clone قابل بازیابی برای UAT بررسی شوند.

## پیش‌نیاز

- مبنای بسته‌ی update دقیقاً V4.0.2 است.
- PHP 8.3 و MySQL 8.4 محیط محلی آزمون جاری بوده‌اند؛ سازگاری سایر نسخه‌ها در این Candidate certify نشده است.
- خروجی بسته و checksum را از پوشه‌ی Candidate بگیرید و hashها را پیش از import کنترل کنید.
- نصب فعال محلی قبلاً با backup مستقل فایل/دیتابیس به V4.0.2 ارتقا یافته است. هیچ نسخه‌ی 4.0.3 روی آن نصب نشده.

## روی QA clone

1. یک clone تازه از فایل‌ها و database در V4.0.2 بسازید؛ مسیر storage و پیکربندی محیط را جدا نگه دارید.
2. backup کامل DB و فایل‌ها بگیرید و امکان بازیابی را با import روی پایگاه دیگری تأیید کنید.
3. قبل از updater، `release-manifest.json` مبنای V4.0.2 را با baseline داخل `proma-update.json` تطبیق دهید. updater باید هر فایل مقصد را با `expected_previous_sha256` بسنجد.
4. بسته‌ی `PromaPay-v4.0.2-to-v4.0.3-update.zip` را فقط در QA clone با baseline رسمی core V4.0.2 نصب کنید. این مسیر در clone تمیز آزموده شد: updater ۹ فایل و یک migration اعمال کرد، نسخهٔ برنامه 4.0.3 شد و داشبورد پس از ارتقا باز شد. migration `2026_10_08_customer_password_mode.sql` ستون `customer_password_mode` را در صورت نبود ایجاد می‌کند و مقدار NULL مشتریان را به `pin` می‌برد.
5. این migration password hash را بازنشانی یا بازنویسی نمی‌کند. قبل از دسترسی مشتری، smoke test ورود PIN و پنل پروفایل را انجام دهید.
6. لاگ updater، تعداد فایل‌ها و نسخه‌ی DB را ذخیره کنید؛ dashboard و گزارش مالی را با baseline QA مقایسه کنید.

## نصب تازه

فایل core/full Candidate در `dist/candidates/v4.0.3/core/` است. بسته‌ها نشان Candidate دارند و برای نصب عملیاتی تأیید نشده‌اند. نصب تازه تنها در DB خالی یا محیط QA جدید مجاز به ارزیابی است.

## بازیابی

در صورت شکست migration یا smoke test، route را از دسترس کاربران خارج کنید، snapshot را به مسیر جدا بازیابی و DB را از dump پیش از update import کنید. logها و manifest شکست را نگه دارید. بازگشت صرفاً با کپی چند فایل کافی نیست؛ restore دیتابیس و فایل‌ها باید از یک backup هم‌زمان باشد.

## وضعیت فعلی

این راهنما دستور اجرای release تأییدشده نیست. cloneای که از نصب محلی V4.0.2 ساخته شد، به‌دلیل تفاوت هش `database/proma-pay-install.sql` با baseline رسمی، پیش از اعمال update رد شد. updater را روی این clone یا نصب فعال با اجبار ادامه ندهید؛ ابتدا drift را بررسی و baseline سازگار تهیه کنید. نصب تازه نیز هنوز آزموده نشده است.
