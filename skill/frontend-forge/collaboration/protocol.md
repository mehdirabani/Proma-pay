# Multi-Agent Collaboration Protocol

Handoff message:
- task_id
- from_agent
- to_agent
- artifact_refs
- decision_summary
- open_risks
- requested_action
- confidence

Negotiation:
1. State proposals.
2. Identify shared hard constraints.
3. Compare evidence, cost, risk and reversibility.
4. Use project-profile weights.
5. If still blocking, invoke arbitrator.
6. Store final decision trace.

No agent may override another agent's explicit release block without arbitration.
