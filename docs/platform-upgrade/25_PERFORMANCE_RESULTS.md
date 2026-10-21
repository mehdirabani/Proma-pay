# Focused performance result — customer list

Measured on the same local QA server and database described in `docs/performance/BEFORE_BASELINE.md`; 30 successful warm requests per run.

| Metric | Before | After |
| --- | ---: | ---: |
| SQL queries, p95 | 1,100 | 45 |
| SQL execution time, p95 | 359.68 ms | 54.25 ms |
| PHP request time, p95 | 431.19 ms | 88.29 ms |
| Full HTTP response time, p95 | 439.99 ms | 100.03 ms |
| Full HTTP response time, p50 | 413.17 ms | 82.31 ms |
| HTML response bytes, p50 | 288,963 | 288,963 |
| HTTP errors in 30 requests | 0 | 0 |

Cause: `CustomersController::index()` recalculated legacy and modern medals for every customer on each page request, running hundreds of queries and possible writes. It also queried the six-month payment trend separately for each customer. The list now reads existing medals and gets all visible customers' trend data in one query. Medal evaluation is queued from relevant contract, payment, installment and identity-document changes, and the existing administrator synchronization action remains available.

Verification: the batched trend exactly matched the prior per-customer result for 20 customers with paid payments on the QA database. The outbox worker integration covered a held competing worker lock, notification retry deduplication, and queued medal processing. These results do not certify production-sized data or browser vitals.
