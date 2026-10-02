# Api Decision Module

## Activation
Runtime activation comes only from `manifests/module-registry.yaml`; this document does not route itself.

## Core decision guidance
Purpose: decision guidance for api. Activation is registry-driven. Preserve repository conventions, prefer smallest correct change, verify version-sensitive APIs, and complete the module verification checks before Done.

## Failure modes
- `replay_attack`: authenticated or financial message can be accepted more than once
- `webhook_auth`: external callback authenticity or signature must be validated
- `event_ordering`: financial events can arrive late duplicated or out of order
- `woo_gateway`: WooCommerce gateway callback changes financial and order state
- `api_contract`: API endpoint validates request authorization and response/error contract

## Preferred APIs
Use repository-confirmed public APIs and existing project conventions.

## Version-sensitive rules
Never assume latest. Prefer runtime/lock evidence, then manifests, then source evidence.

## Verification checklist
- input_contract_test

## Escalation / cross-domain
Activate related modules from concept metadata and risk policy; high-risk work requires stronger verification and lower autonomy.
