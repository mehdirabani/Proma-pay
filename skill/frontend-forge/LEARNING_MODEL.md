# Learning Model

Observation → Candidate Pattern → Repeat Detection → Validation → Confidence → Accepted Knowledge

Rules:
- one observation never becomes a global rule
- accepted rules retain evidence count and success rate
- contradictory evidence reduces confidence
- stale rules are revalidated
- rules below configured success/confidence thresholds are deprecated or removed
- project-local knowledge is not promoted to global engineering knowledge without cross-context validation
