# بررسی استقلال runtime

- manifest شناسه و provider مستقل `proma-partnership-ledger` را دارد.
- کلاس‌ها در namespace `Proma\Plugins\PartnershipLedger` بارگذاری می‌شوند.
- کد جدید به کلاس‌های namespace `Proma\Plugins\InventoryPartners` یا فایل‌های پوشهٔ افزونهٔ قدیمی ارجاع runtime نمی‌دهد.
- dependency manifest خالی است؛ اتصال‌ها فقط به API هستهٔ Proma Pay و hookهای عمومی آن هستند.
- schema عملیاتی عمداً مشترک مانده است؛ این استقلال runtime نیست به معنی schema جداگانه یا کپی داده نیست.
- route URLها به‌صورت backward-compatible روی `plugin/inventory-partners/*` باقی مانده‌اند. افزونهٔ قدیمی و successor نباید هم‌زمان فعال باشند.

آزمون runtime واقعیِ غیرفعال‌بودن افزونهٔ قدیمی تا نصب و cutover در staging انجام نشده است.
