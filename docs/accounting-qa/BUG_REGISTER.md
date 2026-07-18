# Accounting bug register

| ID | Severity | Status | Finding | Evidence / fix |
| --- | --- | --- | --- | --- |
| ACC-IDEMPOTENCY-001 | Critical | FIXED IN SOURCE / NOT EXECUTED | Manual ledger POST did not require a durable request UUID and did not reject changed-data reuse; a simultaneous duplicate request could also surface a duplicate-key error. | Added an atomic insert-or-read request record, request hash validation, durable completed linkage, and uncertain-state handling. |
| ACC-DB-001 | High | FIXED IN SOURCE / NOT EXECUTED | Account creation caught all database exceptions, masking failures. | Replaced catch-all with duplicate-key-safe insert. |
| ACC-TIMEOUT-001 | Critical | OPEN / BLOCKED | Production request timeout and commit state are not reproducible from this workspace. | Requires isolated staging plus production-safe timestamp/log correlation. |
| ACC-INTEGRATION-001 | High | OPEN | Accounting Core hooks execute synchronously; post-commit outbox isolation has not been implemented or tested. | Do not release until isolated and verified. |
