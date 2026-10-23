# فاز ۵ — دروازهٔ API عمومی

## وضعیت موجود

پیش از این فاز، اتصال فروشگاه، کلید API آزمایشیِ hash-only، scopeها و زمینهٔ tenant وجود داشت؛ ولی front controller نسخه‌دار، محدودسازی نرخ برای هر اتصال، مسیرهای عمومی، idempotency پایدار، درخواست سفارش قابل بازبینی و صف رویدادهای webhook وجود نداشت.

## طراحی

- endpoint نسخه‌دار از `/api/v1/...` وارد JSON front controller مستقل می‌شود و session ورود کاربران HTML را شروع نمی‌کند.
- احراز هویت فقط با Bearer آزمایشی `pmp_test_` است. TLS برای همهٔ درخواست‌های غیر loopback اجباری است؛ کلید زنده در این فاز صادر یا پذیرفته نمی‌شود.
- هر درخواست از اتصال فعال و سایت تأییدشده به دست می‌آید، scope همان اتصال را بررسی می‌کند و نرخ را روی بازهٔ دقیقه‌ای همان اتصال می‌بندد.
- `POST /quotes` فقط از policy مالی منتشرشده نرخ و محدودیت را می‌خواند. عدد پول و مدت باید integer باشند؛ نرخ ارسالی مشتری رد می‌شود. نسخه و hash سیاست همراه محاسبه برمی‌گردد.
- `POST /orders` با Idempotency-Key، payload hash و قید یکتای شناسهٔ سفارش، درخواست را با وضعیت `pending_review` ثبت می‌کند. این مسیر به‌تنهایی قرارداد مالی نمی‌سازد.
- `external_order_id` باید شناسهٔ opaque فروشگاه باشد و نباید شمارهٔ تماس، ایمیل یا شناسهٔ ملی داشته باشد.
- `POST /contracts` درخواست قرارداد را برای بازبینی داخلی ثبت می‌کند. مرجع مشتری فقط به‌صورت hash ذخیره می‌شود؛ قرارداد واقعی باید از مسیر داخلی احراز هویت، رضایت، بررسی اسناد/ریسک و ضمانت ساخته شود.
- `GET /contracts/{order_uuid}` و `GET /guarantees/{order_uuid}` فقط وضعیت متصل به همان tenant را بدون اطلاعات تماس یا شمارهٔ چک برمی‌گردانند.
- تغییر سفارش و درخواست قرارداد یک رویداد حداقلی و بدون PII در webhook outbox می‌گذارد. transport و امضای اختصاصی مقصد در اتصال افزونهٔ WordPress فاز ۶ تکمیل می‌شود؛ در این فاز هیچ تماس خروجی به فروشگاه انجام نمی‌شود.
- `GET /webhooks` فقط شمارندهٔ صف را برمی‌گرداند؛ `POST /connection/emergency-disable` با کد علت از فهرست محدود راه خاموش‌کردن فوری اتصال است و از API راه فعال‌سازی مجدد ندارد.

## مسیرها

Base URL برابر `/api/v1` است. درخواست‌های تغییردهنده باید `Content-Type: application/json` و `Idempotency-Key` داشته باشند.

| روش و مسیر | scope | کاربرد |
|---|---|---|
| `GET /connection` | `merchant.read` | وضعیت اتصال و محدودیت درخواست |
| `GET /policies/financial` | `policy:read` | دریافت snapshot محدودشدهٔ سیاست مالی منتشرشده |
| `POST /quotes` | `quote:create` | محاسبه از policy منتشرشده |
| `POST /orders` | `order:create` | ایجاد درخواست سفارش در انتظار بازبینی |
| `POST /contracts` | `contract:write` | ارسال درخواست قرارداد برای همان order UUID |
| `GET /contracts/{order_uuid}` | `contract:read` | وضعیت درخواست و قرارداد متصل |
| `GET /guarantees/{order_uuid}` | `guarantee:read` | وضعیت ضمانت متصل، بدون شمارهٔ چک |
| `GET /webhooks` | `webhook:read` | شمارندهٔ outbox |
| `POST /connection/emergency-disable` | `connection:manage` | خاموش‌کردن غیرقابل‌بازگشت از API |

نمونهٔ quote: `{"cash_price_toman":1000000,"down_payment_toman":250000,"months":6,"interest_method":"flat"}`. نرخ از policy می‌آید. برای سفارش، `external_order_id` به همین payload اضافه می‌شود؛ برای درخواست قرارداد از `{"order_uuid":"...","external_customer_reference":"opaque-reference"}` استفاده می‌شود. پاسخ موفق در `data` و خطا در `error.code` قرار می‌گیرد.

## معیارهای پذیرش

- توکن نامعتبر، scope غایب، HTTP عمومی، روش یا JSON نامعتبر، کلید تکرار ناسازگار، نرخ خارج policy و ورودی پول اعشاری رد شوند.
- retry با کلید و payload یکسان پاسخ ثبت‌شده را بازگرداند؛ استفاده از همان کلید با payload یا endpoint دیگر تعارض دهد.
- سفارش دوبار ایجاد نشود، درخواست قرارداد به‌طور پیش‌فرض pending بماند، اطلاعات تماس و token ذخیره/بازتاب داده نشود و تمام lookupها connection-scoped باشند.
- migration روی دیتابیس دورریختنی و installer تازه هر دو چهار جدول API را ایجاد کنند.

## محدودیت

این فاز کلید زنده، ایجاد/فعال‌سازی مستقیم قرارداد، انتقال بانکی، ارسال واقعی webhook، پنل مدیریت اتصال و production readiness را فعال نمی‌کند. وضعیت فعلی webhook فقط outbox پایدار است تا endpoint، secret management، retry worker و callback در فازهای اتصال WordPress/WooCommerce با هم تکمیل شوند.

## شواهد اجرا

- migration افزایشی روی دیتابیس دورریختنی MySQL 8.4.3 اجرا شد؛ نصب‌گر کامل نیز در دیتابیس تازه اجرا شد و چهار جدول API را ایجاد کرد.
- آزمون integration روی MySQL، احراز هویت و scope، rate limit، quote و محدودیت policy، replay پس از تغییر policy، idempotency conflict، سفارش/درخواست قرارداد، جداسازی tenant، webhook outbox و emergency disable را گذراند.
- release gate محلی static/unit و lint تعداد ۶۴۴ فایل PHP را گذراند. مجموعهٔ integration سراسری همچنان به QA URL و تنظیمات کامل همان محیط نیاز دارد.
