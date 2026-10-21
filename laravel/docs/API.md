# API نسخهٔ ۱

Base path: `/api/v1`. API از Laravel Sanctum personal access token استفاده می‌کند. ابتدا `POST /api/v1/tokens` با `email`, `password`, `device_name` درخواست می‌شود؛ پاسخ token از نوع Bearer است. توکن را فقط روی HTTPS در هدر `Authorization: Bearer …` بفرستید و آن را log نکنید. انقضای پیکربندی‌شده ۱۴۴۰ دقیقه است. token برای کاربر غیرفعال پذیرفته نمی‌شود. مسیر `DELETE /api/v1/tokens/current` توکن جاری را حذف می‌کند.

## مسیرها

| Method | Path | مجوز | کاربرد |
|---|---|---|---|
| `POST` | `/tokens` | ورود؛ محدودیت ۵ درخواست در دقیقه | صدور token |
| `DELETE` | `/tokens/current` | احراز هویت + token دارای ability | لغو token فعلی |
| `GET` | `/user` | `proma-api` | کاربر جاری (id/name/email) |
| `GET` | `/customers` | `customers.view` | فهرست صفحه‌بندی‌شده و شعبه‌محدود؛ query اختیاری `search`, `per_page` |
| `POST` | `/customers` | `customers.create` | ایجاد مشتری؛ پاسخ ۲۰۱ |
| `GET` | `/customers/{customer}` | `customers.view` | مشاهدهٔ مشتری با policy شعبه |
| `POST` | `/customers/{customer}/kyc-cases` | `customers.update` | ثبت پروندهٔ KYC دستی |
| `PUT` | `/kyc-cases/{kycCase}/review` | `kyc.review` | تأیید یا رد KYC؛ رد نیازمند `review_note` است |
| `GET` | `/customers/{customer}/credit-account` | `credit.view` | نمایش حساب اعتبار |
| `POST` | `/customers/{customer}/credit-adjustments` | `credit.adjust` | تغییر سقف؛ `new_limit_toman`, `reason`, `request_id` UUID |
| `GET` | `/payments` | `payments.view` | فهرست پرداخت‌های قابل‌مشاهده |
| `POST` | `/payment-intents` | `payments.record` | ثبت payment intent معلق؛ پاسخ ۲۰۱ |

محدودیت `per_page` در مسیرهای دارای pagination از ۱ تا ۱۰۰ است (پیش‌فرض ۲۰). `request_id` تغییر اعتبار باید در retry همان عملیات ثابت بماند. payment intent از `Idempotency-Key` header یا `idempotency_key` UUID در JSON می‌خواند.

## payloadهای مهم

ایجاد مشتری: `full_name`, `mobile`، و اختیاری `email`, `national_id`, `birth_date` در قالب میلادی `YYYY-MM-DD`. ارقام فارسی/عربی برای موبایل و کد ملی normalize می‌شوند. دادهٔ حساس کد ملی در پاسخ resource نباید خام برگردد.

ثبت درخواست پرداخت: `customer_id`, اختیاری `contract_id`, `idempotency_key`, `amount_toman` (عدد صحیح مثبت، نه ریال), `method` از enum فعلی، و اختیاری `provider_reference`. وضعیت پاسخ **pending** است. این API وجه را verify، تسویه یا بین اقساط تخصیص نمی‌دهد.

## خطا و مرز پایداری

اعتبارسنجی از پاسخ JSON استاندارد Laravel (۴۲۲)، احراز هویت ۴۰۱، مجوز/دامنهٔ شعبه ۴۰۳، رکورد ناموجود ۴۰۴ و محدودسازی ۴۲۹ استفاده می‌کند. پاسخ‌ها header شناسهٔ درخواست دارند. schema نهایی نسخه‌بندی نشده و هنوز قرارداد OpenAPI یا تضمین سازگاری backward وجود ندارد؛ کلاینت production را به این API پایه متصل نکنید تا قرارداد و آزمون سازگاری تصویب شود.
