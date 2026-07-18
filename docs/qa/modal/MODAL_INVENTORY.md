# Core Modal Inventory - V1.3.8

| Module | Modal family | Trigger | Form/action | Shared behavior | Test status |
| --- | --- | --- | --- | --- | --- |
| Contracts | edit, custom installment, cancel, delete | contract cards | contract mutation, cancellation, safe delete | focus trap, Escape, dynamic-height body, mobile footer | PASSED: cancellation flow exercised end-to-end |
| Overdue | contact, details, payment, discount, operator, follow-up, legal | overdue table | call action, collection workflow | focus trap, local close icon, scrolling form body | PASSED: manager contact modal and copy binding verified |
| Calendar | add/delete event and Jalali date picker | calendar toolbar | event/reminder lifecycle | focus trap, date-picker interaction | PASSED: management calendar scope verified |
| File manager | metadata, replacement/relation, soft delete | file table | metadata/version/relation/status | focus trap, dynamic-height form | PASSED: upload, metadata dialog, archive and restore; mobile geometry checked |
| Medals | create/edit definition | medal cards/header | definition lifecycle | focus trap, two-column form, mobile stack | PASSED: redesigned screen rendered without console errors |
| Customers | add/edit/merge and card action dialogs | customer list/cards | customer lifecycle | shared JS hydration | PASSED: card action icons and long-name card layout verified |

All core modals use the common `.modal`, `.modal-content`, `.modal-header`, `.modal-body` and `.modal-footer` primitives. Plugin modals require a separate regression pass because they share the same CSS and JavaScript runtime.
