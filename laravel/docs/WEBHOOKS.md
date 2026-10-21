# Webhookها و پردازش غیرهم‌زمان

در این برش هیچ webhook ورودی/خروجی یا درگاه پرداختی فعال نیست. `payments` فقط intent معلق ثبت می‌کند؛ واردکردن callback ساختگی یا علامت‌زدن پرداخت موفق از پنل/API فعلی پشتیبانی نمی‌شود.

## قرارداد پیشنهادی پیش از پیاده‌سازی

1. برای callback درگاه، امضا را روی raw body با secret محیط بررسی کنید؛ timestamp، replay window، allowlist ارائه‌دهنده و TLS را هم اعمال کنید. IP تنها معیار اعتماد نباشد.
2. ابتدا رویداد را در جدول inbox با `provider + event_id` یکتا ثبت کنید؛ پاسخ سریع دهید و اعمال مالی را در job تراکنشی انجام دهید.
3. تغییر وضعیت، ثبت مرجع ارائه‌دهنده، allocation ledger، audit و outbox را در یک تراکنش انجام دهید. قفل/unique constraint مرجع نهایی idempotency باشد.
4. وضعیت‌های غیرقابل بازگشت، retry و out-of-order را تعریف کنید. پرداخت موفق را دوباره به موفقی تبدیل کردن نباید balance را دو بار تغییر دهد.
5. outbox را با dispatcher قابل retry، backoff محدود، dead-letter/alert و ابزار replay کنترل‌شده ارسال کنید. secret، token، کد ملی و payload کامل را log نکنید.
6. آزمون provider sandbox شامل signature معتبر/نامعتبر، payload تکراری، timeout، پاسخ دیررس، مبلغ/واحد اشتباه، پرداخت لغوشده و race دو callback را اجرا کنید.

پیش از فعال‌سازی، adapter درگاه قدیمی (`helpers/ZibalClient.php`, `ZibalGatewayProvider.php`) و تبدیل ریال/تومان را با مستندات رسمی ارائه‌دهنده و تراکنش‌های مرجع تطبیق دهید. این سند طراحی آینده است؛ هیچ‌یک از موارد بالا در کد حاضر پیاده نشده‌اند.
