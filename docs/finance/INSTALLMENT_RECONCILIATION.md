# Installment Reconciliation

The reconciliation service detects:

- paid totals above installment amount;
- duplicate allocation evidence;
- paid rows with non-zero payable state;
- cancelled contracts with payable installments;
- completed contracts with active debt.

Detection is read-only by default. Corrections require an explicit operation, transaction ownership and audit context. Real approved payments are never deleted by duplicate-contract repair.
