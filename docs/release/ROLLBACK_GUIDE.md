# V1.4.1 Rollback Guide

1. Put the application into maintenance mode.
2. Restore the pre-update database backup.
3. Restore the pre-update web-root backup while preserving the matching `config/database.php`.
4. Restore plugin files matching the restored registry/migration state.
5. Clear application cache and restart PHP workers if available.
6. Run login, contract and installment smoke checks before reopening traffic.

Do not roll back only PHP files after the V1.4.1 migration; database and files must be restored as one consistent snapshot.
