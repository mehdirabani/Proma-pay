# معماری Proma Partnership Ledger

## جایگاه

این افزونه روی Plugin Manager رسمی Proma Pay (`PluginManifest`, `PluginServiceProviderInterface`, route/permission registry و SQL migrations) اجرا می‌شود. وابستگی runtime به کلاس‌های `PromaInventoryPartners` ندارد؛ سرویس‌ها و controllerهای عملیاتی در namespace جدید قرار دارند.

## مالکیت داده

در migration، جداول موجودی/فروش/journal جدیدی ساخته نشده است. کد successor روی جداول `plugin_inventory_*` قبلی کار می‌کند تا دورهٔ انتقال zero-copy باشد. فقط جدول نسخه‌های سیاست مالی افزوده شده است. بنابراین این دو plugin دو کد provider برای یک دادهٔ واحدند و نباید هم‌زمان فعال شوند.

## قواعد مالی

`Domain/InstallmentPricingCalculator` مستقل از UI و دیتابیس است. ورودی نرخ basis points و مبلغ integer Rial است. برای سود مرکب، ضریب در مقیاس صحیح ثابت 1e8 محاسبه می‌شود؛ خروجی هر مرحله و قسط با round-half-up به ریال گرد می‌شود. قسط‌ها با توزیع باقیماندهٔ صحیح جمعشان را دقیقاً با مانده برابر می‌کنند.

`Services/FinancialPolicyService` ورودی را اعتبارسنجی می‌کند و هر ذخیره را به‌صورت رکورد جدید در `plugin_inventory_financial_policy_versions` می‌نویسد. نسخه‌های قدیمی UPDATE نمی‌شوند. hash روی JSON canonical تولیدشده از ساختار ثابت policy ذخیره و در audit ثبت می‌شود.

## جریان‌های موجود و شکاف اتصال

سرویس‌های کالا، خرید، فروش، پرداخت، دفترکل و تسویه از پیاده‌سازی نسخهٔ قبلی منتقل شده‌اند. صفحهٔ جدید سیاست اقساط در این build ابزار quote و نسخه‌بندی است؛ quote هنوز مبلغ قرارداد core را تغییر نمی‌دهد و Snapshot را روی فروش ثبت نمی‌کند. پیش از فعال‌سازی Production این adapter باید در staging با منطق قرارداد/پیش‌پرداخت Proma Pay آزمایش و تکمیل شود.
