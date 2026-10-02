# Weighted Scoring

For a project profile:
score = Σ(metric_score × profile_weight)

Rules:
- do not calculate a metric when no measurement evidence exists; mark `not_measured`
- missing evidence is not equivalent to a passing score
- any failed blocker prevents release regardless of weighted average
- compare current metrics to baseline and report delta
- never claim 95+ production readiness solely from package structure
