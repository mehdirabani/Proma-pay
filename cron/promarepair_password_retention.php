<?php

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$_SERVER['SCRIPT_NAME'] = '/index.php';
require __DIR__ . '/../bootstrap.php';

$plugin = PluginRegistry::find('proma-repair');
if (!$plugin || ($plugin['status'] ?? '') !== 'active') {
    echo json_encode(['ok' => true, 'skipped' => 'proma-repair-not-active'], JSON_UNESCAPED_UNICODE) . PHP_EOL;
    exit;
}

$root = __DIR__ . '/../plugins/PromaRepair/src';
require_once $root . '/Domain/RepairWorkflow.php';
require_once $root . '/Repositories/RepairRepository.php';
require_once $root . '/Repositories/RepairSettingsRepository.php';
require_once $root . '/Support/SecretCipher.php';
require_once $root . '/Services/RepairService.php';

$deleted = (new Proma\Plugins\Repair\Services\RepairService())->purgeExpiredPasswords();
echo json_encode(['ok' => true, 'expired_passwords_deleted' => $deleted], JSON_UNESCAPED_UNICODE) . PHP_EOL;
