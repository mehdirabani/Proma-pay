# Proma Pay Full Bug Audit — Baseline

> **Historical record:** this file captures the V2.2.10 baseline from the audit dated 2026-10-09. It is not a description of the V7.0.0 candidate. For V7 scope and current release evidence, see [FINAL-AUDIT-V7.0.0.md](FINAL-AUDIT-V7.0.0.md).

Baseline captured before this audit made any source changes.

## Source and release state

- Source of truth: working tree at `F:\proma-pay`.
- Git branch: `codex/release-v1.4.5` (14 commits ahead of `origin/codex/release-v1.4.5`).
- HEAD: `31c11c8c8173863cbbad8baf424ab1691318ce0d`.
- Working tree: 1,162 porcelain status entries were observed. The tree contains extensive existing edits, additions, and deletions; none were created by this audit at baseline time.
- Core application version: `2.2.10`; display `V2.2.10`; configured release channel `stable`.
- Core database schema marker: `2026.10.06`.
- Plugin API marker: `1.1`.
- Plugin manifests in the working tree:
  - PromaAccounting `1.2.22` (manifest API `1.0`)
  - PromaRepair `1.2.3` (manifest API `1.1`)
  - PromaSignConnect `1.2.2` (manifest API `1.0`)
  - PromaAffiliate `1.0.4` (manifest API `1.1`)
  - PromaZarinpal `1.0.3` (manifest API `1.0`)
- Database runtime version and active plugin set: **UNKNOWN**. No QA DSN or QA base URL is configured, and this baseline did not connect to an application database.
- PHP runtime available at `C:\xampp\php\php.exe`: PHP 8.2.12; extensions `pdo_mysql`, `mysqli`, `zip`, and `mbstring` are enabled.
- MySQL client binary exists at `C:\xampp\mysql\bin\mysql.exe`; no configured release-test DSN identifies an isolated test server/database.
- Repository SQL migration inventory: 77 `.sql` files found under `database/migrations` and `plugins`.
- PHP test inventory: 282 files found under `tests` and `plugins`.
- Existing candidate release: `dist/candidates/v2.2.10`; its README records Release Gate `NOT RUN`, and its embedded packages are candidate-only.
- No root-level `release-manifest.json` was found in the working tree; candidate manifests are generated inside package artifacts.

## Baseline test state

- `php tools/release-gate.php --local` was started before fixes.
- It remained in its PHP lint phase and was stopped before test execution after inspection showed 12,965 PHP files in the full workspace, including dependency trees. The current lint loop starts one PHP process per file and does not prune `node_modules`/`vendor` directories.
- Static/unit tests: **NOT RUN** by the baseline gate (the lint phase did not finish).
- MySQL/MariaDB integration, migration, fresh-install, and upgrade tests: **BLOCKED / NOT RUN**. Required `PROMA_TEST_DB_DSN`, `PROMA_QA_BASE_URL`, and `PROMA_TEST_ALLOW_DESTRUCTIVE=1` are absent. No non-QA database was used.

## Evidence-backed baseline findings

1. **Accounting manifest declares dead handlers (REPRODUCED statically).** `plugins/PromaAccounting/plugin.json` declares salary and legacy referral/bank/withdrawal routes handled by `AccountingController`, while a search of `plugins/PromaAccounting/src` found no corresponding handler methods. These declarations cannot pass a route-handler integrity check. Changelog/ownership review is still required before deciding which obsolete routes to remove.
2. **Accounting manifest duplicates five permission keys (REPRODUCED).** Each of `analytics_settings_manage`, `referrals_self`, `referral_admin_view`, `referral_admin_manage`, and `referral_withdrawal_self` occurs twice in `plugins/PromaAccounting/plugin.json`.
3. **Release lint has a workspace traversal/performance defect (REPRODUCED by execution).** The local release gate did not complete its first phase in the baseline run because it traverses dependency trees containing 12,965 PHP files, with one PHP process launched for each file.
4. **Release state is contradictory (REPRODUCED in source/artifacts).** `config/version.php` calls V2.2.10 stable, while the existing candidate README states the release gate was not run and the packages are not certified for production.

This file records the pre-change baseline only. Findings, fixes, and post-change evidence belong in the final audit report.
