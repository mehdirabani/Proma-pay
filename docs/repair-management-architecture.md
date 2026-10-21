# Proma Pay Repair Management

## Integration decision

Proma Pay is a custom PHP application with an existing plugin manager, plugin migrations, role permissions, notifications, audit logging, user accounts, and an RTL app layout. Repair Management is therefore shipped as the `proma-repair` plugin and depends on the existing Plugin API. It does not create or copy customer/user records. `repair_orders.customer_id`, staff IDs, and audit actors reference existing `users.id` values.

## Architecture

`plugins/PromaRepair/` follows the existing plugin provider/controller/repository/service/view pattern:

- **Domain** owns repair statuses, allowed transitions, money totals, tracking codes, and customer-safe projections.
- **Application services** coordinate intake, status changes, assignment, finance posting, password access/retention, notifications, and audit events.
- **Repositories** use prepared SQL through the shared `Model` and keep device, assignment, diagnosis, charge, expense, payment, attachment, timeline, note, warranty, and audit data normalized.
- **Controllers/API** enforce login, plugin capability, CSRF for writes, customer ownership, assigned-technician scope, and response projections.
- **Views/assets** use the existing Proma Pay app layout, RTL language, Yekan Bakh inheritance, shared cards/forms/tables/buttons, and route-scoped plugin CSS.

## Permission matrix

| Capability | Admin | Operator | Assigned repair technician | Customer |
| --- | --- | --- | --- | --- |
| Intake and edit repair orders | Always | Plugin permission | No | No |
| View all repair orders | Always | Plugin permission | No | No |
| View/update assigned repairs and diagnosis | Always | Plugin permission | Assignment scope plus permission | No |
| Assign technicians | Always | Plugin permission | No | No |
| Record/view payments and expenses | Always | Separate finance permissions | Record assigned repair cost only | Own payment summary only |
| View profit | Always | Separate finance permission | No | No |
| Reveal device password | Always | Separate sensitive permission | Separate sensitive permission and assignment scope | Never |
| Manage settings, warranty, audit, technicians | Always | Separate capabilities | No | No |
| View repairs | Always | Plugin permission | Assignment scope | Own repair only |

Technicians are ordinary Core users with a plugin-registered `repair_technician` role; administrators may create them in the normal user form or the plugin technician screen. Password hashing, login and CSRF stay in Core. Plugin activation grants the role and deactivation revokes access without deleting users or repairs. Assignment scope is checked in application services in addition to plugin role permissions; a technician cannot browse by changing a repair ID. The formal API and lifecycle are documented in `docs/PLUGIN_EXTENSION_API_1_1.md`.

## Workflow

`received → waiting_inspection → diagnosing → waiting_customer_approval → approved → repairing → waiting_parts → repair_completed → quality_control → ready_for_delivery → delivered`

`cancelled` and `unable_to_repair` are terminal paths. A transition service validates every edge, writes status history and audit data, and sends a core notification. Customer decisions are transactional so an approval can create its service charge only once. Customer labels are a separate allow-listed mapping.

## UI map

- Repair dashboard: operational counts, finance summaries (capability-gated), overdue items, ready-for-delivery, unassigned repairs, debts, and technician workload.
- Intake: existing-customer lookup by normalized phone, customer selection, device/condition/accessory checklist, customer complaint, and optional encrypted credential/photos.
- Orders/detail: searchable responsive cards/table, overview/device/diagnosis/finance/attachments/notes/timeline/audit tabs, with actions hidden and rechecked server-side by capability.
- Technician management: register existing active Proma staff accounts; assignments retain history.
- Customer panel: an integrated `تعمیرات من` page and dashboard card; customer responses omit password, internal notes, technician costs, and profit, and mask IMEI.
- Public tracking: tracking code plus customer mobile, rate-limited, with only public status/estimated delivery/customer-approved total.
- Receipt: A5 print stylesheet, configurable business details/terms, recorded print audit, and a separately editable union text field with editor/version metadata. No official union wording is supplied by the plugin.
- Settings/reports/audit: grouped settings, operational/financial/technician/device aggregates, append-only module audit history.

## API map

Versioned route family under `plugin/repair/api/`: repairs list/create/show/update, status transition, assignment, payments, expenses, customer repairs/detail, and public tracking. Each route declares method and permission in the plugin manifest; controllers additionally enforce object scope and return explicit projections. Public tracking has its own throttling table and does not use a general repair API response.

## Privacy and security

- Device secrets use AES-256-GCM with a random per-install key under `storage/private`; ciphertext and key never enter logs, HTML, or API projections. Reveal is a POST protected by CSRF and its audit event records actor, IP, repair, and time. Default retention is immediate on delivery.
- Attachments use an image-only upload service and authenticated, owner-checked serving; uploads are limited by size and dimensions and stored under the private directory.
- Charges/payments/expenses are traceable, soft-voided rather than deleted, and financial changes are audited with the void reason.
- Tracking attempts use a persistent fixed-window throttle per IP/code and an IP-wide cap. Matching also requires normalized customer mobile.
- Password retention supports immediate deletion (`0`), delayed deletion (`1..3650` days), or manual deletion (`-1`); manual deletion has a separate capability and audit event.
- Delayed cleanup runs when repair routes are used and through the CLI job `cron/promarepair_password_retention.php`; production should schedule the CLI job daily.
- Warranty returns create a new repair linked to its original order, and intake revalidates customer ownership and the active warranty period.
- Customer queries are constrained by `customer_id`; IMEI is masked; internal costs, notes, password, and profit are absent from the public projection.

## Delivered modules

The plugin includes the schema, capabilities, dashboard/intake/orders/detail, workflow, technician registry/assignment, customer approval, notes, private image attachments, charges/expenses/payments, soft voids, financial summaries/reports, receipt printing, public tracking, customer dashboard integration, customer repair pages/API, app notifications, warranty returns, audit review, and API write protections.

The schema is forward-migrated by the plugin manager. Uninstall preserves operational/financial records; purge is refused because these records carry financial and service history.
