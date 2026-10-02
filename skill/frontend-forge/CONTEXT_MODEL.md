# Context Model

Priority:
- P0 Required
- P1 Highly relevant
- P2 Helpful
- P3 Optional
- P4 Ignore

Budget strategy:
1. Resolve task complexity.
2. Reserve budget for generation and review.
3. Rank candidate context.
4. Load P0/P1.
5. Summarize oversized files.
6. Add P2 only when uncertainty remains.
7. Drop stale or duplicate context first.

Never reload unchanged project context when a valid cached summary and dependency map are available.
