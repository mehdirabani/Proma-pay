# Proma Pay V1.4.1 Final QA Results

Test date: 2026-07-21

Environment: PHP 8.2.12, MariaDB on an isolated local port, Chromium/Edge print engine, Windows desktop. Production data and production secrets were not used.

## Automated Gate

| Test group | Result | Evidence |
| --- | --- | --- |
| PHP syntax | PASSED | 268 PHP files linted with zero syntax errors |
| Static and unit regression | PASSED | 28 suites covering Core V1.2.6-V1.4.1, Proma Accounting and Proma Zarinpal |
| Database and HTTP integration | PASSED | 7 suites, including finance, files, custom installments, contract workflows, V1.4.1 blockers and role smoke |
| Customer authentication policy | PASSED | National ID username and last four mobile digits password retained |
| Role authorization | PASSED | Admin, operator, lawyer and customer routes returned healthy or controlled 403 responses |

The strict command `tools/release-gate.php` completed with `RELEASE_GATE_V141_OK` after the stable metadata synchronization.

## Database Compatibility

Fresh installation was imported into an empty database and produced 62 application tables with no seeded operational users. The V1.4.1 blocker integration suite passed on that database.

The V1.4.0 baseline schema was imported separately. The update migration inventory was applied twice to prove idempotency. The resulting table inventory matched the fresh installation at 62 tables, and the V1.4.1 blocker integration suite passed. Repair migrations for backup logs, ecommerce and contract document tables are intentionally included in the differential update package for installations that missed an older migration.

## Financial and Plugin Integrity

Settled installments are rejected server-side, grouped payment allocation uses the current payable amount, and duplicate-contract repair refuses destructive changes when protected payment history exists. Proma Accounting lifecycle state, migration cursor closure and failed-deactivation recovery are covered by automated integration tests.

## Browser and Print QA

Admin, operator, lawyer and customer pages were exercised at desktop 1440 x 900 and mobile 390 x 844. No fatal PHP output or document-level horizontal overflow was observed. Unauthorized routes produced controlled application responses.

A real contract containing two products and eight installments was printed through Chromium/Edge. The result was exactly one A4 portrait page (594.96 x 841.92 points), with no clipping or incoherent overlap. The print stylesheet contains no page-level `zoom` or `transform: scale` workaround.

## Release Artifacts

Archive construction, extraction, internal hash validation, path traversal checks, extracted PHP lint, fresh-package installation and plugin uninstall/reinstall verification are executed after this source report is committed. The final distributed copy of this report is regenerated only after those checks pass.

Known limitation: external production payment gateways and remote SMS/email providers were not charged or contacted during local QA. Their protocol adapters and sandbox/static suites passed, but final live-provider acceptance remains an operational deployment check.
