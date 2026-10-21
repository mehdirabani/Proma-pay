<?php

declare(strict_types=1);

require dirname(__DIR__, 2) . '/plugins/PromaRepair/src/Domain/RepairMoney.php';

use Proma\Plugins\Repair\Domain\RepairMoney;

$assert = static function (bool $condition, string $message): void {
    if (!$condition) throw new RuntimeException($message);
};
$rejects = static function (callable $operation, string $message): void {
    try { $operation(); } catch (InvalidArgumentException | UnexpectedValueException | OverflowException $e) { return; }
    throw new RuntimeException($message);
};

$persian = RepairMoney::input('۱۲۳٬۴۵۶');
$assert($persian === 123456 && is_int($persian), 'Persian digits must normalize to an integer Toman value.');
$assert(RepairMoney::input('1,000,000') === 1000000, 'Valid grouped Toman input must be accepted.');
$rejects(static fn () => RepairMoney::input('-1'), 'Negative money must be rejected.');
$rejects(static fn () => RepairMoney::input('1.25'), 'Fractional Toman input must be rejected explicitly.');
$rejects(static fn () => RepairMoney::input('12,34'), 'Malformed digit grouping must be rejected.');
$rejects(static fn () => RepairMoney::input('1000000000001'), 'Money input overflow must be rejected.');
$assert(RepairMoney::stored('120.00') === 120, 'Whole-Toman DECIMAL values must normalize to integer.');
$rejects(static fn () => RepairMoney::stored('120.01'), 'Fractional historical data must require preflight, not silent rounding.');
$assert(RepairMoney::percentage('12.5') === '12.50', 'Percentage values must retain decimal precision without float conversion.');
$rejects(static fn () => RepairMoney::percentage('100.01'), 'Percentage overflow must be rejected.');

echo "REPAIR_MONEY_INTEGER_INVARIANT_OK\n";
