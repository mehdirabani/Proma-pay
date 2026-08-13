<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
};

$appJs = (string) file_get_contents($root . '/assets/js/app.js');
$controller = (string) file_get_contents($root . '/controllers/ContractsController.php');
$router = (string) file_get_contents($root . '/core/Router.php');
$guard = (string) file_get_contents($root . '/core/RequestFloodGuard.php');

foreach ([
    'class ContractPreviewController',
    'contractPreviewInitialized',
    "proma:modal-closed",
    'window.setTimeout(() => this.requestPreview(key, payload), 650)',
    'this.cancelRequest();',
    'sequence !== this.sequence',
    'this.form.getClientRects().length > 0',
] as $token) {
    $assert(strpos($appJs, $token) !== false, 'Contract preview controller safeguard is missing: ' . $token);
}
$initStart = strpos($appJs, 'const initContractForms = function');
$initEnd = strpos($appJs, 'const initContractNewCustomerPopup', $initStart);
$initSource = substr($appJs, $initStart, $initEnd - $initStart);
$assert(strpos($initSource, 'updatePreview();') === false, 'Contract forms still request a preview during initialization.');
$assert(strpos($controller, "header('Cache-Control: no-store") !== false, 'Contract preview response is cacheable.');
$assert(strpos($controller, "\$preview['calculation_version'] = 'contract-finance-v1'") !== false, 'Contract preview calculation version is missing.');
$assert(strpos($router, '$route !== \'contracts/preview\'') !== false, 'Contract preview still boots all plugins.');
$assert(strpos($guard, "'key' => 'contract-preview'") !== false, 'Contract preview route does not have its own shared-host budget.');

echo "CONTRACT_PREVIEW_STORM_V149_OK\n";
