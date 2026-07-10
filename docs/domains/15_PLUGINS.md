# 15 — Plugins Domain

مستند دامنه پلاگین‌ها، ماژول‌های توسعه‌پذیر و افزونه‌های سیستم در پروژه **Proma Pay**

---

## فهرست مطالب

- [15 — Plugins Domain](#15--plugins-domain)
  - [فهرست مطالب](#فهرست-مطالب)
  - [هدف این دامنه](#هدف-این-دامنه)
  - [مرز دامنه](#مرز-دامنه)
    - [این دامنه مسئول است برای:](#این-دامنه-مسئول-است-برای)
    - [این دامنه مسئول نیست برای:](#این-دامنه-مسئول-نیست-برای)
  - [تعریف پلاگین](#تعریف-پلاگین)
  - [اصل معماری پلاگین‌ها](#اصل-معماری-پلاگینها)
  - [انواع پلاگین‌ها](#انواع-پلاگینها)
  - [موجودیت‌های اصلی](#موجودیتهای-اصلی)
    - [plugins](#plugins)
    - [plugin\_versions](#plugin_versions)
    - [plugin\_settings](#plugin_settings)
    - [plugin\_permissions](#plugin_permissions)
    - [plugin\_migrations](#plugin_migrations)
    - [plugin\_logs](#plugin_logs)
  - [وضعیت‌های پلاگین](#وضعیتهای-پلاگین)
  - [ساختار فایل پلاگین](#ساختار-فایل-پلاگین)
  - [فایل plugin.json](#فایل-pluginjson)
  - [قوانین plugin.json](#قوانین-pluginjson)
  - [چرخه عمر پلاگین](#چرخه-عمر-پلاگین)
  - [نصب پلاگین](#نصب-پلاگین)
  - [فعال‌سازی پلاگین](#فعالسازی-پلاگین)
  - [غیرفعال‌سازی پلاگین](#غیرفعالسازی-پلاگین)
  - [حذف پلاگین](#حذف-پلاگین)
    - [حذف نرم](#حذف-نرم)
    - [حذف کامل](#حذف-کامل)
  - [آپدیت پلاگین](#آپدیت-پلاگین)
  - [Migrationهای پلاگین](#migrationهای-پلاگین)
  - [تنظیمات پلاگین](#تنظیمات-پلاگین)
  - [Permissionهای پلاگین](#permissionهای-پلاگین)
  - [Routeهای پلاگین](#routeهای-پلاگین)
  - [Eventها و Hookها](#eventها-و-hookها)
  - [Assetهای پلاگین](#assetهای-پلاگین)
  - [امنیت پلاگین‌ها](#امنیت-پلاگینها)
  - [کنترل ZIP پلاگین](#کنترل-zip-پلاگین)
  - [Offline Sync Plugin](#offline-sync-plugin)
  - [Workflowها](#workflowها)
    - [آپلود پلاگین](#آپلود-پلاگین)
    - [نصب پلاگین](#نصب-پلاگین-1)
    - [فعال‌سازی پلاگین](#فعالسازی-پلاگین-1)
    - [غیرفعال‌سازی پلاگین](#غیرفعالسازی-پلاگین-1)
    - [حذف پلاگین](#حذف-پلاگین-1)
  - [Permissionهای پیشنهادی](#permissionهای-پیشنهادی)
    - [Plugin Manager](#plugin-manager)
    - [Plugin Settings](#plugin-settings)
    - [Plugin Security](#plugin-security)
  - [دسترسی نقش‌ها](#دسترسی-نقشها)
    - [super\_admin](#super_admin)
    - [admin](#admin)
    - [accountant](#accountant)
    - [operator](#operator)
    - [lawyer](#lawyer)
    - [customer](#customer)
  - [Eventها](#eventها)
  - [Notificationها](#notificationها)
    - [PluginInstalled](#plugininstalled)
    - [PluginEnabled](#pluginenabled)
    - [PluginFailed](#pluginfailed)
    - [PluginSecurityViolationDetected](#pluginsecurityviolationdetected)
  - [Logging و Audit](#logging-و-audit)
    - [Logهای عادی](#logهای-عادی)
    - [Audit Log](#audit-log)
    - [Security Log](#security-log)
  - [قوانین UI](#قوانین-ui)
  - [قوانین فرم Upload پلاگین](#قوانین-فرم-upload-پلاگین)
  - [قوانین امنیتی](#قوانین-امنیتی)
  - [قوانین دیتابیس](#قوانین-دیتابیس)
  - [قوانین Validation](#قوانین-validation)
  - [قابلیت پلاگینی](#قابلیت-پلاگینی)
  - [اثر روی دامنه‌های دیگر](#اثر-روی-دامنههای-دیگر)
  - [چک‌لیست پیاده‌سازی](#چکلیست-پیادهسازی)
  - [چک‌لیست بازبینی](#چکلیست-بازبینی)
  - [Definition of Done](#definition-of-done)
  - [قابلیت‌های آینده](#قابلیتهای-آینده)
  - [پایان فایل](#پایان-فایل)

---

## هدف این دامنه

دامنه **Plugins** مسئول مدیریت پلاگین‌ها و ماژول‌های قابل نصب در Proma Pay است.

هدف این دامنه این است که سیستم از ابتدا قابلیت توسعه داشته باشد و بتوان در آینده بدون دستکاری مستقیم Core، امکانات جدید به سیستم اضافه کرد.

پلاگین‌ها باید بتوانند امکاناتی مثل درگاه پرداخت جدید، پیامک، اتصال به تلگرام، گزارش پیشرفته، بکاپ ابری، OCR رسید، اتصال به حسابداری، هوش مصنوعی، آفلاین‌سینک و ابزارهای حقوقی را به سیستم اضافه کنند.

---

## مرز دامنه

### این دامنه مسئول است برای:

- آپلود پلاگین
- اعتبارسنجی پلاگین
- نصب پلاگین
- فعال‌سازی پلاگین
- غیرفعال‌سازی پلاگین
- حذف پلاگین
- آپدیت پلاگین
- اجرای Migrationهای پلاگین
- مدیریت تنظیمات پلاگین
- مدیریت Permissionهای پلاگین
- ثبت Eventها و Hookهای پلاگین
- مدیریت Assetهای پلاگین
- کنترل امنیت پلاگین
- ثبت Log و Audit عملیات پلاگین
- آماده‌سازی سیستم برای Marketplace آینده

### این دامنه مسئول نیست برای:

- اجرای منطق اصلی قرارداد
- اجرای منطق اصلی پرداخت
- اجرای منطق اصلی اقساط
- اجرای منطق اصلی مالی
- مدیریت مستقیم کاربران
- مدیریت مستقیم مشتریان
- تغییر مستقیم فایل‌های Core بدون Update رسمی

پلاگین فقط باید از API، Hook، Event، Registry و Contractهای رسمی Core استفاده کند.

---

## تعریف پلاگین

پلاگین یک بسته نرم‌افزاری قابل نصب است که قابلیت جدیدی به Proma Pay اضافه می‌کند، بدون اینکه Core سیستم را مستقیماً تغییر دهد.

هر پلاگین باید:

- شناسه یکتا داشته باشد.
- نسخه داشته باشد.
- فایل Manifest داشته باشد.
- وابستگی‌های خود را اعلام کند.
- Permissionهای مورد نیاز را اعلام کند.
- Migrationهای خود را اعلام کند.
- تنظیمات خود را در namespace جدا ذخیره کند.
- قابل فعال و غیرفعال شدن باشد.
- قابل حذف یا آپدیت باشد.
- Log و Audit داشته باشد.

---

## اصل معماری پلاگین‌ها

اصل مهم:

> Core نباید به پلاگین وابسته باشد؛ پلاگین باید به Contractهای رسمی Core وابسته باشد.

ممنوع:

```php
require_once 'plugins/sms-provider/main.php';
```

صحیح:

```php
PluginManager::bootEnabledPlugins();
```

پلاگین نباید باعث شود Core بدون آن کار نکند.

اگر پلاگین غیرفعال شد، سیستم باید همچنان سالم اجرا شود.

---

## انواع پلاگین‌ها

انواع پلاگین‌های پیشنهادی:

| نوع | توضیح |
|---|---|
| `payment_gateway` | درگاه پرداخت جدید |
| `notification_channel` | کانال اعلان مثل SMS، Email، Telegram |
| `report` | گزارش جدید یا Widget گزارش |
| `backup_storage` | فضای ذخیره بکاپ |
| `file_storage` | Storage Driver جدید |
| `calendar_sync` | اتصال به تقویم خارجی |
| `legal_document` | تولید اسناد حقوقی |
| `ai_assistant` | قابلیت هوش مصنوعی |
| `ocr` | تشخیص متن از تصویر |
| `accounting` | اتصال به حسابداری |
| `offline_sync` | همگام‌سازی آفلاین |
| `ui_widget` | ویجت یا کامپوننت UI |
| `security` | ابزار امنیتی |
| `integration` | اتصال به سرویس بیرونی |

---

## موجودیت‌های اصلی

### plugins

جدول اصلی پلاگین‌ها.

| فیلد | توضیح |
|---|---|
| `id` | شناسه داخلی |
| `plugin_id` | شناسه یکتای پلاگین |
| `name` | نام پلاگین |
| `description` | توضیح |
| `version` | نسخه فعلی |
| `author` | سازنده |
| `type` | نوع پلاگین |
| `status` | وضعیت |
| `is_system` | سیستمی بودن |
| `installed_at` | زمان نصب |
| `enabled_at` | زمان فعال‌سازی |
| `disabled_at` | زمان غیرفعال‌سازی |
| `updated_at` | آخرین بروزرسانی |
| `metadata` | داده تکمیلی |

---

### plugin_versions

تاریخچه نسخه‌های پلاگین.

| فیلد | توضیح |
|---|---|
| `id` | شناسه |
| `plugin_id` | پلاگین |
| `version` | نسخه |
| `installed_at` | زمان نصب نسخه |
| `changelog` | تغییرات |
| `status` | وضعیت نسخه |
| `metadata` | داده تکمیلی |

---

### plugin_settings

تنظیمات اختصاصی پلاگین.

| فیلد | توضیح |
|---|---|
| `id` | شناسه |
| `plugin_id` | پلاگین |
| `key` | کلید |
| `value` | مقدار |
| `value_type` | نوع مقدار |
| `is_encrypted` | رمزنگاری شده |
| `created_at` | تاریخ ایجاد |
| `updated_at` | بروزرسانی |

---

### plugin_permissions

Permissionهای ثبت‌شده توسط پلاگین.

| فیلد | توضیح |
|---|---|
| `id` | شناسه |
| `plugin_id` | پلاگین |
| `permission_key` | کلید Permission |
| `title` | عنوان |
| `description` | توضیح |
| `created_at` | تاریخ ایجاد |

---

### plugin_migrations

Migrationهای پلاگین.

| فیلد | توضیح |
|---|---|
| `id` | شناسه |
| `plugin_id` | پلاگین |
| `migration_name` | نام Migration |
| `batch` | Batch |
| `status` | وضعیت |
| `executed_at` | زمان اجرا |
| `error_message` | خطا |

---

### plugin_logs

لاگ عملیات پلاگین.

| فیلد | توضیح |
|---|---|
| `id` | شناسه |
| `plugin_id` | پلاگین |
| `level` | سطح Log |
| `event_type` | نوع رویداد |
| `message` | پیام |
| `context` | داده تکمیلی |
| `created_at` | زمان ثبت |

---

## وضعیت‌های پلاگین

وضعیت‌های پیشنهادی:

| وضعیت | توضیح |
|---|---|
| `uploaded` | آپلود شده ولی نصب نشده |
| `validated` | اعتبارسنجی شده |
| `installed` | نصب شده |
| `enabled` | فعال |
| `disabled` | غیرفعال |
| `update_available` | آپدیت موجود |
| `updating` | در حال آپدیت |
| `failed` | خطادار |
| `uninstalled` | حذف نصب شده |
| `blocked` | مسدود شده به دلیل امنیتی |

---

## ساختار فایل پلاگین

ساختار پیشنهادی پلاگین:

```text
plugin-example.zip
└── plugin-example/
    ├── plugin.json
    ├── src/
    │   ├── Plugin.php
    │   ├── Providers/
    │   ├── Services/
    │   ├── Controllers/
    │   └── Listeners/
    ├── migrations/
    ├── views/
    ├── assets/
    │   ├── css/
    │   ├── js/
    │   └── images/
    ├── routes/
    ├── lang/
    ├── README.md
    └── CHANGELOG.md
```

---

## فایل plugin.json

هر پلاگین باید فایل `plugin.json` داشته باشد.

نمونه:

```json
{
  "id": "sms-provider",
  "name": "SMS Provider",
  "description": "ارسال پیامک از طریق Provider خارجی",
  "version": "1.0.0",
  "type": "notification_channel",
  "author": "Proma Pay",
  "min_proma_version": "1.0.0",
  "min_php": "7.4",
  "requires_backup": true,
  "permissions": [
    "plugin.sms_provider.view",
    "plugin.sms_provider.manage"
  ],
  "settings": [
    {
      "key": "api_key",
      "type": "encrypted",
      "required": true
    }
  ],
  "events": [
    "NotificationCreated"
  ],
  "migrations": [
    "2026_01_01_000001_create_sms_logs_table"
  ]
}
```

---

## قوانین plugin.json

- `id` الزامی است.
- `id` باید یکتا باشد.
- `version` الزامی است.
- `min_proma_version` الزامی است.
- `min_php` باید بررسی شود.
- `permissions` باید ثبت شوند.
- `settings` باید namespace جدا داشته باشند.
- `requires_backup` برای پلاگین‌های حساس باید true باشد.
- Manifest ناقص نباید نصب شود.

---

## چرخه عمر پلاگین

چرخه عمر پلاگین:

```text
upload → validate → install → enable → disable → update → uninstall
```

هر مرحله باید:

- Permission داشته باشد.
- Validation داشته باشد.
- Log داشته باشد.
- در عملیات حساس Audit Log داشته باشد.
- در صورت خطا وضعیت مشخص داشته باشد.

---

## نصب پلاگین

قوانین نصب:

- فقط کاربر مجاز بتواند پلاگین نصب کند.
- فایل پلاگین باید ZIP معتبر باشد.
- مسیرهای داخل ZIP باید امن باشند.
- plugin.json باید معتبر باشد.
- نسخه PHP و نسخه Proma Pay بررسی شود.
- قبل از نصب پلاگین حساس باید Backup گرفته شود.
- Migrationها با کنترل اجرا شوند.
- Permissionهای پلاگین ثبت شوند.
- تنظیمات پیش‌فرض پلاگین ثبت شوند.
- پلاگین بعد از نصب الزاماً فعال نشود، مگر تنظیمات اجازه دهد.

---

## فعال‌سازی پلاگین

فعال‌سازی یعنی پلاگین وارد Boot سیستم شود.

قوانین:

- پلاگین باید installed باشد.
- وابستگی‌ها باید فعال باشند.
- Migrationها باید اجرا شده باشند.
- تنظیمات ضروری باید تکمیل شده باشند.
- Boot پلاگین نباید Fatal Error ایجاد کند.
- اگر فعال‌سازی شکست خورد، پلاگین failed یا disabled شود.
- فعال‌سازی باید Audit Log داشته باشد.

---

## غیرفعال‌سازی پلاگین

غیرفعال‌سازی یعنی پلاگین دیگر در سیستم اجرا نشود.

قوانین:

- غیرفعال‌سازی نباید داده‌های پلاگین را حذف کند.
- Routeها، Hookها و Listenerهای پلاگین نباید اجرا شوند.
- اگر پلاگین در یک قابلیت فعال استفاده می‌شود، باید هشدار داده شود.
- غیرفعال‌سازی پلاگین حساس باید Confirmation داشته باشد.
- غیرفعال‌سازی باید Audit Log داشته باشد.

---

## حذف پلاگین

حذف پلاگین دو حالت دارد:

### حذف نرم

- فایل‌های پلاگین غیرفعال می‌شوند.
- داده‌ها و تنظیمات باقی می‌مانند.
- امکان نصب مجدد وجود دارد.

### حذف کامل

- فایل‌های پلاگین حذف می‌شوند.
- تنظیمات پلاگین حذف یا آرشیو می‌شوند.
- داده‌های پلاگین طبق Policy حذف می‌شوند.
- Migration برگشتی در صورت وجود اجرا می‌شود.

قوانین:

- حذف کامل باید تأیید چندمرحله‌ای داشته باشد.
- قبل از حذف کامل باید Backup گرفته شود.
- حذف کامل باید Audit Log داشته باشد.
- Core نباید بعد از حذف پلاگین خراب شود.

---

## آپدیت پلاگین

قوانین آپدیت:

- نسخه جدید باید از نسخه فعلی بالاتر باشد.
- min_proma_version بررسی شود.
- min_php بررسی شود.
- قبل از آپدیت باید Backup گرفته شود.
- Migrationهای جدید اجرا شوند.
- فایل‌های جدید جایگزین شوند.
- در صورت شکست، Rollback یا پیام واضح وجود داشته باشد.
- changelog نمایش داده شود.
- آپدیت باید Audit Log داشته باشد.

---

## Migrationهای پلاگین

Migration پلاگین فقط باید روی جدول‌های خود پلاگین یا جدول‌های مجاز اثر بگذارد.

قوانین:

- Migration باید Idempotent باشد.
- نام جدول‌های پلاگین باید prefix داشته باشند.
- Migration نباید جدول‌های Core را بدون Contract رسمی تغییر دهد.
- Migration مخرب ممنوع است، مگر با تأیید ویژه و Backup.
- نتیجه Migration باید در `plugin_migrations` ثبت شود.

الگوی نام جدول پلاگین:

```text
plugin_{plugin_id}_{table_name}
```

مثال:

```text
plugin_sms_provider_logs
```

---

## تنظیمات پلاگین

تنظیمات پلاگین باید namespace جدا داشته باشند.

الگو:

```text
plugin.{plugin_id}.{setting_key}
```

نمونه:

```text
plugin.sms_provider.api_key
plugin.sms_provider.sender_number
plugin.offline_sync.sync_interval
```

قوانین:

- تنظیمات حساس باید encrypted باشند.
- مقادیر حساس در UI باید Mask شوند.
- تغییر تنظیمات پلاگین باید Audit Log داشته باشد.
- حذف پلاگین نباید بدون تأیید تنظیمات آن را حذف کند.

---

## Permissionهای پلاگین

پلاگین می‌تواند Permission اختصاصی ثبت کند.

قوانین:

- Permission پلاگین باید با prefix شروع شود.
- Permission باید در RBAC ثبت شود.
- حذف پلاگین نباید Roleهای سیستم را خراب کند.
- اگر Permission حذف شد، نقش‌های مرتبط باید پاکسازی شوند.

الگوی پیشنهادی:

```text
plugin.{plugin_id}.{action}
```

نمونه:

```text
plugin.sms_provider.manage
plugin.ai_assistant.use
plugin.offline_sync.configure
```

---

## Routeهای پلاگین

Routeهای پلاگین باید Prefix مشخص داشته باشند.

الگو:

```text
/plugins/{plugin_id}/...
```

نمونه:

```text
/plugins/sms-provider/settings
/plugins/offline-sync/dashboard
```

قوانین:

- Routeهای پلاگین باید از Middlewareهای Core عبور کنند.
- Auth، CSRF و Permission باید رعایت شود.
- پلاگین نباید Routeهای Core را Override کند، مگر از Extension Point رسمی.
- Routeهای مشتری و Admin باید جدا باشند.

---

## Eventها و Hookها

پلاگین‌ها باید از Event و Hookهای رسمی استفاده کنند.

نمونه Eventهای قابل استفاده:

- `PaymentApproved`
- `InstallmentOverdue`
- `NotificationCreated`
- `LegalCaseCreated`
- `BackupCompleted`
- `ContractCreated`

نمونه Hookها:

- `payment_methods_registry`
- `notification_channels_registry`
- `report_widgets`
- `settings_tabs`
- `file_storage_drivers`
- `calendar_external_sync`

قوانین:

- پلاگین نباید Event را مختل کند.
- خطای پلاگین نباید کل سیستم را از کار بیندازد.
- Event Listener پلاگین باید Log خطا داشته باشد.
- اجرای Hookها باید قابل غیرفعال‌سازی باشد.

---

## Assetهای پلاگین

پلاگین می‌تواند CSS، JS و تصویر داشته باشد.

قوانین:

- Assetهای پلاگین باید از مسیر امن Load شوند.
- Asset نباید به فایل‌های Private دسترسی مستقیم داشته باشد.
- JS پلاگین نباید توکن یا اطلاعات حساس را در Frontend افشا کند.
- Assetها باید در صفحه‌های مربوط به همان پلاگین Load شوند، نه همه سیستم.
- حذف پلاگین باید Assetهای آن را پاک یا غیرفعال کند.

---

## امنیت پلاگین‌ها

پلاگین‌ها یکی از حساس‌ترین بخش‌های سیستم هستند.

موارد ممنوع:

- اجرای `eval`
- اجرای فایل PHP آپلودی بدون اعتبارسنجی
- Path Traversal
- دسترسی مستقیم به Private Storage
- تغییر فایل‌های Core
- تغییر جدول‌های Core بدون Contract رسمی
- غیرفعال کردن CSRF
- دور زدن Permission
- ثبت مقدار حساس در Log خام
- ایجاد Route بدون Auth در بخش Admin

---

## کنترل ZIP پلاگین

هنگام Upload پلاگین باید بررسی شود:

- فایل ZIP معتبر است.
- `plugin.json` وجود دارد.
- مسیر `../` وجود ندارد.
- فایل `.env` وجود ندارد.
- فایل اجرایی خطرناک وجود ندارد.
- ساختار پلاگین معتبر است.
- حجم فایل از حد مجاز بیشتر نیست.
- plugin_id با نام پوشه سازگار است.
- فایل‌های PHP فقط در مسیرهای مجاز هستند.

---

## Offline Sync Plugin

سیستم باید طوری طراحی شود که در آینده پلاگین آفلاین‌سینک قابل نصب باشد.

ویژگی‌های احتمالی:

- ذخیره موقت داده در مرورگر
- IndexedDB
- Service Worker
- صف عملیات آفلاین
- Sync بعد از اتصال اینترنت
- مدیریت Conflict
- نمایش وضعیت Sync
- گزارش خطاهای Sync

قوانین:

- Offline Sync نباید داده حساس را بدون رمزنگاری در مرورگر نگهداری کند.
- Sync باید Permission و Ownership را بعد از اتصال دوباره بررسی کند.
- عملیات مالی حساس نباید بدون تأیید سرور نهایی شود.
- پرداخت و تأیید رسید نباید صرفاً آفلاین نهایی شوند.
- Conflict باید قابل مشاهده و قابل حل باشد.

---

## Workflowها

### آپلود پلاگین

1. کاربر وارد Plugin Manager می‌شود.
2. فایل ZIP پلاگین را انتخاب می‌کند.
3. CSRF بررسی می‌شود.
4. Permission بررسی می‌شود.
5. فایل در Private Storage موقت ذخیره می‌شود.
6. ZIP اعتبارسنجی می‌شود.
7. plugin.json خوانده می‌شود.
8. سازگاری نسخه بررسی می‌شود.
9. نتیجه اعتبارسنجی نمایش داده می‌شود.
10. Log ثبت می‌شود.

---

### نصب پلاگین

1. پلاگین Validate شده انتخاب می‌شود.
2. Backup در صورت نیاز ساخته می‌شود.
3. فایل‌های پلاگین به مسیر plugins منتقل می‌شوند.
4. رکورد پلاگین ثبت می‌شود.
5. Permissionها ثبت می‌شوند.
6. Settings پیش‌فرض ثبت می‌شوند.
7. Migrationها اجرا می‌شوند.
8. وضعیت installed می‌شود.
9. Event نصب Dispatch می‌شود.
10. Audit Log ثبت می‌شود.

---

### فعال‌سازی پلاگین

1. کاربر دکمه فعال‌سازی را می‌زند.
2. Permission بررسی می‌شود.
3. وابستگی‌ها بررسی می‌شوند.
4. تنظیمات ضروری بررسی می‌شوند.
5. پلاگین Boot می‌شود.
6. وضعیت enabled می‌شود.
7. Cache پلاگین‌ها پاک می‌شود.
8. Notification ارسال می‌شود.
9. Audit Log ثبت می‌شود.

---

### غیرفعال‌سازی پلاگین

1. کاربر دکمه غیرفعال‌سازی را می‌زند.
2. هشدار نمایش داده می‌شود.
3. Permission بررسی می‌شود.
4. پلاگین از Registry حذف می‌شود.
5. وضعیت disabled می‌شود.
6. Cache پاک می‌شود.
7. Audit Log ثبت می‌شود.

---

### حذف پلاگین

1. کاربر نوع حذف را انتخاب می‌کند.
2. هشدار نمایش داده می‌شود.
3. تأیید متنی دریافت می‌شود.
4. Backup ساخته می‌شود.
5. پلاگین disabled می‌شود.
6. فایل‌ها حذف یا آرشیو می‌شوند.
7. تنظیمات و داده‌ها طبق Policy حذف یا نگهداری می‌شوند.
8. وضعیت uninstalled می‌شود.
9. Audit Log ثبت می‌شود.

---

## Permissionهای پیشنهادی

### Plugin Manager

- `plugins.view`
- `plugins.upload`
- `plugins.validate`
- `plugins.install`
- `plugins.enable`
- `plugins.disable`
- `plugins.update`
- `plugins.uninstall`
- `plugins.delete`
- `plugins.view_logs`

### Plugin Settings

- `plugins.settings.view`
- `plugins.settings.update`

### Plugin Security

- `plugins.view_sensitive`
- `plugins.manage_permissions`
- `plugins.manage_migrations`
- `plugins.force_disable`

---

## دسترسی نقش‌ها

### super_admin

می‌تواند همه عملیات پلاگین را انجام دهد.

### admin

می‌تواند فقط پلاگین‌های مجاز را مشاهده یا تنظیم کند، اگر Permission داشته باشد.

### accountant

نباید پلاگین نصب یا حذف کند.

### operator

نباید Plugin Manager را ببیند.

### lawyer

نباید Plugin Manager را ببیند.

### customer

هرگز نباید Plugin Manager را ببیند.

---

## Eventها

Eventهای اصلی این دامنه:

- `PluginUploaded`
- `PluginValidated`
- `PluginValidationFailed`
- `PluginInstalled`
- `PluginEnabled`
- `PluginDisabled`
- `PluginUpdated`
- `PluginUpdateFailed`
- `PluginUninstalled`
- `PluginDeleted`
- `PluginMigrationExecuted`
- `PluginMigrationFailed`
- `PluginSecurityViolationDetected`
- `PluginSettingsUpdated`

---

## Notificationها

### PluginInstalled

گیرنده:

- super_admin

پیام پیشنهادی:

> پلاگین جدید با موفقیت نصب شد.

---

### PluginEnabled

گیرنده:

- super_admin

پیام پیشنهادی:

> پلاگین فعال شد.

---

### PluginFailed

گیرنده:

- super_admin

پیام پیشنهادی:

> پلاگین با خطا مواجه شد و نیاز به بررسی دارد.

---

### PluginSecurityViolationDetected

گیرنده:

- super_admin

پیام پیشنهادی:

> رفتار مشکوک در یک پلاگین شناسایی شد.

---

## Logging و Audit

### Logهای عادی

موارد زیر باید Log داشته باشند:

- آپلود پلاگین
- اعتبارسنجی پلاگین
- نصب پلاگین
- فعال‌سازی پلاگین
- غیرفعال‌سازی پلاگین
- اجرای Hook پلاگین
- خطای Listener پلاگین
- اجرای Migration پلاگین

---

### Audit Log

موارد زیر باید Audit Log داشته باشند:

- نصب پلاگین
- فعال‌سازی پلاگین
- غیرفعال‌سازی پلاگین
- حذف پلاگین
- آپدیت پلاگین
- تغییر تنظیمات پلاگین
- تغییر Permissionهای پلاگین
- اجرای Migration حساس

---

### Security Log

موارد زیر باید Security Log داشته باشند:

- Upload پلاگین نامعتبر
- Path Traversal در ZIP
- plugin.json جعلی یا ناقص
- تلاش نصب بدون Permission
- تلاش اجرای Route بدون Auth
- تلاش دسترسی پلاگین به مسیر ممنوع
- تلاش تغییر فایل‌های Core
- خطای امنیتی Hook یا Listener

---

## قوانین UI

Plugin Manager باید شامل موارد زیر باشد:

- لیست پلاگین‌ها
- وضعیت هر پلاگین
- نسخه پلاگین
- نوع پلاگین
- سازنده پلاگین
- دکمه نصب
- دکمه فعال‌سازی
- دکمه غیرفعال‌سازی
- دکمه حذف
- دکمه آپدیت
- صفحه جزئیات پلاگین
- صفحه تنظیمات پلاگین
- نمایش Permissionهای پلاگین
- نمایش Migrationهای پلاگین
- نمایش Logهای پلاگین
- هشدارهای امنیتی

---

## قوانین فرم Upload پلاگین

فرم Upload باید:

- فقط ZIP بپذیرد.
- محدودیت حجم را نمایش دهد.
- Progress Bar داشته باشد.
- نتیجه Validation را نمایش دهد.
- خطاهای خوانا و فارسی نمایش دهد.
- قبل از نصب، اطلاعات پلاگین را نشان دهد.
- اگر پلاگین نیاز به Backup دارد، هشدار دهد.

---

## قوانین امنیتی

موارد الزامی:

- CSRF برای همه عملیات
- Permission سمت سرور
- Private Storage برای ZIP پلاگین
- بررسی plugin.json
- جلوگیری از Path Traversal
- جلوگیری از فایل‌های خطرناک
- Backup قبل از نصب، حذف یا آپدیت حساس
- Audit Log برای عملیات حساس
- Security Log برای رفتار مشکوک
- عدم اجرای پلاگین قبل از Validate
- امکان Force Disable برای super_admin

---

## قوانین دیتابیس

جدول‌های پیشنهادی:

- `plugins`
- `plugin_versions`
- `plugin_settings`
- `plugin_permissions`
- `plugin_migrations`
- `plugin_logs`

Indexهای پیشنهادی:

- `plugins.plugin_id`
- `plugins.status`
- `plugins.type`
- `plugin_versions.plugin_id`
- `plugin_settings.plugin_id`
- `plugin_settings.key`
- `plugin_permissions.plugin_id`
- `plugin_permissions.permission_key`
- `plugin_migrations.plugin_id`
- `plugin_migrations.migration_name`
- `plugin_logs.plugin_id`
- `plugin_logs.event_type`
- `plugin_logs.created_at`

قانون:

`plugins.plugin_id` باید یکتا باشد.

---

## قوانین Validation

- plugin_id الزامی و یکتا باشد.
- plugin_id فقط شامل حروف انگلیسی کوچک، عدد، خط تیره یا آندرلاین باشد.
- version معتبر باشد.
- min_proma_version با نسخه سیستم سازگار باشد.
- min_php با نسخه سرور سازگار باشد.
- plugin.json کامل باشد.
- ZIP مسیر خطرناک نداشته باشد.
- فایل‌های خطرناک رد شوند.
- Migrationها قبل از اجرا بررسی شوند.
- Permissionهای پلاگین prefix درست داشته باشند.
- تنظیمات حساس encrypted شوند.

---

## قابلیت پلاگینی

خود Plugins Domain نیز باید Extension Point داشته باشد.

Extension Pointهای پیشنهادی:

- `plugin_uploaded`
- `plugin_before_validate`
- `plugin_after_validate`
- `plugin_before_install`
- `plugin_after_install`
- `plugin_before_enable`
- `plugin_after_enable`
- `plugin_before_disable`
- `plugin_after_disable`
- `plugin_before_uninstall`
- `plugin_after_uninstall`
- `plugin_marketplace_sources`
- `plugin_security_scanners`

نمونه توسعه‌های آینده:

- Marketplace
- License Manager
- Remote Plugin Repository
- Signature Verification
- Plugin Rating
- Plugin Auto Update
- Dependency Resolver
- Plugin Health Monitor

---

## اثر روی دامنه‌های دیگر

Plugins Domain می‌تواند روی تمام دامنه‌ها اثر داشته باشد، اما فقط از مسیر رسمی.

دامنه‌های قابل توسعه:

- Payments
- Notifications
- Reports
- Files
- Backup and Update
- Calendar
- Legal
- Chat
- Settings
- Financial Calculations
- Installments
- Contracts

مثال:

پلاگین SMS نباید مستقیماً جدول Notifications را دستکاری کند.  
باید از Notification Channel Registry استفاده کند.

---

## چک‌لیست پیاده‌سازی

قبل از پیاده‌سازی قابلیت مربوط به Plugins بررسی شود:

- [ ] Core به پلاگین وابسته نیست.
- [ ] پلاگین Manifest معتبر دارد.
- [ ] ZIP پلاگین Validate می‌شود.
- [ ] Path Traversal کنترل شده است.
- [ ] پلاگین قبل از Validate اجرا نمی‌شود.
- [ ] نصب پلاگین Permission دارد.
- [ ] نصب پلاگین حساس Backup اجباری دارد.
- [ ] Settings پلاگین namespace جدا دارد.
- [ ] Permissionهای پلاگین ثبت می‌شوند.
- [ ] Migrationهای پلاگین کنترل می‌شوند.
- [ ] فعال‌سازی و غیرفعال‌سازی Audit Log دارد.
- [ ] خطای پلاگین کل سیستم را خراب نمی‌کند.
- [ ] UI مطابق UI Constitution است.

---

## چک‌لیست بازبینی

قبل از Merge تغییرات این دامنه:

- [ ] مشتری Plugin Manager را نمی‌بیند.
- [ ] operator به Plugin Manager دسترسی ندارد.
- [ ] پلاگین بدون plugin.json نصب نمی‌شود.
- [ ] پلاگین با نسخه ناسازگار نصب نمی‌شود.
- [ ] پلاگین نمی‌تواند Route بدون Auth بسازد.
- [ ] فایل‌های پلاگین مسیر خطرناک ندارند.
- [ ] Migration مخرب رد یا محدود می‌شود.
- [ ] غیرفعال‌سازی پلاگین Core را خراب نمی‌کند.
- [ ] حذف پلاگین Backup دارد.
- [ ] Security Log برای رفتار مشکوک ثبت می‌شود.
- [ ] Responsive بودن Plugin Manager بررسی شده است.

---

## Definition of Done

این دامنه زمانی کامل است که:

- پلاگین قابل Upload، Validate، Install، Enable، Disable، Update و Uninstall باشد.
- plugin.json خوانده و اعتبارسنجی شود.
- پلاگین‌ها از Event، Hook و Registry رسمی استفاده کنند.
- Core به هیچ پلاگین خاصی وابسته نباشد.
- تنظیمات پلاگین در namespace جدا ذخیره شود.
- Permissionهای پلاگین در RBAC ثبت شوند.
- Migrationهای پلاگین کنترل‌شده اجرا شوند.
- عملیات حساس Backup، Audit و Security Log داشته باشد.
- پلاگین خطادار کل سیستم را از کار نیندازد.
- سیستم آماده Marketplace و توسعه تجاری آینده باشد.

---

## قابلیت‌های آینده

در نسخه‌های آینده این دامنه باید آماده موارد زیر باشد:

- Plugin Marketplace
- Remote Plugin Repository
- License-Based Plugins
- Plugin Auto Update
- Plugin Signature Verification
- Plugin Dependency Resolver
- Plugin Health Check
- Plugin Performance Monitor
- Plugin Security Scanner
- Plugin Rollback
- Paid Plugins
- Developer Portal
- Plugin Documentation Generator
- Plugin UI Builder
- Offline Sync Plugin
- AI Plugin SDK
- Accounting Plugin SDK
- Notification Provider SDK
- Payment Gateway SDK

---

## پایان فایل