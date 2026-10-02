from .io_utils import load_json
def mappings():
    return load_json("runtime/event-transition-map.json")["events"]
def resolve_event(event,state):
    found=[m for m in mappings() if m["event"]==event and m["from"]==state]
    if len(found)!=1: raise ValueError(f"event {event!r} from {state!r}: {len(found)} mappings")
    return found[0]
def validate_event_map(event_data=None, registry_data=None):
    sm=load_json("runtime/state-machine.json")
    allowed={tuple(x) for x in sm["transitions"]}
    evs=(event_data or load_json("runtime/event-transition-map.json"))["events"]
    reg=set((registry_data or load_json("runtime/capability-registry.json"))["capabilities"])
    errors=[]
    mapped=set()
    for e in evs:
        t=(e["from"],e["to"]); mapped.add(t)
        if t not in allowed: errors.append(f"illegal transition:{e['event']}:{t}")
        for c in e.get("activates",[]):
            if c not in reg: errors.append(f"unknown capability:{c}")
    missing=sorted(allowed-mapped)
    if missing: errors.append("unmapped transitions:"+repr(missing))
    return {"valid":not errors,"errors":errors}
