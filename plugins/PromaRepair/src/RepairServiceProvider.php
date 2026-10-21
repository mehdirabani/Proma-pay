<?php

namespace Proma\Plugins\Repair;

class RepairServiceProvider implements \PluginServiceProviderInterface
{
    private static $autoloadRegistered = false;
    private static $userHooksRegistered = false;
    private static $dashboardHookRegistered = false;

    public function register(\PluginManager $manager, array $manifest)
    {
        $this->registerAutoload($manifest['_root']);
        $manager->registerRole($manifest['id'], 'repair_technician', [
            'label' => 'تعمیرکار',
            'landing_route' => 'plugin/repair/dashboard',
            'login_guard' => Support\TechnicianSession::class . '::canLogin',
        ]);
        $getRoutes = [];
        foreach (Support\RouteCatalog::all() as $route) {
            if (empty($route['path']) || empty($route['handler'])) {
                continue;
            }
            $manager->registerRoute($manifest['id'], $route['path'], $route['handler'], [
                'method' => strtoupper((string) ($route['method'] ?? 'GET')),
                'permission' => (string) ($route['permission'] ?? ''),
                'auth' => (bool) ($route['auth'] ?? true),
            ]);
            if (strtoupper((string) ($route['method'] ?? 'GET')) === 'GET') {
                $getRoutes[trim((string) $route['path'], '/')] = true;
            }
        }

        $menus = [
            ['route' => 'plugin/repair/dashboard', 'label' => 'داشبورد تعمیرات', 'permission' => 'plugin.proma-repair.view_orders', 'roles' => ['admin', 'operator', 'repair_technician']],
            ['route' => 'plugin/repair/orders', 'label' => 'پرونده‌های تعمیر', 'permission' => 'plugin.proma-repair.view_orders', 'roles' => ['admin', 'operator']],
            ['route' => 'plugin/repair/orders', 'label' => 'تعمیرات من', 'permission' => 'plugin.proma-repair.view_orders', 'roles' => ['repair_technician']],
            ['route' => 'plugin/repair/technician-expenses', 'label' => 'هزینه‌های من', 'permission' => 'plugin.proma-repair.view_orders', 'roles' => ['repair_technician']],
            ['route' => 'plugin/repair/technician-requests', 'label' => 'درخواست‌های من', 'permission' => 'plugin.proma-repair.view_orders', 'roles' => ['repair_technician']],
            ['route' => 'plugin/repair/technician-history', 'label' => 'تاریخچه تعمیرات', 'permission' => 'plugin.proma-repair.view_orders', 'roles' => ['repair_technician']],
            ['route' => 'plugin/repair/technician-profile', 'label' => 'پروفایل تعمیرکار', 'permission' => 'plugin.proma-repair.view_orders', 'roles' => ['repair_technician']],
            ['route' => 'plugin/repair/expense-approvals', 'label' => 'تأیید هزینه تعمیرکاران', 'permission' => 'plugin.proma-repair.approve_expenses', 'roles' => ['admin', 'operator']],
            ['route' => 'plugin/repair/intake', 'label' => 'پذیرش تعمیر', 'permission' => 'plugin.proma-repair.create_orders'],
            ['route' => 'plugin/repair/technicians', 'label' => 'تعمیرکاران', 'permission' => 'plugin.proma-repair.manage_technicians'],
            ['route' => 'plugin/repair/reports', 'label' => 'گزارش‌ها', 'permission' => 'plugin.proma-repair.view_financial'],
            ['route' => 'plugin/repair/settings', 'label' => 'تنظیمات تعمیرات', 'permission' => 'plugin.proma-repair.manage_settings'],
            ['route' => 'plugin/repair/permissions', 'label' => 'مجوزهای تعمیرات', 'permission' => 'plugin.proma-repair.manage_settings'],
            ['route' => 'customer/repairs', 'label' => 'تعمیرات من', 'permission' => ''],
        ];
        foreach ($menus as $menu) {
            // Do not render menu links when the registered manifest has no matching GET route.
            if (empty($getRoutes[trim((string) $menu['route'], '/')])) {
                continue;
            }
            $menu['group_label'] = 'تعمیرات';
            $menu['group_icon'] = 'package';
            if (isset($menu['roles'])) {
                // Explicit role scope is kept by the Core menu contract.
            } elseif (in_array($menu['route'], ['portal/repairs', 'customer/repairs'], true)) {
                $menu['roles'] = ['customer'];
            } elseif (in_array($menu['route'], ['plugin/repair/dashboard', 'plugin/repair/orders'], true)) {
                $menu['roles'] = ['admin', 'operator'];
            } else {
                $menu['roles'] = ['admin', 'operator'];
            }
            $manager->registerMenu($manifest['id'], $menu);
        }
    }

    public function boot(\PluginManager $manager, array $manifest)
    {
        $this->registerAutoload($manifest['_root']);
        Support\TechnicianSession::guardRequest();
        if (!self::$userHooksRegistered) {
            \PluginHooks::listen('user.created', function (array $payload) {
                $this->syncTechnicianUser((int) ($payload['user_id'] ?? 0), (string) ($payload['role'] ?? ''));
            }, 30);
            \PluginHooks::listen('user.updated', function (array $payload) {
                $this->syncTechnicianUser((int) ($payload['user_id'] ?? 0), (string) ($payload['role'] ?? ''));
            }, 30);
            self::$userHooksRegistered = true;
        }
        if (!self::$dashboardHookRegistered) {
            \PluginHooks::listen('dashboard.customer.widgets', static function (array $payload) {
                $widgets = (array) ($payload['value'] ?? []);
                $customerId = (int) ($payload['context']['customer_id'] ?? 0);
                if ($customerId <= 0 || !\PluginManager::isActive('proma-repair')) {
                    return $widgets;
                }
                $row = \Model::fetch(
                    "SELECT COUNT(*) AS total FROM proma_repairs WHERE customer_id=? AND status<>'draft' AND deleted_at IS NULL",
                    [$customerId]
                );
                $widgets[] = [
                    'title' => 'تعمیرات',
                    'description' => to_persian_digits((int) ($row['total'] ?? 0)) . ' پرونده تعمیر ثبت‌شده',
                    'icon' => 'package',
                    'route' => 'customer/repairs',
                    'action_label' => 'مشاهده تعمیرات',
                ];
                return $widgets;
            }, 20);
            self::$dashboardHookRegistered = true;
        }
    }

    public function install(\PluginManager $manager, array $manifest)
    {
        $this->registerAutoload($manifest['_root']);
        (new Repositories\RepairSettingsRepository())->seedDefaults(\Auth::id());
        $this->syncTechnicianUsers();
        $this->seedRoleCapabilities();
        $this->audit('installed', ['version' => $manifest['version']]);
    }

    public function activate(\PluginManager $manager, array $manifest)
    {
        $this->registerAutoload($manifest['_root']);
        if (!$this->healthCheck($manager, $manifest)) {
            throw new \RuntimeException('جداول مدیریت تعمیرات آماده نیستند.');
        }
        $this->syncTechnicianUsers();
        $this->seedRoleCapabilities();
        $this->audit('activated');
    }

    public function deactivate(\PluginManager $manager, array $manifest)
    {
        $this->registerAutoload($manifest['_root']);
        $this->audit('deactivated', ['data_preserved' => true]);
    }

    public function update(\PluginManager $manager, array $manifest)
    {
        $this->registerAutoload($manifest['_root']);
        (new Repositories\RepairSettingsRepository())->seedDefaults(\Auth::id());
        $this->syncTechnicianUsers();
        $this->seedRoleCapabilities();
        $this->audit('updated', ['version' => $manifest['version']]);
    }

    public function uninstall(\PluginManager $manager, array $manifest, $purge = false)
    {
        $this->registerAutoload($manifest['_root']);
        if ($purge) {
            throw new \RuntimeException('حذف کامل سوابق مالی و تعمیرات از چرخه عادی افزونه مجاز نیست.');
        }
        $this->audit('uninstalled', ['data_preserved' => true]);
    }

    public function healthCheck(\PluginManager $manager, array $manifest)
    {
        $this->registerAutoload($manifest['_root']);
        try {
            return (new Repositories\RepairRepository())->schemaReady();
        } catch (\Throwable $e) {
            return false;
        }
    }

    private function registerAutoload($root)
    {
        if (self::$autoloadRegistered) {
            return;
        }
        $source = rtrim((string) $root, '/\\') . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR;
        spl_autoload_register(static function ($class) use ($source) {
            $prefix = 'Proma\\Plugins\\Repair\\';
            if (strpos((string) $class, $prefix) !== 0) {
                return;
            }
            $path = $source . str_replace('\\', DIRECTORY_SEPARATOR, substr((string) $class, strlen($prefix))) . '.php';
            if (is_file($path)) {
                require_once $path;
            }
        });
        self::$autoloadRegistered = true;
    }

    private function audit($action, array $values = [])
    {
        try {
            \AuditLog::record('repair', 'repair_plugin_' . $action, 'plugin', 0, [
                'actor_user_id' => \Auth::id(),
                'description' => 'چرخه عمر افزونه مدیریت تعمیرات',
                'new_values' => $values + ['plugin_id' => 'proma-repair'],
            ]);
        } catch (\Throwable $ignored) {
        }
    }

    private function syncTechnicianUsers()
    {
        try {
            $users = \Model::fetchAll("SELECT id FROM users WHERE role='repair_technician'");
            foreach ($users as $user) { $this->syncTechnicianUser((int) $user['id'], 'repair_technician'); }
            \Model::execute("UPDATE proma_repair_technicians t JOIN users u ON u.id=t.user_id SET t.role_active=0 WHERE u.role<>'repair_technician'");
        } catch (\Throwable $ignored) {
        }
    }

    private function syncTechnicianUser(int $userId, string $role)
    {
        if ($userId <= 0) { return; }
        try {
            if ($role === 'repair_technician') {
                \Model::execute('INSERT INTO proma_repair_technicians (user_id, active, role_active, created_at) VALUES (?, 1, 1, NOW()) ON DUPLICATE KEY UPDATE role_active=1', [$userId]);
            } else {
                \Model::execute('UPDATE proma_repair_technicians SET role_active=0 WHERE user_id=?', [$userId]);
            }
        } catch (\Throwable $ignored) {
        }
    }

    private function seedRoleCapabilities()
    {
        try {
            $technician = ['view_orders', 'change_status', 'manage_orders', 'record_costs', 'upload_attachments', 'view_device_password'];
            foreach ($technician as $key) {
                \Model::execute('INSERT INTO system_plugin_role_permissions (plugin_id, permission_key, role, is_allowed, updated_at) VALUES (?, ?, ?, 1, NOW()) ON DUPLICATE KEY UPDATE is_allowed = 1, updated_at = NOW()', ['proma-repair', 'plugin.proma-repair.' . $key, 'repair_technician']);
            }
            foreach (['approve_expenses', 'settle_expenses'] as $key) {
                \Model::execute('INSERT IGNORE INTO system_plugin_role_permissions (plugin_id, permission_key, role, is_allowed, updated_at) VALUES (?, ?, ?, 1, NOW())', ['proma-repair', 'plugin.proma-repair.' . $key, 'operator']);
            }
        } catch (\Throwable $ignored) {
        }
    }
}
