<?php
declare(strict_types=1);
$live = ($argv[1] ?? '') === 'live';
$app = $live ? 'C:/laragon/www/proma' : 'C:/laragon/www/proma-qa-update-v403-20261008';
$expectedDb = $live ? 'proma_pay_restore_20261007' : 'proma_qa_update_v403_20261008';
$_SERVER['REQUEST_METHOD'] = 'CLI'; $_SERVER['REMOTE_ADDR'] = '198.51.100.47';
require $app . '/bootstrap.php';
if (Model::db()->query('SELECT DATABASE()')->fetchColumn() !== $expectedDb) throw new RuntimeException('Wrong local update database');
$version = require $app . '/config/version.php';
if ($version['application'] !== '4.0.2') throw new RuntimeException('Local patch requires exact V4.0.2');
$fingerprint = static function () {
    $hash = hash_init('sha256');
    foreach (Model::fetchAll('SELECT id,username,role,status,mobile,password_hash FROM users ORDER BY id') as $row) hash_update($hash, json_encode($row));
    foreach (['contracts','payments','installments'] as $table) foreach (Model::fetchAll('SELECT * FROM `' . $table . '` ORDER BY id') as $row) hash_update($hash, json_encode($row));
    return hash_final($hash);
};
$before = $fingerprint();
$name = 'proma-update-2026-10-08-09-20-00-v404-local.zip';
copy(dirname(__DIR__) . '/dist/candidates/v4.0.4/updates/PromaPay-Update-v4.0.4-local-v4.0.2.zip', $app . '/storage/updates/' . $name);
$result = ScriptUpdateService::install($name);
if (!hash_equals($before, $fingerprint())) throw new RuntimeException('Local patch changed business/credential fingerprints');
$version = require $app . '/config/version.php';
if ($version['application'] !== '4.0.4') throw new RuntimeException('Local patch version not applied');
file_put_contents(dirname(__DIR__) . '/storage/qa-v404-' . ($live ? 'local-install' : 'clone-update') . '-result.json', json_encode(['version' => '4.0.4', 'preserved_business_and_credential_fingerprint' => true, 'files' => count($result['installed_files']), 'migrations' => $result['migrations_count'], 'safety_backup' => $result['backup']], JSON_PRETTY_PRINT));
echo ($live ? 'LOCAL_INSTALL' : 'LOCAL_CLONE_UPDATE') . "_402_TO_404_OK_BUSINESS_AND_CREDENTIALS_PRESERVED\n";
echo 'FILES=' . count($result['installed_files']) . '; MIGRATIONS=' . $result['migrations_count'] . "\n";
