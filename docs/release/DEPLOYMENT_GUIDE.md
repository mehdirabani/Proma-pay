# V1.4.1 Deployment Guide

1. Verify `SHA256SUMS.txt` beside the downloaded archives.
2. Create a database and file backup.
3. For a fresh install, extract `PromaPay-v1.4.1.zip` into an empty web root and run `install.php`.
4. For V1.4.0 update, upload `PromaPay-Update-v1.4.1.zip` through the update screen.
5. Confirm the random update code and allow the migration to finish.
6. Install/update `PromaAccounting-v1.2.4.zip` from Plugins; run health check before activation.
7. Confirm Settings reports V1.4.1 and verify login, one contract, one installment and one notification.

Do not overwrite `config/database.php`, `storage`, uploads or installed plugin data manually.
