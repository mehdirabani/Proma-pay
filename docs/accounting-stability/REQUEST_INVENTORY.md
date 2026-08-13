# Accounting Stability — Request Inventory

## Browser

ممیزی استاتیک `plugins/PromaAccounting/assets/js/accounting.js`: موردی از `setInterval`، polling بی‌نهایت، `fetch` یا `XMLHttpRequest` پیدا نشد. timeoutهای موجود برای focus/رفتار UI هستند. نتیجه: **PASSED (static only)**.

## Server-side

پیش از اصلاح، `SystemOutbox::processPending` در این مسیرهای درخواست عادی فراخوانی می‌شد:

- ایجاد/ویرایش قرارداد
- ثبت پرداخت و پرداخت گروهی
- receipt و installment
- برخی repair/backfillهای Core

این فراخوانی‌ها اکنون با guard worker بی‌اثر می‌شوند مگر اینکه context worker صریح باشد. مسیر مجاز drain: `cron/outbox` با token و limit 1..50.

## مواردی که هنوز نیازمند شواهد staging هستند

تعداد واقعی درخواست، زمان query، PHP worker، connection pool، lock wait، Cron overlap، WAF/ModSecurity، DNS/AAAA و IPv4/IPv6: **BLOCKED / NOT EXECUTED**.
