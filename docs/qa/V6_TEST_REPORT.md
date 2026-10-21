# V6.0.0 Candidate — QA report

This report records local candidate evidence. Production availability and a full host-specific upgrade have not been certified.

## Environment

- PHP 8.3.33 CLI.
- MySQL Server 8.4.3 in a disposable, empty datadir under the workspace; test database `proma_v142` was created only for this run.
- Fresh install schema and V6 additive migration were both applied to that test database. The five V6 tables were confirmed in `information_schema`.
- Production host and production database were not accessed.

## Passed

- Fresh installation schema import and V6 migration on MySQL 8.4.3.
- Full local static/unit gate: 797 PHP files linted and the configured static/unit suite passed. Command: `php tools/release-gate.php --local --static-only`.
- `tests/integration_installment_settlement_v150.php`: deterministic partial allocation, duplicate request handling, correction reversal, and gateway idempotency.
- `tests/integration_payment_overflow_allocation_v204.php`: schedule-scoped overflow for manual and gateway payment paths, including retry behavior.
- `tests/integration_legal_financial_summary_v158.php`: referred legal-cost approval, payable-summary reconciliation, and ledger posting. Its fixture now creates a valid referred case to match the current chargeability policy.
- `tests/integration_v600_features.php`: role-targeted global announcements and read state, explainable Proma Score with overdue debt, and idempotent collection task creation.
- Targeted application-security capability scans on the new ledger, collections, risk, and notification paths returned no findings. The scanner has no full PHP AST parser, so this is limited evidence, not a complete security audit.
- Migration safety analysis found no destructive SQL; the analyzer used its conservative fallback parser because `sqlparse` was unavailable.

## Not run / limits

- The complete DB/HTTP release gate was not run. The local release gate therefore reports `RELEASE_GATE_LOCAL_STATIC_UNIT_PASSED_INTEGRATION_BLOCKED`; only the four integration scripts above were executed against the disposable database.
- No restored V5.0.5 application/database copy, production web server, gateway sandbox, race/recovery suite, or host-specific upload/restore path was available for verification.
- The financial journal is append-only for supported new payment, approved referred legal-cost, and reversal flows. It is not yet a complete historical accounting source: there is no opening-balance backfill, and some legacy discounts, write-offs, imported adjustments, and time-accrued penalty changes are not represented as journal events. Current balances continue to come from `FinancialDebtEngine` over the established application records.
- Proma Score is advisory. Returned-check data is omitted because no authoritative returned-check data source was found.
- Collection progression is available through a token-protected daily cron route or admin action. It creates in-app notifications and tasks; it does not send SMS or file legal actions automatically.

## Release status

The artifacts are V6.0.0 release candidates, not production-certified builds. Before production use, run the full database/HTTP and restored-baseline upgrade gate on a disposable copy of the target host and reconcile historical debt against the ledger/read model.
