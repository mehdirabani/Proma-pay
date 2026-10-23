<?php

declare(strict_types=1);

/** Canonical immutable JSON snapshots used by versioned merchant policies. */
final class MerchantPolicyDocument
{
    private const TYPES = ['financial', 'guarantee', 'commission', 'settlement'];
    private const MAX_BYTES = 65535;

    public static function types(): array
    {
        return self::TYPES;
    }

    public static function canonicalize(string $type, array $policy): array
    {
        $type = strtolower(trim($type));
        if (!in_array($type, self::TYPES, true) || !$policy || self::isList($policy)) {
            throw new InvalidArgumentException('merchant_policy_invalid');
        }

        $normalized = self::normalizeValue($policy);
        try {
            $json = json_encode(
                $normalized,
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR
            );
        } catch (JsonException $e) {
            throw new InvalidArgumentException('merchant_policy_invalid', 0, $e);
        }

        if (!is_string($json) || strlen($json) > self::MAX_BYTES) {
            throw new InvalidArgumentException('merchant_policy_too_large');
        }

        return [
            'type' => $type,
            'json' => $json,
            'sha256' => hash('sha256', $json),
        ];
    }

    private static function normalizeValue($value)
    {
        if (is_array($value)) {
            if (self::isList($value)) {
                $result = [];
                foreach ($value as $item) {
                    $result[] = self::normalizeValue($item);
                }
                return $result;
            }

            $keys = array_keys($value);
            foreach ($keys as $key) {
                if (!is_string($key) || $key === '') {
                    throw new InvalidArgumentException('merchant_policy_key_invalid');
                }
            }
            sort($keys, SORT_STRING);
            $result = [];
            foreach ($keys as $key) {
                $result[$key] = self::normalizeValue($value[$key]);
            }
            return $result;
        }

        if (is_object($value) || is_resource($value) || (is_float($value) && !is_finite($value))) {
            throw new InvalidArgumentException('merchant_policy_value_invalid');
        }

        if ($value === null || is_bool($value) || is_int($value) || is_float($value) || is_string($value)) {
            return $value;
        }

        throw new InvalidArgumentException('merchant_policy_value_invalid');
    }

    private static function isList(array $value): bool
    {
        if ($value === []) {
            return true;
        }
        $expected = 0;
        foreach ($value as $key => $_) {
            if ($key !== $expected++) {
                return false;
            }
        }
        return true;
    }
}
