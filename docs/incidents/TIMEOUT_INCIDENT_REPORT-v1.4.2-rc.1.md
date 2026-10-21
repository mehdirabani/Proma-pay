# Timeout incident report - V1.4.2-rc.1

Report date: 2026-07-21. Timezone: Asia/Tehran. Release channel: RC.

## 1. Original Core version

`1.4.1` - PASSED (read from the canonical local release baseline).

## 2. Final Core version

`1.4.2-rc.1` - PASSED. Stable `1.4.2` is not published.

## 3. Incident timeline

Initial probes to `/`, login, Accounting dashboard, live and ready returned no HTTP response for about 10 seconds. A control Internet request returned 200. Later TCP/TLS/IPv4 probes recovered, five external nodes returned 200, and ten repeated live probes stayed healthy. Exact server-side timestamps remain BLOCKED.

## 4. Affected routes

`/`, `auth/login`, `plugin/accounting/dashboard`, `health/live`, and `health/ready` were observed failing during the initial probe - FAILED at that time.

## 5. Affected IPs

Client public IP was not supplied - BLOCKED. Telemetry masks IPs before writing.

## 6. Global or IP-specific outage

BLOCKED. The initial local failure and later multi-vantage recovery do not prove either a global outage or a single-IP ban.

## 7. Exact blocking component

BLOCKED. Hosting firewall and service logs are required.

## 8. Firewall/WAF rule or jail

BLOCKED. No CSF/LFD/Fail2ban/WAF rule ID or jail evidence was available.

## 9. ModSecurity rule ID

BLOCKED. No ModSecurity audit log was available.

## 10. DNS findings

PASSED. A resolves to `185.78.22.44`; authoritative nameservers are `irns1.netafraz.com` and `irns2.netafraz.com`; no AAAA was advertised.

## 11. IPv4 findings

PASSED after recovery. Forced IPv4 HTTPS returned 200 in about 0.21 seconds.

## 12. IPv6 findings

PASSED for DNS consistency: no IPv6 route is advertised because no AAAA exists. End-to-end IPv6 application testing was NOT EXECUTED.

## 13. TLS findings

PASSED after recovery. TCP 443 and TLS negotiation succeeded.

## 14. ISP and modem findings

BLOCKED. Affected ISP, modem model, public IP and route trace were not supplied.

## 15. Accounting dashboard findings

PASSED locally. Removed an unused account preview, consolidated aggregates, bounded monthly reads, isolated widgets and added a degraded 200 response with request ID.

## 16. Runtime DDL findings

PASSED for audited ordinary request paths. The new index change exists only in a versioned plugin migration.

## 17. Database lock findings

Local audit PASSED; production incident state is BLOCKED because processlist, InnoDB status and lock-wait evidence were unavailable.

## 18. Slow-query findings

PASSED locally. A 100,000-row ledger benchmark used the new covering index; final repository p95 was 211.66 ms.

## 19. Session-lock findings

PASSED. Accounting releases the session lock before widgets; two workers overlapped 515.31 ms of post-release work.

## 20. PHP worker findings

BLOCKED. Production handler, queue, worker count, memory and Entry Process metrics were unavailable.

## 21. Web-server findings

HTTPS identified nginx after recovery - PASSED. HTTP port 80 served content instead of a redirect - FAILED.

## 22. Hosting-limit findings

BLOCKED. CPU, RAM, I/O, Entry Process and process-limit reports were unavailable.

## 23. External-call findings

PASSED for audited clients. Zibal, IPPanel, OpenRouter and Zarinpal now use bounded connect/total timeouts and TLS verification. Provider outage staging is NOT EXECUTED.

## 24. Cron overlap findings

Static and unit lock checks PASSED. Scheduled multi-process hosting execution is NOT EXECUTED.

## 25. Retry-storm findings

PASSED. Fixed overlapping timers were replaced with adaptive non-overlapping polling, AbortController, visibility pause and exponential backoff.

## 26. Root cause

Application-side availability amplifiers were identified and remediated - PASSED. The exact infrastructure cause of the no-HTTP window and reported IP block remains BLOCKED and must not be inferred.

## 27. Code changes

Request telemetry, bounded DB connection timeout, session release, Accounting query isolation, health UI, controlled 429, Cron lock, external-call timeouts and adaptive polling - PASSED.

## 28. Database and index changes

Plugin migration `2026_07_21_accounting_dashboard_performance.sql` adds two guarded covering indexes - PASSED and idempotency-tested.

## 29. Server configuration changes

No production server configuration was changed - NOT EXECUTED.

## 30. Security-rule changes

No WAF/firewall rule was disabled or excluded - NOT EXECUTED. Application login lockouts now return controlled 429 - PASSED.

## 31. Why the correction remains secure

PASSED locally: no broad allowlist, WAF disablement, secret logging or TLS bypass was introduced; routes retain authorization and IPs are masked.

## 32. Load-test results

Sequential Accounting diagnostics PASSED: 100 requests, p95 394.17 ms, max 599.90 ms. Approved multi-worker hosting load is BLOCKED.

## 33. Soak-test results

Short 100-request Accounting soak PASSED. Required 30-60 minute multi-worker soak is NOT EXECUTED; production soak is BLOCKED.

## 34. IP-block regression result

BLOCKED. Approved staging security rules and firewall logs were unavailable.

## 35. Tests executed

PHP lint, legacy Core/plugin suites, MariaDB integrations, role smoke, health endpoints, session concurrency, Accounting benchmarks and failure isolation - PASSED.

## 36. Tests passed

Complete local release gate - PASSED.

## 37. Tests failed

HTTP-to-HTTPS redirect - FAILED. Initial production availability sample - FAILED at incident time.

## 38. Tests blocked

Exact timeout/IP-ban classification, security rule, worker capacity, production lock audit, approved load/soak and IP-block regression - BLOCKED.

## 39. Open defects

Critical infrastructure classification and High WAF/worker/database evidence items remain BLOCKED. See `docs/qa/TIMEOUT_BUG_REGISTER.md`.

## 40. Release-gate result

Local RC gate PASSED. Stable availability gate BLOCKED. Stable ZIP/tag/publication is prohibited.

## 41. Deployment steps

Back up database/files, deploy only to staging, install the Accounting RC separately, run its migration, verify live/ready and system health, execute authenticated role smoke, then collect hosting evidence. Status: NOT EXECUTED on production.

## 42. Rollback steps

Restore the pre-deployment code snapshot and database backup; disable Accounting only if its migration or boot fails; preserve incident/telemetry logs before rollback. Status: NOT EXECUTED on production.

## 43. Monitoring plan

Monitor request IDs, critical/slow request records, DB duration, external failures, queue depth, worker/Entry Process usage, WAF actions and affected masked IPs. Production configuration is BLOCKED pending hosting access.

## 44. Known limitations

Static emergency pages cannot render when TCP/HTTP is unavailable; public readiness intentionally returns no dependency details; exact infrastructure root cause and stable acceptance remain BLOCKED.
