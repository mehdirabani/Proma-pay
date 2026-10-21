# گزارش آزمون این build

تاریخ: ۲۰۲۶-۱۰-۲۲

## آزمون‌های موفق

| بررسی | نتیجه | شاهد |
|---|---|---|
| JavaScript syntax | موفق | `node --check` برای asset اصلی افزونه با exit code صفر |
| Manifest و مسیرها | موفق | ۳۱ مجوز، ۵۰ route، handlerها، migrationها و assetها در آزمون قراردادی بررسی شدند |
| PHP syntax | موفق | ۲۴ فایل PHP؛ بدون خطای `php -l` با PHP 8.3.33 |
| منطق دامنه | موفق | ۲۵ بررسی برای تبدیل ریال/تومان، گردکردن و تخصیص، زیان، سود ساده/مرکب، اقساط و اعتبارسنجی سیاست |
| migration MySQL | موفق | هر ۳ migration successor در Plugin Registry با status=success ثبت شده‌اند |
| ذخیره و audit دیتابیسی | موفق | ذخیرهٔ سیاست مالی در تراکنش آزمایشی، hash برابر SHA-256، ثبت audit و rollback کامل دو شریک و سیاست آزمایشی بررسی شد؛ رکورد QA باقی نماند |
| guard فعال‌سازی متقابل | موفق در آزمون provider | با PluginManager ساختگی، provider جدید در صورت active بودن old و provider قدیمی در صورت active بودن new هر دو فعال‌سازی را رد کردند |
| routeهای رابط successor | موفق با دادهٔ خالی | dashboard، products، purchases، stock، pricing، sales، partners، ledger، settlements، expenses، transfers، profit-loss، reports، activity، financial-policies و settings نمایش داده شدند |
| پیش‌نمایش سادهٔ اقساط | موفق، بدون ذخیره | ۱۰,۰۰۰,۰۰۰ تومان، نرخ ۲٪ ماهانه، ۶ ماه: سود ۱,۲۰۰,۰۰۰ و مبلغ نهایی ۱۱,۲۰۰,۰۰۰؛ گردکردن اقساط جمع را حفظ کرد |
| پیش‌نمایش مرکب و پیش‌پرداخت | موفق، بدون ذخیره | پایهٔ ۱۰,۰۰۰,۰۰۰، پیش‌پرداخت ۲,۰۰۰,۰۰۰، نرخ ۲٪، ۶ ماه، مبنای مبلغ تأمین‌شده: سود ۱,۰۰۹,۲۹۹.۴ و ماندهٔ اقساط ۹,۰۰۹,۲۹۹.۴ تومان |
| سازگاری runtime | موفق | `proma.test` با Proma Pay 7.0.3، Apache 2.4.68، PHP 8.3.33 و MySQL 8.4.3 پاسخ HTTP 200 داد |
| تطبیق read-only دادهٔ محلی | موفق | یک انبار و صفر کالا، شریک، lot، گردش، خرید، فروش، journal، تخصیص، هزینه و تسویه؛ جزئیات در `MIGRATION_VERIFICATION_REPORT.md` |

## وضعیت آزمون محلی

- نسخهٔ `PromaPartnershipLedger 2.0.0-alpha.1` در `C:\laragon\www\proma\plugins\PromaPartnershipLedger` موجود بود و در اولین مشاهدهٔ این نوبت از قبل active بود. نصب یا تغییر lifecycle در این نوبت انجام ندادم.
- وضعیت Registry در شروع بررسی: successor فعال؛ `proma-inventory-partners 1.1.2` در وضعیت `removed` با `deleted_at=2026-10-22 01:48:13`. پوشهٔ legacy در `C:\laragon\www\proma\plugins` وجود نداشت.
- هر سه migration successor (`core`، `v110` و `partnership_ledger`) با status=success ثبت شده‌اند.
- فقط یک provider فعال در runtime دیده شد. guardها با provider و PluginManager ساختگی در هر دو جهت رد فعال‌سازی را نشان دادند؛ آزمون lifecycle واقعی با حضور هم‌زمان دو provider ممکن نبود، چون legacy در runtime نصب نیست.
- queryهای فقط‌خواندنی پیش و پس از routeها و previewها همان شمارش را دادند: یک warehouse؛ صفر ردیف عملیاتی برای محصول، شریک، lot، سریال، خرید، فروش، مرجوعی، سرمایه، journal، تخصیص پرداخت، هزینه، تسویه و نسخهٔ سیاست.
- previewها فقط محاسبه کردند؛ قرارداد core یا فروش تغییر نکرد و نسخهٔ سیاستی ذخیره نشد. هیچ خرید، فروش، پرداخت، انتقال، هزینه یا تسویه‌ای ثبت نشد.
- پیمایش سریع مرورگر موقتاً محدودکنندهٔ عمومی درخواست را فعال کرد (HTTP 429). پس از صبر و کاهش سرعت، مسیرهای باقیمانده با موفقیت باز شدند؛ داده‌ای تغییر نکرد.

این build هنوز با دادهٔ عملیاتی غیرخالی، آزمون واقعی cutover/rollback و همهٔ شکاف‌های parity و اتصال‌های مالی تأیید نشده است؛ Production readiness تأیید نمی‌شود.
