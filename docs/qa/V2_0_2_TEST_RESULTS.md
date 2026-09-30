# Proma Pay 2.0.2 — local release verification

Date: 2026-09-30

Environment: PHP 8.2.12, isolated MariaDB 10.4.32 database `proma_v200_qa`, local PHP HTTP server, Playwright with Microsoft Edge. The 2.0.1 full Core archive was selected as the update baseline. No production site or customer database was used.

## Automated release gate

- PHP syntax check: 420 files passed.
- All configured static/unit checks passed, including version/cache validation, rate-limit and request-storm checks, settlement and legal penalty tests, financial precision, accessibility/security-focused checks and plugin suites.
- MariaDB and HTTP integration suite passed, including financial/settlement workflows, legal cases and costs, installment changes, guarantor/document regression, overdue aggregation, role smoke checks, plugin failure isolation, and accounting dashboards.
- Installment settlement concurrency test: 2 workers overlapped successfully.
- Accounting dashboard DB benchmark: 30 iterations; p95 2.23 ms in the isolated local fixture. Accounting HTTP benchmark: 20 iterations; p95 83.84 ms locally. These are test-fixture measurements, not production-host performance guarantees.
- Release gate result: `RELEASE_GATE_LOCAL_QA_OK_NOT_PRODUCTION_CERTIFIED`.

## Browser regression

Authenticated admin, operator, lawyer and customer sessions were tested at 320, 375, 768, 1024 and 1440 pixels. All 20 role/viewport combinations had no document-width overflow or oversized navigation icon. Mobile navigation open, Escape close, inert state and focus return passed.

For a contract with guarantors and a contract without guarantors, the browser exercised every available tab at 375, 768 and 1440 pixels: 42 tab/viewport states passed, with only the selected tab content visible. All four header quick actions (installments and settlement on both contracts) opened their matching panels. Payment deep links selected payments, and installment actions were visible without horizontal page scrolling. Settings, contract template, legal, medals, contracts and installments returned HTTP 200 at mobile width without horizontal overflow. The final browser run recorded zero page/console errors; the browser's automatic `/favicon.ico` probe was excluded from the page-error assertion.

## Package gate

The full Core installer and 2.0.1-to-2.0.2 differential update were built separately and passed `tools/verify-release.php --local` (`RELEASE_ARCHIVES_V202_OK`). The Core archive has 2,969 entries and is 86,836,470 bytes (about 82.8 MiB); it contains the existing complete template/runtime asset set. The differential archive has 22 ZIP entries (20 changed Core files plus update metadata/guide) and is 188,457 bytes (about 184 KiB). It includes the redesigned `assets/css/components/v2-system.css` as well as the contract views and editor CSP correction. The exact SHA-256 digests are in `SHA256SUMS.txt` and below:

| Artifact | SHA-256 |
| --- | --- |
| `PromaPay-v2.0.2.zip` | `98d8826663aef9d073f868b046d0e9edd65d100dac93adf8db9149c2e1e10bbc` |
| `PromaPay-Update-v2.0.2.zip` | `e148c730a96251c276df96339ab7ae1176d372bcb22e776ca3b21cdffce0b8a7` |

Build artifacts exclude tests, screenshots, logs, storage, customer uploads, database configuration and local QA fixtures.

## Limitations

This is isolated local verification. It cannot certify the installed site's hosting configuration, PHP extensions, file permissions, plugin customization, proxy/cache rules or live financial data. Preserve a current backup and follow the included update guide before updating production.
