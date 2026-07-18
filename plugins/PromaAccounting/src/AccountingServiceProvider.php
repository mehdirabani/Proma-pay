<?php

namespace Proma\Plugins\Accounting;

class AccountingServiceProvider implements \PluginServiceProviderInterface
{
    public function register(\PluginManager $manager, array $manifest)
    {
        foreach (['Money', 'AccountingRepository', 'AccountingIdempotencyService', 'LedgerService', 'CommissionCalculationService', 'CommissionService', 'SalesService'] as $file) {
            require_once __DIR__ . '/Services/' . $file . '.php';
        }
        require_once __DIR__ . '/Controllers/AccountingController.php';

        foreach ($manifest['routes'] ?? [] as $route) {
            if (!is_array($route) || empty($route['path']) || empty($route['handler'])) {
                continue;
            }
            $manager->registerRoute($manifest['id'], $route['path'], $route['handler'], [
                'method' => $route['method'] ?? 'GET',
                'permission' => $route['permission'] ?? '',
                'auth' => true,
            ]);
        }
        foreach ([
            ['route' => 'plugin/accounting/dashboard', 'label' => 'داشبورد حسابداری', 'icon' => 'pie-chart', 'permission' => 'plugin.proma-accounting.view_accounting_dashboard'],
            ['route' => 'plugin/accounting/accounts', 'label' => 'حساب کاربران', 'icon' => 'users', 'permission' => 'plugin.proma-accounting.view_user_accounting'],
            ['route' => 'plugin/accounting/sales', 'label' => 'فروش‌ها', 'icon' => 'shopping-bag', 'permission' => 'plugin.proma-accounting.view'],
            ['route' => 'plugin/accounting/commissions', 'label' => 'کمیسیون فروش', 'icon' => 'percent', 'permission' => 'plugin.proma-accounting.view_commissions'],
            ['route' => 'plugin/accounting/rules', 'label' => 'قوانین کمیسیون', 'icon' => 'sliders', 'permission' => 'plugin.proma-accounting.manage_commission_rules'],
            ['route' => 'plugin/accounting/backfill', 'label' => 'بازسازی فروش‌های قبلی', 'icon' => 'refresh-cw', 'permission' => 'plugin.proma-accounting.backfill_sales'],
            ['route' => 'plugin/accounting/settings', 'label' => 'تنظیمات حسابداری', 'icon' => 'settings', 'permission' => 'plugin.proma-accounting.manage_accounting_settings'],
            ['route' => 'plugin/accounting/help', 'label' => 'راهنمای حسابداری', 'icon' => 'book-open', 'permission' => 'plugin.proma-accounting.view'],
        ] as $menu) {
            $manager->registerMenu($manifest['id'], $menu);
        }
    }

    public function boot(\PluginManager $manager, array $manifest)
    {
        \PluginHooks::listen('contract.created', function (array $payload) {
            SalesService::recordFromContract((int) ($payload['contract_id'] ?? 0), (int) ($payload['actor_user_id'] ?? 0), (int) ($payload['seller_user_id'] ?? 0));
        }, 20);
        \PluginHooks::listen('contract.updated', function (array $payload) {
            SalesService::syncFromContract((int) ($payload['contract_id'] ?? 0), (int) ($payload['actor_user_id'] ?? 0));
        }, 20);
        \PluginHooks::listen('contract.cancelled', function (array $payload) {
            CommissionService::reverseForContract((int) ($payload['contract_id'] ?? 0), (int) ($payload['actor_user_id'] ?? 0), 'لغو قرارداد');
        }, 20);
        \PluginHooks::listen('contract.deleted', function (array $payload) {
            $contractId = (int) ($payload['contract_id'] ?? 0);
            \Model::execute('UPDATE plugin_accounting_sales SET status = \'deleted\', updated_by = ?, updated_at = NOW() WHERE contract_id = ?', [(int) ($payload['actor_user_id'] ?? 0), $contractId]);
            CommissionService::reverseForContract($contractId, (int) ($payload['actor_user_id'] ?? 0), 'حذف دائمی قرارداد');
        }, 20);
        \PluginHooks::listen('payment.completed', function (array $payload) {
            CommissionService::refreshForContract((int) ($payload['contract_id'] ?? 0), (int) ($payload['actor_user_id'] ?? 0));
        }, 30);
    }

    public function install(\PluginManager $manager, array $manifest)
    {
    }

    public function activate(\PluginManager $manager, array $manifest)
    {
    }

    public function deactivate(\PluginManager $manager, array $manifest)
    {
    }

    public function update(\PluginManager $manager, array $manifest)
    {
        $status = self::safeFetch("SELECT setting_value FROM plugin_accounting_settings WHERE setting_key = 'setup_status' LIMIT 1");
        $hasHistory = (bool) self::safeFetch('SELECT id FROM accounting_ledger_entries LIMIT 1')
            || (bool) self::safeFetch('SELECT id FROM plugin_accounting_sales LIMIT 1');
        if (($status['setting_value'] ?? '') === 'pending' && $hasHistory) {
            self::safeExecute("UPDATE plugin_accounting_settings SET setting_value = 'skipped', updated_at = NOW() WHERE setting_key = 'setup_status'");
        }
    }

    public function uninstall(\PluginManager $manager, array $manifest, $purge = false)
    {
        // Financial history is intentionally preserved by the default uninstall.
    }

    public function healthCheck(\PluginManager $manager, array $manifest)
    {
        try {
            self::safeFetch('SELECT id FROM accounting_user_accounts LIMIT 1');
            self::safeFetch('SELECT id FROM accounting_ledger_entries LIMIT 1');
            self::safeFetch('SELECT setting_key FROM plugin_accounting_settings LIMIT 1');
            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }

    protected static function safeFetch($sql, array $params = [])
    {
        $stmt = \Model::db()->prepare($sql);
        try {
            $stmt->execute($params);
            $row = $stmt->fetch();
            return $row ?: null;
        } finally {
            $stmt->closeCursor();
        }
    }

    protected static function safeExecute($sql, array $params = [])
    {
        $stmt = \Model::db()->prepare($sql);
        try {
            $stmt->execute($params);
            return $stmt->rowCount();
        } finally {
            $stmt->closeCursor();
        }
    }
}
