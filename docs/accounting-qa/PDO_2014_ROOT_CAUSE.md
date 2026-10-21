# PDO 2014 root cause audit

## Reported error

The reported failure is consistent with:

```text
SQLSTATE[HY000]: General error: 2014 Cannot execute queries while other unbuffered queries are active
```

## Root cause from source inspection

The update/install path uses multiple sequential database statements: plugin registry reads/writes, migration execution and helper services. If a previous `PDOStatement` cursor remains open, MySQL can reject the next query with error 2014.

The risky pattern was present in shared helpers:

- `core/Model::fetch()`
- `core/Model::fetchAll()`
- `core/Model::execute()`
- direct PDO calls in backup/reset/update helpers

## Fix now present

The shared model helpers now close statement cursors in `finally` blocks. The update-related direct PDO calls in backup/reset/script-update helpers were also closed explicitly.

## Verification status

- Static regression: `tests/static_accounting_update_2014.php` passed.
- PHP lint for modified lifecycle files passed.
- Real MySQL install/update reproduction: **NOT EXECUTED**.
- Browser/admin panel update flow: **NOT EXECUTED**.

Until the staging/host update path is run, this is a source-level fix with static verification, not a full runtime proof.

