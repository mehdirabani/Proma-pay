<?php

declare(strict_types=1);

require dirname(__DIR__, 2) . '/plugins/PromaAffiliate/src/Domain/RewardTrigger.php';

use Proma\Plugins\Affiliate\Domain\RewardTrigger;

$expected = [
    'customer_created' => ['customer_created' => true, 'sale_created' => false, 'first_purchase' => false],
    'sale_created' => ['customer_created' => false, 'sale_created' => true, 'first_purchase' => false],
    'first_purchase' => ['customer_created' => false, 'sale_created' => false, 'first_purchase' => true],
];
foreach ($expected as $trigger => $events) {
    foreach ($events as $event => $shouldMatch) {
        if (RewardTrigger::matches($trigger, $event) !== $shouldMatch) {
            throw new RuntimeException('Affiliate trigger mismatch: ' . $trigger . ' / ' . $event);
        }
    }
}
if (RewardTrigger::normalize('unknown') !== 'first_purchase') throw new RuntimeException('Unknown triggers must fall back to first_purchase.');

echo "AFFILIATE_REWARD_TRIGGER_OK\n";
