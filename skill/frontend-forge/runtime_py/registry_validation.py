from .io_utils import load_json,import_callable,ROOT
from adapters.registry import REGISTRY
def validate_registry():
    reg=load_json("runtime/capability-registry.json")["capabilities"]
    errors=[]
    for cid,c in reg.items():
        typ=c["type"]
        if typ in {"engine","system"}:
            try:import_callable(c["handler"])
            except Exception as e:errors.append(f"{cid}: handler unavailable: {e}")
        elif typ=="adapter":
            if c.get("adapter") not in REGISTRY:errors.append(f"{cid}: adapter unavailable")
        elif typ=="agent":
            if not (ROOT/c["contract_ref"]).exists():errors.append(f"{cid}: contract_ref missing")
    return {"valid":not errors,"errors":errors,"count":len(reg)}
