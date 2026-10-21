<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/src/Services/Money.php';

use Proma\Plugins\Accounting\Services\Money;

$expect = static function (bool $condition, string $message): void {
    if (!$condition) {
        fwrite(STDERR, "ACCOUNT_BALANCE_INTEGER_INVARIANT_FAILED: {$message}\n");
        exit(1);
    }
};

$expect(Money::signedInteger('2500.00') === 2500, 'positive decimal balance');
$expect(Money::signedInteger('-2500.00') === -2500, 'negative decimal balance');
$expect(Money::signedInteger('۰') === 0, 'Persian zero');
$expect(Money::signedDecimal(-2500) === '-2500.00', 'negative exact decimal formatting');
$expect(Money::signedDecimal(0) === '0.00', 'zero exact decimal formatting');

try {
    Money::signedInteger('1.25');
    $expect(false, 'fractional balance rejected');
} catch (InvalidArgumentException) {
    // Ledger balances are stored and updated in whole currency units.
}

$service = (string) file_get_contents(dirname(__DIR__, 2) . '/src/Services/AccountBalanceService.php');
$expect(strpos($service, '(float)') === false && strpos($service, 'floatval(') === false, 'balance position uses no floating point');

echo "ACCOUNT_BALANCE_INTEGER_INVARIANT_OK\n";
