# بله سفیر چندحسابی

هر بازو پیش‌نویس، آزموده‌شده، فعال، غیرفعال یا بایگانی است. فعال‌سازی فقط پس از ارسال آزمایشی موفق مجاز است. کلید API با AES-256-GCM ذخیره و پس از ثبت هرگز بازگردانده نمی‌شود.

نمونه امن:

```bash
curl --location 'https://safir.bale.ai/api/v3/send_message' \
  --header 'api-access-key: <BALE_API_ACCESS_KEY>' \
  --header 'Content-Type: application/json' \
  --data '{
    "request_id": "<UNIQUE_REQUEST_ID>",
    "bot_id": 123456789,
    "phone_number": "989123456789",
    "message_data": {"message": {"text": "پیام آزمایشی پرما پی"}}
  }'
```

کلید افشاشده باید فوراً در پنل کسب‌وکار بله باطل و جایگزین شود. افزونه هیچ کلیدی را از متن گفتگو، نمونه یا سورس استفاده نمی‌کند.
