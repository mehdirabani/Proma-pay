<?php

namespace Proma\Plugins\Affiliate\Domain;

final class RewardTrigger
{
    private const EVENTS = ['customer_created', 'sale_created', 'first_purchase'];

    public static function normalize($value): string
    {
        $value = trim((string) $value);
        return in_array($value, self::EVENTS, true) ? $value : 'first_purchase';
    }

    public static function matches($configuredTrigger, string $event): bool
    {
        return in_array($event, self::EVENTS, true) && self::normalize($configuredTrigger) === $event;
    }
}
