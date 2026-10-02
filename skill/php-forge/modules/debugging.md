# Debugging Decision Module

## Activation
Runtime activation comes only from `manifests/module-registry.yaml`; this document does not route itself.

## Core decision guidance
Purpose: decision guidance for debugging. Activation is registry-driven. Preserve repository conventions, prefer smallest correct change, verify version-sensitive APIs, and complete the module verification checks before Done.

## Failure modes
- `deadlock`: concurrent transactions deadlock and require ordering retry or isolation reasoning
- `queue_poison`: poison message repeatedly fails and blocks or duplicates downstream work
- `woo_action_scheduler`: Action Scheduler jobs can retry duplicate and require idempotent handlers

## Preferred APIs
Use repository-confirmed public APIs and existing project conventions.

## Version-sensitive rules
Never assume latest. Prefer runtime/lock evidence, then manifests, then source evidence.

## Verification checklist
- reproduction_evidence

## Escalation / cross-domain
Activate related modules from concept metadata and risk policy; high-risk work requires stronger verification and lower autonomy.
