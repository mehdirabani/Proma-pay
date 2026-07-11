CREATE TABLE IF NOT EXISTS system_plugins (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    plugin_id VARCHAR(100) NOT NULL,
    name VARCHAR(190) NOT NULL,
    description TEXT NULL,
    version VARCHAR(40) NOT NULL,
    path VARCHAR(255) NOT NULL,
    status VARCHAR(30) NOT NULL DEFAULT 'discovered',
    installed_at DATETIME NULL,
    activated_at DATETIME NULL,
    deactivated_at DATETIME NULL,
    updated_at DATETIME NULL,
    installed_by BIGINT UNSIGNED NULL,
    last_error TEXT NULL,
    manifest_json LONGTEXT NULL,
    deleted_at DATETIME NULL,
    UNIQUE KEY uq_system_plugins_plugin_id (plugin_id),
    KEY idx_system_plugins_status (status),
    KEY idx_system_plugins_installed_by (installed_by)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS system_plugin_migrations (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    plugin_id VARCHAR(100) NOT NULL,
    migration_name VARCHAR(190) NOT NULL,
    batch INT UNSIGNED NOT NULL DEFAULT 1,
    checksum CHAR(64) NOT NULL,
    executed_at DATETIME NULL,
    execution_time_ms INT UNSIGNED NOT NULL DEFAULT 0,
    status VARCHAR(30) NOT NULL DEFAULT 'running',
    error_message TEXT NULL,
    UNIQUE KEY uq_system_plugin_migration (plugin_id, migration_name),
    KEY idx_system_plugin_migrations_status (status),
    KEY idx_system_plugin_migrations_executed_at (executed_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS system_plugin_permissions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    plugin_id VARCHAR(100) NOT NULL,
    permission_key VARCHAR(190) NOT NULL,
    label VARCHAR(190) NOT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL,
    UNIQUE KEY uq_system_plugin_permission (plugin_id, permission_key),
    KEY idx_system_plugin_permissions_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS system_plugin_role_permissions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    plugin_id VARCHAR(100) NOT NULL,
    permission_key VARCHAR(190) NOT NULL,
    role VARCHAR(40) NOT NULL,
    is_allowed TINYINT(1) NOT NULL DEFAULT 0,
    granted_by BIGINT UNSIGNED NULL,
    updated_at DATETIME NULL,
    UNIQUE KEY uq_system_plugin_role_permission (plugin_id, permission_key, role),
    KEY idx_system_plugin_role_permissions_role (role)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS system_plugin_logs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    plugin_id VARCHAR(100) NOT NULL,
    log_level VARCHAR(20) NOT NULL,
    log_type VARCHAR(50) NOT NULL,
    message TEXT NOT NULL,
    context_json LONGTEXT NULL,
    created_at DATETIME NOT NULL,
    KEY idx_system_plugin_logs_plugin (plugin_id),
    KEY idx_system_plugin_logs_level (log_level),
    KEY idx_system_plugin_logs_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
