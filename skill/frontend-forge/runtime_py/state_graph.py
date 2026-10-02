from collections import deque
from .io_utils import load_json
def validate_graph(machine=None):
    m=machine or load_json("runtime/state-machine.json")
    states=set(m["states"]); terminals=set(m["terminal"]); initial=m["initial"]
    errors=[]; adj={s:[] for s in states}; rev={s:[] for s in states}
    for a,b in map(tuple,m["transitions"]):
        if a not in states or b not in states: errors.append(f"unknown state in {a}->{b}"); continue
        adj[a].append(b); rev[b].append(a)
    seen=set(); q=deque([initial])
    while q:
        s=q.popleft()
        if s in seen: continue
        seen.add(s); q.extend(adj[s])
    unreachable=sorted(states-seen)
    if unreachable: errors.append("unreachable: "+",".join(unreachable))
    dead=sorted(s for s in states-terminals if not adj[s])
    if dead: errors.append("dead_end: "+",".join(dead))
    can=set(terminals); q=deque(terminals)
    while q:
        s=q.popleft()
        for p in rev[s]:
            if p not in can: can.add(p); q.append(p)
    stranded=sorted(seen-can)
    if stranded: errors.append("no_terminal_path: "+",".join(stranded))
    for f in m.get("failure_states",[]):
        if not any(x in {"REPAIR","ABORT_REQUESTED","PLAN_VALIDATED"} for x in adj[f]):
            errors.append(f"failure_no_recovery:{f}")
    return {"valid":not errors,"errors":errors,"unreachable":unreachable,"reachable":sorted(seen)}
