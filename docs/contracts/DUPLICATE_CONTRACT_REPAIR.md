# Duplicate Contract Repair

Contract creation uses a request UUID and request hash to ensure one request creates one contract.

Repair workflow:

1. Detect candidates using customer, product, amount and creation proximity.
2. Select the canonical contract.
3. Review installments and all payment/allocation records.
4. Refuse automatic cleanup when the duplicate has protected real payments.
5. Archive a safe duplicate and record a before/after snapshot in `contract_duplicate_repairs`.

Cancellation and duplicate repair are separate operations. Cancellation does not erase payment history.
