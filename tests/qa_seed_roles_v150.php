<?php

$_SERVER['HTTP_HOST'] = 'qa.local';
$_SERVER['SCRIPT_NAME'] = '/index.php';
$_SERVER['REQUEST_METHOD'] = 'CLI';
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
require dirname(__DIR__) . '/bootstrap.php';

// The release gate always provides an isolated destructive DSN.  Do not let
// the seed fall back to a developer's local config/database name.
$dsn = trim((string) getenv('PROMA_TEST_DB_DSN'));
if ($dsn === '') {
    fwrite(STDERR, "PROMA_TEST_DB_DSN is required for the QA seed.\n");
    exit(2);
}
$pdo = new PDO($dsn, getenv('PROMA_TEST_DB_USER') ?: 'root', getenv('PROMA_TEST_DB_PASSWORD') ?: '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
$property = new ReflectionProperty(Model::class, 'pdo');
$property->setAccessible(true);
$property->setValue(null, $pdo);

$users = [
    ['admin', 'qa150admin', 'QA Admin 150', 'Admin#150Pass', '09120001501', '8000001501'],
    ['operator', 'qa150operator', 'QA Operator 150', 'Operator#150Pass', '09120001502', '8000001502'],
    ['lawyer', 'qa150lawyer', 'QA Lawyer 150', 'Lawyer#150Pass', '09120001503', '8000001503'],
    ['customer', '8000001504', 'QA Customer 150', 'Customer#150Pass', '09120001504', '8000001504'],
];
foreach ($users as [$role, $username, $name, $password, $mobile, $nationalId]) {
    if (!Model::fetch('SELECT id FROM users WHERE username = ? LIMIT 1', [$username])) {
        User::create([
            'role' => $role, 'username' => $username, 'full_name' => $name, 'password' => $password,
            'mobile' => $mobile, 'national_id' => $nationalId, 'email' => $username . '@example.test', 'status' => 'active',
        ]);
    }
}
$admin = Model::fetch('SELECT id FROM users WHERE username = ? LIMIT 1', ['qa150admin']);
$manager = PluginManager::instance();
$manager->rescan((int) ($admin['id'] ?? 0));

// Integration tests intentionally alter plugin lifecycle records. Rebuild this
// disposable QA fixture from the local manifest so a previous test cannot
// leave an update candidate or inactive state behind for HTTP smoke tests.
$accountingRoot = dirname(__DIR__) . '/plugins/PromaAccounting';
$accountingManifest = json_decode((string) file_get_contents($accountingRoot . '/plugin.json'), true, 512, JSON_THROW_ON_ERROR);
PluginRegistry::upsert(
    $accountingManifest,
    $accountingRoot,
    (int) ($admin['id'] ?? 0),
    PluginStatus::INSTALLED
);
$manager->activate('proma-accounting', (int) ($admin['id'] ?? 0));

// The HTTP dashboard checks deliberately target the post-onboarding view.
// Keep this QA fixture independent from the production first-run default.
Model::query(
    "INSERT INTO plugin_accounting_settings (setting_key, setting_value, updated_at)
     VALUES ('setup_status', 'completed', NOW())
     ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_at = NOW()"
);
echo "QA_USERS_READY\n";
