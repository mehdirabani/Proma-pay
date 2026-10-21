# Proma Accounting update SQLSTATE 2014 audit

Date: 2026-07-19

## Version inventory

- Core version inspected: `1.3.8` / `V1.3.8`
- Core database version inspected: `2026.07.18`
- Plugin inspected: `proma-accounting`
- Plugin version inspected: `1.2.1`
- Reported update path: `1.2.0 -> 1.2.1`
- Update route: `POST index.php?route=plugins/update/{pluginId}`

## PDO configuration

`core/Model.php` configures:

- `PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION`
- `PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC`
- `PDO::ATTR_EMULATE_PREPARES => false`
- `PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => true` when the constant exists

Buffered query support already existed. The fix did not rely on buffering alone.

## Source root cause

The database abstraction left cursors open:

- `Model::fetch()` executed a statement, read one row, and returned without `closeCursor()`.
- `Model::fetchAll()` returned fetched rows without closing the cursor.
- `Model::execute()` returned `rowCount()` without closing the cursor.
- `BackupService` and `SystemResetService` also used direct `PDO::query()` reads without closing cursors.

These patterns are compatible with the reported MySQL/PDO 2014 failure when the update flow performs another query on the same PDO connection during plugin discovery, migration checks, migration registration, registry updates, health checks, or audit logging.

## Update flow inspected

1. `PluginsController::update()`
2. `PluginManager::update()`
3. `PluginManifest::read()`
4. `PluginManager::runMigrations()`
5. `PluginRegistry::migrationDone()`
6. migration SQL execution
7. `PluginRegistry::recordMigration()`
8. provider `update()`
9. `PluginRegistry::updateManifest()`
10. `PluginRegistry::setStatus()`
11. audit logging

The migration list is built in memory before execution. Migration execution uses `PDO::exec()` for SQL statements; registry reads and writes go through `Model` helpers, so unclosed helper cursors were the main source-level risk.

## Fix

- `Model::fetch()`, `Model::fetchAll()` and `Model::execute()` now close cursors in `finally`.
- Direct query reads in `BackupService` and `SystemResetService` now close cursors.
- `ScriptUpdateService` now closes migration statement cursors in `finally` after draining rowsets.
- Plugin-management actions now log technical exceptions and show safe Persian flash messages with a request ID.
- Plugin manager no longer renders raw `last_error` text on the UI.
- Plugin security warning spacing and wrapping were corrected.

## Reproduction status

- Exact SQLSTATE 2014 reproduction on a real previous-version Accounting update: NOT EXECUTED
- Exact active production statement: BLOCKED
- Source-level unsafe cursor patterns identified: PASSED
- Cursor lifecycle source regression: PASSED
- Protected technical logging source check: PASSED
- Safe plugin manager UI source check: PASSED

## Remaining release blockers

- Real MySQL/MariaDB update from `1.2.0` to `1.2.1`: NOT EXECUTED
- Data preservation comparison: NOT EXECUTED
- Balance reconciliation after update: NOT EXECUTED
- Failure rollback test: NOT EXECUTED
- Responsive browser checks: NOT EXECUTED
- Full release gate: BLOCKED
