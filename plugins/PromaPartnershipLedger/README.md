# Proma Partnership Ledger

افزونهٔ successor برای Proma Pay 7.x، با ادامهٔ داده‌های انبار و شرکای `PromaInventoryPartners`. این بستهٔ PHP برای Plugin Manager اختصاصی Proma Pay است و افزونهٔ WordPress نیست.

## معماری داده

کالا، انبار، محموله، IMEI/Serial، خرید، فروش، تخصیص وجه، journal، آورده، هزینه و تسویه همان جداول `plugin_inventory_*` نسخهٔ قدیمی را استفاده می‌کنند. migration این بسته جدول موازی برای این داده‌ها ایجاد نمی‌کند. جدول افزودهٔ `plugin_inventory_financial_policy_versions` فقط برای نسخه‌های جدید قواعد مالی است.

کد افزونه namespace و provider مستقل دارد و به فایل‌های runtime افزونهٔ قدیمی وابسته نیست. هر دو افزونه در دورهٔ انتقال می‌توانند در مخزن حضور داشته باشند؛ فقط یکی از آن‌ها مجاز است فعال و writer باشد. گارد lifecycle دوجانبه این قاعده را enforce می‌کند.

## نصب

1. ZIP را از Plugin Manager Proma Pay نصب کنید یا پوشهٔ `PromaPartnershipLedger` را در `plugins/` قرار دهید.
2. ابتدا افزونه را نصب کنید تا migration افزایشی اجرا شود؛ افزونهٔ قدیمی را در این مرحله فعال نگه دارید.
3. مجوزهای roleها را از `plugin.proma-inventory-partners.*` به `plugin.proma-partnership-ledger.*` بازبینی و نگاشت کنید.
4. برای cutover، مراحل `docs/MIGRATION_PLAN.md` و `docs/CUTOVER_PLAN.md` را اجرا کنید. فعال‌سازی هم‌زمان عمداً رد می‌شود.
5. داده‌های قدیمی را purge/uninstall نکنید.

## قابلیت‌های موجود در این بسته

- حفظ جریان‌های نسخهٔ قدیمی: کاتالوگ کالا، خرید و موجودی محموله‌ای، سریال/IMEI، فروش نقدی/قراردادی، هزینه، دفتر شرکا، تسویه، گزارش، CSV و activity log.
- پیش‌نمایش نرخ ساده/مرکب اقساط بر مبنای کل قیمت پایه یا مبلغ تأمین‌شده، محاسبات integer-Rial و توزیع دقیق ماندهٔ قسط.
- ثبت نسخهٔ تغییرناپذیر سیاست مالی با snapshot JSON و SHA-256؛ سهم سود پایه و سود مدت بازپرداخت مستقل است.
- route و permissionهای مستقل با حفظ URLهای موجود برای سازگاری bookmarkها.
- ابزار SQL فقط‌خواندنی برای گرفتن snapshot تطبیق پیش/پس از cutover در `tools/migration-snapshot.sql`.

## وضعیت انتشار

این نسخه `2.0.0-alpha.2` و قابل نصب برای بررسی/آزمون staging است. هنوز جایگزین کامل Production محسوب نمی‌شود. فروش چندردیفی، مرجوعی جزئی، اتصال سیاست اقساط به مبلغ قرارداد core، قرارداد و عضویت چندمشارکتی، نگهداری سود، تسویهٔ تأمین‌کنندهٔ مالی، بستن دوره و ابزار تطبیق دیتابیس زنده در این build کامل نیستند. تا تکمیل و تأیید این موارد، افزونهٔ قدیمی را غیرفعال یا حذف نکنید.

## آزمون‌های مستقل

```powershell
php plugins/PromaPartnershipLedger/tests/domain-smoke.php
php plugins/PromaPartnershipLedger/tests/contract-smoke.php
```

آزمون‌های MySQL fixture در `tests/` قرار دارند و فقط باید روی schema آزمایشی مجزا با پیشوند امن اجرا شوند. نتیجهٔ اجرای این نوبت در `docs/TEST_REPORT.md` ثبت می‌شود.
