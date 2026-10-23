# ممیزی Phase 0 — پلتفرم چندفروشگاهی و WooCommerce

- تاریخ ممیزی: 2026-10-23
- دامنه: سورس PHP، مدل داده، افزونه‌ها، مسیرهای مالی، آزمون‌های موجود و شاخه‌های Git مرتبط
- نسخه محصول مشاهده‌شده: Proma Pay 7.0.3 در شاخه محلی؛ PR پاک‌سازی برای ارتقای 7.0.3 به 7.0.4 نوشته شده است.
- مبنای مخزن: `origin/main` در `173d013`; شاخه PR شماره 15 در `2d09d08`.

## خلاصه

Proma Pay یک برنامه PHP سفارشی با MVC داخلی، PDO و MySQL/MariaDB است. افزونه‌های داخلی از Plugin Manager خود پروژه، manifest JSON، provider، مجوزها، routeها و migrationهای SQL استفاده می‌کنند. WordPress و WooCommerce در محصول فعلی وجود ندارند.

قابلیت‌های قرارداد، برنامه اقساط، دریافت پرداخت، انتساب معرف، ثبت اسناد حساب کاربری، پیام‌رسانی، اسناد قرارداد و موجودی/شرکا قابل استفاده مجدد هستند؛ اما این‌ها معادل پلتفرم چندفروشگاهی نیستند. در سورس بررسی‌شده موجودیت Merchant و Site، اعتبارنامه اتصال API، API عمومی نسخه‌دار، نسخه سیاست قیمت‌گذاری اقساط، گردش تأیید چک صیادی یا بدهی/تسویه نقدینگی فروشنده وجود ندارد. افزونه مستقل WooCommerce نیز پیدا نشد.

**نتیجه Phase 0:** مبنای فنی و شکاف‌ها استخراج شد. برای ادامه توسعه می‌توان از قرارداد، پرداخت، Plugin Manager و outbox موجود استفاده کرد؛ مدل تسویه واقعی و فعال‌سازی پولی باید تا تعیین مالک مطالبات، منبع تأمین وجه و تعهدات قراردادی غیرفعال بماند. این گزارش تأیید حقوقی یا آمادگی Production نیست.

## دامنه و کیفیت شواهد

- **مشاهده‌شده:** خواندن سورس، manifestها، migrationها، schema نصب، آزمون‌ها و وضعیت Git؛ جست‌وجوی مسیرهای مرتبط با قرارداد، اقساط، پرداخت، ضمانت، حسابداری، API و Ecommerce.
- **استنباط‌شده:** شکاف‌هایی که از نبود موجودیت/مسیر در کد بررسی‌شده یا از تفاوت میان مدل‌های موجود و نیاز محصول نتیجه می‌شوند.
- **نامعلوم:** schema و داده‌های دیتابیس عملیاتی، وضعیت افزونه‌های فعال روی نصب واقعی، مفاد قراردادهای تجاری، روش تأمین نقدینگی، مشخصات درگاه‌های فروشندگان و الزامات حقوقی نهایی. در این ممیزی به دیتابیس واقعی وصل نشدیم.
- این گزارش بررسی معماری و سطح حمله است؛ تحلیل کامل جریان داده با parser معنایی، آزمون نفوذ، ارزیابی حقوقی یا اثبات آسیب‌پذیری امنیتی انجام نشده است. بنابراین هیچ Finding امنیتی به‌عنوان آسیب‌پذیری تأییدشده اعلام نمی‌شود.
- در این فاز migration یا عملیات روی دیتابیس اجرا نشد.

## وضعیت Git و مبنای توسعه

| منبع | وضعیت مشاهده‌شده | کاربرد در این ممیزی |
|---|---|---|
| `origin/main` | commit `173d013`; مبنای PR #15 | نسخه پایه‌ای که هنوز کد Ecommerce و پوشه Laravel را در Git دارد |
| PR #15، `cleanup/remove-internal-ecommerce-and-laravel-20261023` | commit `2d09d08`; باز و ادغام‌نشده؛ base برابر `main`; 318 فایل در PR | در این شاخه Ecommerce داخلی و اپ مستقل Laravel حذف شده و مسیر قدیمی Ecommerce پاسخ 410 می‌دهد |
| `F:\proma-pay` | شاخه `codex/partnership-ledger-enterprise` در `8a3ead3`; 215 حذف محلی زیر `laravel/`، تغییرات محلی دیگر و بدون merge-base با `origin/main` | نسخه کاری کاربر است؛ تغییرات آن نباید با commitهای این Phase مخلوط شود و شاخه مناسبی برای PR به `main` نیست |

وضعیت PR #15 در زمان ممیزی: باز و قابل ادغام طبق metadata GitHub، اما ادغام نشده و CI برای head آن گزارش نشده است. متن PR آزمون کامل نصب/ارتقا و آزمون‌های DB/HTTP/UI/ایمیل/Backup و Restore را اجراشده اعلام نمی‌کند. PR باید پیش از انتشار با همان آزمون‌های ایزوله تکمیل شود.

## معماری مشاهده‌شده

```text
مرورگر یا کاربر داخلی
  → index.php
  → bootstrap.php (autoload سفارشی، نشست، کنترل درخواست)
  → core/Router.php
  → Controller و مجوز
  → Model / helper / service دامنه
  → PDO
  → MySQL یا MariaDB

مسیر افزونه:
core/PluginManager
  → plugins/<plugin>/plugin.json
  → Service Provider
  → route / permission / hook / service
  → migration و جدول‌های افزونه
```

مرزهای اصلی در `core/Router.php`, `core/PluginManager.php`, `core/PluginManifest.php`, `core/PluginServiceProviderInterface.php`, `core/Model.php` و `models/PluginRegistry.php` هستند. منطق اصلی قراردادها در `models/Contract.php`, اقساط و محاسبات در `models/Installment.php` و `helpers/FinanceHelper.php` و `helpers/MoneyMath.php`، اسناد در `models/ContractDocument.php`، پرداخت در `controllers/PaymentsController.php` و `helpers/PaymentGroupService.php` قرار دارد. UI عمدتاً PHP server-rendered است.

Plugin Manager افزونه‌ها را از `plugin.json` کشف می‌کند و provider افزونه route، مجوز و hook را ثبت می‌کند. Migrationهای افزونه در manifest فهرست شده، checksum و نتیجه اجرای آن‌ها در registry ثبت می‌شود. migrationهای Core زیر `database/migrations/` و schema نصب تازه در `database/proma-pay-install.sql` است. در head شاخه PR #15، schema پایه 81 دستور ساخت جدول و 59 migration مرکزی دارد؛ 6 افزونه رسمی حفظ شده‌اند و در مسیر `tests/` و `plugins/*/tests/` تعداد 173 فایل PHP/JS/CJS آزمون و fixture پیدا شد (169 فایل PHP). پوشه CI با workflow در این head وجود ندارد. این مرزبندی برای افزودن API و مدیریت Merchant مناسب‌تر از واردکردن WordPress به Core است.

## نقشه وابستگی و استفاده مجدد

| جریان | قابلیت فعلی و مسیرهای اصلی | استفاده مجدد و محدودیت |
|---|---|---|
| ورود HTTP و دسترسی | `index.php` → `bootstrap.php` → `core/Router.php` → `core/Auth.php`, CSRF و permissionها | مرز درخواست و دسترسی موجود است؛ احراز هویت کاربر داخلی جایگزین کلید، scope، امضای درخواست و محدودیت نرخ API فروشگاه نیست. |
| قرارداد | `ContractsController` → `Contract::createWithInstallments()` → اقساط, ضمانت عمومی, snapshot ضامن و سند قرارداد | قرارداد موجود مرجع مشتری و اقساط است. این مسیر هنگام ساخت، قرارداد را `active` ثبت می‌کند؛ نباید بدون state machine و کنترل‌های Woo به‌عنوان checkout ناقص فراخوانی شود. |
| محاسبه اقساط | `FinanceHelper::installmentAmount()` و `MoneyMath` → `Contract::generateInstallments()` | روش ساده و مرکب و عدد صحیح/گردکردن موجود است؛ روش مانده‌کاهشی و quote نسخه‌دار در مدل قرارداد فعلی وجود ندارد. هر قسط با مبلغ رُندشده برابر درج می‌شود؛ باید قرارداد نهایی و جمع اقساط به‌صورت صریح snapshot و تطبیق داده شوند. |
| ضمانت | `contract_guarantees`, `ContractDocument::saveGuarantee()` و اسناد قرارداد | اطلاعات عمومی نوع/تعداد/مبلغ/شناسه دارد. موجودیت مستقل چک صیادی، تایید صیاد، ذی‌نفع، تاریخ ثبت/تایید، تاریخچه رویداد و شرط تطابق تاریخ با اولین قسط مشاهده نشد. |
| دریافت وجه | `PaymentsController`, `PaymentGatewayRegistry`, `PaymentGroupService`, `payment_groups`, `payment_allocations`, outbox و reconciliation | برای دریافت/تخصیص پرداخت قرارداد و callback تکراری الگوهای idempotency و تطبیق وجود دارند. این‌ها adapter پیش‌پرداخت جزئی فروشنده WooCommerce نیستند. |
| دفتر مالی قرارداد | `FinancialLedgerService`, `financial_ledger_events`, `financial_ledger_lines` | رویدادهای مانده قرارداد و reversal را ثبت می‌کند؛ دفتر دریافت/بدهی اقساط است و به‌تنهایی دفترکل دارایی نقد یا بدهی Merchant نیست. |
| حسابداری و کمیسیون | `PromaAccounting`، `LedgerService`, `CommissionService`, `CommissionCalculationService` | کمیسیون و مانده کاربران، ثبت idempotent و معکوس‌سازی دارد. ledger آن یک سطر با جهت و balance قبل/بعد برای یک حساب کاربر ثبت می‌کند؛ از سورس بررسی‌شده دفترکل استاندارد دوطرفه با ردیف‌های بدهکار/بستانکار برای تسویه فروشنده اثبات نشد. |
| معرفی مشتری | `PromaAffiliate` و `AffiliateService` | انتساب معرف/پاداش قرارداد جداگانه‌ای دارد؛ باید از کمیسیون فروشنده و سود اقساط تفکیک شود. |
| کالا و شرکا | `PromaInventoryPartners` | موجودی، خرید/فروش، سهم شرکا، دفتر شرکا و تسویه شرکا هستند؛ این «Merchant» فروشگاه خارجی یا طلبکارِ قرارداد تسویه فروشنده نیست. وابستگی این افزونه برای هر فروشگاه خارجی نباید اجباری شود. شاخه محلی `F:\proma-pay` افزونه alpha جداگانه `PromaPartnershipLedger` هم دارد، اما این افزونه در head شاخه PR #15 نیست و مبنای قابلیت‌های رسمی این گزارش محسوب نمی‌شود. |
| پیام و امضا | `PromaSignConnect` | زیرساخت پیام، OTP و سند/امضا قابل اتصال از رویداد است؛ تأیید قانونی چک یا قرارداد همکاری فروشنده را خودکار اثبات نمی‌کند. |
| درگاه | `PromaZarinpal`, `PaymentGatewayRegistry` | درگاه سامانه Proma Pay است. اتصال آن به درگاه بانکی مستقل هر فروشگاه و دریافت پیش‌پرداخت جزئی را تضمین نمی‌کند. |
| تعمیر | `PromaRepair` | گردش اختیاری تعمیر و حساب تعمیرکار است؛ نباید پیش‌شرط قرارداد WooCommerce شود. |

در شاخه‌ای که حذف Ecommerce در PR #15 اعمال شده، جست‌وجوی مسیرهای domain نشان می‌دهد WooCommerce، `api/v1`, merchant site/connection و چک صیادی هنوز وجود ندارند. routeهای `api/plugin/inventory-partners/...` مسیرهای داخلی افزونه برای lookup هستند، نه API عمومی احراز هویت‌شده برای فروشگاه‌های ثالث.

## وضعیت Ecommerce داخلی و Laravel

- در `origin/main` مسیر Ecommerce از قبل در Router بازنشسته/مسدود شده، اما کنترلر، مدل، view، تنظیمات قدیمی، schema نصب و migrationهای `ecommerce_*` هنوز وجود دارند.
- در شاخه PR #15 کد و schema نصب تازه Ecommerce حذف شده‌اند؛ route بازمانده پاسخ 410 می‌دهد و داده‌های جدول‌های نصب‌های قدیمی پاک نمی‌شوند. این جداسازی با الزام «فروشگاه داخلی را برنگردان» سازگار است؛ API آینده باید فقط سرویس اقساط WooCommerce را ارائه کند.
- در `origin/main` پوشه `laravel/` شامل 215 مسیر tracked است. در working tree فیزیکی `F:\proma-pay` این فایل‌ها حذف شده‌اند ولی حذف‌ها در آن شاخه commit نشده‌اند. PR #15 حذف آن‌ها را در یک شاخه قابل review ثبت می‌کند. این گزارش برای ادامه بر مبنای کد PR #15 نوشته شده است تا محصول نهایی بدون اپ مستقل Laravel و Ecommerce داخلی باشد.
- ارجاع‌های باقی‌مانده به Laravel در build scriptها یا assetهای قالب ثالث باید با جست‌وجوی کامل در Phase نهایی دسته‌بندی شوند؛ هر اشاره متنی یا نام آیکن به‌تنهایی dependency اجرایی محسوب نمی‌شود.

## شکاف‌های محصول نسبت به درخواست

| اولویت | شکاف مشاهده‌شده | پیامد پیش از پیاده‌سازی |
|---|---|---|
| مسدودکننده عملیاتی | مالک مطالبات مشتری، مالک سود اقساط، منبع وجه پرداخت زودهنگام فروشنده، مسئول نکول و مرجوعی در قراردادهای تجاری هنوز مشخص نیست. | می‌توان schema و مسیر staging ساخت؛ هیچ پرداخت تسویه واقعی یا ادعای موجودی نقد قبل از تصمیم حقوقی/مالی فعال نشود. |
| P0 فنی | ایجاد قرارداد Core آن را مستقیماً active می‌کند و update استاندارد می‌تواند مبلغ، نرخ، مدت، سررسید و جدول اقساط را بازنویسی کند. | flow خارجی باید state machine و snapshot تغییرناپذیر داشته باشد؛ قرارداد فعال/امضاشده تنها با amendment رسمی تغییر کند. |
| P0 مالی | برنامه فعلی فقط flat/simple و compound را می‌شناسد؛ amortized/reducing-balance، rounding policy نسخه‌دار، quote مالی و تطبیق دقیق جمع برنامه با مبلغ قرارداد در مدل origination موجود نیست. | ابتدا calculator مستقل و deterministic، سپس snapshot و رگرسیون قراردادهای قبلی. |
| P0 ضمانت | چک صیادی با ضریب اتصال، الزام یک چک، تاریخ برابر اولین قسط و تایید رسمی/manual ثبت‌شده، مدل نشده است. | قرارداد کانال Woo تا تأیید ضمانت نباید قابل فعال‌سازی یا ارسال به fulfillment باشد. |
| P0 چندمستاجری | هیچ `merchant`, `merchant_site`, `api_connection` یا credential-per-connection در schema فعلی دیده نشد. | دسترسی و queryها باید از ابتدا connection/merchant-scoped باشند؛ استفاده از `seller_user_id` برای این مرز کافی نیست. |
| P0 API | API عمومی نسخه‌دار، امضای HMAC، timestamp/replay defense، scope، rotation/revocation، rate limit و webhook delivery پایدار برای این اتصال وجود ندارد. | از routeهای داخلی plugin به‌عنوان API ثالث استفاده نشود. |
| P1 تسویه/حسابداری | تسویه شرکا و حساب کاربران موجود است؛ subledger بدهی Merchant، نقد در دسترس، exposure و journal دوطرفه‌ی قابل تطبیق برای پرداخت‌های فروشنده اثبات نشد. | کمیسیون، referral reward، سود اقساط، طلب فروشنده و پول نقد باید حساب‌ها و reversalهای جدا داشته باشند. |
| P1 افزونه WP | افزونه نصب‌پذیر WooCommerce، classic/Blocks checkout، HPOS، CRUD سفارش و payment adapter جزئی وجود ندارد. | نسخه‌ها و سازگاری را فقط پس از آزمون محیط ماتریسی اعلام کنیم. |
| P1 آزمون عملیاتی | محیط DB دورریختنی برای نصب/ارتقا، callback بانکی، WP/Woo و restore در دسترس این ممیزی نبود. | تست‌های دیتابیسی/HTTP/پرداخت را روی fixture مستقل و gateway fake انجام دهیم؛ داده واقعی استفاده نشود. |

## ریسک‌های مالی و حقوقی

1. **مالکیت و نقدینگی:** پرداخت مانده قیمت به فروشنده پیش از وصول اقساط، تعهد نقدینگی می‌سازد. هنوز روشن نیست کدام شخص/نهاد منبع وجه است و مالک طلب اقساط چه کسی است. طلب وصول‌نشده نباید Cash Available یا وجه امانی گزارش شود.
2. **چک و قرارداد:** مبلغ اسمی چک، طلب قطعی نیست. اطلاعات بانکی/ذی‌نفع و تاریخ درج‌شده باید snapshot مستقل داشته باشند. تصویر چک به‌تنهایی تایید صیاد نیست.
3. **عدم تغییر گذشته:** قرارداد و سیاست مالی قبلی باید از نرخ‌ها و سیاست جاری مستقل بمانند. مسیر update موجود در Contract برای قرارداد مستقیم مفید است، اما نباید روی snapshotهای کانال جدید اثر پنهان بگذارد.
4. **برگشت/نکول:** تسویه زودهنگام فروشنده و بازپرداخت مشتری رویدادهای متفاوت هستند. برگشت، dispute و default باید با سند معکوس و حفظ ردپا ثبت شوند؛ حذف یا overwrite پرداخت گذشته مجاز نیست.
5. **جداسازی فروشنده‌ها:** شناسه‌های sequential سفارش یا فروشنده، جای authorization نیستند. هر request باید connection معتبر را به Merchant/Site سمت سرور نگاشت کند و همه queryهای داده/وجوه همان scope را اعمال کنند.
6. **قرارداد اجرایی:** امضای قرارداد همکاری باید مشخص کند طلبکار اقساط، دریافت‌کننده اقساط، تامین‌کننده وجه فروشنده، کسورات، بازپرداخت و مسئول نکول چه کسی است. این مورد نیازمند تایید ذی‌صلاح حقوقی/مالی است و از ممیزی کد قابل تعیین نیست.

## پیشنهاد معماری برای فازهای بعد

- Core فعلی و Plugin Manager حفظ شود. یک افزونه داخلی مستقل برای merchant platform/API ایجاد شود؛ افزونه WooCommerce در پوشه‌ی integration جدا و ZIP نصب‌پذیر خودش ساخته شود.
- قرارداد/اقساط/پرداخت Core مرجع مالی بمانند. شناسه سفارش خارجی و نگاشت آن به contract در جدول mapping افزونه ثبت شود؛ سفارش/قسط core به‌صورت موازی بازسازی نشود.
- منطق quote در سرویس دامنه مستقل و بدون وابستگی به Controller/WordPress باشد. ورودی فروشگاه فقط قیمت نهایی/محتوای مجاز سفارش و انتخاب مشتری را حمل کند؛ نرخ، مدت، مبلغ نهایی و ضریب چک فقط از policy معتبر سمت سرور محاسبه شوند.
- Merchant، Site، Connection و credential مستقل باشند. credential خام به پاسخ API یا log برنگردد؛ secretها رمزگذاری‌شده با کلید خارج از دیتابیس نگه‌داری شوند، HMAC، timestamp، scope، rotation/revocation، rate limit، idempotency و audit اضافه شوند.
- چرخه order/quote/eligibility/down-payment/guarantee/contract/fulfillment/settlement به‌صورت state machine با transitionهای مجاز سمت سرور پیاده شود. outbox برای webhook و event خارجی پایدار و تکرارپذیر باشد.
- تسویه فقط از policy/قرارداد همکاری نسخه‌دار و خطوط تاییدشده محاسبه شود؛ کنترل Liquidity Reserve، Merchant Exposure، تعهدات سررسیدشده و reference بانکی قبل از دستور پرداخت لازم است.

## برنامه Migration افزایشی

1. **Baseline و snapshot:** از schema نصب تازه و یک clone دورریختنی دیتابیس v7.0.3 snapshot بگیرید. تعداد ردیف و جمع مالی `contracts`, `installments`, `payments`, `payment_groups`, `payment_allocations`, `contract_guarantees` و ledgerها را قبل/بعد مقایسه کنید. هیچ DB واقعی در این مرحله استفاده نشود.
2. **جداول افزونه‌ای:** موجودیت‌های merchant، site، connection، credential metadata، policy version، quote، external order mapping، guarantee record/events، settlement policy/batch/line، commission entry، webhook delivery/outbox و API audit را با prefix افزونه و کلیدهای foreign/unique مناسب اضافه کنید.
3. **مرجع مالی واحد:** جدول‌های تازه‌ی تسویه فقط شناسه قرارداد/فروشنده/سفارش و snapshot سیاست را نگه دارند. قرارداد و پرداخت اصلی در جداول Core بماند. از جدول موازی سفارش/پرداخت core اجتناب شود.
4. **سازگاری عقب‌رو:** رکوردهای قدیمی merchant/channel نداشته باشند و معتبر باقی بمانند. اگر اتصال Core لازم شد، ستون‌های nullable یا mapping افزونه با پیش‌فرض خنثی استفاده شود؛ backfill فقط با نگاشت قابل اثبات.
5. **نسخه و idempotency:** unique keyهای scope شده برای `(connection_id, external_order_id)`, `(connection_id, idempotency_key)` و رویدادهای مالی لازم تعریف شوند. snapshot سیاست و قرارداد هرگز با policy جدید بازنویسی نشود.
6. **اجرای کنترل‌شده:** migration افزونه از Plugin Manager ثبت و checksum شود. برای نصب تازه و ارتقا جدا تست شود. اصلاح داده و تغییر جداول فقط پس از نسخه آزمایشی، گزارش تطبیق و backup قابل restore انجام شود.
7. **Rollback:** migration داده موجود را حذف نکند. خاموش‌کردن connection و route، بازگردانی فایل/نسخه و جبران رویدادها با reversal مسیر rollback باشند؛ drop کردن سوابق مالی rollback محسوب نمی‌شود.

## طرح آزمون پذیرش

| دسته | آزمون‌های لازم | معیار قبولی |
|---|---|---|
| محاسبه مالی | Flat، compound، reducing balance؛ دقت نرخ و روز؛ روش گردکردن؛ مبلغ ۶٫۵ میلیون × ضریب ۲ = ۱۳ میلیون؛ تغییر policy؛ اختلاف آخرین قسط | خروجی قطعی و تکرارپذیر، جمع اقساط دقیقاً برابر تعهد snapshot، قرارداد قدیمی بعد از تغییر policy ثابت بماند. |
| کانال مستقیم و ضمانت | فروش مستقیم با نوع ضمانت مجاز؛ Woo با دقیقاً یک sayadi cheque؛ تاریخ چک برابر اولین قسط؛ رد تاریخ نامنطبق؛ تغییر تاریخ قبل/بعد از تایید | قوانین Woo روی فروش مستقیم اعمال نشوند؛ چک تاییدنشده قرارداد را فعال نکند؛ تغییر post-activation فقط با amendment. |
| چندفروشنده/API | دو Merchant، چند Site، دو Provider، credentialهای مجزا؛ دستکاری merchant/order ID؛ key rotation/revocation، scope، rate limit، timestamp/replay، SSRF، webhook signature | هیچ مشاهده یا mutation cross-tenant؛ credential لغوشده رد شود؛ مقصد خارجی allowlist شود. |
| idempotency و همزمانی | دو callback همسان/همزمان، قطع شبکه پس از تایید بانک، retry/backoff، duplicate external order، outbox retry | فقط یک پرداخت/قرارداد/settlement line؛ پرداخت تاییدشده گم نشود و callback تکراری state را دوباره تغییر ندهد. |
| تسویه و دفتر | هر N روز، هفتگی، دو تاریخ ماه شمسی، آخر ماه شمسی، تعطیلات، کسورات، اصلاح مصوب، برگشت و پرداخت ناموفق | مبلغ از قرارداد تجاری نسخه‌دار محاسبه، واجد شرایط شدن کامل کنترل شود و سند برگشت تاریخچه را حفظ کند. |
| Migration | نصب تازه، ارتقا از dump قدیمی v7.0.3، re-run، failure mid-migration، rollback/restore | داده قبلی و hash/جمع مالی بدون تغییر ناخواسته؛ unique indexها و migration registry صحیح؛ هیچ عملیات مخرب روی DB واقعی. |
| WooCommerce | gateway adapter جعلی و adapterهای پشتیبانی‌شده، classic checkout، Checkout Blocks/Store API، HPOS CRUD، کوپن/مالیات/حمل/موجودی، refund | partial payment سفارش را paid نکند؛ callback/refund با Core تطبیق یابد؛ فقط نسخه‌های واقعاً آزموده‌شده اعلام شوند. |
| افزونه‌های موجود | Accounting، Affiliate، Inventory/Partnership Ledger، SignConnect، Zarinpal و Repair فعال/غیرفعال طبق اختیار | رویدادها duplicate reward ندهند؛ plugin اختیاری شرط قرارداد نشود؛ اصلاح و reversal قابل تطبیق بماند. |

## معیار پایان Phase 0

- معماری و مرزهای Core/Plugin مستند شد.
- مسیرهای قرارداد، اقساط، ضمانت، پرداخت، حسابداری و Plugin Manager بررسی شدند.
- قابلیت‌های قابل استفاده مجدد و شکاف‌های لازم برای Merchant/Connection/API/Woo مشخص شدند.
- وضعیت Laravel و Ecommerce در `main`, PR #15 و working tree محلی تفکیک شد.
- خطرهای نقدینگی، مالکیت طلب، ضمانت و سازگاری داده ثبت شد.
- برنامه Migration افزایشی و طرح آزمون پذیرش آماده شد.

## شرط شروع تراکنش عملیاتی

فعال‌سازی پرداخت واقعی فروشنده تا تعیین کتبی مالک طلب اقساط، منبع تسویه، مسئول نکول/مرجوعی، نسخه قرارداد همکاری و تایید حقوقی/مالی مسدود بماند. می‌توان توسعه و آزمون sandbox را با merchant و policy غیرفعال ادامه داد؛ هیچ مبلغ وصول‌نشده‌ای در گزارش نقد موجود نشان داده نشود.