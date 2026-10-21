# ثبت باگ کارت و چاپ V1.3.9

| شناسه | شدت | شرح | وضعیت |
| --- | --- | --- |
| CARD-139-01 | High | تداخل انتخاب‌گر با اطلاعات header قرارداد. | PASSED: grid سه‌ناحیه‌ای و selector static. |
| CARD-139-02 | Medium | عملیات ثانویه در کارت خوانایی کافی نداشتند. | PASSED: SVG محلی، tooltip و aria-label. |
| INSTALLMENT-139-01 | High | توضیح قسط دلخواه ذخیره/نمایش تفکیک‌شده نداشت. | PASSED: schema و مدل تفکیک شد. |
| PRINT-139-01 | Medium | سربرگ چاپ نیاز به تراکم و ساختار بهتر داشت. | PASSED: letterhead فشرده و rounded. |
| PRINT-139-02 | High | نصب قدیمی ممکن بود ستون‌های سند تولیدشده را نداشته باشد و چاپ با خطای 500 متوقف شود. | PASSED: migration و installer ستون‌های لازم را idempotent ایجاد می‌کنند؛ integration سند اجرا شد. |
| QA-139-01 | Medium | خطای کنترل‌شده CLI با exit صفر می‌توانست شکست release gate را پنهان کند. | PASSED: `ErrorHandler` در CLI با exit غیرصفر پایان می‌دهد. |

Critical باز: 0. High باز: 0. Medium مسدودکننده باز: 0.
