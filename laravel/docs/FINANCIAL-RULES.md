# Financial rule controls

## Source evidence and configuration

The Legacy tree contains conflicting penalty defaults. `database/migrations/2026_07_09_legal_penalty_settings.sql` sets a legal rate of 4, while `database/migrations/2026_07_09_contract_print_header_customer_fields.sql` contains normal/legal rates of 10/20 and update statements with 0/2 or 0/4. These files do not establish the values currently approved for production. Laravel therefore migrates one singleton `financial_rules` row with every rate and the grace period set to `NULL`; calculation must not silently substitute a guessed rate.

Before any settlement quote or payment capture is implemented, an authorized policy owner must enter the approved values and the corresponding approval must be retained outside this screen. The current application does not verify that external approval and does not require a second approver for rule changes.

## Current Laravel behavior

- `financial_rules` holds the currently selected rates, grace period, monotonically increasing version, and last updating user.
- `financial_rule_history` stores each previous/current snapshot, actor, request UUID, payload hash, and version. The Eloquent model rejects updates and deletes; this is an application-level guard, not a database account/trigger immutability guarantee.
- `finance.rules.manage` is granted only to the seeded `administrator` role. Both the settings page and update endpoint enforce this permission server-side.
- Persian and Arabic digits are converted before validation. Rates are percentages from 0 through 100 with at most four decimal places; the grace period is an integer from 0 through 365 days.
- A request UUID is idempotent for an identical actor and payload. Reusing it for a different payload returns a validation error. The rules row, history, and audit event are written in one transaction.
- Rates are persisted as decimal text to preserve the exact validated decimal; the calculator converts them to integer rate units. It does not use floating-point money arithmetic.
- Zero is an explicit policy value and differs from unconfigured `NULL`.

## Not implemented

Settlement quotes do not yet load or snapshot this row, and payment capture does not yet lock/revalidate its version. No current production policy values have been established. Legal cost allocation, penalty waiver/adjustment, approval workflow for policy changes, and database-level immutability controls also remain open. Until those gaps are closed, this page is only a governed configuration foundation and is not evidence that financial collection is ready for production.
