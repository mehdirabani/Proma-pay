# اتصال Checkout ووکامرس — Phase 7

## هدف و وضعیت

این فاز درگاه WooCommerce را به API آزمایشی Proma Pay متصل می‌کند. افزونه در Classic Checkout و Checkout Blocks ثبت می‌شود، اما قیمت و شرایط را از API می‌گیرد. سفارش اصلی فروشگاه تا بررسی مرکزی پرداخت‌شده نمی‌شود.

این نسخه Production Ready نیست: API فقط credentialهای `pmp_test_` را می‌پذیرد و پرداخت بانکی واقعی، callback بانکی، استعلام صیاد، تأیید حقوقی و تسویه عملیاتی در این محیط در دسترس نیست.

## جریان Checkout

1. افزونه مبلغ نهایی سبد WooCommerce را با مالیات، حمل، تخفیف و کوپن می‌خواند و بدون اعشار غیرمجاز به تومان تبدیل می‌کند.
2. API مرکزی policy، جدول اقساط، تاریخ اولین سررسید و مبلغ چک را محاسبه می‌کند. افزونه نتیجه و هش snapshot ضمانت را دوباره کنترل می‌کند.
3. هنگام ارسال Checkout، افزونه quote نهایی را در metadata سفارش نگه می‌دارد و یک child order مستقل فقط به مبلغ پیش‌پرداخت می‌سازد.
4. child order را به درگاه آنلاین انتخاب‌شدهٔ فروشنده تحویل می‌دهد. درگاه‌های آفلاین و خود درگاه اقساط از فهرست انتخاب حذف شده‌اند.
5. پس از تأیید پرداخت child order، سفارش API و درخواست قرارداد با کلیدهای idempotency جدا ارسال می‌شوند. ارتباط ناموفق در صف زمان‌بندی‌شده retry می‌شود.
6. وضعیت قرارداد و ضمانت دوره‌ای خوانده می‌شود؛ سفارش والد تا بررسی مرکزی `on-hold` می‌ماند و هرگز با دریافت پیش‌پرداخت `payment_complete()` نمی‌شود.

Quote API اکنون snapshot را در جدول `merchant_api_quotes` و در محدودهٔ merchant/site/connection نگه می‌دارد. `/orders` باید همان UUID و همان ورودی مالی را ارسال کند. quote منقضی، تکراری یا متعلق به اتصال دیگر رد می‌شود. این migration افزایشی باید روی نصب مرکزی اعمال شود:

`database/migrations/2026_10_23_checkout_quote_snapshot_phase7.sql`

جدول فوق به `database/proma-pay-install.sql` نیز اضافه شده تا نصب تازه همین schema را بسازد.

## WooCommerce و رابط‌ها

- در سفارش‌ها تنها WooCommerce CRUD استفاده می‌شود (`wc_get_order`, `wc_create_order`, `WC_Order` و order items)؛ query مستقیم به جدول پست یا order انجام نمی‌شود.
- HPOS compatibility اعلام شده است.
- Checkout Blocks از Payment Method Integration و `onPaymentSetup` برای عبور داده‌های انتخاب سرویس، پیش‌پرداخت، تعداد اقساط، روش محاسبه و پذیرش شرایط استفاده می‌کند.
- در محصول متغیر، ویجت تا انتخاب variation پیشنهاد نهایی نشان نمی‌دهد. ویجت کلی و حذف محصول/variation در پنل محصول قابل تنظیم است.
- مبلغ WooCommerce فقط در واحد صحیح تومان یا ریال پشتیبانی می‌شود؛ divisorهای غیر از ۱ و ۱۰ یا تبدیل دارای اعشار تومان رد می‌شوند.
- بازپرداخت از سفارش والد فقط وقتی کامل و برابر مبلغ child order باشد پذیرفته می‌شود. ابتدا باید درخواست مرکزیِ در انتظار لغو شود؛ سپس refund به درگاه منتخب child order سپرده می‌شود. refund جزئی رد می‌شود چون قرارداد را با دریافت واقعی ناسازگار می‌کند.

## مسیرهای اصلی

- `wordpress-plugins/proma-pay-woocommerce/includes/class-promapaywc-gateway.php`
- `wordpress-plugins/proma-pay-woocommerce/includes/class-promapaywc-commerce.php`
- `wordpress-plugins/proma-pay-woocommerce/includes/class-promapaywc-quote-service.php`
- `wordpress-plugins/proma-pay-woocommerce/includes/class-promapaywc-payment-adapter.php`
- `wordpress-plugins/proma-pay-woocommerce/includes/class-promapaywc-blocks-integration.php`
- `wordpress-plugins/proma-pay-woocommerce/assets/js/checkout-blocks.js`
- `helpers/MerchantApiGateway.php`
- `models/MerchantApiGatewayRepository.php`

## آزمون‌های اجراشده

آزمایش محیطی با WordPress 7.1.3، WooCommerce 11.2.1، PHP 8.3.33 و MySQL 8.4.3 روی نصب موقت و دیتابیس جداگانه انجام شد. یک درگاه بانکی mock در همان QA نصب شد؛ هیچ درخواست یا پول واقعی به بانک ارسال نشد.

- آزمون Unit افزونه: تبدیل ریال/تومان، رد precision نامعتبر، جمع اقساط، هش سیاست و محاسبه/تاریخ چک؛ موفق.
- آزمون Static افزونه: ثبت Gateway/Blocks، CRUD/HPOS، allowlist، refund guard و status polling؛ موفق.
- checkout اجرایی در storage قدیمی: child order و درگاه انتخابی ساخته شد؛ والد `on-hold` و unpaid ماند.
- checkout اجرایی در HPOS: همان assertions با `OrderUtil::custom_orders_table_usage_is_enabled() === true`؛ موفق.
- API integration با MySQL آزمایشی: quote، سیاست‌های مالی و ضمانت، محدودیت scope، idempotency، order/contract و cancellation؛ موفق.
- خطای اولیهٔ test harness در فعال‌سازی تکراری وردپرس شناسایی و رفع شد؛ خطای اجرای سومی گزارش نشد.

آزمون end-to-end مرورگر روی Checkout Block/Store API اجرا نشده است. ثبت و بارگذاری server/client integration و عبور payload در کد و آزمون static بررسی شد. پرداخت واقعی و مسیر نهایی refund بانکی آزموده نشده‌اند.

## ریسک‌ها و محدودیت‌ها

- فقط یک quote تازه و معتبر باید پس از پرداخت پیش‌پرداخت به order مرکزی تبدیل شود. اگر quote در زمان پرداخت منقضی شود، sync به صورت خطادار retry می‌شود و نیازمند reconciliation دستی است؛ سیستم ادعای بازپرداخت خودکار ندارد.
- API فعلی درخواست را به وضعیت بررسی می‌برد و هیچ قرارداد فعال یا ضمانت صیادی ثبت‌شده‌ای نمی‌سازد.
- ثبت موفق درگاه فروشنده به‌تنهایی proof تسویه بانکی نیست؛ callback و reconciliation اختصاصی هر درگاه لازم است.
- سیاست مالکیت مطالبات، تأمین وجه، نکول، مرجوعی، کمیسیون و تسویه نیازمند تأیید قرارداد و حسابداری است.
