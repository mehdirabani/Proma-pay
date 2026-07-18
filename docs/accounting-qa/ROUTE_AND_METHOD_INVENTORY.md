# Proma Accounting route and method inventory

Date: 2026-07-18

| Route | Method | Controller action | Permission | Response | CSRF | Financial idempotency |
| --- | --- | --- | --- | --- | --- | --- |
| `plugin/accounting/dashboard` | GET | `AccountingController@dashboard` | `plugin.proma-accounting.view` | HTML | No | No |
| `plugin/accounting/accounts` | GET | `AccountingController@accounts` | `plugin.proma-accounting.view` | HTML | No | No |
| `plugin/accounting/ledger/{userId}` | GET | `AccountingController@ledger` | `plugin.proma-accounting.view` | HTML | No | No |
| `plugin/accounting/ledger/post` | POST | `AccountingController@postLedger` | `plugin.proma-accounting.post_ledger` | Redirect | Yes | Yes |
| `plugin/accounting/ledger/reverse/{entryId}` | POST | `AccountingController@reverseLedger` | `plugin.proma-accounting.reverse_ledger` | Redirect | Yes | Yes |
| `plugin/accounting/sales` | GET | `AccountingController@sales` | `plugin.proma-accounting.view` | HTML | No | No |
| `plugin/accounting/commissions` | GET | `AccountingController@commissions` | `plugin.proma-accounting.view_commissions` | HTML | No | No |
| `plugin/accounting/commissions/approve/{commissionId}` | POST | `AccountingController@approveCommission` | `plugin.proma-accounting.approve_commissions` | Redirect | Yes | No |
| `plugin/accounting/commissions/post/{commissionId}` | POST | `AccountingController@postCommission` | `plugin.proma-accounting.post_commissions` | Redirect | Yes | Yes, via `commission:{id}` ledger key |
| `plugin/accounting/commissions/reverse/{commissionId}` | POST | `AccountingController@reverseCommission` | `plugin.proma-accounting.reverse_commissions` | Redirect | Yes | Yes, via reversal key |
| `plugin/accounting/sales/assign` | POST | `AccountingController@assignSeller` | `plugin.proma-accounting.assign_seller` | Redirect | Yes | Partial, sale row locking only |
| `plugin/accounting/rules` | GET | `AccountingController@rules` | `plugin.proma-accounting.manage_commission_rules` | HTML | No | No |
| `plugin/accounting/rules/save` | POST | `AccountingController@saveRule` | `plugin.proma-accounting.manage_commission_rules` | Redirect | Yes | No |
| `plugin/accounting/settings` | GET | `AccountingController@settings` | `plugin.proma-accounting.manage_accounting_settings` | HTML | No | No |
| `plugin/accounting/settings/save` | POST | `AccountingController@saveSettings` | `plugin.proma-accounting.manage_accounting_settings` | Redirect | Yes | No |
| `plugin/accounting/settings/preview` | POST | `AccountingController@previewCommission` | `plugin.proma-accounting.manage_accounting_settings` | HTML | Yes | No |
| `plugin/accounting/setup` | GET | `AccountingController@setup` | `plugin.proma-accounting.manage_accounting_settings` | HTML | No | No |
| `plugin/accounting/setup/save` | POST | `AccountingController@saveSetup` | `plugin.proma-accounting.manage_accounting_settings` | Redirect | Yes | No |
| `plugin/accounting/setup/skip` | POST | `AccountingController@skipSetup` | `plugin.proma-accounting.manage_accounting_settings` | Redirect | Yes | No |
| `plugin/accounting/help` | GET | `AccountingController@help` | `plugin.proma-accounting.view` | HTML | No | No |
| `plugin/accounting/backfill` | GET | `AccountingController@backfill` | `plugin.proma-accounting.backfill_sales` | HTML | No | No |
| `plugin/accounting/backfill/run` | POST | `AccountingController@runBackfill` | `plugin.proma-accounting.backfill_sales` | Redirect | Yes | Batch-limited, not fully idempotent |

## Route dispatch result

- Source reproduction: PASSED
- Local isolated route dispatch regression: PASSED
- Authenticated browser route smoke tests: NOT EXECUTED
- Database-backed operation tests: NOT EXECUTED
