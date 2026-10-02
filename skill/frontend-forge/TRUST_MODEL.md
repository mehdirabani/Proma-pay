# Trust Model

Trust levels:
- RUNTIME_TRUSTED — core runtime and provenance verifier.
- PROJECT_TRUSTED — project source selected by operator; dependency code remains untrusted execution.
- PROJECT_UNTRUSTED — project requires full isolation for code execution.
- EXTERNAL_UNTRUSTED — external URLs/artifacts/agent/plugin data.

Production is fail-closed. `unknown`, `unsigned`, `stale`, `unverified`, `missing`
and `untrusted` are never implicit PASS states.
