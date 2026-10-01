# Proma Pay 2.0.3 — local release verification

Date: 2026-10-01

Environment: PHP 8.2.12, MariaDB 10.4.32, isolated database `proma_v200_qa`, local PHP HTTP server and Playwright using Microsoft Edge. The V2.0.2 full Core archive was selected as the exact update baseline. No production site or customer database was used.

## Automated release gate

- PHP lint passed for 420 PHP files.
- The full configured static/unit gate passed, including legacy version consistency, financial precision, legal penalty, rate-limit/request-storm, availability, accessibility/security-focused and both plugin suites.
- The full isolated MariaDB and HTTP integration gate passed: payment/settlement, concurrency, legal workflow/costs/penalties, installment changes, guarantor/account/document handling, overdue aggregation, all four role smoke routes, update/modal behavior, and plugin failure isolation.
- Settlement concurrency test passed with two overlapping workers.
- Local accounting dashboard benchmark: 30 database iterations, p95 1.92 ms; 20 HTTP iterations, p95 181.41 ms. These measurements describe only the isolated local fixture and are not production-host performance guarantees.
- Release gate result: `RELEASE_GATE_LOCAL_QA_OK_NOT_PRODUCTION_CERTIFIED`.

## Targeted mobile browser regression

Authenticated administrator and customer sessions were tested in Microsoft Edge at 320, 360, 375, 390 and 430 CSS pixels. The 26 checks covered the administrator sidebar plus customer installment/dashboard and administrator dashboard/contracts/contract details at all five widths. Assertions verified that the sidebar menu scrolls to its final option, customer names have a usable minimum width and do not wrap as vertical single characters, cards and descriptions do not overflow horizontally, tables render as mobile cards, and no page-level JavaScript errors occur. Result: `BROWSER_V203_MOBILE_OK 26 mobile layout checks`.

## Package verification

- `RELEASE_ARCHIVES_V203_OK`; both archives extracted and the PHP files in the extracted archives passed lint.
- Full installer: `PromaPay-v2.0.3.zip`, 86,836,745 bytes (about 82.8 MiB), 2,969 entries. It includes the complete existing Core runtime, including RTL template CSS, JavaScript, fonts and images. The core archive retains all runtime styling assets.
- Differential update: `PromaPay-Update-v2.0.3.zip`, 13,149 bytes (about 12.8 KiB), 8 entries: six changed Core files plus the update manifest and guide. The exact verified baseline is `PromaPay-v2.0.2.zip`; zero migrations are needed. The six files are `assets/css/components/v2-system.css`, `config/settings.php`, `config/version.php`, `manifest.json`, `package.json` and `service-worker.js`. Thus the small update contains the actual corrected stylesheet, not an omitted UI payload.
- The update manifest file hashes and expected V2.0.2 hashes passed verification. Runtime assets are retained in the full Core archive; config/database.php, plugins, storage, uploaded files and operational data are excluded from the update.

SHA-256:

| Artifact | SHA-256 |
| --- | --- |
| `PromaPay-v2.0.3.zip` | `0bbd33f668bcb045becf2426f6e4033d939637967441a83693579c59a3c4e0a4` |
| `PromaPay-Update-v2.0.3.zip` | `a6fe76dc9c3e41e7ead22750086c8bbe1ff3e74e38a5535e682c3f8683485e40` |
| `PromaAccounting-v1.2.14.zip` | `f47496d44c524178eec539b5bb632d9b26cf28c1978da75ddf7f15dadee9ce1c` |

## Limitations

This is isolated local verification. It does not certify hosting permissions, PHP extensions, production data, custom CSS/plugins, proxy/browser caches, or any live financial state. Keep a verified backup and install the differential update from Settings → Update only on V2.0.2.
