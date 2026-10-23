# ممیزی به‌روز Phase 0 — Proma Pay و سازگاری Bakala

| مشخصه | وضعیت مشاهده‌شده |
|---|---|
| تاریخ | ۲۰۲۶/۱۰/۲۳ (Asia/Tehran) |
| مبنای کد | `8aeed40` روی شاخهٔ `codex/physical-promapay-qa-20261023`، به‌علاوهٔ تغییرات محلی موجود |
| نسخهٔ Core | V7.0.4، کانال Candidate |
| افزونهٔ WooCommerce | 0.8.0-rc.1، فقط اعتبارنامهٔ آزمایشی |
| قالب‌های محلی | Bakala 10.4.33 و Bakala Child 1.0.0 |

این گزارش وضعیت همین checkout را توصیف می‌کند. گزارش قدیمی [ممیزی Phase 0](MULTI_MERCHANT_PHASE_0.md) پیش از پیاده‌سازی فازهای ۱ تا ۱۰ نوشته شده و معماری زمان خودش را ثبت می‌کند؛ برای وضعیت فعلی به این سند رجوع کنید.

## خلاصه

Proma Pay یک سامانهٔ PHP اختصاصی با MVC داخلی، PDO، MySQL/MariaDB و Plugin Manager خودش است. زنجیرهٔ قرارداد، اقساط، دریافت پرداخت و دفتر مالی فعلی حفظ شده است. تاریخچهٔ همین شاخه، commitهای پیاده‌سازی فازهای ۱ تا ۱۰ را دارد: محاسبهٔ مالی، هستهٔ چندفروشنده، ضمانت، تسویه و کمیسیون، API، افزونهٔ WordPress، Checkout، رویدادهای افزونه‌ها و گزارش فروشنده.

افزونهٔ WooCommerce و API مرکزی، سیاست مالی را از سرور می‌گیرند. مسیر فعلی افزونه با کلیدهای `pmp_test_` محدود است و صفحهٔ مدیریت نیز کلید live را نمی‌پذیرد. موتور تسویه به مدارک دستی متکی است؛ درگاه بانکی واقعی، سرویس صیاد و انتقال زنده به فروشنده در این checkout تأیید نشده‌اند.

دو بستهٔ Bakala در مسیر محلی موجود بودند. سورس نسخهٔ 10.4.33، hook استاندارد `woocommerce_single_product_summary` را در قالب محصول نگه می‌دارد و دکمهٔ SnappPay را جداگانه پس از دکمهٔ خرید نمایش می‌دهد. افزونه اکنون یک Adapter محدود به رابط محصول دارد و برای Bakala در صورت حذف hook استاندارد، fallback و گزارش سازگاری دارد. درخواست‌های quote واریاسیون نیز با لغو درخواست قبلی و شناسهٔ پاسخ کنترل می‌شوند.

**نتیجه:** مبنای معماری و مسیرهای فعلی استخراج شد؛ قالب و فایل‌های تجاری آن در Git نادیده گرفته می‌شوند. تأیید Release یا Production صادر نمی‌شود: PHP، WordPress، WooCommerce و دیتابیس آزمایشی در محیط فعلی در دسترس نبودند؛ تست‌های PHP و تست یکپارچهٔ واقعی Bakala اجرا نشدند.

## وضعیت checkout و Git

- شاخهٔ جاری `codex/physical-promapay-qa-20261023` است و upstream آن `origin/codex/final-integration-release-phase10` است؛ نام شاخهٔ محلی و remote یکسان نیست.
- `HEAD` برابر `8aeed40` است. history شامل commitهای فازهای ۱ تا ۱۰ می‌شود.
- پیش از این کار working tree تغییرات محلی متعدد داشت. آن‌ها بخشی از ممیزی یا commit جدید این سند محسوب نمی‌شوند و نباید با تغییرات این کار یکجا commit شوند.
- شاخه به‌تنهایی بر مبنای `main` تنظیم نشده است. PR باز و Draft شمارهٔ ۲۶، head شاخهٔ `codex/final-integration-release-phase10` و base شاخهٔ `codex/merchant-portal-reports-phase9` را دارد؛ تغییرهای همین خط انتشار باید به آن متصل شوند، نه اینکه PR تکراری ساخته شود.
- فایل `.gitignore` اکنون مسیر `/2- Themes/` را مستثنا می‌کند. ZIPهای تجاری حذف نشده‌اند و در دیسک محلی باقی می‌مانند.
- سند `docs/qa/RELEASE_GATE_QA_2026-10-23.md` نتیجهٔ قبلی را برای working tree با `HEAD=8a3ead3` ثبت می‌کند؛ این با `HEAD=8aeed40` در PR شمارهٔ ۲۶ یکسان نیست. متن PR پیش از این کار QA قبلی را به `8aeed40` نسبت می‌داد، اما آن سند این نسبت را اثبات نمی‌کند. بنابراین آن نتیجه در این ممیزی به‌عنوان تست همین HEAD استفاده نشده است.

## معماری واقعی و مسیرهای قابل استفادهٔ مجدد

```text
درخواست کاربر یا فروشگاه
  → index.php / api/v1
  → bootstrap.php و core/Router.php
  → Controller یا MerchantApiGateway
  → سرویس دامنه و Model / PDO
  → MySQL یا MariaDB

افزونهٔ WooCommerce
  → WordPress/WooCommerce gateway و REST endpoint
  → اتصال رمزگذاری‌شدهٔ فروشگاه به API مرکزی
  → quote و سیاست نسخه‌دار مرکزی
  → ثبت سفارش/درخواست و همگام‌سازی وضعیت
```

| حوزه | مسیرهای واقعی | وضعیت / مرز استفاده |
|---|---|---|
| Router و Plugin Manager | `core/Router.php`, `core/PluginManager.php`, `core/PluginManifest.php`, `core/PluginServiceProviderInterface.php` | معماری Core و افزونه‌های داخلی موجود است؛ WordPress افزونهٔ مستقل خود را دارد و نباید به‌صورت افزونهٔ Core جا زده شود. |
| قرارداد و اقساط | `models/Contract.php`, `models/Installment.php`, `helpers/InstallmentFinancialCalculator.php`, `helpers/MoneyMath.php` | قرارداد فروش مستقیم و برنامهٔ اقساط فعلی حفظ می‌شوند. calculator نسخه‌دار سه روش flat، compound و reducing balance را دارد؛ قرارداد Woo باید از snapshot و state machine خودش عبور کند. |
| ضمانت | `helpers/MerchantGuaranteePolicy.php`, `helpers/MerchantGuaranteeCalculator.php`, `database/migrations/2026_10_23_guarantee_engine_phase3.sql` | چک صیادی کانال Woo نوع ثابت و یک‌برگ دارد؛ مبلغ از جمع برنامهٔ اقساط، ضریب نسخه‌دار اتصال و تاریخ از قسط اول ساخته می‌شود. ثبت و تأیید صیاد واقعی به adapter مجاز یا بررسی مستند نیاز دارد. |
| API فروشنده | `helpers/MerchantApiGateway.php`, `helpers/MerchantAccessContext.php`, `api/v1/` | احراز اتصال و scopeهای دقیق، idempotency و مسیرهای نسخه‌دار در سورس موجود است. افزونهٔ WP فقط endpointهای allowlist‌شده را فراخوانی می‌کند. |
| چندفروشنده | `database/migrations/2026_10_23_multi_merchant_core_phase2.sql`, `models/Merchant*.php`, `controllers/MerchantPortalController.php` | فروشنده، سایت، اتصال، عضویت، اعتبارنامه و نسخهٔ سیاست با مرزبندی tenant در مدل وجود دارند. هرگونه migration واقعی باید با DB آزمایشی جداگانه تأیید شود. |
| تسویه و کمیسیون | `helpers/MerchantSettlementCalculator.php`, `helpers/MerchantSettlementService.php`, `helpers/MerchantCommissionService.php`, `database/migrations/2026_10_23_settlement_commission_phase4.sql` | ثبت تعهد، ذخیرهٔ نقدینگی، دستهٔ پرداخت، کمیسیون و reversalهای تعریف‌شده موجودند؛ اتصال بانکی و تصمیم حقوقی دربارهٔ مالک مطالبات موجود نیست. |
| حسابداری داخلی | `plugins/PromaAccounting/`, `plugins/PromaAffiliate/`, `plugins/PromaInventoryPartners/`, `plugins/PromaSignConnect/`, `plugins/PromaZarinpal/`, `plugins/PromaRepair/` | افزونه‌های موجود در حد قراردادهای رویدادی به چرخهٔ فروشنده متصل می‌شوند. Affiliate reward، کمیسیون فروشنده و سود مشتری باید ledgerهای جدا بمانند. |
| افزونهٔ WordPress | `wordpress-plugins/proma-pay-woocommerce/` | اتصال چندگانه، رمزنگاری credential، cache سیاست، لاگ کم‌داده، quote، Classic Checkout، Blocks، HPOS و adapter پیش‌پرداخت در سورس وجود دارند. نسخهٔ فعلی RC و آزمایشی است. |

## تاریخچهٔ پیاده‌سازی فازها در شاخه

| فاز | مسیرهای مهم / commit حاضر در تاریخچه |
|---|---|
| ۱ — موتور مالی | `helpers/InstallmentFinancialCalculator.php`; `793c67a` |
| ۲ — هستهٔ چندفروشنده | `MerchantAccessContext`, `MerchantAccount`, `MerchantConnection`; `ca7b750` |
| ۳ — ضمانت | `MerchantGuarantee*`; `bdd0cec` |
| ۴ — تسویه و کمیسیون | `MerchantSettlement*`, `MerchantCommission*`; `bcdbd3b` |
| ۵ — API | `MerchantApiGateway`, routeهای `api/v1`; `5a2689b` |
| ۶ — افزونهٔ WordPress | `wordpress-plugins/proma-pay-woocommerce`; `6e6a40b` |
| ۷ — Checkout و snapshot | `class-promapaywc-commerce.php`, `2026_10_23_checkout_quote_snapshot_phase7.sql`; `c929aae` |
| ۸ — اتصال افزونه‌های موجود | `SystemOutbox` و providerهای افزونه؛ `2988b72` |
| ۹ — پنل و گزارش فروشنده | `controllers/MerchantPortalController.php`, `views/merchant-portal/`; `4045f3c` |
| ۱۰ — بستهٔ آزمایشی و release gate | `tools/package-woocommerce-plugin.php`, `tests/woocommerce_release_package_phase10.php`; `3043bab`, `30001a0`, `8aeed40` |

وجود commitها به‌معنی اجرای دوباره یا موفقیت فعلی همهٔ آزمون‌های فازها نیست. در این نشست هیچ تست PHP به‌دلیل نبود runtime اجرا نشد.

## وضعیت Laravel و فروشگاه داخلی

- پوشهٔ runtime `laravel/` در checkout فعلی وجود ندارد و معماری فعال Laravel نیست.
- جست‌وجوی متن همچنان نام Laravel را در اسناد تاریخی، راهنمای سبک، داده‌های نمونهٔ JavaScript و نام آیکن‌های Font Awesome نشان می‌دهد. این موارد به‌تنهایی وابستگی اجرایی Laravel نیستند.
- WooCommerce فقط integration بیرونی است؛ فروشگاه داخلی حذف‌شده دوباره ساخته نشده است.
- قبل از بستهٔ نهایی، جست‌وجوی مخزن باید با allowlist فایل‌های تاریخی/نمونه ثبت شود تا ارجاع اجرایی باقی‌مانده با false positive اشتباه نشود.

## ممیزی بسته‌های Bakala

| بسته | اندازه | تعداد ورودی ZIP | نسخهٔ مشاهده‌شده |
|---|---:|---:|---|
| `2- Themes/bakala.zip` | 31,649,109 بایت | 2,369 | Bakala Theme، نسخهٔ 10.4.33 |
| `2- Themes/bakala-child.zip` | 241,508 بایت | 10 | Bakala Theme Child، نسخهٔ 1.0.0 |

فایل `.DS_Store` کنار بسته‌ها نیز وجود دارد؛ کد قالب نیست. فایل‌های ZIP در Git قرار نگرفتند و محتوای آن‌ها از روی stream فقط‌خواندنی بررسی شد. بستهٔ جداگانهٔ افزونه داخل این ZIPها پیدا نشد؛ سورس ادغام‌ها و کتابخانهٔ TGMPA داخل قالب قرار دارد. در header قالب فیلدهای `Requires at least`, `Requires PHP`, `Requires Plugins` یا نسخهٔ WooCommerce ثبت نشده‌اند؛ بنابراین سازگاری نسخه‌ها از metadata قالب قابل اثبات نیست. هیچ ورودی ZIP به‌عنوان encrypted علامت‌گذاری نشده است و در بررسی محدود حاضر loader شناخته‌شدهٔ ionCube/SourceGuardian پیدا نشد. این بررسی جایگزین بازبینی کامل مجوز یا تحلیل استاتیک امنیتی قالب نیست.

فایل style نسخه و نام قالب را می‌دهد، اما سند مجوز تجاری قابل اتکا برای بازنشر بسته شناسایی نشد. بنابراین فقط metadata و مسیرها در گزارش آمده‌اند؛ کد، asset یا ZIP قالب در افزونه یا artifact انتشار کپی نمی‌شود. مکانیزم license قالب نیز دستکاری یا دور زده نشده است.

## نقشهٔ سازگاری Bakala 10.4.33

| بخش | شواهد سورس قالب و hookها | اثر و خطر تداخل |
|---|---|---|
| صفحهٔ محصول و قیمت | `bakala/woocommerce/content-single-product.php`, `single-product/templates/bakala.php`, `single-product/templates/boxed.php`, `single-product/price.php`; هر دو layout، `woocommerce_single_product_summary` را صدا می‌زنند. | محل استاندارد اولویت ۲۵ در افزونه بین خلاصه/قیمت و فرم خرید قرار دارد. Adapter به selector قالب وابسته نیست. |
| Variation و خرید | `single-product/add-to-cart/variable.php`, `variation-add-to-cart-button.php`; رویداد استاندارد Woo `found_variation`, `reset_data` در سورس قالب استفاده می‌شود. | قیمت Variation از سرور مرکزی quote می‌شود. پاسخ‌های دیررس لغو/نادیده گرفته می‌شوند؛ Variation ناموجود widget را پاک می‌کند. |
| دکمهٔ اقساطی قالب | `inc/integrations/woocommerce/single-functions-pc-two.php` و تنظیمات `inc/redux-framework/sections/woocommerce.php`; `bakala_snapppay_btn_product()` در `woocommerce_after_add_to_cart_button` با اولویت ۹۹ یا در layout شرطی `bakala_after_add_to_cart` ثبت می‌شود. | Proma Pay از callback، selector، CSS class یا منطق SnappPay استفاده نمی‌کند. دکمه‌ها مستقل باقی می‌مانند. هیچ مسیر رسمی برای تبدیل SnappPay به Proma Pay دیده نشد. |
| Quick View | `bakala/quickview.php` و قالب‌های محصول سفارشی وجود دارند. | JS افزونه بر DOM افزوده‌شدهٔ پویا مقداردهی می‌شود و روی Bakala frontend نیز بارگذاری می‌شود. رفتار Quick View باید در نصب واقعی آزموده شود. |
| سبد و AJAX | `bakala/functions.php`, `woocommerce/cart/cart.php`, `cart-totals.php`, `cart/mini-cart.php`; endpointهای قالب `bk_add_to_cart`, `bk_update_cart_quantity`, `bk_remove_cart_item`, رویدادهای fragment و hookهای رسمی Woo دیده شدند. | افزونه مبلغ نهایی را در REST endpoint از cart سمت سرور می‌خواند؛ تغییر سبد نباید بر quote قبلی باقی بماند. تست هم‌زمان quote با AJAX قالب هنوز اجرا نشده است. |
| Checkout کلاسیک | `page-checkout.php`, `template-parts/content/page-checkout-pc.php`, `page-checkout-mobile.php`, `woocommerce/checkout/form-checkout.php`, `form-checkout-two.php`, `review-order.php`, `form-billing.php`. فرم‌ها hookهای رسمی checkout را نگه می‌دارند. | `form-checkout-two.php` چند callback پیش‌فرض را موقتاً حذف و دوباره اضافه می‌کند و بخش سفارش/پرداخت را AJAX می‌کند. نمایش gateway Proma Pay در layoutهای واقعی نیازمند آزمون مرورگر است. |
| Checkout Blocks | قالب بررسی‌شده override اختصاصی برای Checkout Blocks نشان نمی‌دهد؛ افزونه `AbstractPaymentMethodType` و ثبت Blocks دارد. | ادغام بلوکی از WooCommerce است؛ از این بسته به‌تنهایی سازگاری Block ثابت نمی‌شود. |
| ورود، موبایل و کد ملی | `woocommerce/checkout/login-checkout.php`, `inc/setting.php`, `woocommerce/account.php`, `woocommerce/myaccount/*`, integration مربوط به Address Plus. تنظیم Digits و fieldهای روشن/اجباری/اعتبارسنجی کد ملی وجود دارند. مقدار حساب در meta `bakala_national_code` دیده می‌شود و ذخیرهٔ پروفایل آن از `sanitize_text_field` استفاده می‌کند. | وجود شماره یا کد ملی در فیلد، اثبات احراز هویت نیست. پیاده‌سازی افزونه آن‌ها را به‌عنوان اثبات هویت فرض نمی‌کند. بررسی API واقعی Digits/اعتبارسنجی و چرخهٔ checkout باید روی نصب فعال انجام شود. |
| ظاهر و ریسپانسیو | `assets/css/`, `assets-mobile/css/`, `assets/js/`, `assets-mobile/js/`; قالب RTL، breakpoint موبایل و حالت‌های ظاهری مستقل دارد. | widget RTL از فونت، رنگ متن و عرض محتوای میزبان ارث می‌برد؛ پس‌زمینهٔ ثابت روشن حذف شده تا در dark mode با متن قالب تضاد نداشته باشد. |

## تغییرهای انجام‌شده برای Bakala

- `wordpress-plugins/proma-pay-woocommerce/includes/class-promapaywc-bakala-compatibility-adapter.php`: تشخیص قالب و child theme از `get_template()`, `get_stylesheet()` و text domain؛ ثبت hook استاندارد WooCommerce و fallback محدود Bakala در `woocommerce_after_single_product_summary`. fallback فقط یک بار برای هر محصول اجرا می‌شود و بدون ذخیرهٔ دادهٔ مالی است.
- `class-promapaywc-commerce.php`: ثبت widget از طریق adapter.
- `class-promapaywc-gateway.php`: بارگذاری JS صفحهٔ محصول روی Bakala frontend برای Quick View؛ سایر قالب‌ها از سطح محصول و آرشیو استاندارد استفاده می‌کنند.
- `assets/js/product-widget.js`: لغو quote قبلی، کنترل ترتیب پاسخ، پاک‌کردن quote منقضی UI هنگام reset و رد محصول ناموجود. قیمت، ضریب چک و تاریخ از response مرکزی می‌آیند.
- `assets/css/commerce.css`: رنگ و پس‌زمینه به قالب میزبان سپرده شده و RTL افزونه حفظ شده است.
- `tests/static.php`: کنترل‌های ایستا برای تشخیص قالب، fallback، جلوگیری از پاسخ stale و DOM پویا اضافه شد.
- `tools/package-woocommerce-plugin.php` تمام فایل‌های `includes/` و `assets/` را بازگشتی جمع می‌کند؛ adapter در build بعدی داخل ZIP خواهد آمد. ZIP تولیدشدهٔ قبلی تا اجرای دوبارهٔ build قدیمی می‌ماند.

## طرح Migration و اجرای امن

1. قرارداد و جداول مستقیم تغییر داده نشوند. داده‌های سفارش/قرارداد Woo با شناسهٔ خارجی یکتا به مدل قرارداد وصل شوند.
2. migrationهای فاز ۲، ۳، ۴، ۵ و snapshot فاز ۷ افزایشی‌اند؛ schema نصب تازه باید با آن‌ها همسان بماند. migrationهای DB فقط روی DB دورریختنی، سپس staging و همراه backup بررسی شوند.
3. quote، سیاست مالی، ضمانت و قرارداد باید snapshot تغییرناپذیر با نسخهٔ policy/calculation باشند. تغییر policy بر قرارداد قبلی اثر نگذارد.
4. idempotency باید برای order create، contract submit، پیش‌پرداخت، webhook و refund در هر دو سمت enforce شود. تلاش مجدد شبکه نباید قرارداد یا پرداخت تکراری بسازد.
5. مدل تسویه واقعی تا وقتی طلبکار اقساط، تأمین‌کنندهٔ وجه فروشنده، مسئول نکول/مرجوعی و قرارداد همکاری تعیین و تأیید نشده غیرفعال بماند. پول وصول‌نشده در گزارش وجه نقد آزاد ظاهر نشود.
6. ساخت artifact باید با manifest و checksum انجام شود؛ `2- Themes/`, اعتبارنامه، دیتابیس، backup، storage و log نباید وارد ZIP یا Git شوند.

## طرح آزمون و وضعیت اجرای همین ممیزی

تست‌های موجودِ مرتبط شامل `tests/installment_financial_calculator.php`, `tests/merchant_core_phase2.php`, `tests/merchant_guarantee_engine_phase3.php`, `tests/merchant_settlement_commission_phase4.php`, `tests/merchant_public_api_phase5.php`, `tests/merchant_plugin_integration_phase8.php`, `tests/merchant_dashboard_phase9.php`, `tests/woocommerce_release_package_phase10.php` و `wordpress-plugins/proma-pay-woocommerce/tests/{unit,static}.php` هستند.

| آزمون در این نشست | نتیجه |
|---|---|
| PHP lint، تست static/unit و release gate | اجرا نشد: `php` در PATH یا runtime شناخته‌شدهٔ محیط موجود نیست. |
| تست Migration و API روی MySQL/MariaDB | اجرا نشد: DB آزمایشی/DSN در این محیط تأیید نشد. |
| نصب WordPress + WooCommerce + Bakala و اجرای Classic/Blocks/HPOS | اجرا نشد: محیط WordPress واقعی حاضر نیست. |
| آزمون Variation سریع، ناموجود، Quick View، cart AJAX، SnappPay کنار Proma Pay، Digits، موبایل و dark mode | سناریوها مشخص‌اند، اما تست مرورگر عملی اجرا نشده است. |
| جست‌وجوی package Bakala و metadata ZIP | انجام شد: نسخهٔ parent/child، مسیرهای Woo و hookهای مورد استفاده ثبت شدند؛ archiveها در Git اضافه نشدند. |

## بلوک‌کننده‌های تجاری و حقوقی

1. مالک مطالبات اقساط مشتری، دریافت‌کنندهٔ اقساط، منبع پرداخت زودهنگام فروشنده، مسئول نکول و سیاست chargeback/مرجوعی هنوز نیازمند تصویب حقوقی و مالی مستند هستند.
2. ذی‌نفع قانونی چک، اعتبار فرایند صیاد و روش مجاز بررسی دستی/سرویس صیاد باید در عملیات واقعی تأیید شود.
3. قرارداد Merchant باید نرخ/کسورات، دورهٔ پرداخت، تأمین نقدینگی و روش اصلاح/فسخ را تعیین کند.
4. اعتبار مجوز بازنشر سورس Bakala از فایل‌های داخل ZIP اثبات نشد؛ بستهٔ تجاری فقط محلی می‌ماند.

## معیارهای پذیرش باز

- تست‌های PHP و migration روی محیط ایزوله با runtime مناسب.
- تست واقعی نسخهٔ قالب 10.4.33، child 1.0.0، WooCommerce اعلام‌شده در محیط تست و WordPress؛ classic checkout، layout دوم Bakala، Blocks، HPOS و refund.
- تأیید امنیتی API، webhook و tenant isolation در اجرا، نه صرفاً تست ایستا.
- تصمیم حقوقی/مالی دربارهٔ مالکیت طلب و وجه تسویه و سپس adapter بانکی مجاز.
- اجرای مجدد package/build، بررسی manifest/ZIP و ایجاد PR از شاخهٔ مشخص و قابل بازبینی.

تا بسته‌شدن این موارد، نسخه RC/آزمایشی بماند و نتیجه را Production Ready تلقی نکنید.
