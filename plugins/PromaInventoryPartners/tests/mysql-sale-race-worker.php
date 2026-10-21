<?php

require_once dirname(__DIR__, 3) . '/core/Model.php';
require_once __DIR__ . '/../src/Domain/Money.php';
require_once __DIR__ . '/../src/Domain/ProfitCalculator.php';
foreach (['AuditService', 'LedgerService', 'OwnershipService', 'PurchaseService', 'SaleService'] as $service) {
    require_once __DIR__ . '/../src/Services/' . $service . '.php';
}

use Proma\Plugins\InventoryPartners\Services\SaleService;

$payload = json_decode((string) ($argv[1] ?? ''), true);
if (!is_array($payload)) { fwrite(STDERR, "Invalid worker payload.\n"); exit(1); }
if (!preg_match('/^(pip_test_|test_)/', (string) \Model::fetch('SELECT DATABASE() AS name')['name'])) {
    fwrite(STDERR, "Refusing to write outside a test database.\n"); exit(1);
}
try {
    $saleId = SaleService::create([
        'product_id' => (int) $payload['product_id'],
        'lot_id' => (int) $payload['lot_id'],
        'warehouse_id' => (int) $payload['warehouse_id'],
        'quantity' => '1',
        'unit_sale_toman' => '2000',
        'selling_expenses_toman' => '0',
        'payment_method' => 'cash',
        'payment_reference' => (string) $payload['idempotency_key'],
        'idempotency_key' => (string) $payload['idempotency_key'],
    ], (int) $payload['actor_id'], 'cash');
    echo json_encode(['ok' => true, 'sale_id' => $saleId], JSON_UNESCAPED_UNICODE);
    exit(0);
} catch (InvalidArgumentException $error) {
    echo json_encode(['ok' => false, 'rejected' => true, 'message' => $error->getMessage()], JSON_UNESCAPED_UNICODE);
    exit(2);
} catch (Throwable $error) {
    fwrite(STDERR, get_class($error) . ': ' . $error->getMessage() . "\n");
    exit(1);
}
