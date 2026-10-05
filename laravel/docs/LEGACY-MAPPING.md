# نگاشت نسخهٔ قدیمی

## دامنهٔ بررسی

فایل نصب `../database/proma-pay-install.sql` شامل ۷۲ دستور ایجاد جدول بود؛ فایل‌های اصلی در `../controllers/`, `../models/`, `../helpers/` و `../core/` هستند. نمونه‌های شاخص: `controllers/AuthController.php`, `CustomersController.php`, `ContractsController.php`, `InstallmentsController.php`, `PaymentsController.php`, `OverdueController.php`, `LegalController.php`, `LawyerController.php`, `EcommerceController.php`, `ImportsController.php`, `UsersController.php`; مدل‌های `models/User.php`, `Contract.php`, `Installment.php`, `Payment.php`, `PaymentRequest.php`, `PaymentReceipt.php`, `IdentityDocument.php`, `LegalCase.php`, `OperatorCall.php`; و قواعد مالی در `helpers/MoneyMath.php`, `FinanceHelper.php`, `PaymentAllocationService.php`, `InstallmentSettlementService.php`, `ZibalClient.php` و `ZibalGatewayProvider.php`.

این فهرست آغاز کشف است، نه فهرست کامل قابلیت‌ها. شمارش schema به جداول تعریف‌شده در SQL نصب محدود است و ممکن است migrations/runtime/pluginها جداول دیگری بسازند. پیش از انتقال باید schema واقعی هر محیط، نسخهٔ برنامه، داده‌ها، تنظیمات و extensionهای نصب‌شده inventory شوند.

## وضعیت و بلوغ

برای هر جدول، `REDESIGNED` یعنی مقصد مفهومی در دامنهٔ Laravel تعیین شده است. ستون بلوغ صادقانه نشان می‌دهد کد مقصد در این برش وجود ندارد یا فقط foundation مشترک موجود است. هیچ داده‌ای منتقل نشده و هیچ ردیفی نباید به علت این فهرست حذف یا بی‌صدا کنار گذاشته شود. `MIGRATED` عمداً برای هیچ جدول استفاده نشده است. تعیین وضعیت حذف/عدم کاربرد نیازمند تأیید محصول و بررسی دادهٔ واقعی است؛ ازاین‌رو در این مرحله موردی با `NOT_APPLICABLE` علامت نخورده است.

## نگاشت جداول

| جدول Legacy | دامنهٔ مقصد | وضعیت | بلوغ مقصد فعلی |
|---|---|---|---|
| `ai_action_logs` | عملیات هوشمند و audit | `REDESIGNED` | برنامه فقط |
| `audit_logs` | audit و تاریخچهٔ تغییر | `REDESIGNED` | audit پایه، فاقد هم‌ارزی داده |
| `chat_attachments` | گفتگو و فایل | `REDESIGNED` | برنامه فقط |
| `chat_channels` | گفتگو | `REDESIGNED` | برنامه فقط |
| `chat_channel_reads` | وضعیت خواندن پیام | `REDESIGNED` | برنامه فقط |
| `contract_change_logs` | نسخه و تغییر قرارداد | `REDESIGNED` | برنامه فقط |
| `contract_guarantees` | تضمین قرارداد | `REDESIGNED` | برنامه فقط |
| `contract_guarantor_people` | اشخاص ضامن | `REDESIGNED` | برنامه فقط |
| `contract_guarantors` | ارتباط ضامن و قرارداد | `REDESIGNED` | برنامه فقط |
| `contract_items` | اقلام قرارداد | `REDESIGNED` | برنامه فقط |
| `contracts` | قرارداد اعتباری | `REDESIGNED` | schema مقصد اولیه، فاقد workflow |
| `customer_merge_logs` | ادغام هویت مشتری | `REDESIGNED` | برنامه فقط |
| `events` | رویدادهای دامنه | `REDESIGNED` | برنامه فقط |
| `generated_contract_documents` | سند تولیدشدهٔ قرارداد | `REDESIGNED` | برنامه فقط |
| `identity_documents` | مدارک احراز هویت | `REDESIGNED` | KYC metadata پایه، فایل/انتقال ندارد |
| `file_audit_logs` | audit فایل | `REDESIGNED` | برنامه فقط |
| `file_relations` | ارتباط فایل و رکورد دامنه | `REDESIGNED` | برنامه فقط |
| `files` | metadata و storage فایل | `REDESIGNED` | برنامه فقط |
| `import_batches` | batch ورود داده | `REDESIGNED` | برنامهٔ انتقال فقط |
| `import_rows` | ردیف‌های import و خطا | `REDESIGNED` | برنامهٔ انتقال فقط |
| `installments` | اقساط قرارداد | `REDESIGNED` | schema مقصد اولیه، فاقد محاسبه/تسویه |
| `legal_case_logs` | تاریخچهٔ پروندهٔ حقوقی | `REDESIGNED` | برنامه فقط |
| `legal_cases` | پروندهٔ حقوقی | `REDESIGNED` | برنامه فقط |
| `medals` | نشان‌ها و امتیاز | `REDESIGNED` | برنامه فقط |
| `messages` | پیام و گفتگو | `REDESIGNED` | برنامه فقط |
| `notifications` | اعلان‌ها | `REDESIGNED` | برنامه فقط |
| `operator_calls` | تماس اپراتور با مشتری | `REDESIGNED` | برنامه فقط |
| `password_resets` | بازنشانی گذرواژه | `REDESIGNED` | سازوکار Laravel موجود، توکن‌ها منتقل نشده |
| `payment_requests` | درخواست/دستور پرداخت | `REDESIGNED` | payment intent پایه؛ تطبیق semantics باز |
| `system_outbox` | outbox و تحویل رویداد | `REDESIGNED` | برنامه فقط |
| `payment_corrections` | اصلاح تراکنش مالی | `REDESIGNED` | برنامه فقط |
| `payment_receipts` | رسید پرداخت | `REDESIGNED` | برنامه فقط |
| `payments` | پرداخت و تخصیص | `REDESIGNED` | intent معلق پایه؛ settlement ندارد |
| `penalties` | جریمه و بخشودگی | `REDESIGNED` | قواعد استخراج/تطبیق نشده |
| `profile_update_requests` | تغییر کنترل‌شدهٔ مشخصات | `REDESIGNED` | برنامه فقط |
| `system_plugins` | افزونه‌های سامانه | `REDESIGNED` | تصمیم نگهداری باز |
| `system_plugin_migrations` | schema افزونه‌ها | `REDESIGNED` | تصمیم نگهداری باز |
| `system_plugin_permissions` | مجوز افزونه‌ها | `REDESIGNED` | تصمیم نگهداری باز |
| `system_plugin_role_permissions` | مجوز نقش افزونه | `REDESIGNED` | تصمیم نگهداری باز |
| `system_plugin_logs` | log افزونه | `REDESIGNED` | تصمیم نگهداری باز |
| `contract_deletion_archives` | آرشیو حذف قرارداد | `REDESIGNED` | برنامه فقط |
| `contract_duplicate_repairs` | رفع تکرار قرارداد | `REDESIGNED` | برنامه فقط |
| `backup_logs` | سابقهٔ پشتیبان | `REDESIGNED` | عملیات backup خارج از برنامه |
| `ecommerce_products` | فهرست فروشگاه | `REDESIGNED` | تصمیم محصول/اتصال باز |
| `ecommerce_orders` | سفارش فروشگاه | `REDESIGNED` | تصمیم محصول/اتصال باز |
| `ecommerce_order_items` | ردیف سفارش | `REDESIGNED` | تصمیم محصول/اتصال باز |
| `ecommerce_installment_requests` | درخواست اعتبار سفارش | `REDESIGNED` | تصمیم محصول/اتصال باز |
| `contract_templates` | قالب قرارداد | `REDESIGNED` | برنامه فقط |
| `contract_template_versions` | نسخهٔ قالب قرارداد | `REDESIGNED` | برنامه فقط |
| `contract_template_audit_logs` | تاریخچهٔ قالب قرارداد | `REDESIGNED` | برنامه فقط |
| `contract_document_rebuild_jobs` | بازتولید سند قرارداد | `REDESIGNED` | برنامه فقط |
| `contract_document_versions` | نسخهٔ سند قرارداد | `REDESIGNED` | برنامه فقط |
| `settlement_quotes` | مبلغ تسویهٔ پیشنهادی | `REDESIGNED` | قواعد قیمت‌گذاری استخراج نشده |
| `payment_groups` | گروه‌بندی پرداخت | `REDESIGNED` | برنامه فقط |
| `payment_allocations` | تخصیص پرداخت به بدهی | `REDESIGNED` | قواعد Legacy استخراج نشده |
| `installment_bulk_operations` | عملیات گروهی قسط | `REDESIGNED` | برنامه فقط |
| `medal_definitions` | تعریف نشان | `REDESIGNED` | برنامه فقط |
| `user_medals` | نشان کاربر | `REDESIGNED` | برنامه فقط |
| `user_medal_history` | تاریخچهٔ نشان | `REDESIGNED` | برنامه فقط |
| `settings` | تنظیمات کسب‌وکار | `REDESIGNED` | تفکیک پیکربندی/env در حال پایه‌گذاری |
| `users` | کاربران و نقش‌ها | `REDESIGNED` | auth و RBAC پایه؛ داده منتقل نشده |
| `auth_login_attempts` | کنترل تلاش ورود | `REDESIGNED` | محدودسازی پایه؛ تاریخچه منتقل نشده |
| `contract_requests` | درخواست قرارداد | `REDESIGNED` | برنامه فقط |
| `legal_policy_versions` | نسخهٔ سیاست حقوقی | `REDESIGNED` | برنامه فقط |
| `contract_legal_policy_snapshots` | snapshot سیاست زمان قرارداد | `REDESIGNED` | برنامه فقط |
| `legal_case_requests` | درخواست اقدام حقوقی | `REDESIGNED` | برنامه فقط |
| `legal_documents` | سند پروندهٔ حقوقی | `REDESIGNED` | برنامه فقط |
| `legal_case_costs` | هزینهٔ حقوقی | `REDESIGNED` | برنامه فقط |
| `legal_cost_payment_allocations` | تخصیص پرداخت هزینهٔ حقوقی | `REDESIGNED` | برنامه فقط |
| `installment_change_requests` | درخواست تغییر قسط | `REDESIGNED` | برنامه فقط |
| `installment_voids` | ابطال قسط | `REDESIGNED` | برنامه فقط |
| `portal_banners` | بنر پورتال | `REDESIGNED` | برنامه فقط |

## روش تکمیل نگاشت

برای هر ماژول، schema زنده و چند نسخهٔ واقعی backup را با migrationها، مدل‌ها، controller/serviceها، job/cronها، triggerها و pluginها مقایسه کنید. سپس برای هر فیلد قواعد تبدیل، مالکیت داده، مقدارهای ناسازگار، مرجع حسابداری، dedupe، reconciliation، rollback و cutover را ثبت کنید. این سند جایگزین آن بررسی اجرایی نیست.
