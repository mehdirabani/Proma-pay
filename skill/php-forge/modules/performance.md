# Performance Decision Module

## Activation
Runtime activation comes only from `manifests/module-registry.yaml`; this document does not route itself.

## Core decision guidance
Purpose: decision guidance for performance. Activation is registry-driven. Preserve repository conventions, prefer smallest correct change, verify version-sensitive APIs, and complete the module verification checks before Done.

## Failure modes
- `large_backfill`: large data backfill requires batching resume strategy lock awareness and deployment compatibility
- `n_plus_one`: repeated per-record queries cause avoidable database amplification
- `memory_pressure`: operation exhausts memory or retains large object sets
- `queue_poison`: poison message repeatedly fails and blocks or duplicates downstream work

## Preferred APIs
Use repository-confirmed public APIs and existing project conventions.

## Version-sensitive rules
Never assume latest. Prefer runtime/lock evidence, then manifests, then source evidence.

## Verification checklist
- before_after_measurement

## Escalation / cross-domain
Activate related modules from concept metadata and risk policy; high-risk work requires stronger verification and lower autonomy.
