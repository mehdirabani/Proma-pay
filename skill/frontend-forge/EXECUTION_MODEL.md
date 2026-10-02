# Execution Model — v13.1

V13.1 makes state/event behavior executable through `runtime_py/runtime.py`.

The canonical state graph is validated for:
- reachability from initial state
- dead states
- nonterminal dead-ends
- terminal reachability
- failure-state recovery/abort paths
- transition/event consistency

Events are not a parallel system anymore.
`runtime/event-transition-map.json` is the bridge between event names and state transitions.

The reference runtime accepts events, verifies the transition, records activated
capabilities, and appends an audit trace.
