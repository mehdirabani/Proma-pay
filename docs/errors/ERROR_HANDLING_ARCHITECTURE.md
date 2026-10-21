# معماری مدیریت خطا

`bootstrap.php` ابتدا `ErrorHandler::install()` را اجرا می‌کند. هر `HttpException` به همان status تبدیل می‌شود؛ خطاهای غیرمنتظره 500 می‌گیرند. درخواست‌های AJAX یا دارای `Accept: application/json` به JSON امن پاسخ می‌گیرند و بقیه HTML RTL مستقل دریافت می‌کنند.

## قرارداد JSON

```json
{
  "ok": false,
  "status": 422,
  "error": {
    "code": "HTTP_422",
    "title": "اطلاعات نیاز به اصلاح دارد",
    "message": "فیلدهای مشخص‌شده را بررسی کنید.",
    "request_id": "...",
    "details": null
  }
}
```

`details` فقط برای خطای دامنه‌ای مجاز در همان کنترلر فرستاده می‌شود و نباید شامل trace، SQL یا مقدار secret باشد.
