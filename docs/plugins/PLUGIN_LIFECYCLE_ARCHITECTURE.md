# Plugin Lifecycle Architecture

The registry is authoritative for operational state while the filesystem and migrations are reconciled inputs.

Supported flow:

`discovered -> uploaded -> validating -> installed -> activating -> active -> deactivating -> inactive`

Update uses `updating`; uninstall uses `uninstalling`. Recoverable failures become `error`; filesystem or rollback inconsistencies become `recovery_required`.

Every transition is locked, migration statements close cursors, provider health is checked before success, and filesystem paths are normalized before they reach the UI. Legacy status strings are normalized for backward compatibility.
