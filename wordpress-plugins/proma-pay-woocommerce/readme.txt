=== Proma Pay for WooCommerce ===
Contributors: proma-pay
Tags: installments, payments, woocommerce
Requires at least: 7.1
Tested up to: 7.1
Requires PHP: 8.3
Stable tag: 0.6.0
License: GPL-2.0-or-later

Connect a WordPress site to approved Proma Pay installment services. This foundation release contains site-level connection management and does not register a WooCommerce payment method.

== Description ==

Configure multiple test-environment Proma Pay connections, choose a default, prioritize and disable connections, test the API connection, refresh the published financial policy, and inspect minimized communication logs.

API credentials and cached financial policy snapshots are encrypted at rest with AES-256-GCM and a key derived from the WordPress authentication salt. Logs do not store request or response bodies, customer details, or credentials. API requests require HTTPS, redirects are disabled, and WordPress safe HTTP URL validation is enabled.

This release accepts test credentials only. Quotes, orders, contracts, guarantees, customer checkout, and live payment processing are not activated by this foundation release. Financial rules are read-only and are supplied by the Proma Pay API.

== Installation ==

1. Upload the `proma-pay-woocommerce` folder to `/wp-content/plugins/` or install its ZIP in WordPress.
2. Activate the plugin.
3. Open Proma Pay in the WordPress administration menu.
4. Add the approved API base URL and a `pmp_test_` API token issued by the Proma Pay administrator with `merchant.read` and `policy:read` scopes.
5. Use “Test and refresh policy” to verify the connection and fetch its published financial policy.

== Security and compatibility ==

Activation, additive schema creation, connection persistence, encrypted credential storage, and capability setup were tested with WordPress 7.1.3, PHP 8.3.33, and MySQL 8.4.3. Older WordPress/PHP versions and WordPress Multisite have not been tested. WooCommerce is not required by this foundation release and no checkout compatibility is claimed.

If the WordPress authentication salts change, stored encrypted credentials and policy snapshots can no longer be decrypted. Re-enter the test credential and refresh the policy. Keep the WordPress salts stable and protect backups of `wp-config.php` accordingly.

== Changelog ==

= 0.6.0 =
* Add encrypted multi-connection storage, API connection checks, financial policy cache, and minimized communication logs.
* Accept test credentials only; live processing is not enabled.
