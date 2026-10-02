POLICIES={
"TOOL_TIMEOUT":{"max_attempts":2,"backoff":"exponential","fallback":"secondary_adapter"},
"EXECUTION_FAILURE":{"max_attempts":2,"backoff":"linear","fallback":"rollback"},
"QUALITY_GATE_FAILURE":{"max_attempts":3,"backoff":"none","fallback":"escalate"},
"REGRESSION_DETECTED":{"max_attempts":2,"backoff":"none","fallback":"rollback"},
}
def handle_failure(ctx):
    failure=ctx["failure"]; attempt=int(ctx.get("attempt",1)); p=POLICIES.get(failure,{"max_attempts":0,"fallback":"abort"})
    if attempt<=p["max_attempts"]: action="RETRY"
    else: action=p["fallback"].upper()
    return {"recovery_action":{"failure":failure,"attempt":attempt,"action":action,"policy":p}}
