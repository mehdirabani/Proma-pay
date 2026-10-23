=== Proma Pay for WooCommerce ===
Contributors: proma-pay
Tags: installments, payments, woocommerce
Requires at least: 7.1
Tested up to: 7.1
Requires PHP: 8.3
Stable tag: 0.8.0-rc.1
License: GPL-2.0-or-later

Connect WooCommerce to multiple test-environment Proma Pay installment services. The plugin provides a WooCommerce gateway, live server-calculated quotes, a separate deposit order routed to the merchant's selected online bank gateway, and pending contract/guarantee synchronization.

== Description ==

Configure multiple test-environment Proma Pay connections, choose a default, prioritize and disable connections, test the API connection, refresh the published financial policy, and inspect minimized communication logs. The installment gateway supports classic checkout and the Checkout Blocks payment-method API. It uses WooCommerce order CRUD and declares HPOS compatibility. The product estimate can be disabled in gateway settings, and products/variations can be excluded individually.

API credentials and cached financial policy snapshots are encrypted at rest with AES-256-GCM and a key derived from the WordPress authentication salt. Logs do not store request or response bodies, customer details, or credentials. API requests require HTTPS, redirects are disabled, and WordPress safe HTTP URL validation is enabled.

This release accepts test credentials only. Financial and guarantee rules come from the API. Quotes are persisted as tenant-scoped, expiring snapshots and are consumed once by external order creation. A paid deposit creates or synchronizes a pending API order and contract request; the main WooCommerce order remains unpaid and on hold pending central review. Refund support is limited to a full deposit refund through the selected bank gateway after the pending API request is cancelled.

== Installation ==

1. Upload the `proma-pay-woocommerce` folder to `/wp-content/plugins/` or install its ZIP in WordPress.
2. Activate the plugin.
3. Open Proma Pay in the WordPress administration menu.
4. Add the approved API base URL and a `pmp_test_` API token issued by the Proma Pay administrator with `merchant.read` and `policy:read` scopes.
5. Use “Test and refresh policy” to verify the connection and fetch its published financial policy.
6. In WooCommerce payment settings, enable Proma Pay, choose whether to show product estimates, and configure an eligible online deposit gateway for each service connection.

== Security and compatibility ==

Activation, additive schema creation, connection persistence, encrypted credential storage, and capability setup were tested with WordPress 7.1.3, PHP 8.3.33, and MySQL 8.4.3. Checkout order creation and deposit delegation were exercised with WooCommerce 11.2.1 in both legacy order storage and HPOS. A full browser-driven Store API payment with a real bank, production callbacks, and refund settlement has not been run. Older WordPress/PHP/WooCommerce versions and WordPress Multisite have not been tested.

Install the central server's `database/migrations/2026_10_23_checkout_quote_snapshot_phase7.sql` migration before enabling API checkout. This release only accepts `pmp_test_` credentials and must not be treated as production payment software. Legal approval, Sayadi verification, real bank settlement, and production credentials are not included.

If the WordPress authentication salts change, stored encrypted credentials and policy snapshots can no longer be decrypted. Re-enter the test credential and refresh the policy. Keep the WordPress salts stable and protect backups of `wp-config.php` accordingly.

== Changelog ==

= 0.8.0-rc.1 =
* Add Classic Checkout and Checkout Blocks installment gateway, live quote validation, merchant bank deposit child orders, and HPOS CRUD support.
* Persist and consume tenant-scoped financial quote snapshots so order creation cannot silently recalculate different terms.
* Keep the parent order unpaid until contract and guarantee review; restrict refunds to full deposit cancellation and refund.

= 0.7.0 =
* Add the central API quote/order/contract checkout flow and first guarantee schedule disclosures.
* Accept test credentials only; online payment processing remains unavailable outside the QA/test lifecycle.

= 0.6.0 =
* Add encrypted multi-connection storage, API connection checks, financial policy cache, and minimized communication logs.
* Accept test credentials only; live processing is not enabled.
