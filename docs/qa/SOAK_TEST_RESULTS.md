# Soak test results

| Test | Status | Reason |
|---|---|---|
| Repeated post-recovery live probe | PASSED | Ten sequential checks remained responsive. |
| 30-minute local authenticated route soak | NOT EXECUTED | The isolated local stack was exercised by targeted benchmarks, but the required 30-minute duration was not executed. |
| Short Accounting dashboard soak | PASSED | 100 authenticated sequential requests against 100,000 ledger rows: p95 394.17 ms, max 599.90 ms, no unexpected 500/blank response. |
| Accounting dashboard concurrency soak | NOT EXECUTED | Requires a multi-worker staging web server and hosting resource metrics. |
| Production worker/memory soak | BLOCKED | Destructive load testing on production is prohibited and staging/resource metrics were not supplied. |
| Cron overlap soak | NOT EXECUTED | Static lock test passed; scheduled concurrency test remains. |

No stable claim is made from the short live probe. A valid final soak must capture latency percentiles, HTTP status distribution, PHP worker usage, DB connections, memory trend and request IDs.
