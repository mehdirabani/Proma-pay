# Timeout investigation status

## Evidence

The reported production request could not be reproduced because this workspace has no isolated MySQL/MariaDB test database, production logs, PHP-FPM logs, web-server logs, or staging URL. A local PHP CLI is available for static and HTTP fallback checks, but it cannot prove the production timeout layer or financial commit state. Therefore that evidence is **NOT EXECUTED**.

## Source findings

1. Accounting hook listeners execute synchronously in `AccountingServiceProvider`; they can add database work to Core financial requests before a response is returned. This is a timeout risk, but is not proven as the reported incident's cause.
2. The former ledger idempotency fallback was derived from the CSRF token instead of a mandatory per-submission request UUID. It could not detect reuse of one request identifier with altered financial data.
3. Concurrent account creation broadly swallowed every database exception, concealing schema/connection/lock failures.

## Mitigation implemented

`2026_07_18_accounting_request_integrity.sql` adds durable request records. Each manual financial POST has a UUID and request hash; duplicate identical requests return the original result, altered reuses are rejected, and an unresolved processing state produces `ACCOUNTING_STATE_UNCERTAIN`. Account creation now uses a duplicate-key-safe insert without suppressing unexpected errors.

## Required next evidence

Run the migration in isolated MySQL/MariaDB staging, correlate web/PHP/database logs with a request ID, inject a post-commit delay, and query `accounting_requests` plus `accounting_ledger_entries` before any retry.
