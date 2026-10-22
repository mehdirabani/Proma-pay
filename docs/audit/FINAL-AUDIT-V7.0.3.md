# Proma Pay 7.0.3 Pre-release Audit

> Historical audit of the V7.0.3 release tree. Its Laravel and store inventory describes that earlier snapshot, not the V7.0.4 source; see [the removal baseline and execution report](ECOMMERCE_LARAVEL_REMOVAL_BASELINE.md).

Date: 2026-10-21 (Asia/Tehran)

## Release identity and scope

- `config/version.php`, `manifest.json`, and `package.json` identify Core version 7.0.3. The configured release channel remains `candidate`.
- The release tree contains the legacy Core application at repository root and the separate Laravel application under `laravel/`; those applications do not share a runtime or plugin loader.
- The separate Laravel application was not independently built or tested as part of this legacy Core 7.0.3 release.
- All six first-party plugin source trees and manifests are included: PromaAccounting 1.2.28, PromaAffiliate 1.1.1, PromaInventoryPartners 1.1.2, PromaRepair 1.2.13, PromaSignConnect 1.3.1, and PromaZarinpal 1.0.7. Their manifests declare Plugin API 1.1, Core `>=6.0.2`, PHP `>=8.1`, and no plugin dependencies.
- The generated 37 MB Laravel test ZIP was excluded. The Laravel source remains in `laravel/`.

## Verification

- PHP 8.3.33 lint passed for 772 PHP files.
- `tools/release-gate.php` passed all 119 configured static, unit, database, migration, HTTP, and performance test invocations against disposable MySQL 8.4.3 database `proma_qa_v204`. The result was `RELEASE_GATE_CANDIDATE_QA_OK_NOT_PRODUCTION_CERTIFIED`.
- A separate isolated lifecycle smoke installed and activated all six official plugins from their source manifests and migrations. All six ended in `active` state in the QA database.
- Plugin manifest integrity reported six manifests and 174 registered routes.
- The installation SQL contains 86 `CREATE TABLE` statements and three seed inserts; review found no customer records or contact, national-ID, or payment-card data.
- An integration test exposed unread result rows from SQL-level `EXECUTE` commands in plugin migrations. `PluginManager::executeMigrationStatement()` now drains and closes those cursors; the regression check and full database gate pass.
- The full staged diff against the previous GitHub `main` reported 427 whitespace diagnostics (trailing spaces or extra blank lines at EOF) in files carried into the release. These were left unchanged rather than applying a repository-wide formatting sweep. Real payment and messaging provider calls were not made.

## Security review

- The final Gitleaks working-tree scan reported 10 generic-key candidates. All are synthetic `idempotency_key` UUID fixtures in Laravel tests, not credentials.
- A scan of repository history across available refs reported 17 candidates. Review classified them as synthetic test UUIDs, a dummy redaction fixture, and content hashes/checksums; no live credential was confirmed.
- A pre-existing remote candidate branch contains an old tracked `.appsec-forge/telemetry.jsonl` file with local path and generated observation identifiers. It contains no confirmed credential or customer record. The file is excluded from this release snapshot; existing history was not rewritten.
- `.gitignore` now excludes runtime storage, backup folders, local environment files, dependency folders, key/certificate files, database dumps, and AppSec telemetry. `laravel/.env.example` remains available as a non-secret template.

## Release boundary

This remains a pre-release on the `candidate` channel. The complete isolated QA gate passed, but production availability evidence and live payment/messaging integration tests were not run. Do not describe this build as production-certified or promote its application channel to `stable` on the basis of this audit.
