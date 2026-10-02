# Laravel Decision Module

## Activation
Runtime activation comes only from `manifests/module-registry.yaml`; this document does not route itself.

## Core decision guidance
Purpose: decision guidance for laravel. Activation is registry-driven. Preserve repository conventions, prefer smallest correct change, verify version-sensitive APIs, and complete the module verification checks before Done.

## Failure modes
- `laravel_controller`: Laravel controller routing and request handling

## Preferred APIs
Use repository-confirmed public APIs and existing project conventions.

## Version-sensitive rules
Never assume latest. Prefer runtime/lock evidence, then manifests, then source evidence.

## Verification checklist
- framework_version_check

## Escalation / cross-domain
Activate related modules from concept metadata and risk policy; high-risk work requires stronger verification and lower autonomy.
