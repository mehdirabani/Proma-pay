# Database timeout and lock audit

| Check | Status | Notes |
|---|---|---|
| PDO error mode and buffered reads | PASSED | Exceptions, native prepares and buffered MySQL queries remain enabled; fetch helpers close cursors. |
| Connection bound | PASSED | PDO connection timeout is set to 5 seconds. |
| Query profiling | PASSED | Count, total duration, error count and slowest fingerprint are recorded without parameters. |
| Accounting full-ledger scan | PASSED | Removed from dashboard summary. |
| Dashboard index migration | PASSED | Idempotent migration executed twice; real MariaDB plan uses the covering ledger and commission indexes. |
| Production slow-query log | BLOCKED | Not available. |
| `SHOW PROCESSLIST` during incident | BLOCKED | Not captured. |
| InnoDB lock/deadlock report | BLOCKED | Not captured. |
| Local MariaDB query plan | PASSED | Tested with 100,000 synthetic ledger rows and 5,000 commission rows. |
| Production `EXPLAIN ANALYZE` | BLOCKED | No production/staging database access. |

Required safe capture:

```sql
SHOW FULL PROCESSLIST;
SHOW ENGINE INNODB STATUS;
SELECT * FROM performance_schema.data_lock_waits;
SELECT * FROM performance_schema.events_statements_summary_by_digest ORDER BY SUM_TIMER_WAIT DESC LIMIT 20;
```

Do not run these commands through a public endpoint and do not include customer query parameters in the report.
