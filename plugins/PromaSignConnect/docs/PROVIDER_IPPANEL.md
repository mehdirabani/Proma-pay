# IPPanel Edge

Base URL پیش‌فرض `https://edge.ippanel.com/v1` است. ارسال عادی و pattern از `POST /api/send` با هدر `Authorization` انجام می‌شود.

- ارسال عادی: `sending_type=webservice`، خط `from_number`، متن `message` و گیرنده در `params.recipients` قرار می‌گیرد.
- ارسال pattern: `sending_type=pattern`، شناسه `code`، یک گیرنده E.164 در `recipients` و مقادیر جایگزین در `params` قرار می‌گیرند.
- ارسال فقط وقتی پذیرفته‌شده ثبت می‌شود که HTTP موفق، `meta.status` موفق و شناسه outbox در پاسخ وجود داشته باشد.
- پیش از فعال‌سازی و پس از تغییر کلید یا خط، یک تست ارسال موفق لازم است.
