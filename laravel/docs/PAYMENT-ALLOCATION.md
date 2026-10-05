# Payment allocation source map

This document records verified Legacy behavior and the current limits of the Laravel settlement slice. It distinguishes the pure calculator port from the newly persisted manual capture flow.

## Source evidence

- `helpers/PaymentAllocationService.php` (`payment-allocation-v2`): payable-row filtering, stable sort, amount bounds, partial/full allocation split, and reward treatment.
- `helpers/InstallmentFinancialStateService.php` (`installment-financial-state-v4-due-date-legal`): effective payment replay, principal/penalty state, payment eligibility, and financial priority.
- `helpers/SettlementQuoteService.php`: selected/schedule/forward/contract scopes, persisted quote TTL and snapshot verification, legal-cost scope, and quote consumption.
- `helpers/PaymentGroupService.php`: transactional parent group + child payments/allocations, request UUID replay, contract/installment row locks, exact-total invariant, gateway pending/completion path.
- `helpers/LegalCaseCostService.php`: eligible court-referred costs, ordering, partial payment updates, and reversal ledger.
- Golden/acceptance evidence: `tests/installment_settlement_engine_v150.php`, `tests/legal_penalty_projection_v156.php`, `tests/integration_installment_settlement_v150.php`, and `tests/integration_payment_overflow_allocation_v204.php`.

## Verified installment rules

1. Effective historical payments are replayed by payment date, then payment ID. Only `paid`, non-corrected payments effective on or before the calculation date count; down-payment rows are excluded from installment principal replay.
2. Eligible installments are ordered by financial priority, then due date, installment number, and row ID. Current priorities are legal-overdue `0`, overdue beyond grace `1`, due today `2`, overdue within grace `3`, partial `4`, upcoming `5`, paid `80`, and cancelled `90`.
3. A partial receipt is applied to legal penalty first, normal penalty second, and principal last. Partial settlement receives no early-payment reward.
4. A full settlement charges `final_payable` (remaining principal + payable penalties − eligible reward); the immutable allocation records the full component amounts and the reward used. This keeps reward application confined to a complete settlement in that transaction.
5. A selected quote includes only its validated selected rows and rejects an amount above their total. The quote snapshot binds the selected IDs, financial totals, calculation version, actor, scope, and expiry; commit locks and recalculates before accepting it.
6. `schedule` expands to the full eligible contract schedule, while `forward` expands from the earliest selected position. Allocation then follows the financial-priority ordering above, not checkbox order. The v150 and v204 tests establish the exact overflow vectors.
7. Contract-wide settlement may add approved, chargeable, non-reversed, unreimbursed legal-case costs only when they are linked to a formal court referral. Costs are ordered by cost date then ID. `selected`, `schedule`, and `forward` exclude these costs. `contract` pays installment debt first then costs; `contract_legal_first` pays eligible costs first.
8. Successful Legacy group commit writes immutable child payment/allocation records and requires their amount sum to equal the received group amount. Legacy correction is an internal ledger operation: it accepts only successful eligible payments, requires a reason, does not transfer money back, and appends a reversal allocation rather than editing the original allocation. Down-payment correction is explicitly rejected.

## Laravel implementation status

`App\Domain\Payments\PaymentAllocationPlanner` ports deterministic row ordering and within-installment splitting. `InstallmentPaymentStateLoader` now replays paid, immutable allocation components from the database into the versioned calculator. An admin-configured `FinancialRule` snapshot is mandatory; rates start as `NULL` and quotes fail closed until values are explicitly set.

Laravel now persists actor-bound, five-minute `SettlementQuote` snapshots for Legacy `selected`, `schedule`, and `forward` scopes, and exposes both API and Persian RTL web flows. Capture supports manual, cash, card, POS, and bank transfer records only. In one transaction it locks the contract, rule row, installments, and prior allocation ledger; recomputes the canonical state/plan; checks the rule version, quote date, expiry, actor, snapshot hash, idempotency payload, component equation, and exact parent/allocation amount; then writes a paid `Payment`, append-only `PaymentAllocation` rows, materialized installment principal/status, quote consumption, and an audit event.

`CorrectCapturedPayment` ports the narrow Legacy installment correction behavior for captured Laravel installment payments. It is administrator-permissioned, reasoned, idempotent, and contract/payment/installment locked. It preserves the original capture/allocation, marks the payment `corrected`, snapshots canonical installment state before and after, and appends positive-magnitude reversal allocation rows flagged with `is_reversal` and `reversal_of_id` (the Laravel amount columns are unsigned). The state loader excludes corrected payments and reversal rows, then the action recomputes and writes materialized installment state in the same transaction. This is strictly an internal correction: `bank_refund_performed` is false. It rejects pending records, down payments, and unsupported payment types.

MariaDB two-process tests now prove that only one of two concurrent captures can collect the same installment and only one correction can reverse the same payment.

The workflow deliberately does not claim the remainder of the Legacy payment system:

- Laravel has no legal-case/referral tables yet. The loader passes no legal referral date; legal penalties and legal-case costs are therefore excluded from quotes, even if an administrator enters a legal rate.
- Gateway, wallet, webhook/callback, customer-uploaded transfer proof review, generated receipt issuance, external refunds, legal-cost correction, reconciliation, and merchant settlement are not implemented. The UI distinguishes the available internal correction from an actual bank refund.
- Captured installment `principal_applied` is the Legacy scheduled-installment debt component (which can include contract finance charges), not a proven decomposition of financed contract principal. Capture does not reduce `credit_accounts.used_toman`; doing so without an evidenced principal allocation would release the wrong credit amount.
- Eloquent guards reject updates/deletes to allocation and quote snapshots, but a privileged SQL client can bypass those application guards. Database-level immutability still needs an operational/database policy.

Do not call the planner directly with materialized display balances; its input must be the canonical state replayed from paid allocation rows and a versioned rules snapshot.

## Explicit unresolved scope

The source supports several payment modes and both manual/gateway flows. Provider behavior, callbacks, refund/reversal caps, receipt-file review, and legal-cost reconciliation each need a separate source map and real MariaDB tests. Laravel has two-process proofs for credit reservation, same-installment capture, and same-payment correction; gateway/refund/settlement races remain open.
