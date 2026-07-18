# نتایج آزمون V1.3.8

| حوزه | وضعیت | شواهد |
| --- | --- | --- |
| lint کامل PHP | PASSED | `PHP_LINT_ALL_OK 247` با PHP 8.2.12 |
| syntax JavaScript | PASSED | `node --check assets/js/app.js` |
| گیت ایستا و افزونه‌ها | PASSED | `php tools/release-gate.php` و خروجی `RELEASE_GATE_STATIC_OK` |
| migration رجیستری فایل | PASSED | اجرا دو مرتبه روی MariaDB 10.4.32 بدون خطا |
| چرخه فایل | PASSED | `tests/integration_v138_file_registry.php` و خروجی `INTEGRATION_V138_FILE_REGISTRY_OK` |
| ورود و مسیرهای مدیریت | PASSED | ورود مدیر، مشتریان، قراردادها، سررسید، تقویم، مدیریت فایل و مدال‌ها در محیط ایزوله |
| لغو قرارداد | PASSED | قرارداد نمونه به `cancelled` و 4 قسط آن به `cancelled` تغییر کرد |
| تماس سررسید مدیر | PASSED | مودال تماس و bind اولیه دکمه کپی شماره در مرورگر بررسی شد |
| واکنش‌گرایی مودال فایل | PASSED | viewport `390x844`؛ هیچ input خارج از viewport نبود و footer قابل مشاهده ماند |
| رگرسیون افزونه‌ها | PASSED | PromaAccounting و PromaZarinpal در release gate عبور کردند |

## محیط آزمون

- PHP: `8.2.12`
- MariaDB: `10.4.32`
- مرورگر: Codex In-app Browser، دسکتاپ و `390x844`
- دیتابیس: کپی ایزوله از نصب V1.3.7؛ داده تولیدی یا محیط عملیاتی تغییر نکرد.

## موارد خارج از بسته

هیچ migration مخربی برای این انتشار وجود ندارد. همگام‌سازی فایل‌های قدیمی از پنل مدیریت فایل به‌صورت مرحله‌ای و به انتخاب مدیر انجام می‌شود.
