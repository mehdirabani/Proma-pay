<?php

require_once dirname(__DIR__, 3) . '/core/PluginManifest.php';
require_once dirname(__DIR__, 3) . '/core/Controller.php';
require_once __DIR__ . '/../src/Controllers/InventoryPartnersController.php';

$root = dirname(__DIR__);
$manifest = PluginManifest::read($root);
$expectedController = 'Proma\\Plugins\\InventoryPartners\\Controllers\\InventoryPartnersController';
$routes = 0;
$permissions = [];
$routePaths = [];

if ($manifest['id'] !== 'proma-inventory-partners') {
    throw new RuntimeException('Unexpected manifest identifier.');
}

foreach ($manifest['routes'] as $route) {
    if (empty($route['path']) || empty($route['handler']) || !in_array($route['method'] ?? '', ['GET', 'POST'], true)) {
        throw new RuntimeException('Route is missing its path, handler, or supported HTTP method.');
    }
    if (isset($routePaths[$route['method'] . ' ' . $route['path']])) {
        throw new RuntimeException('Duplicate route method/path: ' . $route['method'] . ' ' . $route['path']);
    }
    $routePaths[$route['method'] . ' ' . $route['path']] = true;
    if (empty($route['permission'])) {
        throw new RuntimeException('Route has no permission: ' . $route['path']);
    }
    if (strpos($route['handler'], $expectedController . '@') !== 0) {
        throw new RuntimeException('Route points outside the inventory controller: ' . $route['path']);
    }
    [, $method] = explode('@', $route['handler'], 2);
    if (!method_exists($expectedController, $method)) {
        throw new RuntimeException('Missing route handler method ' . $method . '.');
    }
    $routes++;
}

foreach ($manifest['permissions'] as $permission) {
    $key = (string) ($permission['key'] ?? '');
    if ($key === '' || isset($permissions[$key])) {
        throw new RuntimeException('Permission key is missing or duplicated.');
    }
    $permissions[$key] = true;
}
foreach ($manifest['routes'] as $route) {
    if (!isset($permissions[$route['permission']])) {
        throw new RuntimeException('Route refers to an undeclared permission: ' . $route['path']);
    }
}

foreach (array_merge($manifest['migrations'], $manifest['assets']) as $relativePath) {
    if (!is_file($root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath))) {
        throw new RuntimeException('Manifest file is missing: ' . $relativePath);
    }
}

$requiredRoutes = [
    'plugin/inventory-partners/expenses',
    'plugin/inventory-partners/expenses/create',
    'plugin/inventory-partners/settings/brand',
    'plugin/inventory-partners/settings/category',
    'api/plugin/inventory-partners/products',
    'api/plugin/inventory-partners/brands',
    'api/plugin/inventory-partners/categories',
    'api/plugin/inventory-partners/contracts',
];
foreach ($requiredRoutes as $path) {
    if (!isset($routePaths['GET ' . $path]) && !isset($routePaths['POST ' . $path])) {
        throw new RuntimeException('Required searchable catalog/expense route is missing: ' . $path);
    }
}

$migrationFiles = array_filter($manifest['migrations'], static function ($path) { return strtolower(substr($path, -4)) === '.sql'; });
if (count($migrationFiles) < 2) {
    throw new RuntimeException('Core and incremental migrations must both be declared.');
}
foreach ($migrationFiles as $relativePath) {
    $migration = file_get_contents($root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath));
    if (preg_match('/\b(?:FLOAT|DOUBLE)\b/i', $migration)) {
    throw new RuntimeException('Money columns must not use floating-point SQL types.');
    }
    if (preg_match('/CREATE\s+TABLE\s+IF\s+NOT\s+EXISTS\s+(?!plugin_inventory_)/i', $migration)) {
        throw new RuntimeException('Migration creates a table outside the plugin namespace.');
    }
}

$incrementalMigration = file_get_contents($root . '/migrations/2026_10_13_inventory_partners_v110.sql');
foreach (['plugin_inventory_brands', 'plugin_inventory_categories', 'plugin_inventory_expenses', 'plugin_inventory_expense_partner_allocations', 'product_type', 'category_id', 'brand_id', 'ram'] as $schemaFeature) {
    if (strpos($incrementalMigration, $schemaFeature) === false) {
        throw new RuntimeException('Incremental migration is missing expected schema feature: ' . $schemaFeature);
    }
}

echo count($manifest['permissions']) . ' permissions, ' . $routes . ' route handlers, catalog/expense schema, migrations, and assets verified.' . PHP_EOL;
