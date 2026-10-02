def choose_agents(candidates,complexity,risk='LOW'):
    max_agents={'TRIVIAL':1,'SMALL':1,'MEDIUM':2,'LARGE':4,'REPOSITORY_WIDE':5}[complexity]
    scored=[]
    for a in candidates:
        gain=float(a.get('expected_quality_gain',0));cost=max(1,float(a.get('expected_token_cost',1)));r=float(a.get('risk_reduction',0))
        scored.append((gain+r*1.5, (gain+r)/cost, a))
    scored.sort(key=lambda x:(-x[0],-x[1]))
    return [x[2] for x in scored[:max_agents] if x[0]>0]
def handoff(decision,artifact_refs,critical_findings,open_questions):
    return {'decision':decision,'artifact_references':artifact_refs,'critical_findings':critical_findings,'open_questions':open_questions}
