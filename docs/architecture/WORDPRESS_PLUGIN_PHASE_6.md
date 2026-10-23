# فاز ۶ — هسته افزونهٔ WordPress

## وضعیت و محدوده

این فاز افزونهٔ مستقل `Proma Pay for WooCommerce` را با شناسهٔ `proma-pay-woocommerce` اضافه می‌کند. این نسخه زیرساخت اتصال WordPress به API مرکزی را می‌سازد؛ هنوز درگاه Checkout ووکامرس، صفحه محصول، سفارش WooCommerce، دریافت پیش‌پرداخت، قرارداد فعال یا انتقال پول را اجرا نمی‌کند.

## طراحی

- هر سایت WordPress اتصال‌های مجزا با URL، توکن آزمایشی، اولویت، وضعیت فعال و اتصال پیش‌فرض دارد. تغییر وضعیت محلی اتصال، اتصال مرکزی را تغییر نمی‌دهد.
- URL اتصال باید HTTPS با hostname معتبر باشد. درخواست از `wp_safe_remote_request()` با اعتبارسنجی URL، بررسی گواهی TLS روشن، timeout پنج‌ثانیه‌ای و بدون redirect اجرا می‌شود.
- فقط توکن `pmp_test_` پذیرفته می‌شود. مقدار کامل توکن یا بدنهٔ درخواست/پاسخ در گزارش ارتباط ذخیره نمی‌شود.
- توکن و snapshot محدودشدهٔ policy با AES-256-GCM و کلیدی مشتق‌شده از `wp_salt('auth')` رمز می‌شوند. کش پنج دقیقه اعتبار دارد و فقط برای نمایش مدیریتی است؛ Quote در Checkout آینده باید از API آنلاین و policy جاری استفاده کند.
- گزارش ارتباط فقط زمان، اتصال، مسیر مجاز، وضعیت HTTP، نتیجه، مدت، request ID و error code محدودشده را نگه می‌دارد. زمان‌بند روزانه و cleanup احتمالی هر ۲۰۰ درخواست محدودیت نگه‌داری ۹۰ روز و ۵۰٬۰۰۰ ردیف اعمال می‌کنند.
- ثبت و تغییر token در صفحه مدیریت نیز HTTPS می‌خواهد؛ تنها environment صریحاً `local` از این شرط مستثناست. logهای قدیمی پس از ۹۰ روز و policy cache منقضی‌شده با زمان‌بند روزانه پاک می‌شوند.
- تغییر schema با `dbDelta()` افزایشی و فقط در فعال‌سازی یا بازدید مدیر دارای capability انجام می‌شود؛ درخواست عمومی وب migration را اجرا نمی‌کند. جداول روی InnoDB هستند تا تغییر پیش‌فرض با transaction انجام شود. غیرفعال‌کردن اتصال یا افزونه جدول‌ها و تنظیمات را پاک نمی‌کند.
- مدیریت اتصال با capability اختصاصی `manage_proma_pay_connections` و nonce هر عملیات محدود شده است. هنگام فعال‌سازی این capability به نقش Administrator داده می‌شود؛ مدیر سایت می‌تواند همان capability را به کارکنان مورد اعتماد اضافه کند.
- API مسیر `GET /policies/financial` با scope مستقل `policy:read` یک projection محدود به نرخ، روش و سقف‌های محاسبه را برمی‌گرداند. hash خروجی به همین projection تعلق دارد و hash نسخهٔ اصلی جداگانه به‌عنوان `source_policy_sha256` نگهداری می‌شود.

## معیار پذیرش

- نصب اولیه سه جدول اتصال، کش policy و log را بسازد و اجرای مجدد/به‌روزرسانی چیزی را حذف نکند.
- توکن فقط در حالت رمز‌شده ذخیره شود؛ توکن زنده، URL با اعتبارنامه، HTTP و مقصدی که WordPress آن را ناامن تشخیص دهد رد شوند.
- نمایش policy بدون `policy:read` ممکن نباشد و policy از مسیر API محدودشده و رمز‌شده کش شود.
- timeout، خطای HTTP، خطای پاسخ، شناسهٔ درخواست و duration در log ثبت شوند، بدون بدنه یا secret.
- عملیات افزودن، ویرایش، غیرفعال‌سازی، اولویت، انتخاب اتصال پیش‌فرض و تست اتصال فقط پس از بررسی capability و nonce انجام شوند.

## شواهد آزمون

- آزمون واحد افزونه روی PHP محلی، رمزگذاری و tamper detection، rotation کلید، اعتبار URL، transport limits، policy validation، cache ciphertext و انقضای کش را پوشش می‌دهد.
- آزمون Integration عمومی API روی MySQL آزمایشی، endpoint سیاست مالی، scope مستقل، hash و حذف فیلدهای غیرمجاز را بررسی می‌کند.
- افزونه در WordPress 7.1.3، PHP 8.3.33 و MySQL 8.4.3 فعال شد؛ جدول‌های InnoDB، capability، ذخیره/خواندن credential رمز‌شده، default failover، خروجی نام مخرب و guard اجرای migration آزموده شدند. آزمون API نیز پاسخ واقعی endpoint policy را از validator افزونه عبور می‌دهد. WooCommerce هنوز نصب نشده است و هیچ نسخه یا سازگاری Checkout/Blocks/HPOS ادعا نمی‌شود.

## بازبینی امنیتی

- Semantic runtime برای PHP، call graph و dataflow جزئی دارد، اما AST و PHP parser کامل ندارد. دو مورد XSS را نشان داد؛ بررسی مسیرها refute کرد: ورودی POST مربوط به شناسهٔ اتصال وارد رندر جدول نمی‌شود، URL نهایی `esc_url` است، مقدار name با `esc_html`/`esc_attr` چاپ می‌شود و مقدار checkbox به integer تبدیل می‌شود. آزمون WordPress واقعی نام `<script>` را escape کرد و edit ID مخرب را بازتاب نداد.
- Runtime همچنین `update_option()` را به‌عنوان authorization candidate گزارش کرد. مسیر واقعی migration فقط از activation hook یا `admin_init` با `manage_proma_pay_connections` اجرا می‌شود؛ آزمون WordPress نشان داد کاربر ناشناس schema version را تغییر نمی‌دهد و مدیر مجاز می‌تواند migration را اجرا کند بدون حذف اتصال‌ها.
- پس از refutation مورد artifact-bound، مورد تأییدشده‌ای در همین scope باقی نماند. Semgrep و CodeQL روی host نصب نبودند. DAST روی API عمومی اجرا نشد چون endpoint مجاز و محیط QA مستقر در این فاز ارائه نشده است.

## محدودیت‌ها

- فقط محیط آزمایشی و credential آزمایشی پشتیبانی می‌شود.
- ایجاد و تأیید سفارش، قرارداد، ضمانت، پیش‌پرداخت، webhook خروجی و اتصال درگاه پرداخت در فازهای بعدی است.
- WP Multisite و rotation خودکار کلید رمزگذاری هنوز پشتیبانی آزموده‌شده ندارند. اگر WordPress salts عوض شوند، credential ذخیره‌شده قابل رمزگشایی نیست و باید دوباره وارد شود.
- سیستم API فعلی امضای HMAC مستقل برای هر درخواست یا ارسال webhook به WordPress را ندارد؛ این افزونه فقط API Bearer/TLS فاز ۵ را مصرف می‌کند و production security gate باز می‌ماند.
