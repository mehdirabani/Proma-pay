# Accounting dashboard timeout audit

## Source findings

| Finding | Status | Remediation |
|---|---|---|
| Session lock held over dashboard work | PASSED | CSRF/flash state is persisted, then `session_write_close()` releases the lock before heavy widgets. |
| Ten independent summary aggregates | PASSED | Replaced by one statement that scans each required table once. |
| Whole-ledger net sum | PASSED | Replaced with the sum of stored current account balances. |
| Unused expensive account preview | PASSED | Removed from dashboard; it used correlated per-user subqueries and was not rendered. |
| Monthly ledger scan | PASSED | Date-bounded and supported by a covering `(created_at, direction, entry_type, amount)` index migration. |
| Commission aggregate coverage | PASSED | New `(status, calculated_amount)` index migration. |
| Widget failure isolation | PASSED | Summary, series, recent ledger and top sellers load independently with a request-ID fallback notice. |
| Runtime migration/DDL | PASSED | Dashboard request executes no migration or DDL. |
| Production query plan | BLOCKED | Production schema cardinality and `EXPLAIN ANALYZE` were not supplied. |
| Production lock wait | BLOCKED | MySQL process/slow logs unavailable. |

Local MariaDB evidence: 100,000 ledger rows and 5,000 commissions; 30 repository iterations produced p95 153.48 ms. A 100-request authenticated HTTP diagnostic produced p95 394.17 ms and max 599.90 ms without unexpected 500 or blank output.

Accounting plugin RC version: `1.2.5-rc.1`.

The application changes remove clear amplification paths. They do not prove that the initial no-HTTP-response window originated inside Accounting, because `/health/live` also failed in that window.
