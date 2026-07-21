# Timeout bug register

| ID | Severity | Route/component | Root cause / blocker | Files or configuration | Tests | Status |
|---|---|---|---|---|---|---|
| AVAIL-TIMEOUT-001 | Critical | All routes including health/live | Exact server/network component unknown; first probe received no HTTP | Incident docs, telemetry | Availability probes | BLOCKED |
| AVAIL-IPBLOCK-001 | Critical | Client access | Affected IP and ban logs unavailable | None | IP block regression | BLOCKED |
| NET-IPV6-001 | Medium | DNS | No AAAA exists; no broken IPv6 advertisement found | None | DNS audit | PASSED |
| WAF-FALSEPOSITIVE-001 | High | WAF/ModSecurity | Rule ID and audit log unavailable | None | WAF audit | BLOCKED |
| PHP-WORKER-001 | High | PHP/web server | Worker metrics unavailable | Request telemetry | Capacity audit | BLOCKED |
| DB-LOCK-001 | High | MariaDB | Incident process/lock state unavailable | Query telemetry | DB audit | BLOCKED |
| DB-SLOWQUERY-001 | High | Accounting dashboard | Repeated aggregates and unbounded ledger total | Accounting repository and migration | Bounded query test | PASSED |
| SESSION-LOCK-001 | High | Accounting dashboard | Session remained open during all widgets | `core/Auth.php`, Accounting controller | Session-lock test | PASSED |
| ACC-DASHBOARD-001 | High | plugin/accounting/dashboard | Heavy unused query and no widget isolation | Accounting controller/repository/view | Static and real HTTP plugin isolation tests | PASSED |
| EXT-TIMEOUT-001 | High | Zibal/IPPanel/OpenRouter/Zarinpal | Long or missing connection bounds | HTTP clients | External timeout test | PASSED |
| CRON-OVERLAP-001 | High | outbox/calendar cron | No non-blocking overlap lock | `core/CronLock.php`, cron controller | Cron lock static test | PASSED |
| RETRY-STORM-001 | High | Chat/notification polling | Fixed timers, hidden tabs and overlap | `assets/js/app.js` | Polling test | PASSED |
| RATE-LIMIT-001 | Medium | Login | Lock response redirected instead of controlled 429 | Auth controller | Rate-limit test | PASSED |
| HEALTH-001 | Medium | Operations | No safe consolidated admin diagnostics | System health controller/view | PHP lint, live/ready HTTP and admin role smoke | PASSED |
| HTTPS-REDIRECT-001 | Medium | Port 80 | HTTP served content without redirect | Hosting/proxy configuration | Network audit | FAILED |

No record is removed after remediation. Production retest fields remain BLOCKED where infrastructure evidence is absent.
