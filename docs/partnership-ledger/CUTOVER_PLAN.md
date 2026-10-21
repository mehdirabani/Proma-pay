# برنامهٔ Cutover

این سند دستور اجرایی staging/Production است؛ اجرای cutover در Workspace انجام نشده است.

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

Cutover انجام نشده است. هیچ Plugin Registry یا runtime production در این کار تغییر نکرده است.
