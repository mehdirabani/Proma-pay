<?php

declare(strict_types=1);

/** One-time API tokens. Only their SHA-256 digest is persisted. */
final class MerchantApiCredentialToken
{
    public static function issue(string $environment = 'test'): array
    {
        $environment = strtolower(trim($environment));
        if (!in_array($environment, ['live', 'test'], true)) {
            throw new InvalidArgumentException('merchant_credential_environment_invalid');
        }

        $secret = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
        $token = 'pmp_' . $environment . '_' . $secret;

        return [
            'token' => $token,
            'prefix' => substr($token, 0, 16),
            'hash' => hash('sha256', $token, true),
            'environment' => $environment,
        ];
    }

    public static function hashForLookup(string $token): string
    {
        if (!preg_match('/^pmp_(?:live|test)_([A-Za-z0-9_-]{43})$/D', $token, $matches)) {
            throw new InvalidArgumentException('merchant_credential_invalid');
        }

        $base64 = strtr($matches[1], '-_', '+/');
        $decoded = base64_decode($base64 . '=', true);
        $canonical = is_string($decoded)
            ? rtrim(strtr(base64_encode($decoded), '+/', '-_'), '=')
            : '';

        if (!is_string($decoded) || strlen($decoded) !== 32 || !hash_equals($canonical, $matches[1])) {
            throw new InvalidArgumentException('merchant_credential_invalid');
        }

        return hash('sha256', $token, true);
    }

    public static function prefix(string $token): string
    {
        self::hashForLookup($token);
        return substr($token, 0, 16);
    }

    public static function normalizeScopes(array $scopes): array
    {
        $normalized = [];
        foreach ($scopes as $scope) {
            if (!is_string($scope)) {
                throw new InvalidArgumentException('merchant_scope_invalid');
            }
            $scope = trim($scope);
            if ($scope === '' || $scope === '*' || !preg_match('/^[a-z][a-z0-9_.:-]{1,79}$/D', $scope)) {
                throw new InvalidArgumentException('merchant_scope_invalid');
            }
            $normalized[$scope] = $scope;
        }

        if (!$normalized) {
            throw new InvalidArgumentException('merchant_scope_required');
        }

        $normalized = array_values($normalized);
        sort($normalized, SORT_STRING);
        return $normalized;
    }

    public static function encodeScopes(array $scopes): string
    {
        return json_encode(
            self::normalizeScopes($scopes),
            JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
        );
    }

    public static function decodeScopes(string $json): array
    {
        try {
            $scopes = json_decode($json, true, 32, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new UnexpectedValueException('merchant_scope_data_invalid', 0, $e);
        }
        if (!is_array($scopes) || array_values($scopes) !== $scopes) {
            throw new UnexpectedValueException('merchant_scope_data_invalid');
        }
        try {
            return self::normalizeScopes($scopes);
        } catch (InvalidArgumentException $e) {
            throw new UnexpectedValueException('merchant_scope_data_invalid', 0, $e);
        }
    }
}
