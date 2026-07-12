# Template Version Deletion

Deletion is available only to `contract_templates.delete_unused`. It requires POST, a valid CSRF token, a reason, and the confirmation checkbox.

Only non-current draft, superseded, or archived versions with zero generated-document references and zero historical/finalized document references can be physically deleted. Reference verification is fail-closed. The current published version is never deletable. Repeated deletion is idempotent.

The audit record is written inside the same transaction before deletion and stores actor, version metadata, reason, reference counts, time, and IP address.

