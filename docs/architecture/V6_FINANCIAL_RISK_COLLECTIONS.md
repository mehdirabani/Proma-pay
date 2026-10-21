# V6 financial, risk, and collection architecture

## Debt calculation and payment allocation

`FinancialDebtEngine` owns the contract-level receivable read model. The old `ContractFinancialSummaryService` methods delegate to it to preserve callers. It uses the existing installment financial state calculator, payment allocation quote, and chargeable legal-cost summary. Payment creation continues through the existing quoted `PaymentGroupService`, which locks the contract and verifies the quote before writing child allocations. Allocation priority remains the configured legacy policy; this release does not change it.

## Event journal

`FinancialLedgerService` appends immutable event headers and signed, integer-Toman component lines within the current business transaction. It records supported paid payment allocations, approved customer-chargeable legal costs for referred cases, and reversals of events created by V6. Unique idempotency keys make retries safe. The service refuses to write outside a business transaction.

The journal is an audit trail for new supported events. It is not the source of all historical receivable state yet: V5.x data has no opening snapshot, some legacy mutations have no component allocation rows, and time-based late-fee calculations are read from existing policy/state. Use `FinancialDebtEngine::summarize()` for current contractual balances until a reviewed backfill and reconciliation process is delivered.

## Risk score

`CreditRiskEngine` builds the score from contracts, completed installments, payment dates, legal cases, old payment promises, and the current debt read model. Each score change has a reason. Rules are stored as validated JSON settings that only an administrator can change. The suggested credit limit, down payment, term, and guarantor flag are informational only; the system does not approve or reject a contract from the score.

Unkept promises are explicitly an estimate based on a past promise date and a missing later recorded payment on that contract. Check returns are not scored because no verified returned-check feed exists in this installation.

## Collection queues

The collection dashboard reuses `OverdueAggregationService` and `LegalEligibilityService`, avoiding a second legal-eligibility formula. `collection_stage_rules_json` controls stage cutoffs. A token-protected, lock-guarded `cron/collection-stages` route can run the progression daily; admins can also trigger it from the collection center. It creates at most one task per contract/stage/oldest due date and sends idempotent in-app notifications. Closing a task is audited and permission-checked. Contract cancellation and legal case closure remain separate source states.

The contract timeline combines recorded installment due dates, payment records, operator calls/promises, legal cases, and V6 ledger events. It shows only events that exist in the database; it does not infer that an SMS or court action occurred.

## Announcements and event-driven notices

Existing workflow notifications continue to be created at their payment, legal, identity, profile, calendar, messaging, repair, and overdue action points. Administrators can turn notification types on or off in Settings without changing the underlying business record. Global announcements are stored once and associated with their intended active recipients; the read state is per recipient. Announcement links accept internal route slugs only.

## Allocation priority setting

Settings can choose whether contract-wide payment forms default to the existing `contract_legal_first` scope. With this enabled, approved referred legal costs consume the payment first and the remainder follows the existing installment allocation policy. This is a configurable default for payment entry; the component ordering within installment rows remains the established policy (legal late fee, normal late fee, then principal) and is not a freely editable permutation in V6.

## Signature in installment contracts

The existing `contract_seller_signature_path` setting is managed by the admin contract settings screen. `ContractDocument::injectSellerSignature()` adds that configured signature to generated and persisted print bodies in the seller area. The signature image is not copied into historical contracts when the manager later changes the configured file; current print rendering uses the configured seller signature where supported by the existing document engine.
