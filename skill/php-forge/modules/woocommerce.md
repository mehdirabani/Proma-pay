# WooCommerce Decision Module

## Activation
Runtime activation comes only from `manifests/module-registry.yaml`; this document does not route itself.

## Core decision guidance
Prefer supported WooCommerce CRUD/order APIs over direct storage assumptions. Check HPOS compatibility, order lifecycle, Blocks where relevant, gateways/callbacks, refunds, stock idempotency, Action Scheduler retry behavior, session semantics and feature declarations. Payment callbacks cross into FinTech + Security + Testing when they mutate financial/order state.

## Failure modes
- `woo_order_state`: WooCommerce order lifecycle or status mutation must use supported CRUD/state APIs
- `woo_hpos`: WooCommerce order storage compatibility must use CRUD APIs and declare HPOS support appropriately
- `woo_gateway`: WooCommerce gateway callback changes financial and order state
- `woo_refund`: WooCommerce refund changes order and financial state
- `woo_stock`: WooCommerce stock changes must remain consistent with order lifecycle and retries
- `woo_blocks`: WooCommerce Blocks checkout integration has distinct extension interfaces and compatibility constraints
- `woo_action_scheduler`: Action Scheduler jobs can retry duplicate and require idempotent handlers

## Preferred APIs
Use repository-confirmed public APIs and existing project conventions.

## Version-sensitive rules
Never assume latest. Prefer runtime/lock evidence, then manifests, then source evidence.

## Verification checklist
- order_state_test
- hpos_compatibility_check

## Escalation / cross-domain
Activate related modules from concept metadata and risk policy; high-risk work requires stronger verification and lower autonomy.
