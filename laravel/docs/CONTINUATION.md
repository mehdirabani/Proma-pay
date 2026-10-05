# Continuation checkpoint

## Current phase

Phase D is in progress. Legacy payment/allocation, narrow installment-correction, and transfer-proof review rules are mapped. Laravel persists short-lived actor-bound quotes, supports manual capture and staff-assisted transfer-proof submission/review, and allows a separately authorized, reasoned internal correction. Financial policy starts unconfigured because Legacy defaults conflict and current production values are unknown. The customer has no Laravel login, so this is not customer self-service. Actual refunds, legal referral/cost facts, gateways, reconciliation, and merchant settlement remain open. Do not describe the application as production-ready.

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
- Ported the narrow Legacy transfer-proof review flow. Staff submissions use request idempotency, one pending proof per installment, MIME/extension/size validation, random filenames, a dedicated private disk, and file SHA-256. Proof data is not a `Payment` until reviewer approval.
- Added Persian RTL submission controls on active installment rows, an authenticated branch-scoped review queue, approve/reject forms with required review reason, secured staff download, and an explicitly requested short-lived signed API download URL. Verified file downloads and receipt decisions are audited.
- Approval locks the receipt and contract, creates a fresh one-installment quote from the reviewer-approved amount, and captures `bank_transfer` in the same transaction. A stale or over-payable amount fails without closing the pending receipt. Rejection creates no payment/allocation. Exact decision replay is idempotent.
- Laravel retains uploaded evidence after review for audit; Legacy deletes it. This is a deliberate retention change while an approved file-retention/backup policy is still missing. Malware scanning and orphan cleanup are not implemented.
- Fixed `AssignRequestId` to set the header through Symfony's response header bag, allowing streamed file-download responses to pass global middleware.
- Added unit/feature coverage for quote scopes, capture and correction snapshots, replay conflicts, settings changes, expiry, branch denial, RTL and a real two-process MariaDB race for each of credit reservation, same-installment payment capture and same-payment correction. Only one competing financial mutation is accepted in each race.
- Captured installment debt amounts can include finance charges, while the new credit exposure is financed principal. For that reason manual capture does not decrement used credit until a source-backed principal/repayment allocation policy exists.
- Browser setup for a new visual capture was blocked by command execution policy. Feature tests assert Persian labels, RTL direction, validation, and role-gated controls; new-section visual/browser accessibility review remains unverified.

## Current architecture and limitations

Laravel 12, Sanctum, Blade/Tailwind RTL. Contract status reaches active, and credit is reserved/consumed for the inferred exposure. Manual payment capture consumes a verified quote and records installment allocations; credit remains unchanged because installment allocations do not prove repayment of financed principal. A narrow internal correction can remove a captured installment payment from effective history and restore calculated installment balance; it does not perform an actual refund. A staff-assisted transfer-proof flow stores evidence privately and only creates a bank-transfer payment after a reviewer-approved, freshly recalculated quote. The Customer still has no login/portal. Quote/capture excludes legal referral penalties and legal costs because the Laravel legal domain is absent. Gateway callbacks, actual refund/reversal, contract completion/cancellation, reconciliation, credit release/repayment/freeze/expiry, merchant settlement, data migration/import, malware scanning, and other master-prompt domains remain open. The current receipt migration was tested on SQLite; MariaDB 10.4.32 was verified only through the prior correction commit because this machine's disposable MariaDB server now crashes at InnoDB startup. Production DB target remains unknown.

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
- app/Actions/Payments/SubmitPaymentReceipt.php, ReviewPaymentReceipt.php, app/Models/PaymentReceipt.php, `PaymentReceiptEvidenceStorage`, receipt policies/controllers/requests/resources, API/web routes, permission/migration, and Persian RTL views
- tests/Feature/PaymentReceiptTest.php

## Validation evidence

- SQLite: 85 tests/593 assertions (82 passed, three existing row-lock tests intentionally skipped); `migrate:fresh --seed --force --database=sqlite` succeeded after the receipt migration.
- MariaDB 10.4.32 (127.0.0.1:3307) prior evidence: 78 passed/496 assertions through correction migration, including credit reservation, capture and correction races. The receipt migration has not been validated on MariaDB: fresh disposable XAMPP MariaDB datadirs crash during InnoDB startup with `os0file.cc` assertion `slot`; no non-test database was used.
- Pint passes on changed PHP files; `git diff --check -- laravel` is clean.
- A previous MariaDB command through `artisan test --configuration` emitted a duplicate-option warning; the accepted authoritative run is direct PHPUnit with `--configuration=phpunit.mariadb.xml`.
- New contract actions render in RTL feature tests. No fresh browser screenshot claim is made because local server/browser setup was blocked.
- A new browser screenshot was not produced; feature tests verify the Persian RTL quote view and role-gated controls.

## Next exact task

Continue Phase D by mapping LegalCase referral/cost facts and external refund semantics, then credit repayment policy and reconciliation before expanding gateway capture. Follow with merchant settlement and the remaining master-prompt modules. Confirm the production DB target and validate on its exact engine/version; prior MariaDB 10.4.32 evidence and current SQLite suite are insufficient.

## Relevant commits

- Foundation: `c4c0017`
- Initial validation/docs: `43fecd6`
- Phase A gap matrix: `7530ddd`
- Isolated MariaDB checkpoint: `e557694`
- Legacy pricing/API regression work: `4369827`
- KYC-gated contract drafts: `35bd7e4`
- Contract reservation/approval/activation: `e715e31`.
- Legacy financial-state/allocation calculator and source map: `75f0842`.
- Governed financial rule controls: `534fe93`.
- Manual installment settlement: `bb66155`.
- Audited installment corrections: `27be765`.
