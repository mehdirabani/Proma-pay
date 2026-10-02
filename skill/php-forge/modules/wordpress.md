# WordPress Decision Module

## Activation
Runtime activation comes only from `manifests/module-registry.yaml`; this document does not route itself.

## Core decision guidance
Use capability checks for authorization, nonces for request intent rather than authorization, validate/sanitize input and escape at the output context. Respect plugin lifecycle, REST permission callbacks, metadata/options/transients, cron, WP-CLI, multisite, upgrade routines and uninstall behavior. Do not assume current core API compatibility without repository evidence.

## Failure modes
- `wordpress_capability`: WordPress privileged action requires capability authorization separate from nonce
- `wordpress_nonce`: WordPress nonce protects request intent but is not authorization
- `wordpress_output`: WordPress output must be escaped in the output context and input validated appropriately

## Preferred APIs
Use repository-confirmed public APIs and existing project conventions.

## Version-sensitive rules
Never assume latest. Prefer runtime/lock evidence, then manifests, then source evidence.

## Verification checklist
- capability_check
- nonce_when_state_change
- escape_output

## Escalation / cross-domain
Activate related modules from concept metadata and risk policy; high-risk work requires stronger verification and lower autonomy.
