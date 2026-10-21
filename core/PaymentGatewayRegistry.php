<?php

class PaymentGatewayRegistry
{
    protected static $instance;
    protected static $booted = false;
    protected $providers = [];

    public static function instance()
    {
        if (!self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public static function boot()
    {
        $registry = self::instance();
        if (self::$booted) {
            return $registry;
        }
        self::$booted = true;
        $registry->register(new ZibalGatewayProvider());
        if (class_exists('PluginManager')) {
            PluginManager::boot();
        }
        return $registry;
    }

    public function register(PaymentGatewayProviderInterface $provider)
    {
        $id = strtolower(trim((string) $provider->getId()));
        if (!preg_match('/^[a-z][a-z0-9_-]{1,49}$/', $id)) {
            throw new InvalidArgumentException('شناسه درگاه پرداخت معتبر نیست.');
        }
        if (isset($this->providers[$id]) && $this->providers[$id] !== $provider) {
            throw new InvalidArgumentException('درگاه پرداخت تکراری ثبت شده است.');
        }
        $this->providers[$id] = $provider;
        return $this;
    }

    public function get($id)
    {
        $id = strtolower(trim((string) $id));
        return $this->providers[$id] ?? null;
    }

    public function all($enabledOnly = false, $groupPayment = false)
    {
        $providers = [];
        foreach ($this->providers as $provider) {
            if ($enabledOnly && !$provider->isEnabled()) {
                continue;
            }
            if ($groupPayment && !$provider->supportsPaymentGroups()) {
                continue;
            }
            if (!$groupPayment && !$provider->supportsSinglePayment()) {
                continue;
            }
            $providers[] = $provider;
        }
        return $providers;
    }

    public function resolveCustomerGateway($requestedId = '', $groupPayment = false)
    {
        $settings = Settings::allKeyed();
        $allowSelection = (string) ($settings['payment_allow_gateway_selection'] ?? '1') === '1';
        $defaultId = strtolower(trim((string) ($settings['payment_default_gateway'] ?? 'zibal')));
        $candidateId = $allowSelection ? strtolower(trim((string) $requestedId)) : $defaultId;
        if ($candidateId === '') {
            $candidateId = $defaultId;
        }
        $provider = $this->get($candidateId);
        if (!$provider || !$provider->isEnabled()) {
            $provider = $this->get($defaultId);
        }
        if (!$provider || !$provider->isEnabled()) {
            foreach ($this->all(true, $groupPayment) as $available) {
                $provider = $available;
                break;
            }
        }
        if (!$provider || ($groupPayment ? !$provider->supportsPaymentGroups() : !$provider->supportsSinglePayment())) {
            throw new InvalidArgumentException('درگاه پرداخت فعال و سازگاری برای این عملیات پیدا نشد.');
        }
        return $provider;
    }

    public function customerOptions($groupPayment = false)
    {
        $settings = Settings::allKeyed();
        $defaultId = strtolower(trim((string) ($settings['payment_default_gateway'] ?? 'zibal')));
        $options = [];
        foreach ($this->all(true, $groupPayment) as $provider) {
            $options[] = [
                'id' => (string) $provider->getId(),
                'name' => (string) $provider->getName(),
                'description' => (string) $provider->getDescription(),
                'environment' => (string) $provider->getEnvironmentLabel(),
                'default' => (string) $provider->getId() === $defaultId,
            ];
        }
        if ((string) ($settings['payment_allow_gateway_selection'] ?? '1') !== '1' && count($options) > 1) {
            foreach ($options as $option) {
                if ($option['default']) {
                    return [$option];
                }
            }
            return array_slice($options, 0, 1);
        }
        return $options;
    }
}
