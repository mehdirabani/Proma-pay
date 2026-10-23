<?php

declare(strict_types=1);

/** Additive schema installer. Upgrades never delete plugin or merchant data. */
final class PromaPay_WC_Installer
{
    public const SCHEMA_VERSION = '1';
    private const OPTION_SCHEMA_VERSION = 'promapaywc_schema_version';

    public static function activate(): void
    {
        self::install_schema();
        self::grant_capability();
    }

    public static function maybe_upgrade(): void
    {
        if (!function_exists('current_user_can') || !current_user_can('manage_proma_pay_connections')) {
            return;
        }
        if ((string) get_option(self::OPTION_SCHEMA_VERSION, '') !== self::SCHEMA_VERSION) {
            self::install_schema();
        }
    }

    private static function install_schema(): void
    {
        global $wpdb;
        if (!is_object($wpdb) || !isset($wpdb->prefix)) {
            return;
        }
        if (!function_exists('dbDelta')) {
            require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        }

        $charset = $wpdb->get_charset_collate();
        $connections = self::table('connections');
        $policyCache = self::table('policy_cache');
        $logs = self::table('communication_logs');
        $sql = [];
        $sql[] = "CREATE TABLE {$connections} (
            connection_uuid char(36) NOT NULL,
            display_name varchar(80) NOT NULL,
            base_url varchar(255) NOT NULL,
            token_ciphertext longtext NOT NULL,
            token_prefix varchar(16) NOT NULL DEFAULT '',
            enabled tinyint(1) NOT NULL DEFAULT 1,
            priority smallint unsigned NOT NULL DEFAULT 10,
            is_default tinyint(1) NOT NULL DEFAULT 0,
            api_version varchar(16) NOT NULL DEFAULT '',
            health_status varchar(20) NOT NULL DEFAULT 'unknown',
            last_error_code varchar(64) NULL,
            last_checked_at datetime NULL,
            last_policy_sync_at datetime NULL,
            policy_version_uuid char(36) NULL,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY  (connection_uuid),
            KEY connection_selection (enabled,is_default,priority),
            KEY health_status (health_status)
        ) ENGINE=InnoDB {$charset};";
        $sql[] = "CREATE TABLE {$policyCache} (
            connection_uuid char(36) NOT NULL,
            policy_type varchar(32) NOT NULL,
            policy_version_uuid char(36) NOT NULL,
            policy_sha256 char(64) NOT NULL,
            payload_ciphertext longtext NOT NULL,
            expires_at_epoch bigint unsigned NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY  (connection_uuid,policy_type),
            KEY policy_expiry (expires_at_epoch)
        ) ENGINE=InnoDB {$charset};";
        $sql[] = "CREATE TABLE {$logs} (
            log_id bigint unsigned NOT NULL AUTO_INCREMENT,
            connection_uuid char(36) NOT NULL,
            created_at datetime NOT NULL,
            operation varchar(40) NOT NULL,
            http_status smallint unsigned NOT NULL DEFAULT 0,
            outcome varchar(24) NOT NULL,
            duration_ms int unsigned NOT NULL DEFAULT 0,
            request_id varchar(80) NOT NULL DEFAULT '',
            error_code varchar(64) NOT NULL DEFAULT '',
            PRIMARY KEY  (log_id),
            KEY connection_created (connection_uuid,created_at),
            KEY outcome_created (outcome,created_at)
        ) ENGINE=InnoDB {$charset};";

        foreach ($sql as $statement) {
            dbDelta($statement);
        }
        foreach ([$connections, $policyCache, $logs] as $table) {
            $found = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table));
            if (!is_string($found) || $found !== $table) {
                throw new RuntimeException('promapaywc_schema_install_failed');
            }
        }
        update_option(self::OPTION_SCHEMA_VERSION, self::SCHEMA_VERSION, false);
        self::grant_capability();
        self::schedule_cleanup();
    }

    private static function grant_capability(): void
    {
        if (function_exists('get_role')) {
            $administrator = get_role('administrator');
            if (is_object($administrator) && method_exists($administrator, 'add_cap')) {
                $administrator->add_cap('manage_proma_pay_connections');
            }
        }
    }

    private static function schedule_cleanup(): void
    {
        if (function_exists('wp_next_scheduled') && !wp_next_scheduled('promapaywc_daily_cleanup')) {
            wp_schedule_event(time() + 300, 'daily', 'promapaywc_daily_cleanup');
        }
    }

    public static function table(string $suffix): string
    {
        global $wpdb;
        $allowed = ['connections', 'policy_cache', 'communication_logs'];
        if (!in_array($suffix, $allowed, true) || !is_object($wpdb) || !isset($wpdb->prefix)) {
            throw new InvalidArgumentException('promapaywc_table_invalid');
        }
        return $wpdb->prefix . 'proma_pay_' . $suffix;
    }
}
