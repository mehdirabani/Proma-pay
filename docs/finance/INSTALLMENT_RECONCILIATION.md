# Installment Reconciliation

`InstallmentReconciliationService::scan()` remains the canonical, read-only detector for installment materialization and allocation findings. The Financial Reconciliation Center calls this service and adds ledger evidence and an auditable case workflow; it does not create a second installment repair engine.

The reconciliation service detects:

- paid totals above installment amount;
- duplicate allocation evidence;
- paid rows with non-zero payable state;
- cancelled contracts with payable installments;
- completed contracts with active debt.

Detection is read-only. The legacy `apply(array $issues)` API rejects scan snapshots so stale findings cannot be replayed as write commands. For the supported `installment_materialization` finding, an operator or administrator must open a review and submit a proposal. A different administrator approves it, then an administrator executes it. The canonical service locks the contract and source rows, checks a fresh fingerprint and approved-as-of date, recomputes the official installment state, and updates only the materialized installment fields inside the caller's transaction. Contract completion is synchronized and the result is rechecked before the transaction commits; audit failure rolls the operation back.

The workflow never rewrites payments, allocations, payment groups, or Core Ledger rows. Approved payment history is evidence and remains immutable. See [Financial Reconciliation Center](FINANCIAL_RECONCILIATION_CENTER.md) for the ledger scope, historical coverage limits, and run behavior.
