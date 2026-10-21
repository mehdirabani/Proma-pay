# Plugin extension API 1.1 — QA report (2026-10-03)

Scope: Core role/route/menu/permission extension and PromaRepair customer/technician integration. Core application version remains 2.0.3; the API contract is 1.1 and the repair plugin manifest is 1.1.6.

## Changed files in this feature

- Core contract and routing: `config/version.php`, `core/PluginManager.php`, `core/Auth.php`, `core/Router.php`.
- User and role management: `controllers/UsersController.php`, `models/User.php`, `helpers/functions.php`, `views/users/index.php`, `controllers/ProfileReviewsController.php`, `models/ProfileRequest.php`, `views/profile-reviews/index.php`.
- Customer navigation and dashboard extension point: `controllers/DashboardController.php`, `views/dashboard/customer.php`, `views/layouts/app.php`.
- Repair plugin integration: `plugins/PromaRepair/plugin.json`, `plugins/PromaRepair/src/RepairServiceProvider.php`, `plugins/PromaRepair/src/Support/TechnicianSession.php`, `plugins/PromaRepair/src/Controllers/CoreCompatibilityController.php`, `plugins/PromaRepair/src/Controllers/RepairController.php`.
- Contract, tests and report: `docs/PLUGIN_EXTENSION_API_1_1.md`, `docs/repair-management-architecture.md`, `tests/integration_plugin_role_extension.php`, `tests/promarepair_release_checks.php`, this report.

## Verified

- PHP syntax: 52 relevant Core, plugin, view and test files; 0 failures.
- `tests/integration_plugin_role_extension.php`: passed against the disposable MariaDB database `proma_qa_v204`. It covers active/inactive role lookup, role form and list, CSRF rejection (419), validation (422), ordinary password login, profile guard, role edits, route permission denial (403), customer ownership (404), customer list/detail, plugin assets, dashboard widget, repeated registration, existing-session revocation, deactivation data preservation and reactivation.
- `tests/promarepair_release_checks.php`: 29 checks passed.
- `tests/promarepair_domain_smoke.php`: passed.
- `tests/static_v1513_role_redesign.php`: passed.
- Existing static suite: 34 of 37 passed.

## Existing-worktree release blockers

The other three static tests currently fail outside the plugin extension work:

1. `static_v204_payment_scope_and_signature.php`: missing single-column mobile CSS for customer multi-payment selection.
2. `static_v204_mobile_customer_installments.php`: missing mobile cell wrapping CSS.
3. `static_v202_workspace_guarantors.php`: tracked `html/RTL/assets/js/editors/quill.js` is missing from the worktree.

The shared worktree also contains approximately 2,900 unrelated tracked deletions (mostly `html/RTL`) and unrelated CSS/accounting changes. These changes were not overwritten or staged. The HTTP role smoke test requires `PROMA_QA_BASE_URL` and was not run; no local demo server was launched. This report is not a production release approval. Do not build or publish installation/update ZIPs until the full suite and packaging checks pass on an agreed source tree.
