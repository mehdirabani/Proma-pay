# Proma Pay WooCommerce and Bakala QA

**Date:** 2026-10-23  
**Plugin build:** 0.8.0-rc.6  
**Result:** Passed the tested installment checkout flows on a local test merchant.

## Test environment

- Central test service: `http://proma.test` (HTTP 200).
- Separate merchant WordPress site: `http://localhost:8081` (HTTP 200).
- WordPress 7.1.3, WooCommerce 11.2.1, PHP 8.3.33.
- Bakala 10.4.33 with the `bakala-child` child theme active.
- Checkout Blocks page restored as the configured WooCommerce checkout page (page 9); guest checkout enabled.
- SourceGuardian loader enabled only through the isolated PHP configuration used by this local QA site. The protected Bakala theme source was not modified.

## Coverage and results

| Scenario | Result |
| --- | --- |
| Simple product installment quote under Bakala | Passed |
| In-stock variable variation quote | Passed |
| Out-of-stock variation | Correctly showed unavailable state and did not request a quote |
| Classic checkout, cart quantity change, and refreshed installment totals | Passed; stale quote responses were aborted/ignored |
| Checkout Blocks quote route | Passed after the route initialized/restored the WooCommerce cart session |
| Submit without installment consent | Rejected; no WooCommerce order was created |
| Submit with consent using local fake gateway | Passed; parent installment order and test deposit order were created |
| Central order/contract synchronization and idempotent retry | Passed; create calls returned accepted/created responses and retry was idempotent |
| Central polling after deposit | Returned `pending_review`; no guarantee was created before central review |

The test cart used a 10,000,000 IRR product. The local fake gateway recorded a `qa-no-money` transaction only; no bank, card processor, or real funds were involved. The WooCommerce parent order remained `on-hold` while the central request awaited review. This QA run did not approve a contract or create a guarantee.

## Implemented compatibility fixes

- Load storefront assets even when Bakala does not instantiate the gateway during the usual WooCommerce hook.
- Keep variation stock state aligned with quote availability.
- Initialize the WooCommerce cart session in the quote REST route.
- Invalidate cached central policy when connection credentials or base URL change, and retain the guarantee source-policy hash.
- Prevent stale classic-checkout quote responses from replacing the latest selection.
- Update the parent order to `pending_review` after a paid test deposit is synchronized.

## Known QA note

The Bakala cart page emitted one WooCommerce development warning about an inline script reading `wc.wcBlocksData` without declaring the `wc-blocks-data-store` dependency. Cart rendering and quantity AJAX worked; repeated page loads showed no functional JavaScript errors. The warning should be revisited if the theme or WooCommerce Blocks version changes.

## Validation

- Plugin static suite: `PROMAPAY_WC_PHASE7_STATIC_OK`.
- Plugin unit suite: `PROMAPAY_WC_PHASE7_UNIT_OK`.
- PHP syntax check for the commerce integration: passed.
- Node syntax checks for `checkout.js`, `checkout-blocks.js`, and `product-widget.js`: passed.
- Both local HTTP entry points returned 200.

The release archive contains the plugin runtime files only. The local fake-gateway MU plugin, credentials, API tokens, database exports, and protected theme files are excluded. No Git commit or pull request was created because the repository already had unrelated working-tree changes.
