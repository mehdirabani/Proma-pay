# اتصال WordPress

اتصال یا افزونهٔ WordPress در این برش وجود ندارد. WordPress را فعلاً منبع احراز هویت یا مرجع وضعیت مالی Laravel فرض نکنید.

## طرح پیشنهادی

- افزونهٔ جداگانه با تنظیم base URL، TLS اجباری و API token مختص سرویس/حداقل مجوز؛ secret در تنظیمات محافظت‌شده ذخیره و در UI/log نمایش داده نشود.
- ارتباط از طریق قرارداد versioned `/api/v1`; هر درخواست مالی دارای idempotency UUID پایدار باشد. timeout و retry با همان UUID انجام شود.
- webhookهای signed همراه event id و replay protection؛ هیچ کدی در hook عمومی WordPress نباید بدون اعتبارسنجی، مشتری یا پرداخت ایجاد کند.
- برای فرم خرید/اعتبار، ابتدا سفارش WordPress را با شناسهٔ خارجی ثبت کنید؛ تأیید KYC/اعتبار یا موفقیت پرداخت تنها پس از پاسخ قطعی Laravel اعلام شود.
- فیلدهای PII حداقلی، consent و retention روشن، escaping خروجی، capability checks و nonce در فرم‌های admin رعایت شود.
- نسخه‌های WordPress/PHP/PHP extensions/WooCommerce و سازگاری caching را در CI افزونه تست کنید.

این‌ها نیازمندی طراحی‌اند، نه قابلیت آماده. endpoint idempotent سفارش خارجی، کلید خارجی سفارش و نسخهٔ API سازگار هنوز ساخته نشده‌اند.
