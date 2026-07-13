<?php

namespace Proma\Plugins\Zarinpal\DTO;

class PaymentVerifyResult
{
    public $ok;
    public $code;
    public $message;
    public $referenceId;
    public $cardPan;
    public $cardHash;
    public $fee;
    public $feeType;
    public $alreadyVerified;

    public function __construct(array $values = [])
    {
        foreach ($values as $key => $value) {
            if (property_exists($this, $key)) {
                $this->{$key} = $value;
            }
        }
        $this->ok = (bool) $this->ok;
        $this->alreadyVerified = (bool) $this->alreadyVerified;
    }
}
