# Performance baseline — operational prompt follow-up

Date: 2026-10-01

Source version: 2.0.3
Status: **focused financial integration verified; full numeric performance baseline not yet certified**.

## Available measurement points

`core/RequestTelemetry.php` records request duration, HTTP status, query count, cumulative query duration, query errors, slowest normalized query fingerprint, external calls, plugin IDs, recent spans, and peak memory. Normal successful requests are sampled (default 5%); slow requests and errors are retained. Request logs are bounded by `PROMA_REQUEST_LOG_MAX_BYTES` and written to `storage/logs/request-YYYY-MM-DD.jsonl`.

PHP 8.2.12 is available through `C:\xampp\php\php.exe`; MariaDB 10.4.32 was run against a new, isolated QA datadir and database (`proma_qa_v204`, port 33387). Focused payment-allocation, legal-penalty, legal-cost, installment-edit, and concurrency integration tests passed. No seeded 1k/5k/25k workload or browser timing run has been performed, so p50/p95/p99, query-growth and Core Web Vitals remain **unmeasured**; numeric claims here would be fabricated. No test used production/customer data.

## Required baseline matrix

Capture at least 30 warm requests per route at desktop and mobile viewport, with telemetry enabled and the same seeded dataset. Report p50/p95/p99 server duration, p95 query count/time, response bytes, peak memory, HTTP errors, and browser LCP/INP/CLS.

| Flow | Route / interaction | Dataset dimensions | p50 | p95 | p99 | Queries p95 | SQL ms p95 | Response bytes | LCP / INP / CLS | Evidence |
| --- | --- | ---: | ---: | ---: | ---: | ---: | ---: | ---: | --- | --- |
| Login and dashboard | `auth/login`, `dashboard` | 1k customers / 5k contracts | pending | pending | pending | pending | pending | pending | pending | Workload/browser run not performed |
| Customer list + detail | `customers`, `customers/show/{id}` | 1k customers / 5k contracts | pending | pending | pending | pending | pending | pending | pending | Workload/browser run not performed |
| Contract list + detail | `contracts`, `contracts/show/{id}` | 5k contracts / 25k installments | pending | pending | pending | pending | pending | pending | pending | Workload/browser run not performed |
| Installments + overdue filters | `installments`, `overdue` | 25k installments / 5k payments | pending | pending | pending | pending | pending | pending | pending | Workload/browser run not performed |
| Legal queue + case details | `legal`, `legal/show/{id}` | 500 cases / 2k events | pending | pending | pending | pending | pending | pending | pending | Workload/browser run not performed |
| Multi-installment quote | `contracts/settlementQuote/{id}` | 12 installments / 100 payments | pending | pending | pending | pending | pending | pending | N/A | Correctness integration passed; timing not measured |

## Current code-review targets (not measured outcomes)

- `InstallmentFinancialStateService::statesForRows()` batches payment and legal-fact reads by row/contract sets, avoiding one payment query per installment for callers that use the batch method.
- Some detail/list controllers still assemble related data with separate model calls; validate query count using telemetry before changing them.
- `RequestTelemetry` keeps only the slowest query fingerprint per request; use aggregate log analysis or a dedicated profiler for endpoint-level query fingerprints if optimization decisions require ranking repeated queries.
- The baseline is intentionally incomplete until the matrix has real p50/p95/p99 observations. Attachment 2's performance gate remains open.

## Acceptance targets to adopt for the first measured comparison

- No unexpected HTTP 5xx in the 30-request sample.
- No N+1 growth proportional to displayed row count.
- No single page-load request storm; filter typing must debounce and cancel stale requests.
- p95 route latency and browser vitals must be reported against the pre-change baseline before claiming an improvement.
- Each optimization must preserve authorization, result totals, sort/filter behavior, and financial calculation versions.
