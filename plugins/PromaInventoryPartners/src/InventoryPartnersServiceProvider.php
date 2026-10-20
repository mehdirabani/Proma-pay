<?php

namespace Proma\Plugins\InventoryPartners;

class InventoryPartnersServiceProvider implements \PluginServiceProviderInterface
{
    public function register(\PluginManager $manager, array $manifest)
    {
        foreach (['Domain/Money', 'Domain/ProfitCalculator', 'Services/AuditService', 'Services/LedgerService', 'Services/OwnershipService', 'Services/CatalogService', 'Services/ProductService', 'Services/ExpenseService', 'Services/PartnerService', 'Services/PurchaseService', 'Services/SaleService', 'Services/SettlementService', 'Services/StockService', 'Services/ReportingService'] as $file) {
            require_once __DIR__ . '/' . $file . '.php';
        }
        require_once __DIR__ . '/Controllers/InventoryPartnersController.php';
        foreach ($manifest['routes'] ?? [] as $route) {
            if (!is_array($route) || empty($route['path']) || empty($route['handler'])) { continue; }
            $manager->registerRoute($manifest['id'], $route['path'], $route['handler'], [
                'method' => $route['method'] ?? 'GET',
                'permission' => $route['permission'] ?? '',
                'auth' => true,
            ]);
        }
        $menus = [
            ['dashboard', 'داشبورد انبار', 'grid', 'view_inventory'],
            ['products', 'مدیریت کالاها', 'package', 'view_products'],
            ['purchases', 'ثبت خرید و ورود کالا', 'download', 'view_purchases'],
            ['stock', 'موجودی انبار', 'box', 'view_stock'],
            ['pricing', 'قیمت‌گذاری', 'trending-up', 'view_pricing'],
            ['sales', 'فروش کالا', 'shopping-bag', 'view_sales'],
            ['partners', 'شرکا و سرمایه‌ها', 'users', 'view_partners'],
            ['ledger', 'دفتر حساب شرکا', 'book-open', 'view_partner_ledger'],
            ['settlements', 'تسویه‌حساب', 'credit-card', 'view_settlements'],
            ['expenses', 'هزینه‌های شراکت', 'file-minus', 'view_expenses'],
            ['transfers', 'انتقال موجودی', 'repeat', 'view_transfers'],
            ['profit-loss', 'سود و زیان', 'bar-chart-2', 'view_financial_reports'],
            ['reports', 'گزارش‌ها', 'file-text', 'view_reports'],
            ['activity', 'تاریخچه فعالیت‌ها', 'activity', 'view_audit'],
            ['settings', 'تنظیمات', 'settings', 'manage_settings'],
        ];
        foreach ($menus as [$path, $label, $icon, $permission]) {
            $manager->registerMenu($manifest['id'], [
                'route' => 'plugin/inventory-partners/' . $path,
                'label' => $label,
                'icon' => $icon,
                'permission' => 'plugin.proma-inventory-partners.' . $permission,
                'group_label' => 'انبار و شرکا',
                'group_icon' => 'package',
                'roles' => ['admin', 'operator'],
            ]);
        }
    }

    public function boot(\PluginManager $manager, array $manifest)
    {
        \PluginHooks::listen('payment.completed', static function (array $payload) {
            $paymentId = (int) ($payload['payment_id'] ?? 0);
            if ($paymentId > 0) {
                \Proma\Plugins\InventoryPartners\Services\SaleService::importPayment($paymentId, (int) ($payload['actor_user_id'] ?? 0));
            }
        }, 40);
        \PluginHooks::listen('contract.cancelled', static function (array $payload) {
            $contractId = (int) ($payload['contract_id'] ?? 0);
            if ($contractId > 0) {
                \Proma\Plugins\InventoryPartners\Services\SaleService::reconcileContract($contractId, (int) ($payload['actor_user_id'] ?? 0));
            }
        }, 40);
    }

    public function install(\PluginManager $manager, array $manifest)
    {
        if (!\Model::fetch("SELECT id FROM plugin_inventory_warehouses WHERE code = 'MAIN' LIMIT 1")) {
            \Model::execute("INSERT INTO plugin_inventory_warehouses (code, name, status, created_at) VALUES ('MAIN', 'انبار اصلی', 'active', NOW())");
        }
        foreach ([
            ['currency_display', 'toman'],
            ['cost_method', 'lot_specific'],
            ['receipts_policy', 'capital_first'],
            ['rounding_policy', 'largest_remainder_partner_id_tiebreak'],
            ['sku_pattern', '{type}_{brand}_{model}_{color}_{storage}_{ram}'],
            ['fiscal_start_month', '1'],
            ['fiscal_start_day', '1'],
            ['default_warehouse_id', (string) (\Model::lastInsertId() ?: 0)],
        ] as [$key, $value]) {
            \Model::execute('INSERT INTO plugin_inventory_settings (setting_key, setting_value, updated_at) VALUES (?, ?, NOW()) ON DUPLICATE KEY UPDATE setting_key = VALUES(setting_key)', [$key, $value]);
        }
        $warehouse = \Model::fetch("SELECT id FROM plugin_inventory_warehouses WHERE code = 'MAIN' LIMIT 1");
        if ($warehouse) {
            \Model::execute('UPDATE plugin_inventory_settings SET setting_value = ? WHERE setting_key = ?', [(string) $warehouse['id'], 'default_warehouse_id']);
        }
    }

    public function activate(\PluginManager $manager, array $manifest) {}
    public function deactivate(\PluginManager $manager, array $manifest) {}
    public function update(\PluginManager $manager, array $manifest) {}

    public function uninstall(\PluginManager $manager, array $manifest, $purge = false)
    {
        // Posted inventory, ownership, and financial records are retained.
    }

    public function healthCheck(\PluginManager $manager, array $manifest)
    {
        try {
            \Model::fetch('SELECT id FROM plugin_inventory_products LIMIT 1');
            \Model::fetch('SELECT id FROM plugin_inventory_stock_lots LIMIT 1');
            \Model::fetch('SELECT id FROM plugin_inventory_journals LIMIT 1');
            \Model::fetch('SELECT id FROM plugin_inventory_partners LIMIT 1');
            return true;
        } catch (\Throwable $error) {
            return false;
        }
    }
}
