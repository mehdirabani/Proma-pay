# Error Page Test Results - V1.3.6

| آزمون | وضعیت | نتیجه |
| --- | --- | --- |
| route ناشناخته HTML | PASSED | HTTP 404 واقعی، صفحه RTL، title، `h1`، noindex و request ID نمایش داده شد. |
| route ناشناخته JSON | PASSED | HTTP 404 و JSON امن با کد پایدار `HTTP_404` برگشت. |
| افشای فناوری | PASSED | پاسخ پس از hardening هدر `X-Powered-By` نداشت. |
| هدرهای امنیتی | PASSED | CSP، nosniff، frame protection، referrer و permissions policy دیده شد. |
| موبایل | PASSED | در 320×568 و 390×844 صفحه overflow افقی نداشت و هر دو action قابل مشاهده بود. |
| تبلت و دسکتاپ | PASSED | در 768×1024 و 1366×768 کارت به درستی محدود شد و console error ثبت نشد. |
| 500/503 ناشی از خرابی واقعی زیرساخت | NOT EXECUTED | برای جلوگیری از اختلال محیط، خرابی DB/PHP در server واقعی القا نشد؛ فایل static fallback بررسی استاتیک شده است. |

کلیه وضعیت‌ها فقط برای آزمون‌های واقعاً اجراشده ثبت شده‌اند. آزمون‌های انجام‌نشده در `TEST_RESULTS.md` نیز آورده شده‌اند.
