# Cron and job overlap audit

| Check | Status | Notes |
|---|---|---|
| Outbox execution from normal web requests | PASSED | Worker context is required. |
| Token-protected bounded outbox endpoint | PASSED | Batch limit is 1 to 50. |
| Outbox overlap lock | PASSED | Non-blocking file lock returns controlled 409 with `Retry-After`. |
| Calendar reminder overlap lock | PASSED | Uses a separate non-blocking lock. |
| Lock handle release | PASSED | Handles are released in `finally`. |
| Production cron schedule collision test | BLOCKED | Hosting cron list and execution logs unavailable. |
| Long-running queue soak | NOT EXECUTED | Requires staging worker and realistic queue volume. |

Recommended schedule: invoke each token-protected endpoint at most once per minute and alert on repeated 409, dead outbox rows or a growing pending count.
