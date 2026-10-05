# Continuation checkpoint

## Current phase

Phase C contract/credit/schedule foundation is implemented through an active contract, but remains partial. Phase D payment capture/allocation/gateway/refund/reversal/reconciliation has not started. Do not describe the application as production-ready.

## Completed in this continuation

- Kept all changes within `laravel/`; the repository root contains extensive unrelated Legacy working-tree changes and remains untouched by this work.
- Confirmed current Legacy sources for pricing and schedule behavior: integer arithmetic, simple/compound rates, ceiling division, installment amounts rounded up to 100,000 toman, and month-end-safe dates retaining the anchor day.
- Added an integer-only, versioned `ContractPricingCalculator`, a KYC-gated and idempotent draft workflow, row-locked contract numbering, persisted pricing hash, and batched draft installments.
- Added Persian RTL web/API entry points and contract submit, approve, and activate actions. The UI labels expose each stage and only show actions granted by permission.
- Added an append-only status history and financial credit ledger entries. Submit rechecks active customer/latest approved KYC and reserves financed exposure; a different actor must approve; approval consumes that reservation; activation publishes the scheduled installments.
- Credit exposure is calculated as `principal_toman - down_payment_toman`. This is an explicit new implementation inference: Legacy source has no credit limit/account/reservation model or policy to port. It is not claimed as Legacy parity.
- Added a real two-process MariaDB race test: two 700,000-toman contract submissions compete for a 1,000,000-toman account; exactly one succeeds, one receives insufficient-credit validation, and one reservation ledger entry remains.
- The migration rollback test exposed a missing foreign-key drop before removing `users.branch_id`; fixed the down migration and verified the full rollback in the isolated MariaDB run.
- Browser setup for a new visual capture was blocked by command execution policy. Feature tests assert Persian labels, RTL direction, validation, and role-gated controls; new-section visual/browser accessibility review remains unverified.

## Current architecture and limitations

Laravel 12, Sanctum, Blade/Tailwind RTL. Contract status reaches `active`, and credit is reserved/consumed for that inferred exposure. Credit release/repayment/freeze/expiry reconciliation, contract cancellation/completion/legal transitions, installment state corrections, payment capture/allocation, gateway callbacks, refund/reversal, settlement, migration/import, and other master-prompt domains remain open. Production database version is unknown; test evidence is MariaDB 10.4.32, not MySQL 8 or the production target.

## Phase C files added or extended

- `app/Domain/Contracts/ContractPricingCalculator.php`
- `app/Actions/Contracts/CreateContractDraft.php`, `app/Actions/Contracts/TransitionContract.php`, `app/Actions/Kyc/SubmitKycCase.php`
- Contract and credit models, transition request/controllers/routes, and Persian RTL customer detail view
- `database/migrations/2026_10_05_170000_create_contract_draft_workflow.php`
- `database/migrations/2026_10_05_171000_add_contract_credit_transitions.php`
- `database/migrations/2026_10_05_160000_create_access_control_tables.php` rollback fix
- `tests/Unit/ContractPricingCalculatorTest.php`
- `tests/Feature/ContractDraftTest.php`, `tests/Feature/ContractReservationConcurrencyTest.php`, `tests/Support/contract_reservation_worker.php`
- Progress, gap matrix, testing, MariaDB, completion, and continuation documentation

## Validation evidence

- SQLite: 39 passed, 192 assertions; the real-row-lock concurrency proof is intentionally skipped on SQLite.
- MariaDB 10.4.32 (`127.0.0.1:3307`, disposable `%TEMP%` datadir): 40 passed, 202 assertions, including two-process reservation race and migration rollback.
- Pint passes on changed PHP files; `git diff --check -- laravel` is clean.
- A previous MariaDB command through `artisan test --configuration` emitted a duplicate-option warning; the accepted authoritative run is direct PHPUnit with `--configuration=phpunit.mariadb.xml`.
- New contract actions render in RTL feature tests. No fresh browser screenshot claim is made because local server/browser setup was blocked.
- The isolated MariaDB process was stopped after validation.

## Next exact task

Begin Phase D by mapping the current Legacy payment/correction/allocation source and golden cases before writing Laravel payment code. Preserve the distinction between intent and captured money; derive immutable allocation ordering and legal-cost treatment from source/tests. Then implement manual captured-payment recording and allocation with append-only rows, idempotency, invariants, Persian RTL UI/API, and MariaDB race tests. Gateway, refund, settlement, and reconciliation need their own source-backed state transitions and evidence. Do not extend readiness claims beyond the completed evidence.

## Relevant commits

- Foundation: `c4c0017`
- Initial validation/docs: `43fecd6`
- Phase A gap matrix: `7530ddd`
- Isolated MariaDB checkpoint: `e557694`
- Legacy pricing/API regression work: `4369827`
- KYC-gated contract drafts: `35bd7e4`
- Contract reservation/approval/activation: recorded in the current continuation commit.
