# Proma Accounting plugin update bug register

| ID | Severity | Status | Affected file | Finding | Fix / evidence |
| --- | --- | --- | --- | --- | --- |
| ACC-UPDATE-CURSOR-001 | Critical | FIXED IN SOURCE / PARTIALLY TESTED | `core/Model.php` | `fetch`, `fetchAll` and `execute` did not close PDOStatement cursors. | Added `try/finally closeCursor()`. Static regression PASSED. Real MySQL update NOT EXECUTED. |
| ACC-UPDATE-PDO-001 | Critical | FIXED IN SOURCE / BLOCKED | plugin update lifecycle | Reported SQLSTATE 2014 is consistent with unclosed helper cursors during update/migration/registry queries. | Cursor lifecycle fixed centrally. Exact runtime statement remains BLOCKED without staging DB/update package evidence. |
| ACC-UPDATE-MIGRATION-001 | High | FIXED IN SOURCE / PARTIALLY TESTED | `helpers/ScriptUpdateService.php` | Migration statements were drained but not closed from a `finally` path. | Added `finally closeCursor()` around migration statement draining. Static regression PASSED. |
| ACC-UPDATE-ERROR-UI-001 | High | FIXED IN SOURCE / PARTIALLY TESTED | `controllers/PluginsController.php`, `views/plugins/index.php` | Raw PDO/SQLSTATE text could be shown through flash messages or plugin `last_error`. | Plugin actions now log technical exceptions and show safe request-ID messages; raw `last_error` is hidden. Static regression PASSED. |
| ACC-PLUGIN-WARNING-UI-001 | Medium | FIXED IN SOURCE / NOT EXECUTED | `views/plugins/index.php`, `assets/css/components/forms.css` | Security warning text was too close to card borders and mixed text could wrap poorly. | Added structured warning content, padding, icon, and `overflow-wrap`. Browser responsive tests NOT EXECUTED. |
