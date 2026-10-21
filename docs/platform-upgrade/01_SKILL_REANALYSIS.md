# Project skill reanalysis — 2026-10-03

The `skill/` directory has five top-level packages and no root `SKILL.md` router. The repository remains the source of truth for application behavior; packaged examples and benchmark claims do not establish Proma Pay behavior.

| Package | Files observed | Entry point | Use in this upgrade |
| --- | ---: | --- | --- |
| `php-forge` | 147 | `SKILL.md` | Lightweight PHP MVC, fintech invariants, existing routing guidance. Its own readiness is pilot, not an application release claim. |
| `frontend-forge` | 602 | `SKILL.md` | Frontend investigation when changing app assets/views; use direct browser and repository evidence for actual UI claims. |
| `application-security` | 299 | `SKILL.md` | New. PHP and path traversal guidance used for update package paths; patch verification requires positive and negative evidence. Scanner output alone is not a confirmed finding. |
| `database-mysql-architect` | 292 | `SKILL.md` | New. Migration readiness, lock and concurrency, and safety guidance used for upgrade design. MariaDB version and disposable QA evidence are recorded in `00_SOURCE_AUDIT.md`. |
| `ui-ux-pro-max-skill-main` | 685 | `skill/ui-ux-pro-max-skill-main/.claude/skills/ui-ux-pro-max/SKILL.md`; the package also has nested skills under `cli/assets/skills/` | The targeted `ui-ux-pro-max` skill was loaded for the mobile payment regression; its local search returned mobile-first layout and table/card handling guidance. |

The newly added security and database packages were read selectively: `application-security/stacks/php.md`, `playbooks/path-traversal.md`, `playbooks/patch-verification.md`; `database-mysql-architect/playbooks/migration.md`, `knowledge/transactions-concurrency.md`, `checklists/migration-readiness.md`, and `core/safety-policy.md`. For responsive UI work, `frontend-forge/SKILL.md` and the nested UI/UX skill were read; the UI search was limited to the mobile payment/table concern. Neither security nor database package's executable audit runtime was run against Proma Pay. The security capability flags and application finding states are therefore **unknown**, not claimed as passed.

## Applied decision

`ScriptUpdateService` now validates all package targets before backup, rejects unsafe path components and symlinked target parents, serializes installs, and restores original application file bytes if installation fails before migrations start. `tests/update_preflight.php` exercises preflight rejection and the file restoration helpers; `tests/update_file_rollback.php` exercises the complete install path with a post-preflight file failure. Once a migration starts, the service retains installed files and snapshots for coordinated recovery, since database DDL may auto-commit; `tests/update_migration_recovery.php` checks this branch with a simulated database failure. A terminated PHP process remains outside the automatic rollback guarantee. A production release requires a rehearsed upgrade, failure recovery, and schema/data validation on a representative disposable database.
