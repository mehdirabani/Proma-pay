# ممیزی فنی افزونه درگاه زرین‌پال پرما

تاریخ ممیزی: 2026-07-13

## وضعیت مبنا

| مورد | وضعیت فعلی |
|---|---|
| شاخه | `release/v1.3.3` |
| Commit | `7e5bdecaa10f208ed3ffb9dca5cb9e49fa3707af` |
| نسخه Proma Pay | `1.3.3` |
| نسخه Plugin API | `1.0` |
| حداقل PHP پروژه | `8.1` |
| PHP محیط توسعه | `8.2.12` |
| نسخه افزونه هدف | `proma-zarinpal` نسخه `1.0.0` |

نسخه فعلی هسته از حداقل موردنیاز افزونه (`1.2.6`) جدیدتر است و از نظر نسخه پایه سازگار محسوب می‌شود.

## معماری پرداخت فعلی

- `PaymentsController` مستقیماً `ZibalClient` را برای درخواست، انتقال و Verify صدا می‌زند.
- مسیرهای فعلی زیبال `payments/zibal`، `payments/zibalGroup` و `payments/callback` هستند.
- Callback زیبال عمومی است و پس از Verify سمت سرور، `Payment::completeGateway()` یا `PaymentGroupService::completeGateway()` را اجرا می‌کند.
- هسته در حال حاضر interface یا registry عمومی برای چند درگاه ندارد؛ بنابراین افزودن زرین‌پال بدون extension point باعث وابستگی مستقیم کنترلر پرداخت به افزونه می‌شود.
- جدول عمومی مستقل `payment_gateway_transactions` در schema اجرایی فعلی وجود ندارد. رکورد pending تک‌قسطی در `payments` و رکورد گروهی در `payment_groups` نگه‌داری می‌شود.

## معماری زیبال

- Client زیبال در `helpers/ZibalClient.php` قرار دارد و از API نسخه جاری زیبال استفاده می‌کند.
- مبلغ داخلی تومان قبل از ارسال به زیبال در Client به ریال تبدیل می‌شود.
- تنظیمات زیبال در جدول عمومی `settings` نگه‌داری می‌شوند.
- شناسه `gateway_track_id` در جدول `payments` یکتا است.
- پرداخت گروهی، `gateway_track_id` و فهرست اقساط را در `payment_groups` ذخیره می‌کند.
- رفتار زیبال باید بدون حذف مسیرها و تنظیمات قبلی حفظ شود و از طریق adapter داخلی در registry جدید نیز قابل استفاده باشد.

## جداول و وضعیت‌های پرداخت

### `payments`

- ارتباط‌ها: `installment_id`, `payment_group_id`, `contract_id`, `user_id`
- مبلغ: `amount DECIMAL(18,2)` بر مبنای تومان
- درگاه: `method`, `gateway_track_id`, `gateway_ref_id`
- وضعیت‌های اجرایی مشاهده‌شده: `pending`, `paid`, `corrected`
- فقط `paid` و اصلاح‌نشده روی مبلغ پرداخت‌شده قسط اثر دارد.

### `payment_groups`

- وضعیت‌های اجرایی: `pending`, `completed` و حالت‌های دستی موجود
- مبلغ‌های `requested_amount` و `allocated_amount` بر مبنای تومان
- فهرست اقساط در `selection_json`
- کلید `idempotency_key` یکتا است.

### `payment_allocations`

- هر تخصیص به گروه، پرداخت، قرارداد و قسط متصل است.
- قید یکتای `(payment_group_id, installment_id)` از تخصیص دوباره همان قسط در یک گروه جلوگیری می‌کند.

## تخصیص و پرداخت گروهی

- `PaymentGroupService` مبلغ را فقط در همان قرارداد توزیع می‌کند.
- ترتیب تخصیص ابتدا اقساط انتخابی و سپس اقساط بعدی بر اساس شماره قسط است.
- مبلغ بیشتر از کل بدهی قابل تخصیص قبل از نهایی‌سازی رد می‌شود.
- عملیات نهایی در transaction و با قفل `FOR UPDATE` انجام می‌شود.
- پیاده‌سازی فعلی نام روش `zibal` را hardcode کرده است و برای چند درگاه باید پارامتر درگاه دریافت کند، بدون تغییر رفتار مسیرهای قدیمی.

## Callback و idempotency فعلی

- Callback زیبال به session مشتری وابسته نیست.
- Callback به‌تنهایی پرداخت را قطعی نمی‌کند و Verify سمت سرور الزامی است.
- `Payment::completeGateway()` پرداخت `paid` را دوباره ثبت نمی‌کند.
- `PaymentGroupService::completeGateway()` گروه `completed` را دوباره تخصیص نمی‌دهد.
- برای زرین‌پال protection مستقل بر مبنای `(environment, authority)`، وضعیت `verifying` و بازیابی امن کد `101` لازم است.

## تنظیمات و Secretها

- مدل عمومی `Settings` فقط ستون `is_secret` را علامت می‌زند و رمزنگاری واقعی در runtime فعلی ندارد.
- ذخیره Merchant ID زرین‌پال در تنظیمات عمومی به صورت متن خام قابل قبول نیست.
- افزونه باید تنظیمات اختصاصی و AES-256-GCM با کلید خارج از دیتابیس در `storage/private` داشته باشد.
- Merchant ID کامل نباید در view، log، exception یا audit ثبت شود؛ فقط مقدار mask‌شده و fingerprint ذخیره/نمایش داده می‌شود.

## Audit و Financial Log

- `AuditLog::record()` رویدادها، actor، scope قرارداد/مشتری/قسط، IP و user-agent را ثبت می‌کند.
- `PluginHooks` کلیدهای حساس payload را redaction می‌کند.
- هسته ledger عمومی مستقلی برای همه پرداخت‌ها ندارد؛ اثر مالی قطعی از `payments`, `payment_allocations` و snapshot پرداخت حاصل می‌شود.
- افزونه حسابداری اختیاری است و رویداد `payment.completed` را دریافت می‌کند. زرین‌پال نباید مستقیم در جداول حسابداری بنویسد.

## extension pointهای موجود

- Provider چرخه عمر `install`, `activate`, `deactivate`, `update`, `uninstall`, `healthCheck` دارد.
- افزونه می‌تواند route، menu، permission، asset و event listener ثبت کند.
- route می‌تواند با `auth=false` عمومی باشد؛ callback زرین‌پال از همین قابلیت استفاده می‌کند.
- assetهای افزونه فقط در routeهای همان افزونه بارگذاری می‌شوند.

## extension pointهای لازم در هسته

1. `PaymentGatewayProviderInterface` برای قرارداد مشترک درگاه‌ها.
2. `PaymentGatewayRegistry` برای ثبت adapter زیبال و provider افزونه‌ای زرین‌پال.
3. مسیر عمومی initiation تک‌قسطی و گروهی که gateway انتخابی را از registry بگیرد.
4. متد عمومی ساخت pending با `method` و reference اختصاصی در `Payment` و `PaymentGroupService`.
5. نهایی‌سازی سازگار با transaction والد و روش عمومی fail/cancel برای pendingها.
6. نمایش gatewayهای فعال در پنل اقساط، بدون شناخت کلاس زرین‌پال در view یا controller.

## فایل‌های هسته تحت تأثیر

- `core/PaymentGatewayProviderInterface.php` (جدید)
- `core/PaymentGatewayRegistry.php` (جدید)
- `helpers/ZibalGatewayProvider.php` (جدید)
- `controllers/PaymentsController.php`
- `models/Payment.php`
- `helpers/PaymentGroupService.php`
- `views/installments/index.php`
- مستندات، تست‌های static و release builder

هیچ service، repository، migration یا controller اختصاصی زرین‌پال در پوشه‌های عادی هسته قرار نمی‌گیرد.

## فایل‌ها و migrationهای افزونه

- ریشه افزونه: `plugins/PromaZarinpal/`
- Manifest، provider، route، controller، service، repository، DTO، view، asset، lang و test مطابق specification افزونه ایجاد می‌شوند.
- Migration اول:
  - `proma_zarinpal_settings`
  - `proma_zarinpal_transactions`
  - `proma_zarinpal_logs`
- قید یکتا برای `(environment, authority)` و `local_order_id` لازم است.
- migration باید فقط `CREATE TABLE IF NOT EXISTS` و indexهای idempotency را اعمال کند و داده مالی موجود را تغییر ندهد.

## ریسک‌های امنیتی

- جعل مبلغ، customer، contract یا installment از مرورگر.
- قطعی فرض کردن `Status=OK` بدون Verify.
- Verify با مبلغ جاری قسط به جای مبلغ ذخیره‌شده تراکنش.
- callback تکراری و تخصیص/اعلان دوباره.
- نشت Merchant ID، payload خام، card PAN یا خطای فنی provider.
- open redirect، callback دامنه خارجی، SSRF و endpoint غیرمجاز.
- TLS غیرفعال، timeout نامحدود و پاسخ JSON بزرگ/نامعتبر.
- تعویض Merchant بعد از initiation؛ Verify باید snapshot رمزگذاری‌شده همان تراکنش را مصرف کند.
- غیرفعال‌سازی افزونه هنگام وجود تراکنش unresolved.

## ریسک‌های سازگاری

- مسیرهای قدیمی زیبال باید همچنان قابل استفاده باشند.
- schema موجود status `paid` را به جای `approved` استفاده می‌کند؛ افزونه باید با invariant اجرایی پروژه هماهنگ بماند.
- `Payment::completeGateway()` فعلی transaction مستقل باز می‌کند و برای نهایی‌سازی اتمیک زرین‌پال باید transaction والد را تشخیص دهد.
- provider افزونه فقط پس از فعال‌سازی boot می‌شود؛ زرین‌پال بعد از نصب به صورت پیش‌فرض غیرفعال می‌ماند.
- callback افزونه غیرفعال‌شده در registry اجرا نمی‌شود؛ بنابراین deactivation با تراکنش unresolved باید مسدود شود.

## ترتیب پیاده‌سازی

1. افزودن interface/registry عمومی و adapter زیبال.
2. عمومی‌سازی ساخت pending و finalization بدون تغییر invariantهای مالی.
3. ساخت migration، repository، DTO و تنظیمات امن افزونه.
4. ساخت Client رسمی Zarinpal v4، converter مبلغ و error mapper.
5. پیاده‌سازی request، redirect allowlist، callback، Verify و codeهای `100/101`.
6. افزودن controllerها، permissionها، منو، viewها و assetهای افزونه.
7. افزودن انتخاب درگاه در پنل مشتری و مسیر initiation عمومی.
8. تست unit، integration با transport شبیه‌سازی‌شده، security، sandbox endpoint selection و regression زیبال.
9. مستندسازی، bump نسخه هسته، ساخت ZIP/manifest، commit و push.

## شرط پذیرش ممیزی

پیاده‌سازی فقط زمانی قابل تحویل پایدار است که callback بدون session کار کند، مبلغ ذخیره‌شده Verify شود، کد `101` دوباره اثر مالی نسازد، Merchant و اطلاعات کارت نشت نکند، زیبال سالم بماند و بسته افزونه از مدیر چرخه افزونه نصب و فعال شود.
