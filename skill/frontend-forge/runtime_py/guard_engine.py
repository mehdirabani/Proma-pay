from .io_utils import load_json
class GuardFailure(RuntimeError):
    def __init__(self, guard_id, failure_state, reason):
        super().__init__(f"{guard_id}: {reason}")
        self.guard_id=guard_id; self.failure_state=failure_state; self.reason=reason

def evaluate_guard(guard_id, context):
    reg=load_json("runtime/guard-registry.json")
    g=reg[guard_id]
    typ=g["type"]
    ok=False; reason=""
    if typ=="evidence_present":
        missing=[x for x in g["required_evidence"] if not context.get(x)]
        ok=not missing; reason=f"missing evidence: {missing}" if missing else ""
    elif typ=="boolean_flag":
        ok=context.get(g["field"]) is True; reason=f"{g['field']} is not true"
    elif typ=="risk_policy":
        risk=context.get("risk","LOW")
        ok=risk not in {"HIGH","CRITICAL"} or context.get("approval_granted") is True or context.get("auto_policy")=="ALLOW_HIGH_RISK"
        reason=f"risk {risk} requires approval"
    elif typ=="risk_requires_approval":
        ok=context.get("risk") in {"HIGH","CRITICAL"} and context.get("approval_required") is True
        reason="transition only valid for high/critical risk requiring approval"
    elif typ=="quality_evidence":
        ok=context.get("quality_evidence_complete") is True
        reason="mandatory quality evidence incomplete"
    elif typ=="learning_record":
        ok=bool(context.get("learning_record") or context.get("learning_skipped_reason"))
        reason="learning record or skip reason missing"
    else:
        reason=f"unknown guard type {typ}"
    if not ok:
        raise GuardFailure(guard_id,g["failure_state"],reason)
    return True

def evaluate_transition_guards(from_state,to_state,context):
    sm=load_json("runtime/state-machine.json")
    ids=sm.get("guards",{}).get(f"{from_state}->{to_state}",[])
    for gid in ids:
        evaluate_guard(gid,context)
    return ids
