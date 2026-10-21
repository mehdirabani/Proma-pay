# SMS.ir

API پایه `https://api.sms.ir/v1` و هدر احراز هویت `X-API-KEY` است.

- متن عادی از `POST /send/bulk` با `lineNumber`، `messageText` و `mobiles` ارسال می‌شود؛ شماره در قالب ملی `09...` فرستاده می‌شود.
- پیام Verify از `POST /send/verify` با `mobile` بدون صفر ابتدایی، `templateId` و آرایه `parameters` ارسال می‌شود.
- شناسه پیام گیرنده از `data.messageIds[0]` خوانده می‌شود. مقدار خالی یا صفر شکست همان گیرنده است، حتی اگر API یک `packId` برای کل درخواست برگرداند.
- پیش از فعال‌سازی و پس از تغییر کلید یا خط، یک تست ارسال موفق لازم است.
