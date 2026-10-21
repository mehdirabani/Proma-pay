<?php

require_once dirname(__DIR__, 2) . '/src/Support/AmountConverter.php';

use Proma\Plugins\Zarinpal\Support\AmountConverter;

if (AmountConverter::toGateway(125000, 'IRT') !== 125000) {
    throw new RuntimeException('IRT conversion failed.');
}
if (AmountConverter::toGateway(125000, 'IRR') !== 1250000) {
    throw new RuntimeException('IRR conversion failed.');
}
try {
    AmountConverter::toGateway(0, 'IRT');
    throw new RuntimeException('Zero amount was not rejected.');
} catch (InvalidArgumentException $expected) {
}

echo "ZARINPAL_AMOUNT_CONVERTER_OK\n";
