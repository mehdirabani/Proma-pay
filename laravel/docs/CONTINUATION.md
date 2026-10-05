# Continuation checkpoint

## Current phase

Phase C — KYC-gated contract draft and source-verified schedule are implemented through Persian RTL web/API entry points. Approval/activation, credit reservation, and payment/allocation remain open.

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
- Added persisted draft creation: `PR-000001` sequence is row-locked; duplicate request UUID with the same canonical payload replays, changed terms reject; only the latest approved KYC permits draft creation; terms and a calculation hash are stored; generated installments are `draft`; an audit event is recorded.
- Added API `POST /api/v1/contracts` and a Persian RTL form on the authorized customer's page. The page says clearly that draft creation does not reserve credit or register a payment. This is deliberate while credit exposure and approval semantics remain unresolved.
- Added an additive migration for contract pricing/idempotency snapshot fields and the contract sequence row; the 600-installment request cap is a technical write-bound, not a Legacy financial rule.
- Current Laravel suite: 33 tests / 139 assertions on SQLite and MariaDB 10.4.32; Pint passes on all changed PHP files.
- Added `PRODUCTION-GAP-MATRIX.md`.

## Current architecture

Laravel 12 + Sanctum + Blade/Tailwind RTL. SQLite is the configured test default. Customer/KYC/credit-limit adjustment/payment intent are thin foundations. Contract draft creation now has a versioned schedule, KYC gate, audit, idempotency and Persian RTL/API entry points; approval, credit reservation, active lifecycle and payment allocation remain unimplemented. Legacy is separate lightweight PHP MVC under repository root.

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
- `app/Actions/Contracts/CreateContractDraft.php`
- `app/Http/Requests/ContractDraftRequest.php`
- `app/Http/Controllers/Api/ContractController.php` and `app/Http/Resources/ContractResource.php`
- `database/migrations/2026_10_05_170000_create_contract_draft_workflow.php`
- RTL web entry point in `app/Http/Controllers/CustomerActionsController.php`, `app/Http/Controllers/CustomerPageController.php`, `routes/web.php`, `routes/api.php`, and `resources/views/customers/show.blade.php`
- `tests/Unit/ContractPricingCalculatorTest.php`
- `tests/Feature/ContractDraftTest.php`
- `tests/Feature/CreditLimitAdjustmentTest.php`
- `tests/Feature/CustomerManagementTest.php`
- `tests/Feature/PaymentIdempotencyTest.php`
- Matrix, progress, test, and completion evidence docs

## Database changes and environment

Phase C adds an additive migration for draft terms/idempotency and a contract sequence table. The disposable MariaDB test database contains only Laravel test tables; service is loopback-only. Local PHP 8.2 and MariaDB 10.4.32 were used. Production DB version is unknown. A separate attempt to prepare an interactive browser fixture was blocked by command policy when local server/setup commands were issued; feature tests still render and assert the RTL form, but new-section visual capture is not claimed.

## Commands/tests

Read-only `git status`, `git log`, `git diff --stat`; `laravel/` was clean at audit start. Root has extensive prior worktree changes and remains out of scope. Three Legacy DB-free financial regression scripts passed. Initial Laravel migration+seed passed on MariaDB 10.4.32; after the additive draft migration, `RefreshDatabase` applied the current schema and the suite passed with 33 tests/139 assertions on both SQLite and MariaDB.

## Unresolved financial evidence

Legacy `InstallmentFinancialStateService` is versioned and replays dated paid/non-corrected payments, grace, normal/legal penalty rates, legal referral, and reward. `PaymentAllocationService` is versioned `payment-allocation-v2`. Three pure tests pass, but full selected-row/legal-cost allocation, waiver lifecycle, production settings, and historical data semantics still need review. One old legal-transition doc contradicts the current service, v1.5.14 due-date policy, and v156 test; those three agree that after official referral the legal tariff uses installment due-date origin. Keep the conflict visible in Laravel docs and do not change Legacy files.

## Next exact task

Inspect Legacy credit/exposure and approval policy evidence. Then implement audited, append-only reservation/use/release entries and a double-control approval boundary that consumes a draft only if sufficient available credit can be reserved atomically. Do not activate or post payments until those invariants and source semantics have MySQL race tests. Continue into installment allocation only after historical rows/legal-cost semantics are mapped; confirm the production DB version before any readiness claim.

## Relevant commits

- Initial foundation: `c4c0017`
- Initial validation/docs: `43fecd6`
- Phase A assessment: see the commit containing this checkpoint.
