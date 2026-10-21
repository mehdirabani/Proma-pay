# ممیزی فنی نسخه V1.2.6

تاریخ ممیزی: 2026-07-12

این سند قبل از شروع پیاده‌سازی تهیه شده است. وضعیت‌های نوشته‌شده بر اساس کد موجود در شاخه‌ی مبنا هستند، نه صرفاً مستندات طراحی. مواردی که در این گزارش «موجود نیست» هستند باید با migration، تست و مستندات جداگانه تکمیل شوند.

## 1. وضعیت مبنا

| مورد | وضعیت فعلی |
|---|---|
| شاخه‌ی توسعه | `feature/v1.2.6-plugin-accounting` |
| commit مبنا | `ea0559b` - Release v1.0.25 contract lifecycle corrections |
| نسخه‌ی برنامه | `1.0.25` در `config/settings.php` |
| نسخه‌ی نمایشی | `v1.0.25` در `manifest.json` و helper نسخه |
| نسخه‌ی دیتابیس | نسخه‌ی مرکزی مستقل ندارد؛ جدول `migrations` توسط سرویس بروزرسانی ثبت می‌شود و نسخه‌ی schema به‌صورت فایل‌های تاریخ‌دار پراکنده است |
| نیازمندی PHP | حداقل مستندشده `7.4`، نسخه‌ی توصیه‌شده `8.1` و تست‌شده تا `8.2`؛ پرامپت V1.2.6 حداقل اجرایی `8.1+` می‌خواهد |
| دیتابیس | MySQL 5.7+ یا MariaDB 10.3+ با PDO MySQL |
| plugin capability | پوشه و رجیستری اجرایی افزونه در کد فعلی وجود ندارد؛ مستندات افزونه موجود است |

### نتیجه‌ی ممیزی نسخه

تا پایان همه‌ی migrationها، تست‌های مالی، نصب/فعال‌سازی افزونه و تست نصب تازه، نسخه‌ی برنامه نباید به `1.2.6` تغییر کند. یک منبع مرکزی نسخه باید در فاز اول اضافه شود و مقادیر تکراری فعلی به آن متصل شوند.

## 2. معماری فعلی

### مسیریابی

`index.php` پس از `bootstrap.php` یک `Router` می‌سازد. روتر مقدار `$_GET['route']` را به نام کنترلر و متد تبدیل می‌کند و فایل کنترلر را از `controllers/` بارگذاری می‌کند. این معماری convention-based است و route table، middleware زنجیره‌ای و namespace route برای افزونه‌ها ندارد.

### بارگذاری کلاس‌ها

`bootstrap.php` یک autoloader ساده برای `core/`، `models/`، `helpers/` و `controllers/` ثبت می‌کند. PSR-4 و Composer در مسیر اجرای فعلی استفاده نمی‌شوند. برای افزونه‌ها باید یک loader محدود و اعتبارسنجی‌شده اضافه شود؛ بارگذاری فایل دلخواه از دیتابیس ممنوع است.

### دسترسی و احراز هویت

`core/Auth.php` احراز هویت session-based و نقش‌های `admin`، `operator`، `lawyer` و `customer` را مدیریت می‌کند. کنترل اصلی با `requireRole` و helperهای نقش انجام می‌شود. CSRF برای فرم‌های POST در `Controller::onlyPost()` اعمال می‌شود. Permissionهای نام‌گذاری‌شده و مدیریت تفویض‌شده‌ی permission هنوز یک لایه‌ی کامل و مستقل نیستند و باید برای افزونه‌ها namespace داشته باشند.

### دیتابیس و migration

مدل‌ها از `Model::query/fetch/fetchAll/execute` و PDO prepared statements استفاده می‌کنند. بسته‌ی بروزرسانی migrationهای `database/migrations/*.sql` را در جدول `migrations` ثبت می‌کند. تعدادی از مدل‌های قدیمی برای سازگاری، `ALTER TABLE` را در `ensureSchema()` اجرا می‌کنند؛ این الگو برای migrationهای جدید قابل قبول نیست و باید به migrationهای versioned منتقل شود.

## 3. وضعیت دامنه‌های فعلی

### قراردادها

- `contracts` دارای `active`، `completed`، `cancelled` و وضعیت‌های legacy مانند `closed` است.
- `Contract::syncCompletionStatuses()` قرارداد را پس از تکمیل تعهدات به `completed` می‌برد و قرارداد cancelled را فعال نمی‌کند.
- فهرست قراردادها pagination دارد و روی کارت/ردیف به جز کنترل‌های داخلی به جزئیات لینک می‌شود.
- فروشنده‌ی مستقل برای هر قرارداد وجود ندارد؛ فقط `created_by`/لاگ‌های عمومی و `assigned_operator_id` موجود است.
- حذف دائمی امن برای قراردادهای آزمایشی/اشتباه وجود ندارد؛ لغو قرارداد به‌صورت non-destructive و همراه با اصلاحیه‌ی پرداخت برای همان قرارداد اضافه شده است.

### اقساط

- `installments` وضعیت‌های `pending`، `partial`، `paid`، `overdue` و `cancelled` را در عمل استفاده می‌کند.
- مبلغ پایه، پرداخت‌شده، مانده، جریمه و پاداش نگهداری می‌شوند.
- bulk action و payment group استاندارد در جزئیات قرارداد هنوز وجود ندارد.
- تخصیص خودکار مبلغ اضافه به اقساط بعدی، payment allocation مستقل و idempotent در مدل فعلی وجود ندارد.

### پرداخت‌ها و اصلاحیه

- `payments` برای پرداخت قسط، پیش‌پرداخت، درگاه و کارت‌به‌کارت استفاده می‌شود.
- فقط پرداخت `paid` روی قسط اثر می‌گذارد و correction برای پرداخت موفق وجود دارد.
- `Payment::correctForContract()` دامنه‌ی اصلاحیه را به قرارداد انتخاب‌شده محدود می‌کند و پیش‌پرداخت nullable را پشتیبانی می‌کند.
- گروه پرداخت، allocationهای چندقسطی، ledger حسابداری و جلوگیری کامل از over-allocation هنوز مدل مستقل ندارند.

### قرارداد چاپی و اسناد

- `ContractDocument` تولید و ذخیره‌ی یک سند فعلی برای قرارداد را انجام می‌دهد.
- عنوان، header و body قابل ویرایش هستند و چاپ از route جدا انجام می‌شود.
- جدول تاریخچه‌ی نسخه‌های سند و مفهوم finalized/published version وجود ندارد.
- دسترسی مشتری بر اساس قرارداد خودش در کنترلر بررسی می‌شود، اما جریان خودکار ایجاد سند و انتشار نسخه‌دار باید تکمیل شود.

### حذف و لغو قرارداد

- لغو در `Contract::cancel()` تراکنشی است، اقساط را cancelled می‌کند و اصلاحیه‌ی انتخابی پرداخت‌های همان قرارداد را ثبت می‌کند.
- لغو و حذف دائمی باید دو عملیات جدا باشند.
- archive غیرقابل‌حذف برای snapshot قرارداد و gateway warning برای حذف دائمی در کد فعلی کامل نیست.
- سوابق حقوقی و حسابداری هنوز به lifecycle event استاندارد متصل نیستند.

### آواتار

- catalog شش آواتار local در `helpers/functions.php` وجود دارد و انتخاب دستی/دترمینستیک را پشتیبانی می‌کند.
- پیشنهاد مبتنی بر نام/دسته‌بندی، منبع پیشنهاد، قفل دستی و privacy-preserving AI workflow وجود ندارد.

### مدال و gamification

- جدول legacy `medals`، مدال دستی و بخشی از ارزیابی خودکار در `User`/`Achievement` وجود دارد.
- تعریف مستقل medal، تاریخچه‌ی award/revoke، criteria JSON، مدال‌های تکرارشونده و مدیریت کامل icon/color وجود ندارد.

### حسابداری و attribution فروش

- accounting مستقل وجود ندارد.
- `assigned_operator_id` برای مسئول عملیاتی است و جایگزین seller attribution نیست.
- commission rule، user ledger، expense/bonus/deduction، پرداخت/دریافت کاربر و immutable reversal وجود ندارد.

### audit و لاگ

- `AuditLog::record()` با `audit_logs` برای رویدادهای حساس قرارداد و پرداخت وجود دارد.
- event bus، before/transactional/after-commit event و plugin log/security scan مستقل وجود ندارد.

## 4. شکاف‌های migration

برای رسیدن به V1.2.6 حداقل migrationهای زیر لازم‌اند و نباید در مدل‌ها به‌صورت runtime DDL اجرا شوند:

1. نسخه‌ی مرکزی برنامه، schema و plugin API.
2. رجیستری افزونه، تاریخچه‌ی lifecycle، migration registry، permission و route/hook metadata.
3. event/hook persistence فقط در صورت نیاز به ماندگاری.
4. contract document versions، finalized/published state و generation metadata.
5. deletion archive و deletion operation history برای قراردادهای قابل حذف.
6. payment groups و payment allocations با unique/idempotency key و محدودیت scope قرارداد.
7. bulk installment operation history.
8. جدول‌های مستقل accounting فقط داخل `plugins/PromaAccounting/migrations/`.
9. medal definitions، user medals و history با حفظ داده‌های legacy.
10. فیلدهای avatar suggestion/source/locked در صورت نبودن در schema.

هر migration باید idempotent، قابل ثبت در جدول migration، دارای rollback توضیحی و قابل اجرای جداگانه روی MySQL/MariaDB باشد.

## 5. فایل‌های اصلی درگیر

### هسته و زیرساخت

- `bootstrap.php`
- `core/Router.php`
- `core/Controller.php`
- `core/Model.php`
- `core/Auth.php`
- `helpers/functions.php`
- `helpers/ScriptUpdateService.php`
- `config/settings.php`
- `manifest.json`
- `service-worker.js`

### قرارداد، پرداخت و سند

- `models/Contract.php`
- `controllers/ContractsController.php`
- `models/Installment.php`
- `models/Payment.php`
- `helpers/FinanceHelper.php`
- `helpers/ContractFinancialSummaryService.php`
- `models/ContractDocument.php`
- `views/contracts/index.php`
- `views/contracts/show.php`
- `views/contracts/print.php`

### کاربر، مدال و آواتار

- `models/User.php`
- `models/Achievement.php`
- `controllers/UsersController.php`
- `views/users/`
- `views/customers/`
- `assets/images/avatars/`

### migration و release

- `database/proma-pay-install.sql`
- `database/migrations/`
- `install.php`
- `installer.php`
- `CHANGELOG.md`
- `docs/releases/`

## 6. ریشه‌یابی و ریسک

| موضوع | ریشه | سطح ریسک |
|---|---|---|
| نبود افزونه‌ی امن | روتر و autoloader فقط core folders را می‌شناسند | بحرانی |
| حسابداری در هسته | service/event boundary وجود ندارد | بحرانی |
| اختلاف نسخه | version در settings/package/manifest/service worker تکرار شده | زیاد |
| DDL در مدل | `ensureSchema()` برای سازگاری گذشته | زیاد |
| حذف قرارداد | archive و policy مستقل وجود ندارد | بحرانی |
| اسناد | یک رکورد current به‌جای versioned document | زیاد |
| پرداخت چندقسطی | payment فقط به یک installment متصل است | بحرانی |
| overpayment | allocation مستقل و invariant ندارد | بحرانی |
| مدال | legacy row-centric مدل شده، definition/history ندارد | متوسط |
| آواتار هوشمند | فقط انتخاب local و deterministic موجود است | متوسط |
| performance | برخی queryهای لیستی و مدل‌های legacy بدون service/repository تخصصی هستند | متوسط |

## 7. ترتیب اجرای پیشنهادی

1. **فاز صفر:** همین audit، تعیین branch، قرارداد API افزونه و چک‌لیست release.
2. **فاز یک:** version source، plugin registry، manifest validator، secure ZIP extraction، lifecycle service، permission و hook/event bus.
3. **فاز دو:** scaffold و migrationهای `plugins/PromaAccounting/`، attribution فروش، commission و ledger با UI محدود به active plugin.
4. **فاز سه:** deletion preview/archive، حذف دائمی کنترل‌شده، document generation/versioning و customer access.
5. **فاز چهار:** payment groups، allocations، overpayment و idempotent gateway callback.
6. **فاز پنج:** bulk installment actions با transition policy، permission، transaction و audit.
7. **فاز شش:** avatar suggestion privacy-safe و medal definition/history/management.
8. **فاز هفت:** تست نصب تازه، upgrade، migration، امنیت ZIP، permission، مالی، backup/restore، مستندات و سپس bump نسخه به `1.2.6`.

## 8. معیار توقف

تا زمانی که plugin install/activate/deactivate، accounting isolation، اصلاحیه‌ی scoped، document versioning، payment allocation و تست‌های acceptance موفق نشده‌اند، release نهایی V1.2.6 اعلام نمی‌شود. در هر مرحله اگر تست بحرانی شکست بخورد، نسخه‌ی برنامه در مقدار پایدار قبلی باقی می‌ماند.
