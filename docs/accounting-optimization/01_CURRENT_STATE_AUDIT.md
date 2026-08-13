# Proma Accounting — Current State Audit

## Source of truth

- Core: `config/version.php` = `1.4.3`, database version `2026.07.22`, plugin API `1.0`.
- Accounting: `plugins/PromaAccounting/plugin.json` = `1.2.5`, `requires_core = 1.3.2`.
- GitHub remote metadata was inspected previously; production is not assumed identical.
- Workspace is dirty with unrelated Core/release changes; no reset or overwrite was performed.

## Plugin inventory

- 24 manifest routes, 3 migrations, 23 permissions.
- Event listeners: `contract.created`, `contract.updated`, `contract.cancelled`, `contract.deleted`, `payment.completed`.
- Financial services: `Money`, `SalesService`, `CommissionCalculationService`, `CommissionService`, `LedgerService`, `AccountingRepository`, `AccountingIdempotencyService`.
- `accounting.js` has only short UI `setTimeout` calls; static search found no `fetch`, `XMLHttpRequest`, `setInterval` or unbounded polling: **PASSED (static only)**.

## Commission flow

`Contract::createWithInstallments` enqueues `contract.created` in `system_outbox`. The outbox worker dispatches the hook, then `SalesService::recordFromContract` resolves the seller and calls `CommissionService::refreshForSale`. `timingReady()` correctly gates down-payment, first-installment and full-settlement timings; manual approval is also a distinct state.

The actual production failure is not reproducible without a database snapshot. Seller ID, rule ID, timing, outbox row and commission row remain **BLOCKED**.

## Request/resource risks

The Core contains many call sites to `SystemOutbox::processPending` in contract/payment/installment/receipt paths. A worker-context guard exists in `models/SystemOutbox.php`, and `controllers/CronController.php` exposes a token-protected bounded outbox route. This is a stabilization change, but runtime proof that hosting actually invokes the cron is **NOT EXECUTED**.

## Database and money

- PDO cursor cleanup exists in the Core model and Accounting provider safe helpers.
- Accounting `Money` uses integer Toman/basis-points; no float cast was found inside plugin services.
- Core-wide float usage and real MySQL EXPLAIN/lock/connection measurements remain **NOT EXECUTED**.
- Runtime schema repair/DDL locations outside lifecycle migrations require a separate complete Core audit.

## Missing evidence and implementation order

1. Provision isolated staging and capture registry/schema snapshot.
2. Execute automatic-commission reproduction with a known seller/rule/timing.
3. Verify cron worker, row locking, idempotency and dead-letter behavior.
4. Measure request/worker/DB budgets before dashboard optimization.
5. Only then implement remaining reconciliation, balance audit and UI changes.
