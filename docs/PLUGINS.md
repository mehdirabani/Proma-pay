# افزونه‌های رسمی Proma Pay — شاخهٔ Core 7.0.4

این فهرست از پوشه‌های `plugins/*/plugin.json` در شاخهٔ Core نسخهٔ 7.0.4 استخراج شده است. شش افزونه در این شاخه حفظ شده‌اند و فایل‌های آن‌ها در این پاک‌سازی تغییر نکرده است. نسخه‌ها و حداقل نیازمندی‌ها مطابق manifest خود افزونه‌ها هستند؛ اجرای gate و تأیید سازگاری افزونه‌ها با Core 7.0.4 هنوز انجام نشده است. وابستگی خارجی به درگاه یا سرویس پیام‌رسان در زمان فعال‌سازی جداگانه تنظیم می‌شود و وابستگی پلاگینی محسوب نمی‌شود.

| افزونه | نسخه | کارکرد اعلام‌شده در manifest | Provider |
|---|---:|---|---|
| [PromaAccounting](../plugins/PromaAccounting/) (`proma-accounting`) | 1.2.28 | فروش، کمیسیون، دفترکل کاربران، هزینه، پاداش و دریافت/پرداخت | `Proma\\Plugins\\Accounting\\AccountingServiceProvider` |
| [PromaAffiliate](../plugins/PromaAffiliate/) (`proma-affiliate`) | 1.1.1 | کد معرفی، پاداش، اعتبار خرید و برداشت | `Proma\\Plugins\\Affiliate\\AffiliateServiceProvider` |
| [PromaInventoryPartners](../plugins/PromaInventoryPartners/) (`proma-inventory-partners`) | 1.1.2 | کالا، خریدوفروش، موجودی، سهم شرکا و هزینهٔ شراکت | `Proma\\Plugins\\InventoryPartners\\InventoryPartnersServiceProvider` |
| [PromaRepair](../plugins/PromaRepair/) (`proma-repair`) | 1.2.13 | پذیرش و گردش تعمیر، تکنسین، هزینه، رسید و پیگیری | `Proma\\Plugins\\Repair\\RepairServiceProvider` |
| [PromaSignConnect](../plugins/PromaSignConnect/) (`proma-sign-connect`) | 1.3.1 | امضای الکترونیکی داخلی و ارتباط چندکاناله | `Proma\\Plugins\\SignConnect\\SignConnectServiceProvider` |
| [PromaZarinpal](../plugins/PromaZarinpal/) (`proma-zarinpal`) | 1.0.7 | اتصال به درگاه زرین‌پال، تراکنش و بازبینی پرداخت | `Proma\\Plugins\\Zarinpal\\ZarinpalServiceProvider` |

## ساختار و اتصال به Core

هر افزونه در مسیر مستقل `plugins/<نام>/` نگه‌داری می‌شود. `plugin.json` نسخه، نیازمندی‌ها، مجوزها، routeها، فایل‌های migration و provider را معرفی می‌کند. کلاس‌های PHP زیر `src/`، صفحات زیر `views/`، assetها زیر `assets/`، migrationهای SQL زیر `migrations/`، آزمون‌ها زیر `tests/` و راهنماها در `README.md` یا `docs/` قرار دارند.

`core/PluginManager.php` افزونه‌ها را کشف و چرخهٔ نصب/فعال‌سازی را مدیریت می‌کند؛ `core/PluginManifest.php` manifest را می‌خواند و اعتبارسنجی می‌کند. Providerها از قرارداد `core/PluginServiceProviderInterface.php` برای اتصال route، hook، منو و سرویس‌ها به Core استفاده می‌کنند. route و permission در manifest و provider ثبت می‌شوند؛ اجرای handlerها همچنان از Router و کنترل‌های دسترسی Core می‌گذرد. migration هر افزونه باید در فهرست manifest آن ثبت شده باشد.

## جزئیات هر افزونه

### PromaAccounting 1.2.28

- **سورس:** `plugins/PromaAccounting/src/`؛ UI در `views/` و assetها در `assets/`.
- **اتصال:** provider حسابداری routeها و منوهای دفترکل، حساب کاربر، فروش، کمیسیون، قوانین و تنظیمات را ثبت می‌کند. سرویس‌های دفترکل و کمیسیون درخواست را به جدول‌های خود افزونه می‌نویسند و در نقاط اتصال قرارداد/پرداخت Core شناسهٔ قرارداد یا کاربر را نگه می‌دارند.
- **وابستگی اعلام‌شده:** ندارد.
- **Migrationها:** `2026_07_12_accounting_core.sql`, `2026_07_13_accounting_v1_1.sql`, `2026_07_18_accounting_request_integrity.sql`, `2026_07_21_accounting_dashboard_performance.sql`, `2026_07_26_accounting_analytics_salary.sql`, `2026_07_28_customer_referrals.sql`, `2026_07_29_customer_withdrawals_bank.sql`.
- **جدول‌های ساخته‌شده در migrationها:** `plugin_accounting_sales`, `plugin_accounting_sales_logs`, `plugin_accounting_commission_rules`, `plugin_accounting_commissions`, `plugin_accounting_accounts`, `plugin_accounting_ledger_entries`, `plugin_accounting_categories`, `accounting_user_accounts`, `accounting_categories`, `accounting_ledger_entries`, `plugin_accounting_settings`, `plugin_accounting_backfill_jobs`, `accounting_requests`, `accounting_analytics_categories`, `user_salary_rules`, `user_salary_periods`, `customer_referral_codes`, `customer_referrals`, `customer_referral_commissions`, `customer_referral_payouts`, `accounting_referral_settings`, `customer_bank_information`, `customer_referral_withdrawals`.
- **آزمون‌ها:** `plugins/PromaAccounting/tests/` و `tests/Plugins/`.

### PromaAffiliate 1.1.1

- **سورس:** `plugins/PromaAffiliate/src/`، `views/` و `assets/`.
- **اتصال:** کد معرفی و ثبت attribution قرارداد/خرید را فراهم می‌کند. اتصال اختیاری به دفترکل Accounting با فعال‌بودن همان افزونه استفاده می‌شود؛ manifest وابستگی سختی تعریف نکرده است.
- **وابستگی اعلام‌شده:** ندارد.
- **Migrationها:** `2026_10_03_affiliate_core.sql`, `2026_10_04_purchase_commissions.sql`, `2026_10_05_affiliate_wallet_management.sql`, `2026_10_06_affiliate_contract_commissions.sql`.
- **جدول‌های ساخته‌شده در migrationها:** `proma_affiliate_members`, `proma_affiliate_referrals`, `proma_affiliate_transactions`, `proma_affiliate_withdrawals`, `proma_affiliate_settings`, `proma_affiliate_sales`.
- **آزمون‌ها:** `tests/Plugins/AffiliateRewardTriggerTest.php`, `tests/Plugins/AffiliateAccountingConsistencyTest.php` و تست‌های افزونه.

### PromaInventoryPartners 1.1.2

- **سورس:** `plugins/PromaInventoryPartners/src/`، `views/` و `assets/`.
- **اتصال:** از قراردادهای Core برای نسبت‌دادن فروش استفاده می‌کند؛ موجودی، سریال، خرید، فروش، برگشت، سهم مالک و دفتر ثبت داخلی افزونه در جداول خودش نگه‌داری می‌شوند.
- **وابستگی اعلام‌شده:** ندارد.
- **Migrationها:** `2026_10_13_inventory_partners_core.sql`, `2026_10_13_inventory_partners_v110.sql`.
- **جدول‌های ساخته‌شده در migrationها:** `plugin_inventory_settings`, `plugin_inventory_warehouses`, `plugin_inventory_suppliers`, `plugin_inventory_partners`, `plugin_inventory_products`, `plugin_inventory_purchase_headers`, `plugin_inventory_stock_lots`, `plugin_inventory_purchase_lines`, `plugin_inventory_lot_partner_shares`, `plugin_inventory_serial_items`, `plugin_inventory_stock_movements`, `plugin_inventory_stock_adjustments`, `plugin_inventory_sales`, `plugin_inventory_sale_lines`, `plugin_inventory_sale_partner_shares`, `plugin_inventory_sale_serial_items`, `plugin_inventory_payment_allocations`, `plugin_inventory_returns`, `plugin_inventory_return_lines`, `plugin_inventory_journals`, `plugin_inventory_journal_lines`, `plugin_inventory_capital_contributions`, `plugin_inventory_settlement_requests`, `plugin_inventory_transfers`, `plugin_inventory_transfer_lines`, `plugin_inventory_transfer_serial_items`, `plugin_inventory_price_history`, `plugin_inventory_audit_logs`, `plugin_inventory_brands`, `plugin_inventory_categories`, `plugin_inventory_expenses`, `plugin_inventory_expense_partner_allocations`.
- **آزمون‌ها:** `plugins/PromaInventoryPartners/tests/` شامل smoke و integrationهای MySQL.

### PromaRepair 1.2.13

- **سورس:** `plugins/PromaRepair/src/`، `views/` و `assets/`.
- **اتصال:** provider، routeها، مجوزها، migration و بررسی سلامت را به Plugin Manager معرفی می‌کند؛ داده‌های عملیاتی تعمیر از قراردادها و پرداخت‌های Core قابل ارجاع‌اند.
- **وابستگی اعلام‌شده:** ندارد.
- **Migrationها:** `2026_10_01_repair_core.sql`, `2026_10_02_repair_expense_approval.sql`, `2026_10_03_repair_soft_delete.sql`, `2026_10_09_repair_technician_device_password.sql`.
- **جدول‌های ساخته‌شده در migrationها:** `proma_repairs`, `proma_repair_devices`, `proma_repair_technicians`, `proma_repair_assignments`, `proma_repair_diagnoses`, `proma_repair_charges`, `proma_repair_expenses`, `proma_repair_payments`, `proma_repair_attachments`, `proma_repair_status_history`, `proma_repair_notes`, `proma_repair_warranties`, `proma_repair_audit`, `proma_repair_settings`, `proma_repair_setting_versions`, `proma_repair_tracking_attempts`, `proma_repair_expense_events`.
- **آزمون‌ها:** `tests/promarepair_domain_smoke.php`, `tests/promarepair_release_checks.php` و تست‌های `tests/Plugins/`.

### PromaSignConnect 1.3.1

- **سورس:** `plugins/PromaSignConnect/src/`، `views/`، `assets/` و راهنمای providerها در `docs/`.
- **اتصال:** امضا، OTP، webhook و ارسال اعلان را از routeها، event catalog، providerها و سیاست‌های مجوز Core به کار می‌گیرد. رمزهای سرویس در تنظیمات رمزگذاری می‌شوند و خروجی manifest حاوی آن‌ها نیست.
- **وابستگی اعلام‌شده:** ندارد. اتصال زنده به بله سفیر، IPPanel، SMS.ir یا Telegram به حساب و کلید معتبر همان سرویس نیاز دارد.
- **Migrationها:** `2026_07_28_sign_connect.sql`, `2026_07_29_professional_settings.sql`, `2026_07_29_release_candidate_hardening.sql`, `2026_07_30_settings_catalog.sql`, `2026_07_30_security_signature_policies.sql`, `2026_08_01_stable_1_2_0.sql`, `2026_08_01_provider_tests_1_2_1.sql`.
- **جدول‌های ساخته‌شده در migrationها:** `proma_connect_settings`, `proma_connect_templates`, `proma_connect_otp_challenges`, `proma_connect_channel_links`, `proma_connect_deliveries`, `proma_sign_requests`, `proma_sign_signers`, `proma_sign_evidence`, `proma_sign_artifacts`, `proma_connect_webhook_events`, `proma_connect_provider_configs`, `proma_connect_bale_attempts`, `proma_connect_recipient_channel_states`, `proma_connect_notification_rules`, `proma_connect_telegram_link_tokens`, `proma_connect_telegram_links`, `proma_sign_hash_registry`, `proma_connect_settings_audit`, `proma_connect_module_policies`, `proma_sign_signer_policies`, `proma_sign_integrity_runs`, `proma_connect_legal_holds`, `proma_connect_otp_security_events`, `proma_connect_event_catalog`, `proma_connect_provider_event_mappings`, `proma_connect_template_versions`, `proma_connect_retention_policies`, `proma_connect_provider_test_deliveries`, `proma_connect_template_variables`, `proma_connect_variable_provider_mappings`, `proma_connect_broadcast_campaigns`, `proma_connect_broadcast_dispatches`.
- **آزمون‌ها:** `plugins/PromaSignConnect/tests/` و `tests/Plugins/SignConnectCapabilityIntegrityTest.php`. ارسال پیام به provider خارجی در تست‌های انتشار اجرا نمی‌شود.

### PromaZarinpal 1.0.7

- **سورس:** `plugins/PromaZarinpal/src/`، `views/`، `assets/` و `tests/`.
- **اتصال:** `ZarinpalServiceProvider` درگاه را در رجیستری پرداخت Core ثبت می‌کند؛ callback و ابزار بازبینی با permission مستقل در دسترس‌اند. تأیید تراکنش از مسیر client و verification افزونه انجام می‌شود.
- **وابستگی اعلام‌شده:** ندارد؛ تنظیم merchant و انتخاب sandbox/live در خود افزونه است.
- **Migration:** `2026_07_13_zarinpal_gateway.sql`.
- **جدول‌های ساخته‌شده:** `proma_zarinpal_settings`, `proma_zarinpal_transactions`, `proma_zarinpal_logs`.
- **آزمون‌ها:** `plugins/PromaZarinpal/tests/` برای مبلغ، پروتکل، sandbox و رمزگذاری secret.

## مجوز و فایل‌های منتشرنشده

در `plugins/` هیچ افزونهٔ شخص ثالثی پیدا نشد؛ هر شش پوشه در manifest خود Proma Pay را نویسنده معرفی می‌کنند. مجوز مخزن، بستهٔ Proma Pay را proprietary می‌داند؛ انتشار این سورس در همین مخزن رسمی و عمومی انجام می‌شود و به‌معنی مجوز استفادهٔ خارج از شرایط خرید نیست. runtime telemetry، cache، log، `.env` واقعی، فایل پایگاه‌دادهٔ محلی و backup از این شاخه حذف یا ignore شده‌اند.
