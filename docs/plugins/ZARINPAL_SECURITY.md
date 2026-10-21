# امنیت افزونه زرین‌پال

- callback به session و CSRF وابسته نیست، اما هیچ اثر مالی بدون Verify ندارد.
- initiation فقط POST، CSRF و scope مشتری را می‌پذیرد.
- مبلغ، مشتری، قرارداد و قسط از رکوردهای سرور اعتبارسنجی می‌شوند.
- Verify از `gateway_amount` ذخیره‌شده استفاده می‌کند.
- Authority و redirect با allowlist و قالب محدود ساخته می‌شوند.
- SSL peer/host verification فعال و redirect خودکار cURL غیرفعال است.
- Merchant با AES-256-GCM و کلید خارج از دیتابیس ذخیره می‌شود.
- Merchant کامل، session، کد ملی، آدرس و PAN کامل log نمی‌شوند.
- `(environment, authority)`, `local_order_id` و `idempotency_key` قید یکتا دارند.
- `FOR UPDATE` و وضعیت `verifying` از callback هم‌زمان جلوگیری می‌کنند.
- deactivation با تراکنش unresolved مسدود است.
- محیط production و sandbox، Merchant و Authority مستقل دارند؛ Authority نامتناسب با محیط رد می‌شود.
- sandbox به صورت پیش‌فرض هیچ اثر مالی روی پرداخت و قسط ندارد.
- CSV خروجی در برابر Formula Injection خنثی می‌شود.
- تراکنش مبهم یا ناموفق می‌تواند بدون تغییر مالی برای بررسی دستی علامت‌گذاری شود.
