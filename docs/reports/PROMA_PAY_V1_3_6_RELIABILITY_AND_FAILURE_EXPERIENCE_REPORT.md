# PROMA PAY V1.3.6 Reliability and Failure Experience Report

## تغییرهای اعمال‌شده

- `ErrorHandler` در bootstrap پیش از dispatch نصب می‌شود و برای exception، PHP error و fatal shutdown یک مسیر امن دارد.
- پاسخ‌های JSON ساختار `ok/status/error/code/title/message/request_id` دارند؛ صفحات HTML RTL مستقل از layout و دیتابیس هستند.
- خطای route افزونه isolate می‌شود و routeهای اصلی را از دسترس خارج نمی‌کند.
- اعلان‌ها و hookهای حساس پس از commit در `system_outbox` اجرا و در صورت خطا retryable می‌شوند.
- محاسبات مالی Contract، Installment، PaymentReceipt و FinanceHelper دیگر از float برای مبلغ استفاده نمی‌کنند.
- CSS واکنش‌گرا و JS مودال برای قفل اسکرول، Escape، Tab trap و بازگردانی focus افزوده شده است.

## شواهد اجراشده

| مورد | ابزار | وضعیت نهایی در `docs/qa/TEST_RESULTS.md` |
| --- | --- | --- |
| PHP lint | PHP 8.2.12 | PASSED: `PHP_LINT_OK 243` |
| static regression | `tools/release-gate.php` | PASSED: `RELEASE_GATE_STATIC_OK` |
| precision | `tests/financial_precision_v136.php` | PASSED: `FINANCIAL_PRECISION_V136_OK` |
| error contract | `tests/error_response_v136.php` | PASSED: `ERROR_RESPONSE_V136_OK` |
| HTML/JSON 404 و security headers | PHP local server + curl | PASSED: `LIVE_ERROR_HEADERS_AND_JSON_OK` |
| viewport خطای 404 | Chromium local | PASSED: 320px، 390px، 768px و 1366px بدون overflow/console error |
| MySQL fresh install/update | محیط ایزوله | NOT EXECUTED: DSN ایزوله تعریف نشده است |
| payment sandbox و E2E نقش‌ها | sandbox + fixture | NOT EXECUTED: fixture و credential ایزوله فراهم نشده است |

## محدودیت شفاف

این گزارش جایگزین تست روی داده عملیاتی نیست. هر موردی که بدون MySQL ایزوله یا مرورگر واقعی اجرا نشود، در QA با `NOT EXECUTED` یا `BLOCKED` ثبت می‌شود و نباید به عنوان تأیید production برداشت شود.
