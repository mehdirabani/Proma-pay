# افزونه درگاه زرین‌پال

`proma-zarinpal` یک provider مستقل برای `PaymentGatewayRegistry` است. هسته فقط interface عمومی درگاه را می‌شناسد و تمام controllerها، repositoryها، migrationها، تنظیمات و assetهای زرین‌پال در `plugins/PromaZarinpal` قرار دارند.

## معماری

1. هسته مالک محاسبه مبلغ قابل پرداخت و scope مشتری/قرارداد/قسط است.
2. افزونه یک pending محلی و تراکنش زرین‌پال را قبل از تماس خارجی ذخیره می‌کند.
3. درخواست رسمی v4 ارسال و Authority ذخیره می‌شود.
4. Callback فقط Authority و Status را می‌پذیرد.
5. `Status=OK` فقط Verify را شروع می‌کند.
6. codeهای `100/101` داخل transaction دیتابیس به سرویس پرداخت هسته تحویل می‌شوند.
7. `payment.completed` برای افزونه حسابداری اختیاری منتشر می‌شود.

## وضعیت‌ها

`created`, `request_uncertain`, `pending`, `callback_received`, `verifying`, `paid`, `failed`, `cancelled`, `verification_failed`

`request_uncertain` هرگز خودکار درخواست جدید نمی‌سازد. مدیر باید وضعیت را بررسی کند تا پرداخت دوگانه رخ ندهد.

## Extension pointها

- `PaymentGatewayProviderInterface`
- `PaymentGatewayRegistry`
- `payment.completed`
- `payment.group.completed`
- `report.data.providers`

زیبال به عنوان adapter داخلی در همان registry باقی مانده و مسیرهای قدیمی آن نیز حفظ شده‌اند.
