# Validation-Driven Learning Engine

Memory levels:
Session → Project → Pattern → Failure → Long-Term Engineering Knowledge

Promotion pipeline:
Observation
→ Candidate Pattern
→ Repeat Detection
→ Validation
→ Confidence
→ Accepted Knowledge

Default promotion guard:
- observations >= 3
- success_rate >= 0.80
- confidence >= 0.75
- no unresolved contradictory blocker evidence

Bad learning:
- success rate falls below threshold → DEPRECATE
- contradictory evidence → REVALIDATE
- obsolete stack/version rule → STALE then REVALIDATE/REMOVE
- project-specific pattern cannot become global without cross-project evidence

Learning records must include source scope and version applicability.
