# Checkpoints & Resume

Persist compact checkpoints at:
- plan validated
- output generated
- quality validation

Checkpoint contains:
task id, state, selected decisions, affected files, hashes/versions where available, pending gates, retry counts.

Resume rule:
revalidate changed dependencies/context before continuing.
Never resume from a stale execution checkpoint without impact re-analysis.
