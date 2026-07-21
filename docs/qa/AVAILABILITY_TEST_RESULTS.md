# Availability test results

Test date: 2026-07-21. Candidate: Core `1.4.2-rc.1`, Accounting `1.2.5-rc.1`.

| Test | Status | Result |
|---|---|---|
| Initial production route probe | FAILED | `/`, login, Accounting dashboard, live and ready returned curl code 000 after about 10 seconds. |
| Control Internet request | PASSED | `https://example.com` returned 200. |
| TCP 443 after recovery | PASSED | Connection succeeded. |
| Forced IPv4 HTTPS | PASSED | 200 in about 0.21 seconds. |
| Five-node Check-Host | PASSED | All nodes returned 200 in 0.51 to 0.76 seconds. |
| Ten repeated `/health/live` requests after recovery | PASSED | All returned 200 in 0.15 to 0.21 seconds. |
| Health route dependency isolation | PASSED | Static test confirms health runs before plugin boot and without session startup. |
| Local availability source tests | PASSED | Seven new availability/performance/security suites passed. |
| Authenticated role HTTP smoke | PASSED | Admin, operator, lawyer and customer routes returned expected content and access controls. |
| Administrator system-health route | PASSED | Authenticated admin smoke returned 200 and the route remained restricted to administrators. |
| Accounting query-failure isolation | PASSED | Temporarily removing the commission table produced a 200 degraded dashboard with a request ID; the table was restored. |
| Accounting bootstrap-failure isolation | PASSED | An intentionally invalid Accounting manifest did not break the Core dashboard or dependency-free health/live endpoint; registry state was restored. |
| Accounting 100-request diagnostic | PASSED | All 100 returned 200; p95 394.17 ms. |
| Production recurrence classification | BLOCKED | Exact timestamp and server logs unavailable. |

Check-Host evidence: https://check-host.net/check-report/4505482fk743

This result must not be interpreted as a stable production pass; the intermittent failure was observed and its infrastructure source remains unknown.
