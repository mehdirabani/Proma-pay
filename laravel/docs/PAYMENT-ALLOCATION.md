# Payment allocation source map

This document records verified behavior in the current Legacy tree before the Laravel payment workflow is built. It does not claim that Laravel captures or allocates payments yet.

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
8. Successful group commit writes immutable child payment/allocation records and requires their amount sum to equal the received group amount. Legacy correction appends negative reversal allocations rather than editing the original allocation.

## Laravel port status

`App\Domain\Payments\PaymentAllocationPlanner` ports deterministic row ordering and the within-installment split for already-calculated financial state. Laravel does not yet calculate penalty/reward state, persist settlement quotes/groups/allocations, allocate legal-case costs, capture manual payments, or reconcile/reverse them. Do not call this planner directly with materialized display balances; its input contract is a canonical financial state from the not-yet-ported installment calculator.

## Explicit unresolved scope

The source supports several payment modes and both manual/gateway flows. Provider behavior, callbacks, refund/reversal caps, and legal-cost reconciliation each need a separate source map and real MariaDB tests. The v150 concurrency script demonstrates an InnoDB row lock, while the Laravel proof currently covers only contract credit reservation; payment-specific concurrent submissions remain open.
