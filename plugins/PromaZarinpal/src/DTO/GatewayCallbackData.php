<?php

namespace Proma\Plugins\Zarinpal\DTO;

class GatewayCallbackData
{
    public $authority;
    public $status;

    public function __construct($authority, $status)
    {
        $this->authority = trim((string) $authority);
        $this->status = strtoupper(trim((string) $status));
    }
}
