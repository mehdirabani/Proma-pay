# اتصال WooCommerce

اتصال WooCommerce یا endpoint تجارت الکترونیک در این برش ساخته نشده است. جداول ecommerce Legacy در [نگاشت](LEGACY-MAPPING.md) صرفاً inventory شده‌اند؛ فروشگاه جدید و نسبت سفارش به قرارداد تصمیم محصولی باز است.

## طرح پیشنهادی

1. WooCommerce سفارش و آیتم را با شناسهٔ پایدار سفارش/سایت به Laravel می‌فرستد؛ unique key مرکب مانع ساخت تکراری در webhook retry شود.
2. امضای webhook، timestamp، secret هر محیط و شناسهٔ event اعتبارسنجی شوند. در صف inbox ثبت شوند؛ پاسخ سریع به فرستنده داده شود.
3. تبدیل قیمت‌ها واحد مشخص داشته باشد؛ مقدار تومان integer و currency=`TMN` بین سیستم‌ها صریح باشد. مقدار WooCommerce و مبلغ قرارداد پیش از ایجاد credit تعهدی تطبیق داده شود.
4. وضعیت‌های pending/paid/refunded/cancelled به state machine مصوب وصل شوند؛ بازپرداخت باید رخداد جبرانی و audit داشته باشد.
5. تست نسخهٔ فعلی و نسخهٔ هدف WooCommerce روی sandbox، duplicate delivery، refund، مالیات/تخفیف، timezone و rounding را بپوشاند.

تا وقتی قرارداد سفارش، شناسهٔ خارجی، نگاشت lifecycle و گزارش reconciliation پیاده و تأیید نشده، این برنامه WooCommerce را متصل تلقی نمی‌کند.
