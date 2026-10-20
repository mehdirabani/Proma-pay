<?php
declare(strict_types=1);
$mode = $argv[1] ?? 'init';
$app = 'C:/laragon/www/proma-qa-exact-v402-to-v404-20261008';
$database = 'proma_qa_exact_v402_to_v404_20261008';
$config = require 'C:/laragon/www/proma/config/database.php';
if ($mode === 'init') {
    $pdo = new PDO('mysql:host=' . $config['host'] . ';charset=utf8mb4', $config['username'], $config['password'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $check = $pdo->prepare('SELECT COUNT(*) FROM information_schema.SCHEMATA WHERE SCHEMA_NAME = ?'); $check->execute([$database]);
    $exists = (bool) $check->fetchColumn();
    if ($exists && ($argv[2] ?? '') === '--reset-created-qa' && is_file($app . '/installed.lock') && file_get_contents($app . '/installed.lock') === 'qa=1') {
        $pdo->exec('DROP DATABASE `' . $database . '`');
        $exists = false;
    }
    if ($exists && (($argv[2] ?? '') !== '--resume-empty' || $pdo->query("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = '$database'")->fetchColumn())) throw new RuntimeException('Refusing to overwrite exact-baseline QA');
    $reuse = is_dir($app) && in_array($argv[2] ?? '', ['--resume-empty','--reset-created-qa'], true) && file_get_contents($app . '/installed.lock') === 'qa=1';
    if (is_dir($app) && !$reuse) throw new RuntimeException('Refusing to overwrite exact-baseline checkout');
    if (!$exists) $pdo->exec('CREATE DATABASE `' . $database . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
    $zip = new ZipArchive();
    $basePath = dirname(__DIR__) . '/dist/candidates/v4.0.2/core/PromaPay-v4.0.2.zip';
    if ($zip->open($basePath) !== true) throw new RuntimeException('Missing official 402 baseline');
    if (!$reuse) $zip->extractTo($app); $zip->close();
    $config['database'] = $database;
    file_put_contents($app . '/config/database.php', "<?php\nreturn " . var_export($config, true) . ";\n");
    file_put_contents($app . '/installed.lock', 'qa=1');
    $_SERVER['REQUEST_METHOD'] = 'CLI'; $_SERVER['REMOTE_ADDR'] = '198.51.100.48';
    require dirname(__DIR__) . '/bootstrap.php';
    $pdo = new PDO('mysql:host=' . $config['host'] . ';dbname=' . $database . ';charset=utf8mb4', $config['username'], $config['password'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    (new ReflectionProperty(Model::class, 'pdo'))->setValue(null, $pdo);
    (new ReflectionMethod(BackupService::class, 'runSql'))->invoke(null, file_get_contents('C:/laragon/backup/proma-pay-v402-preupgrade-20261008-072958/qa-v402-before-password-mode-test.sql'));
    echo "EXACT_402_QA_CREATED\n";
    exit;
}
$_SERVER['REQUEST_METHOD'] = 'CLI'; $_SERVER['REMOTE_ADDR'] = '198.51.100.48'; require $app . '/bootstrap.php';
if (Model::db()->query('SELECT DATABASE()')->fetchColumn() !== $database) throw new RuntimeException('Wrong exact QA database');
$fingerprint = static function () {
    $h = hash_init('sha256'); foreach (Model::fetchAll('SELECT id,password_hash,username,role,status,mobile FROM users ORDER BY id') as $row) hash_update($h, json_encode($row));
    foreach (['contracts','payments','installments'] as $table) foreach (Model::fetchAll('SELECT * FROM `' . $table . '` ORDER BY id') as $row) hash_update($h, json_encode($row)); return hash_final($h);
};
$before = $fingerprint(); $name = 'proma-update-2026-10-08-09-30-00-v404-exact.zip';
if (!is_dir($app . '/storage/updates')) mkdir($app . '/storage/updates', 0755, true);
copy(dirname(__DIR__) . '/dist/candidates/v4.0.4/updates/PromaPay-Update-v4.0.4-from-v4.0.2.zip', $app . '/storage/updates/' . $name);
$result = ScriptUpdateService::install($name);
if (!hash_equals($before, $fingerprint())) throw new RuntimeException('Exact 402 update changed business data');
echo "EXACT_OFFICIAL_402_TO_404_OK_BUSINESS_AND_CREDENTIALS_PRESERVED\n";
