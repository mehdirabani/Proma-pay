# برنامهٔ مهاجرت از Legacy

## اصل

مهاجرت داده هنوز شروع نشده است. جدول‌ها، قوانین محاسبه، callbackهای پرداخت، jobهای cron و pluginهای نصب‌شده باید پیش از cutover شناسایی شوند. [نگاشت ۷۲ جدول](LEGACY-MAPPING.md) فقط inventory اولیه است و نقشهٔ تبدیل ستون‌به‌ستون محسوب نمی‌شود.

## فازهای پیشنهادی

1. **کشف و تثبیت:** ثبت نسخهٔ Legacy و hash artifact؛ snapshot فقط‌خواندنی schema/data و config؛ inventory pluginها، cron، gateway و فایل‌ها؛ مالک/حساسیت هر فیلد.
2. **تطبیق قواعد:** استخراج قراردادهای مالی از `MoneyMath`, `FinanceHelper`, settlement/allocation/penalty services و مقایسه با ledger واقعی. خروجی مرجع golden-case برای مبلغ، تاریخ سررسید، جریمه، rounding و تخصیص ایجاد شود.
3. **طرح تبدیل:** برای هر table/column مبدا→مقصد، null/default، code page/فارسی، تبدیل رقم، timezone، تاریخ شمسی، Toman/Rial، dedupe، unresolved row و رابطهٔ FK مشخص شود.
4. **ساخت importer:** batch قابل resume و idempotent با checkpoint، dry-run، error quarantine، شمارش ورودی/خروجی، checksum و log بدون PII خام. دادهٔ source را تغییر ندهد.
5. **تست بازیابی:** چند copy ناشناس از production، migration repeat، restart بین batch، rollback/restore و reconciliation کلی و per customer/contract اجرا شود.
6. **اجرای موازی:** فقط خواندن یا shadow calculations را اجرا کنید؛ اختلاف‌ها با مالک مالی حل شوند. از دوطرفه نوشتن بدون قاعدهٔ منبع حقیقت پرهیز شود.
7. **cutover کنترل‌شده:** توقف نوشتن Legacy، snapshot نهایی، import delta، tally و sign-off، route/DNS/app switch و rollback window مشخص. پایش و مالک تصمیم rollback تعیین شود.
8. **توقف Legacy:** تنها پس از تطبیق مالی/حقوقی، پشتیبان قابل بازیابی و دورهٔ پایدار مصوب.

## شرط عبور

هیچ معیار کمی برای تطبیق یا اختلاف مجاز هنوز با کسب‌وکار تصویب نشده است. تا تعیین owner و حد آستانهٔ reconciliation برای هر balance/payment/contract، cutover مالی انجام‌پذیر نیست. هیچ importer، dual-write یا ابزار rollback در کد این برش وجود ندارد.
