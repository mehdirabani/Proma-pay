# Guarantor customer-account workflow — 2.0.2

Contract create and update now resolve guarantors through one server-side service inside the existing contract transaction. A new complete guarantor record must contain a valid ten-digit national ID and mobile number. The service locks matching identities, rejects split/conflicting accounts, inactive/non-customer identities, mismatched national-ID/mobile pairs, and using the contract's customer as their own guarantor.

If no matching identity exists, it creates an active customer account. The existing user-creation rule supplies the initial password as the last four mobile digits. If the exact identity already belongs to an active customer, that account is linked without replacing its password. A conflict aborts the encompassing transaction, leaving neither a partial contract nor an orphan account. Unchanged older document-only guarantor snapshots remain readable during unrelated contract edits.

Document rendering correlates linked accounts with historical snapshots using normalized national ID and emits a guarantor once, while preserving snapshot data used by contract documents. The MariaDB integration test verifies new-account creation, existing-account reuse, password preservation, contract-link updates, invalid/self guarantors, conflict rejection and transaction rollback.
