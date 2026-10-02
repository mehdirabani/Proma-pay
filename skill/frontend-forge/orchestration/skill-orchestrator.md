# Skill Orchestration Engine

Selection inputs:
- required capabilities
- project type
- stack
- compatibility
- benchmark evidence
- dependency constraints
- token budget

Algorithm:
1. Map requirements to capabilities.
2. Select minimum skill set covering required capabilities.
3. Resolve versions/dependencies.
4. Reject incompatible/conflicting skills.
5. Rank equivalent skills by relevant benchmark evidence, not overall popularity.
6. Load only selected skill instructions.
7. Record why each skill was activated.

Conflict policy:
hard project constraints > safety/security > explicit user requirements > profile quality goals > optional preferences.
