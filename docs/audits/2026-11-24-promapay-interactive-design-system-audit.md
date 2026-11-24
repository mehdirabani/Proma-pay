# Proma Pay Interactive Design System — Repository Audit

**تاریخ بررسی:** ۲۴ نوامبر ۲۰۲۶  
**مخزن:** [mehdirabani/Proma-pay](https://github.com/mehdirabani/Proma-pay)  
**وضعیت مبنا:** commit `f795a15` روی شاخه کاری `codex/v704-lawyer-settlement`؛ شاخه توسعه `codex/interactive-design-system` از همین commit ساخته شد.

## خلاصه

Proma Pay یک برنامه وب فارسی با PHP سفارشی است. برنامه اصلی در ریشه مخزن اجرا می‌شود و از Router داخلی، Controllerهای PHP، مدل‌ها و Viewهای PHP استفاده می‌کند. رابط فعلی RTL است و از پوسته آماده RTL، Bootstrap RTL، jQuery، Feather، Font Awesome و Themify در کنار CSS و JavaScript اختصاصی استفاده می‌کند. افزونه‌ها مسیر، View و asset مستقل دارند.

در مخزن هسته طراحی شروع شده است، اما مستندات `docs/design-system/` فقط فهرست کوتاهی از توکن‌ها و کامپوننت‌های پایه است و صفحه تعاملی یا Playground ندارد. ساخت Design System با PHP و JavaScript/CSS فعلی، بدون چارچوب جدید، با معماری سازگار است.

## وضعیت Git و حفاظت از تغییرات موجود

در شروع کار، شاخه `codex/v704-lawyer-settlement` روی `f795a15` قرار داشت. درخت کاری از قبل **۸۸ فایل tracked تغییریافته** و **۱۹ مسیر untracked** داشت؛ تغییرها شامل Controller، مدل، View، تنظیمات، CSS/JS، افزونه‌ها، تست‌ها و مستندات امنیتی بودند. هیچ‌یک از آن‌ها برای این Audit stage یا commit نشده‌اند. شاخه `codex/interactive-design-system` از همان commit ساخته شد تا کار جدید مستقل ثبت شود و تغییرهای موجود دست‌نخورده بمانند.

## نقشه ساختار واقعی مخزن

| مسیر | نقش |
| --- | --- |
| `index.php` | نقطه ورود؛ بارگذاری bootstrap و فراخوانی `Router::dispatch()` |
| `bootstrap.php` | autoload ساده برای `core/`, `models/`, `helpers/`, `controllers/`، راه‌اندازی session و middlewareهای درخواست |
| `core/Router.php` | استخراج route از query string، dispatch افزونه و تبدیل بخش نخست route به نام Controller |
| `core/Controller.php` | بارگذاری View و layout؛ پشتیبانی از Viewهای افزونه |
| `controllers/` | Controllerهای قابلیت‌های اصلی، از جمله مشتری، قرارداد، قسط، پرداخت، وصول، حقوقی و تنظیمات |
| `models/`, `helpers/`, `services/` | دسترسی داده و منطق دامنه/سرویس؛ محاسبات معتبر مالی سمت سرور می‌مانند |
| `views/` | صفحات PHP و partialها؛ پوسته مشترک برنامه در `views/layouts/app.php` است |
| `assets/css/` | `app.css` قدیمی و فایل‌های component/page؛ توکن‌ها در `assets/css/design-system/tokens.css` |
| `assets/js/` | رفتار مشترک در `app.js` و `v2-system.js`، به‌علاوه اسکریپت‌های صفحه |
| `html/RTL/assets/` | پوسته و وابستگی‌های vendor مانند Bootstrap RTL، Font Awesome، Themify و Feather |
| `plugins/` | افزونه‌های PHP با manifest، route، view، asset، migration، تست و مستندات مستقل |
| `docs/design/`, `docs/design-system/`, `docs/ui/` | قواعد طراحی و Auditهای UI موجود |
| `tests/`, `scripts/`, `database/`, `config/` | تست‌های ایستا/واحد/یکپارچه، ساخت انتشار، migrationها و تنظیمات نمونه |

### Route و View

`.htaccess` مسیرهای غیرواقعی را به `index.php` می‌فرستد. `Router` مسیر `?route=...` را می‌خواند، ابتدا routeهای افزونه را به `PluginManager` می‌دهد، سپس Controller را با قرارداد نام‌گذاری از روی slug می‌سازد. `Controller::render()` داده را به View می‌دهد و در حالت معمول آن را با `views/layouts/app.php` رندر می‌کند. مسیر `docs/` مستقیماً از وب مسدود است؛ مستندات قابل مشاهده باید از route برنامه ارائه شوند.

برای Design System یک route مستقل مانند `design-system` و Controller محدود به نقش admin مناسب است. خود صفحه باید layout مستقل داشته باشد تا CSS و رفتار app shell وارد Playground نشوند. Controller فقط catalog ایستا و نسخه را ارائه کند؛ هیچ query یا عملیات مالی لازم نیست.

## CSS، توکن‌ها و کامپوننت‌های فعلی

فایل‌های حاضر در `assets/css/components/`:

- `forms.css`, `layout.css`, `responsive.css`, `panels.css`
- `contract-settings.css`, `role-redesign.css`, `v2-system.css`
- `mobile-first.css`, `v7-system.css`, `v7-reference-rhythm.css`
- `customer-experience.css`, `financial-reconciliation.css`, `booklet-print.css`, `contract-print.css`

`assets/css/app.css` یک فایل تجمیعی/قدیمی بزرگ است. در `views/layouts/app.php` ترتیب بارگذاری از پوسته vendor، `app.css` و `tokens.css` به چندین فایل component و سپس assetهای افزونه می‌رسد. در نتیجه selectorهایی مانند `.btn`, `.card`, `.modal-content`, فرم‌ها و جدول‌ها در چند لایه تعریف و بازتعریف می‌شوند. `tokens.css` نیز علاوه بر تعریف token و alias، style عمومی برای بدنه، دکمه، کارت، فرم، جدول و Dark Mode دارد. قوانین جدید متعددی در فایل‌های `v2-system.css`، `v7-system.css`, `role-redesign.css` و `panels.css` روی همین سطوح اعمال می‌شوند. این هم‌پوشانی، ریسک تقدم CSS و تفاوت ظاهر صفحه‌ها را ایجاد می‌کند؛ بنابراین صفحه جدید باید namespace اختصاصی داشته باشد و فایل‌های سراسری موجود بازنویسی نشوند.

توکن‌های اصلی موجود شامل `--pp-primary-*`, `--pp-success`, `--pp-warning`, `--pp-danger`, `--pp-info`, `--pp-ink`, `--pp-muted`, `--pp-line`, `--pp-surface`, `--pp-canvas`, فاصله‌های `--pp-space-*`, radius، shadow، اندازه sidebar/header/control و tokenهای focus/motion هستند. نام‌های سازگاری `--proma-*`, `--color-*`, `--space-*` و `--ui-*` نیز وجود دارند. در زمان Audit نسخه کاری `tokens.css` علاوه بر HEAD شامل تغییرهای unstaged کاربر بود؛ نباید آن را در پیاده‌سازی جایگزین کرد.

کلاس‌ها و الگوهای فعلی شامل دکمه‌های `.btn` و `.icon-btn`، کارت‌های `.card` و KPI، فرم‌های `.form-group`, `.form-field`, `.form-grid` و `.proma-form-control`، badge/notice، table-wrap، tab، timeline، modalهای Bootstrap-مانند و modal سفارشی، uploader، drawer/nav و empty/loading state است. فهرست مختصر `docs/design-system/COMPONENTS.md` این موارد را نام می‌برد، اما قرارداد Props، API، نمونه زنده و کد اجرایی ندارد.

## JavaScript، Theme، RTL و Responsive

- برنامه بر اساس `package.json` پروژه PHP است؛ React/Vue/Tailwind در runtime رابط فعلی دیده نمی‌شود. jQuery و Bootstrap bundle از پوسته RTL بارگذاری می‌شوند.
- `assets/js/app.js` تعاملات مشترک و قابلیت‌های صفحه را دارد؛ `assets/js/v2-system.js` بخشی از رفتار رابط V2 از جمله دسترس‌پذیری دکمه Theme را اضافه می‌کند.
- Theme با کلاس `body.dark-only` و selectorهای `[data-theme="dark"]` پوشش داده می‌شود. استایل Dark Mode در توکن‌ها و چند فایل component جداگانه تکمیل شده؛ رفتار سوئیچ فعلی به `.mode` متصل است.
- Layout دارای `<html lang="fa" dir="rtl">`، لینک پرش به محتوا و کنترل‌های دارای label/aria در بسیاری از نقاط است. از `dir="ltr"` برای شناسه‌ها و ورودی‌های بین‌المللی استفاده شده است.
- قواعد موجود در `docs/design/RESPONSIVE_RULES.md` و `docs/ui/RESPONSIVE_DESIGN_SYSTEM.md` breakpointهای عمده ۳۷۵/۴۳۰/۷۶۸/۱۰۲۴/۱۲۸۰/۱۴۴۰/۱۹۲۰ را ذکر می‌کنند؛ درخواست جدید ۳۶۰/۳۹۰ و ۸۲۰ را نیز اضافه می‌کند. CSS پاسخ‌گو در چند فایل مختلف پخش شده است.
- برخی قواعد focus و reduced motion در token stylesheet وجود دارد. صفحه جدید باید Semantic HTML، focus قابل مشاهده، کیبورد، `prefers-reduced-motion` و عرض‌های درخواست‌شده را مستقلاً پوشش دهد.

## فونت و آیکون

- فونت Yekan Bakh در `assets/fonts/woff/` و `assets/fonts/woff2/` با وزن‌های Thin، Light، Regular، SemiBold، Bold، ExtraBold، Black و ExtraBlack موجود است؛ `app.css` وزن‌های اصلی را تعریف می‌کند.
- آیکون‌ها چندمنبعی‌اند: Font Awesome، Themify، Feather (شامل SVGهای هر آیکون و Feather runtime)، SVG sprite در پوسته RTL و helper `proma_icon()` که تعدادی SVG inline سفارشی دارد. برای گالری آیکون باید ابتدا مجموعه‌های واقعاً قابل بارگذاری در مسیر `html/RTL/assets/` موجود در Checkout شمرده شوند؛ نام SVGهای helper نیز منبع مستقل دیگری است.

## افزونه و نسخه دسکتاپ Electron

افزونه‌های هسته در `plugins/` شامل `PromaAccounting`, `PromaAffiliate`, `PromaInventoryPartners`, `PromaPartnershipLedger`, `PromaRepair`, `PromaSignConnect`, `PromaZarinpal` و افزونه WooCommerce هستند. `PluginManager` routeها و assetها را به‌صورت پویا بارگذاری می‌کند؛ Design System نباید وارد bootstrap یا CSS افزونه‌ها شود.

`package.json` فعلی `electron/main.js` را entry point و `electron/main.js`/`electron/preload.js` را در فهرست بسته‌بندی معرفی می‌کند، اما در checkout محلی پوشه `electron/` و این دو فایل وجود ندارند. مسیر تست `tests/Security/electron_build_allowlist.cjs` در مخزن هست. این ناهماهنگی پیش از هر ادعای سازگاری Electron باید بررسی شود؛ Design System نباید فایل‌های desktop یا build را تغییر دهد.

## مشکلات و ریسک‌های سازگاری ثبت‌شده

1. selectorهای پایه در پوسته vendor، `app.css`، `tokens.css` و componentهای نسل‌های مختلف هم‌پوشانی دارند؛ اضافه کردن style سراسری می‌تواند صفحات عملیاتی را عوض کند.
2. منبع واقعی رنگ در tokenها تعریف شده، ولی چند component قدیمی هنوز رنگ و اندازه مستقیم دارند؛ نمایش Foundations باید هم token و هم مقدار محاسبه‌شده را از CSS مرجع بخواند.
3. Theme بین `dark-only` و `[data-theme="dark"]` شناخته می‌شود؛ صفحه جدید باید این دو قرارداد را در محدوده خودش هماهنگ کند و state app را بی‌اجازه تغییر ندهد.
4. پیاده‌سازی responsive در چند stylesheet و viewportهای مستندشده کاملاً یکسان نیستند؛ breakpointهای مورد درخواست باید در QA صفحه جدید جداگانه بررسی شوند.
5. Electron metadata به sourceهای غایب اشاره می‌کند و برای Documentation نباید تکیه‌گاه نسخه‌گذاری باشد.
6. شاخه کاری حاوی تغییرهای گسترده و Commit نشده است؛ Commitهای این کار باید فقط مسیرهای متعلق به Design System را stage کنند.

## ساختار پیشنهادی محصول

1. Controller و route مجزا در `controllers/DesignSystemController.php` با دسترسی admin و داده‌های نمونه ایستا.
2. View مستقل در `views/design-system/index.php` با `<html lang="fa" dir="rtl">` و layout مجزا.
3. CSS namespaced در `assets/css/pages/design-system.css` و JS namespaced در `assets/js/design-system.js`؛ هیچ dependency جدیدی لازم نیست.
4. Catalogهای داده‌ای برای tokenها، دسته‌ها، کامپوننت‌ها، نمونه‌ها و متادیتای مستندات تا شمارنده‌ها، sidebar و جست‌وجو از یک منبع واقعی تولید شوند.
5. صفحه سه ناحیه مستندات (ناوبری، محتوای اصلی، فهرست/اطلاعات جانبی) داشته باشد؛ در موبایل ناوبری و پنل اطلاعات به drawer تبدیل شوند.
6. Playgroundها داده محلی نمایشی داشته باشند؛ نمونه‌های مالی به DB، Controllerهای پرداخت، gateway یا عملیات واقعی وصل نشوند.
7. زبان‌های HTML/CSS/JS/PHP/TypeScript/React/JSON به‌عنوان نمونه‌های مشخص برچسب‌گذاری شوند؛ نمونه React فقط Integration Example است چون runtime اصلی PHP است.
8. جست‌وجو از روی نام فارسی و انگلیسی، aliasها و شناسه دسته انجام شود؛ Theme، copy code، modalها و controls با کیبورد قابل استفاده باشند.
9. تست‌های جدید محدود به route دسترسی، ساختار catalog/snippets و مرور تعاملی/responsive باشند؛ هیچ آزمون یا fixture مالی واقعی تغییر نکند.

## موارد خارج از تأیید Audit

این مرحله فقط بررسی و ثبت یافته‌های معماری است. هنوز route، component، CSS، JS یا رفتار محصولی اضافه نشده است. مواردی که به session/dataset معتبر یا اجرای واقعی Electron نیاز دارند، در مراحل پیاده‌سازی و QA گزارش خواهند شد.
