<?php

namespace Proma\Plugins\Zarinpal\Support;

class AmountConverter
{
    public static function toGateway($amountToman, $currency)
    {
        $amount = (int) $amountToman;
        $currency = strtoupper(trim((string) $currency));
        if ($amount <= 0 || !in_array($currency, ['IRT', 'IRR'], true)) {
            throw new \InvalidArgumentException('مبلغ یا واحد پول درگاه معتبر نیست.');
        }
        if ($currency === 'IRR') {
            if ($amount > intdiv(PHP_INT_MAX, 10)) {
                throw new \InvalidArgumentException('مبلغ پرداخت از محدوده مجاز بیشتر است.');
            }
            return $amount * 10;
        }
        return $amount;
    }
}
