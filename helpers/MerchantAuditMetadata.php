<?php

declare(strict_types=1);

/** Validate audit metadata and refuse likely credential material. */
final class MerchantAuditMetadata
{
    private const MAX_BYTES = 16384;
    private const MAX_DEPTH = 8;

    public static function encode(array $metadata): string
    {
        $clean = self::normalize($metadata, 0);
        try {
            $json = json_encode(
                $clean,
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
            );
        } catch (JsonException $e) {
            throw new InvalidArgumentException('merchant_audit_metadata_invalid', 0, $e);
        }

        if (!is_string($json) || strlen($json) > self::MAX_BYTES) {
            throw new InvalidArgumentException('merchant_audit_metadata_too_large');
        }
        return $json;
    }

    private static function normalize(array $items, int $depth): array
    {
        if ($depth > self::MAX_DEPTH) {
            throw new InvalidArgumentException('merchant_audit_metadata_too_deep');
        }

        if (self::isList($items)) {
            $result = [];
            foreach ($items as $value) {
                $result[] = self::normalizeValue($value, $depth + 1);
            }
            return $result;
        }

        $result = [];
        foreach ($items as $key => $value) {
            if (!is_string($key) || $key === ''
                || preg_match('/(?:^|_)(?:secret|token|authorization|password|private_key|api_key|credential_secret|token_hash)(?:_|$)/i', $key)) {
                throw new InvalidArgumentException('merchant_audit_metadata_sensitive');
            }
            $result[$key] = self::normalizeValue($value, $depth + 1);
        }
        return $result;
    }

    private static function normalizeValue($value, int $depth)
    {
        if (is_array($value)) {
            return self::normalize($value, $depth);
        }
        if ($value === null || is_bool($value) || is_int($value) || is_string($value)) {
            if (is_string($value)) {
                if (strlen($value) > 2000) {
                    throw new InvalidArgumentException('merchant_audit_metadata_value_too_large');
                }
                if (preg_match('/pmp_(?:test|live)_[A-Za-z0-9_-]{43}/', $value)) {
                    throw new InvalidArgumentException('merchant_audit_metadata_sensitive');
                }
            }
            return $value;
        }
        if (is_float($value) && is_finite($value)) {
            return $value;
        }
        throw new InvalidArgumentException('merchant_audit_metadata_value_invalid');
    }

    private static function isList(array $items): bool
    {
        $expected = 0;
        foreach ($items as $key => $_) {
            if ($key !== $expected++) {
                return false;
            }
        }
        return true;
    }
}
