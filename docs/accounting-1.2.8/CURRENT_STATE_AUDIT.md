# Proma Accounting 1.2.8 — current state audit

## Local evidence

- Core checkout: `codex/release-v1.4.5`, commit `ed9ef29`.
- Local manifest before this release: Proma Accounting `1.2.7`.
- Five plugin migrations are present; the 1.2.8 referral migration is added by this release.
- Manifest permissions now follow the core contract `plugin.<plugin-id>.<permission>` and the core requirement uses the supported comparator form (`>=1.3.2`).
- Registry/database state and protected production logs are not available in this workspace, therefore remote installation state is **NOT EXECUTED**.

## Recovery conclusion

When the runtime directory is manually removed, the registry can retain a recovery/error row. Re-uploading the same plugin ZIP is the safe recovery path; the lifecycle must never purge financial tables. The release includes explicit state/recovery documentation and keeps install failure distinct from a successful install.

## Release scope

1. Customer referral code/attribution and separate referral commission ledger types.
2. Idempotent commission posting and reversal primitives.
3. Analytics/salary features from 1.2.7 retained.
4. Migration and static validation for 1.2.8.

The customer-referral tables deliberately use a non-sequential random code, one attribution per referred customer, a unique source event UUID, holding-period fields and rule snapshots. Shared IP is not used as an automatic rejection criterion. Contract-modal wiring, payout worker execution and production event delivery require the host application's customer/contract schema and staging database; they are **NOT EXECUTED** in this isolated checkout and must pass the release gate before enabling them in production.

Production install, migration, WAF and long-running request tests remain **BLOCKED/NOT EXECUTED** until the host can be tested.
