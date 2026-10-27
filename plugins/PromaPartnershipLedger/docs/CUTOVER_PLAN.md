# برنامهٔ Cutover

این سند دستور اجرایی staging/Production برای محیطی است که هنوز پیش از cutover قرار دارد.

## پیش‌شرط‌ها

- گزارش parity و migration تکمیل، بدون مغایرت، و توسط مسئول مالی/سامانه بررسی شده باشد.
- backup قابل‌بازیابی داشته باشد.
- آزمون نصب، migration افزایشی، permissionها، hook پرداخت، فروش نقدی/اقساطی، مرجوعی، تسویه و گزارش روی staging موفق باشد.
- نسخه و hash بستهٔ نصب در change record ثبت شده باشد.
- کاربران از پنجرهٔ توقف نوشتن مطلع باشند.

## ترتیب اجرا

1. توقف ثبت خرید، فروش، پرداخت و تسویه در بازهٔ cutover.
2. ثبت snapshot نهایی و اعداد کنترل.
3. غیرفعال‌سازی `proma-inventory-partners` از Plugin Manager؛ هیچ purge/uninstall اجرا نشود.
4. فعال‌سازی `proma-partnership-ledger`. فعال‌سازی باید در صورت فعال‌بودن افزونهٔ قدیمی رد شود.
5. بررسی route/menu، permissionهای کاربر، hookها و نبود provider قدیمی در registry فعال.
6. اجرای smoke read-only و یک چرخهٔ تست در staging. در Production، عملیات آزمایشی مالی فقط با رویهٔ دادهٔ تست و مجوز سازمان انجام شود.
7. بازگشایی ثبت‌ها و پایش reconciliation در اولین چرخه.

## وضعیت

Production cutover انجام نشده است. در اولین مشاهدهٔ آزمون محلی `proma.test`، successor از قبل active و افزونهٔ قدیمی در وضعیت `removed` بود؛ این نوبت هیچ lifecycleای را تغییر نداد. برای rollback محلی باید ابتدا بسته/فایل‌های legacy به runtime بازگردد. وضعیت و شواهد در `MIGRATION_VERIFICATION_REPORT.md` آمده است.
