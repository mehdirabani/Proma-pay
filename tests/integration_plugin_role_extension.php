<?php

declare(strict_types=1);

/** Run only against the disposable QA database; this installs the repair plugin. */
$dsn = trim((string) getenv('PROMA_TEST_DB_DSN'));
if (getenv('PROMA_TEST_ALLOW_DESTRUCTIVE') !== '1'
    || !preg_match('/(?:^|;)dbname=proma_qa_v204(?:;|$)/', $dsn)) {
    fwrite(STDERR, "Refusing plugin integration test outside proma_qa_v204.\n");
    exit(2);
}

$_SERVER['HTTP_HOST'] = 'qa.local';
$_SERVER['SCRIPT_NAME'] = '/index.php';
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['REMOTE_ADDR'] = '198.51.100.240';
require dirname(__DIR__) . '/bootstrap.php';

$pdo = new PDO($dsn, getenv('PROMA_TEST_DB_USER') ?: 'root', getenv('PROMA_TEST_DB_PASSWORD') ?: '', [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
]);
$property = new ReflectionProperty(Model::class, 'pdo');

$property->setValue(null, $pdo);
$expect = static function ($condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
};

$manager = PluginManager::instance();
$manager->discover();
$registered = PluginRegistry::find('proma-repair');
if (($registered['status'] ?? '') === 'update_available') {
    $manager->update('proma-repair');
} elseif (!in_array($registered['status'] ?? '', ['installed', 'active', 'inactive'], true)) {
    $manager->install('proma-repair');
}
if (!PluginManager::isActive('proma-repair')) {
    $manager->activate('proma-repair');
}
$expect(PluginManager::roleAvailable('repair_technician'), 'technician role is active');
$expect(PluginManager::roleLandingRoute('repair_technician') === 'plugin/repair/dashboard', 'plugin owns role landing');
$expect((PluginManager::availableRoles()['repair_technician'] ?? '') === 'تعمیرکار', 'user form role label');
$menus = $manager->menus();
$expect((bool) array_filter($menus, static fn($menu) => ($menu['route'] ?? '') === 'customer/repairs'
    && in_array('customer', $menu['roles'] ?? [], true)), 'customer repair navigation registered');
$expect((bool) array_filter($manager->assetsForRoute('customer/repairs'), static fn($asset) =>
    ($asset['type'] ?? '') === 'css' && strpos((string) ($asset['path'] ?? ''), 'plugins/PromaRepair/') === 0),
    'customer repair assets come from the active plugin manifest');

$manifest = PluginManifest::read(dirname(__DIR__) . '/plugins/PromaRepair');
$provider = new Proma\Plugins\Repair\RepairServiceProvider();
$menuCount = count($manager->menus());
$routesProperty = new ReflectionProperty(PluginManager::class, 'routes');

$routeCount = count($routesProperty->getValue($manager));
$provider->register($manager, $manifest);
$expect(count($manager->menus()) === $menuCount, 'menu registration is idempotent');
$expect(count($routesProperty->getValue($manager)) === $routeCount, 'route registration is idempotent');

$suffix = substr(bin2hex(random_bytes(5)), 0, 9);
$technicianName = 'qa_repair_' . $suffix;
$technicianId = User::create([
    'role' => 'repair_technician', 'username' => $technicianName,
    'full_name' => 'تعمیرکار آزمون', 'mobile' => '0912' . substr((string) random_int(1000000, 9999999), 0, 7),
    'password' => 'QaRepair#' . $suffix, 'status' => 'active',
]);
$stored = User::find($technicianId);
$expect($stored && $stored['role'] === 'repair_technician'
    && password_verify('QaRepair#' . $suffix, $stored['password_hash']), 'technician account is hashed');
$adminName = 'qa_admin_' . $suffix;
User::create(['role' => 'admin', 'username' => $adminName, 'full_name' => 'مدیر آزمون',
    'mobile' => '0913' . random_int(1000000, 9999999), 'password' => 'QaAdmin#' . $suffix,
    'status' => 'active']);
$expect(Auth::login($adminName, 'QaAdmin#' . $suffix), 'administrator signs in normally');
$_GET['role'] = 'repair_technician';
$_SERVER['HTTP_X_REQUESTED_WITH'] = 'XMLHttpRequest';
ob_start();
(new UsersController())->index();
$userPage = (string) ob_get_clean();
unset($_GET['role'], $_SERVER['HTTP_X_REQUESTED_WITH']);
$expect(strpos($userPage, 'value="repair_technician"') !== false
    && strpos($userPage, $technicianName) !== false
    && strpos($userPage, 'name="_csrf"') !== false,
    'administrator form and list include the technician role with CSRF');
$_SERVER['REQUEST_METHOD'] = 'POST';
$_POST = ['full_name' => 'نام آزمون', 'username' => 'qa_invalid_' . $suffix,
    'password' => 'QaInvalid#' . $suffix, 'role' => 'unknown_plugin_role',
    'mobile' => '0915' . random_int(1000000, 9999999), '_csrf' => 'invalid'];
$csrfDenied = false;
try {
    (new UsersController())->store();
} catch (HttpException $e) {
    $csrfDenied = $e->status() === 419;
}
$expect($csrfDenied, 'user creation rejects an invalid CSRF token');
$_POST['_csrf'] = Csrf::token();
$invalidRoleDenied = false;
try {
    (new UsersController())->store();
} catch (HttpException $e) {
    $invalidRoleDenied = $e->status() === 422;
}
$expect($invalidRoleDenied, 'user creation rejects an unknown role with HTTP 422');
$_POST = [];
$_SERVER['REQUEST_METHOD'] = 'GET';
$editableId = User::create(['role' => 'operator', 'username' => 'qa_edit_' . $suffix,
    'full_name' => 'کاربر قابل ویرایش', 'mobile' => '0914' . random_int(1000000, 9999999),
    'password' => 'QaEdit#' . $suffix, 'status' => 'active']);
User::updateUser($editableId, ['role' => 'repair_technician']);
$expect((User::find($editableId)['role'] ?? '') === 'repair_technician'
    && (int) (Model::fetch('SELECT role_active FROM proma_repair_technicians WHERE user_id=?', [$editableId])['role_active'] ?? 0) === 1,
    'editing a user into the technician role creates an active technician profile');
User::updateUser($editableId, ['role' => 'operator']);
$expect((int) (Model::fetch('SELECT role_active FROM proma_repair_technicians WHERE user_id=?', [$editableId])['role_active'] ?? 1) === 0,
    'editing away from the technician role revokes its profile without deleting it');
$expect(Auth::login($technicianName, 'QaRepair#' . $suffix), 'technician uses ordinary login');
$expect(PluginManager::can('plugin.proma-repair.view_orders'), 'technician has assigned capability');
$expect(!PluginManager::can('plugin.proma-repair.manage_technicians'), 'technician cannot manage technicians');
$deniedRoute = false;
try {
    $manager->dispatchRoute('plugin/repair/technicians');
} catch (HttpException $e) {
    $deniedRoute = $e->status() === 403;
}
$expect($deniedRoute, 'Core rejects an unauthorized plugin route with HTTP 403');
Model::execute('UPDATE proma_repair_technicians SET active=0 WHERE user_id=?', [$technicianId]);
$expect(!Auth::login($technicianName, 'QaRepair#' . $suffix), 'inactive technician profile blocks login');
Model::execute('UPDATE proma_repair_technicians SET active=1 WHERE user_id=?', [$technicianId]);

$customerAMobile = '0935' . random_int(1000000, 9999999);
$customerBMobile = '0936' . random_int(1000000, 9999999);
$customerA = User::create(['role' => 'customer', 'full_name' => 'مشتری آزمون الف',
    'username' => 'qa_customer_a_' . $suffix, 'mobile' => $customerAMobile, 'password' => substr($customerAMobile, -4), 'status' => 'active']);
$customerB = User::create(['role' => 'customer', 'full_name' => 'مشتری آزمون ب',
    'username' => 'qa_customer_b_' . $suffix, 'mobile' => $customerBMobile, 'password' => substr($customerBMobile, -4), 'status' => 'active']);
foreach ([$customerA, $customerB] as $customerId) {
    Model::execute('INSERT INTO proma_repairs (tracking_code, customer_id, status, customer_report, created_at) VALUES (?, ?, ?, ?, NOW())',
        ['QA-REPAIR-' . $suffix . '-' . $customerId, $customerId, 'received', 'QA']);
    $repairId = (int) Model::lastInsertId();
    Model::execute('INSERT INTO proma_repair_devices (repair_id, brand, model) VALUES (?, ?, ?)', [$repairId, 'QA', 'Device']);
    $repairIds[$customerId] = $repairId;
}
$repository = new Proma\Plugins\Repair\Repositories\RepairRepository();
$own = $repository->customerList($customerA);
$expect(count($own) === 1 && (int) $own[0]['id'] === $repairIds[$customerA], 'customer list is scoped');
$expect($repository->customerDetail($repairIds[$customerB], $customerA) === null, 'foreign repair detail is denied');
$expect($repository->customerDetail($repairIds[$customerA], $customerA) !== null, 'own repair detail is available');
$widgets = PluginManager::filter('dashboard.customer.widgets', [], ['customer_id' => $customerA]);
$expect(count($widgets) === 1 && ($widgets[0]['route'] ?? '') === 'customer/repairs'
    && strpos((string) ($widgets[0]['description'] ?? ''), to_persian_digits(1)) !== false,
    'active plugin supplies the customer dashboard widget');
$customerRecord = User::find($customerA);
$expect(Auth::login($customerRecord['username'], substr($customerRecord['mobile'], -4)),
    'customer signs in with the required last four mobile digits');
$expect(!Auth::customerLogin($customerRecord['username'], 'Customer#PluginA'),
    'an arbitrary password is not accepted as a customer credential');
$_GET['route'] = 'customer/repairs';
ob_start();
$routed = $manager->dispatchRoute('customer/repairs');
$customerPage = (string) ob_get_clean();
$expect($routed === true && strpos($customerPage, 'تعمیرات من') !== false
    && strpos($customerPage, 'QA-REPAIR-' . $suffix . '-' . $customerA) !== false
    && strpos($customerPage, 'QA-REPAIR-' . $suffix . '-' . $customerB) === false,
    'active customer route renders a scoped repair page and navigation');
$_GET['route'] = 'customer/repairs/' . $repairIds[$customerA];
ob_start();
$ownDetailRouted = $manager->dispatchRoute($_GET['route']);
$ownDetailPage = (string) ob_get_clean();
$expect($ownDetailRouted && strpos($ownDetailPage, 'QA-REPAIR-' . $suffix . '-' . $customerA) !== false,
    'customer can view the detail of their own repair through the registered route');
$forbidden = false;
try {
    $_GET['route'] = 'customer/repairs/' . $repairIds[$customerB];
    $manager->dispatchRoute($_GET['route']);
} catch (HttpException $e) {
    $forbidden = $e->status() === 404;
}
$expect($forbidden, 'foreign customer route returns HTTP 404');
$_GET['route'] = 'customer/repairs';
$forbidden = false;
try {
    Auth::requireRole('admin');
} catch (HttpException $e) {
    $forbidden = $e->status() === 403;
}
$expect($forbidden, 'unauthorized role returns HTTP 403');

$expect(Auth::login($technicianName, 'QaRepair#' . $suffix), 'technician can sign in before account status revocation');
$expect(User::updateUser($technicianId, ['status' => 'inactive']), 'technician account can be deactivated');
$inactiveAccountDenied = false;
try {
    Auth::requireLogin();
} catch (HttpException $e) {
    $inactiveAccountDenied = $e->status() === 403;
}
$expect($inactiveAccountDenied && !Auth::check(), 'deactivating an account revokes its existing session');
$expect(User::updateUser($technicianId, ['status' => 'active']), 'technician account can be reactivated for role test');

$expect(Auth::login($technicianName, 'QaRepair#' . $suffix), 'technician can sign in before role change');
$expect(User::updateUser($technicianId, ['role' => 'operator']), 'technician role can be changed');
$changedRoleDenied = false;
try {
    Auth::requireLogin();
} catch (HttpException $e) {
    $changedRoleDenied = $e->status() === 403;
}
$expect($changedRoleDenied && !Auth::check(), 'changing an account role revokes its existing session');
$expect(User::updateUser($technicianId, ['role' => 'repair_technician']), 'technician role can be restored for plugin deactivation test');

$expect(Auth::login($technicianName, 'QaRepair#' . $suffix), 'technician session exists before deactivation');
$manager->deactivate('proma-repair');
$expect(!PluginManager::roleAvailable('repair_technician'), 'deactivation disables the role');
$existingSessionDenied = false;
try {
    Auth::requireLogin();
} catch (HttpException $e) {
    $existingSessionDenied = $e->status() === 403;
}
$expect($existingSessionDenied, 'deactivation blocks an already signed-in technician session');
$rejectedInactiveRole = false;
try {
    User::create(['role' => 'repair_technician', 'username' => 'qa_inactive_' . $suffix,
        'full_name' => 'تعمیرکار غیرفعال', 'password' => 'QaRepair#' . $suffix]);
} catch (InvalidArgumentException $e) {
    $rejectedInactiveRole = true;
}
$expect($rejectedInactiveRole, 'inactive plugin role cannot be assigned to a new user');
$rejectedInactiveUpdate = false;
try {
    User::updateUser($editableId, ['role' => 'repair_technician']);
} catch (InvalidArgumentException $e) {
    $rejectedInactiveUpdate = true;
}
$expect($rejectedInactiveUpdate, 'inactive plugin role cannot be assigned during user editing');
$expect(PluginManager::roleLandingRoute('repair_technician') === null, 'deactivation removes landing');
$expect(!PluginManager::can('plugin.proma-repair.view_orders'), 'deactivation revokes capability');
$expect(!Auth::login($technicianName, 'QaRepair#' . $suffix), 'deactivation blocks ordinary technician login');
$expect(!array_filter($manager->menus(), static fn($menu) => ($menu['plugin_id'] ?? '') === 'proma-repair'), 'deactivation removes menus');
$expect($manager->dispatchRoute('customer/repairs') === false, 'deactivation removes routes');
$expect(PluginManager::filter('dashboard.customer.widgets', [], ['customer_id' => $customerA]) === [],
    'deactivation removes the customer dashboard widget');
$expect(Auth::login($adminName, 'QaAdmin#' . $suffix), 'admin can still inspect accounts after plugin deactivation');
$_GET['role'] = 'repair_technician';
$_SERVER['HTTP_X_REQUESTED_WITH'] = 'XMLHttpRequest';
ob_start();
(new UsersController())->index();
$inactiveUserPage = (string) ob_get_clean();
unset($_GET['role'], $_SERVER['HTTP_X_REQUESTED_WITH']);
$expect(strpos($inactiveUserPage, $technicianName) !== false
    && strpos($inactiveUserPage, 'value="repair_technician"') !== false,
    'admin can still see and filter preserved accounts of an inactive plugin role');
$expect(User::find($technicianId) !== null && $repository->customerDetail($repairIds[$customerA], $customerA) !== null,
    'deactivation retains users and repairs');
$manager->activate('proma-repair');
$expect(PluginManager::roleAvailable('repair_technician') && count($manager->menus()) === $menuCount,
    'reactivation restores role without duplicate menus');

echo "PLUGIN_ROLE_EXTENSION_INTEGRATION_OK\n";
