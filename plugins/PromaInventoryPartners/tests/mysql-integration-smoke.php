<?php

require_once dirname(__DIR__, 3) . '/core/Model.php';
require_once __DIR__ . '/../src/Domain/Money.php';
require_once __DIR__ . '/../src/Domain/ProfitCalculator.php';
foreach (['AuditService', 'LedgerService', 'OwnershipService', 'CatalogService', 'ProductService', 'ExpenseService', 'PartnerService', 'PurchaseService', 'SaleService', 'SettlementService', 'StockService', 'ReportingService'] as $service) {
    require_once __DIR__ . '/../src/Services/' . $service . '.php';
}

use Proma\Plugins\InventoryPartners\Domain\Money;
use Proma\Plugins\InventoryPartners\Services\PartnerService;
use Proma\Plugins\InventoryPartners\Services\CatalogService;
use Proma\Plugins\InventoryPartners\Services\ExpenseService;
use Proma\Plugins\InventoryPartners\Services\ProductService;
use Proma\Plugins\InventoryPartners\Services\PurchaseService;
use Proma\Plugins\InventoryPartners\Services\ReportingService;
use Proma\Plugins\InventoryPartners\Services\SaleService;
use Proma\Plugins\InventoryPartners\Services\SettlementService;
use Proma\Plugins\InventoryPartners\Services\StockService;

function pipMysqlSame($expected, $actual, $label)
{
    if ($expected !== $actual) {
        throw new RuntimeException($label . ': expected ' . var_export($expected, true) . ', got ' . var_export($actual, true));
    }
}

function pipMysqlThrows(callable $callback, $label)
{
    try {
        $callback();
    } catch (Throwable $error) {
        return;
    }
    throw new RuntimeException($label . ': expected an exception.');
}

$database = (string) \Model::fetch('SELECT DATABASE() AS name')['name'];
if (!preg_match('/^(pip_test_|test_)/', $database)) {
    throw new RuntimeException('Refusing to run integration writes outside a test database. Current schema: ' . $database);
}

\Model::execute("INSERT INTO users (full_name, mobile, status) VALUES ('PIP Test Operator', '09000000000', 'active')");
$actor = (int) \Model::lastInsertId();
\Model::execute("INSERT INTO users (full_name, mobile, status) VALUES ('PIP Test Customer', '09120000000', 'active')");
$customer = (int) \Model::lastInsertId();
$suffix = strtoupper(bin2hex(random_bytes(4)));
$warehouse = ProductService::createWarehouse(['code' => 'W' . $suffix, 'name' => 'انبار آزمون'], $actor);
$secondWarehouse = ProductService::createWarehouse(['code' => 'W2' . $suffix, 'name' => 'انبار مقصد آزمون'], $actor);
$partnerA = PartnerService::create(['partner_code' => 'A' . $suffix, 'full_name' => 'شریک اول آزمون'], $actor);
$partnerB = PartnerService::create(['partner_code' => 'B' . $suffix, 'full_name' => 'شریک دوم آزمون'], $actor);
$brand = CatalogService::create('brand', 'برند آزمون ' . $suffix, $actor);
$category = CatalogService::create('category', 'دسته آزمون ' . $suffix, $actor);
$catalogBrand = CatalogService::findActive('brand', $brand);
pipMysqlSame(1, count(CatalogService::search('brand', 'برند آزمون ' . $suffix)), 'searchable brand catalog');
pipMysqlThrows(static function () use ($actor, $suffix) { CatalogService::create('brand', 'برند آزمون ' . $suffix, $actor); }, 'duplicate brand rejection');
$capitalShares = ['capital_shares' => [$partnerA => '60', $partnerB => '40']];
$profitShares = ['profit_shares' => [$partnerA => '60', $partnerB => '40']];
$expenseShares = ['expense_shares' => [$partnerA => '40', $partnerB => '60']];

$expenseData = [
    'expense_type' => 'rent', 'expense_date' => date('Y-m-d'), 'amount_toman' => '100000',
    'payment_method' => 'bank_transfer', 'payment_reference' => 'PIP-EXP-' . $suffix,
    'note' => 'هزینه اجاره آزمایشی', 'idempotency_key' => 'pip-expense-' . $suffix,
] + $expenseShares;
$expenseId = ExpenseService::create($expenseData, $actor);
pipMysqlSame($expenseId, ExpenseService::create($expenseData, $actor), 'expense idempotency');
$expenseAllocations = \Model::fetchAll('SELECT partner_id, amount_rial FROM plugin_inventory_expense_partner_allocations WHERE expense_id = ? ORDER BY partner_id', [$expenseId]);
pipMysqlSame((int) $partnerA, (int) $expenseAllocations[0]['partner_id'], 'expense allocation partner ordering');
pipMysqlSame(400000, Money::integer($expenseAllocations[0]['amount_rial']), '40 percent expense share');
pipMysqlSame(600000, Money::integer($expenseAllocations[1]['amount_rial']), '60 percent expense share');

$product = ProductService::create([
    'name' => 'کالای آزمون ' . $suffix, 'product_type' => 'digital', 'brand_id' => $brand, 'category_id' => $category,
    'model' => 'M-' . $suffix, 'color' => 'مشکی', 'storage' => '256GB', 'ram' => '8GB', 'tracking_mode' => 'quantity',
    'reference_purchase_toman' => '30000000', 'market_toman' => '36000000', 'sale_toman' => '36000000',
    'minimum_sale_toman' => '0', 'minimum_stock' => '0',
], $actor);
$sku = (string) \Model::fetch('SELECT sku FROM plugin_inventory_products WHERE id = ?', [$product])['sku'];
if (strpos($sku, 'DIGITAL_') !== 0 || strpos($sku, 'M-' . $suffix) === false || strpos($sku, '256GB') === false || strpos($sku, '8GB') === false) {
    throw new RuntimeException('Automatically generated SKU did not include normalized product attributes: ' . $sku);
}
$purchase = [
    'product_id' => $product, 'warehouse_id' => $warehouse, 'quantity' => '1',
    'unit_purchase_toman' => '30000000', 'expenses_toman' => '1000000',
    'purchase_date' => date('Y-m-d'), 'idempotency_key' => 'pip-purchase-' . $suffix,
] + $capitalShares + $profitShares;
$inventoryBeforePurchase = ReportingService::dashboard(true);
$purchaseId = PurchaseService::create($purchase, $actor);
$lot = \Model::fetch('SELECT id, on_hand_qty, unit_landed_cost_rial FROM plugin_inventory_stock_lots WHERE purchase_id = ?', [$purchaseId]);
pipMysqlSame(1, (int) $lot['on_hand_qty'], 'purchase stock');
pipMysqlSame(310000000, Money::integer($lot['unit_landed_cost_rial']), 'purchase landed cost');
$inventoryAfterPurchase = ReportingService::dashboard(true);
pipMysqlSame((int) $inventoryBeforePurchase['units'] + 1, (int) $inventoryAfterPurchase['units'], 'dashboard aggregated stock units');
pipMysqlSame(Money::add(Money::integer($inventoryBeforePurchase['stock_cost_rial']), 310000000), Money::integer($inventoryAfterPurchase['stock_cost_rial']), 'dashboard aggregated landed cost');
pipMysqlSame(Money::add(Money::integer($inventoryBeforePurchase['market_value_rial']), 360000000), Money::integer($inventoryAfterPurchase['market_value_rial']), 'dashboard aggregated market value');
pipMysqlSame(Money::add(Money::integer($inventoryBeforePurchase['planned_sale_value_rial']), 360000000), Money::integer($inventoryAfterPurchase['planned_sale_value_rial']), 'dashboard aggregated planned sales value');

$saleData = [
    'product_id' => $product, 'lot_id' => (int) $lot['id'], 'warehouse_id' => $warehouse,
    'quantity' => '1', 'unit_sale_toman' => '36000000', 'selling_expenses_toman' => '0',
    'payment_method' => 'cash', 'payment_reference' => 'PIP-' . $suffix, 'idempotency_key' => 'pip-sale-' . $suffix,
];
$saleId = SaleService::create($saleData, $actor, 'cash');
pipMysqlSame(50000000, Money::integer(\Model::fetch('SELECT net_profit_rial FROM plugin_inventory_sales WHERE id = ?', [$saleId])['net_profit_rial']), 'cash sale net profit');
pipMysqlSame(360000000, SettlementService::payableBalance($partnerA) + SettlementService::payableBalance($partnerB), 'cash sale partner payable');
pipMysqlThrows(static function () use ($saleData, $actor) {
    $saleData['idempotency_key'] = 'pip-sale-overstock-' . bin2hex(random_bytes(4));
    SaleService::create($saleData, $actor, 'cash');
}, 'oversell prevention');

$settlementKey = 'pip-settlement-' . $suffix;
$settlementId = SettlementService::request($partnerA, 200000000, 'آزمون تسویه', $actor, $settlementKey);
pipMysqlSame($settlementId, SettlementService::request($partnerA, 200000000, 'آزمون تکرار', $actor, $settlementKey), 'settlement idempotency');
SettlementService::approve($settlementId, $actor);
SettlementService::pay($settlementId, 'bank_transfer', 'PIP-TRANSFER-' . $suffix, $actor);
pipMysqlSame('paid', (string) \Model::fetch('SELECT status FROM plugin_inventory_settlement_requests WHERE id = ?', [$settlementId])['status'], 'settlement payment');
pipMysqlThrows(static function () use ($settlementId, $actor) {
    SettlementService::pay($settlementId, 'bank_transfer', 'PIP-DUPLICATE', $actor);
}, 'duplicate settlement payment prevention');

$transferPurchase = $purchase;
$transferPurchase['quantity'] = '2';
$transferPurchase['expenses_toman'] = '0';
$transferPurchase['idempotency_key'] = 'pip-purchase-transfer-' . $suffix;
$transferPurchaseId = PurchaseService::create($transferPurchase, $actor);
$transferLot = \Model::fetch('SELECT id FROM plugin_inventory_stock_lots WHERE purchase_id = ?', [$transferPurchaseId]);
$transferId = StockService::createTransfer([
    'lot_id' => (int) $transferLot['id'], 'from_warehouse_id' => $warehouse,
    'to_warehouse_id' => $secondWarehouse, 'quantity' => '1', 'idempotency_key' => 'pip-transfer-' . $suffix,
], $actor);
pipMysqlSame(1, (int) \Model::fetch('SELECT reserved_qty FROM plugin_inventory_stock_lots WHERE id = ?', [(int) $transferLot['id']])['reserved_qty'], 'transfer reservation');
StockService::receiveTransfer($transferId, $actor);
pipMysqlSame('received', (string) \Model::fetch('SELECT status FROM plugin_inventory_transfers WHERE id = ?', [$transferId])['status'], 'transfer receive');
pipMysqlSame(2, (int) \Model::fetch('SELECT SUM(on_hand_qty) AS total FROM plugin_inventory_stock_lots WHERE purchase_id = ?', [$transferPurchaseId])['total'], 'transfer preserves total stock');
$adjustmentId = StockService::adjust([
    'product_id' => $product, 'lot_id' => (int) $transferLot['id'], 'warehouse_id' => $warehouse,
    'quantity' => '1', 'direction' => 'out', 'reason' => 'آزمون کسری', 'idempotency_key' => 'pip-adjust-' . $suffix,
], $actor);
if ((int) $adjustmentId <= 0) { throw new RuntimeException('Stock adjustment did not create its document.'); }
pipMysqlSame(1, (int) \Model::fetch('SELECT SUM(on_hand_qty) AS total FROM plugin_inventory_stock_lots WHERE purchase_id = ?', [$transferPurchaseId])['total'], 'stock adjustment changes on-hand with a document');

$contractProduct = ProductService::create([
    'name' => 'کالای قراردادی آزمون ' . $suffix, 'product_type' => 'mobile', 'brand_id' => $brand, 'category_id' => $category,
    'tracking_mode' => 'quantity',
    'reference_purchase_toman' => '30000000', 'market_toman' => '36000000', 'sale_toman' => '36000000',
    'minimum_sale_toman' => '0', 'minimum_stock' => '0',
], $actor);
$contractPurchase = $purchase;
$contractPurchase['product_id'] = $contractProduct;
$contractPurchase['expenses_toman'] = '0';
$contractPurchase['idempotency_key'] = 'pip-purchase-contract-' . $suffix;
$contractPurchase['capital_shares'] = [$partnerA => '40', $partnerB => '60'];
$contractPurchase['profit_shares'] = [$partnerA => '40', $partnerB => '60'];
$contractPurchaseId = PurchaseService::create($contractPurchase, $actor);
$contractLot = \Model::fetch('SELECT id FROM plugin_inventory_stock_lots WHERE purchase_id = ?', [$contractPurchaseId]);
\Model::execute('INSERT INTO contracts (customer_id, principal_amount, status) VALUES (?, ?, \'active\')', [$customer, '36000000.00']);
$contractId = (int) \Model::lastInsertId();
$contractSale = SaleService::create([
    'product_id' => $contractProduct, 'lot_id' => (int) $contractLot['id'], 'warehouse_id' => $warehouse,
    'quantity' => '1', 'unit_sale_toman' => '36000000', 'selling_expenses_toman' => '0',
    'contract_id' => $contractId, 'idempotency_key' => 'pip-contract-sale-' . $suffix,
    'capital_shares' => [$partnerA => '40', $partnerB => '60'],
    'profit_shares' => [$partnerA => '40', $partnerB => '60'],
], $actor, 'installment');
\Model::execute("INSERT INTO payments (contract_id, amount, status, is_corrected, payment_type, principal_applied) VALUES (?, '10000000.00', 'paid', 0, 'down_payment', NULL)", [$contractId]);
$paymentId = (int) \Model::lastInsertId();
pipMysqlSame(true, SaleService::importPayment($paymentId, $actor), 'installment down payment import');
pipMysqlSame(false, SaleService::importPayment($paymentId, $actor), 'payment import idempotency');
pipMysqlSame(100000000, Money::integer(\Model::fetch('SELECT collected_principal_rial FROM plugin_inventory_sales WHERE id = ?', [$contractSale])['collected_principal_rial']), 'installment collection amount');
$paymentShareLines = \Model::fetchAll("SELECT partner_id, SUM(credit_rial) AS allocated_rial FROM plugin_inventory_journal_lines WHERE journal_id = (SELECT id FROM plugin_inventory_journals WHERE event_key = ?) AND account_code = 'partner_capital_payable' GROUP BY partner_id ORDER BY partner_id", ['payment:' . $paymentId . ':capital']);
pipMysqlSame(40000000, Money::integer($paymentShareLines[0]['allocated_rial']), 'installment 40 percent partner allocation');
pipMysqlSame(60000000, Money::integer($paymentShareLines[1]['allocated_rial']), 'installment 60 percent partner allocation');
\Model::execute('UPDATE payments SET is_corrected = 1 WHERE id = ?', [$paymentId]);
SaleService::reconcileContract($contractId, $actor);
pipMysqlSame(0, Money::integer(\Model::fetch('SELECT collected_principal_rial FROM plugin_inventory_sales WHERE id = ?', [$contractSale])['collected_principal_rial']), 'corrected payment reversal');
\Model::execute("UPDATE contracts SET status = 'cancelled' WHERE id = ?", [$contractId]);
SaleService::returnFull($contractSale, 'آزمون برگشت کامل', '', $actor);
pipMysqlSame('refunded', (string) \Model::fetch('SELECT status FROM plugin_inventory_sales WHERE id = ?', [$contractSale])['status'], 'contract sale return');
pipMysqlSame(1, (int) \Model::fetch('SELECT on_hand_qty FROM plugin_inventory_stock_lots WHERE id = ?', [(int) $contractLot['id']])['on_hand_qty'], 'return restores stock');

$serialProduct = ProductService::create([
    'name' => 'کالای سریالی آزمون ' . $suffix, 'product_type' => 'accessory', 'brand_id' => $brand, 'category_id' => $category, 'tracking_mode' => 'serial',
    'reference_purchase_toman' => '30000000', 'market_toman' => '36000000', 'sale_toman' => '36000000',
    'minimum_sale_toman' => '0', 'minimum_stock' => '0',
], $actor);
$serialNumber = 'IMEI-' . $suffix;
$serialPurchase = $purchase;
$serialPurchase['product_id'] = $serialProduct;
$serialPurchase['serial_numbers'] = $serialNumber;
$serialPurchase['expenses_toman'] = '0';
$serialPurchase['idempotency_key'] = 'pip-purchase-serial-' . $suffix;
$serialPurchaseId = PurchaseService::create($serialPurchase, $actor);
$serialLot = \Model::fetch('SELECT id FROM plugin_inventory_stock_lots WHERE purchase_id = ?', [$serialPurchaseId]);
$serialSale = SaleService::create([
    'product_id' => $serialProduct, 'lot_id' => (int) $serialLot['id'], 'warehouse_id' => $warehouse,
    'quantity' => '1', 'unit_sale_toman' => '36000000', 'selling_expenses_toman' => '0',
    'payment_method' => 'cash', 'payment_reference' => 'PIP-SERIAL-' . $suffix,
    'serial_numbers' => $serialNumber, 'idempotency_key' => 'pip-sale-serial-' . $suffix,
], $actor, 'cash');
pipMysqlSame('sold', (string) \Model::fetch('SELECT status FROM plugin_inventory_serial_items WHERE serial_no = ?', [$serialNumber])['status'], 'serial item sale');
SaleService::returnFull($serialSale, 'آزمون برگشت کالای سریالی', 'PIP-REFUND-' . $suffix, $actor);
pipMysqlSame('in_stock', (string) \Model::fetch('SELECT status FROM plugin_inventory_serial_items WHERE serial_no = ?', [$serialNumber])['status'], 'serial return restores the unit');

foreach ([
    ReportingService::dashboard(true), ReportingService::products('', true), ReportingService::purchases(true),
    ReportingService::sales(true), ReportingService::partners(true), ReportingService::partners(false),
    ReportingService::partnerLedger($partnerA), ReportingService::settlements(true), ReportingService::transfers(),
    ReportingService::profitLoss('', ''), ReportingService::expenses(date('Y-m-d'), date('Y-m-d')), ReportingService::priceHistory($product), ReportingService::activity(),
    ReportingService::warehouses(), ReportingService::suppliers(), ReportingService::activeProducts(),
    ReportingService::activeWarehouses(), ReportingService::settings(), StockService::lots(true), StockService::movements(100, true),
] as $report) {
    if (!is_array($report)) { throw new RuntimeException('A reporting query returned an unexpected result.'); }
}

$unbalanced = \Model::fetch("SELECT COUNT(*) AS total FROM (SELECT j.id FROM plugin_inventory_journals j JOIN plugin_inventory_journal_lines l ON l.journal_id = j.id GROUP BY j.id HAVING SUM(l.debit_rial) <> SUM(l.credit_rial) OR SUM(l.debit_rial) = 0) broken");
pipMysqlSame(0, (int) $unbalanced['total'], 'all test journals are balanced');

echo "MySQL integration smoke passed: searchable catalogs, automatic SKU attributes, 40/60 expense allocation/idempotency, purchase/cash/serial sales, oversell guard, settlement, transfer, adjustment, 40/60 installment allocation/import/idempotency/reversal, returns, reports, and balanced ledger.\n";
