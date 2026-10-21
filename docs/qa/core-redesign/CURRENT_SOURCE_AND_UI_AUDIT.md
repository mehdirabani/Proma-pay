# V1.3.8 Core Source and UI Audit

Status: implementation audit complete; browser and database regression evidence is recorded separately.

| Area | Route | Controller / View | Shared client code | Data / permissions | Defect found | Fix status |
| --- | --- | --- | --- | --- | --- | --- |
| Customer cards | `customers` | `CustomersController`, `views/customers/index.php` | `assets/js/app.js`, `assets/css/app.css` | users, admin/operator policy | Feather replacement could leave colored icon buttons without a visible glyph | local inline SVG actions added; card navigation excludes inner controls |
| Contract cards | `contracts` | `ContractsController`, `views/contracts/index.php` | app JS/CSS | contracts, installments; admin edit/cancel | action row forced horizontal scrolling and clipped cards | wrapping action layout and local chart/timeline icons added |
| Cancellation | `contracts/cancel/{id}` | `ContractsController::cancel`, `Contract::cancel` | modal manager | contracts, installments, payments, audit, outbox; admin | user could not distinguish cancellation from permanent deletion | transaction, idempotent status transition and explanatory modal audited; copy and action wording improved |
| Overdue | `overdue` | `OverdueController`, `views/overdue/index.php` | Ajax filters, modal manager | installments, operator calls; admin/operator | management branch omitted the contact trigger and phone URI was not canonical | shared call trigger, canonical `tel:+98...`, copy action, aligned filter bar |
| Calendar | `calendar` | `CalendarController`, `Event` | date/modal JS | events, notifications; role-aware | management merged automatic installment schedule into operational calendar | server-side role/type filtering; automatic installment events now merge only for customer portal |
| Medals | `medals` | `MedalsController`, `Medal`, `views/medals/index.php` | modal manager, app CSS | medal definitions, awards, history; admin | dense raw-JSON form and no management overview | summary, filter tabs, definition cards, history and batch synchronization added |
| File manager | `file-manager` | `FileManagerController`, `FileRecord`, `views/file-manager/index.php` | modal manager, app CSS | files, file_relations, file_audit_logs; admin | isolated directory listing, physical hard delete and leaked storage model | central registry, soft lifecycle, metadata, versions, relations and audit introduced |
| Upload sources | profile, payments, chat, legal, products, settings | UploadHelper and attachment models | n/a | secure uploads/public assets | uploaded files did not consistently enter a central registry | UploadHelper registers new files; identity/payment/chat/legal records create relations |

## Shared responsive and accessibility findings

- Card action rows now wrap inside their grid cell rather than scrolling horizontally.
- Icon-only controls have an inline SVG fallback, Persian title and accessible label.
- Modal content uses dynamic viewport height, scroll containment and accessible close controls.
- Filter bars align labels, selects and buttons to a common 44px control baseline and stack safely on small screens.
- Remaining pages must be visually checked in the V1.3.8 browser regression run before release.
