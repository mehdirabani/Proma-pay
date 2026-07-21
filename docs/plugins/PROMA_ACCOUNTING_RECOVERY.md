# Proma Accounting Recovery

1. Open Plugins and run reconciliation when the registry and filesystem disagree.
2. Use health check before activation.
3. If state is `error`, inspect the safe tracking log and retry repair.
4. If state is `recovery_required`, restore the plugin files from the matching plugin ZIP and run repair.
5. Removal with data preservation deletes plugin files/registration only.
6. Full purge is a separate explicit operation and must never be inferred from normal uninstall.

V1.2.4 keeps ledger and commission data during ordinary reinstall/update recovery.
