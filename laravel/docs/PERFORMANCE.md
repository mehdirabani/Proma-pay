# کارایی و رشد

## پایه‌های فعلی

- فهرست مشتری و پرداخت صفحه‌بندی دارد (حداکثر ۱۰۰ مورد).
- queryهای dashboard شمارش‌محورند و نمای پرداخت مشتری با relation محدود eager-load می‌شود.
- indexهای migration شامل یکتایی موبایل، کد ملی hash، request/idempotency key، branch/status/time و customer/status است.

## بررسی‌های لازم پیش از بارگذاری واقعی

1. حجم داده و الگوی query را با دادهٔ ناشناس یا synthetic نزدیک production اندازه بگیرید؛ SQLite برای benchmark موتور هدف معیار نیست.
2. `EXPLAIN` queryهای dashboard، جست‌وجوی `LIKE` روی نام/موبایل، تاریخچهٔ ledger، اقساط معوق و گزارش مالی را روی MySQL/MariaDB بگیرید. اگر جست‌وجوی بزرگ لازم شد، طراحی index/search جداگانه انجام دهید.
3. صفحه‌بندی با ترتیب یکتا، سقف فیلتر، select حداقلی و eager loading را نگه دارید. export بزرگ باید stream/job باشد، نه query یک‌باره در حافظه.
4. contention برای یک حساب اعتبار/قرارداد و callback تکراری را با چند worker و load test بررسی کنید؛ lock و idempotency باید در DB production تأیید شود.
5. cache فقط دادهٔ غیرحساس و قابل invalidation را نگه دارد. اطلاعات هویتی و balance نباید از cache عمومی/مشترک بدون scope استفاده کند.
6. queue، connection pool، PHP-FPM، OPcache و سقف worker را پس از اندازه‌گیری تنظیم کنید؛ مقدار ثابت فرضی برای ظرفیت production انتخاب نشده است.

در این نسخه آزمون بار یا benchmark اجرا نشده است؛ هیچ ادعای ظرفیت درخواست/حجم داده ثبت نمی‌شود.
