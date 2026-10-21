# Proma Accounting current state audit

Date: 2026-07-19

## Scope

This audit treats `proma-accounting` version `1.2.1` as a potentially partial installation until the database registry, migrations and provider health are proven current on the target host.

## Source state inspected

| Item | Observed value |
| --- | --- |
| Git branch | `release/v1.3.4` |
| Git commit | `28434d0` |
| Core application version | `1.3.8` |
| Core display version | `V1.3.8` |
| Core database version | `2026.07.18` |
| Plugin id | `proma-accounting` |
| Plugin source version | `1.2.1` |
| Plugin requires_core | `1.3.2` |
| Plugin routes | 22 manifest routes |
| Plugin migrations | 3 manifest migrations |

## Registry/database state

Runtime database inspection was **NOT EXECUTED** in this workspace. No safe target database credentials or staging lifecycle run were available in this turn, so the real `system_plugins` row, applied `plugin_migrations`, active/inactive state and host-installed file state remain **BLOCKED** pending a host/staging run.

## Critical lifecycle findings

Before this source fix, the plugin lifecycle could report a misleading stable state:

- `install()` inserted/upserted the registry as installed before migrations and provider install completed.
- `activate()` allowed activation from `installed`/`inactive` without proving all manifest migrations were recorded as successful.
- `activate()` did not gate success on provider `healthCheck()`.
- `update()` did not restore the backup plugin files when migration/provider update failed after staged upload.

## Source fixes now present

- Install now enters `installing` first and only becomes `installed` after migrations, provider install, permissions and health check complete.
- Install failure becomes `installation_failed`, not a false installed state.
- Activation now requires complete manifest migrations and provider health before and after provider activation.
- Update now enters `updating`; failure attempts backup rollback before marking `repair_required`.
- Migration failure is surfaced as `migration_failed`.
- New lifecycle statuses are preserved by filesystem reconciliation.

## Release gate

`1.2.2` is **not packaged yet**. The next safe step is to run the real install/update/repair workflow against staging or the target host and confirm:

1. the registry row is not partial,
2. all three migrations are recorded successful,
3. plugin health passes,
4. failed-update rollback restores the previous plugin folder/version,
5. no accounting financial data is deleted or rewritten.

