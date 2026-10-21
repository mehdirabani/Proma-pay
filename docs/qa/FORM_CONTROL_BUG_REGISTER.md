# ثبت باگ کنترل‌های فرم V1.3.9

| شناسه | شدت | ریشه | نتیجه |
| --- | --- | --- | --- |
| FORM-139-01 | Medium | selector فیلد متنی submit/button/reset/image را هم می‌گرفت. | PASSED: selector مستثنا شد. |
| FORM-139-02 | Medium | focus بدون جانشین در برخی overrideهای legacy ممکن بود ناپدید شود. | PASSED: focus هسته با accent داخلی و token مشترک تثبیت شد. |
| FORM-139-03 | Low | plugin CSS می‌توانست ظاهر فرم core را بازتعریف کند. | PASSED: scope افزونه و tokenهای core بررسی شد. |

Critical باز: 0. High باز: 0. Medium مسدودکننده باز: 0.
