<?php
declare(strict_types=1);
$app = 'C:/laragon/www/proma-qa-clean-final-v402-20261008';
if (($argv[1] ?? '') === '--final-fresh') {
    $previousApp = $app;
    $app = 'C:/laragon/www/proma-qa-final-v403-to-v404-20261008';
    if (is_dir($app)) throw new RuntimeException('Refusing to overwrite the fresh final-package clone');
    $base = new ZipArchive();
    if ($base->open(dirname(__DIR__) . '/dist/candidates/v4.0.3/core/PromaPay-v4.0.3.zip') !== true) throw new RuntimeException('Missing verified 403 baseline');
    $base->extractTo($app); $base->close();
    copy($previousApp . '/config/database.php', $app . '/config/database.php');
    file_put_contents($app . '/installed.lock', 'qa=1');
    if (!is_dir($app . '/storage/updates')) mkdir($app . '/storage/updates', 0755, true);
}
$_SERVER['REQUEST_METHOD'] = 'CLI'; $_SERVER['REMOTE_ADDR'] = '198.51.100.46';
require $app . '/bootstrap.php';
if (Model::db()->query('SELECT DATABASE()')->fetchColumn() !== 'proma_qa_update_v403_final_20261008') throw new RuntimeException('Wrong update QA database');
$fingerprint = static function () { $hash = hash_init('sha256'); foreach (['users','contracts','payments','installments'] as $table) foreach (Model::fetchAll('SELECT * FROM `' . $table . '` ORDER BY id') as $row) hash_update($hash, json_encode($row)); return hash_final($hash); };
$before = $fingerprint();
$name = 'proma-update-2026-10-08-09-10-00-v404.zip';
copy(dirname(__DIR__) . '/dist/candidates/v4.0.4/updates/PromaPay-Update-v4.0.4.zip', $app . '/storage/updates/' . $name);
$result = ScriptUpdateService::install($name);
if (!hash_equals($before, $fingerprint())) throw new RuntimeException('Patch upgrade changed financial/user records');
$version = require $app . '/config/version.php';
if ($version['application'] !== '4.0.4') throw new RuntimeException('Patch version not applied');
echo "EXACT_PACKAGED_UPDATE_403_TO_404_OK_DATA_FINGERPRINT_PRESERVED\n";
echo 'FILES=' . count($result['installed_files'] ?? []) . "\n";
