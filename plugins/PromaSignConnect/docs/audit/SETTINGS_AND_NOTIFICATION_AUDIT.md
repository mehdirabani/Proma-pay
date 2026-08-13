# ممیزی تنظیمات و اعلان‌های Proma Sign & Connect

تاریخ: ۱۴۰۵/۰۵/۰۷  
نسخه بررسی‌شده: `1.1.1-rc.1`

## نتیجه

گزارش کاربر تأیید شد. route عمومی `settings/{section}` برای پانزده section باز
می‌شود، اما فقط overview، sms، bale، telegram، notifications و otp محتوای اختصاصی
دارند. هشت section زیر به یک پاسخ placeholder مشترک می‌رسند:

- signature
- templates
- routing
- webhooks
- security
- deliveries
- health
- diagnostics
- retention

همچنین IPPanel و SMS.ir داخل section مشترک `sms` هستند، event catalog واقعی وجود
ندارد و عنوان اصلی رویدادها کلید فنی انگلیسی است.

## ماتریس routeهای فعلی

| Route | Method | Controller | Permission | Persistence | وضعیت |
|---|---|---|---|---|---|
| `settings/{section}` | GET | `DashboardController@section` | `proma_connect.view` | چند جدول با query مستقیم | sectionهای متعدد نمایشی |
| `settings/save` | POST | `DashboardController@save` | `proma_connect.settings.manage` | `proma_connect_settings` | whitelist کلی؛ validation وابسته به section ندارد |
| `rules/save` | POST | `NotificationRuleController@save` | `proma_connect.settings.manage` | `proma_connect_notification_rules` | کلید event از POST پذیرفته می‌شود؛ catalog validation ندارد |
| `bale/save` | POST | `BaleController@save` | `providers.manage` | `proma_connect_provider_configs` | عملیاتی |
| `bale/test/{id}` | POST | `BaleController@test` | `providers.manage` | config + attempt | عملیاتی، فراخوانی صریح |
| `bale/state/{id}` | POST | `BaleController@state` | `providers.manage` | provider config | عملیاتی |
| `telegram/validate` | POST | `TelegramController@validate` | `providers.manage` | settings رمز‌شده | عملیاتی، ولی webhook کامل نیست |
| `telegram/link` | POST | `TelegramController@link` | authenticated | link token | عملیاتی برای کاربر جاری |

همه POSTها از `Controller::onlyPost` و route middleware هسته عبور می‌کنند و فرم‌های
فعلی `csrf_field()` دارند. audit اختصاصی فقط در save عمومی تنظیمات نوشته می‌شود؛
rule، Telegram و maintenance audit یکدست ندارند.

## علت‌های ریشه‌ای

1. view واحد با branch نهایی عمومی ساخته شده و sectionها controller/service مستقل ندارند.
2. `SettingsService::defaults()` هم schema و هم whitelist تلقی شده، اما type/range/
   dependency validation ندارد.
3. جدول `proma_connect_templates` فقط آخرین رکورد را نگه می‌دارد و version table یا
   provider-event mapping ندارد.
4. `proma_connect_notification_rules` به catalog مرجع وصل نیست؛ controller هر کلید
   sanitizeشده POST را می‌پذیرد.
5. provider capability و event pattern در JSONهای پراکنده نگه‌داری می‌شود و UI/validator
   مشترک ندارد.
6. delivery table داده کافی برای event/template version و recipient masked ندارد.
7. صفحات maintenance هیچ route عملیاتی و service مجاز ندارند.

## اثر

- مدیر نمی‌تواند تشخیص دهد هر event دقیقاً چه معنایی دارد.
- یک Pattern Code/Template ID مستقل به ازای event قابل مدیریت نیست.
- تنظیم OTP بر routing واقعی dispatch اثر کامل ندارد.
- موفقیت ظاهری save ممکن است configuration ناسازگار را بپذیرد.
- سلامت، صف، retention و گزارش تحویل قابل بهره‌برداری نیستند.

## اصلاح لازم

- migration نسخه‌ای برای catalog، template versions، provider-event mappings،
  OTP/routing/retention policies و health snapshots؛
- `NotificationEventCatalog` و `TemplateVariableRegistry` با کلیدهای ثابت؛
- route و validator مستقل برای provider/event/template/maintenance؛
- نمایش عنوان فارسی و کلید فنی ثانویه؛
- pagination گزارش تحویل و عملیات maintenance فقط با POST، CSRF، permission و audit؛
- تست persistence، validation، عدم تغییر تنظیمات نامرتبط و نبود placeholder.

## دروازه آزمون

این ممیزی به‌تنهایی مجوز package نیست. migration واقعی MySQL/MariaDB، تست feature،
provider mock و viewportهای خواسته‌شده باید اجرا شوند.

