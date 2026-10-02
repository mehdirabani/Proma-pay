# FinTech Invariant Module

## Activation
Runtime activation comes only from `manifests/module-registry.yaml`; this document does not route itself.

## Core decision guidance
Core invariants: money is not float; a logical financial command must not apply twice; balances, credit and refunds must obey explicit invariants; financial state changes need auditability.

Decision tree: identify financial state → write invariant → identify transaction identity → idempotency → concurrency/isolation → allowed state transition → retry/timeout ambiguity → audit trail → recovery test.

Event model: delivery can duplicate, arrive late, arrive out of order, or time out after external success. Exactly-once transport must never be assumed.

Forbidden shortcuts: blind retry, mutable transaction identity, float money, refund beyond captured amount, settlement without reconciliation.

## Failure modes
- `payment_mutation`: operation creates or changes a payment or transaction state
- `balance_invariant`: balance must respect domain constraints such as not becoming negative without overdraft
- `double_spend`: concurrent operations can spend the same available funds more than once
- `duplicate_charge`: customer may be charged more than once for one logical purchase
- `idempotency`: repeated delivery of same command must not apply financial effect more than once
- `settlement`: settlement aggregates eligible confirmed financial records and must preserve totals
- `reconciliation`: internal records must be reconciled with external processor or bank evidence
- `refund`: refund reverses captured value and must not exceed eligible captured amount
- `capture`: authorized funds are captured into a final charge
- `void`: authorization is cancelled before capture and invalid later transitions must be rejected
- `ledger`: financial ledger records durable auditable entries and balances derive from entries
- `credit_limit`: credit exposure must not exceed approved limit under concurrent updates
- `installment_state`: installment schedules and paid overdue remaining states must remain consistent
- `event_ordering`: financial events can arrive late duplicated or out of order

## Preferred APIs
Use repository-confirmed public APIs and existing project conventions.

## Version-sensitive rules
Never assume latest. Prefer runtime/lock evidence, then manifests, then source evidence.

## Verification checklist
- financial_invariant_test
- idempotency_analysis
- concurrency_analysis

## Escalation / cross-domain
Activate related modules from concept metadata and risk policy; high-risk work requires stronger verification and lower autonomy.
