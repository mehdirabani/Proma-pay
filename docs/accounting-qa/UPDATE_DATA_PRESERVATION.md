# Proma Accounting update data preservation

## Goal

Plugin updates must change plugin files and schema safely without deleting accounting business data or presenting a failed partial update as successful.

## Current source guarantees

- Update starts with status `updating`.
- The stored previous status is normalized to `active`, `inactive` or `installed`; otherwise rollback resumes as `inactive`.
- Manifest version is only promoted through `PluginRegistry::updateManifest()` after migrations, provider update, optional reactivation, migration completeness and health checks pass.
- If update fails, `restoreFailedUpdate()` attempts to remove the failed candidate folder and rename the backup folder back into place.
- If rollback succeeds, the registry is restored to the previous stable plugin manifest and previous stable status.
- If rollback cannot be completed safely, the registry is marked `repair_required`.

## Accounting data

No source change in this repair path drops or truncates Proma Accounting ledger, commission, expense, bonus, payment, receipt or settings tables.

The source-level expectation is:

- existing accounting rows are preserved;
- only missing migrations are executed;
- duplicate manual ledger posts are guarded by `accounting_request_uuid`;
- update rollback affects plugin files/registry status, not accounting transaction tables.

## Verification status

Runtime data preservation on an actual host database is **NOT EXECUTED** in this workspace. Before packaging `1.2.2`, staging should compare row counts/checksums for key accounting tables before and after install/update/rollback.

