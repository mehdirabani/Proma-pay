# Accounting Stability — Current State Audit

## شواهد فعلی

- `AccountingServiceProvider` رویدادهای `contract.created`، `contract.updated`، `contract.cancelled`، `contract.deleted` و `payment.completed` را ثبت می‌کند.
- `contract.created` در transaction قرارداد به `system_outbox` enqueue می‌شود و پس از commit، کد قدیمی همان request را به `SystemOutbox::processPending(25)` می‌فرستاد.
- همین الگو در مسیرهای پرداخت، receipt، installment و payment group نیز تکرار شده و batchهای 10 تا 50تایی دارد.
- افزونه polling بی‌نهایت ندارد؛ `accounting.js` فقط timeoutهای کوتاه برای رفتار UI دارد و `fetch`/`setInterval` در آن دیده نشد.
- محاسبهٔ پول افزونه در `Money` با integer/basis-points انجام می‌شود؛ در Core هنوز مسیرهای عمومی با float وجود دارد و ممیزی precision کامل **NOT EXECUTED** است.

## نتیجهٔ commission

کمیسیون به‌صورت مستقیم در controller قرارداد ساخته نمی‌شود؛ زنجیرهٔ فعلی این است:

`contract.create` → `system_outbox(plugin_hook)` → `contract.created` listener → `SalesService::recordFromContract` → `CommissionService::refreshForSale` → `timingReady` → commission row.

بنابراین اگر worker اجرا نشود، event و commission در همان لحظه مصرف نمی‌شوند. همچنین اگر timing قانون `after_down_payment`، `after_first_installment`، `after_full_settlement` یا `manual_approval` باشد، صرف ایجاد قرارداد کمیسیون نهایی ایجاد نمی‌کند.

## اصلاح اعمال‌شده

`SystemOutbox::processPending` اکنون فقط در CLI یا context صریح `PROMA_OUTBOX_WORKER=true` اجرا می‌شود. مسیر `cron/outbox` با همان cron token موجود، batch محدود حداکثر 50تایی را پردازش می‌کند. درخواست‌های معمول فقط enqueue می‌کنند و دیگر worker پنهان داخل page request ندارند.

این اصلاح علت timeout ناشی از پردازش هم‌زمان outbox را هدف می‌گیرد، اما علت نهایی شبکه/WAF/IP هنوز بدون لاگ production قابل اعلام نیست: **BLOCKED**.
