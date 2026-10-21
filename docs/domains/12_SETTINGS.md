# 12 — Settings Domain

مستند دامنه تنظیمات سیستم در پروژه **Proma Pay**

---

## فهرست مطالب

- [12 — Settings Domain](#12--settings-domain)
  - [فهرست مطالب](#فهرست-مطالب)
  - [هدف این دامنه](#هدف-این-دامنه)
  - [مرز دامنه](#مرز-دامنه)
    - [این دامنه مسئول است برای:](#این-دامنه-مسئول-است-برای)
    - [این دامنه مسئول نیست برای:](#این-دامنه-مسئول-نیست-برای)
  - [تعریف تنظیمات](#تعریف-تنظیمات)
  - [اصل مهم Settings](#اصل-مهم-settings)
  - [دسته‌بندی تنظیمات](#دستهبندی-تنظیمات)
  - [موجودیت‌های اصلی](#موجودیتهای-اصلی)
    - [settings](#settings)
    - [setting\_history](#setting_history)
    - [setting\_groups](#setting_groups)
  - [نوع مقدار تنظیمات](#نوع-مقدار-تنظیمات)
  - [تنظیمات عمومی](#تنظیمات-عمومی)
  - [تنظیمات برند](#تنظیمات-برند)
  - [تنظیمات مالی](#تنظیمات-مالی)
  - [تنظیمات اقساط](#تنظیمات-اقساط)
  - [تنظیمات پرداخت](#تنظیمات-پرداخت)
  - [تنظیمات کارت‌به‌کارت](#تنظیمات-کارتبهکارت)
  - [تنظیمات قرارداد](#تنظیمات-قرارداد)
  - [تنظیمات اعلان‌ها](#تنظیمات-اعلانها)
  - [تنظیمات چت](#تنظیمات-چت)
  - [تنظیمات فایل‌ها](#تنظیمات-فایلها)
  - [تنظیمات حقوقی](#تنظیمات-حقوقی)
  - [تنظیمات تقویم](#تنظیمات-تقویم)
  - [تنظیمات امنیتی](#تنظیمات-امنیتی)
  - [تنظیمات بکاپ و آپدیت](#تنظیمات-بکاپ-و-آپدیت)
  - [تنظیمات پلاگین‌ها](#تنظیمات-پلاگینها)
  - [تنظیمات حساس](#تنظیمات-حساس)
  - [Cache تنظیمات](#cache-تنظیمات)
  - [Workflowها](#workflowها)
    - [مشاهده تنظیمات](#مشاهده-تنظیمات)
    - [ذخیره تنظیمات](#ذخیره-تنظیمات)
    - [تغییر تنظیم حساس](#تغییر-تنظیم-حساس)
  - [Permissionهای پیشنهادی](#permissionهای-پیشنهادی)
    - [Settings عمومی](#settings-عمومی)
    - [گروه‌های تنظیمات](#گروههای-تنظیمات)
    - [قالب‌ها](#قالبها)
  - [دسترسی نقش‌ها](#دسترسی-نقشها)
    - [super\_admin](#super_admin)
    - [admin](#admin)
    - [accountant](#accountant)
    - [lawyer](#lawyer)
    - [operator](#operator)
    - [customer](#customer)
  - [Eventها](#eventها)
  - [Logging و Audit](#logging-و-audit)
    - [Logهای عادی](#logهای-عادی)
    - [Audit Log](#audit-log)
    - [Security Log](#security-log)
  - [قوانین UI](#قوانین-ui)
  - [قوانین امنیتی](#قوانین-امنیتی)
  - [Sanitization تنظیمات HTML](#sanitization-تنظیمات-html)
  - [نکات دیتابیس](#نکات-دیتابیس)
  - [قوانین Validation](#قوانین-validation)
  - [قابلیت پلاگینی](#قابلیت-پلاگینی)
  - [تنظیمات پلاگین](#تنظیمات-پلاگین)
  - [اثر روی دامنه‌های دیگر](#اثر-روی-دامنههای-دیگر)
  - [چک‌لیست پیاده‌سازی](#چکلیست-پیادهسازی)
  - [چک‌لیست بازبینی](#چکلیست-بازبینی)
  - [Definition of Done](#definition-of-done)
  - [قابلیت‌های آینده](#قابلیتهای-آینده)
  - [پایان فایل](#پایان-فایل)

---

## هدف این دامنه

دامنه **Settings** مسئول مدیریت تمام تنظیمات قابل تغییر سیستم در Proma Pay است.

هدف این دامنه این است که تنظیمات مهم سیستم در فایل‌های پراکنده، کدهای Hardcode شده یا دیتابیس نامنظم پخش نشوند.

تمام تنظیمات باید از یک ساختار مرکزی، قابل کنترل، قابل اعتبارسنجی، قابل Cache و قابل Audit مدیریت شوند.

---

## مرز دامنه

### این دامنه مسئول است برای:

- مدیریت تنظیمات عمومی سیستم
- مدیریت تنظیمات برند
- مدیریت تنظیمات مالی
- مدیریت تنظیمات اقساط
- مدیریت تنظیمات پرداخت
- مدیریت تنظیمات کارت‌به‌کارت
- مدیریت تنظیمات قرارداد
- مدیریت قالب قرارداد
- مدیریت تنظیمات اعلان‌ها
- مدیریت تنظیمات چت
- مدیریت تنظیمات فایل‌ها
- مدیریت تنظیمات حقوقی
- مدیریت تنظیمات تقویم
- مدیریت تنظیمات امنیتی
- مدیریت تنظیمات بکاپ و آپدیت
- مدیریت تنظیمات پلاگین‌ها
- اعتبارسنجی تنظیمات قبل از ذخیره
- Cache کردن تنظیمات پرمصرف
- ثبت Audit Log برای تغییرات حساس

### این دامنه مسئول نیست برای:

- اجرای پرداخت
- تولید قرارداد
- محاسبه مستقیم اقساط
- ارسال مستقیم اعلان
- اجرای بکاپ
- اجرای آپدیت
- نصب پلاگین
- مدیریت کاربران
- مدیریت مشتریان

این دامنه فقط تنظیمات را نگهداری و ارائه می‌کند. اجرای عملیات در دامنه مربوط انجام می‌شود.

---

## تعریف تنظیمات

تنظیمات مجموعه‌ای از مقادیر قابل تغییر هستند که رفتار سیستم را کنترل می‌کنند.

نمونه:

- نام سامانه
- لوگو
- شماره کارت فروشگاه
- فعال بودن پرداخت آنلاین
- تعداد روز قبل از سررسید برای Reminder
- حداکثر حجم فایل آپلود
- قالب قرارداد
- قوانین جریمه
- قوانین تخفیف
- فعال بودن چت مشتری
- فعال بودن بکاپ زمان‌بندی‌شده

---

## اصل مهم Settings

هیچ مقدار قابل تغییر نباید Hardcode شود.

ممنوع:

```php
$cardNumber = '6037-xxxx-xxxx-xxxx';
```

صحیح:

```php
$cardNumber = Settings::get('payment.card_to_card.card_number');
```

---

## دسته‌بندی تنظیمات

دسته‌های اصلی تنظیمات:

| دسته | توضیح |
|---|---|
| `general` | تنظیمات عمومی |
| `brand` | برند و ظاهر پایه |
| `financial` | تنظیمات مالی |
| `installments` | تنظیمات اقساط |
| `payments` | پرداخت و درگاه |
| `card_to_card` | اطلاعات کارت‌به‌کارت |
| `contracts` | قالب و قوانین قرارداد |
| `notifications` | اعلان‌ها |
| `chat` | گفت‌وگو |
| `files` | فایل‌ها و Upload |
| `legal` | حقوقی |
| `calendar` | تقویم و Reminder |
| `security` | امنیت |
| `backup` | بکاپ |
| `update` | آپدیت |
| `plugins` | پلاگین‌ها |

---

## موجودیت‌های اصلی

### settings

جدول اصلی تنظیمات.

| فیلد | توضیح |
|---|---|
| `id` | شناسه |
| `group` | گروه تنظیم |
| `key` | کلید تنظیم |
| `value` | مقدار تنظیم |
| `value_type` | نوع مقدار |
| `is_encrypted` | رمزنگاری شده یا نه |
| `is_public` | قابل مشاهده عمومی یا نه |
| `is_system` | سیستمی یا قابل حذف |
| `description` | توضیح |
| `created_at` | تاریخ ایجاد |
| `updated_at` | بروزرسانی |

---

### setting_history

تاریخچه تغییرات تنظیمات حساس.

| فیلد | توضیح |
|---|---|
| `id` | شناسه |
| `setting_key` | کلید تنظیم |
| `old_value` | مقدار قبلی |
| `new_value` | مقدار جدید |
| `changed_by` | تغییر دهنده |
| `changed_at` | زمان تغییر |
| `reason` | دلیل تغییر در صورت نیاز |
| `metadata` | داده تکمیلی |

---

### setting_groups

گروه‌بندی تنظیمات.

| فیلد | توضیح |
|---|---|
| `id` | شناسه |
| `name` | نام سیستمی |
| `display_name` | عنوان نمایشی |
| `description` | توضیح |
| `sort_order` | ترتیب نمایش |
| `is_active` | فعال بودن |
| `created_at` | تاریخ ایجاد |
| `updated_at` | بروزرسانی |

---

## نوع مقدار تنظیمات

نوع‌های قابل پشتیبانی:

| نوع | توضیح |
|---|---|
| `string` | متن |
| `integer` | عدد صحیح |
| `decimal` | عدد اعشاری |
| `boolean` | بله/خیر |
| `json` | داده ساختاریافته |
| `text` | متن بلند |
| `html` | HTML کنترل‌شده |
| `file_id` | فایل |
| `date` | تاریخ |
| `time` | زمان |
| `datetime` | تاریخ و زمان |
| `encrypted` | مقدار رمزنگاری‌شده |

---

## تنظیمات عمومی

تنظیمات عمومی پیشنهادی:

| کلید | توضیح |
|---|---|
| `general.app_name` | نام سامانه |
| `general.app_url` | آدرس سامانه |
| `general.timezone` | منطقه زمانی |
| `general.locale` | زبان پیش‌فرض |
| `general.date_format` | فرمت تاریخ |
| `general.currency` | واحد پول |
| `general.currency_symbol` | نماد واحد پول |
| `general.items_per_page` | تعداد آیتم در هر صفحه |
| `general.maintenance_mode` | حالت تعمیرات |

قوانین:

- نام سامانه پیش‌فرض باید `پروما` باشد.
- Timezone باید قابل تنظیم باشد.
- واحد پول باید در تمام بخش‌های مالی یکسان استفاده شود.
- Maintenance Mode فقط برای مدیر مجاز قابل تغییر باشد.

---

## تنظیمات برند

تنظیمات برند:

| کلید | توضیح |
|---|---|
| `brand.name` | نام برند |
| `brand.logo_file_id` | لوگو |
| `brand.favicon_file_id` | فاوآیکون |
| `brand.primary_color` | رنگ اصلی |
| `brand.footer_text` | متن فوتر |
| `brand.developer_name` | نام توسعه‌دهنده |
| `brand.developer_email` | ایمیل توسعه‌دهنده |
| `brand.developer_github` | گیت‌هاب توسعه‌دهنده |

مقادیر پیشنهادی توسعه‌دهنده:

```text
developer_name = مهدی ربانی
developer_email = pgm.mehdirabani@gmail.com
developer_github = https://github.com/mehdirabani/
```

قوانین:

- لوگو باید از Files Domain مدیریت شود.
- تغییر لوگو باید Validation فایل داشته باشد.
- متن فوتر نباید شامل Script باشد.
- رنگ‌ها باید با Design System سازگار باشند.

---

## تنظیمات مالی

تنظیمات مالی حساس هستند.

نمونه کلیدها:

| کلید | توضیح |
|---|---|
| `financial.default_profit_mode` | نوع محاسبه داخلی |
| `financial.default_profit_rate` | نرخ داخلی |
| `financial.show_internal_profit` | نمایش سود داخلی |
| `financial.allow_manual_adjustments` | اجازه اصلاح مالی دستی |
| `financial.allow_overpayment` | اجازه پرداخت اضافه |
| `financial.rounding_mode` | روش گرد کردن |
| `financial.rounding_precision` | دقت گرد کردن |

قوانین:

- مشتری نباید تنظیمات سود داخلی را ببیند.
- تغییر تنظیمات مالی باید Audit Log داشته باشد.
- تنظیمات مالی باید فقط برای نقش مجاز نمایش داده شود.
- فرمول و نرخ داخلی نباید در پنل مشتری یا قرارداد عمومی افشا شود.

---

## تنظیمات اقساط

کلیدهای پیشنهادی:

| کلید | توضیح |
|---|---|
| `installments.default_count` | تعداد پیش‌فرض اقساط |
| `installments.default_due_day_interval` | فاصله پیش‌فرض سررسیدها |
| `installments.due_soon_days` | چند روز قبل از سررسید هشدار داده شود |
| `installments.allow_custom_installments` | اجازه قسط سفارشی |
| `installments.allow_partial_payment` | اجازه پرداخت ناقص |
| `installments.print_booklet_enabled` | فعال بودن دفترچه اقساط |

قوانین:

- تغییر تنظیمات تولید اقساط روی قراردادهای قبلی نباید بی‌دلیل اثر بگذارد.
- قراردادهای قبلی باید Snapshot یا داده ذخیره‌شده خود را داشته باشند.
- تغییر تنظیمات اقساط باید Audit Log داشته باشد.

---

## تنظیمات پرداخت

کلیدهای پیشنهادی:

| کلید | توضیح |
|---|---|
| `payments.gateway_enabled` | فعال بودن پرداخت آنلاین |
| `payments.card_to_card_enabled` | فعال بودن کارت‌به‌کارت |
| `payments.manual_payment_enabled` | فعال بودن پرداخت دستی |
| `payments.receipt_max_size` | حداکثر حجم رسید |
| `payments.receipt_allowed_types` | فرمت‌های مجاز رسید |
| `payments.prevent_duplicate_transactions` | جلوگیری از تراکنش تکراری |

قوانین:

- اطلاعات درگاه نباید Hardcode شود.
- کلیدهای حساس درگاه باید encrypted ذخیره شوند.
- تغییر تنظیمات پرداخت باید Audit Log داشته باشد.
- رسید پرداخت پیش‌فرض حداکثر 1MB باشد.

---

## تنظیمات کارت‌به‌کارت

کلیدهای پیشنهادی:

| کلید | توضیح |
|---|---|
| `card_to_card.bank_name` | نام بانک |
| `card_to_card.card_number` | شماره کارت |
| `card_to_card.account_owner` | صاحب حساب |
| `card_to_card.iban` | شماره شبا |
| `card_to_card.account_number` | شماره حساب |
| `card_to_card.description` | توضیحات پرداخت |
| `card_to_card.card_design` | نوع طراحی کارت |

قوانین:

- شماره کارت باید از Settings خوانده شود.
- شماره کارت نباید در کد Hardcode شود.
- تغییر شماره کارت باید Audit Log داشته باشد.
- کارت گرافیکی باید با HTML/CSS ساخته شود، نه تصویر ثابت.
- مشتری فقط اطلاعات لازم برای واریز را ببیند.

---

## تنظیمات قرارداد

کلیدهای پیشنهادی:

| کلید | توضیح |
|---|---|
| `contracts.default_template_id` | قالب پیش‌فرض قرارداد |
| `contracts.contract_number_prefix` | پیشوند شماره قرارداد |
| `contracts.allow_multiple_items` | اجازه چند کالا |
| `contracts.require_guarantee` | الزام ضمانت |
| `contracts.require_guarantor` | الزام ضامن |
| `contracts.pdf_enabled` | فعال بودن PDF |
| `contracts.print_enabled` | فعال بودن چاپ |
| `contracts.show_imei_fields` | نمایش IMEI |

قوانین:

- قالب قرارداد باید قابل ویرایش باشد.
- تغییر قالب قرارداد نباید PDFهای قبلی را خراب کند.
- تغییر قالب قرارداد باید Audit Log داشته باشد.
- متن قرارداد نباید فرمول داخلی سود را افشا کند.
- خروجی قرارداد باید RTL و قابل چاپ باشد.

---

## تنظیمات اعلان‌ها

کلیدهای پیشنهادی:

| کلید | توضیح |
|---|---|
| `notifications.enabled` | فعال بودن اعلان‌ها |
| `notifications.in_app_enabled` | اعلان درون‌برنامه‌ای |
| `notifications.chat_bot_enabled` | پیام ربات |
| `notifications.sms_enabled` | پیامک |
| `notifications.email_enabled` | ایمیل |
| `notifications.deduplication_enabled` | جلوگیری از اعلان تکراری |
| `notifications.default_due_reminder_days` | یادآوری پیش‌فرض سررسید |

قوانین:

- نسخه پایه حداقل باید in_app را داشته باشد.
- SMS و Email باید از طریق Plugin یا Channel رسمی اضافه شوند.
- متن اعلان مشتری نباید اطلاعات داخلی داشته باشد.
- تغییر قالب اعلان حساس باید Audit Log داشته باشد.

---

## تنظیمات چت

کلیدهای پیشنهادی:

| کلید | توضیح |
|---|---|
| `chat.enabled` | فعال بودن چت |
| `chat.customer_chat_enabled` | چت مشتری |
| `chat.internal_chat_enabled` | چت داخلی |
| `chat.announcement_channel_enabled` | کانال عمومی |
| `chat.attachment_enabled` | فایل در چت |
| `chat.attachment_max_size` | حداکثر حجم فایل چت |
| `chat.auto_delete_attachments_days` | حذف خودکار فایل چت |
| `chat.bot_enabled` | ربات سیستم |

قوانین:

- مشتری نباید چت داخلی را ببیند.
- فایل چت باید Validation داشته باشد.
- فایل چت حساس باید Private باشد.
- حذف خودکار فایل‌های چت باید قابل تنظیم باشد.

---

## تنظیمات فایل‌ها

کلیدهای پیشنهادی:

| کلید | توضیح |
|---|---|
| `files.default_max_size` | حداکثر حجم پیش‌فرض |
| `files.identity_document_max_size` | حداکثر حجم مدرک هویتی |
| `files.payment_receipt_max_size` | حداکثر حجم رسید |
| `files.legal_document_max_size` | حداکثر حجم فایل حقوقی |
| `files.allowed_image_types` | فرمت‌های تصویر مجاز |
| `files.private_storage_enabled` | فعال بودن Storage خصوصی |
| `files.cleanup_enabled` | پاکسازی خودکار |

مقادیر پیش‌فرض پیشنهادی:

```text
identity_document_max_size = 1MB
payment_receipt_max_size = 1MB
chat_attachment_max_size = 1MB
legal_document_max_size = 5MB
```

---

## تنظیمات حقوقی

کلیدهای پیشنهادی:

| کلید | توضیح |
|---|---|
| `legal.enabled` | فعال بودن ماژول حقوقی |
| `legal.min_overdue_days` | حداقل روز تأخیر برای پیشنهاد حقوقی |
| `legal.min_overdue_installments` | حداقل تعداد اقساط معوق |
| `legal.min_debt_amount` | حداقل مبلغ بدهی |
| `legal.auto_suggestion_enabled` | پیشنهاد خودکار ارجاع |
| `legal.require_manager_approval` | نیاز به تأیید مدیر |
| `legal.default_lawyer_id` | وکیل پیش‌فرض |

قوانین:

- ارجاع حقوقی خودکار نباید بدون کنترل مدیریتی عملیات حساس انجام دهد.
- پیشنهاد خودکار با اجرای واقعی تفاوت دارد.
- تغییر آستانه‌های حقوقی باید Audit Log داشته باشد.

---

## تنظیمات تقویم

کلیدهای پیشنهادی:

| کلید | توضیح |
|---|---|
| `calendar.enabled` | فعال بودن تقویم |
| `calendar.default_view` | نمای پیش‌فرض |
| `calendar.reminders_enabled` | فعال بودن Reminder |
| `calendar.default_reminder_minutes` | زمان پیش‌فرض یادآوری |
| `calendar.payment_promise_enabled` | فعال بودن وعده پرداخت |
| `calendar.legal_sessions_enabled` | فعال بودن جلسات حقوقی |

قوانین:

- Reminder باید از Notifications Domain ارسال شود.
- تقویم مشتری و کاربران داخلی باید از نظر Scope جدا باشند.
- تاریخ‌ها در UI شمسی و در ذخیره‌سازی استاندارد باشند.

---

## تنظیمات امنیتی

کلیدهای پیشنهادی:

| کلید | توضیح |
|---|---|
| `security.login_attempt_limit` | محدودیت تلاش ورود |
| `security.lockout_minutes` | مدت قفل بعد از تلاش ناموفق |
| `security.session_lifetime` | عمر Session |
| `security.force_https` | اجبار HTTPS |
| `security.csrf_enabled` | فعال بودن CSRF |
| `security.password_min_length` | حداقل طول رمز |
| `security.audit_sensitive_actions` | Audit عملیات حساس |
| `security.show_raw_errors` | نمایش خطای خام |

قوانین:

- `show_raw_errors` در Production باید false باشد.
- CSRF نباید غیرفعال شود مگر در محیط توسعه و با هشدار.
- تغییر تنظیمات امنیتی باید Audit Log و Security Log داشته باشد.

---

## تنظیمات بکاپ و آپدیت

کلیدهای پیشنهادی:

| کلید | توضیح |
|---|---|
| `backup.enabled` | فعال بودن بکاپ |
| `backup.auto_backup_enabled` | بکاپ خودکار |
| `backup.include_private_files` | شامل فایل‌های خصوصی |
| `backup.retention_days` | مدت نگهداری بکاپ |
| `update.enabled` | فعال بودن آپدیت |
| `update.require_backup_before_update` | الزام بکاپ قبل از آپدیت |
| `update.allow_plugin_updates` | اجازه آپدیت پلاگین‌ها |

قوانین:

- قبل از آپدیت باید بکاپ گرفته شود.
- بکاپ نباید در مسیر Public باشد.
- تغییر تنظیمات Backup و Update باید Audit Log داشته باشد.
- اگر ZipArchive نبود، Backup باید fallback داشته باشد.

---

## تنظیمات پلاگین‌ها

کلیدهای پیشنهادی:

| کلید | توضیح |
|---|---|
| `plugins.enabled` | فعال بودن سیستم پلاگین |
| `plugins.upload_enabled` | امکان Upload پلاگین |
| `plugins.auto_update_enabled` | آپدیت خودکار پلاگین |
| `plugins.require_backup_before_install` | الزام بکاپ قبل از نصب |
| `plugins.allow_unverified_plugins` | اجازه پلاگین تأییدنشده |
| `plugins.marketplace_enabled` | مارکت‌پلیس پلاگین در آینده |

قوانین:

- پلاگین‌ها باید Manifest داشته باشند.
- نصب پلاگین باید Permission و Audit Log داشته باشد.
- پلاگین نباید بدون بررسی فعال شود.
- تنظیمات پلاگین باید در namespace جدا ذخیره شود.

---

## تنظیمات حساس

تنظیمات زیر حساس محسوب می‌شوند:

- کلیدهای درگاه پرداخت
- تنظیمات مالی داخلی
- تنظیمات سود
- تنظیمات جریمه
- شماره کارت فروشگاه
- تنظیمات امنیتی
- تنظیمات Backup و Restore
- تنظیمات Update
- تنظیمات Plugin Upload
- تنظیمات SMTP یا SMS Provider
- توکن‌های API

قوانین:

- مقادیر حساس باید encrypted ذخیره شوند.
- مقادیر حساس نباید در Log خام ثبت شوند.
- نمایش مقادیر حساس در UI باید Mask شده باشد.
- تغییر مقادیر حساس باید Audit Log داشته باشد.

---

## Cache تنظیمات

تنظیمات پرمصرف باید Cache شوند.

قوانین:

- بعد از تغییر تنظیم، Cache مربوط باید پاک شود.
- Cache نباید باعث نمایش مقدار قدیمی در عملیات حساس شود.
- تنظیمات امنیتی و پرداخت باید بعد از تغییر فوراً اعمال شوند.
- در صورت خطای Cache، سیستم باید بتواند از دیتابیس مقدار را بخواند.

---

## Workflowها

### مشاهده تنظیمات

1. کاربر وارد Settings می‌شود.
2. Permission بررسی می‌شود.
3. گروه‌های قابل مشاهده بر اساس Role فیلتر می‌شوند.
4. مقادیر حساس Mask می‌شوند.
5. تنظیمات در UI نمایش داده می‌شوند.

---

### ذخیره تنظیمات

1. کاربر فرم تنظیمات را ارسال می‌کند.
2. CSRF بررسی می‌شود.
3. Permission بررسی می‌شود.
4. Validation انجام می‌شود.
5. مقادیر حساس Encrypt می‌شوند.
6. مقدار قبلی و جدید برای Audit آماده می‌شود.
7. تنظیمات ذخیره می‌شوند.
8. Cache پاک می‌شود.
9. Event مربوط Dispatch می‌شود.
10. Audit Log ثبت می‌شود.
11. Toast موفقیت نمایش داده می‌شود.

---

### تغییر تنظیم حساس

1. کاربر مقدار حساس را تغییر می‌دهد.
2. Permission سطح بالا بررسی می‌شود.
3. در صورت نیاز، تأیید دوباره گرفته می‌شود.
4. دلیل تغییر دریافت می‌شود.
5. مقدار ذخیره می‌شود.
6. Security Log ثبت می‌شود.
7. Audit Log ثبت می‌شود.
8. Notification برای مدیر اصلی در صورت نیاز ارسال می‌شود.

---

## Permissionهای پیشنهادی

### Settings عمومی

- `settings.view`
- `settings.update`
- `settings.view_sensitive`

### گروه‌های تنظیمات

- `settings.update_general`
- `settings.update_brand`
- `settings.update_financial`
- `settings.update_installments`
- `settings.update_payments`
- `settings.update_card_to_card`
- `settings.update_contracts`
- `settings.update_notifications`
- `settings.update_chat`
- `settings.update_files`
- `settings.update_legal`
- `settings.update_calendar`
- `settings.update_security`
- `settings.update_backup`
- `settings.update_update`
- `settings.update_plugins`

### قالب‌ها

- `settings.manage_contract_templates`
- `settings.manage_notification_templates`

---

## دسترسی نقش‌ها

### super_admin

می‌تواند همه تنظیمات را طبق Policy مدیریت کند.

### admin

می‌تواند بیشتر تنظیمات عمومی، برند، قرارداد، پرداخت و عملیاتی را طبق Permission تغییر دهد.

### accountant

می‌تواند برخی تنظیمات مالی یا پرداخت را فقط در صورت Permission ببیند یا تغییر دهد.

### lawyer

معمولاً فقط تنظیمات حقوقی محدود را در صورت Permission می‌بیند.

### operator

نباید به Settings دسترسی داشته باشد، مگر موارد بسیار محدود.

### customer

هرگز به Settings داخلی دسترسی ندارد.

---

## Eventها

Eventهای اصلی این دامنه:

- `SettingUpdated`
- `SettingGroupUpdated`
- `SensitiveSettingUpdated`
- `FinancialSettingsUpdated`
- `PaymentSettingsUpdated`
- `CardToCardSettingsUpdated`
- `ContractSettingsUpdated`
- `NotificationSettingsUpdated`
- `SecuritySettingsUpdated`
- `BackupSettingsUpdated`
- `PluginSettingsUpdated`
- `SettingsCacheCleared`

---

## Logging و Audit

### Logهای عادی

موارد زیر باید Log داشته باشند:

- مشاهده صفحه Settings
- ذخیره تنظیمات عمومی
- پاک شدن Cache تنظیمات
- خطای Validation تنظیمات

---

### Audit Log

موارد زیر باید Audit Log داشته باشند:

- تغییر تنظیمات مالی
- تغییر تنظیمات پرداخت
- تغییر شماره کارت
- تغییر قالب قرارداد
- تغییر تنظیمات امنیتی
- تغییر تنظیمات بکاپ
- تغییر تنظیمات آپدیت
- تغییر تنظیمات پلاگین‌ها
- تغییر تنظیمات فایل‌های خصوصی

---

### Security Log

موارد زیر باید Security Log داشته باشند:

- تلاش دسترسی به Settings بدون Permission
- تلاش تغییر تنظیمات حساس بدون Permission
- CSRF نامعتبر در Settings
- مقدار مشکوک در تنظیمات HTML
- تلاش غیرفعال کردن CSRF یا امنیت در Production

---

## قوانین UI

صفحه Settings باید ساختار تب‌بندی داشته باشد.

تب‌های پیشنهادی:

- عمومی
- برند
- مالی
- اقساط
- پرداخت
- کارت‌به‌کارت
- قرارداد
- اعلان‌ها
- چت
- فایل‌ها
- حقوقی
- تقویم
- امنیت
- بکاپ و آپدیت
- پلاگین‌ها

قوانین UI:

- هر تب فقط برای نقش مجاز نمایش داده شود.
- فیلدهای حساس Mask شوند.
- فیلدهای ضروری `*` داشته باشند.
- خطاها زیر همان فیلد نمایش داده شوند.
- ذخیره با Toast موفقیت و خطا باشد.
- تغییر تنظیم حساس Confirmation Modal داشته باشد.
- تنظیمات طولانی گروه‌بندی شوند.
- UI باید RTL و Responsive باشد.

---

## قوانین امنیتی

موارد الزامی:

- CSRF برای تمام فرم‌ها
- Permission سمت سرور
- Escape خروجی‌ها
- Validation دقیق مقدارها
- Encrypt مقادیر حساس
- Mask مقادیر حساس در UI
- عدم ثبت مقدار حساس در Log خام
- Audit Log برای تغییرات حساس
- Security Log برای دسترسی غیرمجاز
- پاکسازی Cache بعد از تغییر
- جلوگیری از HTML یا Script خطرناک

---

## Sanitization تنظیمات HTML

اگر تنظیمی از نوع HTML باشد، باید Sanitizer رسمی استفاده شود.

ممنوع:

```php
Settings::set('footer_text', $_POST['footer_text']);
```

صحیح:

```php
Settings::set('footer_text', HtmlSanitizer::clean($_POST['footer_text']));
```

---

## نکات دیتابیس

جدول‌های پیشنهادی:

- `settings`
- `setting_groups`
- `setting_history`

Indexهای پیشنهادی:

- `settings.group`
- `settings.key`
- `settings.is_encrypted`
- `settings.is_public`
- `setting_history.setting_key`
- `setting_history.changed_by`
- `setting_history.changed_at`

قانون:

`settings.key` باید یکتا باشد.

---

## قوانین Validation

- کلید تنظیمات باید یکتا باشد.
- group باید معتبر باشد.
- value_type باید معتبر باشد.
- boolean فقط مقدار معتبر بپذیرد.
- integer و decimal باید عدد معتبر باشند.
- رنگ باید فرمت معتبر داشته باشد.
- URL باید معتبر باشد.
- Email باید معتبر باشد.
- شماره کارت باید فرمت معتبر داشته باشد.
- حجم فایل باید عدد مثبت باشد.
- مقدارهای حساس باید Encrypt شوند.
- HTML باید Sanitized شود.
- تنظیمات امنیتی خطرناک در Production با هشدار یا محدودیت ذخیره شوند.

---

## قابلیت پلاگینی

Settings Domain باید آماده توسعه پلاگینی باشد.

Extension Pointهای پیشنهادی:

- `settings_tabs`
- `settings_sections`
- `settings_fields`
- `setting_before_save`
- `setting_after_save`
- `sensitive_setting_updated`
- `settings_validation_rules`
- `settings_cache_cleared`
- `plugin_settings_registered`

نمونه پلاگین‌های آینده:

- تنظیمات SMS Provider
- تنظیمات Email Provider
- تنظیمات درگاه پرداخت جدید
- تنظیمات OCR
- تنظیمات هوش مصنوعی
- تنظیمات Google Calendar
- تنظیمات فضای ابری
- تنظیمات حسابداری
- تنظیمات Offline Sync

---

## تنظیمات پلاگین

هر پلاگین باید تنظیمات خود را با namespace جدا ثبت کند.

الگو:

```text
plugin.{plugin_id}.{setting_key}
```

نمونه:

```text
plugin.offline_sync.enabled
plugin.offline_sync.sync_interval
plugin.sms_provider.api_key
```

قوانین:

- تنظیمات پلاگین نباید با تنظیمات Core تداخل داشته باشد.
- مقادیر حساس پلاگین باید encrypted باشند.
- حذف پلاگین نباید بدون تأیید تنظیمات آن را حذف کند.
- تغییر تنظیمات پلاگین باید Audit Log داشته باشد.

---

## اثر روی دامنه‌های دیگر

تغییرات Settings ممکن است روی تمام دامنه‌ها اثر بگذارد.

نمونه اثرها:

- تغییر تنظیمات مالی روی Financial Calculations اثر دارد.
- تغییر تنظیمات اقساط روی Installments اثر دارد.
- تغییر تنظیمات پرداخت روی Payments اثر دارد.
- تغییر شماره کارت روی کارت‌به‌کارت اثر دارد.
- تغییر قالب قرارداد روی Contracts اثر دارد.
- تغییر تنظیمات Reminder روی Calendar و Notifications اثر دارد.
- تغییر تنظیمات Upload روی Files اثر دارد.
- تغییر تنظیمات حقوقی روی Legal اثر دارد.
- تغییر تنظیمات پلاگین‌ها روی Plugin Manager اثر دارد.

---

## چک‌لیست پیاده‌سازی

قبل از پیاده‌سازی قابلیت مربوط به Settings بررسی شود:

- [ ] تنظیمات در Service مرکزی خوانده می‌شوند.
- [ ] مقدار قابل تغییر Hardcode نشده است.
- [ ] Permission سمت سرور بررسی می‌شود.
- [ ] مقادیر حساس encrypted هستند.
- [ ] مقادیر حساس در UI Mask می‌شوند.
- [ ] مقدارهای حساس در Log خام ذخیره نمی‌شوند.
- [ ] Validation دقیق انجام می‌شود.
- [ ] Cache بعد از تغییر پاک می‌شود.
- [ ] تغییرات حساس Audit Log دارند.
- [ ] تنظیمات پلاگین namespace جدا دارند.
- [ ] UI مطابق UI Constitution است.

---

## چک‌لیست بازبینی

قبل از Merge تغییرات این دامنه:

- [ ] مشتری به Settings دسترسی ندارد.
- [ ] operator به Settings حساس دسترسی ندارد.
- [ ] شماره کارت Hardcode نشده است.
- [ ] قالب قرارداد از Settings خوانده می‌شود.
- [ ] تنظیمات مالی از مشتری مخفی است.
- [ ] کلیدهای درگاه encrypted هستند.
- [ ] تغییر تنظیمات امنیتی Security Log دارد.
- [ ] تنظیمات HTML Sanitized می‌شوند.
- [ ] Cache تنظیمات درست invalidate می‌شود.
- [ ] Responsive بودن صفحه Settings بررسی شده است.

---

## Definition of Done

این دامنه زمانی کامل است که:

- تنظیمات از یک ساختار مرکزی مدیریت شوند.
- تنظیمات مهم Hardcode نباشند.
- تنظیمات به گروه‌های واضح تقسیم شده باشند.
- Permission و Scope برای مشاهده و تغییر تنظیمات رعایت شود.
- مقادیر حساس encrypted و Mask شوند.
- تغییرات حساس Audit Log داشته باشند.
- Cache تنظیمات درست مدیریت شود.
- تنظیمات پلاگین‌ها پشتیبانی شوند.
- UI تنظیمات ساده، RTL، مرتب و Responsive باشد.
- تغییر تنظیمات روی دامنه‌های مرتبط قابل پیش‌بینی باشد.

---

## قابلیت‌های آینده

در نسخه‌های آینده این دامنه باید آماده موارد زیر باشد:

- Settings Import / Export
- Environment-Based Settings
- Branch-Based Settings
- User-Level Preferences
- Advanced Settings Search
- Setting Versioning
- Setting Rollback
- Remote Configuration
- Feature Flags
- Plugin Settings Marketplace
- Settings Permission Builder
- AI Settings Assistant
- Configuration Health Check

---

## پایان فایل