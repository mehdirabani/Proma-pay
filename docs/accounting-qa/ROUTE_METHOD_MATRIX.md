# Proma Accounting route/method matrix

Source: `plugins/PromaAccounting/plugin.json` version `1.2.1`.

| Method | Path | Handler | Permission |
| --- | --- | --- | --- |
| GET | `plugin/accounting/dashboard` | `AccountingController@dashboard` | `plugin.proma-accounting.view` |
| GET | `plugin/accounting/accounts` | `AccountingController@accounts` | `plugin.proma-accounting.view` |
| GET | `plugin/accounting/ledger/{userId}` | `AccountingController@ledger` | `plugin.proma-accounting.view` |
| POST | `plugin/accounting/ledger/post` | `AccountingController@postLedger` | `plugin.proma-accounting.post_ledger` |
| POST | `plugin/accounting/ledger/reverse/{entryId}` | `AccountingController@reverseLedger` | `plugin.proma-accounting.reverse_ledger` |
| GET | `plugin/accounting/sales` | `AccountingController@sales` | `plugin.proma-accounting.view` |
| GET | `plugin/accounting/commissions` | `AccountingController@commissions` | `plugin.proma-accounting.view_commissions` |
| POST | `plugin/accounting/commissions/approve/{commissionId}` | `AccountingController@approveCommission` | `plugin.proma-accounting.approve_commissions` |
| POST | `plugin/accounting/commissions/post/{commissionId}` | `AccountingController@postCommission` | `plugin.proma-accounting.post_commissions` |
| POST | `plugin/accounting/commissions/reverse/{commissionId}` | `AccountingController@reverseCommission` | `plugin.proma-accounting.reverse_commissions` |
| POST | `plugin/accounting/sales/assign` | `AccountingController@assignSeller` | `plugin.proma-accounting.assign_seller` |
| GET | `plugin/accounting/rules` | `AccountingController@rules` | `plugin.proma-accounting.manage_commission_rules` |
| POST | `plugin/accounting/rules/save` | `AccountingController@saveRule` | `plugin.proma-accounting.manage_commission_rules` |
| GET | `plugin/accounting/settings` | `AccountingController@settings` | `plugin.proma-accounting.manage_accounting_settings` |
| POST | `plugin/accounting/settings/save` | `AccountingController@saveSettings` | `plugin.proma-accounting.manage_accounting_settings` |
| POST | `plugin/accounting/settings/preview` | `AccountingController@previewCommission` | `plugin.proma-accounting.manage_accounting_settings` |
| GET | `plugin/accounting/setup` | `AccountingController@setup` | `plugin.proma-accounting.manage_accounting_settings` |
| POST | `plugin/accounting/setup/save` | `AccountingController@saveSetup` | `plugin.proma-accounting.manage_accounting_settings` |
| POST | `plugin/accounting/setup/skip` | `AccountingController@skipSetup` | `plugin.proma-accounting.manage_accounting_settings` |
| GET | `plugin/accounting/help` | `AccountingController@help` | `plugin.proma-accounting.view` |
| GET | `plugin/accounting/backfill` | `AccountingController@backfill` | `plugin.proma-accounting.backfill_sales` |
| POST | `plugin/accounting/backfill/run` | `AccountingController@runBackfill` | `plugin.proma-accounting.backfill_sales` |

## 405 guard

The core plugin router now separates "path matched with wrong HTTP method" from "route not found" and returns a Persian 405 response with the allowed methods. This prevents a POST-only accounting action from being silently treated as a missing route.

