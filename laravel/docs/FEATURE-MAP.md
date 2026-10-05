# نقشهٔ قابلیت‌ها

وضعیت‌ها مطابق واژگان خواسته‌شده در برنامهٔ مهاجرت‌اند: `MIGRATED`, `REDESIGNED`, `REPLACED`, `DEPRECATED`, `REMOVED_WITH_REASON`, `NOT_APPLICABLE`. وضعیت به معنی تکمیل مهاجرت داده نیست؛ ستون «بلوغ این برش» روشن می‌کند چه چیزی واقعاً وجود دارد.

| حوزه | وضعیت | بلوغ در این برش | شاهد در Laravel | کار باقیمانده |
|---|---|---|---|---|
| محیط Laravel جدا از Legacy | `REPLACED` | پایهٔ اجرا | `artisan`, `composer.json`, `routes/` | استقرار آزمایشی و CI |
| UI فارسی، راست‌چین و واکنش‌گرا | `REDESIGNED` | ورود، داشبورد، مشتری، قرارداد و quote/capture/correction قسط | `resources/views/`, `resources/css/app.css` | آزمون UX با کاربران و صفحات باقی‌مانده |
| ورود کارکنان و نشست | `REDESIGNED` | پیاده‌سازی | `WebAuthController`, middleware | SSO/سیاست سازمانی در صورت نیاز |
| نقش، مجوز، شعبه | `REDESIGNED` | پیاده‌سازی پایه | `Role`, `Permission`, `Customer::visibleTo` | ماتریس دسترسی کامل و بازبینی همهٔ مسیرها |
| مشتری و پروندهٔ KYC | `REDESIGNED` | UI و API پایه | `Customer`, `KycCase`, Actions | انتقال مدارک، اتصال ارائه‌دهنده، retention و تطبیق Legacy |
| حساب و سقف اعتبار | `REDESIGNED` | افزایش/کاهش سقف و ledger | `AdjustCreditLimit` | قواعد مصرف/رزرو، limitهای منفی، reconciliation و concurrency واقعی |
| قرارداد و اقساط | `REDESIGNED` | draft تا activation، برنامهٔ نسخه‌دار و محاسبهٔ حالت قسط | `CreateContractDraft`, `TransitionContract`, `InstallmentFinancialStateCalculator` | لغو/تکمیل قرارداد، ضامن، ضمانت، تغییر زمان‌بندی و ابطال |
| پرداخت و تسویهٔ اقساط | `REDESIGNED` | payment intent، manual capture، correction داخلی و ثبت کارمند-محور/بازبینی مدرک انتقال با دفتر append-only | `RegisterPaymentIntent`, `CaptureManualPayment`, `CorrectCapturedPayment`, `SubmitPaymentReceipt`, `ReviewPaymentReceipt` | customer portal، gateway، برگشت بانکی، هزینهٔ حقوقی و reconciliation |
| جریمه، بخشودگی و هزینهٔ حقوقی | `REDESIGNED` | نگاشت دامنه فقط | [LEGACY-MAPPING](LEGACY-MAPPING.md) | استخراج و تصویب هر قاعده از Legacy با آزمون مرجع |
| اسناد و تولید قرارداد | `REDESIGNED` | نگاشت دامنه فقط | جدول‌های مقصد آینده در نگاشت | فایل امن، نسخه‌بندی، تولید PDF/قالب و مهاجرت اسناد |
| پنل/فروشگاه تجارت الکترونیک | `REDESIGNED` | نگاشت دامنه فقط | جدول‌های ecommerce قدیمی در نگاشت | تصمیم محصول و اتصال WooCommerce |
| webhook و پیام‌رسانی بیرونی | `REDESIGNED` | مستند برنامه‌ای | [WEBHOOKS.md](WEBHOOKS.md) | inbox/outbox، امضا، تکرار، retry و پایش |
| WordPress و WooCommerce | `REDESIGNED` | قرارداد پیشنهادی فقط | [WORDPRESS-INTEGRATION.md](WORDPRESS-INTEGRATION.md), [WOOCOMMERCE-INTEGRATION.md](WOOCOMMERCE-INTEGRATION.md) | افزونهٔ جدا، sandbox و آزمون سازگاری |
| import/export و تطبیق داده | `REDESIGNED` | برنامه فقط | [MIGRATION.md](MIGRATION.md) | dry-run، گزارش خطا، انتقال تکرارپذیر و reconciliation |
| فایل، پشتیبان و بازیابی | `REDESIGNED` | private storage و upload/download کنترل‌شده برای مدرک انتقال بانکی؛ دامنه‌های دیگر فقط برنامه‌اند | `PaymentReceiptEvidenceStorage`, `PaymentReceiptPolicy`, [SECURITY.md](SECURITY.md), [DEPLOYMENT.md](DEPLOYMENT.md) | اسکن بدافزار، retention/backup/restore، orphan cleanup و اتصال KYC/قرارداد/حقوقی/تعمیرات |
| افزونه‌ها و توسعه‌پذیری Legacy | `REDESIGNED` | تصمیم نگرفته/پیاده‌سازی نشده | `system_plugins*` در نگاشت | تعیین افزونه‌های فعال، مرز جداسازی و تصمیم نگهداری |
| log, metrics, tracing و alert | `REDESIGNED` | شناسهٔ درخواست و audit پایه | `AssignRequestId`, `audit_events` | exporter، redaction، dashboard و alert |

## تفاوت وضعیت و بلوغ

`REDESIGNED` برای این جدول یعنی تصمیم گرفته شده قابلیت باید در معماری مقصد دوباره طراحی/ارزیابی شود؛ به معنی پایان طراحی جزئیات یا مهاجرت نیست. `REPLACED` در محیط Laravel فقط به پایهٔ اجرای جدید اشاره دارد. هیچ جدول/رکورد قدیمی هنوز منتقل نشده است. در نتیجه این سند ادعای برابری عملکردی یا آماده‌بودن محصول برای جایگزینی Legacy نمی‌کند.
