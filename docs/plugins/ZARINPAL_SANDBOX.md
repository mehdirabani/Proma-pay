# محیط آزمایشی زرین‌پال

Endpointهای مورد استفاده:

- Request: `https://sandbox.zarinpal.com/pg/v4/payment/request.json`
- Verify: `https://sandbox.zarinpal.com/pg/v4/payment/verify.json`
- StartPay: `https://sandbox.zarinpal.com/pg/StartPay/{authority}`

Merchant آزمایشی هرگز از Merchant عملیاتی پر نمی‌شود. تراکنش sandbox با `environment=sandbox` ذخیره و در پنل مدیریت برچسب‌گذاری می‌شود. پیشوند Authority محیط آزمایشی نیز باید با `S` شروع شود تا با رکورد production اشتباه نشود.

## اثر مالی

اثر مالی sandbox به صورت پیش‌فرض خاموش است. در این حالت Verify رسمی انجام و تراکنش با وضعیت `sandbox_verified` ثبت می‌شود، اما پرداخت موفق هسته ساخته نمی‌شود و مانده قسط تغییر نمی‌کند. فعال‌سازی اثر مالی فقط با مجوز صریح sandbox و برای تست کنترل‌شده ممکن است.

صفحه «محیط آزمایشی زرین‌پال» یک فرم واقعی برای ساخت درخواست sandbox دارد. شناسه و مبلغ قسط دوباره از دیتابیس خوانده می‌شوند و عملیات به کد تأیید تصادفی نیاز دارد.

تست خودکار پروژه endpoint selection، payload، redirect و code `101` را با transport کنترل‌شده بررسی می‌کند. آزمون زنده شبکه به Merchant آزمایشی معتبر و دامنه Callback قابل دسترسی عمومی نیاز دارد.
