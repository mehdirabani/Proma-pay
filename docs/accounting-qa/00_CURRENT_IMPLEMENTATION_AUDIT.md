# Proma Accounting current implementation audit

Date: 2026-07-18

## Version inventory

- Core manifest version: `v1.3.8`
- Accounting plugin ID: `proma-accounting`
- Accounting plugin version inspected: `1.2.1`
- Plugin API version: `1.0`
- Required Core version: `1.3.2`
- Required PHP version: `>=8.1`

## Source inventory

- Declared route count: 22
- GET route count: 10
- POST route count: 12
- Controller count: 1
- Service count: 7
- Migration count: 3
- JavaScript file count: 1
- CSS file count: 2
- Primary view count: 10
- Shared Accounting modal component: `views/components/ui.php`

## Important findings

- `plugin/accounting/ledger/{userId}` was declared before `plugin/accounting/ledger/post`.
- Core plugin route dispatch previously returned a raw 405 as soon as the first matching route had a different HTTP method.
- Because `ledger/{userId}` matches the literal string `post`, a valid `POST plugin/accounting/ledger/post` was stopped before the real POST route could run.
- Direct browser access to `plugin/accounting/ledger/post` by GET also needed to resolve to the exact POST-only route and render the Core 405 page.

## Environment limits

- Current database schema state: NOT EXECUTED
- Browser reproduction against a running authenticated instance: NOT EXECUTED
- Production logs: NOT EXECUTED
- Plugin registry database state: NOT EXECUTED
- Full release gate: BLOCKED

No final package was produced from this audit because the database, browser, security, responsive, lifecycle and release-gate tests were not executed.
