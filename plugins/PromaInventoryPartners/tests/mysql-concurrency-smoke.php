<?php

require_once dirname(__DIR__, 3) . '/core/Model.php';
require_once __DIR__ . '/../src/Domain/Money.php';
require_once __DIR__ . '/../src/Domain/ProfitCalculator.php';
foreach (['AuditService', 'LedgerService', 'OwnershipService', 'CatalogService', 'ProductService', 'PartnerService', 'PurchaseService'] as $service) {
    require_once __DIR__ . '/../src/Services/' . $service . '.php';
}

use Proma\Plugins\InventoryPartners\Services\PartnerService;
use Proma\Plugins\InventoryPartners\Services\CatalogService;
use Proma\Plugins\InventoryPartners\Services\ProductService;
use Proma\Plugins\InventoryPartners\Services\PurchaseService;

$database = (string) \Model::fetch('SELECT DATABASE() AS name')['name'];
if (!preg_match('/^(pip_test_|test_)/', $database)) {
    throw new RuntimeException('Refusing to run concurrency writes outside a test database.');
}
if (!function_exists('proc_open')) { throw new RuntimeException('proc_open is required for the concurrency test.'); }

\Model::execute("INSERT INTO users (full_name, mobile, status) VALUES ('PIP Race Operator', '09000000001', 'active')");
$actor = (int) \Model::lastInsertId();
$suffix = strtoupper(bin2hex(random_bytes(4)));
$warehouse = ProductService::createWarehouse(['code' => 'R' . $suffix, 'name' => 'انبار آزمون هم‌زمانی'], $actor);
$partnerA = PartnerService::create(['partner_code' => 'RA' . $suffix, 'full_name' => 'شریک اول آزمون هم‌زمانی'], $actor);
$partnerB = PartnerService::create(['partner_code' => 'RB' . $suffix, 'full_name' => 'شریک دوم آزمون هم‌زمانی'], $actor);
$category = CatalogService::create('category', 'دسته آزمون هم‌زمانی ' . $suffix, $actor);
$product = ProductService::create([
    'name' => 'کالای آخرین موجودی ' . $suffix, 'category_id' => $category, 'product_type' => 'accessory', 'tracking_mode' => 'quantity',
    'reference_purchase_toman' => '1000', 'market_toman' => '2000', 'sale_toman' => '2000',
    'minimum_sale_toman' => '0', 'minimum_stock' => '0',
], $actor);
$shares = [
    'capital_shares' => [$partnerA => '50', $partnerB => '50'],
    'profit_shares' => [$partnerA => '50', $partnerB => '50'],
];
$purchaseId = PurchaseService::create([
    'product_id' => $product, 'warehouse_id' => $warehouse, 'quantity' => '1',
    'unit_purchase_toman' => '1000', 'expenses_toman' => '0', 'purchase_date' => date('Y-m-d'),
    'idempotency_key' => 'race-purchase-' . $suffix,
] + $shares, $actor);
$lot = \Model::fetch('SELECT id FROM plugin_inventory_stock_lots WHERE purchase_id = ?', [$purchaseId]);
$payload = [
    'product_id' => $product, 'lot_id' => (int) $lot['id'], 'warehouse_id' => $warehouse,
    'actor_id' => $actor, 'idempotency_key' => 'race-sale-a-' . $suffix,
];
$payloadTwo = $payload;
$payloadTwo['idempotency_key'] = 'race-sale-b-' . $suffix;
$worker = __DIR__ . '/mysql-sale-race-worker.php';
$command = [PHP_BINARY, $worker];
$descriptors = [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
$processA = proc_open(array_merge($command, [json_encode($payload, JSON_THROW_ON_ERROR)]), $descriptors, $pipesA);
$processB = proc_open(array_merge($command, [json_encode($payloadTwo, JSON_THROW_ON_ERROR)]), $descriptors, $pipesB);
if (!is_resource($processA) || !is_resource($processB)) { throw new RuntimeException('Could not start both sale workers.'); }
fclose($pipesA[0]); fclose($pipesB[0]);
$stdoutA = stream_get_contents($pipesA[1]); $stderrA = stream_get_contents($pipesA[2]);
$stdoutB = stream_get_contents($pipesB[1]); $stderrB = stream_get_contents($pipesB[2]);
fclose($pipesA[1]); fclose($pipesA[2]); fclose($pipesB[1]); fclose($pipesB[2]);
$exitA = proc_close($processA); $exitB = proc_close($processB);
$resultA = json_decode($stdoutA, true); $resultB = json_decode($stdoutB, true);
if ($stderrA !== '' || $stderrB !== '' || !is_array($resultA) || !is_array($resultB)) {
    throw new RuntimeException('Sale worker failed: ' . trim($stderrA . ' ' . $stderrB . ' ' . $stdoutA . ' ' . $stdoutB));
}
$successes = (int) !empty($resultA['ok']) + (int) !empty($resultB['ok']);
$rejections = (int) !empty($resultA['rejected']) + (int) !empty($resultB['rejected']);
if ($successes !== 1 || $rejections !== 1 || !in_array($exitA, [0, 2], true) || !in_array($exitB, [0, 2], true)) {
    throw new RuntimeException('Expected one sale success and one stock rejection; got ' . $stdoutA . ' / ' . $stdoutB);
}
$saleCount = (int) \Model::fetch('SELECT COUNT(*) AS total FROM plugin_inventory_sale_lines WHERE lot_id = ?', [(int) $lot['id']])['total'];
if ($saleCount !== 1) { throw new RuntimeException('Concurrent sale created more than one sale line.'); }

echo "MySQL concurrency smoke passed: two concurrent sales against one unit produced one success and one rejection.\n";
