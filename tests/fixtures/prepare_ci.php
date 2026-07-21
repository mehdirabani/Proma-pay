<?php

declare(strict_types=1);

if ((string) getenv('PROMA_CI_FIXTURE') !== '1') {
    fwrite(STDERR, "This fixture may only run with PROMA_CI_FIXTURE=1.\n");
    exit(2);
}

$root = dirname(__DIR__, 2);
$db = [
    'host' => getenv('PROMA_TEST_DB_HOST') ?: '127.0.0.1',
    'database' => getenv('PROMA_TEST_DB_NAME') ?: 'proma_pay',
    'username' => getenv('PROMA_TEST_DB_USER') ?: 'root',
    'password' => getenv('PROMA_TEST_DB_PASSWORD') ?: '',
    'charset' => 'utf8mb4',
];
$config = "<?php\n\nreturn " . var_export($db, true) . ";\n";
if (file_put_contents($root . '/config/database.php', $config, LOCK_EX) === false) {
    throw new RuntimeException('Could not write the CI database configuration.');
}
if (file_put_contents($root . '/installed.lock', 'qa=' . gmdate('c'), LOCK_EX) === false) {
    throw new RuntimeException('Could not create the CI installation marker.');
}

$_SERVER['HTTP_HOST'] = '127.0.0.1:8080';
$_SERVER['SCRIPT_NAME'] = '/index.php';
$_SERVER['REQUEST_METHOD'] = 'CLI';
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';

require $root . '/bootstrap.php';

Settings::seedDefaults();
Settings::saveMany([
    'system_name' => 'پروما پی QA',
    'company_name' => 'فروشگاه آزمون پروما',
    'ecommerce_enabled' => '1',
    'landing_enabled' => '1',
]);

$users = [
    [
        'role' => 'admin',
        'username' => 'qa-admin',
        'full_name' => 'مدیر آزمون',
        'national_id' => '0010000001',
        'mobile' => '09120001001',
        'password' => 'QaAdmin#143',
        'department' => 'مدیریت',
    ],
    [
        'role' => 'operator',
        'username' => 'qa-operator',
        'full_name' => 'اپراتور آزمون',
        'national_id' => '0010000002',
        'mobile' => '09120001002',
        'password' => 'QaOperator#143',
        'department' => 'اقساط',
    ],
    [
        'role' => 'lawyer',
        'username' => 'qa-lawyer',
        'full_name' => 'وکیل آزمون',
        'national_id' => '0010000003',
        'mobile' => '09120001003',
        'password' => 'QaLawyer#143',
        'department' => 'حقوقی',
    ],
    [
        'role' => 'customer',
        'username' => '0010001432',
        'full_name' => 'مشتری آزمون',
        'national_id' => '0010001432',
        'mobile' => '09120001432',
        'password' => '1432',
        'department' => null,
    ],
];

$ids = [];
foreach ($users as $data) {
    $existing = Model::fetch('SELECT id FROM users WHERE username = ? LIMIT 1', [$data['username']]);
    if ($existing) {
        $id = (int) $existing['id'];
        Model::execute(
            'UPDATE users SET role = ?, full_name = ?, national_id = ?, mobile = ?, password_hash = ?, status = ?, department = ? WHERE id = ?',
            [
                $data['role'],
                $data['full_name'],
                $data['national_id'],
                $data['mobile'],
                password_hash($data['password'], PASSWORD_DEFAULT),
                'active',
                $data['department'],
                $id,
            ]
        );
    } else {
        $id = User::create($data + [
            'email' => $data['username'] . '@qa.example.test',
            'status' => 'active',
            'address' => 'نشانی آزمون رابط کاربری',
        ]);
    }
    $ids[$data['role']] = $id;
}

if ((int) (Model::fetch("SELECT COUNT(*) AS total FROM profile_update_requests WHERE user_id = ? AND status = 'pending'", [$ids['customer']])['total'] ?? 0) === 0) {
    ProfileRequest::createRequest($ids['customer'], [
        'full_name' => 'نام اصلاح‌شده مشتری آزمون',
        'address' => 'نشانی پیشنهادی برای بررسی مدیریت',
    ]);
}

$pluginManager = PluginManager::instance();
$pluginManager->rescan($ids['admin']);
$accounting = PluginRegistry::find('proma-accounting');
if ($accounting) {
    if (($accounting['status'] ?? '') === PluginStatus::DISCOVERED) {
        $pluginManager->install('proma-accounting', $ids['admin']);
    }
    if (($accounting['status'] ?? '') !== PluginStatus::ACTIVE) {
        $pluginManager->activate('proma-accounting', $ids['admin']);
    }
}

echo json_encode([
    'status' => 'READY',
    'users' => $ids,
    'accounting' => PluginRegistry::find('proma-accounting')['status'] ?? 'missing',
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;
