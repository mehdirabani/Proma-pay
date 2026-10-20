<?php

namespace Proma\Plugins\Affiliate;

use Proma\Plugins\Affiliate\Services\AffiliateService;

final class AffiliateServiceProvider implements \PluginServiceProviderInterface
{
    private static bool $hooksRegistered = false;

    public function register(\PluginManager $manager, array $manifest): void
    {
        $root = (string) ($manifest['_root'] ?? '');
        spl_autoload_register(static function ($class) use ($root): void {
            $prefix = 'Proma\\Plugins\\Affiliate\\';
            if (strpos((string) $class, $prefix) !== 0) return;
            $path = rtrim($root, '/\\') . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR
                . str_replace('\\', DIRECTORY_SEPARATOR, substr((string) $class, strlen($prefix))) . '.php';
            if (is_file($path)) require_once $path;
        });
        foreach ($manifest['routes'] ?? [] as $route) {
            if (!is_array($route) || empty($route['path']) || empty($route['handler'])) continue;
            $manager->registerRoute($manifest['id'], $route['path'], $route['handler'], [
                'method' => $route['method'] ?? 'GET', 'permission' => $route['permission'] ?? '', 'auth' => $route['auth'] ?? true,
            ]);
        }
        foreach ([
            ['route'=>'plugin/affiliate/admin','label'=>'مدیریت افیلیت','icon'=>'users','permission'=>'plugin.proma-affiliate.view_admin','roles'=>['admin']],
            ['route'=>'plugin/affiliate/withdrawals','label'=>'برداشت‌های افیلیت','icon'=>'dollar-sign','permission'=>'plugin.proma-affiliate.view_admin','roles'=>['admin']],
            ['route'=>'plugin/affiliate/wallet','label'=>'کسب درآمد','icon'=>'trending-up','permission'=>'','roles'=>['customer']],
        ] as $menu) {
            $menu['group_label'] = in_array('customer', (array) ($menu['roles'] ?? []), true) ? 'کسب درآمد' : 'افیلیت مارکتینگ';
            $menu['group_icon'] = 'gift';
            $manager->registerMenu($manifest['id'], $menu);
        }
    }

    public function boot(\PluginManager $manager, array $manifest): void
    {
        if (self::$hooksRegistered) return;
        \PluginHooks::listen('affiliate.customer.created', static function (array $payload): void {
            $referralCode = (string) ($payload['referral_code'] ?? '');
            if ($referralCode !== '') AffiliateService::registerReferral((int) ($payload['customer_id'] ?? 0), $referralCode);
        }, 20);
        \PluginHooks::listen('affiliate.contract.sale_selected', static function (array $payload): void {
            AffiliateService::registerContractReferral((int) ($payload['contract_id'] ?? 0), (int) ($payload['customer_id'] ?? 0), (int) ($payload['referrer_user_id'] ?? 0));
        }, 20);
        \PluginHooks::listen('affiliate.contract.created', static function (array $payload): void {
            AffiliateService::completeReferralSale((int) ($payload['contract_id'] ?? 0), (int) ($payload['customer_id'] ?? 0));
        }, 30);
        \PluginHooks::listen('payment.completed', static function (array $payload): void {
            $hasContractAffiliate = AffiliateService::completeContractReferralPurchase((int) ($payload['contract_id'] ?? 0), (int) ($payload['payment_id'] ?? 0));
            if (!$hasContractAffiliate) AffiliateService::completeReferralPurchase((int) ($payload['customer_id'] ?? 0), (int) ($payload['payment_id'] ?? 0));
        }, 40);
        \PluginHooks::listen('dashboard.customer.widgets', static function (array $payload): array {
            $widgets = (array) ($payload['value'] ?? []);
            $customerId = (int) ($payload['context']['customer_id'] ?? 0);
            if ($customerId <= 0) return $widgets;
            try {
                $member = AffiliateService::ensureMember($customerId);
                $widgets[] = [
                    'title' => 'کسب درآمد',
                    'description' => 'درآمد از معرفی مشتری: ' . \money_toman($member['reward_balance'] ?? 0),
                    'icon' => 'trending-up', 'route' => 'plugin/affiliate/wallet', 'action_label' => 'مدیریت درآمد',
                ];
            } catch (\Throwable $ignored) { }
            return $widgets;
        }, 30);
        \PluginHooks::listen('dashboard.admin.widgets', static function (array $payload): array {
            $widgets = (array) ($payload['value'] ?? []);
            $stats = AffiliateService::adminStats();
            $widgets[] = [
                'title' => 'افیلیت مارکتینگ',
                'description' => \to_persian_digits($stats['affiliates'] ?? 0) . ' معرف · ' . \to_persian_digits($stats['referrals'] ?? 0) . ' معرفی/فروش ثبت‌شده',
                'icon' => 'gift', 'route' => 'plugin/affiliate/admin', 'action_label' => 'مدیریت سناریوها',
            ];
            return $widgets;
        }, 30);
        self::$hooksRegistered = true;
    }

    public function install(\PluginManager $manager, array $manifest): void { }
    public function activate(\PluginManager $manager, array $manifest): void { }
    public function deactivate(\PluginManager $manager, array $manifest): void { }
    public function update(\PluginManager $manager, array $manifest): void { }
    public function uninstall(\PluginManager $manager, array $manifest, $purge = false): void { }
    public function healthCheck(\PluginManager $manager, array $manifest): bool
    {
        try {
            \Model::fetch('SELECT user_id FROM proma_affiliate_members LIMIT 1');
            if (!\PluginManager::isActive('proma-accounting')) {
                // Affiliate can be installed before Accounting. It must not
                // mutate wallets until the ledger is active, but a fresh,
                // empty installation is healthy and can finish setup.
                $transactions = \Model::fetch('SELECT COUNT(*) AS total FROM proma_affiliate_transactions') ?: [];
                return (int) ($transactions['total'] ?? 0) === 0;
            }
            $reconciliation = AffiliateService::accountingReconciliation();
            if ($reconciliation['missing_or_inconsistent'] > 0 || $reconciliation['orphan_ledger_entries'] > 0) {
                if (class_exists('PluginRegistry')) {
                    \PluginRegistry::logRuntimeError('affiliate.accounting_reconciliation', new \RuntimeException(
                        'Unreconciled affiliate ledger entries: missing=' . $reconciliation['missing_or_inconsistent']
                        . ', orphan=' . $reconciliation['orphan_ledger_entries']
                    ));
                }
                return false;
            }
            return true;
        }
        catch (\Throwable $e) { return false; }
    }
}
