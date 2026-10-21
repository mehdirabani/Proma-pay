# Proma Pay feature-upgrade source audit

Audit date: 2026-10-02

Source: current tracked Core checkout, version 2.0.3 (`codex/release-v1.4.5`).
Scope: highest-priority contract/customer/payment/document/call/push/mobile requirements from attachment 1. Attachment 2 is assessed after these items. This is a repository audit, not a production-data audit.

## Findings and disposition

| Area | Current implementation | Finding / risk | Disposition |
| --- | --- | --- | --- |
| Contract signature block | `models/ContractDocument.php::signatureSection()` labels the buyer as «امضای امانت‌دار»; simple-template preview in `ContractTemplateService` says «امضای مشتری». | Production generation and preview disagree, directly explaining the reported output. | Correct the generated signature label and add regression assertions. Existing already-generated documents are immutable snapshots and will not be silently rewritten; regenerate only when an authorized user requests it. |
| Legal penalty origin | `InstallmentFinancialStateService::accrueTo()` applies the legal tariff from the installment due-date cursor after formal referral; `2026_09_21_legal_penalty_due_date_policy.sql` updates the clause. | Required policy is already present in tracked source. The existing v1.5.6 unit/integration checks cover it; production deployment/migration state remains unverified. | Preserve the existing canonical calculation; rerun focused unit tests. |
| Manual group payment | `PaymentAllocationService` has deterministic integer allocation. `SettlementQuoteService` persists a locked, expiring quote. `PaymentGroupService` revalidates under contract/row locks and writes immutable child allocations. | A selected-scope quote rejected amounts above selected rows. | Added explicit `schedule` scope: expands from server-side eligible rows under contract lock, stores exact quote IDs, retains idempotency and immutable allocation checks, and rejects amounts above installment debt. MariaDB test verifies ordered split and legal-cost exclusion. |
| Customer group gateway payment | `PaymentsController::gatewayGroup()` and `PaymentGroupService::completeGateway()` create and re-verify the persisted scope. | A second payment can change the quote after external capture. The old callback returned an error without retaining verified money for finance staff. | Scope is persisted; a stale verified callback enters a durable `review_required` group with exact verified amount/reference and no installment allocation. Admin-only, CSRF-protected reconciliation requires a typed bank reference and reason, creates a fresh locked quote, records audit in the same transaction, and either allocates the whole exact amount or keeps the group in review. While a review remains, the customer panel warns about captured money and online payment entry for that contract is gated server-side. MariaDB covers competing payment, role/reference rejection, retry and excess-debt rejection. |
| Customer identity documents | `IdentityDocument` stores private upload paths and creates optional `FileRecord` relations. | Documents were absent from customer detail. Direct storage URLs must not be introduced. | Admin customer detail now lists identity and associated registry documents through authorized no-cache stream routes. Preview URLs are assigned only on opening a document to avoid eager requests. Browser authorization and preview checks remain open. |
| Direct call action | `normalize_iran_phone()` exists and overdue management has a call directory. | Common contract/customer workflow had no direct `tel:` action; guarantor contact was not consistently actionable. | Normalized call links were added to customer and contract detail. Desktop copy fallback remains open. |
| Contract action-menu clipping | Contract detail uses native `<details>` inside a card/layout stacking context; global CSS includes overflow/transform contexts. | An open menu can be clipped by ancestor overflow or stacking context. | Added a body portal with fixed placement, bounded scrolling, escape/outside-click close and preserved menu styling. Browser verification remains required. |
| Android push | Tracked source has Electron shell only; no Android project, Gradle/Cordova/Capacitor app, Firebase configuration, push SDK, or authenticated device-token registration. Service worker has cache handling but no Push handlers. | Native Android push cannot be truthfully implemented or end-to-end verified from this source alone. Adding a fake settings switch would misrepresent capability. | Block implementation until the actual Android app/provider and credential provisioning are available. No page-load scheduler or speculative FCM integration is added. |
| Authentication / access | `CustomersController::show()` and `FileManagerController::view/download()` are admin-only. Contract routes run contract ownership/role authorization. | Existing backend controls are useful foundations; any new document route must retain them and must not accept user-supplied file paths. | Add route-level authorization and object ownership checks. |
| Operational prompt 2 | `core/RequestTelemetry.php` records sampled request duration, SQL count/time, slowest fingerprint, errors, spans and memory peak. `SystemOutbox` and paginated query services exist in several domains. | A static code review cannot substitute for production-like request/load measurements. No stable baseline can be claimed without PHP, MySQL/MariaDB and browser runners. | Use repository telemetry and isolated browser/DB runs when available; record missing runtime evidence as a release gate, not as a pass. |

## Confirmed legal-penalty due-date behavior

The canonical state service deliberately differentiates actual referral from internal review and derives the legal tariff from the installment due date (subject to configured grace). The tests `tests/legal_penalty_projection_v156.php`, `tests/integration_legal_penalty_projection_v156.php`, `tests/static_v156.php`, and `tests/static_v157.php` cover the distinction and no-double-counting contract. This audit does not certify that the corresponding migration was run on a live installation.

## Verification performed in isolated QA

- MariaDB 10.4.32, fresh database `proma_qa_v204` on local port 33387, separate temporary data directory.
- `tests/integration_payment_overflow_allocation_v204.php`: manual allocation, gateway pending/callback, duplicate retry, and schedule-vs-contract legal-cost scope all pass.
- `tests/integration_payment_scope_migration_v204.php`: the controlled updater installed the missing `allocation_scope` column in isolated QA.
- `tests/integration_gateway_reconciliation_v204.php`: controlled updater installed the review columns; a competing payment invalidated a gateway quote, and the externally verified amount/reference remained in an unallocated review group across callback retry. Admin reconciliation then allocated the exact amount and wrote an audit record. Unauthorized or wrong-reference attempts were rejected. An amount above the remaining contract debt stayed unallocated in review.
- `tests/zibal_client_exact_money.php`: integer Toman/Rial conversion and malformed/overflowing gateway response checks pass.
- `tests/integration_installment_settlement_v150.php`, `tests/integration_legal_penalty_projection_v156.php`, `tests/integration_installment_settlement_concurrency_v150.php`, `tests/integration_legal_financial_summary_v158.php`, and `tests/integration_contract_installment_management_v157.php` pass.
- Changed PHP files lint clean; financial static/unit checks pass.
- QA data is synthetic. No production data was read or modified.

## Release-critical evidence still required

- Authenticated browser check of customer documents and contact links, including unauthorized IDs.
- Desktop/mobile browser verification of the contract overflow menu; the user asked not to start a local demo, so this has not been run.
- The admin reconciliation workflow is implemented, but its typed-reference checkbox is an operator attestation, not an automated bank-statement verification. Production bank reconciliation and the refund/credit path for excess funds remain release-critical. The queue deliberately does not auto-allocate excess funds.
- The customer payment gate checks persisted review state before an external gateway request; it does not replace a future provider-side atomic preauthorization design for a review created concurrently with initiation.
- A payment exceeding the whole contract debt is still rejected; creating a customer credit or unapplied balance requires the approved accounting policy and a reconciled ledger path.
- Android push cannot pass until a real Android client and delivery credentials/configuration are supplied.
- Attachment 2's full performance and operational release gates require actual workload and browser evidence; source inspection alone is insufficient.
