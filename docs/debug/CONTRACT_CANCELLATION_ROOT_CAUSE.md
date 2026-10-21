# Contract Cancellation Audit - V1.3.8

## Route and transaction path

`POST contracts/cancel/{id}` -> `ContractsController::cancel()` -> `Contract::cancel()`.

The controller requires the admin role, POST request, CSRF validation and the final confirmation field. The model locks the contract with `FOR UPDATE`, rejects empty reasons and completed contracts, returns idempotently for already-cancelled contracts, optionally creates payment corrections, cancels operational installments, writes a document/audit record and queues notification/plugin work after commit.

## Root cause category from reported behavior

The prior failure report is consistent with an unclear modal/confirmation flow and insufficient distinction between cancellation, correction and deletion. The existing data path already had a status transition, but the visual copy made it easy to expect payment reversal or permanent deletion.

## V1.3.8 safeguards

- cancellation modal explicitly states that financial history is preserved;
- destructive action is named `لغو قطعی قرارداد`;
- active, overdue, financial, document, legal and accounting counts remain visible before confirmation;
- the `cancelled` transition is idempotent, so a refresh/double click cannot re-cancel;
- payment correction stays opt-in and does not imply a bank refund;
- permanent test-record deletion remains on its separate endpoint.

## Required regression matrix

Run against an isolated database before release:

1. active contract without payment;
2. active contract with paid installment and correction unchecked (must block);
3. same contract with correction checked (must cancel and preserve history);
4. overdue contract;
5. contract with documents, guarantee, guarantor, legal case and plugin relation;
6. already cancelled contract (idempotent success);
7. completed contract (blocked);
8. eligible mistake/test contract deletion after cancellation changes.
