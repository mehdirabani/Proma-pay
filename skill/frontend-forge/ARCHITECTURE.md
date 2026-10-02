# V14 Architecture

The runtime has six executable layers:

1. Runtime core — session, state/events, guards, scheduler, capability executor.
2. Integration — adapters, browser runtime, agent hosts and CI.
3. Persistence — SQLite sessions/events/checkpoints/artifacts/evidence/metrics.
4. Safety — command, path, environment, sandbox and secret policies.
5. Quality — evidence normalization, project-weighted gates, regression and visual comparison.
6. Reliability — failure manager, retry, rollback, resume, cancellation and audit trace.

Markdown files document the system but are not counted as runtime capabilities unless a consumer references them.
