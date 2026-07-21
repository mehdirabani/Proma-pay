# Availability release checklist

Candidate: `1.4.2-rc.1`. Stable decision: **BLOCKED**.

| Gate | Status |
|---|---|
| Canonical version detected | PASSED |
| Request ID and structured timing | PASSED |
| Accounting bounded queries | PASSED |
| Session-lock remediation | PASSED |
| Polling backoff/cancellation | PASSED |
| External connect/total timeout | PASSED |
| Cron overlap protection | PASSED |
| health/live dependency isolation | PASSED |
| health/ready safe readiness | PASSED |
| Static emergency pages | PASSED |
| Admin safe health page | PASSED |
| Accounting HTTP query/bootstrap failure isolation | PASSED |
| No runtime DDL in ordinary audited paths | PASSED |
| Exact timeout classification | BLOCKED |
| Exact IP-ban source/rule | BLOCKED |
| WAF/ModSecurity audit | BLOCKED |
| Production DB lock/slow-query audit | BLOCKED |
| Worker/hosting capacity audit | BLOCKED |
| Production load test | BLOCKED |
| Soak test | BLOCKED |
| IP-block regression | BLOCKED |
| HTTP to HTTPS redirect | FAILED |
| Critical/High release blockers zero | FAILED |
| Stable availability gate | BLOCKED |

Only an RC package may be generated. A stable ZIP, stable tag or `RELEASE_GATE_OK` is prohibited until all blocking evidence is supplied and retested.
