# ثبت باگ‌های ممیزی تثبیت ریشه‌ای

وضعیت‌ها فقط یکی از `PASSED`، `FAILED`، `BLOCKED` یا `NOT EXECUTED` هستند. «رفع در سورس» جایگزین اجرای تست نیست.

| شناسه | شدت | ماژول | علت ریشه‌ای | اصلاح | تست رگرسیون | وضعیت |
| --- | --- | --- | --- | --- | --- | --- |
| RR-001 | High | ویرایشگر قالب | تب دیداری در HTML فعال بود، در حالی که Quill فقط برای `contracts/show/*` بارگذاری می‌شد. | بارگذاری route-scoped، fallback منبع، adapter با state واقعی و retry کنترل‌شده | `static_template_editor_reliability_v158.php` PASS؛ browser E2E لازم است | NOT EXECUTED |
| RR-002 | High | ویرایشگر قالب | source و Quill از یک state مشترکِ صریح استفاده نمی‌کردند؛ `currentValue` ممکن بود در حالت source مقدار قدیمی visual را برگرداند. | یک adapter با state `source`/`visual`، sync پیش از submit و recovery محلی | `static_template_editor_reliability_v158.php` PASS؛ تست رفت‌وبرگشت محتوا لازم است | NOT EXECUTED |
| RR-003 | Medium | ویرایشگر قالب | `prompt`، `confirm` و `alert` مرورگر با modal و اعلان هسته ناسازگار بودند. | modal برای find/replace، جایگزینی منبع و تأیید تبدیل به HTML؛ اعلان زندهٔ درون رابط | `static_template_editor_reliability_v158.php` PASS | NOT EXECUTED |
| RR-004 | High | Router افزونه | خطای controller افزونه پس از log می‌توانست به Router هسته برگشته و 404 جعلی بسازد. | پاسخ 500/503 کنترل‌شده با Request ID برای route ثبت‌شدهٔ افزونه | `static_template_editor_reliability_v158.php` PASS؛ HTTP lifecycle لازم است | NOT EXECUTED |
| RR-005 | Medium | lifecycle افزونه | provider ناموفق routeهای خود را ثبت نمی‌کرد؛ core برای URL افزونه 404 می‌داد. | نگهداری route claimهای provider ناموفق و پاسخ 503 برای همان route | `static_template_editor_reliability_v158.php` PASS؛ HTTP lifecycle لازم است | NOT EXECUTED |
| RR-006 | Medium | schema قالب | گزارش پیوست از DDL زمان درخواست سخن می‌گفت، اما سورس فعلی `ContractTemplateService` از `SchemaGuard` فقط خواندنی استفاده می‌کند. | بازتولید DDL در این baseline تأیید نشد؛ migrationها منبع ساخت schema باقی می‌مانند. | `RuntimeDdlAndLockTest.php` PASS | PASSED |
| RR-008 | High | امنیت قالب HTML | در نبود `DOMDocument`، sanitizer قالب به fallback مبتنی بر regex تنزل می‌کرد. | fallback حذف شد؛ ذخیره/انتشار HTML ساختاریافته بدون DOM با پیام روشن متوقف می‌شود. | `contract_template_renderer_security.php` PASS؛ تست محیط فاقد DOM لازم است | NOT EXECUTED |
| RR-007 | High | release gate | MariaDB آزمایشی و سرور browser QA در workspace حاضر در دسترس نیستند. | خارج از کد؛ باید محیط QA تأمین و خروجی نهایی نصب شود. | integration / E2E / update / rollback | BLOCKED |

## ریسک‌های باز

- ممیزی کامل افزونه حسابداری، حسابرسی مالی و تحلیل عملکرد نیازمند database و browser واقعی است؛ بدون آن هیچ موردی به‌عنوان PASSED گزارش نمی‌شود.
- بررسی همهٔ UIها در اندازه‌های مختلف باید با screenshot واقعی انجام شود؛ ممیزی کد جایگزین آن نیست.
