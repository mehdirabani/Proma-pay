# Architecture Reasoner

Purpose: choose architecture only after generating viable alternatives.

Inputs:
- requirement contract
- project graph
- constraints
- stack profile

Process:
1. Generate 2–4 candidates when architecture choice is material.
2. Reject candidates violating hard constraints.
3. Score remaining candidates on scale, performance, maintainability, SEO, accessibility, deployment and expected change.
4. Run trade-off engine.
5. Emit a selected architecture and confidence.

Output: decision record conforming to `contracts/decision.schema.json`.

Failure: no candidate satisfies hard constraints.
Recovery: relax optional constraints only; never silently relax must-haves.
