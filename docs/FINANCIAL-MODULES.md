# ماژول‌های مالی Proma Pay 7.0.4

این راهنما مسیرهای سورس را به هم وصل می‌کند تا جریان مالی از قرارداد تا ثبت دریافت و دفترکل قابل دنبال‌کردن باشد. جزئیات عملیاتی هر گردش در `docs/workflows/` و قواعد تفصیلی حسابداری در `docs/accounting/` قرار دارد. این سند شرح معماری کد است؛ جایگزین قرارداد حسابداری یا تنظیم سیاست مالی کسب‌وکار نیست.

## ۱. قیمت قرارداد و برنامهٔ اقساط

مسیر اصلی قرارداد در `controllers/ContractsController.php` و `models/Contract.php` است. پیش‌نمایش و محاسبه از `helpers/FinanceHelper.php` و `helpers/MoneyMath.php` انجام می‌شود؛ پیش‌پرداخت از اصل کسر و مانده به‌عنوان مبلغ تأمین‌شده محاسبه می‌شود.

منطق `FinanceHelper::installmentAmount()` دو روش را پیاده می‌کند:

- **ساده:** سود متناسب با نرخ ماهانه و تعداد ماه‌ها روی اصلِ تأمین‌شده محاسبه می‌شود.
- **مرکب:** نرخ هر ماه روی ماندهٔ همان مرحله اعمال می‌شود.

مبلغ هر قسط با تقسیم رو به بالای کل مبلغ بر تعداد ماه‌ها به‌دست می‌آید و سپس به گام 100,000 واحد پولی رو به بالا گرد می‌شود. نرخ با دقت چهار رقم اعشار به واحد صحیح تبدیل می‌شود؛ ضرب/تقسیم‌های مالی در `MoneyMath` با عدد صحیح انجام می‌شوند تا محاسبه به خطای ممیز شناور وابسته نباشد. `contractPreview()` اصل، پیش‌پرداخت، ماندهٔ تأمین‌شده، مبلغ قسط، کل پرداخت و روش سود را برمی‌گرداند.

مسیر ثبت قرارداد و ایجاد schedule در `models/Contract.php`؛ ایجاد، ویرایش و نمایش اقساط در `models/Installment.php` و `controllers/InstallmentsController.php` است. اقساط، تاریخ سررسید و پرداخت اولیه به قرارداد وصل می‌مانند.

## ۲. بدهی، سررسید و پیش‌نمایش دریافت

`helpers/InstallmentFinancialStateService.php`, `helpers/FinancialDebtEngine.php` و `helpers/FinanceHelper.php` وضعیت اصل باقی‌مانده، جریمهٔ عادی/حقوقی، پاداش واجدشرایط و مبلغ قابل پرداخت را از اقساط و پرداخت‌های مرتبط می‌سازند. `controllers/OverdueController.php` و داشبوردهای وصول از همین مسیرهای محاسباتی استفاده می‌کنند؛ نباید مبلغ مانده را با جمع خام ستون‌های وضعیت جایگزین کرد.

`docs/workflows/03_INSTALLMENT_PAYMENT.md`, `docs/workflows/PAYMENT_ALLOCATION.md` و `docs/workflows/07_SETTLEMENT.md` ترتیب و قواعد ثبت دریافت، تخصیص یک پرداخت به یک یا چند قسط، پرداخت ناقص، تسویهٔ زودتر از موعد و بستن قرارداد را شرح می‌دهند.

## ۳. دریافت، اصلاح و تأیید درگاه

- `models/Payment.php`, `models/PaymentRequest.php` و `models/PaymentReceipt.php` نگه‌داری دریافت، درخواست درگاه و رسید را انجام می‌دهند؛ Controllerهای `controllers/PaymentsController.php` و `controllers/InstallmentsController.php` ورودی HTTP را به آن‌ها وصل می‌کنند.
- پرداخت دستی یا آنلاین پس از کنترل وضعیت قرارداد/قسط و مجوز ثبت می‌شود. `PaymentAllocationService` قواعد تخصیص را اجرا می‌کند و کلید idempotency مانع ثبت دوبارهٔ همان درخواست می‌شود.
- اصلاح پرداخت، درخواست/رسید و تخصیص از مسیرهای صریح دامنه و جدول‌های Core مانند `payment_corrections`, `payment_allocations`, `payment_receipts`, `payment_requests` و `payment_groups` قابل پیگیری‌اند. رکورد تاریخی را برای «اصلاح» بی‌ردپا بازنویسی نکنید؛ از گردش اصلاح یا برگشت تعریف‌شده استفاده کنید.
- callback درگاه باید از مسیر adapter تأیید شود. `core/PaymentGatewayRegistry.php` رجیستری Core را می‌سازد؛ PromaZarinpal callback، verify، sandbox و گزارش تراکنش را در `plugins/PromaZarinpal/` پیاده می‌کند. reconciliation و outbox برای نگه‌داشت وجه تأییدشده و تکرار امن پردازش در مدل‌های Core و migrationهای مربوط مستند شده‌اند.

## ۴. حسابداری و دفترکل کاربران — PromaAccounting

منطق در `plugins/PromaAccounting/src/Services/` قرار دارد:

- `LedgerService` سند/ردیف دفترکل و اثر آن بر ماندهٔ حساب کاربر را ثبت می‌کند؛ دسته‌بندی هزینه، پاداش، کسر، دریافت و پرداخت در Controller و Repository افزونه به آن متصل‌اند.
- `SalesService` فروش قرارداد، فروشنده، اصل، پیش‌پرداخت و ماندهٔ تأمین‌شده را به قرارداد Core نسبت می‌دهد.
- `CommissionCalculationService` قوانین کمیسیون را محاسبه می‌کند؛ `CommissionService` چرخهٔ تأیید، ثبت، پرداخت و برگشت را جدا می‌کند. مجوزها و وضعیت‌ها در manifest افزونه تعریف شده‌اند.
- جدول‌های افزونه در `plugins/PromaAccounting/migrations/` هستند؛ شرح کامل آن‌ها در [PLUGINS.md](PLUGINS.md) آمده است.

قاعدهٔ مانده و علامت بدهکار/بستانکار در [`docs/accounting/ACCOUNTING_BALANCE_CONVENTION.md`](accounting/ACCOUNTING_BALANCE_CONVENTION.md) ثبت شده است. سند اصلاحی باید با ردیف/رویداد تازه قابل حسابرسی باشد.

## ۵. افیلیت و کمیسیون معرفی — PromaAffiliate

`plugins/PromaAffiliate/src/Services/AffiliateService.php` کد معرفی و attribution فروش/قرارداد را به اعضا وصل می‌کند. migrations جداگانه اعضا، referral، تراکنش، فروش و برداشت را نگه می‌دارند. اتصال به دفترکل حسابداری اختیاری است و manifest وابستگی سختی به PromaAccounting اعلام نمی‌کند؛ برای این‌که اثر کیف پول به حسابداری منتقل شود، افزونهٔ Accounting باید فعال باشد.

## ۶. خرید، موجودی، فروش کالا و سهم شرکا — PromaInventoryPartners

سرویس‌های `StockService`, `SaleService`, `OwnershipService`, `ProfitCalculator` و `LedgerService` زیر `plugins/PromaInventoryPartners/src/` مدیریت خرید، محموله و سریال، حرکت/تعدیل انبار، فروش، برگشت، بهای تمام‌شده، هزینهٔ فروش و توزیع سرمایه/سود بین شرکا را انجام می‌دهند.

- فروش اقساطی به `contract_id` Core متصل می‌شود و سرویس فروش اصل قرارداد را با جمع قیمت کالا تطبیق می‌دهد.
- قفل ردیف، کنترل موجودی، hash درخواست و idempotency key مانع فروش دوباره یا عبور از موجودی آزاد می‌شوند.
- مبلغ‌های پایگاه این افزونه با پسوندهای `_rial` و کلاس `Money` نگه‌داری می‌شوند؛ مرز تبدیل Toman/Rial در ورودی‌ها صریح است.
- جداول خرید، محموله، سهم، سریال، فروش، تخصیص پرداخت، برگشت و journal در migration افزونه‌اند؛ جدول‌ها در [PLUGINS.md](PLUGINS.md) فهرست شده‌اند.

## ۷. درآمد و هزینهٔ تعمیر — PromaRepair

`plugins/PromaRepair/src/Domain/` و `Services/` وضعیت پذیرش و تعمیر، هزینهٔ قطعه/تعمیر، دریافت مشتری، تأیید هزینه، تکنسین و خلاصهٔ مالی پرونده را پیاده می‌کنند. جدول‌های `proma_repair_*` مسیر مجزا و ممیزی‌شدهٔ تعمیر هستند و به دادهٔ قرارداد/مشتری Core از طریق شناسه‌های مرجع وصل می‌شوند. مستند گردش در [repair-management-architecture.md](repair-management-architecture.md) است.

## ۸. امضا و اعلان مالی — PromaSignConnect

PromaSignConnect دفترکل یا مبلغ قرارداد را محاسبه نمی‌کند؛ امضا، OTP، evidence، webhook و تحویل اعلان را برای پشتیبانی از فرایند قرارداد نگه می‌دارد. تنظیم کلید provider در افزونه رمزگذاری می‌شود. تست adapter محلی به معنی آزمایش ارسال پیام واقعی نیست.

## جداول اصلی که باید هنگام دنبال‌کردن جریان بررسی شوند

| مفهوم | جدول‌ها / مدل‌ها |
|---|---|
| قرارداد و اقساط | `contracts`, `installments`; `models/Contract.php`, `models/Installment.php` |
| پرداخت و تخصیص | `payments`, `payment_allocations`, `payment_groups`, `payment_corrections`, `payment_receipts`, `payment_requests`; `models/Payment.php` |
| تسویه و reconciliation | `settlement_quotes`, `system_outbox`, جدول‌های تراکنش درگاه؛ `helpers/InstallmentSettlementService.php` و پلاگین درگاه |
| رویداد و ممیزی Core | `financial_ledger_events`, `financial_ledger_lines`, `audit_logs`, `contract_change_logs` |
| دفترکل/کمیسیون حسابداری | `plugin_accounting_ledger_entries`, `plugin_accounting_commissions`, `plugin_accounting_sales` |
| موجودی و سهم شریک | جدول‌های `plugin_inventory_*` در migrationهای InventoryPartners |
| افیلیت | جدول‌های `proma_affiliate_*` |
| تعمیر | جدول‌های `proma_repair_*` |

## راهنماهای مرتبط

- [گردش پرداخت قسط](workflows/03_INSTALLMENT_PAYMENT.md)
- [تخصیص پرداخت](workflows/PAYMENT_ALLOCATION.md)
- [تسویه قرارداد](workflows/07_SETTLEMENT.md)
- [فروش و کمیسیون](workflows/SALES_COMMISSION.md)
- [حساب کاربران](workflows/USER_ACCOUNTING.md)
- [راهنمای افزونه‌ها و migrationها](PLUGINS.md)
