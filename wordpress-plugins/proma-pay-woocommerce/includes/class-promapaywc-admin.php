<?php

declare(strict_types=1);

/** Capability- and nonce-protected site connection settings. */
final class PromaPay_WC_Admin
{
    private const CAPABILITY = 'manage_proma_pay_connections';
    private const PAGE = 'proma-pay-connections';

    public static function register(): void
    {
        add_action('admin_menu', [self::class, 'menu']);
        add_action('admin_post_promapaywc_save_connection', [self::class, 'save_connection']);
        add_action('admin_post_promapaywc_toggle_connection', [self::class, 'toggle_connection']);
        add_action('admin_post_promapaywc_default_connection', [self::class, 'default_connection']);
        add_action('admin_post_promapaywc_test_connection', [self::class, 'test_connection']);
    }

    public static function menu(): void
    {
        add_menu_page(
            __('Proma Pay', 'proma-pay-woocommerce'),
            __('Proma Pay', 'proma-pay-woocommerce'),
            self::CAPABILITY,
            self::PAGE,
            [self::class, 'render'],
            'dashicons-money-alt'
        );
    }

    public static function render(): void
    {
        self::authorize();
        $repository = new PromaPay_WC_Connection_Repository();
        $connections = $repository->all();
        $tab = isset($_GET['tab']) ? sanitize_key(wp_unslash($_GET['tab'])) : 'connections';
        $editing = isset($_GET['edit']) ? $repository->get(sanitize_text_field(wp_unslash($_GET['edit']))) : null;
        echo '<div class="wrap"><h1>' . esc_html__('Proma Pay connections', 'proma-pay-woocommerce') . '</h1>';
        self::notice();
        echo '<h2 class="nav-tab-wrapper">';
        self::tab_link('connections', __('Connections', 'proma-pay-woocommerce'), $tab);
        self::tab_link('logs', __('Communication log', 'proma-pay-woocommerce'), $tab);
        echo '</h2>';
        if ($tab === 'logs') {
            self::render_logs($repository);
        } else {
            self::render_connections($repository, $connections, $editing);
        }
        echo '</div>';
    }

    private static function render_connections(PromaPay_WC_Connection_Repository $repository, array $connections, ?array $editing): void
    {
        echo '<p>' . esc_html__('This build accepts test credentials only. Financial rules are read from Proma Pay and cannot be edited here.', 'proma-pay-woocommerce') . '</p>';
        echo '<table class="widefat striped"><thead><tr><th>' . esc_html__('Name', 'proma-pay-woocommerce') . '</th><th>'
            . esc_html__('Service URL', 'proma-pay-woocommerce') . '</th><th>' . esc_html__('Credential', 'proma-pay-woocommerce') . '</th><th>'
            . esc_html__('Health / API', 'proma-pay-woocommerce') . '</th><th>' . esc_html__('Deposit gateway', 'proma-pay-woocommerce') . '</th><th>'
            . esc_html__('Priority', 'proma-pay-woocommerce') . '</th><th>'
            . esc_html__('State', 'proma-pay-woocommerce') . '</th><th>' . esc_html__('Actions', 'proma-pay-woocommerce') . '</th></tr></thead><tbody>';
        if (!$connections) {
            echo '<tr><td colspan="8">' . esc_html__('No service connections have been configured.', 'proma-pay-woocommerce') . '</td></tr>';
        }
        foreach ($connections as $connection) {
            $uuid = (string) $connection['connection_uuid'];
            $cached = $repository->cached_policy($uuid);
            echo '<tr><td>' . esc_html((string) $connection['display_name'])
                . ((int) $connection['is_default'] === 1 ? ' <strong>(' . esc_html__('Default', 'proma-pay-woocommerce') . ')</strong>' : '')
                . '</td><td><code>' . esc_html((string) $connection['base_url']) . '</code></td><td><code>'
                . esc_html((string) $connection['token_prefix']) . '••••••••••••••••</code></td><td>'
                . esc_html((string) $connection['health_status']) . ' / ' . esc_html((string) ($connection['api_version'] ?: '—'));
            if ($cached !== null) {
                echo '<br><small>' . esc_html__('Policy', 'proma-pay-woocommerce') . ': '
                    . esc_html((string) $cached['policy_version_uuid']) . ' · '
                    . esc_html(substr((string) $cached['policy_sha256'], 0, 12)) . '</small>';
            }
            if (!empty($connection['last_error_code'])) {
                echo '<br><small>' . esc_html((string) $connection['last_error_code']) . '</small>';
            }
            echo '</td><td>' . esc_html((string) ($connection['prepayment_gateway_id'] ?? '—'))
                . '</td><td>' . esc_html((string) $connection['priority']) . '</td><td>'
                . ((int) $connection['enabled'] === 1 ? esc_html__('Enabled', 'proma-pay-woocommerce') : esc_html__('Disabled', 'proma-pay-woocommerce'))
                . '</td><td>';
            self::action_form('promapaywc_test_connection', $uuid, __('Test and refresh policy', 'proma-pay-woocommerce'));
            echo ' <a class="button" href="' . esc_url(add_query_arg(['page' => self::PAGE, 'edit' => $uuid], admin_url('admin.php'))) . '">'
                . esc_html__('Edit', 'proma-pay-woocommerce') . '</a> ';
            self::action_form('promapaywc_toggle_connection', $uuid, (int) $connection['enabled'] === 1 ? __('Disable', 'proma-pay-woocommerce') : __('Enable', 'proma-pay-woocommerce'));
            if ((int) $connection['enabled'] === 1 && (int) $connection['is_default'] !== 1) {
                self::action_form('promapaywc_default_connection', $uuid, __('Set default', 'proma-pay-woocommerce'));
            }
            echo '</td></tr>';
        }
        echo '</tbody></table>';
        self::connection_form($editing);
    }

    private static function connection_form(?array $editing): void
    {
        $isEditing = is_array($editing);
        $uuid = $isEditing ? (string) $editing['connection_uuid'] : '';
        echo '<h2>' . esc_html($isEditing ? __('Edit connection', 'proma-pay-woocommerce') : __('Add connection', 'proma-pay-woocommerce')) . '</h2>';
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
        echo '<input type="hidden" name="action" value="promapaywc_save_connection">';
        if ($isEditing) {
            echo '<input type="hidden" name="connection_uuid" value="' . esc_attr($uuid) . '">';
        }
        wp_nonce_field('promapaywc_save_connection');
        echo '<table class="form-table"><tbody>';
        self::text_field('display_name', __('Connection name', 'proma-pay-woocommerce'), (string) ($editing['display_name'] ?? ''), true, 80);
        self::text_field('base_url', __('Proma Pay API base URL', 'proma-pay-woocommerce'), (string) ($editing['base_url'] ?? ''), true, 255, 'url');
        self::text_field('bearer_token', __('Test API token', 'proma-pay-woocommerce'), '', !$isEditing, 128, 'password', $isEditing ? __('Leave blank to keep the saved token.', 'proma-pay-woocommerce') : __('Only pmp_test_ credentials are accepted. Live tokens are not supported in this build.', 'proma-pay-woocommerce'));
        self::text_field('priority', __('Priority (1 is highest)', 'proma-pay-woocommerce'), (string) ($editing['priority'] ?? '10'), true, 3, 'number');
        self::prepayment_gateway_field((string) ($editing['prepayment_gateway_id'] ?? ''));
        self::currency_divisor_field((int) ($editing['currency_divisor'] ?? 1));
        echo '<tr><th scope="row">' . esc_html__('Connection options', 'proma-pay-woocommerce') . '</th><td>';
        echo '<label><input type="checkbox" name="enabled" value="1" ' . checked((int) ($editing['enabled'] ?? 1), 1, false) . '> ' . esc_html__('Enabled', 'proma-pay-woocommerce') . '</label><br>';
        echo '<label><input type="checkbox" name="is_default" value="1" ' . checked((int) ($editing['is_default'] ?? 0), 1, false) . '> ' . esc_html__('Make this the default connection', 'proma-pay-woocommerce') . '</label>';
        echo '</td></tr></tbody></table>';
        submit_button($isEditing ? __('Save connection', 'proma-pay-woocommerce') : __('Add connection', 'proma-pay-woocommerce'));
        echo '</form>';
    }

    private static function prepayment_gateway_field(string $selected): void
    {
        $gateways = PromaPay_WC_Payment_Adapter::available_gateways();
        echo '<tr><th scope="row"><label for="promapaywc-prepayment-gateway">' . esc_html__('Deposit payment gateway', 'proma-pay-woocommerce') . '</label></th><td>';
        echo '<select id="promapaywc-prepayment-gateway" name="prepayment_gateway_id"><option value="">'
            . esc_html__('Select an enabled online WooCommerce gateway', 'proma-pay-woocommerce') . '</option>';
        foreach ($gateways as $id => $title) {
            echo '<option value="' . esc_attr((string) $id) . '" ' . selected($selected, (string) $id, false) . '>'
                . esc_html((string) $title . ' (' . $id . ')') . '</option>';
        }
        echo '</select><p class="description">' . esc_html__('The gateway charges a separate order for the exact deposit. Cash, cheque, and Proma Pay gateways are excluded.', 'proma-pay-woocommerce') . '</p></td></tr>';
    }

    private static function currency_divisor_field(int $selected): void
    {
        echo '<tr><th scope="row"><label for="promapaywc-currency-divisor">' . esc_html__('Store currency units per Toman', 'proma-pay-woocommerce') . '</label></th><td>';
        echo '<select id="promapaywc-currency-divisor" name="currency_divisor">';
        foreach ([1 => __('Store currency is Toman', 'proma-pay-woocommerce'), 10 => __('Store currency is Rial (10 Rial = 1 Toman)', 'proma-pay-woocommerce')] as $value => $label) {
            echo '<option value="' . esc_attr((string) $value) . '" ' . selected($selected, $value, false) . '>' . esc_html($label) . '</option>';
        }
        echo '</select></td></tr>';
    }

    private static function render_logs(PromaPay_WC_Connection_Repository $repository): void
    {
        echo '<p>' . esc_html__('Logs contain operation, status, duration, request ID, and safe error code only. Request bodies, response bodies, customer details, and credentials are not stored.', 'proma-pay-woocommerce') . '</p>';
        echo '<table class="widefat striped"><thead><tr><th>' . esc_html__('Time (UTC)', 'proma-pay-woocommerce') . '</th><th>'
            . esc_html__('Connection', 'proma-pay-woocommerce') . '</th><th>' . esc_html__('Operation', 'proma-pay-woocommerce') . '</th><th>'
            . esc_html__('HTTP', 'proma-pay-woocommerce') . '</th><th>' . esc_html__('Outcome', 'proma-pay-woocommerce') . '</th><th>'
            . esc_html__('Duration', 'proma-pay-woocommerce') . '</th><th>' . esc_html__('Request ID / Error', 'proma-pay-woocommerce') . '</th></tr></thead><tbody>';
        $logs = $repository->recent_logs(50);
        if (!$logs) {
            echo '<tr><td colspan="7">' . esc_html__('No API communication has been logged.', 'proma-pay-woocommerce') . '</td></tr>';
        }
        foreach ($logs as $log) {
            echo '<tr><td>' . esc_html((string) $log['created_at']) . '</td><td>' . esc_html((string) ($log['display_name'] ?? ''))
                . '</td><td>' . esc_html((string) $log['operation']) . '</td><td>' . esc_html((string) $log['http_status'])
                . '</td><td>' . esc_html((string) $log['outcome']) . '</td><td>' . esc_html((string) $log['duration_ms']) . ' ms</td><td>'
                . esc_html((string) $log['request_id']) . ' ' . esc_html((string) $log['error_code']) . '</td></tr>';
        }
        echo '</tbody></table>';
    }

    private static function text_field(string $name, string $label, string $value, bool $required, int $maxLength, string $type = 'text', string $description = ''): void
    {
        echo '<tr><th scope="row"><label for="promapaywc-' . esc_attr($name) . '">' . esc_html($label) . '</label></th><td>';
        echo '<input class="regular-text" id="promapaywc-' . esc_attr($name) . '" name="' . esc_attr($name) . '" type="'
            . esc_attr($type) . '" value="' . esc_attr($value) . '" maxlength="' . esc_attr((string) $maxLength) . '"'
            . ($required ? ' required' : '') . ($type === 'number' ? ' min="1" max="999" step="1"' : '') . '>';
        if ($description !== '') {
            echo '<p class="description">' . esc_html($description) . '</p>';
        }
        echo '</td></tr>';
    }

    private static function action_form(string $action, string $uuid, string $label): void
    {
        echo '<form class="inline-form" style="display:inline" method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
        echo '<input type="hidden" name="action" value="' . esc_attr($action) . '"><input type="hidden" name="connection_uuid" value="' . esc_attr($uuid) . '">';
        wp_nonce_field($action . ':' . $uuid);
        submit_button($label, 'secondary small', 'submit', false);
        echo '</form> ';
    }

    private static function tab_link(string $tab, string $label, string $selected): void
    {
        $class = $selected === $tab ? ' nav-tab-active' : '';
        echo '<a class="nav-tab' . esc_attr($class) . '" href="' . esc_url(add_query_arg(['page' => self::PAGE, 'tab' => $tab], admin_url('admin.php'))) . '">'
            . esc_html($label) . '</a> ';
    }

    private static function notice(): void
    {
        $notice = isset($_GET['promapay_notice']) ? sanitize_key(wp_unslash($_GET['promapay_notice'])) : '';
        $messages = [
            'saved' => __('Connection saved.', 'proma-pay-woocommerce'),
            'disabled' => __('Connection disabled.', 'proma-pay-woocommerce'),
            'enabled' => __('Connection enabled.', 'proma-pay-woocommerce'),
            'default' => __('Default connection updated.', 'proma-pay-woocommerce'),
            'healthy' => __('Connection and financial policy refreshed.', 'proma-pay-woocommerce'),
            'error' => __('The request could not be completed. Check the safe error code in the connection or communication log.', 'proma-pay-woocommerce'),
        ];
        if (isset($messages[$notice])) {
            $class = in_array($notice, ['healthy', 'saved', 'enabled', 'default'], true) ? 'notice-success' : 'notice-warning';
            echo '<div class="notice ' . esc_attr($class) . ' is-dismissible"><p>' . esc_html($messages[$notice]) . '</p></div>';
        }
    }

    public static function save_connection(): void
    {
        self::authorize();
        self::require_admin_tls();
        check_admin_referer('promapaywc_save_connection');
        try {
            $input = [
                'display_name' => sanitize_text_field(wp_unslash($_POST['display_name'] ?? '')),
                'base_url' => trim((string) wp_unslash($_POST['base_url'] ?? '')),
                'bearer_token' => trim((string) wp_unslash($_POST['bearer_token'] ?? '')),
                'priority' => wp_unslash($_POST['priority'] ?? '10'),
                'prepayment_gateway_id' => sanitize_key(wp_unslash($_POST['prepayment_gateway_id'] ?? '')),
                'currency_divisor' => wp_unslash($_POST['currency_divisor'] ?? '1'),
                'enabled' => isset($_POST['enabled']),
                'is_default' => isset($_POST['is_default']),
            ];
            $uuid = isset($_POST['connection_uuid']) ? sanitize_text_field(wp_unslash($_POST['connection_uuid'])) : null;
            (new PromaPay_WC_Connection_Repository())->save($input, $uuid !== '' ? $uuid : null);
            self::redirect('saved');
        } catch (Throwable $error) {
            self::redirect('error');
        }
    }

    public static function toggle_connection(): void
    {
        self::authorize();
        $uuid = self::posted_uuid('promapaywc_toggle_connection');
        $repository = new PromaPay_WC_Connection_Repository();
        $connection = $repository->get($uuid);
        if ($connection === null) {
            self::redirect('error');
        }
        $enabled = (int) $connection['enabled'] !== 1;
        self::redirect($repository->set_enabled($uuid, $enabled) ? ($enabled ? 'enabled' : 'disabled') : 'error');
    }

    public static function default_connection(): void
    {
        self::authorize();
        $uuid = self::posted_uuid('promapaywc_default_connection');
        $connection = (new PromaPay_WC_Connection_Repository())->get($uuid);
        if ($connection === null || (int) $connection['enabled'] !== 1) {
            self::redirect('error');
        }
        self::redirect((new PromaPay_WC_Connection_Repository())->set_default($uuid) ? 'default' : 'error');
    }

    public static function test_connection(): void
    {
        self::authorize();
        $uuid = self::posted_uuid('promapaywc_test_connection');
        $repository = new PromaPay_WC_Connection_Repository();
        $connection = $repository->get($uuid);
        if ($connection === null) {
            self::redirect('error');
        }
        $client = new PromaPay_WC_Api_Client($repository);
        $result = (new PromaPay_WC_Policy_Service($repository, $client))->refresh($uuid);
        self::redirect(!empty($result['ok']) ? 'healthy' : 'error');
    }

    private static function posted_uuid(string $action): string
    {
        $uuid = sanitize_text_field(wp_unslash($_POST['connection_uuid'] ?? ''));
        check_admin_referer($action . ':' . $uuid);
        if (!PromaPay_WC_Connection_Repository::valid_uuid($uuid)) {
            self::redirect('error');
        }
        return $uuid;
    }

    private static function authorize(): void
    {
        if (!current_user_can(self::CAPABILITY)) {
            wp_die(esc_html__('You are not allowed to manage Proma Pay connections.', 'proma-pay-woocommerce'), '', ['response' => 403]);
        }
    }

    private static function require_admin_tls(): void
    {
        $localEnvironment = function_exists('wp_get_environment_type') && wp_get_environment_type() === 'local';
        if (function_exists('is_ssl') && !is_ssl() && !$localEnvironment) {
            wp_die(esc_html__('Use HTTPS in WordPress administration before submitting connection credentials.', 'proma-pay-woocommerce'), '', ['response' => 400]);
        }
    }

    private static function redirect(string $notice): void
    {
        wp_safe_redirect(add_query_arg(['page' => self::PAGE, 'promapay_notice' => sanitize_key($notice)], admin_url('admin.php')));
        exit;
    }
}
