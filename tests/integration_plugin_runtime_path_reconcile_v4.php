<?php

declare(strict_types=1);

$dsn = trim((string) getenv('PROMA_TEST_DB_DSN'));
if (!preg_match('/(?:^|;)dbname=proma_qa_v204(?:;|$)/i', $dsn)
    || getenv('PROMA_TEST_ALLOW_DESTRUCTIVE') !== '1') {
    fwrite(STDERR, "This plugin path recovery test is restricted to the disposable release QA database.\n");
    exit(2);
}

$_SERVER['HTTP_HOST'] = 'qa.local';
$_SERVER['SCRIPT_NAME'] = '/index.php';
$_SERVER['REQUEST_METHOD'] = 'CLI';
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
require dirname(__DIR__) . '/bootstrap.php';

$pdo = new PDO(
    $dsn,
    (string) (getenv('PROMA_TEST_DB_USER') ?: 'root'),
    (string) (getenv('PROMA_TEST_DB_PASSWORD') ?: ''),
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
);
$pdoProperty = new ReflectionProperty(Model::class, 'pdo');

$pdoProperty->setValue(null, $pdo);

$repairRoot = dirname(__DIR__) . '/plugins/PromaRepair';
$manifest = PluginManifest::read($repairRoot);
$pluginId = (string) $manifest['id'];
$original = PluginRegistry::findAny($pluginId);
$stalePath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'proma-qa-old-root-' . bin2hex(random_bytes(8)) . DIRECTORY_SEPARATOR . 'PromaRepair';
$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
};

try {
    PluginRegistry::upsert($manifest, $stalePath, null, PluginStatus::ACTIVE);
    PluginManager::boot();

    $resolved = PluginRegistry::find($pluginId);
    $assert(is_array($resolved) && ($resolved['status'] ?? '') === PluginStatus::ACTIVE, 'A moved active plugin should remain active after resolving its local files.');
    $resolver = new ReflectionMethod(PluginManager::class, 'resolveRuntimePlugin');
    $resolver->setAccessible(true);
    $runtimeResolved = $resolver->invoke(null, $resolved);
    $assert(realpath((string) ($runtimeResolved['path'] ?? '')) === realpath($repairRoot), 'The runtime resolver should locate the plugin directory in the current install.');
    $storedPath = str_replace('\\', '/', (string) ($resolved['path'] ?? ''));
    $expectedStalePath = str_replace('\\', '/', $stalePath);
    $assert($storedPath === $expectedStalePath, 'Runtime resolution should not rewrite historical registry metadata.');
    $assert(PluginManager::roleAvailable('repair_technician'), 'An active plugin role should remain available after a backup restore to a new path.');
} finally {
    if ($original) {
        $restore = $pdo->prepare(
            'UPDATE system_plugins SET name = ?, description = ?, version = ?, path = ?, status = ?, installed_at = ?, activated_at = ?, deactivated_at = ?, updated_at = ?, installed_by = ?, last_error = ?, manifest_json = ?, deleted_at = ? WHERE plugin_id = ?'
        );
        $restore->execute([
            $original['name'], $original['description'], $original['version'], $original['path'], $original['status'],
            $original['installed_at'], $original['activated_at'], $original['deactivated_at'], $original['updated_at'],
            $original['installed_by'], $original['last_error'], $original['manifest_json'], $original['deleted_at'], $pluginId,
        ]);
    } else {
        $delete = $pdo->prepare('DELETE FROM system_plugins WHERE plugin_id = ?');
        $delete->execute([$pluginId]);
    }
}

echo "PLUGIN_RUNTIME_PATH_RECONCILE_V4_OK\n";
