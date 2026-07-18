# آزمون افزونه‌ها در V1.3.7-RC.1

## PASSED

- آزمون static، کمیسیون و یکپارچگی درخواست افزونه `PromaAccounting 1.2.1`.
- آزمون static، تبدیل مبلغ، client، پروتکل رسمی mock، sandbox، cipher و static-security افزونه زرین‌پال.
- تمام موارد بالا از طریق `tools/release-gate.php` اجرا شدند و پرداخت واقعی یا credential عملیاتی استفاده نشد.

## BLOCKED

- نصب، فعال‌سازی، ارتقا و uninstall افزونه‌ها روی MySQL/MariaDB ایزوله.
- چرخه واقعی ثبت سند، کمیسیون، لغو قرارداد و outbox با افزونه حسابداری.
- callback و verify واقعی زرین‌پال در sandbox با credential اختصاصی.

تا زمان عبور موارد BLOCKED، هیچ بسته پایدار یا ادعای QA کامل افزونه صادر نمی‌شود.
