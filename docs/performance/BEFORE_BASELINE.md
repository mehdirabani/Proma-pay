# QA HTTP baseline — 2026-10-03

This is a measured **local QA baseline**, not a production load result. PHP 8.2.12 built-in server and MariaDB 10.4.32 were used with the disposable `proma_qa_v204` database. After seeding QA roles, the database held approximately 143 users, 52 contracts, 99 installments and 83 payments. Requests were sequential, authenticated with QA accounts, warmed once per route, then repeated 30 times. Telemetry sampling was set to 100%. The request flood budget was raised only in this QA server process so the sampling run would measure pages instead of HTTP 429 responses.

The first run, with the normal flood budget, received HTTP 429 and was discarded. The corrected run had 30 HTTP 200 responses on every route below. `plugin/accounting` returned 404; the actual accounting route is `plugin/accounting/dashboard`, measured separately after correcting the route.

| Route | Wall p50 / p95 / p99 ms | PHP p95 ms | SQL count p95 | SQL p95 ms | HTML bytes p50 | Peak memory p95 MiB |
| --- | ---: | ---: | ---: | ---: | ---: | ---: |
| Admin dashboard | 98.23 / 118.10 / 121.29 | 110.51 | 71 | 74.21 | 53,691 | 4 |
| Customers | 413.17 / 439.99 / 453.45 | 431.19 | 1,100 | 359.68 | 288,963 | 4 |
| Customer detail | 102.11 / 121.17 / 136.90 | 115.65 | 53 | 77.44 | 45,318 | 6 |
| Contracts | 92.06 / 115.45 / 116.05 | 107.42 | 48 | 66.39 | 374,834 | 6 |
| Contract detail | 122.42 / 146.48 / 147.80 | 139.72 | 136 | 88.78 | 111,989 | 6 |
| Installments | 82.52 / 104.47 / 104.67 | 98.58 | 48 | 60.76 | 171,092 | 6 |
| Payments | 71.32 / 97.36 / 103.80 | 90.46 | 39 | 56.54 | 284,544 | 4 |
| Overdue | 89.80 / 103.28 / 103.95 | 97.17 | 65 | 58.52 | 160,747 | 6 |
| Legal | 67.68 / 81.44 / 82.12 | 76.12 | 40 | 45.51 | 85,948 | 4 |
| Calendar | 65.72 / 89.28 / 132.17 | 83.43 | 42 | 53.68 | 40,681 | 4 |
| Review | 57.44 / 78.80 / 79.66 | 74.09 | 41 | 47.76 | 31,259 | 4 |

The customer row is the **before** measurement for the customer-list optimization in this task. The other rows are the working-tree baseline at that point. Subsequent separate 30-request runs measured the accounting dashboard at wall p95 94.33 ms / PHP p95 88.01 ms / SQL count p95 47, and the customer dashboard at wall p95 110.80 ms / PHP p95 105.56 ms / SQL count p95 29.

Machine-readable evidence: `QA_HTTP_BASELINE.json`, `QA_SERVER_BASELINE.json`, `QA_ADDITIONAL_BASELINE.json`, and `QA_ADDITIONAL_SERVER_BASELINE.json` in this directory. These files contain only aggregate measurements and route names.

Not measured: TTFB, JS/CSS transfer sizes, DOM nodes, LCP, CLS, INP, browser device matrix, concurrent load, or 1k/5k/25k production-sized data. The p99 from 30 samples is only the observed maximum and is not a stable tail-latency estimate. A full release baseline remains open.
