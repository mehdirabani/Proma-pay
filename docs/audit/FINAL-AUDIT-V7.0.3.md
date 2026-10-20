# Proma Pay V7.0.3 Candidate — Release Audit

Date: 2026-10-20 (Asia/Tehran)

## Scope

- Release identity, Core/API/plugin versions, and V7.0.2 update baseline.
- PHP syntax, static/unit release gate, plugin manifest inventory, and candidate package integrity.
- Candidate package contents and the available local upgrade evidence.

## Findings addressed

Three older static checks still required V1-era dashboard hooks, a fixed V5 minimum Core version for PromaAccounting, and the previous customer purchase-history CSS classes. The active V7 UI uses role-specific dashboard hooks, Accounting declares Core `>=6.0.2` with Plugin API `1.1`, and customer history uses the `customer-history-*`/`customer-purchase-*` component family. The checks now validate those current contracts.

## Verification results

- PHP syntax passed for 1,417 files.
- `tools/release-gate.php --static-only` passed all configured static and unit checks.
- Candidate artifacts were rebuilt with gate status `LOCAL_STATIC_UNIT_PASSED_INTEGRATION_BLOCKED` and verified by `tools/verify-candidate-artifacts.php`, including the universal Core update from V2.0.0.
- The Core archive contains 2,699 runtime files; the V7.0.2 differential update contains 9 files and no new migrations.
- Six plugin archives and the full Core-plus-plugins bundle were generated. Archive inventories, manifests, SHA-256 checksums, paths, and packaged PHP syntax passed candidate verification.
- The prior local QA report records a V7.0.2-to-V7.0.3 update, route/UI checks, and browser preview checks: `dist/candidates/v7.0.3-local-qa2/QA-REPORT-fa.md`.

## Remaining release boundary

- The full database/HTTP gate could not run: `PROMA_TEST_DB_DSN` is unset, the local database is unavailable, and `proma.test` is not responding.
- Real payment and messaging-provider calls were not tested.
- Production availability is not certified. Keep this build on the Candidate channel until the isolated database/HTTP gate passes.
- The Git worktree is still dirty and its current branch is named `codex/release-v1.4.5`, while the application files identify V7.0.3. Candidate archives therefore describe the current working tree, not a clean V7.0.3 commit or tag.
