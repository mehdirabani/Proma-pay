def root_lineage(e):
    parents=tuple(sorted(e.get("derivation_parent_ids") or []))
    return (e.get("producer_id"),e.get("source_id"),parents)
def independence(a,b):
    if not a or not b:return "UNKNOWN"
    if a.get("evidence_id")==b.get("evidence_id"):return "CORRELATED"
    if a.get("source_id")==b.get("source_id"):return "CORRELATED"
    pa=set(a.get("derivation_parent_ids") or []);pb=set(b.get("derivation_parent_ids") or [])
    if pa & pb:return "CORRELATED"
    if a.get("producer_id")==b.get("producer_id"):return "PARTIALLY_CORRELATED"
    return "INDEPENDENT"
def independent_groups(items):
    groups=[]
    for e in items:
        placed=False
        for g in groups:
            if any(independence(e,x)=="CORRELATED" for x in g):
                g.append(e);placed=True;break
        if not placed:groups.append([e])
    return groups
