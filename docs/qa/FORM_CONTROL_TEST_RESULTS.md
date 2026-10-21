# نتایج کنترل‌های فرم V1.3.9

| کنترل | وضعیت | شاهد |
| --- | --- | --- |
| lint تمام فایل‌های PHP | PASSED | `253` فایل با PHP `8.2.12` |
| syntax تمام JavaScriptها | PASSED | `584` فایل |
| فرم قرارداد دسکتاپ، حالت normal و focus | PASSED | computed-style مرورگر محلی: ارتفاع `44px`، مرز شفاف و accent داخلی focus |
| checkbox کارت قرارداد | PASSED | target برابر `44px`، انتخاب بدون navigation |
| فرم کارت/قرارداد در 390x844 | PASSED | بدون horizontal overflow |
| افزونه حسابداری و زرین‌پال | PASSED | static gate و بررسی tokenهای field |
| invalid و disabled/readonly در مرورگر | NOT EXECUTED | منطق و selectorها static بررسی شدند؛ screenshot مستقل ثبت نشد |
| dark mode تعاملی | BLOCKED | محیط مرورگر QA امکان mutation attribute تم را نداد و کنترل تم زنده در مسیر آزمون موجود نبود |
| Firefox و WebKit | BLOCKED | در محیط QA محلی فراهم نبود |

کنترل‌های کاربر شامل placeholder، راست‌چین، padding، focus قابل مشاهده، state خطا و disabled در CSS مشترک تعریف شده‌اند. نتیجه‌های اجرا نشده نباید به‌معنای PASS تلقی شوند.
