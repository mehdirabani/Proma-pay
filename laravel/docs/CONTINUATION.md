# Continuation checkpoint

## Current phase

Phase C — source-verified contract pricing slice and authorization/idempotency regression coverage. The application remains incomplete and broader domain work remains active.

## Completed work

- Confirmed the foundation commits `c4c0017` and `43fecd6`; Phase A assessment checkpoint is `7530ddd`. `laravel/` was clean at audit start for this continuation.
- Read both commit summaries, Laravel routes/models/migrations/tests and current completion/architecture/data docs.
- Scanned skills and read PHPForge Laravel/fintech/database/API/security/legacy/testing/performance/WP/Woo modules, MySQL Architect core + fintech/concurrency/migration material, PHP/Laravel security notes, and UI Styling.
- Compared current Legacy SQL table inventory with the committed map: current SQL has 73 tables; the old map has 72 and omits `customer_automation_events`.
- Ran three DB-free Legacy financial regression scripts: installment settlement v150, legal-penalty projection v156, and financial precision v136; all passed.
- Initialized a new disposable MariaDB 10.4.32 datadir under `%TEMP%`, bound the process to loopback port 3307, and ran Laravel migrations/seed plus the 13 feature tests successfully.
- Added `phpunit.mariadb.xml` and `docs/MYSQL-TESTING.md` so database-driver validation is repeatable and separate from SQLite defaults; updated test and completion evidence with the verified MariaDB run.
- Added `Domain\Contracts\ContractPricingCalculator`, an integer-only port of the current Legacy simple/compound installment quote, down-payment, upward rounding, and month-end due-date rules. The current source golden case is `tests/financial_precision_v136.php`; Legacy date behavior was checked directly against `FinanceHelper::addMonths` for a January 31 anchor.
- Added branch-scoped denial tests for KYC review, credit adjustment, and payment intent, plus a changed-payload credit idempotency test.
- Current Laravel suite: 25 tests / 91 assertions on SQLite and MariaDB 10.4.32; Pint passes on all changed PHP files.
- Added `PRODUCTION-GAP-MATRIX.md`.

## Current architecture

Laravel 12 + Sanctum + Blade/Tailwind RTL. SQLite is the configured test default. Customer/KYC/credit-limit adjustment/payment intent are thin foundations; contracts/installments have schema/models only; payment intent remains pending. Legacy is separate lightweight PHP MVC under repository root.

## Files changed in Phase A and the MariaDB checkpoint

- `docs/PRODUCTION-GAP-MATRIX.md`
- `docs/CONTINUATION.md`
- `docs/PROGRESS.md`
- `docs/LEGACY-MAPPING.md` (refresh current inventory from 72 to 73)
- `docs/TESTING.md` and `docs/COMPLETION-REPORT-fa.md` (append MariaDB evidence)
- `phpunit.mariadb.xml` and `docs/MYSQL-TESTING.md`

No runtime code or database migration has been changed in Phase A or the MariaDB validation checkpoint.

## Phase C implementation files

- `app/Domain/Contracts/ContractPricingCalculator.php`
- `tests/Unit/ContractPricingCalculatorTest.php`
- `tests/Feature/CreditLimitAdjustmentTest.php`
- `tests/Feature/CustomerManagementTest.php`
- `tests/Feature/PaymentIdempotencyTest.php`
- Matrix, progress, test, and completion evidence docs

## Database changes and environment

No committed Laravel schema change. A disposable test database was created under `%TEMP%`; it contains only Laravel test tables and the database service is temporary. Local PHP 8.2 and MariaDB 10.4.32 were used. Production DB version is unknown.

## Commands/tests

Read-only `git status`, `git log`, `git diff --stat`; `laravel/` was clean at audit start. Root has extensive prior worktree changes and remains out of scope. Three Legacy DB-free financial regression scripts passed. Laravel `migrate:fresh --seed --force` passed on MariaDB 10.4.32; current suite has 25 tests/91 assertions passing on both SQLite and MariaDB.

## Unresolved financial evidence

Legacy `InstallmentFinancialStateService` is versioned and replays dated paid/non-corrected payments, grace, normal/legal penalty rates, legal referral, and reward. `PaymentAllocationService` is versioned `payment-allocation-v2`. Three pure tests pass, but full selected-row/legal-cost allocation, waiver lifecycle, production settings, and historical data semantics still need review. One old legal-transition doc contradicts the current service, v1.5.14 due-date policy, and v156 test; those three agree that after official referral the legal tariff uses installment due-date origin. Keep the conflict visible in Laravel docs and do not change Legacy files.

## Next exact task

Implement persisted contract draft/approval boundaries and Persian RTL contract UI around the source-verified quote without posting money or reserving credit until the business exposure basis is resolved from Legacy evidence. Add MySQL tests for the persisted lifecycle. Continue into installment allocation only after its historical rows/legal-cost semantics have been mapped. Confirm the production database version before any readiness claim.

## Relevant commits

- Initial foundation: `c4c0017`
- Initial validation/docs: `43fecd6`
- Phase A assessment: see the commit containing this checkpoint.
