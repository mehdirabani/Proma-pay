# Proma Pay V1.4.0 Current System Audit

## Baseline

- Source baseline: commit `5814817`, Proma Pay V1.4.0.
- Architecture: server-rendered PHP MVC with PDO/MySQL and optional plugins.
- Supported runtime: PHP 8.1 or newer; QA runtime: PHP 8.2.12 and MariaDB.
- Customer authentication rule: national ID/identifier plus the last four mobile digits.

## Release-blocking findings

- Plugin states could diverge between filesystem, registry and migration state.
- Request-time schema creation masked incomplete clean-install schemas.
- Settled installments needed one authoritative payment guard.
- Duplicate contract repair lacked a safe, audited workflow.
- Profile review was all-or-nothing and avatar ownership needed explicit protection.
- Notification, chat, calendar and reversible medal state required recipient-aware persistence.
- Release archives used legacy paths and a manually maintained update inventory.

V1.4.1 addresses these findings through migration-first schema management, centralized domain services, role-aware UI, automated integration tests and a strict release gate.
