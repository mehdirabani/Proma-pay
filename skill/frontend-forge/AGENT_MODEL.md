# Agent Model

Agents are bounded workers, not personas.

Every agent contract declares:
- mission
- accepted inputs
- emitted outputs
- authority
- tools/capabilities
- activation conditions
- quality gates
- failure policy
- handoff targets

Agents cannot silently expand scope.
Blocking authority is explicit.
Low confidence (<0.60) triggers additional analysis or second-agent review.
