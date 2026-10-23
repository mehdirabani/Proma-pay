# قرارداد API فروشندگان — نسخهٔ v1

مسیر پایه `https://<host>/<base>/api/v1` است. درخواست‌ها باید HTTPS باشند و با `Authorization: Bearer pmp_test_...` احراز هویت شوند. هر token به یک connection، فروشنده و سایت محدود و دارای مجوزهای صریح است. هر تغییر وضعیت باید `Idempotency-Key` یکتا با طول 16 تا 100 نویسه بفرستد.

## مجوزها و endpointها

| Method / path | Scope | رفتار |
|---|---|---|
| `GET /connection` | `merchant.read` | وضعیت و محیط همین اتصال |
| `GET /policies/financial` | `policy:read` | policy نسخه‌دار و حداقل دادهٔ checkout |
| `GET /policies/guarantee` | `policy:read` | policy ضمانت همین اتصال و حدود مرکزی |
| `POST /quotes` | `quote:create` | محاسبهٔ server-side و صدور quote ذخیره‌شده و منقضی‌شونده |
| `POST /orders` | `order:create` | مصرف یک quote همان اتصال و ثبت درخواست `pending_review` |
| `POST /orders/{order_uuid}/cancel` | `order:cancel` | لغو درخواست هنوز پیوندنخورده به قرارداد |
| `POST /contracts` | `contract:write` | ثبت درخواست بررسی قرارداد، نه ایجاد قرارداد فعال |
| `GET /contracts/{order_uuid}` | `contract:read` | وضعیت درخواست و قرارداد پیوندخورده، در صورت وجود |
| `GET /guarantees/{order_uuid}` | `guarantee:read` | وضعیت ضمانت واقعیِ پیوندخورده یا `not_created` |
| `GET /webhooks` | `webhook:read` | آمار صف؛ انتقال outbound تا پیکربندی transport فقط در outbox می‌ماند |
| `POST /connection/emergency-disable` | `connection:manage` | قطع فوری همین اتصال |

## Quote و سفارش

در `POST /quotes` اعداد صحیح تومان بفرستید:

```json
{"cash_price_toman":1000000,"down_payment_toman":250000,"months":3,"interest_method":"flat"}
```

روش نرخ از policy مرکزی انتخاب می‌شود (`flat`, `compound`, یا `reducing_balance` فقط اگر policy آن را منتشر کند). پاسخ شامل `quote_uuid`, `policy_version_uuid`, زمان انقضا، محاسبه و برنامهٔ اقساط است. برای کانال WooCommerce مبلغ چک و سررسید آن از policy guarantee مرکزی/اتصال محاسبه می‌شود؛ سررسید چک باید با اولین قسط یکی باشد.

`POST /orders` باید دقیقاً همان ورودی quote را به‌علاوهٔ شناسهٔ سفارش فروشگاه و `quote_uuid` ارسال کند:

```json
{"external_order_id":"WC-123","quote_uuid":"<uuid>","cash_price_toman":1000000,"down_payment_toman":250000,"months":3,"interest_method":"flat"}
```

quote یک‌بارمصرف، tenant-scoped و منقضی‌شونده است. درخواست با policy دیگری دوباره محاسبه نمی‌شود. تکرار همان Idempotency-Key و payload پاسخ ذخیره‌شده را برمی‌گرداند؛ استفادهٔ دوباره از quote با سفارش متفاوت رد می‌شود.

در `POST /contracts` فقط `order_uuid` و `external_customer_reference` گرفته می‌شود. مرجع مشتری به SHA-256 تبدیل می‌شود و مقدار خام نگهداری نمی‌شود. این مرجع به‌تنهایی برای تطبیق با حساب مشتری داخلی کافی نیست. پاسخ `pending_review` است؛ تا زمان تأیید داخلی، قرارداد، بدهی یا ضمانت واقعی ساخته نمی‌شود.

## وضعیت‌ها، پول و خطا

- سفارش API ممکن است `pending_review`, `approved`, `rejected`, `cancelled` باشد.
- درخواست قرارداد به‌صورت جداگانه `not_requested` یا `pending_review` و بعداً نتیجهٔ بررسی دارد.
- مقدارهای API با پسوند `_toman` تومان هستند؛ مبلغ چک با پسوند `_rial` ریال است. client حق محاسبهٔ نرخ، قسط، مبلغ چک یا مبلغ تسویه را ندارد.
- `gross_receivable_toman` تعهد قراردادی است و موجودی نقدی نیست. فقط تسویهٔ با وضعیت واقعی `paid` به‌عنوان پرداخت ثبت‌شده گزارش می‌شود.
- خطاهای اعتبارسنجی `4xx`، نبود scope `403`، دادهٔ tenant ناموجود `404`، تعارض idempotency یا شناسهٔ سفارش `409`، محدودیت نرخ `429` و نیاز TLS `426` دارند.
- payload وب‌هوک در outbox مرکزی نگهداری می‌شود؛ این API هنوز ادعای ارسال واقعی callback نمی‌کند.

تمام مثال‌ها ساختگی‌اند. برای تست از دیتابیس مستقل و token با محیط `test` استفاده کنید.
