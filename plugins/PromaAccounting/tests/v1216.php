<?php

$root = dirname(__DIR__);
$manifest = json_decode((string) file_get_contents($root . '/plugin.json'), true);
if (!preg_match('/^\d+\.\d+\.\d+$/', (string) ($manifest['version'] ?? ''))
    || version_compare((string) $manifest['version'], '1.2.18', '<')) {
    fwrite(STDERR, "Accounting plugin version is older than the v1.2.18 regression baseline\n");
    exit(1);
}
$service = (string) file_get_contents($root . '/src/Services/AccountBalanceService.php');
foreach (['creditor', 'debtor', 'settled', 'accounting_user_accounts'] as $needle) {
    if (strpos($service, $needle) === false) {
        fwrite(STDERR, "missing balance semantic: {$needle}\n");
        exit(1);
    }
}
$ledger = (string) file_get_contents($root . '/src/Services/LedgerService.php');
if (strpos($ledger, "'admin','operator','lawyer','repair_technician'") === false || strpos($ledger, "'customer','repair_technician'") !== false) {
    fwrite(STDERR, "account roles must exclude customers\n");
    exit(1);
}
$route = null;
foreach (($manifest['routes'] ?? []) as $item) {
    if (($item['path'] ?? '') === 'plugin/accounting/my-finance') { $route = $item; break; }
}
if (!is_array($route) || (string) ($route['permission'] ?? 'x') !== '') {
    fwrite(STDERR, "self finance route must be authenticated without role permission\n");
    exit(1);
}
echo "PROMA_ACCOUNTING_V1216_OK\n";
