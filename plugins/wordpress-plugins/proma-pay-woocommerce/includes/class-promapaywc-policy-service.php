<?php

declare(strict_types=1);

/** Refreshes server-owned financial policy snapshots and stores them encrypted. */
final class PromaPay_WC_Policy_Service
{
    private PromaPay_WC_Connection_Repository $repository;
    private PromaPay_WC_Api_Client $client;

    public function __construct(PromaPay_WC_Connection_Repository $repository, PromaPay_WC_Api_Client $client)
    {
        $this->repository = $repository;
        $this->client = $client;
    }

    public function refresh(string $uuid): array
    {
        $connection = $this->repository->get($uuid);
        if ($connection === null) {
            return ['ok' => false, 'error_code' => 'connection_not_found'];
        }
        $status = $this->client->request($connection, 'GET', '/connection');
        if (!$status['ok']) {
            $this->repository->record_health($uuid, 'error', '', '', (string) $status['error_code']);
            return ['ok' => false, 'error_code' => (string) $status['error_code']];
        }
        $summary = $status['data']['connection'] ?? null;
        if (!is_array($summary) || ($summary['environment'] ?? '') !== 'test' || ($summary['api_version'] ?? '') !== 'v1') {
            $this->repository->record_health($uuid, 'degraded', '', '', 'connection_response_invalid');
            return ['ok' => false, 'error_code' => 'connection_response_invalid'];
        }

        $policyResponse = $this->client->request($connection, 'GET', '/policies/financial');
        if (!$policyResponse['ok']) {
            $this->repository->record_health(
                $uuid,
                'degraded',
                'v1',
                '',
                (string) $policyResponse['error_code']
            );
            return ['ok' => false, 'error_code' => (string) $policyResponse['error_code']];
        }
        $policy = $policyResponse['data']['policy'] ?? null;
        if (!self::valid_policy($policy)) {
            $this->repository->record_health($uuid, 'degraded', 'v1', '', 'financial_policy_invalid');
            return ['ok' => false, 'error_code' => 'financial_policy_invalid'];
        }

        try {
            $this->repository->cache_policy($uuid, $policy);
        } catch (Throwable $error) {
            $this->repository->record_health($uuid, 'degraded', 'v1', '', 'financial_policy_cache_failed');
            return ['ok' => false, 'error_code' => 'financial_policy_cache_failed'];
        }
        $this->repository->record_health($uuid, 'healthy', 'v1', (string) $policy['policy_version_uuid']);
        return ['ok' => true, 'connection' => $summary, 'policy' => $policy];
    }

    /** Return a fresh encrypted policy snapshot, refreshing the central API only after its bounded TTL. */
    public function current(string $uuid): array
    {
        $policy = $this->repository->cached_policy($uuid);
        if (is_array($policy) && self::valid_policy($policy)) {
            return ['ok' => true, 'policy' => $policy];
        }
        return $this->refresh($uuid);
    }

    public static function valid_policy($policy): bool
    {
        if (!is_array($policy) || array_diff(array_keys($policy), ['policy_type','policy_version_uuid','policy_sha256','source_policy_sha256','document']) !== []
            || !PromaPay_WC_Connection_Repository::valid_uuid((string) ($policy['policy_version_uuid'] ?? ''))
            || !preg_match('/\A[a-f0-9]{64}\z/D', (string) ($policy['policy_sha256'] ?? ''))
            || !preg_match('/\A[a-f0-9]{64}\z/D', (string) ($policy['source_policy_sha256'] ?? ''))
            || ($policy['policy_type'] ?? '') !== 'financial'
            || !is_array($policy['document'] ?? null)) {
            return false;
        }
        $document = $policy['document'];
        if (array_diff(array_keys($document), ['monthly_rate_bps', 'methods', 'limits']) !== []
            || !isset($document['monthly_rate_bps'], $document['methods'])
            || !is_int($document['monthly_rate_bps']) || $document['monthly_rate_bps'] < 0 || $document['monthly_rate_bps'] > 10000
            || !is_array($document['methods']) || !self::is_list($document['methods']) || !$document['methods']) {
            return false;
        }
        foreach ($document['methods'] as $method) {
            if (!is_string($method) || !in_array($method, ['flat', 'compound', 'reducing_balance'], true)) {
                return false;
            }
        }
        if (count(array_unique($document['methods'])) !== count($document['methods'])) {
            return false;
        }
        $limits = $document['limits'] ?? [];
        $allowedLimitKeys = [
            'min_order_toman','max_order_toman','min_months','max_months',
            'min_down_payment_bps','min_down_payment_toman','max_down_payment_bps','first_due_days',
        ];
        if (!is_array($limits) || array_diff(array_keys($limits), $allowedLimitKeys) !== []) {
            return false;
        }
        foreach ($allowedLimitKeys as $key) {
            if (array_key_exists($key, $limits) && (!is_int($limits[$key]) || $limits[$key] < 0)) {
                return false;
            }
        }
        if (isset($limits['min_order_toman']) && $limits['min_order_toman'] < 1) {
            return false;
        }
        if (isset($limits['min_order_toman'], $limits['max_order_toman']) && $limits['max_order_toman'] < $limits['min_order_toman']) {
            return false;
        }
        if (isset($limits['max_months']) && ($limits['max_months'] < 1 || $limits['max_months'] > 480)) {
            return false;
        }
        if (isset($limits['min_months']) && ($limits['min_months'] < 1 || $limits['min_months'] > 480)) {
            return false;
        }
        if (isset($limits['min_months'], $limits['max_months']) && $limits['min_months'] > $limits['max_months']) {
            return false;
        }
        if (isset($limits['min_down_payment_bps']) && $limits['min_down_payment_bps'] > 10000) {
            return false;
        }
        if (isset($limits['max_down_payment_bps']) && ($limits['max_down_payment_bps'] < 1 || $limits['max_down_payment_bps'] > 10000)) {
            return false;
        }
        if (isset($limits['first_due_days']) && ($limits['first_due_days'] < 1 || $limits['first_due_days'] > 90)) {
            return false;
        }
        $canonical = [
            'monthly_rate_bps' => $document['monthly_rate_bps'],
            'methods' => $document['methods'],
            'limits' => $limits,
        ];
        sort($canonical['methods'], SORT_STRING);
        ksort($canonical['limits'], SORT_STRING);
        try {
            $json = json_encode($canonical, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR);
        } catch (JsonException $error) {
            return false;
        }
        return hash_equals((string) $policy['policy_sha256'], hash('sha256', $json));
    }

    private static function is_list(array $value): bool
    {
        $expected = 0;
        foreach ($value as $key => $_) {
            if ($key !== $expected++) {
                return false;
            }
        }
        return true;
    }
}
