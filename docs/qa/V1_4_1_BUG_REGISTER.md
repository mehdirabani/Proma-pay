# V1.4.1 Bug Register

| ID | Severity | Finding | Resolution |
| --- | --- | --- | --- |
| V141-01 | Critical | Settled installment accepted repeated payment paths | Central settlement service and backend rejection |
| V141-02 | Critical | Duplicate contract repair could endanger real payments | Audited repair service refuses protected payment history |
| V141-03 | High | Plugin state diverged after failed lifecycle operations | Unified state machine, lock and reconciliation service |
| V141-04 | High | Runtime DDL hid incomplete installer schemas | SchemaGuard plus clean-install parity repair |
| V141-05 | High | Notification/chat state was not recipient/channel specific | Persistent recipient state and channel read cursors |
| V141-06 | Medium | Profile changes could not be partially approved safely | Per-field review snapshots and conflict handling |
| V141-07 | Medium | Calendar mobile view and reminder dedupe were incomplete | Mobile agenda, role visibility and dedupe key |
| V141-08 | Medium | Reversible medals lacked reliable revoke/restore history | Evaluation, history and reconciliation services |
| V141-09 | Medium | Header profile menu stayed invisible after activation | Accessible button, explicit open state and CSS visibility rule |
| V141-10 | Medium | Release updater could omit newly added source files | Git-baseline differential inventory and archive verification |

Open release-blocking defects: none after the final gate. Low-risk limitations are documented in the final test report.
