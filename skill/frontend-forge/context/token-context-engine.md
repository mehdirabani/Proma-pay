# Token-Aware Context Engine

Inputs:
task, project graph, changed files, token budget.

Algorithm:
1. Classify candidate context P0–P4.
2. Expand direct dependencies for changed nodes.
3. Rank by relevance × dependency distance × freshness.
4. Deduplicate semantically repeated summaries.
5. Load P0/P1 until minimum sufficiency.
6. Add P2 only when unresolved uncertainty remains.
7. Summarize large low-granularity files.
8. Reject stale cached context when hashes/versions changed.

Budget overflow order:
drop P4 → P3 → stale P2 → summarize P2/P1 → request narrower execution scope.

Protected context:
hard constraints, public interfaces, current changed files, failing tests, relevant architecture decisions.
