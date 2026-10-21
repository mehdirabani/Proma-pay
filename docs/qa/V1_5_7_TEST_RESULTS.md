# گزارش آزمون V1.5.7

تاریخ تهیه: ۲۰۲۶/۰۸/۰۱

## عبورکرده

- PHP lint برای فایل‌های تغییرکردهٔ Core، کنترلر، View، سرویس‌های مالی، پرداخت و نصب‌کننده
- تمام تست‌های `tests/static*.php`، از جمله V1.4.0 تا V1.5.7
- `tests/financial_precision_v136.php`
- `tests/error_response_v136.php`
- `tests/session_v144.php`
- `tests/request_flood_guard_v148.php`
- `tests/contract_preview_storm_v149.php`
- `tests/installment_settlement_engine_v150.php`
- `tests/legal_penalty_projection_v156.php`
- `tests/update_v144.php`
- `node --check assets/js/app.js`

این آزمون‌ها وجود تب‌های قرارداد، حذف اسکریپت درون‌خطی، محاسبهٔ batch، وضعیت خطای جریمهٔ حقوقی، ابطال ایمن، توکن‌های فاصله‌گذاری، سناریوهای بدهی اپراتور، مرز هزینهٔ حقوقی تأییدنشده و تخصیص هزینهٔ حقوقی در تسویهٔ کامل را بررسی می‌کنند.

## آزمون یکپارچه‌سازی و HTTP

- گیت رسمی محلی با MariaDB ایزوله و وب‌سرور QA اجرا شد: `RELEASE_GATE_LOCAL_QA_OK_NOT_PRODUCTION_CERTIFIED`.
- `tests/integration_contract_installment_management_v157.php`، سناریوهای ویرایش/ابطال امن قسط، محاسبهٔ حقوقی، تسویه و هم‌زمانی را با موفقیت اجرا کرد.
- تست migration تسویهٔ V1.5.1 با یک schema قدیمی و کلید خارجی واقعی اجرا شد؛ migration اکنون پیش از حذف کلید یکتا، ایندکس پشتیبان کلید خارجی را ایجاد می‌کند.
- `tests/http_v141_role_smoke.php`، `tests/http_v143_modal_smoke.php`، تست HTTP داشبورد حسابداری و سناریوی خطای ایزولهٔ افزونه همگی عبور کردند.
- آزمون عملکرد MariaDB داشبورد حسابداری با ۳۰ تکرار عبور کرد؛ p95 برابر ۵٫۹۴ms بود. آزمون HTTP داشبورد حسابداری با ۲۰ تکرار عبور کرد؛ p95 برابر ۱۰۹٫۰۱ms بود.

## وضعیت انتشار

بسته با گیت کامل محلی ساخته می‌شود. این نتیجه، گواهی دسترس‌پذیری محیط عملیاتی یا هاست کاربر نیست؛ پس از نصب، یک بازبینی کوتاه روی هاست مقصد لازم است.
