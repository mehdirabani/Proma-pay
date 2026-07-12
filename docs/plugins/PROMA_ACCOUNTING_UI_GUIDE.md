# راهنمای رابط Proma Accounting

تمام CSSها زیر `.proma-accounting` محدودند. کنترل تک‌خطی 44px، کارت 22px و صفحه در دسکتاپ 24px padding دارد. در تبلت مقادیر 19px و در موبایل 13/15px استفاده می‌شود.

ساختار هر فیلد شامل label، control و help است. checkbox و radio از قواعد input متنی مستثنی هستند. switch حداقل ارتفاع لمسی 58px، focus visible و `aria-checked` دارد. grid در دسکتاپ 12 ستونه و در موبایل تک‌ستونه می‌شود.

برای فیلد پیچیده از `details/summary` استفاده می‌شود تا hover تنها راه دسترسی نباشد. dark mode از متغیرهای scoped استفاده می‌کند.
