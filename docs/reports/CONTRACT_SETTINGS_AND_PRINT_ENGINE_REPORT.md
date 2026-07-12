# Contract Settings and Print Engine Report

1. **Top spacing:** حاشیه ثابت 18mm همراه با padding پیش‌نمایش باعث فاصله زیاد بود؛ profile جدید 6mm و print padding صفر دارد.
2. **Line height:** مقدار ثابت 1.54 حذف و مقدار profileمحور با پیش‌فرض 1.45 جایگزین شد.
3. **Spacing edits:** renderer مبتنی بر `nl2br` ساختار پاراگراف نداشت؛ renderer معنایی اضافه شد.
4. **Page break:** جدول دیگر `break-inside: avoid` کلی ندارد؛ فقط row محافظت می‌شود.
5. **Settings architecture:** مسیر مستقل `settings/contracts/*` و فرم‌های save جدا ایجاد شد.
6. **Dashboard:** وضعیت قالب، نسخه، stale documents، لوگو و actionهای اصلی نمایش داده می‌شود.
7. **Effective template:** نسخه published یا default system template.
8. **Copy current:** source قالب مؤثر با placeholderها کپی می‌شود.
9. **Clipboard fallback:** Clipboard API در secure context و textarea fallback پیاده شد.
10. **Editor:** Quill محلی، حالت ساده/source، toolbar، متغیر، find/replace و شمارنده.
11. **Renderer:** plain text و structured HTML با خروجی معنایی.
12. **Plain compatibility:** قالب‌های قدیمی به `plain_text_v1` منتقل می‌شوند.
13. **Structured content:** فرمت `structured_html_v1` برای خروجی ویرایشگر بصری.
14. **Sanitization:** script، event، JavaScript URL، style و attributeهای ناشناخته حذف می‌شوند.
15. **Template versions:** draft، published، superseded و archived.
16. **Publish:** دلیل و تایید الزامی؛ نسخه قبلی حذف نمی‌شود.
17. **Print profiles:** compact، standard، comfortable و custom.
18. **Compact preset:** 6/10/10/10mm، فونت 10.5pt و line-height 1.45.
19. **Top margin:** از 18mm به 6mm پیش‌فرض قابل تنظیم رسید.
20. **Line height:** بازه معتبر 1.2 تا 2.
21. **Paragraph spacing:** مستقل از line-height و با پیش‌فرض 1.5mm.
22. **Table pages:** table ادامه‌پذیر، thead تکرارشونده و row غیرقابل شکست.
23. **Live preview:** A4 واقعی، داده نمونه، zoom و profile مشترک.
24. **Existing documents:** style فوراً اعمال می‌شود؛ content قدیمی stale می‌شود.
25. **Bulk rebuild:** jobهای 25تایی قابل ادامه با گزارش failure.
26. **Manual editor:** از sanitizer و profile مشترک استفاده می‌کند و نسخه سند را حفظ می‌کند.
27. **Permissions:** ده permission صریح در `ContractPermission` تعریف شد.
28. **Audit:** draft، publish، restore، reset، header، numbering و print profile ثبت می‌شوند.
29. **Migration:** جداول قالب، audit، job و ستون‌های template version ایجاد می‌شوند.
30. **Modified files:** مدل سند، کنترلرهای تنظیمات/قرارداد، layout، چاپ، تنظیمات و build release.
31. **New files:** سه سرویس، permission، migration، دو view، CSS/JS، تست و مستندات.
32. **Tests executed:** PHP lint، JS syntax، static V126-V130، MariaDB migration/workflow، Chrome/Edge PDF و جدول 80 ردیفی.
33. **Tests passed:** همه تست‌های فوق پاس شدند.
34. **Failed tests:** تست قدیمی margin ثابت شکست خورد و به contract profile جدید اصلاح شد؛ failure محصول باقی نماند.
35. **Deployment:** بکاپ، نصب update، اجرای migration، بررسی preview و انتشار draft مورد نظر.
36. **Known limitations:** خاموش کردن browser header/footer فقط توسط کاربر پنجره چاپ ممکن است؛ راهنمای آن نمایش داده می‌شود.

## نتیجه پذیرش

- Chrome و Edge خروجی A4 با ابعاد 209.89 در 297.01mm تولید کردند.
- header از ناحیه چاپی بالا شروع می‌شود و اولین متن در حدود 9.26mm دیده شد.
- جدول 80 ردیفی در سه صفحه ادامه یافت و header جدول در هر صفحه تکرار شد.
- سند نمونه کامل به‌دلیل حجم واقعی متن و امضا دو صفحه است؛ فواصل اضافی و صفحه خالی مصنوعی وجود ندارد.
