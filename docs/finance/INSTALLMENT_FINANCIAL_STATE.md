# Installment Financial State

`InstallmentSettlementService` is the authoritative gate for payable amount and payment permission.

- Paid amount is derived from approved successful allocations.
- Payable amount never falls below zero.
- A settled installment has payable amount zero and payment is forbidden.
- Penalty accumulation stops at the effective settlement date.
- Cancelled contracts cannot expose payable installments.
- A repeated settlement request returns the approved Persian domain error and creates no payment row.

Money remains stored and calculated using the existing decimal-safe money helpers.
