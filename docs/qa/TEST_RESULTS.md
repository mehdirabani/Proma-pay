# Test Results - V1.3.6

زمان اجرا: `2026-07-18` در workspace محلی Windows. هیچ داده، کلید، درگاه یا پایگاه دادهٔ عملیاتی استفاده نشد.

| شناسه | آزمون | وضعیت | شواهد |
| --- | --- | --- | --- |
| QA-136-01 | PHP lint همه فایل‌های PHP | PASSED | `PHP_LINT_OK 243` با PHP `8.2.12` |
| QA-136-02 | JavaScript syntax | PASSED | `JS_SYNTAX_OK` |
| QA-136-03 | regression gate نسخه‌های پیشین و V1.3.6 | PASSED | `RELEASE_GATE_STATIC_OK` |
| QA-136-04 | دقت مبلغ و نرخ ثابت | PASSED | `FINANCIAL_PRECISION_V136_OK` |
| QA-136-05 | قرارداد خطای HTML/JSON | PASSED | `ERROR_RESPONSE_V136_OK` |
| QA-136-06 | 404 واقعی در سرور PHP محلی | PASSED | HTTP `404`، HTML RTL، `X-Request-Id`، `Cache-Control: no-store` و بدون مسیر/SQL/trace |
| QA-136-07 | 404 JSON واقعی | PASSED | HTTP `404` و payload معتبر شامل `ok=false`، `status=404` و `request_id` |
| QA-136-08 | هدرهای خطای واقعی | PASSED | CSP حاضر و `X-Powered-By` حذف شد: `LIVE_ERROR_HEADERS_AND_JSON_OK` |
| QA-136-09 | واکنش‌گرایی صفحه خطا | PASSED | مرورگر محلی: 320×568، 390×844، 768×1024 و 1366×768؛ بدون overflow افقی و بدون console error |
| QA-136-10 | یکپارچگی ZIP نصب، بروزرسانی و افزونه‌ها | PASSED | hash manifest، ZIP Slip، فایل‌های ممنوع و فایل checksum کنترل شد |
| QA-136-11 | نصب تازه با MySQL ایزوله | NOT EXECUTED | `PROMA_TEST_DB_DSN` تعریف نشده است |
| QA-136-12 | ارتقا از نسخه پشتیبانی‌شده با MySQL ایزوله | NOT EXECUTED | `PROMA_TEST_DB_DSN` تعریف نشده است |
| QA-136-13 | پرداخت واقعی/Callback sandbox | NOT EXECUTED | اعتبارنامهٔ sandbox و fixture ایزوله فراهم نشده است |
| QA-136-14 | E2E نقش‌های admin/operator/lawyer/customer | NOT EXECUTED | fixture و session ایزوله فراهم نشده است |

## نتیجه انتشار

گیت استاتیک و یکپارچگی بسته **PASSED** است. پذیرش کامل production هنوز **BLOCKED** است تا آزمون‌های MySQL ایزوله، update واقعی، callback پرداخت sandbox و E2E نقش‌ها انجام شود. این وضعیت به‌خاطر شفافیت کیفیت است و به معنی خرابی بسته نیست.
