# Database Decision Module

## Activation
Runtime activation comes only from `manifests/module-registry.yaml`; this document does not route itself.

## Core decision guidance
For large backfills evaluate batching, lock duration, index impact, replication lag, transaction size, resumability, backpressure, deploy compatibility, rollback feasibility and dual-read/write only when justified. For concurrency defects identify isolation level and invariant before changing SQL.

## Failure modes
- `sql_injection`: untrusted input changes SQL command structure
- `large_backfill`: large data backfill requires batching resume strategy lock awareness and deployment compatibility
- `deadlock`: concurrent transactions deadlock and require ordering retry or isolation reasoning
- `phantom_read`: transaction observes new matching rows due isolation behavior
- `stale_write`: write overwrites newer state because concurrency version was not checked
- `n_plus_one`: repeated per-record queries cause avoidable database amplification

## Preferred APIs
Use repository-confirmed public APIs and existing project conventions.

## Version-sensitive rules
Never assume latest. Prefer runtime/lock evidence, then manifests, then source evidence.

## Verification checklist
- migration_rollback_review

## Escalation / cross-domain
Activate related modules from concept metadata and risk policy; high-risk work requires stronger verification and lower autonomy.
