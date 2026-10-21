# Plugin extension API 1.1 — roles, permissions, routes, menus

The Core loads an active plugin's `PluginServiceProviderInterface` provider from `plugin.json`. The provider owns its role, navigation, handlers and assets; no Core file needs to name the plugin's routes or CSS. `plugin_api_version: "1.1"` requires a Core with API 1.1 or newer. Older plugins targeting API 1.0 remain supported.

## Registration contract

Register extensions in `register(PluginManager $manager, array $manifest)`:

```php
$manager->registerRole($manifest['id'], 'repair_technician', [
    'label' => 'تعمیرکار',
    'landing_route' => 'plugin/repair/dashboard',
    'login_guard' => TechnicianSession::class . '::canLogin', // optional
]);
$manager->registerRoute($manifest['id'], 'customer/repairs',
    RepairController::class . '@customerRepairs', ['method' => 'GET', 'auth' => true]);
$manager->registerMenu($manifest['id'], [
    'route' => 'customer/repairs', 'label' => 'تعمیرات', 'roles' => ['customer'],
]);
```

Role keys use lowercase ASCII letters, digits and `_`, begin with a letter, and fit the 30-character `users.role` field. A plugin cannot replace a built-in role or another plugin's role. Re-registering the same definition is harmless; conflicting definitions fail. After installation, plugin roles appear in user-management forms so accounts can be prepared before activation. Login, route access and permissions remain disabled until activation. `PluginManager::assignableRoles()` and `roleAssignable($role)` govern assignment; `availableRoles()`, `roleAvailable($role)` and `roleLandingRoute($role)` govern active runtime access. Built-in roles remain in `config/settings.php`; plugins must not edit that file.

An optional static `login_guard` callable receives the fetched Core user row and returns a boolean. PromaRepair uses it to require an active technician profile before the ordinary login can create a session. Existing sessions are also checked by the plugin on each request.

Declare plugin permissions in the manifest's `permissions` list (`key` and `label`). The installer and updater upsert these idempotently. Use keys like `plugin.proma-repair.view_orders`; roles receive grants through `system_plugin_role_permissions`. The server checks permissions with `PluginManager::requirePermission($key)` or `PluginManager::can($key)`. Even administrator access to a plugin permission is denied while that plugin is inactive. Assigning a plugin role to a user must be rejected while its plugin is inactive; existing users retain their stored role.

Register each method/path combination once with `registerRoute`. Repeating an identical registration is harmless; a conflicting handler is rejected. Route options support `method`, `auth` and `permission`. Restrict object access in the handler as well: the repair customer detail query includes both repair ID and authenticated `customer_id`. Use `ErrorHandler::abort(403)` for forbidden operations, `abort(404)` for an absent or foreign customer object, and `abort(422)` for invalid input. `Router` preserves these HTTP statuses rather than converting them to a generic 404. Write handlers must verify CSRF (`Controller::onlyPost()` or `Csrf::verify()`). Hiding a menu never substitutes for route authorization.

`registerMenu` accepts `route`, `label`, `roles`, `permission`, `icon`, `group_label` and `group_icon`. An omitted/empty `roles` list means administrator-only visibility; an explicit list scopes the item. The Core discards menu items of inactive plugins. Repeat registration does not duplicate an identical item. The manifest's `assets` list contains plugin-relative CSS/JS paths; Core loads them only for a matching active plugin route. Do not override `auth/login`, `users` or other Core routes to introduce a role.

For an optional customer dashboard card, register a `dashboard.customer.widgets` filter in `boot()`. It receives `['value' => $widgets, 'context' => ['customer_id' => $id]]` and returns the augmented array. Each card has `title`, `description`, `icon`, `route` and `action_label`. Check that the owning plugin is still active inside a long-lived listener; keep queries scoped to the supplied customer ID. This hook is presentation-only: the linked route must enforce authentication and ownership independently.

## Lifecycle and deployment

1. Install from the normal plugin installer. Controlled plugin migrations and permission upserts run; registration is not a page-request migration.
2. The installed provider's role is selectable in the ordinary administrator user form. Activate the plugin to enable technician login, routes, menus and permission grants; existing technician accounts can be prepared before this step.
3. Update through the plugin updater. Increase the plugin version, run forward-only migrations, and upsert new permissions. Repeated provider registration remains idempotent.
4. Deactivate. Routes, menu items, role login and plugin permissions become unavailable immediately. Do **not** delete or rewrite users, repairs or financial history. Reactivation restores availability with existing data.

PromaRepair uses this contract and includes a real MariaDB regression test at `tests/integration_plugin_role_extension.php`. Run it only with `PROMA_TEST_ALLOW_DESTRUCTIVE=1` and a DSN whose database is exactly `proma_qa_v204`; it installs/upgrades the plugin and creates synthetic users and repairs in that disposable database.
