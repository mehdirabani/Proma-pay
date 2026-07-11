<?php

namespace Proma\Plugins\Accounting;

class AccountingServiceProvider implements \PluginServiceProviderInterface
{
    public function register(\PluginManager $manager, array $manifest)
    {
        foreach (['Money', 'AccountingRepository', 'LedgerService', 'CommissionService', 'SalesService'] as $file) {
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
        $manager->registerMenu($manifest['id'], [
            'route' => 'plugin/accounting/dashboard',
            'label' => 'حسابداری',
            'icon' => 'briefcase',
            'permission' => 'plugin.proma-accounting.view',
        ]);
    }

    public function boot(\PluginManager $manager, array $manifest)
    {
        \PluginHooks::listen('contract.created', function (array $payload) {
            SalesService::recordFromContract((int) ($payload['contract_id'] ?? 0), (int) ($payload['actor_user_id'] ?? 0));
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
    }

    public function uninstall(\PluginManager $manager, array $manifest, $purge = false)
    {
        // Financial history is intentionally preserved by the default uninstall.
    }

    public function healthCheck(\PluginManager $manager, array $manifest)
    {
        try {
            \Model::fetch('SELECT id FROM plugin_accounting_accounts LIMIT 1');
            \Model::fetch('SELECT id FROM plugin_accounting_ledger_entries LIMIT 1');
            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }
}
