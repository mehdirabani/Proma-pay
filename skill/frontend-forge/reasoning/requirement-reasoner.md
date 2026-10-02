# Requirement Reasoner

Purpose: convert user intent into a compact task contract.

Inputs:
- user request
- project profile
- available context

Outputs:
- explicit requirements
- inferred requirements (marked as inferred)
- constraints
- must-have vs optional
- uncertainties
- confidence

Activation: every non-trivial task.

Decision rules:
- never invent a missing business constraint
- if uncertainty materially changes architecture, request/derive evidence before irreversible work
- prefer safe reversible defaults when clarification is unavailable

Failure: conflicting must-have requirements.
Recovery: emit conflict record and invoke constraint engine.

Quality gate: every must-have requirement maps to a validation criterion.
