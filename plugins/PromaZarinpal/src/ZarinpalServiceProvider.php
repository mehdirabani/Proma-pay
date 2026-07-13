<?php

namespace Proma\Plugins\Zarinpal;

use Proma\Plugins\Zarinpal\Repositories\ZarinpalTransactionRepository;
use Proma\Plugins\Zarinpal\Services\ZarinpalGateway;
use Proma\Plugins\Zarinpal\Services\ZarinpalSettingsService;

class ZarinpalServiceProvider implements \PluginServiceProviderInterface
{
    private static $autoloadRegistered = false;

    public function register(\PluginManager $manager, array $manifest)
    {
        $this->registerAutoload($manifest['_root']);
        foreach ($manifest['routes'] ?? [] as $route) {
            if (!is_array($route) || empty($route['path']) || empty($route['handler'])) {
                continue;
            }
            $manager->registerRoute($manifest['id'], $route['path'], $route['handler'], [
                'method' => strtoupper((string) ($route['method'] ?? 'GET')),
                'permission' => (string) ($route['permission'] ?? ''),
                'auth' => array_key_exists('auth', $route) ? (bool) $route['auth'] : true,
            ]);
        }
        foreach ([
            ['route' => 'plugin/zarinpal/transactions', 'label' => 'تراکنش‌ها', 'icon' => 'credit-card', 'permission' => 'plugin.proma-zarinpal.view_zarinpal_transactions'],
            ['route' => 'plugin/zarinpal/settings', 'label' => 'تنظیمات درگاه', 'icon' => 'settings', 'permission' => 'plugin.proma-zarinpal.manage_zarinpal_settings'],
            ['route' => 'plugin/zarinpal/sandbox', 'label' => 'محیط آزمایشی', 'icon' => 'shield', 'permission' => 'plugin.proma-zarinpal.use_zarinpal_sandbox'],
        ] as $menu) {
            $menu['group_label'] = 'درگاه زرین‌پال';
            $menu['group_icon'] = 'credit-card';
            $manager->registerMenu($manifest['id'], $menu);
        }
        \PaymentGatewayRegistry::instance()->register(new ZarinpalGateway());
    }

    public function boot(\PluginManager $manager, array $manifest)
    {
        $manager->registerHook($manifest['id'], 'report.data.providers', function (array $payload) {
            $value = $payload['value'] ?? [];
            if (!is_array($value)) {
                $value = [];
            }
            $value['proma_zarinpal_transactions'] = [
                'label' => 'تراکنش‌های زرین‌پال',
                'permission' => 'plugin.proma-zarinpal.view_zarinpal_transactions',
            ];
            return $value;
        }, 20);
    }

    public function install(\PluginManager $manager, array $manifest)
    {
        $this->registerAutoload($manifest['_root']);
        (new ZarinpalSettingsService())->save(ZarinpalSettingsService::defaults(), \Auth::id());
        $this->audit('installed', ['version' => $manifest['version']]);
    }

    public function activate(\PluginManager $manager, array $manifest)
    {
        $this->registerAutoload($manifest['_root']);
        $gateway = new ZarinpalGateway();
        if (!$gateway->healthCheck()) {
            throw new \RuntimeException('جداول افزونه زرین‌پال آماده نیستند.');
        }
        $this->audit('activated', ['enabled' => false]);
    }

    public function deactivate(\PluginManager $manager, array $manifest)
    {
        $this->registerAutoload($manifest['_root']);
        $pending = (new ZarinpalTransactionRepository())->unresolvedCount();
        if ($pending > 0) {
            throw new \RuntimeException('تعدادی تراکنش زرین‌پال هنوز تعیین تکلیف نشده‌اند. ابتدا آن‌ها را بررسی کنید.');
        }
        $this->audit('deactivated');
    }

    public function update(\PluginManager $manager, array $manifest)
    {
        $this->registerAutoload($manifest['_root']);
        $this->audit('updated', ['version' => $manifest['version']]);
    }

    public function uninstall(\PluginManager $manager, array $manifest, $purge = false)
    {
        $this->registerAutoload($manifest['_root']);
        if ($purge) {
            throw new \RuntimeException('حذف کامل تاریخچه مالی زرین‌پال از چرخه عادی افزونه مجاز نیست. ابتدا بکاپ و فرایند آرشیو مالی انجام دهید.');
        }
        $this->audit('uninstalled', ['data_preserved' => true]);
    }

    public function healthCheck(\PluginManager $manager, array $manifest)
    {
        $this->registerAutoload($manifest['_root']);
        return (new ZarinpalGateway())->healthCheck();
    }

    private function registerAutoload($root)
    {
        if (self::$autoloadRegistered) {
            return;
        }
        $source = rtrim((string) $root, '/\\') . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR;
        spl_autoload_register(static function ($class) use ($source) {
            $prefix = 'Proma\\Plugins\\Zarinpal\\';
            if (strpos((string) $class, $prefix) !== 0) {
                return;
            }
            $relative = substr((string) $class, strlen($prefix));
            $path = $source . str_replace('\\', DIRECTORY_SEPARATOR, $relative) . '.php';
            if (is_file($path)) {
                require_once $path;
            }
        });
        self::$autoloadRegistered = true;
    }

    private function audit($action, array $values = [])
    {
        try {
            \AuditLog::record('plugin', 'zarinpal_' . $action, 'plugin', 0, [
                'actor_user_id' => \Auth::id(),
                'description' => 'چرخه عمر افزونه زرین‌پال',
                'new_values' => $values + ['plugin_id' => 'proma-zarinpal'],
            ]);
        } catch (\Throwable $ignored) {
        }
    }
}
