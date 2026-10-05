# Continuation checkpoint

## Current phase

Phase D is in progress. Legacy payment/allocation and narrow installment-correction rules are mapped. Laravel persists short-lived actor-bound quotes, captures only manual/cash/card/POS/bank-transfer receipts, and allows a separately authorized, reasoned internal correction of an eligible captured installment payment. Capture and correction revalidate state under MariaDB row locks. Financial policy starts unconfigured because Legacy defaults conflict and current production values are unknown. Uploaded receipt review, actual refunds, legal referral/cost facts, gateways, reconciliation, and merchant settlement remain open. Do not describe the application as production-ready.

## Completed in this continuation

- Kept all changes within `laravel/`; the repository root contains extensive unrelated Legacy working-tree changes and remains untouched by this work.
- Confirmed current Legacy sources for pricing and schedule behavior: integer arithmetic, simple/compound rates, ceiling division, installment amounts rounded up to 100,000 toman, and month-end-safe dates retaining the anchor day.
- Added an integer-only, versioned `ContractPricingCalculator`, a KYC-gated and idempotent draft workflow, row-locked contract numbering, persisted pricing hash, and batched draft installments.
- Added Persian RTL web/API entry points and contract submit, approve, and activate actions. The UI labels expose each stage and only show actions granted by permission.
- Added an append-only status history and financial credit ledger entries. Submit rechecks active customer/latest approved KYC and reserves financed exposure; a different actor must approve; approval consumes that reservation; activation publishes the scheduled installments.
- Credit exposure is calculated as `principal_toman - down_payment_toman`. This is an explicit new implementation inference: Legacy source has no credit limit/account/reservation model or policy to port. It is not claimed as Legacy parity.
- Added a real two-process MariaDB race test: two 700,000-toman contract submissions compete for a 1,000,000-toman account; exactly one succeeds, one receives insufficient-credit validation, and one reservation ledger entry remains.
- The migration rollback test exposed a missing foreign-key drop before removing `users.branch_id`; fixed the down migration and verified the full rollback in the isolated MariaDB run.
- Mapped Legacy `payment-allocation-v2`, installment financial-state v4, settlement quote scopes, legal-case cost eligibility/order, and correction reversal semantics in `docs/PAYMENT-ALLOCATION.md`.
- Added integer-only Laravel `MoneyMath`, `InstallmentFinancialStateCalculator`, and `PaymentAllocationPlanner`. The pure ports replay effective payments by date/ID, fail closed for missing actual legal rate/inconsistent referral, calculate due-date-origin penalties and reward eligibility, order rows by Legacy financial priority, apply partial receipts legal penalty → normal penalty → principal, and use reward only for a full settlement.
- Added 11 unit tests/46 assertions over v150/v156 source-backed cases. The corresponding Legacy DB-free v150 settlement, v156 legal-penalty, and v136 pricing scripts still pass.
- Added nullable-by-default, administrator-only versioned financial rule controls with Persian numeric input handling, idempotency, history, and audit. Rates are never inferred from conflicting Legacy migration defaults.
- Added a database-backed settlement quote builder for `selected`, `schedule`, and `forward` scopes, with persisted settings/version and financial-plan snapshots, actor binding, expiry, stale-state rechecks, and server-side API/web access control.
- Added final manual capture for cash/card/POS/bank-transfer/manual methods. Capture creates one paid payment parent and append-only allocation components in the same transaction, validates the net component equation and exact allocated total, consumes the quote once, and updates installment materialized status.
- Added a Persian RTL quote review/confirmation page and customer installment selection UI; the existing payment-intent path remains pending and separate. The page explains legal costs and gateway limitations and distinguishes internal correction from actual bank refund.
- Ported Legacy's narrow installment payment-correction behavior: administrator permission, paid installment captures only, mandatory reason, request idempotency, payment/contract/installment locks, before/after canonical snapshots, corrected payment status, and immutable reversal allocations. Pending, down-payment, and unsupported payment types are rejected; no bank refund is sent. Reversal rows preserve positive magnitudes and carry is_reversal/reversal_of_id flags because Laravel amount columns are unsigned.
- Fixed installment-state replay to return calculated status over stale materialized status. Correction now rebuilds all affected installment status/paid principal and appends reversal rows atomically with the correction and audit.
- Added unit/feature coverage for quote scopes, capture and correction snapshots, replay conflicts, settings changes, expiry, branch denial, RTL and a real two-process MariaDB race for each of credit reservation, same-installment payment capture and same-payment correction. Only one competing financial mutation is accepted in each race.
- Captured installment debt amounts can include finance charges, while the new credit exposure is financed principal. For that reason manual capture does not decrement used credit until a source-backed principal/repayment allocation policy exists.
- Browser setup for a new visual capture was blocked by command execution policy. Feature tests assert Persian labels, RTL direction, validation, and role-gated controls; new-section visual/browser accessibility review remains unverified.

## Current architecture and limitations

Laravel 12, Sanctum, Blade/Tailwind RTL. Contract status reaches active, and credit is reserved/consumed for the inferred exposure. Manual payment capture consumes a verified quote and records installment allocations; credit remains unchanged because installment allocations do not prove repayment of financed principal. A narrow internal correction can remove a captured installment payment from effective history and restore the calculated installment balance; it does not perform an actual refund. Quote/capture excludes legal referral penalties and legal costs because the Laravel legal domain is absent. Uploaded payment proof and review, gateway callbacks, actual refund/reversal, contract completion/cancellation, reconciliation, credit release/repayment/freeze/expiry, merchant settlement, data migration/import, and other master-prompt domains remain open. Production database version is unknown; test evidence is MariaDB 10.4.32, not MySQL 8 or the production target.

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

## Phase D source map and pure port

- `docs/PAYMENT-ALLOCATION.md`
- `app/Domain/Payments/MoneyMath.php`
- `app/Domain/Payments/InstallmentFinancialStateCalculator.php`
- `app/Domain/Payments/PaymentAllocationPlanner.php`
- `app/Domain/Payments/InstallmentPaymentStateLoader.php`, `SettlementQuotePlanner.php`, `SettlementQuoteSnapshot.php`
- `app/Actions/Payments/CreateSettlementQuote.php`, `CaptureManualPayment.php`, `UpdateFinancialRules.php`
- Settlement quote/payment allocation schema, API resources/controllers, Persian RTL views
- `tests/Unit/InstallmentFinancialStateCalculatorTest.php`
- `tests/Unit/PaymentAllocationPlannerTest.php`
- app/Actions/Payments/CorrectCapturedPayment.php, app/Models/PaymentCorrection.php, correction API/web endpoints and permission migration
- tests/Feature/PaymentCorrectionTest.php, tests/Feature/PaymentCorrectionConcurrencyTest.php, tests/Support/payment_correction_worker.php

## Validation evidence

- SQLite: 78 tests/466 assertions (75 passed, three intentional row-lock skips); the real-row-lock concurrency proofs are not claimed on SQLite.
- MariaDB 10.4.32 (127.0.0.1:3307, disposable %TEMP% datadir): 78 passed, 496 assertions, including two-process credit reservation, same-installment capture and same-payment correction races plus full migration rollback. migrate:fresh --seed --force and rollback succeeded after the latest permission/correction migration.
- Pint passes on changed PHP files; `git diff --check -- laravel` is clean.
- A previous MariaDB command through `artisan test --configuration` emitted a duplicate-option warning; the accepted authoritative run is direct PHPUnit with `--configuration=phpunit.mariadb.xml`.
- New contract actions render in RTL feature tests. No fresh browser screenshot claim is made because local server/browser setup was blocked.
- A new browser screenshot was not produced; feature tests verify the Persian RTL quote view and role-gated controls.

## Next exact task

Continue Phase D with the Legacy customer-uploaded transfer-proof/review flow and secure private file storage, then map legal referral/cost facts and decide source-backed external refund semantics. Add credit repayment policy and reconciliation before expanding gateway capture. Then implement merchant settlement and the remaining master-prompt modules. Confirm the production DB target and validate on its exact engine/version; MariaDB 10.4.32 alone is insufficient.

## Relevant commits

- Foundation: `c4c0017`
- Initial validation/docs: `43fecd6`
- Phase A gap matrix: `7530ddd`
- Isolated MariaDB checkpoint: `e557694`
- Legacy pricing/API regression work: `4369827`
- KYC-gated contract drafts: `35bd7e4`
- Contract reservation/approval/activation: recorded in the current continuation commit.
- Legacy financial-state/allocation calculator and source map: in the current continuation commit.
