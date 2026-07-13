<?php

namespace Proma\Plugins\Zarinpal\DTO;

class PaymentRequestData
{
    public $context;
    public $localOrderId;
    public $internalAmountToman;
    public $gatewayAmount;
    public $currency;
    public $environment;
    public $merchantId;
    public $description;
    public $callbackUrl;

    public function __construct(array $values)
    {
        foreach ($values as $key => $value) {
            if (property_exists($this, $key)) {
                $this->{$key} = $value;
            }
        }
    }
}
