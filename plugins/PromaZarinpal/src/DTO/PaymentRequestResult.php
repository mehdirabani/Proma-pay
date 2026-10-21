<?php

namespace Proma\Plugins\Zarinpal\DTO;

class PaymentRequestResult
{
    public $ok;
    public $authority;
    public $redirectUrl;
    public $transactionId;
    public $message;
    public $code;

    public function __construct($ok, $message = '', array $data = [])
    {
        $this->ok = (bool) $ok;
        $this->message = (string) $message;
        foreach ($data as $key => $value) {
            if (property_exists($this, $key)) {
                $this->{$key} = $value;
            }
        }
    }

    public function toArray()
    {
        return ['ok' => $this->ok, 'message' => $this->message, 'authority' => $this->authority, 'redirect_url' => $this->redirectUrl, 'transaction_id' => $this->transactionId, 'code' => $this->code];
    }
}
