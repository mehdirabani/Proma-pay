# Commission root cause

Bug ID: `ACC-COMMISSION-001`

## Current source finding

Manual commission posting uses:

- Route: `POST plugin/accounting/commissions/post/{commissionId}`
- Controller: `AccountingController@postCommission`
- Service: `CommissionService::post`
- Ledger effect: `LedgerService::post(..., 'commission:' . commission_id, ...)`

The commission route itself does not have the same exact-vs-dynamic collision as `ledger/post`. However, commission posting ultimately depends on the same ledger posting service and financial idempotency behavior.

## Source-level safeguards found

- Commission posting runs inside a database transaction.
- The commission row is selected `FOR UPDATE`.
- Existing `posted_ledger_entry_id` or `posted` status prevents a second effective post.
- The ledger entry uses deterministic idempotency key `commission:{id}`.
- `LedgerService` now records idempotency state in `accounting_requests`.

## Remaining unverified areas

- Valid commission POST with a real commission row: NOT EXECUTED
- Missing account / inactive account: NOT EXECUTED
- Duplicate browser submit: NOT EXECUTED
- Concurrent submit: NOT EXECUTED
- Notification/report/plugin event failure after commit: NOT EXECUTED
- Minimum, maximum and rounding scenarios against database fixtures: NOT EXECUTED

## Status

Source audit: PASSED

Runtime commission reproduction and database verification: BLOCKED until a real MySQL/MariaDB test database and authenticated test user are available.
