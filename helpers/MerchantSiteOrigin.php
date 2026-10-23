<?php

declare(strict_types=1);

/** Normalize merchant origins without doing DNS lookups or outbound requests. */
final class MerchantSiteOrigin
{
    public static function normalize(string $origin): string
    {
        $origin = trim($origin);
        if ($origin === '' || strlen($origin) > 270) {
            throw new InvalidArgumentException('merchant_site_origin_invalid');
        }

        $parts = parse_url($origin);
        if (!is_array($parts)
            || strtolower((string) ($parts['scheme'] ?? '')) !== 'https'
            || isset($parts['user'])
            || isset($parts['pass'])
            || isset($parts['query'])
            || isset($parts['fragment'])
            || (isset($parts['port']) && (int) $parts['port'] !== 443)
            || !in_array((string) ($parts['path'] ?? ''), ['', '/'], true)) {
            throw new InvalidArgumentException('merchant_site_origin_invalid');
        }

        $host = strtolower(rtrim((string) ($parts['host'] ?? ''), '.'));
        $label = '[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?';
        if ($host === ''
            || strlen($host) > 253
            || filter_var($host, FILTER_VALIDATE_IP) !== false
            || $host === 'localhost'
            || !preg_match('/^(?:' . $label . '\.)+' . $label . '$/D', $host)) {
            throw new InvalidArgumentException('merchant_site_origin_invalid');
        }

        return 'https://' . $host;
    }
}
