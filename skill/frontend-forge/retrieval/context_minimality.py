EVIDENCE_KEYS=('target','style','type','behavior','test','route','consumer','a11y','performance')
def evidence_types(candidate):
    types=set()
    reason=str(candidate.get('reason',''))
    if candidate.get('graph_distance',9)==0:types.add('target')
    if 'style' in reason:types.add('style')
    if 'test' in reason:types.add('test')
    if 'route' in reason:types.add('route')
    if 'reverse_import' in reason or 'consumer' in reason:types.add('consumer')
    if 'import' in reason:types.add('behavior')
    for x in candidate.get('selected_because',[]):types.add(x)
    return types

def minimum_evidence_set(candidates,critical_paths=()):
    critical=set(critical_paths);remaining=list(candidates);selected=[];covered=set();all_ev=set().union(*(evidence_types(x) for x in remaining)) if remaining else set()
    while remaining:
        best=None;best_gain=-1
        for c in remaining:
            ev=evidence_types(c);gain=len(ev-covered)+(5 if c.get('path') in critical else 0)+(3 if c.get('graph_distance')==0 else 0)
            if gain>best_gain:best,best_gain=c,gain
        if best is None or best_gain<=0:break
        selected.append(best);covered|=evidence_types(best);remaining.remove(best)
        if critical.issubset({x.get('path') for x in selected}) and all_ev.issubset(covered):break
    kept={x.get('path') for x in selected};removed=[x for x in candidates if x.get('path') not in kept]
    return {'selected':selected,'removed':removed,'zero_marginal_value_ratio':round(len(removed)/max(1,len(candidates)),4),'covered_evidence':sorted(covered)}
