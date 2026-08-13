# ممیزی وضعیت فعلی Proma Sign & Connect

تاریخ ممیزی: ۱۴۰۵/۰۵/۰۷  
وضعیت انتشار: **آزمایشی / غیرقابل انتشار**

## شناسنامه منبع

| مورد | مقدار مشاهده‌شده |
|---|---|
| Core | `1.5.4` |
| Plugin API | `1.0` |
| نسخه افزونه هنگام شروع ممیزی | `1.1.0` |
| نسخه کاری پس از ممیزی | `1.1.1-rc.1` |
| شاخه | `codex/release-v1.4.5` |
| commit مبنا | `ed9ef29` |
| مسیر | `plugins/PromaSignConnect` |

## موجودی فنی

- Manifest: `plugin.json` با provider، route، permission، migration و health-check.
- Migrationها: ساخت تنظیمات، قالب‌ها، OTP، linkها، delivery tracking، درخواست/امضاکننده/
  evidence/artifact، webhook replay، تنظیمات حرفه‌ای و migration سخت‌سازی RC.
- Controllerها: Dashboard، Bale، Telegram، Webhook، OTP، Signature و NotificationRule.
- Serviceها: Settings، SecretCipher، ProviderRegistry، Dispatch، OTP، Telegram linking،
  Bale account/payload، notification rule و signature/hash.
- Providerها: IPPanel، SMS.ir، Bale Safir، Telegram و provider داخلی افزوده‌شده در RC.
- Routeهای عمومی: OTP request/verify، Telegram webhook و public integrity verify؛
  routeهای مدیریتی با permission manifest محافظت می‌شوند.
- Job قبلی افزونه در RC حذف شد؛ dispatch از `system_outbox` با event نوع
  `plugin_hook` و aggregate delivery عبور می‌کند.
- مدل اختصاصی جداگانه وجود ندارد و سرویس‌ها از `Model` هسته استفاده می‌کنند.
- secretها با AES-256-GCM و کلید محیطی `PROMA_APP_KEY` نگهداری می‌شوند؛ نسخه‌گذاری
  کلید هنوز تکمیل نیست.
- فایل artifact در schema پیش‌بینی شده اما Core File Management هنوز متصل نیست.

## رفتار lifecycle مشاهده‌شده

هسته برای install/update migrationهای manifest را فقط در lifecycle اجرا می‌کند،
checksum و زمان migration را ثبت می‌کند، قفل MySQL دارد و در update ناموفق تلاش
به بازیابی نسخه قبلی می‌کند. Provider افزونه در uninstall عادی history را نگه
می‌دارد؛ purge کنترل‌شده هنوز پیاده نشده است. health فعلی افزونه فقط وجود جدول‌ها
را می‌سنجد و برای انتشار کافی نیست.

## خطا و حریم خصوصی

Core Outbox خطا را bounded ثبت می‌کند. پاسخ خام provider نباید به view یا مشتری
برسد. HTTP client در RC فقط HTTPS و میزبان‌های رسمی allow-listشده را می‌پذیرد،
redirect را رد می‌کند، TLS را verify می‌کند، timeout را محدود و پاسخ را روی ۱ MiB
قطع می‌کند. redaction و طبقه‌بندی providerها هنوز نیازمند تست mock کامل است.

## پوشش تست مشاهده‌شده

در شروع فقط `tests/static.php` و `tests/ProfessionalSettingsTest.php` وجود داشت.
هر دو پس از تغییر RC با PHP محلی lint/اجرا شدند و pass شدند؛ این‌ها جایگزین تست
MySQL، provider mock، مرورگر، concurrency، accessibility یا performance نیستند.

## دامنه بررسی

این ممیزی بر مبنای کد واقعی هسته Proma Pay نسخه `1.5.4`، Plugin API نسخه `1.0`،
manifest افزونه، migrationها، providerها، controllerها، سرویس‌ها، viewها و تست‌های
موجود انجام شده است. نتیجه این سند مجوز انتشار نیست؛ هر ادعای پایداری منوط به عبور
قابل تکرار از دروازه انتشار است.

## وضعیت موجود

- افزونه از چرخه رسمی `PluginManager` و `PluginServiceProviderInterface` استفاده می‌کند.
- نصب و بروزرسانی هسته دارای قفل lifecycle، ثبت migration و rollback نسخه قبلی است.
- تنظیمات حساس با `SecretCipher` رمز می‌شوند و OTP به‌صورت hash ذخیره می‌شود.
- تنظیمات IPPanel، SMS.ir، بله سفیر و تلگرام در یک مرکز تنظیمات RTL ارائه شده‌اند.
- امضا، شواهد پایه، زنجیره رویداد و صفحه اعتبارسنجی عمومی پیاده‌سازی اولیه دارند.
- تنها دو تست PHP موجود است و هیچ اجرای واقعی provider، migration matrix، مرورگر
  responsive یا performance evidence در مخزن ثبت نشده است.

## شکاف‌های بحرانی

1. `DispatchService` یک صف مستقل در `proma_connect_deliveries` می‌سازد و متن پیام،
   از جمله OTP قابل استفاده، را در `payload_json` نگه می‌دارد. این با Core Outbox
   و اصل عدم نگهداری secret/message snapshot قابل استفاده ناسازگار است.
2. interface ارتباطی فقط عملیات پایه را پوشش می‌دهد؛ health، capability،
   idempotency، retry classification، status lookup و webhook verification قرارداد
   یکسان ندارند.
3. controllerهای بله و تلگرام مستقیماً provider/HTTP را می‌سازند و abstraction
   سراسری را دور می‌زنند.
4. وب‌هوک تلگرام بخشی از پردازش کسب‌وکار را همزمان انجام می‌دهد و تحویل سریع به
   Core Outbox ندارد.
5. `HttpClient` سقف اندازه پاسخ، redaction متمرکز، allow-list میزبان، محافظت DNS،
   circuit breaker و policy استاندارد timeout/retry ندارد.
6. زنجیره hash امضا هنگام ثبت همزمان قفل پایگاه داده ندارد و نسخه کلید HMAC برای
   rotation تاریخی ثبت نمی‌شود.
7. artifact امضاشده با ذخیره‌سازی خصوصی، hash فایل و سیاست دانلود مجاز وجود ندارد.
8. گزینه ورود OTP فقط تنظیم/مستند شده و با مسیر رسمی Auth به‌صورت end-to-end
   یکپارچه و آزمایش نشده است؛ MFA نیز وجود ندارد.
9. preference دریافت‌کننده، versioning قالب، provider داخلی سامانه و fallback
   policy کامل وجود ندارد.
10. health check فقط وجود جدول‌ها را بررسی می‌کند و وضعیت migration، job، secret،
    provider، artifact و chain را گزارش نمی‌کند.

## شکاف‌های مهم UI و عملکرد

- صفحه تنظیمات search، تشخیص تغییر ذخیره‌نشده و dirty-state guard ندارد.
- pagination سراسری برای حساب‌های بله، تحویل‌ها و داشبورد اثبات نشده است.
- داشبورد چند query شمارشی جداگانه دارد و budget واقعی query/time اندازه‌گیری نشده است.
- asset loading روی routeهای encodeشده نیازمند تست مرورگر واقعی است.
- هم‌سویی ظاهری با shell هسته در کد بهتر شده، اما viewportهای موبایل/تبلت/دسکتاپ
  هنوز با evidence تصویری تأیید نشده‌اند.

## شکاف lifecycle و انتشار

- manifest فعلی `1.1.0` ظاهر پایدار دارد، در حالی که release gate کامل نشده است.
- بازه حداکثر نسخه تست‌شده هسته و گزارش سازگاری واقعی وجود ندارد.
- purge صریح داده‌ها در provider عملاً پشتیبانی نشده است.
- migration rollback/partial failure روی دیتابیس واقعی آزمایش نشده است.
- checksum package، SBOM/فهرست فایل، گزارش امنیت و گزارش performance معتبر وجود ندارد.

## تصمیم ممیزی

نسخه فعلی باید به release-candidate برگردد و تا رفع موارد Blocker/Critical و ثبت
نتیجه آزمون‌های واقعی، بسته ZIP نصب‌پذیر جدید تولید نشود. ترتیب اصلاح:

1. حذف OTP/plaintext از persistence و اتصال dispatch به Core Outbox؛
2. تکمیل قرارداد provider و HTTP safety؛
3. سخت‌سازی webhook، OTP و امضا/chain/artifact؛
4. تکمیل lifecycle health و UI؛
5. افزودن unit/integration/security/performance/responsive tests؛
6. اجرای release gate و فقط در صورت موفقیت، ساخت package با checksum.
