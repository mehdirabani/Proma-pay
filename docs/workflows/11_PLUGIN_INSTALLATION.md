````markdown
# 11 — Plugin Installation Workflow

مستند Workflow نصب، اعتبارسنجی، فعال‌سازی، غیرفعال‌سازی، آپدیت و حذف پلاگین در پروژه **Proma Pay**

---

## هدف Workflow

هدف این Workflow این است که سیستم بتواند پلاگین‌ها را به شکل امن، قابل کنترل، قابل توسعه و بدون آسیب به Core نصب و مدیریت کند.

پلاگین‌ها باید بتوانند قابلیت‌های جدید مثل درگاه پرداخت، پیامک، گزارش پیشرفته، بکاپ ابری، اتصال به حسابداری، OCR، هوش مصنوعی، کانال اعلان، Offline Sync یا ابزارهای حقوقی را به سیستم اضافه کنند؛ اما نباید باعث شوند Core سیستم به آن‌ها وابسته شود.

اصل مهم:

> Core باید بدون هر پلاگین اختیاری همچنان سالم اجرا شود.

---

## بازیگران

| بازیگر | نقش |
|---|---|
| Super Admin | نصب، فعال‌سازی، آپدیت و حذف پلاگین |
| Admin | مشاهده پلاگین‌ها در صورت Permission |
| Plugin Manager | مدیریت چرخه عمر پلاگین |
| Backup Service | ساخت بکاپ قبل از نصب یا آپدیت |
| Update Service | مدیریت آپدیت پلاگین |
| Security System | اعتبارسنجی امنیتی پلاگین |
| Files System | ذخیره موقت و امن ZIP پلاگین |
| Notification System | ارسال نتیجه عملیات |
| System | اجرای Migration، Hook و Eventهای پلاگین |

---

## Domainهای درگیر

این Workflow به Domainهای زیر وابسته است:

- Plugins
- Backup & Update
- Files
- Settings
- Users & Roles
- Notifications
- Reports
- Logging & Audit
- Security
- Calendar در صورت نیاز
- Payments در پلاگین‌های پرداخت
- Notifications در پلاگین‌های پیام‌رسانی
- Reports در پلاگین‌های گزارش‌گیری

---

## پیش‌نیازها

قبل از اجرای این Workflow باید موارد زیر آماده باشند:

- Plugin Manager فعال باشد.
- مسیر پلاگین‌ها مشخص باشد.
- Private Storage برای آپلود ZIP آماده باشد.
- Permissionهای مدیریت پلاگین تعریف شده باشند.
- Backup Service فعال باشد.
- Migration Runner آماده باشد.
- Hook و Event Registry آماده باشد.
- Settings Registry آماده باشد.
- Permission Registry آماده باشد.
- CSRF فعال باشد.
- Security Log و Audit Log فعال باشند.
- ZipArchive یا fallback مناسب مدیریت شود.

---

## ورودی‌ها

ورودی‌های آپلود پلاگین:

- plugin_zip_file
- install_after_upload در صورت نیاز
- enable_after_install در صورت نیاز

ورودی‌های نصب پلاگین:

- uploaded_plugin_id
- confirmation_text در صورت نیاز
- create_backup_before_install
- run_migrations

ورودی‌های فعال‌سازی:

- plugin_id
- confirmation در صورت نیاز

ورودی‌های غیرفعال‌سازی:

- plugin_id
- reason در صورت نیاز

ورودی‌های حذف:

- plugin_id
- uninstall_type
- confirmation_text
- keep_data یا delete_data

ورودی‌های آپدیت:

- plugin_id
- update_package_file
- create_backup_before_update
- run_migrations

---

## خروجی‌ها

خروجی‌های این Workflow:

- ثبت پلاگین در جدول plugins
- ثبت نسخه پلاگین
- ثبت تنظیمات پیش‌فرض پلاگین
- ثبت Permissionهای پلاگین
- اجرای Migrationهای پلاگین
- فعال یا غیرفعال شدن پلاگین
- ثبت Hookها و Event Listenerها
- ثبت Assetهای پلاگین
- ایجاد Backup قبل از عملیات حساس
- ثبت Audit Log و Security Log
- ارسال Notification نتیجه عملیات
- نمایش وضعیت پلاگین در Plugin Manager

---

## تعریف پلاگین

پلاگین یک بسته قابل نصب است که قابلیت جدیدی به Proma Pay اضافه می‌کند، بدون اینکه فایل‌های Core را مستقیماً تغییر دهد.

هر پلاگین باید:

- شناسه یکتا داشته باشد.
- نسخه داشته باشد.
- فایل `plugin.json` داشته باشد.
- نوع پلاگین را مشخص کند.
- حداقل نسخه Proma Pay را اعلام کند.
- حداقل نسخه PHP را اعلام کند.
- Permissionهای مورد نیاز را اعلام کند.
- Settings خود را اعلام کند.
- Migrationهای خود را اعلام کند.
- Hookها و Eventهای خود را اعلام کند.

---

## وضعیت‌های پلاگین

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

## ساختار استاندارد پلاگین

ساختار پیشنهادی:

```text
plugin-example.zip
└── plugin-example/
    ├── plugin.json
    ├── src/
    ├── migrations/
    ├── views/
    ├── assets/
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
  "migrations": [
    "2026_01_01_000001_create_sms_logs_table"
  ]
}
```

---

## مراحل اصلی Workflow

نمای کلی مراحل:

1. Super Admin فایل ZIP پلاگین را آپلود می‌کند.
2. فایل در Private Storage موقت ذخیره می‌شود.
3. ZIP اعتبارسنجی می‌شود.
4. `plugin.json` خوانده و Validate می‌شود.
5. سازگاری نسخه بررسی می‌شود.
6. خطرات امنیتی بررسی می‌شود.
7. اگر پلاگین نیاز به Backup دارد، بکاپ ساخته می‌شود.
8. فایل‌های پلاگین در مسیر مجاز نصب می‌شوند.
9. Permissionهای پلاگین ثبت می‌شوند.
10. Settings پیش‌فرض پلاگین ثبت می‌شوند.
11. Migrationهای پلاگین اجرا می‌شوند.
12. پلاگین نصب می‌شود.
13. در صورت درخواست، پلاگین فعال می‌شود.
14. Hookها و Event Listenerها ثبت می‌شوند.
15. Notification و Audit Log ثبت می‌شود.

---

## آپلود پلاگین

### مراحل

1. کاربر وارد Plugin Manager می‌شود.
2. گزینه «آپلود پلاگین» را انتخاب می‌کند.
3. فایل ZIP را انتخاب می‌کند.
4. CSRF بررسی می‌شود.
5. Permission بررسی می‌شود.
6. حجم فایل بررسی می‌شود.
7. پسوند و MIME Type بررسی می‌شود.
8. فایل در Private Storage موقت ذخیره می‌شود.
9. رکورد اولیه پلاگین با وضعیت `uploaded` ساخته می‌شود.
10. مرحله اعتبارسنجی شروع می‌شود.

### قوانین

- فقط ZIP معتبر پذیرفته شود.
- فایل نباید در Public Storage ذخیره شود.
- فایل آپلودی نباید مستقیم اجرا شود.
- نام فایل نباید منبع اعتماد باشد.
- Upload باید Log داشته باشد.

---

## اعتبارسنجی پلاگین

### موارد بررسی

- وجود `plugin.json`
- معتبر بودن JSON
- وجود `id`
- یکتا بودن `id`
- معتبر بودن `version`
- سازگاری `min_proma_version`
- سازگاری `min_php`
- معتبر بودن `type`
- امن بودن مسیرهای ZIP
- نبود فایل‌های ممنوع
- نبود Path Traversal
- نبود فایل‌های مخرب
- معتبر بودن Permissionها
- معتبر بودن Settings
- معتبر بودن Migrationها

### مسیرهای ممنوع

```text
../
/absolute/path
.env
config.php
storage/private
backups
core بدون اجازه رسمی
```

### فایل‌های خطرناک

```text
.exe
.bat
.sh
.phar
.htaccess خطرناک
فایل‌های PHP خارج از مسیر مجاز
```

---

## نصب پلاگین

### مراحل

1. پلاگین باید وضعیت `validated` داشته باشد.
2. کاربر دکمه نصب را می‌زند.
3. Permission بررسی می‌شود.
4. اگر `requires_backup` فعال است، بکاپ ساخته می‌شود.
5. فایل‌ها در مسیر staging استخراج می‌شوند.
6. فایل‌ها به مسیر plugins منتقل می‌شوند.
7. رکورد پلاگین در جدول plugins ثبت یا بروزرسانی می‌شود.
8. نسخه پلاگین در plugin_versions ثبت می‌شود.
9. Permissionهای پلاگین ثبت می‌شوند.
10. Settings پیش‌فرض ثبت می‌شوند.
11. Migrationهای پلاگین اجرا می‌شوند.
12. وضعیت پلاگین `installed` می‌شود.
13. Notification و Audit Log ثبت می‌شود.

### قوانین

- نصب پلاگین باید داخل فرآیند کنترل‌شده انجام شود.
- پلاگین قبل از نصب نباید Boot شود.
- اگر Migration شکست خورد، نصب باید failed شود.
- اگر نصب شکست خورد، فایل‌های نیمه‌کاره باید حذف یا Rollback شوند.
- پلاگین نصب‌شده الزاماً نباید خودکار فعال شود، مگر تنظیمات اجازه دهد.

---

## فعال‌سازی پلاگین

فعال‌سازی یعنی پلاگین وارد چرخه اجرای سیستم شود.

### مراحل

1. کاربر دکمه فعال‌سازی را می‌زند.
2. Permission بررسی می‌شود.
3. وضعیت پلاگین بررسی می‌شود.
4. وابستگی‌ها بررسی می‌شوند.
5. Settings ضروری بررسی می‌شوند.
6. پلاگین Boot تستی می‌شود.
7. Hookها ثبت می‌شوند.
8. Event Listenerها ثبت می‌شوند.
9. Routeهای پلاگین فعال می‌شوند.
10. وضعیت پلاگین `enabled` می‌شود.
11. Cache پلاگین‌ها پاک می‌شود.
12. Notification و Audit Log ثبت می‌شود.

### قوانین

- پلاگین باید installed باشد.
- پلاگین failed نباید فعال شود.
- اگر Settings ضروری ناقص باشد، فعال‌سازی ممنوع است.
- اگر Boot خطا داد، پلاگین باید disabled یا failed شود.
- خطای پلاگین نباید کل سیستم را از کار بیندازد.

---

## غیرفعال‌سازی پلاگین

غیرفعال‌سازی یعنی پلاگین دیگر اجرا نشود، اما داده‌های آن باقی بماند.

### مراحل

1. کاربر دکمه غیرفعال‌سازی را می‌زند.
2. Permission بررسی می‌شود.
3. هشدار نمایش داده می‌شود.
4. پلاگین از Registry فعال حذف می‌شود.
5. Hookها غیرفعال می‌شوند.
6. Event Listenerها غیرفعال می‌شوند.
7. Routeهای پلاگین غیرفعال می‌شوند.
8. وضعیت پلاگین `disabled` می‌شود.
9. Cache پاک می‌شود.
10. Audit Log ثبت می‌شود.

### قوانین

- غیرفعال‌سازی نباید داده‌های پلاگین را حذف کند.
- اگر پلاگین در قابلیت فعال استفاده می‌شود، هشدار نمایش داده شود.
- غیرفعال‌سازی پلاگین سیستمی ممنوع یا نیازمند Permission ویژه است.
- Core نباید بعد از غیرفعال‌سازی پلاگین خراب شود.

---

## حذف پلاگین

حذف پلاگین دو نوع دارد:

### حذف نرم

در حذف نرم:

- پلاگین disabled می‌شود.
- فایل‌های پلاگین حذف یا آرشیو می‌شوند.
- داده‌های پلاگین باقی می‌مانند.
- Settings پلاگین باقی می‌ماند.
- امکان نصب مجدد وجود دارد.

### حذف کامل

در حذف کامل:

- پلاگین disabled می‌شود.
- فایل‌های پلاگین حذف می‌شوند.
- Settings پلاگین حذف یا آرشیو می‌شود.
- داده‌های پلاگین طبق Policy حذف می‌شود.
- Migration برگشتی در صورت وجود اجرا می‌شود.

### قوانین

- حذف کامل باید تأیید متنی داشته باشد.
- قبل از حذف کامل باید Backup گرفته شود.
- حذف پلاگین باید Audit Log داشته باشد.
- حذف پلاگین نباید Core را خراب کند.
- حذف داده‌های پلاگین باید قابل کنترل باشد.

---

## تأیید متنی حذف کامل

برای حذف کامل، کاربر باید عبارت زیر را وارد کند:

```text
حذف کامل پلاگین را تایید می‌کنم
```

بدون این عبارت، حذف کامل اجرا نشود.

---

## آپدیت پلاگین

### مراحل

1. کاربر پکیج آپدیت پلاگین را آپلود می‌کند.
2. پکیج Validate می‌شود.
3. plugin_id با پلاگین نصب‌شده مقایسه می‌شود.
4. نسخه جدید بررسی می‌شود.
5. سازگاری نسخه Proma Pay بررسی می‌شود.
6. Backup قبل از آپدیت ساخته می‌شود.
7. پلاگین موقتاً وارد وضعیت `updating` می‌شود.
8. فایل‌های جدید در staging استخراج می‌شوند.
9. فایل‌ها جایگزین می‌شوند.
10. Migrationهای جدید اجرا می‌شوند.
11. نسخه پلاگین بروزرسانی می‌شود.
12. Cache پاک می‌شود.
13. پلاگین به وضعیت قبلی یا enabled برمی‌گردد.
14. Notification و Audit Log ثبت می‌شود.

### قوانین

- نسخه جدید باید از نسخه فعلی بالاتر باشد.
- Update نباید plugin_id را تغییر دهد.
- قبل از آپدیت باید Backup گرفته شود.
- اگر آپدیت شکست خورد، Rollback یا وضعیت failed ثبت شود.
- Changelog باید در UI نمایش داده شود.

---

## Migrationهای پلاگین

Migrationهای پلاگین باید کنترل‌شده باشند.

### قوانین

- Migration باید Idempotent باشد.
- Migration اجراشده دوباره اجرا نشود.
- Migration باید در plugin_migrations ثبت شود.
- جدول‌های پلاگین باید Prefix داشته باشند.
- پلاگین نباید جدول‌های Core را بدون Contract رسمی تغییر دهد.
- Migration مخرب باید ممنوع یا نیازمند تأیید ویژه باشد.

الگوی نام جدول:

```text
plugin_{plugin_id}_{table_name}
```

نمونه:

```text
plugin_sms_provider_logs
```

---

## Permissionهای پلاگین

پلاگین می‌تواند Permission اختصاصی ثبت کند.

الگو:

```text
plugin.{plugin_id}.{action}
```

نمونه:

```text
plugin.sms_provider.manage
plugin.sms_provider.view_logs
plugin.ai_assistant.use
```

قوانین:

- Permissionها باید در RBAC ثبت شوند.
- حذف پلاگین نباید Roleهای سیستم را خراب کند.
- Permission پلاگین باید در صفحه نقش‌ها قابل مشاهده باشد.
- Permission حذف‌شده باید از Roleها پاکسازی شود.

---

## Settings پلاگین

تنظیمات پلاگین باید namespace جدا داشته باشند.

الگو:

```text
plugin.{plugin_id}.{setting_key}
```

نمونه:

```text
plugin.sms_provider.api_key
plugin.sms_provider.sender_number
```

قوانین:

- تنظیمات حساس باید encrypted باشند.
- مقدار حساس در UI باید Mask شود.
- تغییر Settings پلاگین باید Audit Log داشته باشد.
- پلاگین disabled نباید Settings خود را از دست بدهد.
- حذف کامل می‌تواند Settings را طبق Policy حذف کند.

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
/plugins/ai-assistant/dashboard
```

قوانین:

- Routeهای پلاگین باید از Auth Middleware عبور کنند.
- CSRF و Permission باید رعایت شود.
- پلاگین نباید Routeهای Core را Override کند.
- Routeهای Admin و Customer باید جدا باشند.

---

## Hookها و Eventها

پلاگین‌ها باید فقط از Hookها و Eventهای رسمی استفاده کنند.

نمونه Hookها:

- `payment_methods_registry`
- `notification_channels_registry`
- `report_widgets`
- `settings_tabs`
- `backup_storage_drivers`
- `calendar_event_types`

نمونه Eventها:

- `PaymentApproved`
- `InstallmentOverdue`
- `ContractSettled`
- `BackupCompleted`
- `LegalCaseCreated`

قوانین:

- خطای Hook پلاگین نباید کل سیستم را خراب کند.
- Listenerهای پلاگین باید Try/Catch و Log داشته باشند.
- پلاگین disabled نباید Hook اجرا کند.
- Hookهای حساس باید محدود و کنترل‌شده باشند.

---

## Assetهای پلاگین

پلاگین می‌تواند CSS، JS و تصویر داشته باشد.

قوانین:

- Assetهای پلاگین باید فقط در صفحات مرتبط Load شوند.
- JS پلاگین نباید اطلاعات حساس را افشا کند.
- Assetها نباید به Private Storage مستقیم دسترسی داشته باشند.
- حذف یا غیرفعال‌سازی پلاگین باید Assetهای آن را غیرفعال کند.
- Assetهای پلاگین نباید UI اصلی را خراب کنند.

---

## Backup قبل از عملیات حساس

در این موارد Backup الزامی است:

- نصب پلاگین حساس
- آپدیت پلاگین
- حذف کامل پلاگین
- اجرای Migration حساس
- نصب پلاگین پرداخت
- نصب پلاگین ذخیره‌سازی فایل
- نصب پلاگین مالی یا حقوقی

قانون:

اگر Backup شکست خورد، عملیات حساس نباید ادامه پیدا کند.

---

## Rollback پلاگین

Rollback باید در صورت شکست نصب یا آپدیت تلاش شود.

### موارد Rollback

- حذف فایل‌های نیمه‌کاره
- بازگرداندن فایل‌های نسخه قبلی
- بازگرداندن دیتابیس از بکاپ در صورت نیاز
- تغییر وضعیت پلاگین به نسخه قبلی
- غیرفعال کردن پلاگین خطادار
- ثبت Log و Notification

### قوانین

- Rollback باید Log داشته باشد.
- Rollback ناقص باید به Super Admin اطلاع دهد.
- اگر پلاگین باعث خطای عمومی شد، Force Disable باید ممکن باشد.
- Core باید بتواند با پلاگین disabled اجرا شود.

---

## Force Disable

Force Disable برای مواقعی است که پلاگین باعث خطای جدی می‌شود.

قوانین:

- فقط Super Admin بتواند Force Disable کند.
- Force Disable باید حتی اگر Boot پلاگین خطا می‌دهد قابل انجام باشد.
- وضعیت پلاگین `blocked` یا `disabled` شود.
- Security یا Error Log ثبت شود.
- Notification برای Super Admin ایجاد شود.

---

## Validationها

### Upload

- فایل الزامی است.
- فایل باید ZIP معتبر باشد.
- حجم فایل از حد مجاز بیشتر نباشد.
- فایل در Private Storage ذخیره شود.
- فایل مستقیم اجرا نشود.

### plugin.json

- id الزامی و یکتا باشد.
- version معتبر باشد.
- type معتبر باشد.
- min_proma_version سازگار باشد.
- min_php سازگار باشد.
- permissions معتبر باشند.
- settings معتبر باشند.
- migrations معتبر باشند.

### Security

- مسیرهای خطرناک رد شوند.
- فایل‌های ممنوع رد شوند.
- Path Traversal رد شود.
- فایل PHP خارج از مسیر مجاز رد شود.
- Route بدون Auth رد شود.
- Permission بدون Prefix درست رد شود.

---

## Eventها

Eventهای اصلی این Workflow:

- `PluginUploaded`
- `PluginValidationStarted`
- `PluginValidated`
- `PluginValidationFailed`
- `PluginInstallStarted`
- `PluginInstalled`
- `PluginInstallFailed`
- `PluginEnabled`
- `PluginDisabled`
- `PluginUpdateStarted`
- `PluginUpdated`
- `PluginUpdateFailed`
- `PluginUninstallStarted`
- `PluginUninstalled`
- `PluginDeleted`
- `PluginMigrationExecuted`
- `PluginMigrationFailed`
- `PluginForceDisabled`
- `PluginSecurityViolationDetected`

---

## Notificationها

### PluginInstalled

گیرنده:

- Super Admin

پیام پیشنهادی:

```text
پلاگین با موفقیت نصب شد.
```

---

### PluginEnabled

گیرنده:

- Super Admin

پیام پیشنهادی:

```text
پلاگین فعال شد.
```

---

### PluginInstallFailed

گیرنده:

- Super Admin

پیام پیشنهادی:

```text
نصب پلاگین با خطا مواجه شد.
```

---

### PluginSecurityViolationDetected

گیرنده:

- Super Admin

پیام پیشنهادی:

```text
رفتار مشکوک در پکیج پلاگین شناسایی شد.
```

---

## Timeline

Timelineهای پیشنهادی:

- آپلود پلاگین
- اعتبارسنجی پلاگین
- نصب پلاگین
- اجرای Migration پلاگین
- فعال‌سازی پلاگین
- غیرفعال‌سازی پلاگین
- آپدیت پلاگین
- حذف پلاگین
- Force Disable پلاگین
- خطای امنیتی پلاگین

قانون:

Timelineهای پلاگین فقط برای کاربران داخلی سطح بالا نمایش داده شوند.

---

## Logging و Audit

### Log عادی

موارد زیر Log عادی دارند:

- Upload پلاگین
- Validate پلاگین
- Install پلاگین
- Enable پلاگین
- Disable پلاگین
- اجرای Hook
- اجرای Migration
- ارسال Notification

### Audit Log

موارد زیر Audit Log دارند:

- نصب پلاگین
- فعال‌سازی پلاگین
- غیرفعال‌سازی پلاگین
- آپدیت پلاگین
- حذف پلاگین
- تغییر Settings پلاگین
- تغییر Permissionهای پلاگین
- Force Disable
- اجرای Migration حساس

### Security Log

موارد زیر Security Log دارند:

- Upload فایل مشکوک
- Path Traversal
- plugin.json نامعتبر
- تلاش نصب بدون Permission
- تلاش فعال‌سازی پلاگین خطرناک
- تلاش Route بدون Auth
- فایل PHP خارج از مسیر مجاز
- CSRF نامعتبر

---

## خطاهای احتمالی

| خطا | رفتار مناسب |
|---|---|
| فایل ZIP نامعتبر است | رد Upload و نمایش خطا |
| plugin.json وجود ندارد | رد پلاگین |
| plugin_id تکراری است | نمایش خطا یا پیشنهاد آپدیت |
| نسخه Proma Pay ناسازگار است | رد نصب |
| نسخه PHP ناسازگار است | رد نصب |
| مسیر خطرناک وجود دارد | Security Log و رد پلاگین |
| Backup شکست خورد | توقف عملیات |
| Migration شکست خورد | نصب failed و Rollback در صورت امکان |
| Boot پلاگین خطا داد | پلاگین disabled یا failed شود |
| حذف کامل شکست خورد | وضعیت مشخص و Log ثبت شود |

---

## قوانین UI

### صفحه Plugin Manager

باید شامل موارد زیر باشد:

- لیست پلاگین‌ها
- نام پلاگین
- نوع پلاگین
- نسخه
- سازنده
- وضعیت
- دکمه نصب
- دکمه فعال‌سازی
- دکمه غیرفعال‌سازی
- دکمه آپدیت
- دکمه حذف
- دکمه مشاهده Log
- دکمه تنظیمات پلاگین

---

## صفحه جزئیات پلاگین

باید نمایش دهد:

- نام
- توضیح
- نسخه
- نوع
- سازنده
- وضعیت
- حداقل نسخه Proma Pay
- حداقل نسخه PHP
- Permissionهای پلاگین
- Settings پلاگین
- Migrationهای اجراشده
- Changelog
- Logهای اخیر
- هشدارهای امنیتی

---

## Modal نصب پلاگین

باید شامل موارد زیر باشد:

- خلاصه اطلاعات پلاگین
- وضعیت اعتبارسنجی
- Permissionهای درخواستی
- Settings ضروری
- هشدار نیاز به Backup
- Changelog در صورت وجود
- دکمه نصب
- دکمه لغو

---

## قوانین امنیتی

موارد الزامی:

- CSRF برای همه عملیات
- Permission سمت سرور
- ذخیره ZIP در Private Storage
- عدم اجرای پلاگین قبل از Validate
- جلوگیری از Path Traversal
- بررسی plugin.json
- بررسی نسخه سیستم و PHP
- Backup قبل از عملیات حساس
- Audit Log برای عملیات حساس
- Security Log برای موارد مشکوک
- Force Disable برای پلاگین خطادار
- عدم وابستگی Core به پلاگین

---

## تست‌های ضروری

### Upload و Validate

- فایل ZIP معتبر آپلود شود.
- plugin.json خوانده شود.
- وضعیت پلاگین validated شود.
- فایل با مسیر خطرناک رد شود.
- پلاگین بدون plugin.json رد شود.

### نصب موفق

- پلاگین Validate شده نصب شود.
- Backup قبل از نصب ساخته شود.
- Permissionها ثبت شوند.
- Settings ثبت شوند.
- Migration اجرا شود.
- وضعیت installed شود.

### فعال‌سازی

- پلاگین installed فعال شود.
- Hookها و Eventها ثبت شوند.
- وضعیت enabled شود.
- خطای Boot باعث failed شدن شود.

### غیرفعال‌سازی

- پلاگین enabled غیرفعال شود.
- Hookها اجرا نشوند.
- داده‌ها باقی بمانند.
- Core سالم بماند.

### آپدیت

- نسخه جدید نصب شود.
- Backup ساخته شود.
- Migration جدید اجرا شود.
- نسخه پلاگین تغییر کند.
- در خطا Rollback یا failed ثبت شود.

### حذف

- حذف نرم داده‌ها را نگه دارد.
- حذف کامل تأیید متنی بخواهد.
- قبل از حذف کامل Backup ساخته شود.
- Core بعد از حذف سالم باشد.

### تست‌های امنیتی

- کاربر بدون Permission نتواند نصب کند.
- پلاگین با Path Traversal رد شود.
- Route بدون Auth رد شود.
- فایل اجرایی غیرمجاز رد شود.
- CSRF نامعتبر رد شود.
- مشتری Plugin Manager را نبیند.

---

## چک‌لیست پیاده‌سازی

قبل از پیاده‌سازی این Workflow بررسی شود:

- [ ] Plugin Manager آماده است.
- [ ] plugin.json Validate می‌شود.
- [ ] ZIP پلاگین در Private Storage ذخیره می‌شود.
- [ ] Path Traversal کنترل شده است.
- [ ] نسخه Proma Pay و PHP بررسی می‌شود.
- [ ] Backup قبل از نصب، آپدیت و حذف کامل ساخته می‌شود.
- [ ] Permissionهای پلاگین در RBAC ثبت می‌شوند.
- [ ] Settings پلاگین namespace جدا دارد.
- [ ] Migrationهای پلاگین کنترل‌شده اجرا می‌شوند.
- [ ] Hook و Event Registry آماده است.
- [ ] Force Disable وجود دارد.
- [ ] Log، Audit و Security Log ثبت می‌شوند.
- [ ] UI مطابق UI Constitution است.

---

## Definition of Done

این Workflow زمانی کامل است که:

- Super Admin بتواند پلاگین ZIP را آپلود کند.
- پلاگین قبل از نصب کامل Validate شود.
- پلاگین بدون plugin.json نصب نشود.
- پلاگین ناسازگار نصب نشود.
- نصب پلاگین Permission، Backup و Audit داشته باشد.
- پلاگین بتواند Settings، Permission، Migration، Hook و Event ثبت کند.
- پلاگین قابل فعال‌سازی و غیرفعال‌سازی باشد.
- خطای پلاگین Core را از کار نیندازد.
- آپدیت پلاگین امن و قابل Rollback باشد.
- حذف نرم و حذف کامل پشتیبانی شود.
- Force Disable برای پلاگین خطادار وجود داشته باشد.
- مشتری هیچ دسترسی به Plugin Manager نداشته باشد.
- UI مدیریت پلاگین RTL، امن، خوانا و Responsive باشد.

---

## پایان فایل
````
