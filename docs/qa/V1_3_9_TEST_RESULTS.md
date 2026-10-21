# نتایج QA انتشار V1.3.9

| دسته | وضعیت | شاهد |
| --- | --- | --- |
| PHP lint | PASSED | `253` فایل با PHP `8.2.12` |
| JavaScript syntax | PASSED | `584` فایل |
| Git whitespace | PASSED | `git diff --check` |
| static V1.3.9 | PASSED | `tests/static_v139.php` |
| release gate | PASSED | `tools/release-gate.php` |
| migration re-run | PASSED | دو اجرای متوالی روی MariaDB `10.4.32` |
| custom footer preservation | PASSED | مقدار سفارشی QA پس از اجرای مجدد migration دست‌نخورده ماند |
| integration custom installment | PASSED | ایجاد، ویرایش و پاک‌سازی `tests/integration_v139_custom_installments.php` |
| integration contract document schema | PASSED | تولید و پاک‌سازی سند در `tests/integration_v139_contract_print_schema.php` |
| integration file registry | PASSED | `tests/integration_v138_file_registry.php` |
| integration Zarinpal | PASSED | دو اجرای متوالی `tests/integration_zarinpal_v134.php` |
| CLI controlled exception | PASSED | خطای کنترل‌شده با exit code غیرصفر خاتمه یافت |
| desktop browser | PASSED | کارت قرارداد، selector و field focus در QA محلی |
| mobile 390x844 | PASSED | بدون overflow افقی در کارت قرارداد |
| one-page PDF scenario | PASSED | Chrome و Edge، دو محصول و هشت قسط، یک صفحه A4 |
| ZIP و SHA-256 | PASSED | core/update و دو افزونه بازخوانی و manifest بررسی شد |

Critical باز: 0. High باز: 0. Medium مسدودکننده باز: 0. موارد خارج از پوشش: dark mode تعاملی، Firefox/WebKit و screen reader بومی.
