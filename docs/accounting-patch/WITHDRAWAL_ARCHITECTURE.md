# Withdrawal architecture

Requests are stored in `customer_referral_withdrawals`. A request reserves its amount logically through `reserved_amount_toman`; only requested, under_review, approved and processing requests count as reserved. Payment and immutable ledger posting require the host's production financial account policy and are not claimed as executed in this isolated checkout.
