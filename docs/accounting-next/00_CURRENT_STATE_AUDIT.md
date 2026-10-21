# Proma Accounting — Current State Audit

تاریخ ممیزی: 2026-07-21 (Asia/Tehran)  
وضعیت سند: ممیزی خواندنی؛ در این مرحله هیچ تغییر کدی یا انتشار بسته انجام نشده است.

## نتیجهٔ اجرایی

وضعیت فعلی برای ورود به مرحلهٔ پیاده‌سازی «آمادهٔ انتشار» نیست. نسخهٔ checkout محلی هسته `1.4.1` و نسخهٔ manifest افزونه `1.2.4` است، اما وضعیت نصب واقعی افزونه، registry و schema دیتابیس از این workspace قابل اثبات نیست. دسترسی production هم ارائه نشده و مقایسهٔ محتوایی با شاخهٔ اصلی GitHub هنوز انجام نشده است. بنابراین release gate فعلاً **BLOCKED** است.

## هویت منبع و نسخه‌ها

| مورد | مقدار | وضعیت شواهد |
|---|---|---|
| شاخهٔ محلی | `codex/release-v1.4.1` | PASSED (git) |
| commit محلی | `a07e6fb` | PASSED (git) |
| نسخهٔ Core در `config/version.php` | `1.4.1` / `V1.4.1` | PASSED (فایل منبع) |
| تاریخ database version | `2026.07.21` | PASSED (فایل منبع) |
| Plugin API | `1.0` | PASSED (فایل منبع) |
| نسخهٔ manifest حسابداری | `1.2.4` | PASSED (فایل منبع) |
| حداقل Core افزونه | `1.3.2` | PASSED (فایل منبع) |
| remote GitHub | `mehdirabani/Proma-pay` | PASSED (metadata فقط) |
| production نصب‌شده | نامشخص | BLOCKED؛ دسترسی/خروجی registry ارائه نشده |

remote دارای شاخهٔ `codex/release-v1.4.1` با همان commit محلی و شاخهٔ `main` با SHA متفاوت (`7f37469…`) است. چون object شاخهٔ main در clone فعلی وجود ندارد، diff محتوایی GitHub در این مرحله **NOT EXECUTED** است؛ هیچ fetch یا تغییری در refs محلی انجام نشد.

## ساختار فعلی افزونه

- manifest شامل 24 route، سه migration و 23 permission است.
- migrationهای اعلام‌شده:
  - `2026_07_12_accounting_core.sql`
  - `2026_07_13_accounting_v1_1.sql`
  - `2026_07_18_accounting_request_integrity.sql`
- شمارش استاتیک `CREATE TABLE` در migrationها: 13 مورد (و چند ALTER شرطی برای ستون‌ها/ایندکس‌ها).
- سرویس‌های اصلی موجود: `AccountingRepository`، `AccountingIdempotencyService`، `CommissionCalculationService`، `CommissionService`، `LedgerService`، `SalesService` و `Money`.
- route ثبت سند دفترکل با GET fallback و POST method جداگانه تعریف شده است؛ route حذف قانون کمیسیون نیز در manifest وجود دارد.

## DDL و مرز درخواست وب

در کد PHP افزونه DDL مستقیم در controller/service معمولی پیدا نشد؛ DDL افزونه در migrationهای SQL متمرکز است. در Core، موارد DDL یافت‌شده مربوط به backup و جدول داخلی migration است (`helpers/BackupService.php` و `helpers/ScriptUpdateService.php`). این موارد باید در مرحلهٔ بعد با مسیرهای lifecycle و منع اجرای DDL در page request تطبیق داده شوند.

## یکپارچگی مالی

`plugins/PromaAccounting/src/Services/Money.php` برای مبلغ از integer و برای نرخ از basis-points استفاده می‌کند و در خود افزونه cast شناور (`float`/`double`) پیدا نشد. با این حال در بخش‌های عمومی Core چندین محاسبهٔ مالی با `(float)` وجود دارد (برای نمونه dashboard و `ContractFinancialSummaryService`). این یک finding بین‌لایه‌ای است و تا بررسی کامل مسیرهای ورودی/خروجی، وضعیت آن **NOT EXECUTED** برای آزمون precision و **OPEN** برای اصلاح معماری محسوب می‌شود؛ نبود cast شناور در افزونه به‌تنهایی اثبات سلامت مالی کل سامانه نیست.

## مجوز و دسترسی

manifest مجوزهای ریزدانه برای ledger، commission، rules، settings و backfill دارد و routeها به آن‌ها متصل‌اند. ممیزی فعلی فقط اتصال استاتیک manifest را بررسی کرده است؛ ماتریس role/permission واقعی، deny-by-default، CSRF و authorization در runtime با browser/API تست نشده و **BLOCKED / NOT EXECUTED** است.

## registry، schema و production

هیچ اتصال یا query به دیتابیس production یا registry نصب‌شده اجرا نشد. بنابراین موارد زیر هنوز قابل ادعا نیستند:

- نسخهٔ ثبت‌شده و status واقعی `proma-accounting`؛
- migrationهای موفق/ناموفق و checksum آن‌ها؛
- وجود واقعی تمام 13 جدول و ستون‌های شرطی؛
- وجود دادهٔ legacy، orphan یا duplicate؛
- امکان rollback و حفظ داده در محیط نصب‌شده.

برای ادامه، یک staging DB قابل بازتولید یا dump ماسک‌شده و خروجی read-only registry لازم است. تست destructive روی production مجاز نیست.

## تست و release gate

در این audit turn، به‌علت نبود PHP CLI در PATH، تست‌های PHP اجرا نشدند: **NOT EXECUTED**. تست‌های browser، HTTP با MySQL واقعی، migration upgrade/rollback، concurrency/idempotency، security و performance نیز **BLOCKED** هستند. فایل‌های تست موجود در repository صرفاً inventory شدند و نتیجهٔ قبلی یا ادعای «بدون باگ» از آن‌ها استنتاج نمی‌شود.

بسته‌های قدیمی موجود در `dist/plugins/PromaAccounting` عبارت‌اند از v1.2.1، v1.2.2 و v1.2.3؛ در این مرحله بستهٔ v1.2.4 یا نسخهٔ بعدی ساخته/منتشر نشده است.

## موارد باز برای مرحلهٔ بعد

1. تعیین منبع حقیقت نسخهٔ Core و plugin در production و GitHub release branch.
2. اجرای diff محتوایی با `main`/release مورد تأیید پس از در دسترس بودن object یا archive آن.
3. راه‌اندازی staging DB و ثبت snapshot registry/schema قبل از هر migration.
4. تکمیل ماتریس route × method × permission × role و تست deny-by-default.
5. ممیزی تمام مسیرهای مبلغ در Core برای حذف float از محاسبات مالی حساس.
6. اجرای release gate کامل؛ فقط در صورت PASSED شدن همهٔ دروازه‌های لازم، بسته‌بندی نسخهٔ بعدی مجاز است.

