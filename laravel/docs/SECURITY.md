# امنیت

## کنترل‌های موجود در این برش

- نشست Laravel با encryption فعال در نمونهٔ env، CSRF محافظت‌شده توسط framework و rate limit ورود وب و صدور token (۵ درخواست در دقیقه).
- API به token Sanctum با ability `proma-api` و عمر پیکربندی‌شدهٔ ۲۴ ساعت محدود است؛ دسترسی routeها permission middleware دارد.
- کاربر غیرفعال رد می‌شود؛ مشتریان برای کارمند شعبه‌دار query-scoped هستند و policyهای مشاهده/ایجاد نیز فراخوانی می‌شوند.
- password hashing توسط Laravel؛ مدیر بدون حساب/گذرواژهٔ پیش‌فرض و از ورودی تعاملی CLI ساخته می‌شود.
- شناسهٔ ملی رمزگذاری‌شده و hash یکتا، request ID و audit برای تغییر سقف اعتبار/عملیات حساس پایه ثبت می‌شوند.
- مبالغ صحیح تومان‌اند؛ idempotency key و تراکنش برای تغییر سقف اعتبار و payment intent اعمال شده است.

## موانع انتشار تولید

- هنوز security review کامل، threat-model اجرایی، audit dependency نهایی، DAST، بررسی کنترل دسترسی همهٔ صفحات/API و آزمون tenant isolation کامل وجود ندارد.
- مجوز کامل policy همهٔ objectها هنوز بازبینی نشده؛ هر query/mutation جدید باید branch scoping را در server side اعمال کند.
- audit event پایه است و tamper-evidence، دسترسی محدود DB، retention، export و alert عملیاتی تعریف نشده.
- کلید encryption و HMAC باید در secret manager نگهداری و rotation/re-encryption روی backup آزمایش شود. از دست‌رفتن APP_KEY ممکن است خواندن PII را ناممکن کند.
- token یک‌بار در پاسخ ایجاد ظاهر می‌شود؛ log/proxy/analytics را برای جلوگیری از افشای Authorization بررسی کنید.
- upload امن، antivirus، سیاست file type/size، signed URL و lifecycle مدارک هنوز پیاده نشده است.
- HTTPS، secure cookie، CSP/HSTS، backup رمزگذاری‌شده، restore drill، مدیریت secret و response headers باید در استقرار پیکربندی شوند.
- از `.env`, sqlite محلی، پایگاه داده و فایل‌های build حاوی config در commit/backup عمومی اجتناب کنید.

در [DEPLOYMENT](DEPLOYMENT.md) حداقل چک‌لیست عملیات آمده است. این فهرست گواهی امنیتی یا مجوز پردازش production نیست.
