# Performance test results

| Test | Status | Result |
|---|---|---|
| PHP lint of changed source | PASSED | No syntax errors in new Core/plugin/test files. |
| JavaScript syntax | PASSED | `node --check assets/js/app.js`. |
| Dashboard bounded-query static test | PASSED | One summary statement, date-bounded monthly ledger, no unused account preview. |
| Query profiler wiring | PASSED | Every `Model::query` records count and duration. |
| Session-lock regression static test | PASSED | Dashboard releases the session lock before widget queries. |
| Index migration idempotency source check | PASSED | Information-schema guards prevent duplicate indexes. |
| Real MariaDB query plan | PASSED | On 100,000 ledger rows the monthly query changed from full scan to `range` with `Using index`; commission sum uses its covering index. |
| Repository benchmark | PASSED | Final gate: 30 iterations across summary, series, recent ledger and top sellers: p50 167.07 ms, p95 211.66 ms, max 225.24 ms. |
| HTTP dashboard benchmark | PASSED | Diagnostic soak of 100 authenticated iterations: p50 253.38 ms, p95 394.17 ms, max 599.90 ms; final 20-request gate: p95 282.72 ms, max 283.79 ms. |
| Session concurrency | PASSED | Two workers sharing one session reached a post-release barrier and overlapped 515.31 ms of concurrent work, proving the session lock was not retained. |
| Complete local release gate | PASSED | 286 PHP files, legacy regressions, plugin suites, real MariaDB integrations and authenticated HTTP suites completed successfully. |
| Production `EXPLAIN ANALYZE` | BLOCKED | Production/staging DB access unavailable. |
| Approved production concurrent load | BLOCKED | No staging target or hosting resource telemetry. |

The index choices target actual predicates in the dashboard: a covering `created_at, direction, entry_type, amount` range index for monthly totals/series and `status, calculated_amount` for commission sums. Final production justification still requires production cardinality and query-plan evidence.
