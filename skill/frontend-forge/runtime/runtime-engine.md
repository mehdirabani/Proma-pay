# Runtime Engine

Purpose: execute task workflows against the canonical state machine.

Inputs:
- task contract
- context bundle
- execution plan

Outputs:
- state events
- generated artifacts
- validation report
- recovery record when needed

Execution rules:
- emit event for every state change
- persist checkpoints after PLAN_VALIDATED, OUTPUT_GENERATED and QUALITY_VALIDATION
- bound retries per failure type
- do not transition directly from generation to completion
- blocker failures require repair, rollback, or abort

Default retry policy:
- context failure: 1 re-resolution attempt
- transient execution failure: 2 attempts
- quality failure: 2 repair cycles
- agent conflict: 1 arbitration cycle then escalation
- deterministic dependency incompatibility: no blind retry

Failure/recovery matrix is in `runtime/failure-policy.json`.
