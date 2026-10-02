<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$read = static function (string $path) use ($root): string {
    $value = file_get_contents($root . '/' . $path);
    if ($value === false) throw new RuntimeException('Missing file: ' . $path);
    return $value;
};
$assert = static function (bool $condition, string $message): void {
    if (!$condition) throw new RuntimeException($message);
};

$document = $read('models/ContractDocument.php');
$quote = $read('helpers/SettlementQuoteService.php');
$group = $read('helpers/PaymentGroupService.php');
$gateway = $read('helpers/ZibalGatewayProvider.php');
$payments = $read('controllers/PaymentsController.php');
$view = $read('views/installments/index.php');
$contractView = $read('views/contracts/show.php');
$customerController = $read('controllers/CustomersController.php');
$fileManagerController = $read('controllers/FileManagerController.php');
$fileRecord = $read('models/FileRecord.php');
$styles = $read('assets/css/components/v2-system.css');
$install = $read('database/proma-pay-install.sql');
$migration = $read('database/migrations/2026_10_01_group_payment_allocation_scope.sql');
$customerFileStart = strpos($fileRecord, 'public static function forCustomer(');
$customerFileEnd = $customerFileStart === false ? false : strpos($fileRecord, 'public static function backfillStorage(', $customerFileStart);
$customerFileQuery = $customerFileStart !== false && $customerFileEnd !== false
    ? substr($fileRecord, $customerFileStart, $customerFileEnd - $customerFileStart)
    : '';

$assert(strpos($document, "'امضای مشتری'") !== false && strpos($document, "'امضای امانت‌دار'") === false, 'Generated contract signature label is still incorrect.');
$assert(strpos($quote, "['selected', 'contract', 'schedule']") !== false, 'The schedule-only overflow quote scope is missing.');
$assert(strpos($group, 'allocation_scope') !== false && strpos($group, "verifyLocked((string) (\$group['quote_uuid'] ?? ''), \$contract, \$rows, \$ids, \$amount, (int) \$group['customer_id'], \$scope)") !== false, 'Gateway completion does not re-verify the persisted allocation scope.');
$assert(strpos($gateway, "(\$context['settlement_scope'] ?? 'selected')") !== false, 'Gateway provider does not persist the payment allocation scope.');
$assert(strpos($payments, "\$_POST['selected_installment_ids']") !== false && strpos($payments, 'statesForRows($installments)') !== false, 'Group payment does not batch-check selected installments.');
$assert(strpos($view, "name=\"selected_installment_ids[]\"") !== false && strpos($view, "fetchQuote('schedule'") !== false, 'Customer payment UI does not explicitly expand overflow to the contract schedule.');
$assert(strpos($view, 'proma-v2-data-table proma-installment-list-table') !== false && strpos($view, 'data-label="قابل پرداخت"') !== false, 'Installment list is missing labeled responsive card markup.');
$assert(strpos($contractView, 'proma-v2-data-table proma-contract-installments-table') !== false && strpos($contractView, 'data-label="جزئیات مالی امروز"') !== false, 'Contract installment rows do not expose mobile-friendly labels.');
$assert(strpos($styles, '.proma-installment-group-items { grid-template-columns: minmax(0, 1fr); }') !== false, 'Customer multi-payment selection has no single-column mobile layout.');
$assert(strpos($customerController, 'public function identityDocument($id)') !== false && strpos($customerController, '$this->requireRole(\'admin\')') !== false, 'Identity-document stream is missing management authorization.');
$assert($customerFileQuery !== '' && strpos($customerFileQuery, 'storage_path') === false, 'Customer document listing exposes storage paths or has no customer query.');
$assert(substr_count($fileManagerController, "Cache-Control: private, no-store, max-age=0") >= 2, 'Managed file streaming is not marked private/no-store.');
$assert(strpos($install, '`allocation_scope` varchar(20)') !== false && strpos($migration, 'allocation_scope') !== false, 'Payment allocation scope is missing from fresh install or migration schema.');

echo "STATIC_V204_PAYMENT_SCOPE_AND_SIGNATURE_OK\n";
