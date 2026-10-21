# Proma Accounting 1.2.6 → 1.2.7 — Current State Audit

## وضعیت baseline

- Manifest فعلی: `plugins/PromaAccounting/plugin.json`، نسخه `1.2.6`، حداقل Core `1.3.2`.
- افزونه اکنون routeهای dashboard/accounts/ledger/sales/commissions/rules/settings/backfill را دارد.
- migrationهای فعلی: accounting core، v1.1، request integrity و dashboard performance.
- `Money` فعلی در سرویس‌های افزونه integer Toman و basis-points را به‌کار می‌برد.
- JavaScript افزونه در ممیزی استاتیک polling دائمی یا fetch دوره‌ای ندارد.
- Outbox و listenerهای commission موجودند، اما scheduler حقوق، salary periods و analytics classification وجود ندارند.

## gapهای قطعی برای 1.2.7

1. داشبورد مالی شخصی با enforce شدن scope کاربر وجود ندارد.
2. endpoint تجمیعی chart/summary و cache ایزوله وجود ندارد.
3. جدول و تنظیمات `accounting_analytics_categories` وجود ندارد.
4. snapshot طبقه‌بندی روی سند نهایی وجود ندارد؛ تغییر mapping تاریخی باید بدون بازنویسی تاریخچه طراحی شود.
5. `user_salary_rules`، salary period idempotency و reconciliation وجود ندارد.
6. مسیر مجزا برای ثبت حقوق (افزایش بدهی) و پرداخت حقوق (کاهش مانده) وجود ندارد.
7. money input سه‌رقمی مشترک و parser مستقل سمت سرور در کل فرم‌های افزونه تکمیل نشده است.
8. permissionهای `accounting.analytics.*` و routeهای مدیریت analytics/salary وجود ندارند.
9. تست‌های واقعی Jalali boundary، IDOR، concurrency، cache isolation و salary retry اجرا نشده‌اند.

## نام‌گذاری مالی

در UI عنوان رسمی «سود و زیان» استفاده نمی‌شود. عنوان مورد تأیید:

**گزارش درآمد، هزینه و خالص عملکرد مالی**

فرمول گزارش: `net_result = configured_income - configured_expense`. مقدار منفی فقط «خالص منفی» نامیده می‌شود و به‌عنوان سود و زیان رسمی شرکت ادعا نمی‌شود.

## ترتیب اجرای 1.2.7

1. migrationهای idempotent و permission/lifecycle gate؛
2. classification و snapshot مالی؛
3. salary rule/period/payment با unique key و worker قفل‌شده؛
4. MoneyInput و parser authoritative؛
5. endpoint تجمیعی analytics با scope و cache؛
6. UI داشبورد و نمودارهای lazy؛
7. تست‌های مالی، امنیتی، performance و release gate؛
8. فقط در صورت PASSED شدن gate، تغییر manifest به `1.2.7` و ساخت ZIP.

وضعیت فعلی این release: **NOT IMPLEMENTED / NOT RELEASED**.
