from .runtime import RuntimeSession

def execute_scenario(scenario):
    session = RuntimeSession()
    for event in scenario["setup_events"]:
        session.dispatch(event)
    session.dispatch(scenario["inject_event"])
    reached = session.state
    ok = reached == scenario["expected_state"]
    recovery_state = None
    if scenario.get("recovery_events"):
        for event in scenario["recovery_events"]:
            session.dispatch(event)
        recovery_state = session.state
        if scenario.get("expected_recovery_state"):
            ok = ok and recovery_state == scenario["expected_recovery_state"]
    return {
        "id":scenario["id"],
        "ok":ok,
        "failure_state":reached,
        "recovery_state":recovery_state,
        "trace":session.trace
    }
