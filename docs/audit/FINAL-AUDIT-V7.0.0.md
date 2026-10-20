# Proma Pay V7.0.0 Candidate — Audit Note

Date: 2026-10-13 (Asia/Tehran)

## Scope reviewed

- Release identity and candidate packaging configuration.
- Bale provider save behavior for matching active and archived titles, the database unique-key failure path, and preservation of event mapping row identity during restore.
- Credit-risk list pagination, cached-score display, and advisory-only wording.
- Contract list summary scope, contract detail tab isolation, installment and overdue status tabs, and the collection operations board.
- Legal summary counts, review category separation, notification workspace layout, and explicit contact-field selection for the chat profile panel.
- Shared responsive styling for administrator, operator, lawyer, customer, and plugin role shells, plus desktop/tablet/mobile layout rules added for V7.
- Role-specific home-page composition for operator, lawyer, repair technician, and customer workflows.
- Affiliate plugin migration execution path for SQL-level prepared-statement commands.

## Findings addressed

1. **Bale provider title collision.** Archived configuration rows still participate in `uq_provider_title`, so filtering archived rows out of the duplicate lookup was not enough to avoid MySQL error 1062. Matching archived rows are now restored in place; active collisions return a user-facing edit instruction; a concurrent collision is caught and translated to a user-facing message.
2. **Credit-risk list did not match its intended workspace.** The previous list had no summary cards or page navigation and exposed only a plain table. V7 adds summary cards, search, pagination, score bars, and responsive rows. It does not invent a score for missing or stale saved profiles.
3. **The remaining reference screens needed a shared, responsive workbench layout.** V7 adds contract page summaries and isolated detail tabs, tightens installment and overdue filters, groups the collection queues, separates notification controls from the inbox, and presents chat contact details beside the conversation. Legal and review pages also show their own queue counts and categories.
4. **Affiliate wallet migration could fail at installation.** The plugin migration runner sent MySQL `PREPARE` and related commands through PDO's prepared-statement protocol. Those SQL-level commands now use `PDO::exec`; the migration's information-schema checks keep retries safe when earlier statements already added some columns.
5. **Role dashboards shared the same generic hierarchy.** Operator, lawyer, technician, and customer views now lead with role-specific queues and actions. The technician portal shows only assigned repairs and the technician's own expenses.

## Release evidence and limitations

- Core version: `7.0.0`; plugin API: `1.1`; release channel: `candidate`.
- Application tests and browser/UI flows: **not run**.
- Database migrations, fresh-install/upgrade flows, and external Bale/provider calls: **not run**. Affiliate migration fix was traced to the runner execution mode and reviewed against its retry-safe column checks; it was not run against an isolated MySQL instance.
- Candidate packaging integrity checks: run by the packaging scripts when the artifacts are built; these checks validate archive contents against their manifests and SHA-256 inventories, not application behavior.
- Production availability: **not certified**.

This note is a scoped candidate audit, not a claim that every subsystem or role has passed end-to-end QA. Review [V7.0.0 release notes](../releases/V7.0.0.md) for the shipped scope.
