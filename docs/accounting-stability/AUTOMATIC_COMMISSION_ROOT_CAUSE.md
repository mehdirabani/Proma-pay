# Automatic Commission — Root Cause Status

## آنچه از کد اثبات شد

کمیسیون توسط listener افزونه روی `contract.created` ساخته می‌شود، اما event از مسیر transactional outbox عبور می‌کند. چون Core قبلاً outbox را فقط بعد از commit و داخل همان HTTP request پردازش می‌کرد، ایجاد commission به اجرای همان request وابسته بود. بعد از اصلاح، page request فقط event را ثبت می‌کند و cron/worker آن را با batch محدود مصرف می‌کند.

## آنچه هنوز اثبات نشده

- قرارداد واقعی production و seller ثبت‌شده؛
- rule ID و timing واقعی؛
- نتیجهٔ `timingReady` برای قرارداد گزارش‌شده؛
- وجود/وضعیت row در `system_outbox` و `plugin_accounting_commissions`؛
- لاگ خطای listener یا DB.

وضعیت reproduction واقعی: **BLOCKED** تا staging DB یا خروجی read-only production ارائه شود.
