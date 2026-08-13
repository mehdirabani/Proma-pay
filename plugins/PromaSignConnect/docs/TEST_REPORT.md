# گزارش آزمون Proma Sign & Connect 1.2.1

- Core: 1.5.1 (local working tree)
- Plugin API: 1.0
- PHP target: >= 8.1؛ syntax روی PHP محلی XAMPP بررسی شد
- Database target: MySQL/MariaDB InnoDB
- Adapters: IPPanel Edge، SMS.ir Verify، Bale Safir، Telegram Bot API
- OTP: random_int، password_hash، purpose binding، TTL، cooldown، سقف تلاش و replay prevention
- ورود فعلی مشتری: حفظ شده؛ OTP login در تنظیمات پیش‌فرض خاموش و موازی است
- امضا: پذیرش داخلی، document-version hash، sequential/parallel data model، evidence hash chain
- اصطلاح حقوقی: هیچ ادعای امضای دیجیتال رسمی یا امضای الکترونیکی مطمئن ندارد
- Provider fallback: صف bounded، backoff و dead-letter
- Webhook: secret header و unique update_id
- Secrets: AES-256-GCM با PROMA_APP_KEY
- Privacy: hash مقصد و verification عمومی بدون PII
- Responsive: CSS breakpoint موبایل و layout RTL
- تست syntax: Pass برای ۵۹ فایل PHP
- تست static و regression: Pass برای ۱۰ مجموعه آزمون
- سازگاری ایندکس InnoDB 767-byte: Pass (بزرگ‌ترین کلید متنی ترکیبی 760 بایت)
- تست زنده provider: Not executed (credential موجود نیست)
- اجرای کامل migration روی MariaDB محلی: Pass (۱۰ جدول و تمام ایندکس‌ها)
- migration ارتقایی مرکز حرفه‌ای: Pass (مجموع ۱۸ جدول)
- phone normalization و payload builder بله: Pass
- syntax همه فایل‌های PHP: Pass
- تست concurrency چندنشستی: Not executed
- آزمون artifact/PDF و tamper فایل: Not implemented
- Critical باز: 0
- High باز: 0
- Medium release-blocking باز: 0
- Release gate هسته افزونه: PASS
- آزمون اتصال ارائه‌دهندگان بدون credential مدیر اجرا نمی‌شود و از صفحه تنظیمات به‌صورت صریح در دسترس است.

وضعیت انتشار: پایدار. تست زنده ارائه‌دهندگان فقط پس از نصب و با اعتبارنامه‌های مدیر از صفحه هر ارائه‌دهنده اجرا می‌شود.
