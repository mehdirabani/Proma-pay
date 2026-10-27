<?php

declare(strict_types=1);

/** Persists site-specific service connections, encrypted cache, and minimized logs. */
final class PromaPay_WC_Connection_Repository
{
    private const CACHE_TTL = 300;

    public function all(): array
    {
        global $wpdb;
        $rows = $wpdb->get_results(
            'SELECT connection_uuid,display_name,base_url,token_prefix,prepayment_gateway_id,currency_divisor,enabled,priority,is_default,api_version,
                    health_status,last_error_code,last_checked_at,last_policy_sync_at,policy_version_uuid
             FROM ' . PromaPay_WC_Installer::table('connections') . '
             ORDER BY is_default DESC,priority ASC,display_name ASC',
            'ARRAY_A'
        );
        return is_array($rows) ? $rows : [];
    }

    public function enabled(): array
    {
        return array_values(array_filter($this->all(), static fn (array $row): bool => (int) $row['enabled'] === 1));
    }

    public function get(string $uuid): ?array
    {
        if (!self::valid_uuid($uuid)) {
            return null;
        }
        global $wpdb;
        $row = $wpdb->get_row(
            $wpdb->prepare(
                'SELECT * FROM ' . PromaPay_WC_Installer::table('connections') . ' WHERE connection_uuid = %s LIMIT 1',
                $uuid
            ),
            'ARRAY_A'
        );
        return is_array($row) ? $row : null;
    }

    public function secret(array $connection): string
    {
        $ciphertext = (string) ($connection['token_ciphertext'] ?? '');
        if ($ciphertext === '') {
            throw new UnexpectedValueException('promapaywc_credential_missing');
        }
        $token = PromaPay_WC_Crypto::decrypt($ciphertext);
        if (!preg_match('/\Apmp_test_[A-Za-z0-9_-]{43}\z/D', $token)) {
            throw new UnexpectedValueException('promapaywc_test_credential_invalid');
        }
        return $token;
    }

    public function save(array $input, ?string $uuid = null): string
    {
        global $wpdb;
        $name = trim((string) ($input['display_name'] ?? ''));
        if ($name === '' || strlen($name) > 80 || preg_match('/[\x00-\x1F\x7F]/', $name)) {
            throw new InvalidArgumentException('promapaywc_connection_name_invalid');
        }
        $baseUrl = PromaPay_WC_Api_Client::normalize_base_url((string) ($input['base_url'] ?? ''));
        $priorityValue = filter_var($input['priority'] ?? 10, FILTER_VALIDATE_INT);
        if ($priorityValue === false || $priorityValue < 1 || $priorityValue > 999) {
            throw new InvalidArgumentException('promapaywc_connection_priority_invalid');
        }
        $enabled = !empty($input['enabled']) ? 1 : 0;
        $makeDefault = !empty($input['is_default']);
        if ($makeDefault && $enabled !== 1) {
            throw new InvalidArgumentException('promapaywc_disabled_default_forbidden');
        }
        $existing = $uuid === null ? null : $this->get($uuid);
        if ($uuid !== null && $existing === null) {
            throw new InvalidArgumentException('promapaywc_connection_not_found');
        }
        $prepaymentGatewayId = trim((string) ($input['prepayment_gateway_id'] ?? ($existing['prepayment_gateway_id'] ?? '')));
        if ($prepaymentGatewayId !== '' && !preg_match('/\A[a-z0-9_:-]{1,100}\z/D', $prepaymentGatewayId)) {
            throw new InvalidArgumentException('promapaywc_prepayment_gateway_invalid');
        }
        $currencyDivisor = filter_var($input['currency_divisor'] ?? ($existing['currency_divisor'] ?? 1), FILTER_VALIDATE_INT);
        if (!in_array($currencyDivisor, [1, 10], true)) {
            throw new InvalidArgumentException('promapaywc_currency_divisor_invalid');
        }
        $reassignDefault = $existing !== null && (int) $existing['is_default'] === 1 && $enabled === 0 && !$makeDefault;

        $rawToken = trim((string) ($input['bearer_token'] ?? ''));
        if ($rawToken !== '' && !preg_match('/\Apmp_test_[A-Za-z0-9_-]{43}\z/D', $rawToken)) {
            throw new InvalidArgumentException('promapaywc_only_test_credentials_allowed');
        }
        if ($existing === null && $rawToken === '') {
            throw new InvalidArgumentException('promapaywc_test_credential_required');
        }
        $ciphertext = $rawToken !== '' ? PromaPay_WC_Crypto::encrypt($rawToken) : (string) $existing['token_ciphertext'];
        $prefix = $rawToken !== '' ? substr($rawToken, 0, 16) : (string) $existing['token_prefix'];
        $uuid = $uuid ?? self::uuid4();
        $now = current_time('mysql', true);

        if ($wpdb->query('START TRANSACTION') === false) {
            throw new RuntimeException('promapaywc_connection_transaction_unavailable');
        }
        try {
            if ($makeDefault || $reassignDefault) {
                if ($wpdb->query('UPDATE ' . PromaPay_WC_Installer::table('connections') . ' SET is_default = 0') === false) {
                    throw new RuntimeException('promapaywc_default_reset_failed');
                }
            }
            if ($existing === null) {
                $result = $wpdb->insert(PromaPay_WC_Installer::table('connections'), [
                    'connection_uuid' => $uuid,
                    'display_name' => $name,
                    'base_url' => $baseUrl,
                    'token_ciphertext' => $ciphertext,
                    'token_prefix' => $prefix,
                    'prepayment_gateway_id' => $prepaymentGatewayId,
                    'currency_divisor' => $currencyDivisor,
                    'enabled' => $enabled,
                    'priority' => (int) $priorityValue,
                    'is_default' => ($makeDefault || ($enabled === 1 && $this->count() === 0)) ? 1 : 0,
                    'health_status' => 'unknown',
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            } else {
                $result = $wpdb->update(PromaPay_WC_Installer::table('connections'), [
                    'display_name' => $name,
                    'base_url' => $baseUrl,
                    'token_ciphertext' => $ciphertext,
                    'token_prefix' => $prefix,
                    'prepayment_gateway_id' => $prepaymentGatewayId,
                    'currency_divisor' => $currencyDivisor,
                    'enabled' => $enabled,
                    'priority' => (int) $priorityValue,
                    'is_default' => $makeDefault ? 1 : ($reassignDefault ? 0 : (int) $existing['is_default']),
                    'health_status' => $rawToken !== '' ? 'unknown' : (string) $existing['health_status'],
                    'last_error_code' => $rawToken !== '' ? null : $existing['last_error_code'],
                    'updated_at' => $now,
                ], ['connection_uuid' => $uuid]);
            }
            if ($result === false) {
                throw new RuntimeException('promapaywc_connection_save_failed');
            }
            if ($reassignDefault) {
                $table = PromaPay_WC_Installer::table('connections');
                $next = $wpdb->get_var("SELECT connection_uuid FROM {$table} WHERE enabled = 1 ORDER BY priority ASC,display_name ASC LIMIT 1");
                if (is_string($next) && self::valid_uuid($next)
                    && $wpdb->update($table, ['is_default' => 1, 'updated_at' => $now], ['connection_uuid' => $next]) === false) {
                    throw new RuntimeException('promapaywc_default_selection_failed');
                }
            }
            if ($wpdb->query('COMMIT') === false) {
                throw new RuntimeException('promapaywc_connection_commit_failed');
            }
        } catch (Throwable $error) {
            $wpdb->query('ROLLBACK');
            throw $error;
        }

        if ($rawToken !== '' || ($existing !== null && $baseUrl !== (string) $existing['base_url'])) {
            $this->delete_policy_cache($uuid);
        }
        return $uuid;
    }

    public function set_enabled(string $uuid, bool $enabled): bool
    {
        global $wpdb;
        if (!self::valid_uuid($uuid)) {
            return false;
        }
        $existing = $this->get($uuid);
        if ($existing === null) {
            return false;
        }
        $table = PromaPay_WC_Installer::table('connections');
        if ($wpdb->query('START TRANSACTION') === false) {
            return false;
        }
        try {
            $result = $wpdb->update($table, [
                'enabled' => $enabled ? 1 : 0,
                'updated_at' => current_time('mysql', true),
            ], ['connection_uuid' => $uuid]);
            if ($result === false) {
                throw new RuntimeException('promapaywc_connection_state_failed');
            }
            if (!$enabled && (int) $existing['is_default'] === 1) {
                if ($wpdb->query("UPDATE {$table} SET is_default = 0") === false) {
                    throw new RuntimeException('promapaywc_default_reset_failed');
                }
                $next = $wpdb->get_var("SELECT connection_uuid FROM {$table} WHERE enabled = 1 ORDER BY priority ASC,display_name ASC LIMIT 1");
                if (is_string($next) && self::valid_uuid($next)
                    && $wpdb->update($table, ['is_default' => 1, 'updated_at' => current_time('mysql', true)], ['connection_uuid' => $next]) === false) {
                    throw new RuntimeException('promapaywc_default_selection_failed');
                }
            }
            if ($wpdb->query('COMMIT') === false) {
                throw new RuntimeException('promapaywc_connection_commit_failed');
            }
            if (!$enabled) {
                $this->delete_policy_cache($uuid);
            }
            return true;
        } catch (Throwable $error) {
            $wpdb->query('ROLLBACK');
            return false;
        }
    }

    public function set_default(string $uuid): bool
    {
        $connection = $this->get($uuid);
        if ($connection === null || (int) $connection['enabled'] !== 1) {
            return false;
        }
        global $wpdb;
        $table = PromaPay_WC_Installer::table('connections');
        if ($wpdb->query('START TRANSACTION') === false) {
            return false;
        }
        $cleared = $wpdb->query("UPDATE {$table} SET is_default = 0");
        $selected = $wpdb->update($table, ['is_default' => 1, 'updated_at' => current_time('mysql', true)], ['connection_uuid' => $uuid]);
        if ($cleared === false || $selected === false) {
            $wpdb->query('ROLLBACK');
            return false;
        }
        if ($wpdb->query('COMMIT') === false) {
            $wpdb->query('ROLLBACK');
            return false;
        }
        return true;
    }

    public function record_health(string $uuid, string $status, string $apiVersion = '', string $policyVersion = '', string $errorCode = ''): void
    {
        global $wpdb;
        $status = in_array($status, ['healthy', 'degraded', 'error'], true) ? $status : 'error';
        $wpdb->update(PromaPay_WC_Installer::table('connections'), [
            'health_status' => $status,
            'api_version' => substr($apiVersion, 0, 16),
            'policy_version_uuid' => self::valid_uuid($policyVersion) ? $policyVersion : null,
            'last_error_code' => self::safe_code($errorCode),
            'last_checked_at' => current_time('mysql', true),
            'last_policy_sync_at' => $policyVersion !== '' && self::valid_uuid($policyVersion) ? current_time('mysql', true) : null,
            'updated_at' => current_time('mysql', true),
        ], ['connection_uuid' => $uuid]);
    }

    public function cache_policy(string $uuid, array $policy): void
    {
        if (!self::valid_uuid($uuid)
            || !self::valid_uuid((string) ($policy['policy_version_uuid'] ?? ''))
            || !preg_match('/\A[a-f0-9]{64}\z/D', (string) ($policy['policy_sha256'] ?? ''))
            || !is_array($policy['document'] ?? null)) {
            throw new InvalidArgumentException('promapaywc_policy_cache_invalid');
        }
        $json = wp_json_encode($policy, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        $ciphertext = PromaPay_WC_Crypto::encrypt($json);
        global $wpdb;
        $result = $wpdb->replace(PromaPay_WC_Installer::table('policy_cache'), [
            'connection_uuid' => $uuid,
            'policy_type' => 'financial',
            'policy_version_uuid' => $policy['policy_version_uuid'],
            'policy_sha256' => $policy['policy_sha256'],
            'payload_ciphertext' => $ciphertext,
            'expires_at_epoch' => time() + self::CACHE_TTL,
            'updated_at' => current_time('mysql', true),
        ]);
        if ($result === false) {
            throw new RuntimeException('promapaywc_policy_cache_write_failed');
        }
    }

    public function cached_policy(string $uuid): ?array
    {
        global $wpdb;
        $row = $wpdb->get_row(
            $wpdb->prepare(
                'SELECT payload_ciphertext,expires_at_epoch FROM ' . PromaPay_WC_Installer::table('policy_cache') . '
                 WHERE connection_uuid = %s AND policy_type = %s LIMIT 1',
                $uuid,
                'financial'
            ),
            'ARRAY_A'
        );
        if (!is_array($row) || (int) $row['expires_at_epoch'] <= time()) {
            if (is_array($row)) {
                $this->delete_policy_cache($uuid);
            }
            return null;
        }
        try {
            $policy = json_decode(PromaPay_WC_Crypto::decrypt((string) $row['payload_ciphertext']), true, 32, JSON_THROW_ON_ERROR);
        } catch (Throwable $error) {
            $this->delete_policy_cache($uuid);
            return null;
        }
        return is_array($policy) ? $policy : null;
    }

    public function delete_policy_cache(string $uuid): void
    {
        global $wpdb;
        if (self::valid_uuid($uuid)) {
            $wpdb->delete(PromaPay_WC_Installer::table('policy_cache'), [
                'connection_uuid' => $uuid,
                'policy_type' => 'financial',
            ]);
        }
    }

    public function log(string $uuid, string $operation, int $status, string $outcome, int $durationMs, string $requestId = '', string $errorCode = ''): void
    {
        global $wpdb;
        if (!self::valid_uuid($uuid)) {
            return;
        }
        $wpdb->insert(PromaPay_WC_Installer::table('communication_logs'), [
            'connection_uuid' => $uuid,
            'created_at' => current_time('mysql', true),
            'operation' => preg_match('/\A[a-z_]{1,40}\z/D', $operation) ? $operation : 'unknown',
            'http_status' => max(0, min(599, $status)),
            'outcome' => in_array($outcome, ['success', 'http_error', 'transport_error', 'invalid_response'], true) ? $outcome : 'invalid_response',
            'duration_ms' => max(0, min(600000, $durationMs)),
            'request_id' => self::safe_request_id($requestId),
            'error_code' => self::safe_code($errorCode),
        ]);
        if (random_int(1, 200) === 1) {
            $this->cleanup_expired();
        }
    }

    public function recent_logs(int $limit = 25): array
    {
        global $wpdb;
        $limit = max(1, min(100, $limit));
        $rows = $wpdb->get_results(
            'SELECT l.created_at,l.connection_uuid,c.display_name,l.operation,l.http_status,l.outcome,
                    l.duration_ms,l.request_id,l.error_code
             FROM ' . PromaPay_WC_Installer::table('communication_logs') . ' l
             LEFT JOIN ' . PromaPay_WC_Installer::table('connections') . ' c ON c.connection_uuid = l.connection_uuid
             ORDER BY l.log_id DESC LIMIT ' . $limit,
            'ARRAY_A'
        );
        return is_array($rows) ? $rows : [];
    }

    public function cleanup_expired(): void
    {
        global $wpdb;
        $logs = PromaPay_WC_Installer::table('communication_logs');
        $wpdb->query('DELETE FROM ' . PromaPay_WC_Installer::table('policy_cache') . ' WHERE expires_at_epoch < ' . time());
        $wpdb->query("DELETE FROM {$logs} WHERE created_at < DATE_SUB(UTC_TIMESTAMP(), INTERVAL 90 DAY)");
        $cutoff = $wpdb->get_var("SELECT log_id FROM {$logs} ORDER BY log_id DESC LIMIT 1 OFFSET 49999");
        if (is_numeric($cutoff)) {
            $wpdb->query("DELETE FROM {$logs} WHERE log_id < " . (int) $cutoff);
        }
    }

    private function count(): int
    {
        global $wpdb;
        return (int) $wpdb->get_var('SELECT COUNT(*) FROM ' . PromaPay_WC_Installer::table('connections'));
    }

    public static function valid_uuid(string $uuid): bool
    {
        return (bool) preg_match('/\A[0-9a-f]{8}-[0-9a-f]{4}-[1-8][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}\z/iD', $uuid);
    }

    private static function uuid4(): string
    {
        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
        $hex = bin2hex($bytes);
        return substr($hex, 0, 8) . '-' . substr($hex, 8, 4) . '-' . substr($hex, 12, 4) . '-' . substr($hex, 16, 4) . '-' . substr($hex, 20);
    }

    private static function safe_code(string $value): string
    {
        return preg_match('/\A[a-z0-9_-]{1,64}\z/D', $value) ? $value : '';
    }

    private static function safe_request_id(string $value): string
    {
        return self::valid_uuid($value) ? strtolower($value) : '';
    }
}
